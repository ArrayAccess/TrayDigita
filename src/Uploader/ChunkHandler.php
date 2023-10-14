<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Uploader;

use ArrayAccess\TrayDigita\Exceptions\Runtime\RuntimeException;
use ArrayAccess\TrayDigita\Uploader\Exceptions\DirectoryUnWritAbleException;
use ArrayAccess\TrayDigita\Uploader\Exceptions\FileLockedException;
use ArrayAccess\TrayDigita\Uploader\Exceptions\FileUnWritAbleException;
use ArrayAccess\TrayDigita\Uploader\Exceptions\InvalidOffsetPositionException;
use ArrayAccess\TrayDigita\Uploader\Exceptions\InvalidRequestId;
use ArrayAccess\TrayDigita\Uploader\Exceptions\MaxIncrementExceededException;
use ArrayAccess\TrayDigita\Uploader\Exceptions\SourceFileFailException;
use ArrayAccess\TrayDigita\Uploader\Exceptions\SourceFileMovedException;
use ArrayAccess\TrayDigita\Uploader\Exceptions\SourceFileNotFoundException;
use ArrayAccess\TrayDigita\Util\Filter\Consolidation;
use Psr\Http\Message\ResponseInterface;
use SplFileInfo;
use function is_file;
use function preg_match;
use function preg_quote;
use function sprintf;
use function substr;

class ChunkHandler
{
    const STATUS_WAITING = 0;
    const STATUS_CHECKING = 1;
    const STATUS_NOT_READY = 2;
    const STATUS_READY = 7;
    const STATUS_BEGIN = 9;
    const STATUS_RESUME = 17;
    const STATUS_FAIL = -1;

    const MAX_INCREMENT = 1000;

    const INCREMENT_SEPARATOR = '@';

    /**
     * @var string
     */
    public readonly string $targetCacheFile;

    /**
     * @var int
     */
    private int $status = self::STATUS_WAITING;

    /**
     * @var resource
     */
    private $cacheResource = null;

    /**
     * @var int
     */
    private int $written = 0;

    /**
     * @var int
     */
    private int $size = 0;

    /**
     * @var ?string
     */
    private ?string $movedFile = null;

    /**
     * @var ?string
     */
    private ?string $lastTarget = null;

    /**
     * @param ChunkProcessor $processor
     * @throws InvalidRequestId
     */
    public function __construct(
        public readonly ChunkProcessor $processor
    ) {
        if (!$this->processor->requestIdHeader->valid) {
            throw new InvalidRequestId(
                sprintf(
                    'Request id "%s" is not valid',
                    $this->processor->requestIdHeader->header
                )
            );
        }
        $this->targetCacheFile = sprintf(
            '%1$s%2$s%3$s.%4$s',
            $this->processor->chunk->getUploadCacheStorageDirectory(),
            DIRECTORY_SEPARATOR,
            $this->processor->requestIdHeader->header,
            $this->processor->chunk->partialExtension
        );
    }

    public function appendResponseHeader(ResponseInterface $response) : ResponseInterface
    {
        return $this
            ->processor
            ->chunk
            ->appendResponseBytes($response)
            ->withHeader(
                $this->processor::X_REQUEST_ID,
                $this->processor->requestIdHeader->header
            );
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getMovedFile(): ?string
    {
        return $this->movedFile;
    }

    public function getLastTarget(): ?string
    {
        return $this->lastTarget;
    }

    public function deletePartial(): bool
    {
        if (is_file($this->targetCacheFile)) {
            return unlink($this->targetCacheFile);
        }
        return false;
    }

    /**
     * @return int
     */
    protected function check() : int
    {
        if ($this->status !== self::STATUS_WAITING) {
            return $this->status;
        }

        $this->status = self::STATUS_CHECKING;
        $uploadDirectory = $this
            ->processor
            ->chunk
            ->getUploadCacheStorageDirectory();
        if (!is_dir($uploadDirectory)) {
            mkdir($uploadDirectory, 0755, true);
        }

        if (!is_dir($uploadDirectory)) {
            $this->status = self::STATUS_NOT_READY;
            throw new RuntimeException(
                'Cache upload storage does not exist.'
            );
        }

        if (!is_writable($uploadDirectory)) {
            $this->status = self::STATUS_NOT_READY;
            throw new DirectoryUnWritAbleException(
                'Cache upload storage is not writable.'
            );
        }

        $this->processor->chunk->getManager()?->dispatch(
            'chunkHandler.uploadReady',
            $this
        );

        $this->status = self::STATUS_READY;
        $this->size = is_file($this->targetCacheFile)
            ? filesize($this->targetCacheFile)
            : 0;
        return $this->status;
    }

    /**
     * @return int
     */
    public function getWritten(): int
    {
        return $this->written;
    }

    /**
     * @return int
     */
    public function getSize(): int
    {
        if ($this->size === 0) {
            $this->check();
        }

        return $this->size;
    }

    /**
     * @param string $mode
     *
     * @return int
     */
    private function writeResource(string $mode) : int
    {
        if (file_exists($this->targetCacheFile) && !is_writable($this->targetCacheFile)) {
            $this->status = self::STATUS_FAIL;
            throw new FileUnWritAbleException(
                $this->targetCacheFile,
                'Upload cache file is not writable.'
            );
        }

        $this->cacheResource = Consolidation::callbackReduceError(
            fn() => fopen($this->targetCacheFile, $mode)
        );

        if (!is_resource($this->cacheResource)) {
            $this->status = self::STATUS_FAIL;
            throw new SourceFileFailException(
                'Can not create cached stream.'
            );
        }

        flock($this->cacheResource, LOCK_EX|LOCK_NB, $wouldBlock);
        if ($wouldBlock) {
            throw new FileLockedException(
                $this->targetCacheFile,
                'Cache file has been locked.'
            );
        }

        $uploadedStream      = $this->processor->uploadedFile->getStream();
        if ($uploadedStream->isSeekable()) {
            $uploadedStream->rewind();
        }

        $this->written = 0;
        while (!$uploadedStream->eof()) {
            $this->written += (int) fwrite($this->cacheResource, $uploadedStream->read(2048));
        }

        $stat = Consolidation::callbackReduceError(fn () => fstat($this->cacheResource));
        $this->size = $stat ? (int) ($stat['size']??$this->size+$this->written) : ($this->size+$this->written);
        flock($this->cacheResource, LOCK_EX);
        return $this->written;
    }

    /**
     * @param ?int $position
     *
     * @return int
     */
    public function start(?int $position = null): int
    {
        if ($this->status === self::STATUS_WAITING) {
            $this->check();
        }

        $position ??= $this->size;
        if ($this->status !== self::STATUS_READY) {
            return $this->written;
        }

        $mode = 'ab+';
        if ($position === 0) {
            $mode = 'wb+';
        } elseif ($position !== $this->size) {
            throw new InvalidOffsetPositionException(
                $position,
                $this->size,
                'Offset upload position is invalid.'
            );
        }

        $this->status = self::STATUS_RESUME;
        return $this->writeResource($mode);
    }

    /**
     * @param string $target
     * @param bool $overrideIfExists
     * @param bool $increment
     *
     * @return bool|string
     * @throws SourceFileNotFoundException
     * @throws SourceFileMovedException
     * @throws MaxIncrementExceededException
     * @throws FileUnWritAbleException
     * @throws DirectoryUnWritAbleException
     */
    public function put(
        string $target,
        bool $overrideIfExists = false,
        bool $increment = true
    ): false|string {
        $movedFile = $this->getMovedFile();
        if (!is_file($movedFile?:$this->targetCacheFile)) {
            throw new SourceFileNotFoundException(
                $movedFile?:$this->targetCacheFile,
                'Source uploaded file does not exist.'
            );
        }

        if ($this->status === self::STATUS_WAITING) {
            $this->check();
        }

        $ready = match ($this->status) {
            self::STATUS_READY,
            self::STATUS_BEGIN,
            self::STATUS_RESUME => true,
            default => false
        };
        if (!$ready) {
            throw new SourceFileMovedException(
                sprintf(
                    'Upload cache file is not ready to move : (%d).',
                    $this->status
                )
            );
        }

        $this->close();
        if (file_exists($target)) {
            if (!$overrideIfExists) {
                if ($increment) {
                    $spl = new SplFileInfo($target);
                    $targetDirectory = $spl->getPath();
                    $ext   = $spl->getExtension();
                    $targetBaseName = substr($spl->getBasename(), 0, -(strlen($ext)+1));
                    $quote = preg_quote(self::INCREMENT_SEPARATOR, '~');
                    if (preg_match("~({$quote}[0-9]+)$~", $targetBaseName, $match)) {
                        $targetBaseName = substr($targetBaseName, 0, -strlen($match[1]));
                    }
                    $count = 0;
                    do {
                        if (++$count > self::MAX_INCREMENT) {
                            throw new MaxIncrementExceededException(
                                $spl->getFilename(),
                                self::MAX_INCREMENT
                            );
                        }
                        $target = sprintf(
                            '%1$s/%2$s%3$s',
                            $targetDirectory,
                            sprintf(
                                '%s%s%s',
                                $targetBaseName,
                                self::INCREMENT_SEPARATOR,
                                $count
                            ),
                            $ext ? ".$ext" : ''
                        );
                    } while (file_exists($target));
                } else {
                    return false;
                }
            } else {
                if (!is_writable($target)) {
                    throw new FileUnWritAbleException(
                        $target,
                        sprintf(
                            '%s is not writable.',
                            $target
                        )
                    );
                }
            }
        }

        $targetDirectory = dirname($target);
        if (!is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0755, true);
        }

        if (!is_writable($targetDirectory)) {
            throw new DirectoryUnWritAbleException(
                $targetDirectory,
                sprintf(
                    '%s is not writable.',
                    $target
                )
            );
        }

        if ($movedFile) {
            $result = Consolidation::callbackReduceError(
                fn () => copy($movedFile, $target)
            );
        } else {
            $result = Consolidation::callbackReduceError(
                fn() => rename($this->targetCacheFile, $target)
            );
        }

        $this->lastTarget = null;
        if ($result) {
            $this->lastTarget = realpath($target) ?: $target;
            if (!$movedFile) {
                $this->movedFile = $this->lastTarget;
            }
        }

        return $this->lastTarget?:false;
    }

    public function close(): void
    {
        if (is_resource($this->cacheResource)) {
            fflush($this->cacheResource);
            flock($this->cacheResource, LOCK_UN);
            fclose($this->cacheResource);
            $this->cacheResource = null;
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
