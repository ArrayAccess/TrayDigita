<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Middleware;

use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Handler\ErrorHandler;
use ArrayAccess\TrayDigita\Handler\Interfaces\ErrorHandlerInterface;
use ArrayAccess\TrayDigita\Handler\Interfaces\ShutdownHandlerInterface;
use ArrayAccess\TrayDigita\Handler\ShutdownHandler;
use ArrayAccess\TrayDigita\Http\Interfaces\HttpExceptionInterface;
use ArrayAccess\TrayDigita\Http\Interfaces\ResponseEmitterInterface;
use ArrayAccess\TrayDigita\Http\ResponseEmitter;
use ArrayAccess\TrayDigita\Traits\Http\ResponseFactoryTrait;
use ArrayAccess\TrayDigita\Traits\Http\ServerRequestFactoryTrait;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use ErrorException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use function error_get_last;
use function get_class;
use function is_bool;
use function is_subclass_of;
use function ob_clean;
use function ob_get_length;
use function ob_get_level;
use function ob_start;
use function register_shutdown_function;
use function set_exception_handler;
use const E_COMPILE_ERROR;
use const E_ERROR;

class ErrorMiddleware extends AbstractMiddleware
{
    use ResponseFactoryTrait,
        ServerRequestFactoryTrait;

    private array $errorHandlers = [];

    private array $subclassErrorHandlers = [];

    protected ?ErrorHandlerInterface $defaultErrorHandler = null;

    private static ?ErrorMiddleware $middlewareInstance = null;

    private ?ServerRequestInterface $request = null;

    private ?Throwable $exceptionHandled = null;

    private ?ShutdownHandlerInterface $shutdownHandler = null;

    protected bool $displayErrorDetails;

    public function __construct(
        ContainerInterface $container,
        ?bool $displayErrorDetails = null
    ) {
        parent::__construct($container);
        if (!self::$middlewareInstance) {
            self::$middlewareInstance = $this;
            register_shutdown_function(static function () {
                self::$middlewareInstance->handleShutdown();
            });
        }

        $this->displayErrorDetails = $displayErrorDetails === true;
        set_exception_handler(function ($e) {
            $this->exceptionHandled = $e;
        });
        $shutdown = ContainerHelper::use(
            ShutdownHandlerInterface::class,
            $this->container
        );
        if ($shutdown) {
            $this->setShutdownHandler($shutdown);
        }
    }

    public static function handlePossibleShutdown(): void
    {
        self::$middlewareInstance?->handleShutdown();
    }

    public function getShutdownHandler(): ?ShutdownHandlerInterface
    {
        return $this->shutdownHandler;
    }

    public function setShutdownHandler(?ShutdownHandlerInterface $shutdownHandler): void
    {
        $this->shutdownHandler = $shutdownHandler;
    }

    private function handleShutdown(): void
    {
        if (!$this->exceptionHandled) {
            $error = error_get_last();
            $type = $error['type'] ?? null;
            if ($type !== E_COMPILE_ERROR && $type !== E_ERROR) {
                return;
            }
            $exception = new ErrorException(
                $error['message'],
                $error['type'],
                $error['type'],
                $error['file'],
                $error['line']
            );
        } else {
            $exception = $this->exceptionHandled;
        }

        if (ob_get_level() > 0 && ob_get_length() > 0) {
            while (ob_get_level() > 0 && ob_get_length() > 0) {
                ob_clean();
            }
            ob_start();
        }

        $displayErrorDetails = $this->displayErrorDetails;
        $request = $this->request;
        if (!$request) {
            $request = $this->getDefaultServerRequest();
        }
        $shutdownHandler = $this->shutdownHandler;
        if (!$shutdownHandler) {
            $shutdownHandler = ContainerHelper::use(
                ShutdownHandlerInterface::class,
                $this->getContainer()
            )??new ShutdownHandler($this->getContainer());
        }

        // no dispatch
        /*
        $manager = ContainerHelper::use(ManagerInterface::class, $this->getContainer());
        // @dispatch(middleware.displayErrorDetails)
        $displayErrorDetails = (bool) ($manager
            ?->dispatch('middleware.displayErrorDetails', $displayErrorDetails)??$displayErrorDetails);
        */
        // $displayErrorDetails = true;
        $response = $shutdownHandler->process(
            $request,
            $exception,
            $this,
            $displayErrorDetails
        );

        $responseEmitter = ContainerHelper::use(ResponseEmitterInterface::class, $this->getContainer())
            ??new ResponseEmitter();
        if ($responseEmitter->isClosed()) {
            return;
        }

        if (!$response) {
            $exceptionType = get_class($exception);
            $handler = $this->getErrorHandler($exceptionType);
            $response = $handler(
                $request,
                $exception,
                $displayErrorDetails
            );
        }

        // no dispatch on shutdown
        /*$newResponse = $manager?->dispatch('response.final', $response);
        if ($newResponse instanceof ResponseInterface) {
            $response = $newResponse;
        }*/

        $responseEmitter->emit($response, true, false);
        $responseEmitter->close();
    }

    public function isDisplayErrorDetails(): bool
    {
        return $this->displayErrorDetails;
    }

    public function setDisplayErrorDetails(bool $displayErrorDetails): void
    {
        $this->displayErrorDetails = $displayErrorDetails;
    }

    /**
     * @return ?LoggerInterface
     */
    public function getLogger(): ?LoggerInterface
    {
        return ContainerHelper::getNull(
            LoggerInterface::class,
            $this->getContainer()
        );
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        try {
            $this->request = $request;
            return $handler->handle($request);
        } catch (Throwable $e) {
            return $this->handleException($request, $e);
        }
    }

    public function handleException(
        ServerRequestInterface $request,
        Throwable $exception
    ): ResponseInterface {
        if ($exception instanceof HttpExceptionInterface) {
            $request = $exception->getRequest();
        }
        $exceptionType = get_class($exception);
        $handler = $this->getErrorHandler($exceptionType);
        $displayErrorDetails = $this->displayErrorDetails;
        $container = $this->getContainer();
        $manager = ContainerHelper::getNull(ManagerInterface::class, $container);
        if ($manager) {
            // @dispatch(middleware.displayErrorDetails)
            $displayErrorDetails = $manager
                ->dispatch('middleware.displayErrorDetails', $displayErrorDetails);
        }

        $displayErrorDetails = is_bool($displayErrorDetails)
            ? $displayErrorDetails
            : $this->displayErrorDetails;
        return $handler(
            $request,
            $exception,
            $displayErrorDetails
        );
    }

    public function addErrorHandler(
        string $type,
        ErrorHandlerInterface $handler,
        bool $handleSubclasses
    ): void {
        if ($handleSubclasses) {
            $this->subclassErrorHandlers[$type] = $handler;
        } else {
            $this->errorHandlers[$type] = $handler;
        }
    }

    public function getErrorHandler(string $type) : ErrorHandlerInterface
    {
        if (isset($this->handlers[$type])) {
            return $this->handlers[$type];
        }

        if (isset($this->subClassHandlers[$type])) {
            return $this->subClassHandlers[$type];
        }

        foreach ($this->subclassErrorHandlers as $class => $handler) {
            if (is_subclass_of($type, $class)) {
                return $handler;
            }
        }

        return $this->getDefaultErrorHandler();
    }

    public function getDefaultErrorHandler(): ErrorHandlerInterface
    {
        if (!$this->defaultErrorHandler) {
            $this->defaultErrorHandler = new ErrorHandler(
                $this->getContainer(),
                $this->getLogger(),
                $this->getResponseFactory()
            );
        }

        return $this->defaultErrorHandler;
    }
}
