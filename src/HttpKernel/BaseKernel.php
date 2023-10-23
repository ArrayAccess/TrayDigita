<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\HttpKernel;

use ArrayAccess\TrayDigita\Benchmark\Injector\ManagerProfiler;
use ArrayAccess\TrayDigita\Benchmark\Interfaces\ResetInterface;
use ArrayAccess\TrayDigita\Benchmark\Middlewares\DebuggingMiddleware;
use ArrayAccess\TrayDigita\Collection\Config;
use ArrayAccess\TrayDigita\Container\Interfaces\ContainerIndicateInterface;
use ArrayAccess\TrayDigita\Container\Interfaces\SystemContainerInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Exceptions\Runtime\RuntimeException;
use ArrayAccess\TrayDigita\HttpKernel\Helper\KernelCommandLoader;
use ArrayAccess\TrayDigita\HttpKernel\Helper\KernelControllerLoader;
use ArrayAccess\TrayDigita\HttpKernel\Helper\KernelDatabaseEventLoader;
use ArrayAccess\TrayDigita\HttpKernel\Helper\KernelMiddlewareLoader;
use ArrayAccess\TrayDigita\HttpKernel\Helper\KernelModuleLoader;
use ArrayAccess\TrayDigita\HttpKernel\Helper\KernelSchedulerLoader;
use ArrayAccess\TrayDigita\HttpKernel\Interfaces\HttpKernelInterface;
use ArrayAccess\TrayDigita\HttpKernel\Interfaces\TerminableInterface;
use ArrayAccess\TrayDigita\Kernel\Interfaces\KernelInterface;
use ArrayAccess\TrayDigita\Kernel\Interfaces\RebootableInterface;
use ArrayAccess\TrayDigita\L10n\Translations\Adapter\Gettext\PoMoAdapter;
use ArrayAccess\TrayDigita\L10n\Translations\Adapter\Json\JsonAdapter;
use ArrayAccess\TrayDigita\L10n\Translations\Interfaces\TranslatorInterface;
use ArrayAccess\TrayDigita\Middleware\RoutingMiddleware;
use ArrayAccess\TrayDigita\PossibleRoot;
use ArrayAccess\TrayDigita\Util\Filter\Consolidation;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use ArrayAccess\TrayDigita\Util\Filter\DataNormalizer;
use ArrayAccess\TrayDigita\Util\Parser\DotEnv;
use DateTimeZone;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\Yaml\Yaml;
use Throwable;
use function array_keys;
use function date_default_timezone_get;
use function date_default_timezone_set;
use function define;
use function defined;
use function dirname;
use function file_exists;
use function file_get_contents;
use function hash_hmac;
use function in_array;
use function ini_get;
use function is_dir;
use function is_file;
use function is_iterable;
use function is_numeric;
use function is_string;
use function json_decode;
use function max;
use function microtime;
use function mkdir;
use function pathinfo;
use function preg_replace;
use function realpath;
use function sha1;
use function str_replace;
use function strtolower;
use function trim;
use const CONFIG_FILE;
use const DIRECTORY_SEPARATOR;
use const INF;
use const PATHINFO_EXTENSION;

/**
 * @mixin HttpKernelInterface
 */
abstract class BaseKernel implements
    KernelInterface,
    RequestHandlerInterface,
    RebootableInterface,
    ContainerIndicateInterface
{
    private int $bootStack = 0;

    private bool $booted = false;

    private bool $shutdown = false;

    protected ?float $bootTime = null;

    /**
     * @var int
     */
    protected int $requestStackSize = 0;

    /**
     * @var bool
     */
    protected bool $resetServices = false;

    /**
     * @var array<string, string>
     */
    protected array $registeredDirectories = [];

    /**
     * @var array<string>
     */
    protected array $controllersDirectories = [];

    /**
     * @var array|string[]
     */
    protected array $allowedConfigExtension = [
        'json',
        'yaml',
        'yml',
        'php',
        'env'
    ];

    /**
     * @param HttpKernelInterface $httpKernel
     * @param ?string $baseConfigFileName base config from root path
     *
     * @uses KernelInterface::BASE_CONFIG_FILE_NAME
     */
    public function __construct(
        protected HttpKernelInterface $httpKernel,
        ?string $baseConfigFileName = KernelInterface::BASE_CONFIG_FILE_NAME
    ) {
        if (!is_string($baseConfigFileName)) {
            return;
        }
        $baseConfigFileName = trim($baseConfigFileName);
        $baseConfigFileName = DataNormalizer::normalizeDirectorySeparator($baseConfigFileName);
        $extension = strtolower(pathinfo($baseConfigFileName, PATHINFO_EXTENSION));
        if (in_array($extension, $this->allowedConfigExtension)) {
            $this->baseConfigFile = $baseConfigFileName;
        }
    }

    public function registerControllerDirectory(string ...$directory): array
    {
        foreach ($directory as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $this->controllersDirectories[realpath($dir)] = $dir;
        }
        return $this->controllersDirectories;
    }

    public function getControllersDirectories(): array
    {
        return array_keys($this->controllersDirectories);
    }

    public function removeControllerDirectory(string ...$directory): array
    {
        $removed = [];
        foreach ($directory as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $dir = realpath($dir);
            if (isset($this->controllersDirectories[$dir])) {
                unset($this->controllersDirectories[$dir]);
                $removed[] = $dir;
            }
        }
        return $removed;
    }

    /**
     * @return ?ContainerInterface
     */
    public function getContainer(): ?ContainerInterface
    {
        return $this->getHttpKernel()->getContainer();
    }

    public function getStartMemory(): int
    {
        return $this->getHttpKernel()->getStartMemory();
    }

    public function getStartTime(): float
    {
        return $this->getHttpKernel()->getStartTime();
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }

    public function isShutdown(): bool
    {
        return $this->shutdown;
    }

    /** @noinspection PhpUnused */
    public function getBootTime(): float
    {
        return null !== $this->bootTime ? $this->bootTime : -INF;
    }

    /**
     * @return HttpKernelInterface
     */
    public function getHttpKernel() : HttpKernelInterface
    {
        return $this->httpKernel;
    }

    /**
     * Doing boot
     *
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function boot()
    {
        if ($this->booted === true) {
            if (!$this->requestStackSize
                && true === $this->resetServices
                && ($resetter = ContainerHelper::getNull(
                    ResetInterface::class,
                    $this->getContainer()
                ))
            ) {
                try {
                    $resetter->reset();
                } catch (Throwable) {
                }
            }

            $this->bootTime = microtime(true);
            $this->resetServices = false;
            return;
        }

        ++$this->bootStack;
        $this->shutdown = false;
        $this->bootTime = microtime(true);
        // do init
        !$this->isHasInit() && $this->init();

        $this->preBoot();
        $this->booted = true;
        // @dispatch(kernel.boot)
        $this
            ->getHttpKernel()
            ->getManager()
            ?->dispatch('kernel.boot', $this);
        $this->postBoot();
    }

    /** @noinspection PhpMissingReturnTypeInspection */
    public function shutdown()
    {
        if (!$this->booted || $this->shutdown) {
            return;
        }

        $this->shutdown = true;
        $this->booted = false;
        $this->requestStackSize = 0;
        // @dispatch(kernel.shutdown)
        $this->getHttpKernel()
            ->getManager()
            ?->dispatch('kernel.shutdown', $this);
    }

    /** @noinspection PhpMissingReturnTypeInspection */
    protected function postBoot()
    {
        // @dispatch(kernel.postBoot)
        $this->getHttpKernel()->getManager()?->dispatch(
            'kernel.postBoot',
            $this
        );
    }

    /** @noinspection PhpMissingReturnTypeInspection */
    protected function preBoot()
    {
        $this->bootTime = microtime(true);
        // @dispatch(kernel.preBoot)
        $this->getHttpKernel()->getManager()?->dispatch(
            'kernel.preBoot',
            $this
        );
    }

    /** @noinspection PhpMissingReturnTypeInspection */
    public function reboot()
    {
        // @dispatch(kernel.reboot)
        $this->getHttpKernel()
            ->getManager()
            ?->dispatch('kernel.reboot', $this);
        $this->shutdown();
        $this->boot();
    }

    public function terminate(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): void {
        // @dispatch(kernel.terminate)
        $this
            ->getHttpKernel()
            ->getManager()
            ?->dispatch('kernel.terminate', $this, $request, $response);
        if (($kernel = $this->getHttpKernel()) instanceof TerminableInterface) {
            $kernel->terminate($request, $response);
        }
    }

    public function handle(ServerRequestInterface $request) : ResponseInterface
    {
        if (!$this->booted) {
            $this->boot();
        }

        $manager = $this->getHttpKernel()->getManager();
        $manager?->dispatch(
            'kernel.beforeHandle',
            $this
        );

        ++$this->requestStackSize;
        $this->resetServices = true;
        try {
            $response = $this->getHttpKernel()->handle($request);
            $manager?->dispatch(
                'kernel.handle',
                $this,
                $response
            );
            return $response;
        } finally {
            --$this->requestStackSize;
            $manager?->dispatch(
                'kernel.afterHandle',
                $this,
                $response??null
            );
        }
    }

    public function run(ServerRequestInterface $request): ResponseInterface
    {
        return $this
            ->getHttpKernel()
            ->dispatchResponse($this->handle($request));
    }

    public function __call(string $name, array $arguments)
    {
        return $this->getHttpKernel()->$name(...$arguments);
    }

    private bool $hasInit = false;

    private ?string $configError = null;
    private ?string $configFile = null;

    private bool $ready = false;

    /*! NAMESPACE */
    protected ?string $appNameSpace = null;
    protected ?string $controllerNameSpace = null;
    protected ?string $entityNamespace = null;
    protected ?string $middlewareNamespace = null;
    protected ?string $migrationNameSpace = null;
    protected ?string $moduleNameSpace = null;
    protected ?string $databaseEventNameSpace = null;
    protected ?string $commandNameSpace = null;
    protected ?string $schedulerNamespace = null;


    /*! STATUS */
    private bool $providerRegistered = false;

    private ?string $rootDirectory = null;

    /**
     * @var string
     */
    private string $baseConfigFile = self::BASE_CONFIG_FILE_NAME;

    /**
     * @var ?Throwable
     */
    private ?Throwable $configErrorException = null;

    public function isHasInit(): bool
    {
        return $this->hasInit;
    }

    public function isReady(): bool
    {
        return $this->ready;
    }

    public function getConfigErrorException(): ?Throwable
    {
        return $this->configErrorException;
    }

    final public function init() : static
    {
        if ($this->hasInit) {
            return $this;
        }

        if (!defined('TD_APP_DIRECTORY')) {
            throw new RuntimeException(
                "TD_APP_DIRECTORY is not defined!"
            );
        }

        // lock
        $this->hasInit = true;
        $root = PossibleRoot::getPossibleRootDirectory();
        if (!$root) {
            throw new RuntimeException(
                "Could not detect root directory!"
            );
        }

        /**
         * @var SystemContainerInterface $container
         */
        $httpKernel = $this->getHttpKernel();
        $container = $httpKernel->getContainer();
        $manager = ContainerHelper::use(ManagerInterface::class, $container);
        $config = ContainerHelper::use(Config::class, $container);
        if (!$config) {
            $container->remove(Config::class);
            $config = new Config();
            $container->set(Config::class, fn () => $config);
        }

        $container->remove(KernelInterface::class);
        $container->raw(KernelInterface::class, $this);
        $appDirectory = realpath(TD_APP_DIRECTORY)?:TD_APP_DIRECTORY;
        $this->rootDirectory = $root;
        if (defined('TD_INDEX_FILE')
            && is_string(TD_INDEX_FILE)
            && file_exists(TD_INDEX_FILE)
        ) {
            $publicDirectory = dirname(realpath(TD_INDEX_FILE)??TD_INDEX_FILE);
        } else {
            if (Consolidation::isCli()) {
                // use default public
                $publicDirectory = $root . DIRECTORY_SEPARATOR . 'public';
            } else {
                $documentRoot = $_SERVER['DOCUMENT_ROOT']??null;
                $documentRoot = $documentRoot && is_dir($documentRoot)
                    ? $documentRoot
                    : null;
                if (!$documentRoot
                    && is_string($_SERVER['SCRIPT_FILENAME']??null)
                    && is_file($_SERVER['SCRIPT_FILENAME'])
                ) {
                    $publicDirectory = dirname($_SERVER['SCRIPT_FILENAME']);
                } else {
                    $publicDirectory = $root . DIRECTORY_SEPARATOR . 'public';
                }
            }
        }
        $defaultPaths = [
            'controller' => $appDirectory . '/Controllers',
            'entity' => $appDirectory . '/Entities',
            'language' => $appDirectory . '/Languages',
            'middleware' => $appDirectory . '/Middlewares',
            'migration' => $appDirectory . '/Migrations',
            'module' => $appDirectory . '/Modules',
            'view' => $appDirectory . '/Views',
            'databaseEvent' => $appDirectory . '/DatabaseEvents',
            'scheduler' => $appDirectory . '/Schedulers',
            'command' => $appDirectory . '/Commands',
            'storage' => $root . '/storage',
            'data' => $root . '/data',
            'public' => $publicDirectory,
            'upload' => $publicDirectory . '/uploads',
            'template' => 'templates',
        ];

        // path
        $path = $config->get('path');
        if (!$path instanceof Config) {
            $path = new Config();
            $config->set('path', $path);
        }

        $path->addDefaults($defaultPaths);
        $config->set('path', $path);

        $configFile = $root . DIRECTORY_SEPARATOR . $this->baseConfigFile;
        if (defined('CONFIG_FILE') && is_string(CONFIG_FILE)) {
            $configFile = CONFIG_FILE;
        }

        if (!defined('CONFIG_FILE')) {
            define('CONFIG_FILE', realpath($configFile) ?: $configFile);
        }

        // use resolver
        if (CONFIG_FILE !== $configFile
            && (
                !is_string(CONFIG_FILE)
                || is_file(CONFIG_FILE)
            )
        ) {
            // override
            $this->configFile = CONFIG_FILE;
        } else {
            $this->configFile = $configFile;
        }

        if (!file_exists(CONFIG_FILE)) {
            $this->configError = self::CONFIG_UNAVAILABLE;
        } elseif (!is_file(CONFIG_FILE)) {
            $this->configError = self::CONFIG_NOT_FILE;
        } else {
            $configurations = null;
            try {
                // json, yaml, yml, php, env
                switch (strtolower(pathinfo($this->configFile, PATHINFO_EXTENSION))) {
                    case 'env':
                        $configurations = DataNormalizer::notationToArray(
                            DotEnv::fromFile($this->configFile)
                        );
                        break;
                    case 'json':
                        $configurations = json_decode((string)file_get_contents($this->configFile), true);
                        break;
                    case 'yaml':
                    case 'yml':
                        $configurations = Yaml::parseFile($this->configFile);
                        break;
                    case 'php':
                        $configurations = (function ($configFile) {
                            $result = require $configFile;
                            return is_iterable($result) ? $result : null;
                        })->bindTo(null)($configFile);
                        break;
                }
            } catch (Throwable $e) {
                $this->configErrorException = $e;
                unset($e);
            }

            if (!is_iterable($configurations)) {
                $this->configError = self::CONFIG_NOT_ITERABLE;
            } elseif (empty($configurations)) {
                $this->configError = self::CONFIG_EMPTY_FILE;
            } else {
                $this->ready = true;
                $this->configError = null;
                $config->merge($configurations);
            }
        }

        if (!$config->get('path') instanceof Config) {
            $config->set('path', $path);
        }

        /**
         * @var Config $path
         */
        $path = $config->get('path');
        // env
        $environment = $config->get('environment');
        if (!$environment instanceof Config) {
            $environment = new Config();
            $config->set('environment', $environment);
        }

        /*! PATH */
        $path->addDefaults($defaultPaths);
        // normalize
        foreach ($defaultPaths as $pathName => $directory) {
            if (!is_string($path->get($pathName))
                || trim($path->get($pathName)) === ''
            ) {
                $path->set($pathName, $directory);
            }
        }

        $uploadDirectory = $path->get('upload');
        $uploadDirectory = $publicDirectory . DIRECTORY_SEPARATOR . $uploadDirectory;
        $uploadDirectory = realpath($uploadDirectory)??$uploadDirectory;
        $uploadPath = realpath($path->get('upload'))??null;
        if (!$uploadPath || !str_starts_with($uploadPath, $publicDirectory)) {
            if ($uploadPath && is_dir($publicDirectory . DIRECTORY_SEPARATOR . $uploadPath)) {
                $uploadDirectory = $publicDirectory . DIRECTORY_SEPARATOR . $uploadPath;
                $uploadDirectory = realpath($uploadDirectory)??$uploadDirectory;
            }
            $path->set('upload', $uploadDirectory);
        }

        if ($this->getConfigError()) {
            $iniGet = @ini_get('display_errors');
            $environment['displayErrorDetails'] = is_string($iniGet)
                && strtolower($iniGet) === 'on'
                || $iniGet === '1'
                || $iniGet === 'true';
        }
        // debug also display error
        $debug = $environment->get('debug') === true;
        if ($debug) {
            $environment['displayErrorDetails'] = true;
        }
        $environment['displayErrorDetails'] = (bool)$environment['displayErrorDetails'];
        $container->setParameter('displayErrorDetails', $environment['displayErrorDetails']);
        if ($environment['displayErrorDetails']) {
            $manager?->attach(
                'jsonResponder.debug',
                static fn() => true
            );
        }
        if ($manager
            && true === $environment->get('profiling')
        ) {
            try {
                $managerProfiler = ContainerHelper::decorate(ManagerProfiler::class, $container);
                $managerProfiler->registerProviders();
                $manager->setDispatchListener($managerProfiler);
            } catch (Throwable) {
            }
        }

        try {
            // @dispatch(kernel.beforeInitConfig)
            $manager->dispatch('kernel.beforeInitConfig');

            /*! TIMEZONE */
            $databaseConfig = $config->get('database');
            $databaseConfig = $databaseConfig instanceof Config
                ? $databaseConfig
                : null;
            $timezone = $environment->get('timezone');
            /** @noinspection DuplicatedCode */
            $timezone = is_string($timezone) ? trim($timezone) : null;
            $timezone = $timezone?:null;
            if ($timezone) {
                $timezone = strtolower($timezone) === 'system'
                    ? date_default_timezone_get()
                    : $timezone;
                try {
                    $timezone = new DateTimeZone($timezone);
                    $timezone = $timezone->getName();
                } catch (Throwable) {
                    $timezone = null;
                }
            }

            $timezone = $timezone ?: null;
            $databaseTimeZone = $databaseConfig?->get('timezone');
            /** @noinspection DuplicatedCode */
            $databaseTimeZone = is_string($databaseTimeZone)
                ? trim($databaseTimeZone)
                : null;
            $databaseTimeZone = $databaseTimeZone?:null;
            if ($databaseTimeZone) {
                $databaseTimeZone = strtolower($databaseTimeZone) === 'system'
                    ? date_default_timezone_get()
                    : $databaseTimeZone;
                try {
                    // try timezone
                    $databaseTimeZone = new DateTimeZone($databaseTimeZone);
                    $databaseTimeZone = $databaseTimeZone->getName();
                } catch (Throwable) {
                    $databaseTimeZone = null;
                }
            }

            // FALLBACK DEFAULT
            $timezone ??= $databaseTimeZone??date_default_timezone_get();
            try {
                $timezone = new DateTimeZone($timezone);
                $timezone = $timezone->getName();
            } catch (Throwable) {
            }
            $databaseTimeZone = $databaseTimeZone?:$timezone;

            // SET CONFIG
            $databaseConfig?->set('timezone', $databaseTimeZone);
            $environment->set('timezone', $timezone);

            // SET TIMEZONE
            Consolidation::callbackReduceError(static fn() => date_default_timezone_set($timezone));

            $templatePath = $path->get('template');
            $templatePath = !is_string($templatePath) || trim($templatePath) === ''
                ? $templatePath
                : 'templates';
            $path->set('template', $templatePath);
            $storageDirectory = $path->get('storage');
            $dataDirectory = $path->get('data');
            if (!Consolidation::isCli()) {
                if (!is_dir($storageDirectory)) {
                    mkdir($storageDirectory, 0755, true);
                }
                if (!is_dir($dataDirectory)) {
                    mkdir($dataDirectory, 0755, true);
                }
            }
            $dataDirectory = realpath($dataDirectory)?:$dataDirectory;
            $storageDirectory = realpath($storageDirectory)?:$storageDirectory;
            $path->set('storage', $storageDirectory);
            $path->set('data', $dataDirectory);

            // directory parameter
            $container->setParameter('viewsDirectory', $path->get('view'));
            $container->setParameter('controllersDirectory', $path->get('controller'));
            $container->setParameter('languagesDirectory', $path->get('language'));
            $container->setParameter('migrationsDirectory', $path->get('migration'));
            $container->setParameter('modulesDirectory', $path->get('module'));
            $container->setParameter('entitiesDirectory', $path->get('entity'));
            // $container->setParameter('repositoriesDirectory', $path->get('repository'));
            $container->setParameter('databaseEventsDirectory', $path->get('databaseEvent'));
            $container->setParameter('schedulersDirectory', $path->get('scheduler'));
            $container->setParameter('commandsDirectory', $path->get('command'));
            $container->setParameter('middlewaresDirectory', $path->get('middleware'));
            $container->setParameter('storageDirectory', $storageDirectory);
            $container->setParameter('uploadsDirectory', $uploadDirectory);
            $container->setParameter('publicDirectory', $publicDirectory);
            $container->setParameter('dataDirectory', $dataDirectory);
            $container->setParameter('templatePath', $templatePath);

            // security
            $security = $config->get('security');
            $security = $security instanceof Config ? $security : new Config();
            $config->set('security', $security);

            $secretKey = $security->get('secret');
            $saltKey    = $security->get('salt');
            $nonceKey    = $security->get('nonce');

            $secretKey = is_string($secretKey) && trim($secretKey) !== ''
                ? sha1($secretKey)
                : sha1(__DIR__); // fallback default
            $saltKey = is_string($saltKey) && trim($saltKey) !== ''
                ? sha1($saltKey)
                : hash_hmac('sha1', __DIR__, $secretKey);
            $nonceKey = is_string($nonceKey) && trim($nonceKey) !== ''
                ? sha1($nonceKey)
                : hash_hmac('sha1', $saltKey, $secretKey);

            $security->set('secretKeyHash', $secretKey);
            $security->set('saltKeyHash', $saltKey);
            $security->set('nonceKeyHash', $nonceKey);

            // set container param
            $container->setParameter('secretKey', $secretKey);
            $container->setParameter('saltKey', $saltKey);
            $container->setParameter('nonceKey', $nonceKey);

            // cookie
            $cookie = $config->get('cookie');
            $cookie = $cookie instanceof Config ? $cookie : new Config();
            $config->set('cookie', $cookie);

            $cookieNames = [
                'user' => [
                    'name' => 'auth_user',
                    'lifetime' => 0,
                    'wildcard' => false
                ],
                'admin' => [
                    'name' => 'auth_admin',
                    'lifetime' => 0,
                    'wildcard' => false
                ]
            ];
            foreach ($cookieNames as $key => $name) {
                $cookieData = $cookie->get($key);
                $cookieData = $cookieData instanceof Config
                    ? $cookieData
                    : new Config();
                // replace
                $cookie->set($key, $cookieData);
                $cookieName = $cookieData->get('name');
                $cookieName = is_string($cookieName) && trim($cookieName) !== ''
                    ? trim($cookieName)
                    : $name;
                $cookieName = preg_replace(
                    '~[^!#$%&\'*+-.^_`|\~a-z0-9]~i',
                    '',
                    $cookieName
                );
                $cookieName = $cookieName === '' ? $name : $cookieName;
                $cookieLifetime = $cookieData->get('lifetime');
                $cookieLifetime = is_numeric($cookieLifetime) ? $cookieLifetime : 0;
                $cookieLifetime = max((int) $cookieLifetime, 0);
                $cookieWildcard = $cookieData->get('wildcard') === true;
                $cookieNames[$key]['name'] = $cookieName;
                $cookieNames[$key]['wildcard'] = $cookieWildcard;
                $cookieNames[$key]['lifetime'] = $cookieLifetime;
            }

            $namespace = dirname(str_replace('\\', '/', __NAMESPACE__));
            // app
            $this->appNameSpace = str_replace('/', '\\', $namespace) . '\\App';
            $appNameSpace = $this->appNameSpace;

            $this->controllerNameSpace = "$appNameSpace\\Controllers\\";
            $this->entityNamespace = "$appNameSpace\\Entities\\";
            $this->middlewareNamespace = "$appNameSpace\\Middlewares\\";
            $this->migrationNameSpace = "$appNameSpace\\Migrations\\";
            $this->moduleNameSpace = "$appNameSpace\\Modules\\";
            $this->databaseEventNameSpace = "$appNameSpace\\DatabaseEvents\\";
            $this->schedulerNamespace = "$appNameSpace\\Schedulers\\";
            $this->commandNameSpace = "$appNameSpace\\Commands\\";
            $this->registeredDirectories = [
                $this->moduleNameSpace => $path->get('module') ?? $defaultPaths['module'],
                $this->controllerNameSpace => $path->get('controller') ?? $defaultPaths['controller'],
                $this->entityNamespace => $path->get('entity') ?? $defaultPaths['entity'],
                $this->schedulerNamespace => $path->get('scheduler') ?? $defaultPaths['scheduler'],
                $this->middlewareNamespace => $path->get('middleware') ?? $defaultPaths['middleware'],
                $this->migrationNameSpace => $path->get('migration') ?? $defaultPaths['migration'],
                $this->databaseEventNameSpace => $path->get('databaseEvent') ?? $defaultPaths['databaseEvent'],
                $this->commandNameSpace => $path->get('command') ?? $defaultPaths['command'],
            ];

            $routing = ContainerHelper::getNull(
                RoutingMiddleware::class,
                $container
            )??new RoutingMiddleware($container, $this->getHttpKernel()->getRouter());
            try {
                $debugMiddleware = ContainerHelper::resolveCallable(
                    DebuggingMiddleware::class,
                    $container
                );
            } catch (Throwable) {
                $debugMiddleware = new DebuggingMiddleware($container);
            }
            // @dispatch(kernel.initConfig)
            $manager->dispatch('kernel.initConfig', $this);
        } finally {
            // @dispatch(kernel.afterInitConfig)
            $manager->dispatch('kernel.afterInitConfig', $this);
        }
        if (isset($this->registeredDirectories[$this->controllerNameSpace])) {
            $this->registerControllerDirectory(
                $this->registeredDirectories[$this->controllerNameSpace]
            );
        }

        // do register namespace first
        $this->registerAutoloaderNameSpace();
        // do register providers
        $this->registerProviders();

        /*! MIDDLEWARE */
        // add routing middleware on before module
        // to make routing executed on last
        $httpKernel->addMiddleware($routing);

        /*! SERVICES */
        // do register modules
        KernelModuleLoader::register($this);
        // do register schedulers
        KernelSchedulerLoader::register($this);

        /*! APPS */
        // do register middlewares
        KernelMiddlewareLoader::register($this);
        // do register controllers
        KernelControllerLoader::register($this);
        // do register database events
        KernelDatabaseEventLoader::register($this);
        // do register commands
        KernelCommandLoader::register($this);

        // registering debug middleware at the first middleware
        $httpKernel->addMiddleware($debugMiddleware);

        return $this;
    }

    /**
     * Register kernel provider
     */
    protected function registerProviders(): void
    {
        if ($this->providerRegistered) {
            return;
        }
        $this->providerRegistered = true;
        $container = $this->getContainer();
        $manager = ContainerHelper::use(ManagerInterface::class, $container);
        $manager?->dispatch('kernel.beforeRegisterProviders', $this);
        try {
            $config = ContainerHelper::use(Config::class, $container)?->get('path');
            $config = $config instanceof Config ? $config : null;
            $translator = ContainerHelper::use(TranslatorInterface::class, $container);
            $translator = $translator instanceof TranslatorInterface
                ? $translator
                : null;
            if ($translator) {
                if ($container instanceof SystemContainerInterface) {
                    try {
                        $poMoAdapter = $container->decorate(PoMoAdapter::class);
                        $jsonAdapter = $container->decorate(JsonAdapter::class);
                    } catch (Throwable) {
                    }
                }
                $poMoAdapter ??= new PoMoAdapter($translator);
                $jsonAdapter ??= new JsonAdapter($translator);
                $translator->addAdapter($poMoAdapter);
                $translator->addAdapter($jsonAdapter);

                $languageDirectory = $config?->get('language');
                if (is_string($languageDirectory) && is_dir($languageDirectory)) {
                    $translator->registerDirectory(TranslatorInterface::DEFAULT_DOMAIN, $languageDirectory);
                }
            }
            $manager?->dispatch('kernel.registerProviders', $this);
        } finally {
            $manager?->dispatch('kernel.afterRegisterProviders', $this);
        }
    }

    public function getRegisteredDirectories(): array
    {
        return $this->registeredDirectories;
    }

    public function getAppNameSpace(): ?string
    {
        return $this->appNameSpace;
    }

    public function getControllerNameSpace(): ?string
    {
        return $this->controllerNameSpace;
    }

    public function getEntityNamespace(): ?string
    {
        return $this->entityNamespace;
    }

    public function getMiddlewareNamespace(): ?string
    {
        return $this->middlewareNamespace;
    }

    public function getMigrationNameSpace(): ?string
    {
        return $this->migrationNameSpace;
    }

    public function getModuleNameSpace(): ?string
    {
        return $this->moduleNameSpace;
    }

    public function getDatabaseEventNameSpace(): ?string
    {
        return $this->databaseEventNameSpace;
    }

    public function getSchedulerNamespace(): ?string
    {
        return $this->schedulerNamespace;
    }

    public function getCommandNameSpace(): ?string
    {
        return $this->commandNameSpace;
    }

    public function getConfigError(): ?string
    {
        return $this->configError;
    }

    public function getConfigFile(): ?string
    {
        return $this->configFile;
    }

    public function getRootDirectory() : ? string
    {
        return $this->rootDirectory;
    }

    private function registerAutoloaderNameSpace(): void
    {
        foreach ($this->registeredDirectories as $namespace => $directory) {
            Consolidation::registerAutoloader($namespace, $directory);
        }
    }
}
