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
use function file_get_contents;
use function file_put_contents;
use function filesize;
use function fseek;
use function is_array;
use function is_file;
use function is_float;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function microtime;
use function preg_match;
use function preg_quote;
use function sprintf;
use function substr;
use function unlink;
use const JSON_UNESCAPED_SLASHES;

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

    public readonly string $targetCacheMetaFile;

    /**
     * @var array{
     *      first_time: ?float,
     *      size: ?int,
     *      count: ?int,
     *      mimetype: ?string,
     *      timing: array<array{written: int, time: float, size: int}>
     *  }
     */
    private array $metadata = [
        'first_time' => null,
        'size' => null,
        'count' => null,
        'mimetype' => null,
        'timing' => [],
    ];

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
                    $this->processor->chunk->translateContext(
                        'Request id "%s" is not valid',
                        'chunk-uploader'
                    ),
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
        $this->targetCacheMetaFile = sprintf(
            '%1$s.%2$s',
            $this->targetCacheFile,
            $this->processor->chunk->partialMetaExtension
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

    /**
     * @return array{
     *     first_time: ?float,
     *     size: ?int,
     *     count: ?int,
     *     mimetype: ?string,
     *     timing: array<array{written: int, time: float, size: int}>
     * }
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function deletePartial(): bool
    {
        $status = false;
        if (is_file($this->targetCacheFile)) {
            $status = Consolidation::callbackReduceError(
                fn () => unlink($this->targetCacheFile)
            );
        }
        if (is_file($this->targetCacheMetaFile)) {
            $new_status = Consolidation::callbackReduceError(
                fn () => unlink($this->targetCacheMetaFile)
            );
            $status = $status || $new_status;
        }
        return $status;
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
                $this->processor->chunk->translateContext(
                    'Cache upload storage is not writable.',
                    'chunk-uploader'
                )
            );
        }

        $this->processor->chunk->getManager()?->dispatch(
            'chunkHandler.uploadReady',
            $this
        );

        $this->status = self::STATUS_READY;
        if (is_file($this->targetCacheFile)) {
            $this->size = filesize($this->targetCacheFile);
        } else {
            $this->size = 0;
        }
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
     * @param int $offset
     *
     * @return int
     */
    private function writeResource(string $mode, int $offset) : int
    {
        if (file_exists($this->targetCacheFile) && !is_writable($this->targetCacheFile)) {
            $this->status = self::STATUS_FAIL;
            throw new FileUnWritAbleException(
                $this->targetCacheFile,
                $this->processor->chunk->translateContext(
                    'Upload cache file is not writable.',
                    'chunk-uploader'
                )
            );
        }

        $this->cacheResource = Consolidation::callbackReduceError(
            fn() => fopen($this->targetCacheFile, $mode)
        );

        if (!is_resource($this->cacheResource)) {
            $this->status = self::STATUS_FAIL;
            throw new SourceFileFailException(
                $this->processor->chunk->translateContext(
                    'Can not create cached stream.',
                    'chunk-uploader'
                )
            );
        }

        flock($this->cacheResource, LOCK_EX|LOCK_NB, $wouldBlock);
        if ($wouldBlock) {
            throw new FileLockedException(
                $this->targetCacheFile,
                $this->processor->chunk->translateContext(
                    'Cache file has been locked.',
                    'chunk-uploader'
                )
            );
        }

        $uploadedStream      = $this->processor->uploadedFile->getStream();
        if ($uploadedStream->isSeekable()) {
            $uploadedStream->rewind();
        }
        fseek($this->cacheResource, $offset);
        $this->written = 0;
        while (!$uploadedStream->eof()) {
            $this->written += (int) fwrite($this->cacheResource, $uploadedStream->read(2048));
        }
        $isFirst = $this->size === 0;
        $stat = Consolidation::callbackReduceError(fn () => fstat($this->cacheResource));
        $this->size = $stat ? (int) (
            $stat['size']??($offset + $this->written)
        ) : ($offset + $this->written);
        flock($this->cacheResource, LOCK_EX);
        $written = null;
        $time = $_SERVER['REQUEST_FLOAT_TIME']??null;
        $time = is_float($time) ? $time : microtime(true);
        if (is_file($this->targetCacheMetaFile)) {
            $meta = Consolidation::callbackReduceError(fn () => json_decode(
                file_get_contents($this->targetCacheMetaFile),
                true
            ));
            $valid = is_array($meta)
                && isset($meta['first_time'], $meta['mimetype'], $meta['count'], $meta['timing'])
                && is_string($meta['mimetype'])
                && is_float($meta['first_time'])
                && is_int($meta['count'])
                && is_array($meta['timing'])
                && count($meta['timing']) === $meta['count']
                && preg_match('~^[^/]+/~', $meta['mimetype']);
            if (!$valid) {
                 Consolidation::callbackReduceError(fn () => unlink($this->targetCacheMetaFile));
            } else {
                $written = $meta;
                $written['count'] += 1;
                $written['timing'][] = [
                    'time' => $time,
                    'written' => $this->written,
                    'size' => $this->size
                ];
            }
        } elseif ($isFirst) {
            $written = [
                'first_time' => $time,
                'mimetype' => $this->processor->uploadedFile->getClientMediaType(),
                'count' => 1,
                'timing' => [
                    [
                        'time' => $time,
                        'written' => $this->written,
                        'size' => $this->size
                    ]
                ]
            ];
        }
        if (is_array($written)) {
            $this->metadata = $written;
            Consolidation::callbackReduceError(fn () => file_put_contents(
                $this->targetCacheMetaFile,
                json_encode($written, JSON_UNESCAPED_SLASHES)
            ));
        }

        return $this->written;
    }

    /**
     * @param ?int $offset
     *
     * @return int
     */
    public function start(?int $offset = null): int
    {
        if ($this->status === self::STATUS_WAITING) {
            $this->check();
        }

        $offset ??= $this->size;
        if ($this->status !== self::STATUS_READY) {
            return $this->written;
        }

        $mode = 'wb+';
        if ($offset > 0) {
            $allowRevertPosition = $this->processor->chunk->isAllowRevertPosition();
            if (!$allowRevertPosition && $offset !== $this->size
                || $allowRevertPosition && $offset > $this->size
            ) {
                // do delete
                $this->deletePartial();
                throw new InvalidOffsetPositionException(
                    $offset,
                    $this->size,
                    $this->processor->chunk->translateContext(
                        'Offset upload position is invalid.',
                        'chunk-uploader'
                    )
                );
            }
        }

        $this->status = self::STATUS_RESUME;
        return $this->writeResource($mode, $offset);
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
                $this->processor->chunk->translateContext(
                    'Source uploaded file does not exist.',
                    'chunk-uploader'
                )
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
                    $this->processor->chunk->translateContext(
                        'Upload cache file is not ready to move : (%d).',
                        'chunk-uploader'
                    ),
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
                            $this->processor->chunk->translateContext(
                                'Target file "%s" is not writable.',
                                'chunk-uploader'
                            ),
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
                    $this->processor->chunk->translateContext(
                        'Target directory "%s" is not writable.',
                        'chunk-uploader'
                    ),
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

        if (is_file($this->targetCacheMetaFile)) {
            Consolidation::callbackReduceError(
                fn() => unlink($this->targetCacheMetaFile)
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
