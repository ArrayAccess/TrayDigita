<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Handler;

use ArrayAccess\TrayDigita\Handler\Interfaces\ErrorHandlerInterface;
use ArrayAccess\TrayDigita\Http\Code;
use ArrayAccess\TrayDigita\Http\Exceptions\HttpException;
use ArrayAccess\TrayDigita\Http\Factory\ResponseFactory;
use ArrayAccess\TrayDigita\Http\RequestResponseExceptions\MethodNotAllowedException;
use ArrayAccess\TrayDigita\Util\Filter\Consolidation;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use ArrayAccess\TrayDigita\View\ErrorRenderer\HtmlTraceAbleErrorRenderer;
use ArrayAccess\TrayDigita\View\ErrorRenderer\JsonErrorRenderer;
use ArrayAccess\TrayDigita\View\Interfaces\ErrorRendererInterface;
use ArrayAccess\TrayDigita\View\Interfaces\ViewInterface;
use ErrorException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use function current;
use function explode;
use function implode;
use function is_string;
use function next;
use function preg_match;
use function sprintf;
use function strtolower;
use function trim;
use const E_COMPILE_ERROR;
use const E_ERROR;

class ErrorHandler implements ErrorHandlerInterface
{
    protected ?LoggerInterface $logger;

    private ?Throwable $exception = null;

    protected ?string $contentType = null;

    protected string $defaultContentType = 'text/html';

    protected string $defaultRenderer = HtmlTraceAbleErrorRenderer::class;

    protected bool $displayErrorDetails = false;

    protected bool $logNonError = false;

    protected array $errorRenderers = [
        'application/json' => JsonErrorRenderer::class,
        // 'application/xml'  => XmlErrorRenderer::class,
        //'text/xml'         => XmlErrorRenderer::class,
        // 'text/html'        => HtmlErrorRenderer::class,
        'text/html'        => HtmlTraceAbleErrorRenderer::class,
//        'text/plain'       => PlainTextErrorRenderer::class,
    ];

    protected ResponseFactoryInterface $responseFactory;

    public function __construct(
        protected ?ContainerInterface $container = null,
        ?LoggerInterface $logger = null,
        ?ResponseFactoryInterface $responseFactory = null
    ) {
        if ($this->container) {
            $logger ??= ContainerHelper::use(
                LoggerInterface::class,
                $this->container
            );
            $responseFactory = ContainerHelper::use(
                ResponseFactoryInterface::class,
                $this->container
            );
        }
        $logger && $this->setLogger($logger);
        $this->responseFactory = $responseFactory??new ResponseFactory();
    }

    public function isLogNonError(): bool
    {
        return $this->logNonError;
    }

    public function setLogNonError(bool $logNonError): void
    {
        $this->logNonError = $logNonError;
    }

    public function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }

    public function setLogger(?LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function getContainer(): ?ContainerInterface
    {
        return $this->container;
    }

    public function setErrorRenderer(string $type, string|ErrorRendererInterface $renderer): void
    {
        $type = strtolower($type);
        $this->errorRenderers[$type] = $renderer;
    }

    public function determineStatusCode(ServerRequestInterface $request): int
    {
        if ($request->getMethod() === 'OPTIONS') {
            return 200;
        }

        if ($this->exception instanceof HttpException) {
            $code = $this->exception->getCode();
            if ($code < 100 || $code >= 600) {
                $code = 500;
            }
            return $code;
        }

        return 500;
    }

    protected function determineContentType(ServerRequestInterface $request) : ?string
    {
        $acceptHeader = strtolower($request->getHeaderLine('Accept'));
        $acceptHeaderList = explode(',', $acceptHeader);
        $current = current($acceptHeaderList);
        $contentType = null;
        do {
            if (!is_string($current) || ($current = trim($current)) === '') {
                continue;
            }
            $contentType = $current;
            if ($current === 'text/plain') {
                continue;
            }
            if (isset($this->errorRenderers[$current])) {
                return $current;
            }
        } while (($current = next($acceptHeaderList)) !== false);

        unset($current);

        if (is_string($contentType) && isset($this->errorRenderers[$contentType])) {
            return $contentType;
        }

        if (preg_match('/\+(json|xml)/', $acceptHeader, $matches)) {
            $mediaType = 'application/' . $matches[1];
            if (isset($this->errorRenderers[$mediaType])) {
                return $mediaType;
            }
        }

        return null;
    }

    protected function respond(ServerRequestInterface $request) : ResponseInterface
    {
        $renderer = $this->defaultRenderer;
        $contentType = $this->defaultContentType;
        if ($this->contentType && isset($this->errorRenderers[$this->contentType])) {
            $renderer = $this->errorRenderers[$this->contentType];
            $contentType = $this->contentType;
        }

        $response = $this
            ->responseFactory
            ->createResponse($this->determineStatusCode($request))
            ->withHeader('Content-Type', $contentType);
        if ($this->exception instanceof MethodNotAllowedException) {
            $allowedMethods = implode(', ', $this->exception->getAllowedMethods());
            $response = $response->withHeader('Allow', $allowedMethods);
        }

        if (is_string($renderer)) {
            $renderer = new $renderer($this);
        }

        $body = $renderer($request, $this->exception, $this->displayErrorDetails);
        if ($body) {
            $response->getBody()->write($body);
        }
        return $response;
    }

    protected int $inLoopError = 0;

    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails
    ): ResponseInterface {
        $this->exception = $exception;
        $this->displayErrorDetails = $displayErrorDetails;
        $this->contentType ??= $this->determineContentType($request);
        $logging = true;
        $logNonError = false;
        $view = ContainerHelper::use(ViewInterface::class, $this->getContainer());
        if ($exception instanceof HttpException) {
            if ($exception->getCode() > 400
                && $exception->getCode() < 500
            ) {
                $logging = $this->isLogNonError();
                $logNonError = $logging;
            }
            $view?->setParameter('title', $exception->getTitle());
        } else {
            $view?->setParameter(
                'title',
                sprintf('500 %s', Code::statusMessage(500))
            );
        }

        /**
         * disable respond on cli and the exception is @uses HttpException
         * direct throw on command line interface
         */
        if (Consolidation::isCli() && !$exception instanceof HttpException) {
            throw $exception;
        }

        try {
            if ($logging) {
                // log as critical
                if (($exception instanceof ErrorException
                        && ($exception->getSeverity() === E_ERROR
                            || $exception->getSeverity() === E_COMPILE_ERROR
                        ))
                    || (
                        !$exception instanceof HttpException
                        && (
                            $exception->getCode() === E_ERROR
                            || $exception->getCode() === E_COMPILE_ERROR
                        )
                    )
                ) {
                    $this->getLogger()?->critical($exception);
                } else {
                    $logNonError // if log non error
                        ? $this->getLogger()?->notice($exception)
                        : $this->getLogger()?->error($exception);
                }
            }
            return $this->respond($request);
        } catch (Throwable $e) {
            if (!$this->inLoopError) {
                $this->inLoopError++;
                return $this($request, $e, $displayErrorDetails);
            }
            return $this->errorFallback(
                $request,
                $e,
                $displayErrorDetails
            );
        }
    }

    private function errorFallback(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails
    ) : ResponseInterface {
        $response = $this
            ->responseFactory
            ->createResponse(500)
            ->withHeader('Content-Type', 'text/html');
        $htmlRenderer = new HtmlTraceAbleErrorRenderer($this);
        $html = $htmlRenderer($request, $exception, $displayErrorDetails);

        $response->getBody()->write($html);
        return $response;
    }
}
