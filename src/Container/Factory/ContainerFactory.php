<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Container\Factory;

use ArrayAccess\TrayDigita\Assets\AssetCollection;
use ArrayAccess\TrayDigita\Assets\AssetsJsCssQueue;
use ArrayAccess\TrayDigita\Assets\Interfaces\AssetsCollectionInterface;
use ArrayAccess\TrayDigita\Auth\Cookie\UserAuth;
use ArrayAccess\TrayDigita\Auth\Generator\HashIdentity;
use ArrayAccess\TrayDigita\Auth\Generator\Interfaces\NonceInterface;
use ArrayAccess\TrayDigita\Auth\Generator\Interfaces\NonceRequestInterface;
use ArrayAccess\TrayDigita\Auth\Generator\Nonce;
use ArrayAccess\TrayDigita\Auth\Generator\RequestNonce;
use ArrayAccess\TrayDigita\Auth\Roles\Interfaces\PermissionInterface;
use ArrayAccess\TrayDigita\Auth\Roles\Permission;
use ArrayAccess\TrayDigita\Benchmark\Injector\ManagerProfiler;
use ArrayAccess\TrayDigita\Benchmark\Interfaces\ProfilerInterface;
use ArrayAccess\TrayDigita\Benchmark\Profiler;
use ArrayAccess\TrayDigita\Benchmark\Waterfall;
use ArrayAccess\TrayDigita\Cache\Cache;
use ArrayAccess\TrayDigita\Collection\Config;
use ArrayAccess\TrayDigita\Console\Application;
use ArrayAccess\TrayDigita\Container\Container;
use ArrayAccess\TrayDigita\Container\ContainerWrapper;
use ArrayAccess\TrayDigita\Container\Exceptions\ContainerFrozenException;
use ArrayAccess\TrayDigita\Container\Interfaces\SystemContainerInterface;
use ArrayAccess\TrayDigita\Container\Interfaces\ContainerFactoryInterface;
use ArrayAccess\TrayDigita\Database\Connection;
use ArrayAccess\TrayDigita\Database\DatabaseEventsCollector;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Event\Manager;
use ArrayAccess\TrayDigita\Handler\ErrorHandler;
use ArrayAccess\TrayDigita\Handler\Interfaces\MiddlewareDispatcherInterface;
use ArrayAccess\TrayDigita\Handler\Interfaces\ShutdownHandlerInterface;
use ArrayAccess\TrayDigita\Handler\ShutdownHandler;
use ArrayAccess\TrayDigita\Http\Factory\RequestFactory;
use ArrayAccess\TrayDigita\Http\Factory\ResponseFactory;
use ArrayAccess\TrayDigita\Http\Factory\ServerRequestFactory;
use ArrayAccess\TrayDigita\Http\Factory\StreamFactory;
use ArrayAccess\TrayDigita\Http\Interfaces\ResponseEmitterInterface;
use ArrayAccess\TrayDigita\Http\ResponseEmitter;
use ArrayAccess\TrayDigita\HttpKernel\Interfaces\HttpKernelInterface;
use ArrayAccess\TrayDigita\Image\Factory\ImageResizerFactory;
use ArrayAccess\TrayDigita\Image\ImageResizer;
use ArrayAccess\TrayDigita\Image\Interfaces\ImageResizerFactoryInterface;
use ArrayAccess\TrayDigita\Kernel\HttpKernel;
use ArrayAccess\TrayDigita\Kernel\Interfaces\KernelInterface;
use ArrayAccess\TrayDigita\Kernel\Kernel;
use ArrayAccess\TrayDigita\L10n\Translations\Interfaces\TranslatorInterface;
use ArrayAccess\TrayDigita\L10n\Translations\Translator;
use ArrayAccess\TrayDigita\Logger\Logger;
use ArrayAccess\TrayDigita\Middleware\MiddlewareDispatcher;
use ArrayAccess\TrayDigita\Module\Modules;
use ArrayAccess\TrayDigita\Responder\Factory\HtmlResponderFactory;
use ArrayAccess\TrayDigita\Responder\Factory\JsonResponderFactory;
use ArrayAccess\TrayDigita\Responder\Interfaces\HtmlResponderFactoryInterface;
use ArrayAccess\TrayDigita\Responder\Interfaces\JsonResponderFactoryInterface;
use ArrayAccess\TrayDigita\Routing\Factory\RouteFactory;
use ArrayAccess\TrayDigita\Routing\Interfaces\RouteFactoryInterface;
use ArrayAccess\TrayDigita\Routing\Interfaces\RouterInterface;
use ArrayAccess\TrayDigita\Routing\Interfaces\RouteRunnerInterface;
use ArrayAccess\TrayDigita\Routing\Router;
use ArrayAccess\TrayDigita\Routing\RouteRunner;
use ArrayAccess\TrayDigita\Scheduler\Scheduler;
use ArrayAccess\TrayDigita\Templates\Wrapper;
use ArrayAccess\TrayDigita\Uploader\Chunk;
use ArrayAccess\TrayDigita\View\Interfaces\ViewInterface;
use ArrayAccess\TrayDigita\View\View;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Configuration as DoctrineConfiguration;
use Doctrine\DBAL\Connection as DoctrineConnection;
use Doctrine\ORM\Configuration;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application as SymfonyConsole;
use function array_merge;
use function is_string;

class ContainerFactory implements ContainerFactoryInterface
{
    final public const DEFAULT_SERVICES = [
        // factory
        RouteFactoryInterface::class => RouteFactory::class,
        ServerRequestFactoryInterface::class => ServerRequestFactory::class,
        StreamFactoryInterface::class => StreamFactory::class,
        RequestFactoryInterface::class => RequestFactory::class,
        ResponseFactoryInterface::class => ResponseFactory::class,
        JsonResponderFactoryInterface::class => JsonResponderFactory::class,
        HtmlResponderFactoryInterface::class => HtmlResponderFactory::class,
        ImageResizerFactoryInterface::class => ImageResizerFactory::class,
        Wrapper::class => Wrapper::class,
        // benchmark
        ProfilerInterface::class => Profiler::class,
        Waterfall::class => Waterfall::class,

        // common services
        ManagerInterface::class => Manager::class,
        RouterInterface::class => Router::class,
        ViewInterface::class => View::class,
        RouteRunnerInterface::class => RouteRunner::class,
        MiddlewareDispatcherInterface::class => MiddlewareDispatcher::class,
        ResponseEmitterInterface::class => ResponseEmitter::class,
        // kernel
        KernelInterface::class => Kernel::class,
        HttpKernelInterface::class => HttpKernel::class,
        // scheduler
        Scheduler::class => Scheduler::class,
        // assets
        AssetsCollectionInterface::class => AssetCollection::class,
        // i18n
        TranslatorInterface::class => Translator::class,
        // handler
        ErrorHandler::class => ErrorHandler::class,
        ShutdownHandlerInterface::class => ShutdownHandler::class,
        // Handler
        LoggerInterface::class => Logger::class,
        // cache
        CacheItemPoolInterface::class => Cache::class,
        // database
        Connection::class => Connection::class,
        EventManager::class => EventManager::class,
        DoctrineConfiguration::class => Configuration::class,
        DoctrineConnection::class => [Connection::class, 'getConnection'],
        // config
        Config::class => Config::class,
        // collector
        DatabaseEventsCollector::class => DatabaseEventsCollector::class,
        // permission
        PermissionInterface::class => Permission::class,
        // profiler
        ManagerProfiler::class => ManagerProfiler::class,
        // application console
        Application::class => Application::class,
        // image resizer
        ImageResizer::class => ImageResizer::class,
        // has identity
        HashIdentity::class => HashIdentity::class,
        // module storage
        Modules::class => Modules::class,
        // upload
        Chunk::class => Chunk::class,
        // nonce
        NonceInterface::class => Nonce::class,
        NonceRequestInterface::class => RequestNonce::class,
        // user auth
        UserAuth::class => UserAuth::class,
        // Assets
        AssetsJsCssQueue::class => AssetsJsCssQueue::class,
    ];

    public const DEFAULT_SERVICE_ALIASES = [
        // factory
        'routeFactory' => RouteFactoryInterface::class,
        routeFactory::class => RouteFactoryInterface::class,
        'serverRequestFactory' => ServerRequestFactoryInterface::class,
        ServerRequestFactory::class => ServerRequestFactoryInterface::class,
        'streamFactory' => StreamFactoryInterface::class,
        StreamFactory::class => StreamFactoryInterface::class,
        'requestFactory' => RequestFactoryInterface::class,
        requestFactory::class => RequestFactoryInterface::class,
        'responseFactory' => ResponseFactoryInterface::class,
        ResponseFactory::class => ResponseFactoryInterface::class,
        'jsonResponderFactory' => JsonResponderFactoryInterface::class,
        jsonResponderFactory::class => JsonResponderFactoryInterface::class,
        'htmlResponderFactory' => HtmlResponderFactoryInterface::class,
        HtmlResponderFactory::class => HtmlResponderFactoryInterface::class,
        // benchmark
        'profiler' => ProfilerInterface::class,
        'benchmark' => ProfilerInterface::class,
        Profiler::class => ProfilerInterface::class,
        'waterfall' => Waterfall::class,
        // common services
        'manager' => ManagerInterface::class,
        Manager::class => ManagerInterface::class,
        'router' => RouterInterface::class,
        Router::class => RouterInterface::class,
        'view' => ViewInterface::class,
        View::class => ViewInterface::class,
        'routeRunner' => RouteRunnerInterface::class,
        RouteRunner::class => RouteRunnerInterface::class,
        // by default route runner as request handler
        RequestHandlerInterface::class => RouteRunnerInterface::class,

        'middlewareDispatcher' => MiddlewareDispatcherInterface::class,
        MiddlewareDispatcher::class => MiddlewareDispatcherInterface::class,
        'responseEmitter' => ResponseEmitterInterface::class,
        ResponseEmitter::class => ResponseEmitterInterface::class,
        'httpKernel' => HttpKernelInterface::class,
        HttpKernel::class => HttpKernelInterface::class,
        'scheduler' => Scheduler::class,
        AssetCollection::class => AssetsCollectionInterface::class,
        'assets' => AssetsCollectionInterface::class,
        'assetsCollection' => AssetsCollectionInterface::class,

        // i18n
        'translator' => TranslatorInterface::class,
        Translator::class => TranslatorInterface::class,

        // handler
        'errorhandler' => ErrorHandler::class,
        'shutdownHandler' => ShutdownHandlerInterface::class,
        ShutdownHandler::class => ShutdownHandlerInterface::class,

        // Handler
        'logger' => LoggerInterface::class,
        Logger::class => LoggerInterface::class,
        // cache
        'cacheItem' => CacheItemPoolInterface::class,
        'cache' => CacheItemPoolInterface::class,
        Cache::class => CacheItemPoolInterface::class,
        // database
        'database' => Connection::class,
        'databaseConnection' => Connection::class,
        'connection' => Connection::class,
        'eventManager' => EventManager::class,
        'doctrineConfiguration' => DoctrineConfiguration::class,
        Configuration::class => DoctrineConfiguration::class,
        'config' => Config::class,
        'databaseEvents' => DatabaseEventsCollector::class,
        'databaseEventsCollector' => DatabaseEventsCollector::class,
        // permission
        'permission' => PermissionInterface::class,
        Permission::class => PermissionInterface::class,
        // application console
        'console' => Application::class,
        SymfonyConsole::class => Application::class,
        // image resizer
        'imageResizer' => ImageResizer::class,
        // has identity
        'hashIdentity' => HashIdentity::class,
        // module storage
        'modules' => Modules::class,
        // upload
        'chunk' => Chunk::class,
        // nonce
        Nonce::class => NonceInterface::class,
        'nonce' => Nonce::class,
        RequestNonce::class => NonceRequestInterface::class,
        'nonceRequest' => NonceRequestInterface::class,
        // user auth
        'userAuth' => UserAuth::class,
        // assets
        'assetsQueue' => AssetsJsCssQueue::class
    ];

    protected array $defaultServices = self::DEFAULT_SERVICES;
    protected array $defaultServiceAliases = self::DEFAULT_SERVICE_ALIASES;

    public function __construct()
    {
    }

    /**
     * @param array{string: mixed} $definitions
     * @param array $aliases
     * @return ContainerInterface
     * @throws ContainerFrozenException
     */
    public function createContainer(
        array $definitions = [],
        array $aliases = [],
    ): ContainerInterface {
        $container = new Container();
        foreach ($definitions as $id => $service) {
            if (!is_string($id)) {
                continue;
            }
            /**
             * New container does not have frozen data
             */
            $container->set($id, $service);
        }

        foreach ($aliases as $id => $target) {
            $container->setAlias($id, $target);
        }

        return $container;
    }

    /**
     * @return SystemContainerInterface
     * @throws ContainerFrozenException
     */
    public function createDefault(): SystemContainerInterface
    {
        $default = array_merge($this->defaultServices, self::DEFAULT_SERVICES);
        $defaultAliases = array_merge($this->defaultServiceAliases, self::DEFAULT_SERVICE_ALIASES);
        return ContainerWrapper::maybeContainerOrCreate(
            $this->createContainer($default, $defaultAliases)
        );
    }
}
