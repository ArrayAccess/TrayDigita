<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Responder;

use ArrayAccess\TrayDigita\Http\Exceptions\HttpRuntimeException;
use ArrayAccess\TrayDigita\Http\RequestResponseExceptions\MethodNotAllowedException;
use ArrayAccess\TrayDigita\Http\ServerRequest;
use ArrayAccess\TrayDigita\Kernel\Decorator;
use ArrayAccess\TrayDigita\Responder\Interfaces\FileResponderInterface;
use ArrayAccess\TrayDigita\Uploader\Exceptions\SourceFileFailException;
use ArrayAccess\TrayDigita\Util\Filter\MimeType;
use ArrayAccess\TrayDigita\Util\Generator\RandomString;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use SplFileInfo;
use SplFileObject;
use function array_filter;
use function array_map;
use function array_shift;
use function array_unique;
use function array_values;
use function connection_status;
use function explode;
use function fastcgi_finish_request;
use function function_exists;
use function gmdate;
use function header;
use function header_remove;
use function headers_sent;
use function implode;
use function in_array;
use function is_array;
use function is_numeric;
use function is_string;
use function ksort;
use function md5;
use function ob_end_clean;
use function ob_get_level;
use function preg_match;
use function reset;
use function sprintf;
use function str_contains;
use function str_replace;
use function strtoupper;
use function ucwords;
use const CONNECTION_NORMAL;
use const LOCK_NB;
use const LOCK_SH;
use const LOCK_UN;

class FileResponder implements FileResponderInterface
{
    protected SplFileInfo $file;

    public const DEFAULT_MIMETYPE = 'application/octet-stream';

    protected string $attachmentFileName;

    protected int $size;

    protected bool $sendLastModifiedTime = true;

    protected bool $sendAsAttachment = false;

    protected bool $sendRealMimeType = true;

    protected bool $sendContentLength = true;

    protected bool $allowRange = true;

    protected int $maxRanges = 100;

    protected ?string $boundary = null;

    private ?string $eTag = null;

    private array $headerSent = [];

    /**
     * Default allowed method
     */
    public const ALLOWED_METHODS = [
        'OPTIONS',
        'HEAD',
        'POST',
        'GET'
    ];

    public function __construct(SplFileInfo|string $file)
    {
        if (is_string($file)) {
            $file = new SplFileInfo($file);
        }
        $this->file = $file;
        $this->attachmentFileName = $this->file->getBasename();
        $this->size = $this->valid() ? ($this->file->getSize()?:0) : 0;
    }

    public function getFile(): SplFileInfo
    {
        return $this->file;
    }

    public function valid(): bool
    {
        return $this->file->isFile() && $this->file->isReadable();
    }

    public function setAllowRange(bool $enable): void
    {
        $this->allowRange = $enable;
    }

    public function isAllowRange(): bool
    {
        return $this->allowRange;
    }

    public function sendLastModifiedTime(bool $enable): void
    {
        $this->sendLastModifiedTime = $enable;
    }

    public function isSendLastModifiedTime() : bool
    {
        return $this->sendLastModifiedTime;
    }

    public function sendAsAttachment(bool $enable): void
    {
        $this->sendAsAttachment = $enable;
    }

    public function isSendAsAttachment(): bool
    {
        return $this->sendAsAttachment;
    }

    public function isSendContentLength(): bool
    {
        return $this->sendContentLength;
    }

    public function sendContentLength(bool $enable): void
    {
        $this->sendContentLength = $enable;
    }

    public function setAttachmentFileName(string $fileName): void
    {
        $this->attachmentFileName = $fileName;
    }

    public function getAttachmentFileName(): string
    {
        return $this->attachmentFileName;
    }

    public function resetFileName(): void
    {
        $this->attachmentFileName = $this->getFile()->getBasename();
    }

    public function sendRealMimeType(bool $enable): void
    {
        $this->sendRealMimeType = $enable;
    }

    public function isSendRealMimeType(): bool
    {
        return $this->sendRealMimeType;
    }

    public function setMaxRanges(int $ranges): void
    {
        if ($ranges < 0) {
            $ranges = 0;
        }
        $this->maxRanges = $ranges;
    }

    public function getMaxRanges(): int
    {
        return $this->maxRanges;
    }

    public function getBoundary(): string
    {
        return $this->boundary ??= md5(RandomString::bytes(16));
    }

    /**
     * @return ?string
     */
    public function getEtag(): ?string
    {
        if (!$this->valid()) {
            return null;
        }
        if ($this->eTag) {
            return $this->eTag;
        }
        /**
         * @link http://lxr.nginx.org/ident?_i=ngx_http_set_etag
         */
        $time = $this->file->getMTime();
        $size = $this->size;
        // hexadecimal using hex: modification time & hex: size
        return $this->eTag = sprintf('%x-%x', $time, $size);
    }

    /**
     * @param string $name
     * @param string|int|float $value
     * @param int $code
     * @return bool
     */
    private function sendHeader(
        string $name,
        string|int|float $value,
        int $code = 0
    ): bool {
        if (headers_sent()) {
            return false;
        }
        $name = trim($name);
        if ($name === '') {
            return false;
        }
        $name = ucwords(str_replace(' ', '-', strtolower($name)), '-');
        $value  = is_string($value) ? trim($value) : $value;
        $header = $value ? "$name: $value" : $name;
        if (isset($this->headerSent[$name])) {
            return false;
        }
        $this->headerSent[$name] = $header;
        header($header, true, $code);
        return true;
    }

    public function sendHeaderLastModified(): bool
    {
        if (!$this->isSendLastModifiedTime()) {
            return false;
        }

        return $this->sendHeader(
            'Last-Modified',
            gmdate('Y-m-d H:i:s \G\M\T')
        );
    }

    /**
     * @param string|array|null $cacheType
     * @param int|null $maxAge
     * @return bool
     */
    public function sendHeaderCache(
        string|array|null $cacheType = null,
        ?int $maxAge = null
    ) : bool {
        $data = [];
        if ($cacheType) {
            $cacheType = !is_array($cacheType) ? [$cacheType] : $cacheType;
            $cacheType = array_filter($cacheType, 'is_string');
            if (!empty($cacheType)) {
                $cacheType = array_unique(array_map('strtolower', $cacheType));
                $data = array_values($cacheType);
            }
        }
        if ($maxAge) {
            $data[] = sprintf('max-age=%d', $maxAge);
        }
        return $this->sendHeader('Cache-Control', implode(', ', $data));
    }

    /**
     * Send accept ranges
     *
     * @return bool
     */
    public function sendHeaderAcceptRanges(): bool
    {
        return $this->sendHeader(
            'Accept-Ranges',
            $this->isAllowRange() || $this->getMaxRanges() < 1
                ? 'bytes'
                : 'none'
        );
    }

    public function sendHeaderContentLength(int $length): bool
    {
        if (!$this->isSendContentLength()) {
            return false;
        }
        return $this->sendHeader('Content-Length', $length);
    }

    public function sendHeaderEtag(): bool
    {
        $etag = $this->getEtag();
        return $etag && $this->sendHeader('Etag', $etag);
    }

    public function sendHeaderContentType($contentType, int $code = 0): bool
    {
        return $this->sendHeader('Content-Type', $contentType, $code);
    }

    public function getDetermineMimeType(): ?string
    {
        if ($this->isSendRealMimeType()) {
            $mimeType = MimeType::fileMimeType($this->file->getRealPath());
        } else {
            $mimeType = MimeType::mime($this->file->getExtension());
        }
        return $mimeType;
    }

    public function sendHeaderMimeType(): bool
    {
        $mimeType = $this->getDetermineMimeType();
        return $mimeType && $this->sendHeader('Content-Type', $mimeType);
    }

    /**
     * @return bool
     */
    public function sendHeaderAttachment() : bool
    {
        if (!$this->isSendAsAttachment()) {
            return false;
        }
        return $this->sendHeader(
            'Content-Disposition',
            sprintf(
                'attachment; filename="%s"',
                rawurlencode($this->getAttachmentFileName())
            )
        );
    }

    public function displayRangeNotSatisfy() : never
    {
        $this->sendHeaderContentType('text/html', 416);
        $this->sendHeader('Content-Range', 'bytes */'.$this->size);
        $this->stopRequest();
    }

    public function send(?ServerRequestInterface $request = null): never
    {
        $request ??= ServerRequest::fromGlobals(
            Decorator::service(ServerRequestFactoryInterface::class),
            Decorator::service(StreamFactoryInterface::class),
        );
        $method = strtoupper($request->getMethod());
        if (!in_array($method, self::ALLOWED_METHODS)) {
            $exceptions = new MethodNotAllowedException(
                $request
            );
            $exceptions->setAllowedMethods(self::ALLOWED_METHODS);
            throw $exceptions;
        }
        if (!$this->valid()) {
            throw new SourceFileFailException(
                $this->file->getPathname(),
                sprintf(
                    'File %s is not valid',
                    $this->file
                )
            );
        }
        // remove all buffer
        $count = 5;
        while (--$count > 0 && ob_get_level() > 0) {
            ob_end_clean();
        }
        if (headers_sent()) {
            throw new HttpRuntimeException(
                'Header already sent'
            );
        }
        $this->sendRequestData($request);
    }

    private function sendRequestData(ServerRequestInterface $request) : never
    {
        // remove x-powered-by php
        header_remove('X-Powered-By');
        $method = strtoupper($request->getMethod());
        if ($method === 'OPTIONS') {
            $this->sendHeaderContentType('text/html');
            // just allow options get head post only
            $this->sendHeaderAcceptRanges();
            // 604800 is 1 week
            $this->sendHeaderCache(maxAge: 604800);
            $this->sendHeader('Allow', implode(', ', self::ALLOWED_METHODS));
            exit(0);
        }


        $fileSize = $this->size;
        $rangeHeader = trim($request->getHeaderLine('Range'));
        // multi-bytes boundary
        $boundary = $this->getBoundary();
        $ranges = [];
        // header for multi-bytes
        $headers = [];
        $total = $fileSize;
        $rangeTotal = 0;
        // if empty
        if ($total === 0) {
            // set content length
            $this->sendHeaderContentLength($total);
            // send mimetype header
            $this->sendHeaderMimeType();
            // send etag
            $this->sendHeaderEtag();
            // send last modifier
            $this->sendHeaderLastModified();
            // send attachment header
            $this->sendHeaderAttachment();
            $this->stopRequest();
        }
        /**
         * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Range_requests
         */
        // get mime types
        $mimeType = $this->getDetermineMimeType();
        $rangeMimeType = $mimeType??self::DEFAULT_MIMETYPE;
        $totalRanges = 0;
        $maxRanges = $this->getMaxRanges();
        // byte offset start from zero, minus 1
        $maxRange = ($fileSize - 1);
        $maxRangeRequest = $maxRange;
        $minRangeRequest = null;
        if ($maxRanges > 0 && $rangeHeader && preg_match('~^bytes=(.+)$~i', $rangeHeader, $match)) {
            $total = 0;
            $rangeHeader = array_map('trim', explode(',', trim($match[1])));
            foreach ($rangeHeader as $range) {
                $range = trim($range);
                if ($range === '') {
                    continue;
                }
                $range = explode('-', $range, 2);
                $start = array_shift($range);
                $end   = array_shift($range);

                if (($start === '' && $end === '')) {
                    // stop
                    $this->displayRangeNotSatisfy();
                }

                $start = $start === '' ? 0 : $start;
                $end   = $end === '' ? $maxRange : $end;
                if (! is_numeric($start)
                    || ! is_numeric($end)
                    || (is_string($start) && str_contains('.', $start))
                    || (is_string($end) && str_contains('.', $end))
                    || ((int) $start) > ((int) $end)
                    || ((int) $start) > $maxRange
                ) {
                    $headers = null;
                    $ranges = null;
                    // stop
                    $this->displayRangeNotSatisfy();
                }

                $start = (int) $start;
                $end = (int) $end;
                // get minimum from maxRange
                $end = min($end, $maxRange);

                /**
                 * Determine range set min & max
                 */
                $minRangeRequest ??= $start;
                if ($maxRangeRequest >= $end) {
                    $maxRangeRequest = $end;
                }
                if ($minRangeRequest > $start) {
                    $minRangeRequest = $start;
                }

                // starting point is zero so append 1 on ending
                $currentTotal = ($end + 1) - $start;
                $total += $currentTotal;
                // set start & max -> end
                $ranges[$start][$end] = [$start, $end];
                // add headers
                $header  = sprintf("\r\n--%s\r\n", $boundary);
                $header .= sprintf("Content-Type: %s\r\n", $rangeMimeType);
                $header .= sprintf("Content-Range: bytes %d-%d/%d\r\n\r\n", $start, $end, $fileSize);
                $rangeTotal += $currentTotal + strlen($header);
                $headers[$start][$end] = $header;
                $totalRanges++;
                // break on max range limit
                if ($totalRanges === $maxRanges) {
                    break;
                }
            }

            // if contain offset start 0 && max range bytes is on range
            // don't process ranges
            if (empty($ranges)
                || ($minRangeRequest === 0 && $maxRangeRequest >= $maxRange)
                || !$this->isAllowRange()
                || $this->getMaxRanges() < 1
            ) {
                $ranges = [];
                $total = $fileSize;
            } else {
                ksort($ranges);
                foreach ($ranges as $key => $range) {
                    ksort($range);
                    $ranges[$key] = $range;
                }
            }
        }
        // send accept
        // $this->sendAcceptRanges();
        // if only 1 or empty ranges
        if (($empty = empty($ranges)) || $totalRanges === 1) {
            // send cache
            // $this->sendCacheHeader(['public', 'must-revalidate'], maxAge: 604800);
            $startingPoint = 0;
            // if ranges
            if (!$empty) {
                $ranges = reset($ranges);
                $ranges = array_shift($ranges);
                $startingPoint = array_shift($ranges);
                $end = array_shift($ranges);
                $total = ($end + 1) - $startingPoint;
                if ($total !== $fileSize) {
                    $this->sendHeader('Content-Range', "bytes $startingPoint-$end/$fileSize");
                }
            }

            // set content length
            $this->sendHeaderContentLength($total);
            // send mimetype header
            $this->sendHeaderMimeType();
            // send etag
            $this->sendHeaderEtag();
            // send last modifier
            $this->sendHeaderLastModified();
            // send attachment header
            $this->sendHeaderAttachment();

            // set etag
            $this->sendHeaderEtag();
            if ($method === 'HEAD') {
                $this->stopRequest();
            }

            $sock = $this->getSock();
            $sock->fseek($startingPoint);
            while (!$sock->eof() && $total > 0) {
                $read = 4096;
                if ($total < $read) {
                    $read = $total;
                    $total = 0;
                }
                // stop
                if ($read < 1) {
                    break;
                }
                echo $sock->fread($read);
            }
            $this->stopRequest();
        }

        if (!$this->isAllowRange()) {
            $this->displayRangeNotSatisfy();
        }

        // get socket
        $sock = $this->getSock();

        // send boundary and status code -> partial content 206
        $this->sendHeaderContentType("multipart/byteranges; boundary=$boundary", 206);
        // send range total
        $this->sendHeaderContentLength($rangeTotal);
        // send etag
        $this->sendHeaderEtag();
        // send last modifier
        $this->sendHeaderLastModified();
        // send attachment header
        $this->sendHeaderAttachment();

        // no process if method header
        if ($method === 'HEAD') {
            $this->stopRequest();
        }
        foreach ($ranges as $key => $range) {
            // getting headers
            $header = $headers[$key];
            unset($header[$key]);
            foreach ($range as $ending => $rangeValue) {
                $this->checkConnection($sock);
                $start = $rangeValue[0];
                $end = $rangeValue[1];
                $total = ($end + 1) - $start;
                $sock->seek($start);
                // print headers
                echo $header[$ending];
                while ($total > 0 && $sock->valid()) {
                    $this->checkConnection($sock);
                    $read = 4096;
                    if ($total < $read) {
                        $read = $total;
                        $total = 0;
                    }
                    echo $sock->fread($read);
                }
            }
        }

        $this->stopRequest();
    }

    private function getSock(): SplFileObject
    {
        $sock = $this->file->openFile('rb');
        if (!$sock->flock(LOCK_SH|LOCK_NB)) {
            // where can not lock, use unprocessable entity
            header('Content-Type: text/html', true, 422);
            $this->stopRequest();
        }
        return $sock;
    }

    private function stopRequest(): never
    {
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        exit(0);
    }

    private function checkConnection($sock = null) : void
    {
        $sock?->flock(LOCK_UN);
        if (connection_status() !== CONNECTION_NORMAL) {
            $this->stopRequest();
        }
    }
}
