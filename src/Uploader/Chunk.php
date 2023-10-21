<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Uploader;

use ArrayAccess\TrayDigita\Collection\Config;
use ArrayAccess\TrayDigita\Container\Interfaces\ContainerIndicateInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerAllocatorInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\EmptyArgumentException;
use ArrayAccess\TrayDigita\Traits\Manager\ManagerAllocatorTrait;
use ArrayAccess\TrayDigita\Traits\Service\TranslatorTrait;
use ArrayAccess\TrayDigita\Uploader\Exceptions\DirectoryUnWritAbleException;
use ArrayAccess\TrayDigita\Uploader\Exceptions\NotExistsDirectoryException;
use ArrayAccess\TrayDigita\Util\Filter\Consolidation;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use DirectoryIterator;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use function is_dir;
use function is_int;
use function is_string;
use function is_writable;
use function max;
use function min;
use function realpath;
use function sprintf;
use function sys_get_temp_dir;
use const DIRECTORY_SEPARATOR;

class Chunk implements ManagerAllocatorInterface, ContainerIndicateInterface
{
    use ManagerAllocatorTrait,
        TranslatorTrait;

    const SUFFIX_STORAGE_DIRECTORY = 'chunk_uploads';

    /**
     * 5 Hours
     */
    const MAX_AGE_FILE = 18000;

    const MIN_FILE_SIZE = 1024;

    const DEFAULT_MIN_FILE_SIZE = 512000;

    /**
     * Maximum size for unlink
     */
    const DEFAULT_MAX_DELETE_COUNT = 50;

    /**
     * @var string
     */
    protected string $uploadCacheStorageDirectory;

    /**
     * @var string
     */
    public readonly string $partialExtension;

    /**
     * @var string
     */
    public readonly string $partialMetaExtension;

    /**
     * @var int
     */
    protected int $maxDeletionCount = self::DEFAULT_MAX_DELETE_COUNT;

    /**
     * @var ?int $limitMaxFileSize total max file size null as unlimited
     * default : 134217728 bytes or 128MiB
     */
    private ?int $limitMaxFileSize = 134217728;

    /**
     * @var ?int minimum size limit null as unlimited
     * default: 512000 as 500Kib
     */
    private ?int $limitMinimumFileSize = self::DEFAULT_MIN_FILE_SIZE;

    private int $maxUploadFileSize;

    private bool $allowRevertPosition = false;

    public function __construct(
        protected ContainerInterface $container,
        ?string $storageDirectory = null
    ) {
        if ($storageDirectory === null) {
            $config = ContainerHelper::service(Config::class);
            $path = $config?->get('path');
            $storageDirectory = $path instanceof Config
                ? $path->get('storage')
                : null;
            if (!is_string($storageDirectory)
                || !is_dir($storageDirectory)
            ) {
                $storageDirectory = null;
            }
        }
        $manager = ContainerHelper::use(
            ManagerInterface::class,
            $this->container
        );
        if ($manager) {
            $this->setManager($manager);
        }
        $this->partialExtension = 'partial';
        $this->partialMetaExtension = $this->partialExtension . '.meta';
        $storageDirectory = $storageDirectory??sys_get_temp_dir();
        $this->assertDirectory($storageDirectory);
        $storageDirectory = (realpath($storageDirectory)??$storageDirectory);
        $this->uploadCacheStorageDirectory = $storageDirectory
            . DIRECTORY_SEPARATOR
            . self::SUFFIX_STORAGE_DIRECTORY;
        $this->maxUploadFileSize = Consolidation::getMaxUploadSize();
    }

    public function isAllowRevertPosition(): bool
    {
        return $this->allowRevertPosition;
    }

    public function setAllowRevertPosition(bool $allowRevertPosition): void
    {
        $this->allowRevertPosition = $allowRevertPosition;
    }

    public function getContainer(): ?ContainerInterface
    {
        return $this->container;
    }

    private function assertDirectory(string $directory): void
    {
        if (trim($directory) === '') {
            throw new EmptyArgumentException(
                $this->translateContext(
                    'Storage directory could not be empty or whitespace only.',
                    'chunk-uploader'
                )
            );
        }
        if (!is_dir($directory)) {
            throw new NotExistsDirectoryException(
                $directory,
                sprintf(
                    $this->translateContext(
                        'Directory %s is not exist',
                        'chunk-uploader'
                    ),
                    $directory
                )
            );
        }
        if (!is_writable($directory)) {
            throw new DirectoryUnWritAbleException(
                $directory,
                sprintf(
                    $this->translateContext(
                        'Directory %s is not writable',
                        'chunk-uploader'
                    ),
                    $directory
                )
            );
        }
    }

    public function getUploadCacheStorageDirectory(): string
    {
        return $this->uploadCacheStorageDirectory;
    }

    public function setUploadCacheStorageDirectory(string $uploadCacheStorageDirectory): void
    {
        $this->uploadCacheStorageDirectory = $uploadCacheStorageDirectory;
    }

    public function getMaxDeletionCount(): int
    {
        return $this->maxDeletionCount;
    }

    public function setMaxDeletionCount(int $maxDeletionCount): void
    {
        $this->maxDeletionCount = $maxDeletionCount;
    }

    public function getLimitMaxFileSize(): ?int
    {
        return $this->limitMaxFileSize;
    }

    public function setLimitMaxFileSize(?int $limitMaxFileSize): ?int
    {
        if (is_int($limitMaxFileSize)) {
            // minimum 500KiB
            $limitMaxFileSize = max($limitMaxFileSize, self::DEFAULT_MIN_FILE_SIZE);
            if (is_int($this->limitMinimumFileSize)
                && $limitMaxFileSize < $this->limitMinimumFileSize
            ) {
                // assert min
                $this->limitMinimumFileSize = min($limitMaxFileSize, $this->limitMinimumFileSize);
            }
        }
        $this->limitMaxFileSize = $limitMaxFileSize;
        return $this->limitMaxFileSize;
    }

    public function getMaxUploadFileSize(): int
    {
        return $this->maxUploadFileSize;
    }

    public function setLimitMinimumFileSize(?int $limitMinFileSize): ?int
    {
        if (is_int($limitMinFileSize)) {
            $limitMinFileSize = min($limitMinFileSize, $this->getMaxUploadFileSize());
            $limitMinFileSize = min($limitMinFileSize, $this->getLimitMaxFileSize());
            // minimum is 1024
            $limitMinFileSize = max($limitMinFileSize, self::MIN_FILE_SIZE);
        }
        $this->limitMinimumFileSize = $limitMinFileSize;
        return $this->limitMinimumFileSize;
    }

    public function getLimitMinimumFileSize(): ?int
    {
        return $this->limitMinimumFileSize;
    }

    public function appendResponseBytes(ResponseInterface $response): ResponseInterface
    {
        return $response->withHeader('Accept-Ranges', 'bytes');
    }

    public function createProcessor(
        UploadedFileInterface $file,
        ServerRequestInterface $request
    ): ChunkProcessor {
        return ChunkProcessor::createFromRequest(
            $this,
            $file,
            $request
        );
    }

    public function clean(?int $max = null) : int
    {
        $max ??= $this->getMaxDeletionCount();
        if ($max <= 0 || !is_dir($this->uploadCacheStorageDirectory)) {
            return 0;
        }

        $deleted = 0;
        foreach (new DirectoryIterator(
            $this->uploadCacheStorageDirectory
        ) as $item) {
            if ($max <= 0) {
                break;
            }
            $isPartial = $item->getExtension() !== $this->partialExtension;
            $isPartialMeta = $item->getExtension() !== $this->partialMetaExtension;
            if ($item->isDot()
                || $item->getBasename()[0] === '.'
                || (!$isPartial && !$isPartialMeta)
                || !$item->isFile()
                || $item->isLink()
            ) {
                continue;
            }
            if ($item->getMTime() > (time() - self::MAX_AGE_FILE)) {
                continue;
            }
            if (!$item->isWritable()) {
                continue;
            }
            if ($isPartial) {
                $max--;
            }
            $deleted++;
            unlink($item->getRealPath());
        }

        return $deleted;
    }

    public function __destruct()
    {
        $this->clean();
    }
}
