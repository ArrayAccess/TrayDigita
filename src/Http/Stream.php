<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Http;

use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\UnsupportedArgumentException;
use ArrayAccess\TrayDigita\Exceptions\Runtime\UnProcessableException;
use ArrayAccess\TrayDigita\Http\Exceptions\FileNotFoundException;
use Psr\Http\Message\StreamInterface;
use Throwable;
use function clearstatcache;
use function fclose;
use function feof;
use function fopen;
use function fread;
use function fseek;
use function fstat;
use function ftell;
use function fwrite;
use function is_array;
use function is_file;
use function is_int;
use function is_resource;
use function preg_match;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;
use function stream_get_contents;
use function stream_get_meta_data;
use const E_USER_ERROR;
use const PHP_VERSION_ID;
use const SEEK_SET;

class Stream implements StreamInterface
{
    /**
     * @see http://php.net/manual/function.fopen.php
     * @see http://php.net/manual/en/function.gzopen.php
     */
    public const READABLE_MODES = '~r|a\+|ab\+|w\+|wb\+|x\+|xb\+|c\+|cb\+~';

    public const WRITABLE_MODES = '~a|w|r\+|rb\+|rw|x|c~';

    /**
     * @var resource
     */
    private $stream;

    /**
     * @var ?int
     */
    private ?int $size = null;

    /**
     * @var array
     */
    private array $metadata;

    /**
     * @var array
     */
    private array $customMetadata;

    /**
     * @var bool
     */
    private bool $seekable;

    /**
     * @var bool
     */
    private bool $readable;

    /**
     * @var bool
     */
    private bool $writable;

    /**
     * @var ?string
     */
    private ?string $uri;

    /**
     * @param resource $stream
     */
    public function __construct($stream, array $options = [])
    {
        $this->assertIsResource($stream);
        $this->stream   = $stream;
        $this->customMetadata = $options;
        $this->metadata = stream_get_meta_data($this->stream);
        $this->readable = (bool)preg_match(
            self::READABLE_MODES,
            $this->metadata['mode']
        );
        $this->writable = (bool)preg_match(
            self::WRITABLE_MODES,
            $this->metadata['mode']
        );
        $this->seekable = (bool) $this->metadata['seekable'];
        if (isset($options['size']) && is_int($options['size']) && $options['size'] >= 0) {
            $this->size = $options['size'];
        }
        $this->uri = $this->getMetadata('uri');
    }

    /**
     * @param mixed $stream
     * @throws UnsupportedArgumentException
     */
    private function assertIsResource(mixed $stream): void
    {
        if (!is_resource($stream)) {
            throw new UnsupportedArgumentException(
                'Stream must be a resource'
            );
        }
    }

    /**
     * @throws UnProcessableException
     */
    private function assertDetachedResource($stream): void
    {
        if (!$stream || !is_resource($stream)) {
            throw new UnProcessableException(
                'Stream is detached'
            );
        }
    }

    /**
     * @param string $fileName
     * @param string $mode
     *
     * @return static
     */
    public static function fromFile(string $fileName, string $mode = 'r') : static
    {
        if (!preg_match('~^php://(fd|filter|memory|temp|std(in|out|err)|(in|out)put)~i', $fileName)
            && !is_file($fileName)
        ) {
            throw new FileNotFoundException($fileName);
        }
        return new static(fopen($fileName, $mode));
    }

    /**
     * @return string
     */
    public function __toString() : string
    {
        try {
            if ($this->isSeekable()) {
                $this->seek(0);
            }
            return $this->getContents();
        } catch (Throwable $e) {
            if (PHP_VERSION_ID >= 70400) {
                throw $e;
            }
            throw new UnProcessableException(
                sprintf(
                    '%s::__toString exception: %s',
                    static::class,
                    $e
                ),
                E_USER_ERROR
            );
        }
    }

    public function close(): void
    {
        if (!$this->stream) {
            return;
        }
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
        $this->detach();
    }

    public function detach()
    {
        if (!$this->stream) {
            return null;
        }

        $result = $this->stream;
        $this->stream =
        $this->size =
        $this->uri = null;
        $this->seekable = $this->readable = $this->writable = false;

        return $result;
    }

    public function getSize() : ?int
    {
        if ($this->size !== null) {
            return $this->size;
        }

        if (!$this->stream) {
            return null;
        }

        // Clear the stat cache if the stream has a URI
        if ($this->uri) {
            clearstatcache(true, $this->uri);
        }

        $stats = fstat($this->stream);
        if (is_array($stats) && isset($stats['size'])) {
            $this->size = $stats['size'];
            return $this->size;
        }

        return null;
    }

    public function tell() : int
    {
        $this->assertDetachedResource($this->stream);
        $result = ftell($this->stream);
        if ($result === false) {
            throw new UnProcessableException(
                'Unable to determine stream position'
            );
        }

        return $result;
    }

    public function eof() : bool
    {
        $this->assertDetachedResource($this->stream);
        return feof($this->stream);
    }

    /**
     * @return bool
     */
    public function isSeekable() : bool
    {
        return $this->seekable;
    }

    public function seek($offset, $whence = SEEK_SET): void
    {
        $this->assertDetachedResource($this->stream);
        if (!$this->seekable) {
            throw new UnProcessableException('Stream is not seekable');
        }

        $result = fseek($this->stream, $offset);
        if ($result === -1) {
            throw new UnProcessableException('Unable to determine stream position');
        }
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function isWritable() : bool
    {
        return $this->writable;
    }

    public function write($string) : int
    {
        $this->assertDetachedResource($this->stream);

        if (!$this->writable) {
            throw new UnProcessableException(
                'Cannot write to a non-writable stream'
            );
        }

        // We can't know the size after writing anything
        $this->size = null;
        $result = fwrite($this->stream, $string);
        if ($result === false) {
            throw new UnProcessableException('Unable to write to stream');
        }

        return $result;
    }

    public function isReadable() : bool
    {
        return $this->readable;
    }

    public function read($length) : string
    {
        $this->assertDetachedResource($this->stream);
        if (!$this->readable) {
            throw new UnProcessableException(
                'Cannot read from non-readable stream'
            );
        }
        if ($length < 0) {
            throw new UnProcessableException(
                'Length parameter cannot be negative'
            );
        }
        if (0 === $length) {
            return '';
        }

        try {
            $string = fread($this->stream, $length);
        } catch (Throwable $e) {
            throw new UnProcessableException(
                'Unable to read from stream',
                0,
                $e
            );
        }

        if (false === $string) {
            throw new UnProcessableException('Unable to read from stream');
        }

        return $string;
    }

    private function assertContent($content) : string
    {
        if ($content === false) {
            throw new UnProcessableException('Unable to read stream contents');
        }

        return $content;
    }

    public function getContents() : string
    {
        $this->assertDetachedResource($this->stream);
        if (!$this->readable) {
            throw new UnProcessableException(
                'Cannot read from non-readable stream'
            );
        }

        $ex = null;
        set_error_handler(static function (int $errno, string $errstr) use (&$ex) : bool {
            $ex = new UnProcessableException(sprintf(
                'Unable to read stream contents: %s',
                $errstr
            ));

            return true;
        });

        try {
            return $this->assertContent(stream_get_contents($this->stream));
        } catch (Throwable $e) {
            $ex = new UnProcessableException(sprintf(
                'Unable to read stream contents: %s',
                $e->getMessage()
            ), 0, $e);
        } finally {
            restore_error_handler();
        }
        throw $ex;
    }

    /**
     * @param ?string $key
     *
     * @return mixed
     */
    public function getMetadata(?string $key = null) : mixed
    {
        if (!$this->stream) {
            return $key ? null : [];
        } elseif (!$key) {
            return $this->customMetadata + stream_get_meta_data($this->stream);
        } elseif (isset($this->customMetadata[$key])) {
            return $this->customMetadata[$key];
        }

        $meta = stream_get_meta_data($this->stream);

        return $meta[$key] ?? null;
    }

    public function __destruct()
    {
        $this->close();
    }
}
