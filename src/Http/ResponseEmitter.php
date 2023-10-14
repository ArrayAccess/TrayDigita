<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Http;

use ArrayAccess\TrayDigita\Http\Exceptions\HttpRuntimeException;
use ArrayAccess\TrayDigita\Http\Exceptions\OutPutBufferingException;
use ArrayAccess\TrayDigita\Http\Factory\StreamFactory;
use ArrayAccess\TrayDigita\Http\Interfaces\ResponseEmitterInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use function connection_status;
use function fastcgi_finish_request;
use function flush;
use function function_exists;
use function header;
use function headers_sent;
use function in_array;
use function ob_end_clean;
use function ob_end_flush;
use function ob_get_contents;
use function ob_get_length;
use function ob_get_level;
use function ob_get_status;
use function ob_start;
use function preg_match;
use function rtrim;
use function sprintf;
use function str_replace;
use function strlen;
use function substr;
use function ucwords;
use function vsprintf;
use const CONNECTION_NORMAL;
use const PHP_OUTPUT_HANDLER_FLUSHABLE;
use const PHP_OUTPUT_HANDLER_REMOVABLE;
use const PHP_SAPI;

class ResponseEmitter implements ResponseEmitterInterface
{
    private const CONTENT_PATTERN_REGEX = '/(?P<unit>[\w]+)\s+(?P<first>\d+)-(?P<last>\d+)\/(?P<length>\d+|\*)/';

    /**
     * Maximum output buffering size for each iteration.
     */
    protected int $maxBufferLength = 8192;

    /**
     * @var bool
     */
    private bool $emitted = false;

    private int $emitCount = 0;

    private bool $closed = false;

    private ?StreamInterface $previousOutput = null;

    protected function assertNoPreviousOutput(): void
    {
        $file = $line = null;
        if (headers_sent($file, $line)) {
            throw new HttpRuntimeException(sprintf(
                'Unable to emit response: Headers already sent in file %s on line %s. '
                . 'This happens if echo, print, printf, print_r, var_dump, var_export or '
                . 'similar statement that writes to the output buffer are used.',
                $file,
                $line
            ));
        }

        if (ob_get_level() <= 0) {
            return;
        }

        if (ob_get_length() <= 0) {
            return;
        }

        $this->previousOutput = (new StreamFactory())->createStream(ob_get_contents());
        throw new OutPutBufferingException(
            $this->previousOutput,
            'Output has been emitted previously; cannot emit response.'
        );
    }

    public function getPreviousOutput(): ?StreamInterface
    {
        return $this->previousOutput;
    }

    /**
     * Emit the status line.
     *
     * Emits the status line using the protocol version and status code from
     * the response; if a reason phrase is available, it, too, is emitted.
     *
     * It's important to mention that, in order to prevent PHP from changing
     * the status code of the emitted response, this method should be called
     * after `emitBody()`
     */
    protected function emitStatusLine(ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();
        if (headers_sent()) {
            return;
        }
        header(
            vsprintf(
                'HTTP/%s %d%s',
                [
                    $response->getProtocolVersion(),
                    $statusCode,
                    rtrim(' ' . $response->getReasonPhrase()),
                ]
            ),
            true,
            $statusCode
        );
    }

    /**
     * Emit response headers.
     *
     * Loops through each header, emitting each; if the header value
     * is an array with multiple values, ensures that each is sent
     * in such a way as to create aggregate headers (instead of replace
     * the previous).
     */
    protected function emitHeaders(ResponseInterface $response): void
    {
        if (headers_sent()) {
            return;
        }
        $statusCode = $response->getStatusCode();
        foreach ($response->getHeaders() as $header => $values) {
            $name = $this->toWordCase($header);
            $first = $name !== 'Set-Cookie';

            foreach ($values as $value) {
                header(
                    sprintf(
                        '%s: %s',
                        $name,
                        $value
                    ),
                    $first,
                    $statusCode
                );

                $first = false;
            }
        }
    }

    /**
     * Converts header names to word-case.
     */
    protected function toWordCase(string $header): string
    {
        $filtered = str_replace('-', ' ', $header);
        $filtered = ucwords($filtered);

        return str_replace(' ', '-', $filtered);
    }

    public function emit(
        ResponseInterface $response,
        bool $reduceError = false,
        bool $sendPreviousBuffer = true
    ): void {
        $this->emitCount++;

        $this->emitted = true;
        $cleaned = false;
        if (!$reduceError) {
            $this->assertNoPreviousOutput();
        } elseif (ob_get_length() > 0) {
            $c = 10;
            while ($c-- > 0 && ob_get_length() > 0 && ob_get_level() > 0) {
                $cleaned = true;
                if ($sendPreviousBuffer) {
                    $this->previousOutput ??= (new StreamFactory())->createStream();
                    $this->previousOutput->write(ob_get_contents());
                }
                ob_end_clean();
            }
        }

        $this->emitStatusLine($response);

        $this->emitHeaders($response);

        flush();

        $cleaned === true && ob_start();
        $range = $this->parseContentRange($response->getHeaderLine('Content-Range'));

        if ($sendPreviousBuffer && $this->previousOutput) {
            $this->emitBody($this->previousOutput, $this->maxBufferLength);
        }

        if ($range !== null && $range[0] === 'bytes') {
            $this->emitBodyRange($range, $response, $this->maxBufferLength);
        } else {
            $this->emitBody($response, $this->maxBufferLength);
        }
    }

    /**
     * Parse content-range header
     * http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.16.
     *
     * @psalm-return null|array{0: string, 1: int, 2: int, 3: string|int}
     *     returns null if no content range or an invalid content range is provided
     */
    private function parseContentRange(string $header): ?array
    {
        if (preg_match(self::CONTENT_PATTERN_REGEX, $header, $matches) === 1) {
            return [
                $matches['unit'],
                (int) $matches['first'],
                (int) $matches['last'],
                $matches['length'] === '*' ? '*' : (int) $matches['length'],
            ];
        }

        return null;
    }

    /**
     * Emit a range of the message body.
     *
     * @psalm-param array{0: string, 1: int, 2: int, 3: string|int} $range
     */
    private function emitBodyRange(array $range, ResponseInterface $response, int $maxBufferLength): void
    {
        [/* $unit */, $first, $last, /* $length */] = $range;

        $body = $response->getBody();

        $length = $last - $first + 1;

        if ($body->isSeekable()) {
            $body->seek($first);
            $first = 0;
        }

        if (! $body->isReadable()) {
            echo substr($body->getContents(), $first, $length);

            return;
        }

        $remaining = $length;

        while ($remaining >= $maxBufferLength && ! $body->eof()) {
            $contents = $body->read($maxBufferLength);
            $remaining -= strlen($contents);

            echo $contents;

            if (connection_status() !== CONNECTION_NORMAL) {
                break;
            }
        }

        if ($remaining <= 0) {
            return;
        }

        if ($body->eof()) {
            return;
        }

        echo $body->read($remaining);
    }

    /**
     * Sends the message body of the response.
     */
    private function emitBody(
        ResponseInterface|StreamInterface $responseOrStream,
        int $maxBufferLength
    ): void {
        $body = $responseOrStream instanceof StreamInterface
            ? $responseOrStream
            : $responseOrStream->getBody();

        if ($body->isSeekable()) {
            $body->rewind();
        }

        if (! $body->isReadable()) {
            echo $body;

            return;
        }

        while (! $body->eof()) {
            echo $body->read($maxBufferLength);

            if (connection_status() !== CONNECTION_NORMAL) {
                break;
            }
        }
    }

    public function getEmitCount(): int
    {
        return $this->emitCount;
    }

    public function emitted(): bool
    {
        return $this->emitted;
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    public function close() : void
    {
        if ($this->isClosed()) {
            return;
        }
        $this->closed = true;
        if (! in_array(PHP_SAPI, ['cli', 'phpdbg'], true)) {
            $status = ob_get_status(true);
            $level = count($status);
            $flags = PHP_OUTPUT_HANDLER_REMOVABLE | PHP_OUTPUT_HANDLER_FLUSHABLE;
            $maxBufferLevel = 0;
            while ($level-- > $maxBufferLevel
                && isset($status[$level])
                && ($status[$level]['del']
                    ??(! isset($status[$level]['flags']) || $flags === ($status[$level]['flags'] & $flags))
                )
            ) {
                ob_end_flush();
            }
        }

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }
}
