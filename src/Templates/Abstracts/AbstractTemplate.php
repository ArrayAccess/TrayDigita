<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Templates\Abstracts;

use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\L10n\Translations\Interfaces\TranslatorInterface;
use ArrayAccess\TrayDigita\Templates\Interfaces\TemplateInterface;
use ArrayAccess\TrayDigita\Util\Filter\DataNormalizer;
use ArrayAccess\TrayDigita\View\Interfaces\ViewInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use function is_string;
use function substr;
use const DIRECTORY_SEPARATOR;

abstract class AbstractTemplate implements TemplateInterface
{
    protected string $templateDirectory;

    protected ?array $metadata = null;

    protected ?string $textDomain = null;

    public function __construct(
        public readonly AbstractTemplateRule $templateRule,
        protected string $basePath
    ) {
        $this->templateDirectory = $templateRule
                ->getTemplatesDirectory()
            . DIRECTORY_SEPARATOR
            . $this->basePath;
    }

    public function getView() : ViewInterface
    {
        return $this->getTemplateRule()->getWrapper()->getView();
    }

    public function getManager(): ManagerInterface
    {
        return $this->getView()->getManager();
    }

    public function getContainer(): ?ContainerInterface
    {
        return $this->getView()->getContainer();
    }

    public function getTemplateDirectory(): string
    {
        return $this->templateDirectory;
    }

    public function getTextDomain(): string
    {
        if ($this->textDomain) {
            return $this->textDomain;
        }
        $textDomain = $this->getMetadata()['text_domain']??(
            $this->getMetadata()['textDomain']??(
                $this->getMetadata()['textDomain']??null
            )
        );
        $this->textDomain = is_string($textDomain) && $textDomain
            ? $textDomain
            : TranslatorInterface::DEFAULT_DOMAIN;
        return $this->textDomain;
    }

    /**
     * @return array
     */
    public function getMetadata(): array
    {
        return $this->metadata??[];
    }

    public function getTemplateRule(): AbstractTemplateRule
    {
        return $this->templateRule;
    }

    abstract public function getName(): string;

    abstract public function getVersion(): ?string;

    abstract public function getDescription(): ?string;

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    public function getDirectory(): string
    {
        return $this->templateDirectory;
    }

    abstract public function valid(): bool;

    abstract public function isMetadataValid() : bool;

    public function getBaseURI(
        string $path = '',
        RequestInterface|UriInterface|null $requestOrUri = null
    ): UriInterface {
        $basePath = DataNormalizer::normalizeUnixDirectorySeparator(
            $this->getBasePath(),
            true
        );
        $basePath = ltrim($basePath, '/');
        if (str_starts_with($path, '/')) {
            $path = substr($path, 1);
        }
        $base = $this
            ->getTemplateRule()
            ->getWrapper()
            ->getTemplatePath();
        $newPath = $base . '/' . $basePath . '/' . $path;
        return $this
            ->getTemplateRule()
            ->getWrapper()
            ->getView()
            ->getBaseURI($newPath, $requestOrUri);
    }

    public function __toString(): string
    {
        return '';
    }

    public function __get(string $name)
    {
        return $this->getMetadata()[$name]??null;
    }

    public function __isset(string $name): bool
    {
        return isset($this->getMetadata()[$name]);
    }
}
