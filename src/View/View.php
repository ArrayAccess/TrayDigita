<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\View;

use ArrayAccess\TrayDigita\Collection\Config;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerAllocatorInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\UnsupportedArgumentException;
use ArrayAccess\TrayDigita\Http\ServerRequest;
use ArrayAccess\TrayDigita\Kernel\Decorator;
use ArrayAccess\TrayDigita\Routing\Interfaces\RouterInterface;
use ArrayAccess\TrayDigita\Templates\Abstracts\AbstractTemplateRule;
use ArrayAccess\TrayDigita\Traits\Http\ResponseFactoryTrait;
use ArrayAccess\TrayDigita\Traits\Http\StreamFactoryTrait;
use ArrayAccess\TrayDigita\Traits\Manager\ManagerAllocatorTrait;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use ArrayAccess\TrayDigita\Util\Filter\DataNormalizer;
use ArrayAccess\TrayDigita\View\Engines\TwigEngine;
use ArrayAccess\TrayDigita\View\Exceptions\FileNotFoundException;
use ArrayAccess\TrayDigita\View\Interfaces\ViewEngineInterface;
use ArrayAccess\TrayDigita\View\Interfaces\ViewInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriInterface;
use function array_filter;
use function array_flip;
use function array_key_exists;
use function array_map;
use function array_shift;
use function array_unique;
use function array_unshift;
use function array_values;
use function explode;
use function in_array;
use function is_object;
use function is_string;
use function is_subclass_of;
use function preg_match;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function substr;

class View implements ViewInterface, ManagerAllocatorInterface
{
    use ResponseFactoryTrait,
        ManagerAllocatorTrait,
        StreamFactoryTrait;
    private int $dispatcherHeaderCount = 0;

    private int $dispatcherFooterCount = 0;

    /**
     * @var ViewEngineInterface[]|class-string<ViewEngineInterface>[]
     */
    protected array $registeredEngines = [
        // 'php'   => PhpEngine::class,
        // 'phtml' => PhpEngine::class,
        'twig'  => TwigEngine::class,
    ];

    protected array $viewsDirectories;

    /**
     * The views parameters
     *
     * @var array
     */
    protected array $parameters = [];

    /**
     * @var ?ServerRequestInterface
     */
    protected ?ServerRequestInterface $request = null;

    /**
     * @var ?AbstractTemplateRule
     */
    protected ?AbstractTemplateRule $templateRule = null;

    public function __construct(
        protected ContainerInterface $container,
        ?ManagerInterface $manager = null,
        string|iterable $viewsDirectory = '',
    ) {
        $manager ??= ContainerHelper::service(ManagerInterface::class, $this->container);
        $this->setManager($manager??Decorator::manager());
        if (empty($viewsDirectory)) {
            $config = ContainerHelper::use(Config::class, $this->container);
            $config = $config?->get('path')??null;
            $config = $config instanceof Config ? $config : null;
            $directory = $config?->get('view');
            if ($directory) {
                $viewsDirectory = $directory;
            }
        }

        $this->setViewsDirectory($viewsDirectory);
    }

    /**
     * @inheritdoc
     */
    public function getManager(): ManagerInterface
    {
        return $this->managerObject;
    }

    public function getRequest(): ServerRequestInterface
    {
        if (!$this->request) {
            $this->request = ServerRequest::fromGlobals(
                ContainerHelper::use(ServerRequestFactoryInterface::class, $this->container),
                ContainerHelper::use(StreamFactoryInterface::class, $this->container)
            );
        }
        return $this->request;
    }

    public function setRequest(ServerRequestInterface $request): void
    {
        $this->request = $request;
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    public function removeParameter($name): void
    {
        unset($this->parameters[$name]);
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
        return array_key_exists($name, $this->getParameters());
    }

    public function getParameters(): array
    {
        $active = $this->getTemplateRule()?->getActive();
        if ($active) {
            return $this->parameters + ['template' => $active];
        }
        return $this->parameters;
    }

    public function getParameter($name, $default = null)
    {
        return $this->hasParameter($name)
            ? $this->getParameters()[$name]
            : $default;
    }

    public function hasEngine(string $extension) : bool
    {
        return isset($this->registeredEngines[$extension]);
    }

    public function removeEngine(string $extension): void
    {
        unset($this->registeredEngines[$extension]);
    }

    /**
     * @param ViewEngineInterface|class-string<ViewEngineInterface> $engine
     * @param ?string $extension
     * @return $this
     */
    public function addEngine(ViewEngineInterface|string $engine, string $extension = null): static
    {
        if (!is_subclass_of($engine, ViewEngineInterface::class)) {
            throw new UnsupportedArgumentException(
                sprintf(
                    'Argument engine must be sub class of %s',
                    ViewEngineInterface::class
                )
            );
        }
        if ($extension === null) {
            $engine = is_string($engine) ? new $engine($this) : $engine;
            foreach ($engine->getExtensions() as $extension) {
                $this->registeredEngines[$extension] = $engine;
            }
            return $this;
        }
        $this->registeredEngines[$extension] = $engine;
        return $this;
    }

    public function getEngines(): array
    {
        $obj = null;
        foreach ($this->registeredEngines as $ext => $engine) {
            $engine = $this->registeredEngines[$ext];
            if (is_string($engine)) {
                if ($obj === null) {
                    $obj = array_flip(array_map(
                        'get_class',
                        array_filter(
                            $this->registeredEngines,
                            'is_object'
                        )
                    ));
                }

                $engineName = $engine;
                if (isset($obj[$engineName])) {
                    $engine = $this->registeredEngines[$obj[$engineName]];
                } else {
                    $engine = new $engine($this);
                }
                /**
                 * @var ViewEngineInterface $engine
                 */
                foreach ($engine->getExtensions() as $ext2) {
                    $named = $this->registeredEngines[$ext]??null;
                    if ($named !== $engineName) {
                        continue;
                    }
                    $this->registeredEngines[$ext2] = $engine;
                }
                $this->registeredEngines[$ext] = $engine;
            }
        }

        return $this->registeredEngines;
    }

    public function getViewsDirectory(): array
    {
        $views = array_unique($this->viewsDirectories);
        $templateRule = $this->getTemplateRule()?->getActive();
        if ($templateRule) {
            array_unshift(
                $views,
                $templateRule->getTemplateDirectory()
            );
        }

        return $views;
    }

    public function getTemplateRule(): ?AbstractTemplateRule
    {
        return $this->templateRule;
    }

    public function setTemplateRule(?AbstractTemplateRule $templateRule): void
    {
        $this->templateRule = $templateRule;
        if ($templateRule->getActive()) {
            $this->clearVariableCache();
        }
    }

    public function setViewsDirectory(string|iterable $dir): void
    {
        $dir = is_string($dir) ? [$dir] : $dir;
        $this->viewsDirectories = [];
        foreach ($dir as $d) {
            if (!is_string($d) || trim($d)) {
                continue;
            }
            $this->viewsDirectories[] = DataNormalizer::normalizeDirectorySeparator($d, true);
        }

        $this->viewsDirectories = array_unique($dir);
        $this->clearVariableCache();
    }

    public function clearVariableCache(): void
    {
        foreach ($this->registeredEngines as $engine) {
            if ($engine instanceof ViewEngineInterface) {
                $engine->clearVariableCache();
            }
        }
    }

    public function appendViewsDirectory(string $dir): void
    {
        $this->viewsDirectories[] = DataNormalizer::normalizeDirectorySeparator($dir, true);
        $this->clearVariableCache();
    }

    public function prependViewsDirectory(string $dir): void
    {
        array_unshift(
            $this->viewsDirectories,
            DataNormalizer::normalizeDirectorySeparator($dir, true)
        );
        $this->viewsDirectories = array_values(array_unique($this->viewsDirectories));
        $this->clearVariableCache();
    }

    public function exist(string $path): bool
    {
        $extension = $this->getPathExtensionEngine($path);
        if ($extension && $this->getEngine($extension)?->exist($path)) {
            return true;
        }

        foreach ($this->registeredEngines as $ext => $engine) {
            if ($ext === $extension) {
                continue;
            }
            $engine = is_string($engine) ? $this->getEngine($ext) : $engine;
            if ($engine->exist($path)) {
                return true;
            }
        }
        return false;
    }

    public function getEngine(string $extension) : ?ViewEngineInterface
    {
        $engine = $this->registeredEngines[$extension]??null;
        if (is_string($engine)) {
            foreach ($this->registeredEngines as $eng) {
                if (is_object($eng) && in_array($extension, $eng->getExtensions())) {
                    $this->registeredEngines[$extension] = $eng;
                    return $this->registeredEngines[$extension];
                }
            }
            $engine = new $engine($this);
            $this->registeredEngines[$extension] = $engine;
        }
        return $engine;
    }

    private function getPathExtensionEngine(string $path) : ?string
    {
        if (!preg_match('~\.([a-z0-9_]+)$~', $path)) {
            return null;
        }
        foreach ($this->registeredEngines as $extension => $engine) {
            if (str_ends_with($path, ".$extension")) {
                return $extension;
            }
        }

        return null;
    }

    public function render(string $path, array $parameters = []): string
    {
        if ($path === '1') {
            return $path;
        }
        $manager = $this->getManager();
        try {
            $manager->dispatch(
                'view.beforeRender',
                $path,
                $parameters,
                $this
            );
            $extension = $this->getPathExtensionEngine($path);
            if ($extension && ($engine = $this->getEngine($extension))?->exist($path)) {
                $result = $engine->render($path, $parameters, $this);
            } else {
                foreach ($this->registeredEngines as $ext => $engine) {
                    if ($ext === $extension) {
                        continue;
                    }
                    $engine = is_string($engine) ? $this->getEngine($ext) : $engine;
                    if ($engine->exist($path)) {
                        $result = $engine->render($path, $parameters, $this);
                        break;
                    }
                }
            }
            if (!isset($result)) {
                throw new FileNotFoundException(
                    $path,
                    sprintf(
                        'View "%s" has not found from engine.',
                        $path
                    )
                );
            }
            $manager->dispatch(
                'view.render',
                $path,
                $parameters,
                $this,
                $result
            );
            return $result;
        } finally {
            $manager->dispatch(
                'view.afterRender',
                $path,
                $parameters,
                $this,
                $result??null
            );
        }
    }

    public function serve(
        string $path,
        array $parameters = [],
        ?ResponseInterface $response = null
    ): ResponseInterface {
        $response ??= $this
            ->getResponseFactory()
            ->createResponse();
        if (!$response->getHeaderLine('Content-Type')) {
            $response = $response
                ->withHeader(
                    'Content-Type',
                    'text/html'
                );
        }
        $body = $response->getBody();
        if (!$body->isWritable()) {
            $body = $this->getStreamFactory()->createStream();
            $response = $response->withBody($body);
        }
        $body->write($this->render($path, $parameters));
        return $response;
    }

    public function getBaseURI(
        string $path = '',
        RequestInterface|UriInterface|null $requestOrUri = null
    ): UriInterface {
        $requestOrUri ??= $this->getRequest();
        if ($requestOrUri instanceof RequestInterface) {
            $requestOrUri = $requestOrUri->getUri();
        }
        $basePath = ContainerHelper::use(
            RouterInterface::class,
            $this->getContainer()
        )->getBasePath();
        $basePath = !str_starts_with($basePath, '/')
            ? '/'. $basePath : $basePath;
        if ($basePath !== '/' && ! str_starts_with($basePath, '/')) {
            $basePath = $basePath . '/';
        }
        if (str_contains($path, '#')) {
            $paths = explode('#', $path, 2);
            $path = array_shift($paths);
            $fragment = array_shift($paths) ?: null;
        }
        if (str_contains($path, '?')) {
            $paths = explode('?', $path, 2);
            $path = array_shift($paths);
            $query = array_shift($paths) ?: null;
        }
        if (str_starts_with($path, '/')) {
            $basePath = substr($basePath, 0, -1);
        }
        return $requestOrUri
            ->withPath($basePath . $path)
            ->withQuery($query??'')
            ->withFragment($fragment??'');
    }

    public function getTemplateURI(
        string $path = '',
        RequestInterface|UriInterface|null $requestOrUri = null
    ): UriInterface {
        $active = $this->getTemplateRule()?->getActive();
        return $active
            ? $active->getBaseURI($path, $requestOrUri)
            : $this->getTemplateURI($path, $requestOrUri);
    }

    public function dispatchHeader(bool $allowMultiple = true): void
    {
        if ($this->getDispatcherHeaderCount() > 0 && !$allowMultiple) {
            return;
        }

        $this->dispatcherHeaderCount++;
        $this
            ->getManager()
            ?->dispatch(
                'view.contentHeader',
                $this
            );
    }

    public function dispatchFooter(bool $allowMultiple = true): void
    {
        if ($this->getDispatcherFooterCount() > 0 && !$allowMultiple) {
            return;
        }

        $this->dispatcherFooterCount++;
        $this
            ->getManager()
            ?->dispatch(
                'view.contentFooter',
                $this
            );
    }

    public function getDispatcherHeaderCount(): int
    {
        return $this->dispatcherHeaderCount;
    }

    public function getDispatcherFooterCount(): int
    {
        return $this->dispatcherFooterCount;
    }
}
