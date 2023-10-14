<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\View\Engines;

use ArrayAccess\TrayDigita\Util\Filter\DataNormalizer;
use ArrayAccess\TrayDigita\View\Exceptions\FileNotFoundException;
use ArrayAccess\TrayDigita\View\Interfaces\ViewEngineInterface;
use ArrayAccess\TrayDigita\View\Interfaces\ViewInterface;
use Stringable;
use function array_filter;
use function array_key_exists;
use function array_map;
use function array_unique;
use function is_dir;
use function is_file;
use function is_string;
use function realpath;
use function sha1;
use function sprintf;
use function str_starts_with;
use const DIRECTORY_SEPARATOR;

abstract class AbstractEngine implements ViewEngineInterface
{
    protected array $extensions = [];

    protected array $cachedFiles = [];

    protected array $parameters = [];

    private ?array $cachedViewsDirectory = null;

    public function __construct(protected ViewInterface $view)
    {
    }

    public function getExtensions(): array
    {
        return $this->extensions;
    }

    public function getView(): ViewInterface
    {
        return $this->view;
    }

    public function setParameters(array $parameters): void
    {
        $this->parameters = $parameters;
    }

    public function setParameter($name, $value): void
    {
        $this->parameters[$name] = $value;
    }

    public function hasParameter($name): bool
    {
        return array_key_exists($name, $this->parameters);
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getParameter($name, $default = null)
    {
        return $this->hasParameter($name)
            ? $this->parameters[$name]
            : $default;
    }

    public function removeParameter($name): void
    {
        unset($this->parameters[$name]);
    }

    protected function normalizePath(
        string $path
    ): string {
        return DataNormalizer::normalizeDirectorySeparator(
            $path,
            true
        );
    }

    /**
     * @return array<string>
     */
    final public function getFilteredViewsDir(): array
    {
        return $this->cachedViewsDirectory ??= array_map([$this, 'normalizePath'], array_filter(
            array_unique($this->getView()->getViewsDirectory()),
            static fn ($e) => is_string($e) && is_dir($e)
        ));
    }

    public function getFile(string $filePath): ?string
    {
        $filePath = $this->normalizePath($filePath);
        $cacheKey = sha1($filePath);
        if (isset($this->cachedFiles[$cacheKey])) {
            return $this->cachedFiles[$cacheKey]?:null;
        }

        $viewPaths = $this->getFilteredViewsDir();
        $extensions = array_map(
            static fn ($e) => str_starts_with($e, '.') ? $e : ".$e",
            array_filter($this->getExtensions(), 'is_string')
        );

        $this->cachedFiles[$cacheKey] = false;
        foreach ($viewPaths as $viewPath) {
            $path = str_starts_with($filePath, $viewPath)
                ? $filePath
                : $viewPath . DIRECTORY_SEPARATOR . $filePath;
            foreach ($extensions as $extension) {
                if (!str_ends_with($path, $extension)) {
                    continue;
                }
                if (is_file($path)) {
                    return $this->cachedFiles[$cacheKey] = realpath($path) ?: $path;
                }
            }
            foreach ($extensions as $extension) {
                $fPath = "$path$extension";
                if (is_file($fPath)) {
                    return $this->cachedFiles[$cacheKey] = realpath($fPath) ?: $fPath;
                }
            }
        }
        return null;
    }

    public function exist(string $path): bool
    {
        return $this->getFile($path) !== null;
    }

    abstract protected function internalInclude(string $path, array $parameters) : string|Stringable;

    public function partial(
        string $path,
        array $parameters
    ): string {
        $file = $this->getFile($path);
        if ($file) {
            $manager = $this->view->getManager();
            $manager?->dispatch('viewEngine.partial', $file, $path, $parameters, $this);
            return $this->internalInclude($file, $parameters);
        }

        throw new FileNotFoundException(
            $path,
            sprintf(
                'View "%s" has not found from engine.',
                $path
            )
        );
    }

    /**
     * Render files
     *
     * @param string $path
     * @param array $parameters
     * @param ViewInterface|null $view
     * @return string
     */
    public function render(
        string $path,
        array $parameters,
        ?ViewInterface $view = null
    ) : string {
        $view ??= $this->getView();
        if ($view !== $this->getView()) {
            $obj = clone $this;
            $obj->view = $view;
            $file = $obj->getFile($path);
        } else {
            $file = $this->getFile($path);
        }

        if ($file) {
            $obj = clone $this;
            foreach ($parameters as $key => $value) {
                $obj->setParameter($key, $value);
            }
            $obj->view = $view;
            $manager = $this->view->getManager();
            try {
                $manager?->dispatch('viewEngine.render', $file, $path, $parameters, $obj, $this);
                $result = (string)$obj->internalInclude($file, $parameters);
            } finally {
                $manager?->dispatch(
                    'viewEngine.rendered',
                    $file,
                    $path,
                    $parameters,
                    $obj,
                    $this,
                    $result??null
                );
            }
            return $result;
        }

        throw new FileNotFoundException(
            $path,
            sprintf(
                'View "%s" has not found from engine.',
                $path
            )
        );
    }

    public function clearVariableCache(): void
    {
        $this->cachedViewsDirectory = null;
        $this->cachedFiles = [];
    }

    public function __destruct()
    {
        $this->clearVariableCache();
    }
}
