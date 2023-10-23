<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Routing;

use ArrayAccess\TrayDigita\Container\ContainerWrapper;
use ArrayAccess\TrayDigita\Container\Interfaces\SystemContainerInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerAllocatorInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Http\Code;
use ArrayAccess\TrayDigita\Http\Exceptions\HttpException;
use ArrayAccess\TrayDigita\Module\Interfaces\ModuleInterface;
use ArrayAccess\TrayDigita\Module\Modules;
use ArrayAccess\TrayDigita\Responder\Interfaces\FileResponderInterface;
use ArrayAccess\TrayDigita\Routing\Interfaces\ControllerInterface;
use ArrayAccess\TrayDigita\Routing\Interfaces\RouteInterface;
use ArrayAccess\TrayDigita\Routing\Interfaces\RouterInterface;
use ArrayAccess\TrayDigita\Traits\Http\ResponseFactoryTrait;
use ArrayAccess\TrayDigita\Traits\Http\StreamFactoryTrait;
use ArrayAccess\TrayDigita\Traits\Responder\HtmlResponderFactoryTrait;
use ArrayAccess\TrayDigita\Traits\Responder\JsonResponderFactoryTrait;
use ArrayAccess\TrayDigita\Traits\View\ViewTrait;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use ArrayAccess\TrayDigita\Util\Filter\DataType;
use JsonSerializable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use ReflectionException;
use ReflectionMethod;
use Stringable;
use Throwable;
use function array_values;
use function is_int;
use function is_iterable;
use function is_object;
use function is_string;
use function json_decode;
use function method_exists;
use const JSON_THROW_ON_ERROR;

abstract class AbstractController implements ControllerInterface
{
    use ResponseFactoryTrait,
        StreamFactoryTrait,
        HtmlResponderFactoryTrait,
        JsonResponderFactoryTrait,
        ViewTrait;

    private bool $dispatched = false;

    private ?RouteInterface $route = null;

    private ?ResponseInterface $response = null;

    protected ?SystemContainerInterface $container = null;

    protected ?ManagerInterface $manager = null;

    protected bool $asJSON = false;

    protected ?int $statusCode = null;

    final public function __construct(public readonly RouterInterface $router)
    {
        $container = $this->router->getContainer();
        $this->container = $container ? ContainerWrapper::maybeContainerOrCreate($container) : null;
        if ($this instanceof ManagerAllocatorInterface) {
            $manager = ContainerHelper::use(ManagerInterface::class, $this->container);
            if ($manager) {
                $this->setManager($manager);
            }
        }
    }

    /**
     * @template M as ModuleInterface
     * @psalm-param class-string<M> $moduleClassName
     * @psalm-return ?M
     */
    public function getModule(string $moduleClassName): ?ModuleInterface
    {
        return ContainerHelper::service(Modules::class, $this->getContainer())
            ?->get($moduleClassName);
    }

    /**
     * @template T of object
     * @psalm-param object|object<T>|class-string<T>|string $expect
     * @return T|mixed
     * @noinspection PhpUnused
     */
    public function expectContainer(object|string $expect)
    {
        return ContainerHelper::use(
            $expect,
            $this->getContainer()
        );
    }

    /**
     * @template T of object
     * @param string $expect
     * @return T|mixed
     * @noinspection PhpUnused
     */
    public function serviceContainer(string $expect)
    {
        return ContainerHelper::service(
            $expect,
            $this->getContainer()
        );
    }

    /**
     * Html render view
     *
     * @param string $path
     * @param array $variable
     * @param ResponseInterface|null $response
     * @return ResponseInterface
     */
    public function render(
        string $path,
        array $variable = [],
        ?ResponseInterface $response = null
    ): ResponseInterface {
        if ($response) {
            $this->statusCode = $response->getStatusCode();
        }
        $variable['controller'] ??= $this;
        $this->asJSON = false;
        return $this->getView()->serve(
            $path,
            $variable,
            $response
        );
    }

    /**
     * Render json message
     *
     * @param int $code
     * @param mixed $data
     * @param ResponseInterface|null $response
     * @return ResponseInterface
     */
    public function renderJson(
        int $code,
        mixed $data,
        ?ResponseInterface $response = null
    ): ResponseInterface {
        $this->asJSON = true;
        $this->statusCode = $code;
        return $this
            ->getJsonResponder()
            ->serve(
                $code,
                $data,
                $response
            );
    }

    public function getContainer(): SystemContainerInterface|null
    {
        if (!$this->container && ($container = $this->router->getContainer())) {
            $this->container = ContainerWrapper::maybeContainerOrCreate($container);
        }
        return $this->container;
    }

    public function getManager(): ?ManagerInterface
    {
        if (!$this->manager) {
            $this->manager = ContainerHelper::use(
                ManagerInterface::class,
                $this->getContainer()
            );
        }
        return $this->manager;
    }

    public function redirect(
        string|UriInterface $uri,
        int $code = 302,
        ?ResponseInterface $response = null
    ) : ResponseInterface {
        $code =  $code === 301 ? 301 : 302;
        $response ??= $this->getResponseFactory()->createResponse($code);
        return DataType::appendNoCache($response)
            ->withHeader(
                'Location',
                (string) $uri
            );
    }

    /**
     * @return bool
     */
    final public function isDispatched(): bool
    {
        return $this->dispatched;
    }

    public function getRoute(): ?RouteInterface
    {
        return $this->route;
    }

    /**
     * @throws ReflectionException
     */
    public function dispatch(
        RouteInterface $route,
        ServerRequestInterface $request,
        string $method,
        ...$arguments
    ): ResponseInterface {
        if ($this->isDispatched()) {
            return $this->response;
        }
        $this->dispatched = true;
        $this->route = $route;
        // set current controller
        $this->getContainer()
            ?->setParameter(
                'currentController',
                $this
            );
        $this->getView()?->setRequest($request);
        $manager = $this->router->getManager();
        $response = $this->beforeDispatch($request, $method, ...$arguments);

        // @dispatch(controller.beforeDispatch)
        $response = $manager ? $manager->dispatch(
            'controller.beforeDispatch',
            $response,
            $this,
            $request,
            $method,
            ...$arguments
        ) : $response;
        try {
            if (!is_string($response)
                && !$response instanceof ResponseInterface
                && !$response instanceof Stringable
                && !(is_object($response) && method_exists($response, '__toString'))
            ) {
                $response = $this->getResponseFactory()->createResponse();
                $refMethod = new ReflectionMethod($this, $method);
                $method = $refMethod->getName();
                $resolver = ContainerWrapper::maybeContainerOrCreate(
                    $this->getContainer()
                )->getResolver();
                $_arguments = [
                    ServerRequestInterface::class => $request,
                    ResponseInterface::class => $response
                ] + $arguments;
                try {
                    $_arguments = $resolver->resolveArguments(
                        new ReflectionMethod($this, $method),
                        $_arguments,
                        array_values($_arguments)
                    );
                } catch (Throwable) {
                    $_arguments = array_values($_arguments);
                }
                try {
                    if ($refMethod->isPrivate()) {
                        $response = (function ($method, ...$arguments) {
                            return $this->$method(...$arguments);
                        })->call(
                            $this,
                            $resolver,
                            $refMethod->getName(),
                            ...$_arguments
                        );
                    } else {
                        $response = $this->{$refMethod->getName()}(...$_arguments);
                    }
                } catch (HttpException $e) {
                    $response = $e;
                    if (Code::statusMessage($e->getCode()) !== null) {
                        $this->statusCode = $e->getCode();
                    } else {
                        $this->statusCode = 500;
                    }
                }
            }
            $statusCode = 200;
            if (is_int($this->statusCode)
                && Code::statusMessage($this->statusCode) !== null
            ) {
                $statusCode = $this->statusCode;
            }
            if ($response instanceof FileResponderInterface) {
                // stop here
                $response->send($request);
            }

            if (!$response instanceof ResponseInterface) {
                $this->response ??= $this->getResponseFactory()->createResponse($statusCode);
                if (is_iterable($response)
                    || $response instanceof JsonSerializable
                    || $this->asJSON && $response instanceof Throwable
                ) {
                    $this->response = $this
                        ->getJsonResponder()
                        ->serve(
                            $this->response->getStatusCode(),
                            $response,
                            $this->response
                        );
                } elseif ($response instanceof StreamInterface) {
                    if ($this->asJSON) {
                        try {
                            json_decode(
                                (string) $response,
                                flags: JSON_THROW_ON_ERROR
                            );
                            $this->response = $this
                                ->getJsonResponder()
                                ->appendContentType($this->response);
                        } catch (Throwable) {
                        }
                    }
                    $this->response = $this
                        ->response
                        ->withBody($response);
                } elseif (is_string($response)
                    || $response instanceof Stringable
                    || is_object($response) && method_exists($response, '__tostring')
                ) {
                    $useJson = false;
                    if ($this->asJSON) {
                        try {
                            json_decode(
                                (string) $response,
                                flags: JSON_THROW_ON_ERROR
                            );
                            $useJson = true;
                            $body = $this->response->getBody()->isWritable()
                                ? $this->response->getBody()
                                : $this->getStreamFactory()->createStream();
                            $body->write($response);
                            $this->response = $this->getJsonResponder()
                                ->appendContentType(
                                    $this->response->withBody($body)
                                );
                        } catch (Throwable) {
                        }
                    }
                    if (!$useJson) {
                        $response = (string) $response;
                        $this->response = $this
                            ->getHtmlResponder()
                            ->serve($statusCode, $response, $this->response);
                    }
                } elseif ($this->asJSON) {
                    $this->response = $this
                        ->getJsonResponder()
                        ->serve(
                            $this->response->getStatusCode(),
                            $response,
                            $this->response
                        );
                } else {
                    $this->response = $this
                        ->getHtmlResponder()
                        ->serve($statusCode, $response, $this->response);
                }
            } else {
                $this->response = $response;
            }

            // @dispatch(controller.dispatch)
            $manager?->dispatch(
                'controller.dispatch',
                $this->response,
                $this,
                $request,
                $method,
                ...$arguments
            );

            $this->response = $this->afterDispatch(
                $this->response,
                $request,
                $method,
                ...$arguments
            );
        } finally {
            // @dispatch(controller.afterDispatch)
            $response = $manager?->dispatch(
                'controller.afterDispatch',
                $this->response,
                $this,
                $request,
                $method,
                ...$arguments
            );
        }

        if ($response instanceof ResponseInterface) {
            $this->response = $response;
        }

        return $this->response;
    }

    /**
     * Method called before route match called.
     * This method should be overridden when controller need to stop the method execution.
     * To stop execution the return values must be:
     * 1. string
     * 2. @param ServerRequestInterface $request
     * @param string $method
     * @param array $arguments
     * @uses ResponseInterface
     * 3. returning array|@uses JsonSerializable and response content type need to be:
     *  regex: /^application/(?:[^+]+\+)?json\s*($|;)/
     *
     */
    public function beforeDispatch(ServerRequestInterface $request, string $method, ...$arguments)
    {
    }

    /**
     * Method called after succeed dispatch
     *
     * @param ResponseInterface $response
     * @param ServerRequestInterface $request
     * @param string $method
     * @param ...$arguments
     * @return ResponseInterface
     */
    public function afterDispatch(
        ResponseInterface $response,
        ServerRequestInterface $request,
        string $method,
        ...$arguments
    ): ResponseInterface {
        return $response;
    }
}
