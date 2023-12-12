<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Routing;

use ArrayAccess\TrayDigita\Container\Interfaces\ContainerAllocatorInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerAllocatorInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\InvalidArgumentException;
use ArrayAccess\TrayDigita\Http\Interfaces\HttpExceptionInterface;
use ArrayAccess\TrayDigita\Http\RequestResponseExceptions\MethodNotAllowedException;
use ArrayAccess\TrayDigita\Http\RequestResponseExceptions\NotFoundException;
use ArrayAccess\TrayDigita\Routing\Attributes\Group;
use ArrayAccess\TrayDigita\Routing\Attributes\Interfaces\HttpMethodAttributeInterface;
use ArrayAccess\TrayDigita\Routing\Exceptions\RouteControllerException;
use ArrayAccess\TrayDigita\Routing\Interfaces\ControllerInterface;
use ArrayAccess\TrayDigita\Routing\Interfaces\MatchedRouteInterface;
use ArrayAccess\TrayDigita\Routing\Interfaces\RouteFactoryInterface;
use ArrayAccess\TrayDigita\Routing\Interfaces\RouteInterface;
use ArrayAccess\TrayDigita\Routing\Interfaces\RouterInterface;
use ArrayAccess\TrayDigita\Traits\Container\ContainerAllocatorTrait;
use ArrayAccess\TrayDigita\Traits\Http\ResponseFactoryTrait;
use ArrayAccess\TrayDigita\Traits\Manager\ManagerAllocatorTrait;
use ArrayAccess\TrayDigita\Traits\Routing\RouteFactoryTrait;
use ArrayAccess\TrayDigita\Util\Filter\Consolidation;
use Closure;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use Throwable;
use function addcslashes;
use function array_merge;
use function array_pop;
use function asort;
use function defined;
use function dirname;
use function implode;
use function in_array;
use function is_bool;
use function is_file;
use function is_string;
use function ltrim;
use function preg_match;
use function preg_quote;
use function preg_replace;
use function realpath;
use function restore_error_handler;
use function set_error_handler;
use function spl_object_hash;
use function sprintf;
use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;
use function uasort;
use const PREG_NO_ERROR;
use const TD_INDEX_FILE;

class Router implements RouterInterface, ManagerAllocatorInterface, ContainerAllocatorInterface
{
    use ResponseFactoryTrait,
        ContainerAllocatorTrait,
        ManagerAllocatorTrait;

    use RouteFactoryTrait {
        getRouteFactory as getDefaultRouteFactory;
    }

    public const REGEX_DELIMITER = [
        self::DEFAULT_DELIMITER,
        '~',
        '@',
        '=',
        '+',
        '`',
        '%'
    ];

    public const DEFAULT_DELIMITER = '#';

    protected array $registeredControllers = [];

    protected string $basePath = '';

    /**
     * @var array<RouteInterface>
     */
    protected array $registeredRoutes = [];

    /**
     * @var array<RouteInterface>
     */
    protected array $deferredRoutes = [];

    protected array $registeredRoutesByMethod = [];

    protected array $deferredRoutesByMethod = [];

    protected array $prefixes = [];

    ///**
    // * @var array{0:array, 1:string, 2:string}
    // */
    //protected array $matchedParams = [];

    private bool $dispatched = false;

    protected bool $dispatchAllHttpMethod = false;

    protected RouteFactoryInterface $routeFactory;

    public function __construct(
        ContainerInterface $container,
        ?ManagerInterface $manager = null
    ) {
        $this->setContainer($container);
        if (!$manager && $container->has(ManagerInterface::class)) {
            try {
                $manager = $container->get(ManagerInterface::class);
            } catch (Throwable) {
            }

            if (!$manager instanceof ManagerInterface) {
                $manager = null;
            }
        }
        if ($manager) {
            $this->setManager($manager);
        }
    }

    public function getContainer(): ContainerInterface
    {
        return $this->containerObject;
    }

    public function isDispatchAllHttpMethod(): bool
    {
        return $this->dispatchAllHttpMethod;
    }

    public function setDispatchAllHttpMethod(bool $dispatchAllHttpMethod): void
    {
        $this->dispatchAllHttpMethod = $dispatchAllHttpMethod;
    }

    public function getRouteFactory() : RouteFactoryInterface
    {
        $this->routeFactory ??= $this->getDefaultRouteFactory();
        return $this->routeFactory;
    }

    public function setRouteFactory(RouteFactoryInterface $routeFactory): void
    {
        $this->routeFactory = $routeFactory;
    }

    /**
     * @return bool
     */
    public function isDispatched(): bool
    {
        return $this->dispatched;
    }

    public function group(string $pattern, callable $callback): static
    {
        $this->prefixes[] = $pattern;
        if ($callback instanceof Closure) {
            try {
                $ref = new ReflectionFunction($callback);
                if (!$ref->isStatic() && !$ref->getClosureThis()) {
                    $callback = $callback->bindTo($this);
                }
            } catch (ReflectionException) {
            }
        }
        $callback($this);
        array_pop($this->prefixes);
        return $this;
    }

    public function addRoute(
        Route $route
    ): static {
        $id = spl_object_hash($route);
        if ($this->isDispatched()) {
            $this->deferredRoutes[$id] = $route;
            foreach ($route->getMethods() as $method) {
                $this->deferredRoutesByMethod[$method][$id] = $route->getPriority();
            }
        } else {
            $this->registeredRoutes[$id] = $route;
            foreach ($route->getMethods() as $method) {
                $this->registeredRoutesByMethod[$method][$id] = $route->getPriority();
            }
        }

        return $this;
    }

    public function removeRoute(
        Route $route
    ) : bool {
        $id = spl_object_hash($route);
        if (!isset($this->registeredRoutes[$id])
            && !isset($this->deferredRoutes[$id])
        ) {
            return false;
        }

        foreach ($this->registeredRoutesByMethod as $method => $routeIds) {
            unset($this->registeredRoutesByMethod[$method][$id]);
            if (empty($this->registeredRoutesByMethod[$method])) {
                unset($this->registeredRoutesByMethod[$method]);
            }
        }

        foreach ($this->deferredRoutesByMethod as $method => $routeIds) {
            unset($this->deferredRoutesByMethod[$method][$id]);
            if (empty($this->deferredRoutesByMethod[$method])) {
                unset($this->deferredRoutesByMethod[$method]);
            }
        }

        unset($this->registeredRoutes[$id], $this->deferredRoutes[$id]);
        return true;
    }

    public function hasRoute(Route $route): bool
    {
        return isset($this->registeredRoutes[spl_object_hash($route)]);
    }

    /**
     * @return array<Route>
     */
    public function getRegisteredRoutes(): array
    {
        return $this->registeredRoutes;
    }

    /**
     * @return array<Route>
     */
    public function getDeferredRoutes(): array
    {
        return $this->deferredRoutes;
    }

    public function isDeferred(Route $route): bool
    {
        return isset($this->deferredRoutes[spl_object_hash($route)]);
    }

    /**
     * @param string|ControllerInterface $controller
     * @return array<Route>
     */
    public function addRouteController(string|ControllerInterface $controller): array
    {
        try {
            $ref = new ReflectionClass($controller);
        } catch (Throwable $e) {
            throw new InvalidArgumentException(
                $e->getMessage()
            );
        }

        if (!$ref->isSubclassOf(ControllerInterface::class)) {
            throw new InvalidArgumentException(
                sprintf('Argument must be subclass of %s', ControllerInterface::class)
            );
        }

        $group = $ref->getAttributes(
            Group::class,
            ReflectionAttribute::IS_INSTANCEOF
        )[0]??null;
        $prefix = $group?->newInstance()->getPattern()??'';
        $routes = [];
        $className = $ref->getName();
        foreach ($ref->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== $className) {
                continue;
            }

            $attributes = $method->getAttributes(
                HttpMethodAttributeInterface::class,
                ReflectionAttribute::IS_INSTANCEOF
            );

            $methodName = $method->getName();
            if (isset($this->registeredControllers[$className][$methodName])) {
                foreach ($this->registeredControllers[$className][$methodName] as $id) {
                    if (isset($this->registeredRoutes[$id])) {
                        unset($this->registeredRoutes[$id]);
                    }
                }
            }

            $this->registeredControllers[$className][$methodName] = [];
            foreach ($attributes as $attribute) {
                try {
                    $attribute = $attribute->newInstance();
                } catch (Throwable $e) {
                    throw new RouteControllerException(
                        $this,
                        $e,
                        $e->getMessage()
                    );
                }
                if (!$attribute instanceof HttpMethodAttributeInterface) {
                    continue;
                }
                $pattern = $attribute->getPattern();
                // if (str_ends_with($prefix, '/') && $pattern[0]??null === '/') {
                //    $pattern = substr($pattern, 1);
                //}
                $route = $this->map(
                    methods: $attribute->getMethods(),
                    pattern: $prefix . $pattern,
                    controller: [$controller, $method->getName()],
                    priority: $attribute->getPriority(),
                    name: $attribute->getName(),
                    hostName: $attribute->getHostName()
                );

                $id = spl_object_hash($route);
                $this->registeredControllers[$className][$methodName][] = $id;
                $routes[$id] = $route;
            }
        }
        return $routes;
    }

    public function map(
        array|string $methods,
        string $pattern,
        callable|array $controller,
        ?int $priority = null,
        ?string $name = null,
        ?string $hostName = null
    ): RouteInterface {
        $prefix = implode('/', $this->prefixes);
        $route = $this->getRouteFactory()->createRoute(
            $methods,
            $prefix . $pattern,
            $controller,
            $priority,
            $name,
            $hostName
        );

        $this->addRoute($route);
        return $route;
    }

    /**
     * Handle route on CLI method
     *
     * @param string $pattern
     * @param callable|array $controller
     * @param int|null $priority
     * @param string|null $name
     * @param string|null $hostName
     * @return RouteInterface
     */
    public function cli(
        string $pattern,
        callable|array $controller,
        ?int $priority = null,
        ?string $name = null,
        ?string $hostName = null
    ): RouteInterface {
        return $this->map('CLI', $pattern, $controller, $priority, $name, $hostName);
    }

    public function get(
        string $pattern,
        callable|array $controller,
        ?int $priority = null,
        ?string $name = null,
        ?string $hostName = null
    ): RouteInterface {
        return $this->map('GET', $pattern, $controller, $priority, $name, $hostName);
    }

    public function any(
        string $pattern,
        callable|array $controller,
        ?int $priority = null,
        ?string $name = null,
        ?string $hostName = null
    ): RouteInterface {
        return $this->map('ANY', $pattern, $controller, $priority, $name, $hostName);
    }

    public function post(
        string $pattern,
        callable|array $controller,
        ?int $priority = null,
        ?string $name = null,
        ?string $hostName = null
    ): RouteInterface {
        return $this->map('POST', $pattern, $controller, $priority, $name, $hostName);
    }
    public function put(
        string $pattern,
        callable|array $controller,
        ?int $priority = null,
        ?string $name = null,
        ?string $hostName = null
    ): RouteInterface {
        return $this->map('PUT', $pattern, $controller, $priority, $name, $hostName);
    }

    public function delete(
        string $pattern,
        callable|array $controller,
        ?int $priority = null,
        ?string $name = null,
        ?string $hostName = null
    ): RouteInterface {
        return $this->map('DELETE', $pattern, $controller, $priority, $name, $hostName);
    }

    public function head(
        string $pattern,
        callable|array $controller,
        ?int $priority = null,
        ?string $name = null,
        ?string $hostName = null
    ): RouteInterface {
        return $this->map('HEAD', $pattern, $controller, $priority, $name, $hostName);
    }
    public function options(
        string $pattern,
        callable|array $controller,
        ?int $priority = null,
        ?string $name = null,
        ?string $hostName = null
    ): RouteInterface {
        return $this->map('OPTIONS', $pattern, $controller, $priority, $name, $hostName);
    }

    public function connect(
        string $pattern,
        callable|array $controller,
        ?int $priority = null,
        ?string $name = null,
        ?string $hostName = null
    ): RouteInterface {
        return $this->map('CONNECT', $pattern, $controller, $priority, $name, $hostName);
    }

    public function patch(
        string $pattern,
        callable|array $controller,
        ?int $priority = null,
        ?string $name = null,
        ?string $hostName = null
    ): RouteInterface {
        return $this->map('PATCH', $pattern, $controller, $priority, $name, $hostName);
    }

    public function trace(
        string $pattern,
        callable|array $controller,
        ?int $priority = null,
        ?string $name = null,
        ?string $hostName = null
    ): RouteInterface {
        return $this->map('TRACE', $pattern, $controller, $priority, $name, $hostName);
    }

    private function resolveBasePath(ServerRequestInterface $request): void
    {
        if ($this->getBasePath() !== '') {
            return;
        }
        if (Consolidation::isCli()) {
            $this->setBasePath('/');
            return;
        }

        $serverParams = $request->getServerParams();
        $documentRoot = $serverParams['DOCUMENT_ROOT'];
        if (empty($documentRoot)) {
            $this->setBasePath('/');
            return;
        }
        $scriptFileName = null;
        if (isset($_SERVER['SCRIPT_FILENAME'])) {
            $scriptFileName = realpath(dirname($_SERVER['SCRIPT_FILENAME']));
            $scriptFileName = !str_starts_with($scriptFileName, $documentRoot)
                ? null
                : $scriptFileName;
        }

        if (($scriptFileName??null) === null
            && defined('TD_INDEX_FILE')
            && is_string(TD_INDEX_FILE)
            && is_file(TD_INDEX_FILE)
            && ($index = realpath(TD_INDEX_FILE)) !== false
            && str_starts_with($index, $documentRoot)
        ) {
            $scriptFileName = dirname($index);
        }
        if ($scriptFileName === null) {
            $this->setBasePath('/');
            return;
        }
        $path = substr($documentRoot, strlen($scriptFileName));
        $this->setBasePath($path?:'/');
    }

    /**
     * @param ServerRequestInterface $request
     * @return MatchedRouteInterface|HttpExceptionInterface
     */
    public function dispatch(ServerRequestInterface $request): MatchedRouteInterface|HttpExceptionInterface
    {
        $this->resolveBasePath($request);
        $manager = $this->getManager();
        // @dispatch(router.beforeDispatch)
        $manager
            ?->dispatch(
                'router.beforeDispatch',
                $this,
                $request
            );
        try {
            // @dispatch(router.request)
            $dispatchedRequest = $manager
                ?->dispatch('router.request', $request, $this);
            $dispatchedRequest = $dispatchedRequest instanceof ServerRequestInterface
                ? $dispatchedRequest
                : $request;

            // @dispatch(router.beforeLooping)
            $manager
                ?->dispatch(
                    'router.beforeLooping',
                    $this,
                    $dispatchedRequest,
                    $request
                );

            // force override
            $httpMethod = $dispatchedRequest->getMethod();
            $dispatchAll = $this->isDispatchAllHttpMethod();

            // @dispatch(route.use.dispatchAllHttpMethod)
            $dispatchAllMethod = $manager?->dispatch(
                'router.useAllHttpMethod',
                $dispatchAll,
                $this,
                $dispatchedRequest,
                $request
            );

            $dispatchAllMethod = is_bool($dispatchAllMethod)
                ? $dispatchAllMethod
                : $dispatchAll;
            if ($dispatchAllMethod !== true) {
                $routes = $this->registeredRoutesByMethod['*'] ?? [];
                $routes = array_merge($routes, $this->registeredRoutesByMethod[$httpMethod] ?? []);
                asort($routes);
            } else {
                // sorting
                uasort($this->registeredRoutes, static function (Route $a, Route $b) {
                    return $a->getPriority() === $b->getPriority() ? 0 : (
                    $a->getPriority() < $b->getPriority() ? -1 : 1
                    );
                });
                $routes = $this->registeredRoutes;
            }

            $matchedParams = null;
            $matchedRoute = null;
            // foreach ($this->getRegisteredRoutes() as $id => $route) {
            foreach ($routes as $id => $priority) {
                unset($routes[$id]);
                $route = $this->registeredRoutes[$id];
                try {
                    $matchedParams = $this->matchRouteParamByRequest($route, $request);
                } catch (MethodNotAllowedException $e) {
                    $matchedRoute = $e;
                    break;
                }
                if ($matchedParams !== null) {
                    $matchedRoute = $route;
                    break;
                }
            }

            // @dispatch(router.looping)
            $manager?->dispatch(
                'router.looping',
                $this,
                $dispatchedRequest,
                $request,
                $matchedRoute
            );
            $matchedRoute = $matchedRoute instanceof RouteInterface
                ? new MatchedRoute($dispatchedRequest, $this, $matchedRoute, $matchedParams)
                : null;
            $matchedRoute = ! $matchedRoute ? new NotFoundException($request) : $matchedRoute;

            // @dispatch(route.afterLooping)
            $manager?->dispatch(
                'router.afterLooping',
                $this,
                $dispatchedRequest,
                $request,
                $matchedRoute
            );

            // @dispatch(router.match|router.notFound|router.methodNotAllowed)
            $manager?->dispatch(
                $matchedRoute instanceof MatchedRouteInterface
                    ? 'router.match' : (
                $matchedRoute instanceof MethodNotAllowedException
                    ? 'router.methodNotAllowed'
                    : 'router.notFound'
                ),
                $this,
                $dispatchedRequest,
                $request,
                $matchedRoute
            );
            return $matchedRoute;
        } finally {
            // @dispatch(route.afterDispatch)
            $manager
                ?->dispatch(
                    'router.afterDispatch',
                    $this,
                    $request
                );
        }
    }

    public function setBasePath(string $path): void
    {
        if (trim($path) === '') {
            $this->basePath = '';
            return;
        }
        $path = '/'. trim($path, '/');
        $this->basePath = $path;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * @param Route $route
     * @param ServerRequestInterface $request
     * @return ?array{array, string, string}
     * @throws MethodNotAllowedException
     */
    public function matchRouteParamByRequest(Route $route, ServerRequestInterface $request): ?array
    {
        $uri = $request->getUri();
        $hostName = strtolower($uri->getHost());
        if ($hostName && ($host = $route->getHostName())
            && strtolower($host) !== $hostName
        ) {
            $length = strlen($host);
            $delimiter = substr($host, 0, 1);
            // check regex
            if ($length < 3
                || !in_array($delimiter, self::REGEX_DELIMITER)
                || ! ($delimiterQuoted = preg_quote($delimiter, '~'))
                || ! preg_match(
                    "~^$delimiterQuoted.+{$delimiterQuoted}[imsxADSUXJun]*$~s",
                    $host,
                    $match
                )
            ) {
                return null;
            }
            if (!preg_match($host, $hostName, $m, PREG_NO_ERROR)) {
                return null;
            }
        }

        $basePath = $this->getBasePath();
        $path     = $uri->getPath();
        if (($path[0]??'') !== '/') {
            $path .= '/'.$path;
        }
        // stop this if not match
        if (!str_starts_with($path, $basePath)) {
            return null;
        }

        $basePath = rtrim($basePath, '/');
        $path = substr($path, strlen($basePath));
        if ($path === '') {
            $path = '/';
        } elseif ($path[0] !== '/') {
            return null;
        }

        $compiledPattern = $route->getCompiledPattern();
        $delimiter = substr($compiledPattern, 0, 1);
        $useRegex = false;
        // use regex pattern with "#" delimiter
        // eg: $router->get('#^/hello/world$#');
        if ($delimiter !== ''
            && in_array($delimiter, self::REGEX_DELIMITER)
            && str_starts_with($compiledPattern, $delimiter)
            && (
                str_ends_with($compiledPattern, $delimiter)
                || (
                    ($delimiterQuoted = preg_quote($delimiter, '~'))
                    && preg_match(
                        "~^$delimiterQuoted.+{$delimiterQuoted}[imsxADSUXJun]*$~s",
                        $compiledPattern
                    )
                )
            )
        ) {
            $useRegex = $delimiter === self::DEFAULT_DELIMITER;
            $compiledPattern = $useRegex
                ? $compiledPattern
                : substr($compiledPattern, 1, -1);
        }

        $pattern = $compiledPattern;
        $skip_first = false;
        $skip_last = false;
        if (!$useRegex) {
            $compiledPattern = addcslashes($compiledPattern, '#');
            $pattern = $compiledPattern;
            // if contains start with "^", eg: ^/path
            $skip_first = str_starts_with($pattern, '^');
            // if contains $, eg: path/$
            $skip_last = str_ends_with($pattern, '$');
            if (!$skip_first) {
                if ($pattern[0]??null === '/') {
                    $pattern = ltrim($pattern, '/');
                    $pattern = "^(?:/+)?$pattern";
                } else {
                    $pattern = "^(?:/*)?$pattern";
                }
            }
            if (!$skip_last) {
                if (preg_match('#(^|[^/])?(?:/|\[/])$#', $pattern)) {
                    $pattern .= '?';
                }
                $pattern .= '(/*)?$';
            }
            $pattern = "#$pattern#";
        }

        set_error_handler(static fn () => null);
        preg_match(
            $pattern,
            $path,
            $match,
            PREG_NO_ERROR
        );
        // restore
        restore_error_handler();
        if (empty($match)) {
            return null;
        }
        if (!$route->containMethod($request->getMethod())) {
            $exception = new MethodNotAllowedException(
                $request
            );

            $exception->setAllowedMethods($route->getMethods());
            throw $exception;
        }

        $first = '';
        $last = '';
        if (!$useRegex) {
            if (!$skip_first) {
                $groupingPattern = preg_replace(
                    '~^#\^\(\?:([^)]+\))(.+)$~',
                    '#^($1$2',
                    $pattern
                );
                $matched = $match[0];
                // rematch with grouping
                preg_match($groupingPattern, $matched, $m);
                $first = $m[1]??'';
            }
            if (!$skip_last) {
                $last = array_pop($match);
            }
        }

        return [
            $match,
            $first,
            $last
        ];
    }

    public function __debugInfo(): ?array
    {
        return Consolidation::debugInfo($this);
    }
}
