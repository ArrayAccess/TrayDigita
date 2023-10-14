<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Http;

use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\InvalidArgumentException;
use ArrayAccess\TrayDigita\Exceptions\Runtime\UnProcessableException;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use function fclose;
use function fopen;
use function fwrite;
use function in_array;
use function is_resource;
use function is_string;
use function move_uploaded_file;
use function rename;
use function sprintf;
use const PHP_SAPI;
use const UPLOAD_ERR_CANT_WRITE;
use const UPLOAD_ERR_EXTENSION;
use const UPLOAD_ERR_FORM_SIZE;
use const UPLOAD_ERR_INI_SIZE;
use const UPLOAD_ERR_NO_FILE;
use const UPLOAD_ERR_NO_TMP_DIR;
use const UPLOAD_ERR_OK;
use const UPLOAD_ERR_PARTIAL;

class UploadedFile implements UploadedFileInterface
{
    const ERRORS = [
        UPLOAD_ERR_OK,
        UPLOAD_ERR_INI_SIZE,
        UPLOAD_ERR_FORM_SIZE,
        UPLOAD_ERR_PARTIAL,
        UPLOAD_ERR_NO_FILE,
        UPLOAD_ERR_NO_TMP_DIR,
        UPLOAD_ERR_CANT_WRITE,
        UPLOAD_ERR_EXTENSION,
    ];

    /**
     * @var string|null
     */
    private ?string $clientFilename;

    /**
     * @var string|null
     */
    private ?string $clientMediaType;

    /**
     * @var int
     */
    private int $error;

    /**
     * @var string|null
     */
    private ?string $file;

    /**
     * @var bool
     */
    private bool $moved = false;

    /**
     * @var int|null
     */
    private ?int $size;

    /**
     * @var ?StreamInterface
     */
    private ?StreamInterface $stream = null;

    /**
     * @param StreamInterface|string|resource $streamOrFile
     * @param int|null $size
     * @param int $errorStatus
     * @param string|null $clientFilename
     * @param string|null $clientMediaType
     * @noinspection PhpMissingParamTypeInspection
     */
    public function __construct(
        $streamOrFile,
        ?int $size,
        int $errorStatus,
        string $clientFilename = null,
        string $clientMediaType = null
    ) {
        $this->setError($errorStatus);
        $this->size = $size;
        $this->clientFilename = $clientFilename;
        $this->clientMediaType = $clientMediaType;

        if ($this->isOk()) {
            $this->setStreamOrFile($streamOrFile);
        }
        // resolve size
        if ($this->size === null) {
            $this->size = $this->getStream()->getSize();
        }
    }

    /**
     * Depending on the value set file or stream variable
     *
     * @param StreamInterface|string|resource $streamOrFile
     *
     * @throws InvalidArgumentException
     * @noinspection PhpMissingParamTypeInspection
     */
    private function setStreamOrFile($streamOrFile) : void
    {
        if (is_string($streamOrFile)) {
            $this->file = $streamOrFile;
        } elseif (is_resource($streamOrFile)) {
            $this->stream = new Stream($streamOrFile);
        } elseif ($streamOrFile instanceof StreamInterface) {
            $this->stream = $streamOrFile;
        } else {
            throw new InvalidArgumentException(
                'Invalid stream or file provided for UploadedFile'
            );
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function setError(int $error) : void
    {
        if (false === in_array($error, self::ERRORS, true)) {
            throw new InvalidArgumentException(
                'Invalid error status for UploadedFile'
            );
        }

        $this->error = $error;
    }

    private function isStringNotEmpty($param) : bool
    {
        return is_string($param) && false === empty($param);
    }

    /**
     * Return true if there is no upload error
     */
    private function isOk() : bool
    {
        return $this->error === UPLOAD_ERR_OK;
    }

    public function isMoved() : bool
    {
        return $this->moved;
    }

    /**
     * @throws UnProcessableException if is moved or not ok
     */
    private function validateActive() : void
    {
        if (false === $this->isOk()) {
            throw new UnProcessableException('Cannot retrieve stream due to upload error');
        }

        if ($this->isMoved()) {
            throw new UnProcessableException('Cannot retrieve stream after it has already been moved');
        }
    }

    public function getStream() : StreamInterface
    {
        $this->validateActive();

        if ($this->stream instanceof StreamInterface) {
            return $this->stream;
        }

        /** @var string $file */
        $file = $this->file;

        return new Stream(fopen($file, 'r+'));
    }

    public function moveTo($targetPath) : void
    {
        $this->validateActive();

        if (false === $this->isStringNotEmpty($targetPath)) {
            throw new InvalidArgumentException(
                'Invalid path provided for move operation; must be a non-empty string'
            );
        }

        if ($this->file) {
            $this->moved = PHP_SAPI === 'cli'
                ? rename($this->file, $targetPath)
                : move_uploaded_file($this->file, $targetPath);
        } else {
            $this->stream->rewind();
            $sock = fopen($targetPath, 'wb');
            while (!$this->stream->eof()) {
                fwrite($sock, $this->stream->read(4096));
            }
            fclose($sock);
            $this->moved = true;
        }

        if (false === $this->moved) {
            throw new UnProcessableException(
                sprintf('Uploaded file could not be moved to %s', $targetPath)
            );
        }
    }

    public function getSize() : ?int
    {
        return $this->size;
    }

    public function getError() : int
    {
        return $this->error;
    }

    public function getClientFilename() : ?string
    {
        return $this->clientFilename;
    }

    public function getClientMediaType() : ?string
    {
        return $this->clientMediaType;
    }
}
