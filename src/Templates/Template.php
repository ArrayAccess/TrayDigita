<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Templates;

use ArrayAccess\TrayDigita\Templates\Abstracts\AbstractTemplate;
use ArrayAccess\TrayDigita\Templates\Abstracts\AbstractTemplateRule;
use ArrayAccess\TrayDigita\Traits\Service\TranslatorTrait;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use Throwable;
use function array_key_exists;
use function fclose;
use function feof;
use function file_get_contents;
use function filesize;
use function fopen;
use function fread;
use function is_array;
use function is_bool;
use function is_file;
use function is_int;
use function is_scalar;
use function is_string;
use function json_decode;
use function preg_match;
use function sha1;
use function sprintf;
use function trim;
use const DIRECTORY_SEPARATOR;

final class Template extends AbstractTemplate
{
    use TranslatorTrait;

    /**
     * @max size is 500KB
     */
    public const MAX_FILE_SIZE = 512000;

    private ?bool $metadataValid = null;

    public function __construct(
        AbstractTemplateRule $templateRule,
        protected string $basePath,
        protected string $jsonFile,
        protected array $invalidFiles,
        protected array $loadedFiles
    ) {
        parent::__construct($templateRule, $this->basePath);
    }

    public function getContainer(): ?ContainerInterface
    {
        return $this->templateRule->wrapper->getView()->getContainer();
    }

    /**
     * @final prevent override
     * @return array{name: string, version: ?string, description: ?string, mixed}
     */
    final public function getMetadata(): array
    {
        if ($this->metadata !== null) {
            return $this->metadata;
        }
        $this->metadataValid = false;
        $this->metadata = [
            'name' => $this->basePath,
            'version' => null,
            'description' => null,
        ];
        if (!$this->valid()) {
            return $this->metadata;
        }

        $jsonFile = $this->getTemplateDirectory()
            . DIRECTORY_SEPARATOR
            . $this->jsonFile;
        if (!is_file($jsonFile)) {
            return $this->metadata;
        }
        $cache = ContainerHelper::use(
            CacheItemPoolInterface::class,
            $this->getTemplateRule()
                ->getWrapper()
                ->getView()
                ->getContainer()
        );
        $cacheKey = 'template_'.sha1($jsonFile);
        $cacheItem = null;
        try {
            $cacheItem = $cache?->getItem($cacheKey);
            $cacheData = $cacheItem?->get();
            if (is_array($cacheData)
                && ($cacheData['key'] ?? null) === $cacheKey
                && is_int($cacheData['time'] ?? null)
                && ($cacheData['time'] + 1800) > time()
                && is_array($cacheData['meta'] ?? null)
                && is_string($cacheData['meta']['name'] ?? null)
                && is_bool($cacheData['valid'] ?? null)
                && array_key_exists('description', $cacheData['meta'])
                && array_key_exists('version', $cacheData['meta'])
                && (
                    $cacheData['meta']['version'] === null
                    || is_string($cacheData['meta']['version'])
                ) && (
                    $cacheData['meta']['description'] === null
                    || is_string($cacheData['meta']['description'])
                )
            ) {
                $this->metadataValid = $cacheData['valid'];
                $this->metadata = $cacheData['meta'];
                return $this->metadata;
            }
        } catch (Throwable) {
        }

        if (filesize($jsonFile) > self::MAX_FILE_SIZE) {
            $sock = fopen($jsonFile, 'r');
            $count = 3;
            while (!feof($sock) && $count++ < 3) {
                $read = fread($sock, 4096);
                preg_match('~\"name\"\s*:\s*(\"[^\"]+\"|null)~', $read, $match);
                if (!empty($match)) {
                    $this->metadata['name'] = json_decode(trim($match[1]))?:$this->metadata['name'];
                }
                preg_match('~\"description\"\s*:\s*(\"[^\"]+\"|null)~', $read, $match);
                if (!empty($match)) {
                    $this->metadata['description'] = json_decode(trim($match[1]))?:$this->metadata['description'];
                }
                preg_match(
                    '~\"version\"\s*:\s*(\"[^\"]+\"|null|[0-9](?:[0-9.]*[0-9]+)?)~',
                    $read,
                    $match
                );
                if (!empty($match)) {
                    $this->metadata['version'] = json_decode(trim($match[1]))?:$this->metadata['version'];
                    $this->metadata['version'] = is_scalar($this->metadata['version'])
                        ? (string) $this->metadata['version']
                        : $this->metadata['version'];
                }
            }
            fclose($sock);
        } else {
            $this->metadataValid = true;
            $json = json_decode((string)file_get_contents($jsonFile), true);
            $json = !is_array($json) ? [] : $json;
            $name = $json['name'] ?? null;
            $description = $json['description'] ?? null;
            $description = is_string($description) ? $description : null;
            $version = $json['version'] ?? null;
            $version = is_scalar($version) ? (string)$version : null;
            $name = !is_string($name) ? $this->metadata['name'] : $name;
            $json['name'] = $name;
            $json['description'] = $description;
            $json['version'] = $version;
            $this->metadata = $json;
        }

        if ($cacheItem && $cache) {
            $cacheItem->set([
                'time' => time(),
                'key' => $cacheKey,
                'valid' => $this->metadataValid,
                'meta' => $this->metadata
            ])->expiresAfter(3600);
            $cache->save($cacheItem);
        }

        return $this->metadata;
    }

    public function getName(): string
    {
        return $this->getMetadata()['name'];
    }

    public function getVersion(): ?string
    {
        return $this->getMetadata()['version'];
    }

    public function getDescription(): ?string
    {
        return $this->getMetadata()['description'];
    }

    public function valid(): bool
    {
        return empty($this->invalidFiles);
    }

    public function isMetadataValid(): bool
    {
        if ($this->metadataValid === null) {
            $this->getMetadata();
        }
        return $this->metadataValid;
    }

    public function __toString(): string
    {
        return sprintf(
            '%s | %s : %s',
            $this->getName(),
            $this->translateContext('version', 'template'),
            $this->getVersion()??$this->translateContext('unknown', 'template')
        );
    }
}
