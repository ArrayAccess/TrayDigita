<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Templates\Abstracts;

use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\InvalidArgumentException;
use ArrayAccess\TrayDigita\Http\Exceptions\FileNotFoundException;
use ArrayAccess\TrayDigita\Templates\Template;
use ArrayAccess\TrayDigita\Templates\Wrapper;
use ArrayAccess\TrayDigita\Util\Filter\DataNormalizer;
use DirectoryIterator;
use function array_filter;
use function array_search;
use function is_dir;
use function is_file;
use function is_string;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function substr;
use function trim;
use const DIRECTORY_SEPARATOR;

abstract class AbstractTemplateRule
{
    protected array $requiredFiles = [];

    private bool $filteredRequiredFiles = false;

    final const METADATA_JSON = 'template.json';

    protected string $jsonPath = self::METADATA_JSON;

    protected ?string $templateLoad = 'template.php';

    private ?string $initializedDirectory = null;

    private array $templates = [];

    private ?AbstractTemplate $active = null;

    public function __construct(public readonly Wrapper $wrapper)
    {
    }

    public function getTemplateLoad(): ?string
    {
        return $this->templateLoad;
    }

    public function setActive(string|AbstractTemplate $active): bool
    {
        if (is_string($active)) {
            $active = trim($active);
            $active = $this->getTemplates()[$active]??null;
        }
        if ($active instanceof AbstractTemplate
            && $active->valid()
        ) {
            $this->active = $active;
            $view = $this->wrapper->getView();
            if ($view->getTemplateRule() === $this) {
                $view->clearVariableCache();
            }
            return true;
        }

        return false;
    }

    public function getActiveName(): ?string
    {
        return (string) ($this->active
            ? array_search($this->active, $this->templates)
            : null)??null;
    }

    public function getTemplate(string $name): ?AbstractTemplate
    {
        return $this->getTemplates()[$name]??null;
    }

    public function getActive(): ?AbstractTemplate
    {
        return $this->active;
    }

    public function getRequiredFiles(): array
    {
        return $this->internalRequiredFiles();
    }

    public function getWrapper(): Wrapper
    {
        return $this->wrapper;
    }

    public function getTemplatesDirectory(): string
    {
        return $this->getWrapper()->getPublicDirectory()
            . DIRECTORY_SEPARATOR
            . $this->getWrapper()->getTemplatePath();
    }

    /**
     * @return array
     */
    final protected function internalRequiredFiles() : array
    {
        if ($this->filteredRequiredFiles) {
            return $this->requiredFiles;
        }
        $this->filteredRequiredFiles = true;
        $requiredFiles = $this->requiredFiles;
        $requiredFiles = array_filter(
            $requiredFiles,
            static fn ($e) => is_string($e) && trim($e) !== ''
        );
        return $this->requiredFiles = $requiredFiles;
    }

    public function validate(string $fullTemplateDirectory)
    {
        $fullTemplateDirectory = DataNormalizer::normalizeDirectorySeparator(
            $fullTemplateDirectory,
            true
        );

        if (trim($fullTemplateDirectory) === '') {
            throw new InvalidArgumentException(
                'Template directory could not be empty'
            );
        }

        $templatesDirectory = $this->getTemplatesDirectory();
        if (!str_contains($fullTemplateDirectory, DIRECTORY_SEPARATOR)) {
            $fullTemplateDirectory = $templatesDirectory . DIRECTORY_SEPARATOR . $fullTemplateDirectory;
        }
        if ($fullTemplateDirectory === $templatesDirectory) {
            throw new InvalidArgumentException(
                'Template directory could not be empty'
            );
        }
        if (!str_starts_with($fullTemplateDirectory, $templatesDirectory)) {
            throw new InvalidArgumentException(
                'Directory contain outside templates directory'
            );
        }
        if (!is_dir($fullTemplateDirectory)) {
            throw new FileNotFoundException(
                $fullTemplateDirectory,
                'Directory template does not exists'
            );
        }

        $basePath = trim(
            substr($fullTemplateDirectory, strlen($templatesDirectory)),
            DIRECTORY_SEPARATOR
        );
        $basePath = trim($basePath);
        if (str_contains($basePath, DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException(
                'Basepath should not contain any sub directory'
            );
        }

        if (isset($this->templates[$basePath])) {
            return $this->templates[$basePath];
        }

        $manager = $this->wrapper->getView()->getManager();
        try {
            $manager->dispatch(
                'template.beforeValidate',
                $basePath,
                $fullTemplateDirectory,
                $this
            );
            $invalidFiles = [];
            $validFiles = [];
            $jsonFile = $this->jsonPath;
            /** @noinspection PhpConditionAlreadyCheckedInspection */
            if (!is_string($jsonFile) || str_ends_with($jsonFile, '.json')) {
                $jsonFile = self::METADATA_JSON;
            }

            $jsonFile = DataNormalizer::normalizeDirectorySeparator($jsonFile, true);
            $jsonPath = $fullTemplateDirectory . DIRECTORY_SEPARATOR . $jsonFile;
            if (is_file($jsonPath)) {
                $validFiles[] = $jsonFile;
            } else {
                $invalidFiles[] = $jsonFile;
            }
            foreach ($this->internalRequiredFiles() as $file) {
                $path = $fullTemplateDirectory . DIRECTORY_SEPARATOR . $file;
                if (!is_file($path)) {
                    $invalidFiles[] = $file;
                    continue;
                }
                $validFiles[] = $file;
            }
            $template = new Template(
                $this,
                $basePath,
                $jsonFile,
                $invalidFiles,
                $validFiles
            );
            if (!$this->active && empty($invalidFiles)) {
                $this->active = $template;
            }
            $this->templates[$basePath] = $template;
            $manager->dispatch(
                'template.validate',
                $basePath,
                $fullTemplateDirectory,
                $this
            );
            return $template;
        } finally {
            $manager->dispatch(
                'template.afterValidate',
                $basePath,
                $fullTemplateDirectory,
                $this,
                $template??null
            );
        }
    }

    public function initialize(): void
    {
        if ($this->initializedDirectory !== null) {
            return;
        }

        $this->initializedDirectory = $this->getTemplatesDirectory();
        if (!is_dir($this->initializedDirectory)) {
            return;
        }
        $manager = $this->wrapper->getView()->getManager();
        $manager->dispatch('template.beforeInitialize', $this);
        try {
            foreach (new DirectoryIterator($this->initializedDirectory) as $spl) {
                if ($spl->isDot() || !$spl->isDir()) {
                    continue;
                }
                $baseName = $spl->getBasename();
                $this->templates[$baseName] = $this->validate($spl->getRealPath());
            }
            $manager->dispatch('template.initialize', $this);
        } finally {
            $manager->dispatch('template.afterInitialize', $this);
        }
    }

    /**
     * @return array<string, AbstractTemplate>
     */
    public function getTemplates(): array
    {
        $this->initialize();
        return $this->templates;
    }
}
