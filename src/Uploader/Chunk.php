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
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use DirectoryIterator;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use function is_dir;
use function is_string;
use function is_writable;
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
     * @var int
     */
    protected int $maxDeletionCount = self::DEFAULT_MAX_DELETE_COUNT;

    /**
     * @var ?int $limitMaxFileSize total max file size null as unlimited
     * default : 134217728 bytes or 128MB
     */
    private ?int $limitMaxFileSize = 134217728;

    public function __construct(
        protected ContainerInterface $container,
        ?string $storageDir = null
    ) {
        if ($storageDir === null) {
            $config = ContainerHelper::service(Config::class);
            $path = $config?->get('path');
            $storageDirectory = $path instanceof Config
                ? $path->get('storage')
                : null;
            if (is_string($storageDirectory)
                && is_dir($storageDirectory)
            ) {
                $storageDir = $storageDirectory;
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
        $storageDir = $storageDir??sys_get_temp_dir();
        $this->assertDirectory($storageDir);
        $storageDir = (realpath($storageDir)??$storageDir);
        $this->uploadCacheStorageDirectory = $storageDir
            . DIRECTORY_SEPARATOR
            . self::SUFFIX_STORAGE_DIRECTORY;
    }

    public function getContainer(): ?ContainerInterface
    {
        return $this->container;
    }

    private function assertDirectory(string $directory): void
    {
        if (trim($directory) === '') {
            throw new EmptyArgumentException(
                'Storage directory could not be empty or whitespace only.'
            );
        }
        if (!is_dir($directory)) {
            throw new NotExistsDirectoryException(
                $directory,
                sprintf(
                    'Directory %s is not exist',
                    $directory
                )
            );
        }
        if (!is_writable($directory)) {
            throw new DirectoryUnWritAbleException(
                $directory,
                sprintf(
                    'Directory %s is not writable',
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

    public function setLimitMaxFileSize(?int $limitMaxFileSize): void
    {
        $this->limitMaxFileSize = $limitMaxFileSize;
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
        $max ??= $this->maxDeletionCount;
        if (!is_dir($this->uploadCacheStorageDirectory) || $max <= 0) {
            return 0;
        }

        $deleted = 0;
        foreach (new DirectoryIterator(
            $this->uploadCacheStorageDirectory
        ) as $item) {
            if ($item->isDot()
                || $item->getExtension() !== $this->partialExtension
                || $item->getBasename()[0] === '.'
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
            if ($max-- < 0) {
                break;
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
