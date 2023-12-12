<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\View\Engines;

use ArrayAccess\TrayDigita\Collection\Config;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use ArrayAccess\TrayDigita\View\Twig\Loader\FileLoader;
use ArrayAccess\TrayDigita\View\Twig\TwigEnvironment;
use ArrayAccess\TrayDigita\View\Twig\TwigExtensions\ContentDispatcher;
use ArrayAccess\TrayDigita\View\Twig\TwigExtensions\HtmlTagAttributes;
use ArrayAccess\TrayDigita\View\Twig\TwigExtensions\Miscellaneous;
use ArrayAccess\TrayDigita\View\Twig\TwigExtensions\Translator;
use Stringable;
use Throwable;
use Twig\Cache\CacheInterface;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;
use function debug_backtrace;
use function in_array;
use function str_starts_with;
use const DEBUG_BACKTRACE_IGNORE_ARGS;

/**
 * @mixin Environment
 */
final class TwigEngine extends AbstractEngine
{
    protected array $extensions = [
        'twig'
    ];

    /**
     * @var array<class-string<\ArrayAccess\TrayDigita\View\Twig\TwigExtensions\AbstractExtension>
     * @noinspection PhpFullyQualifiedNameUsageInspection
     */
    protected array $extensionClasses = [
        HtmlTagAttributes::class,
        Translator::class,
        ContentDispatcher::class,
        Miscellaneous::class,
    ];

    protected ?Environment $twig = null;

    public function getTwig(): Environment
    {
        if (!$this->twig) {
            $manager = $this->getView()->getManager();
            $manager?->dispatch('viewEngine.beforeTwigEngineLoaded', $this);
            $options = [];
            $container = $this->view->getContainer();
            if ($container?->has(CacheInterface::class)) {
                $options['cache'] = ContainerHelper::getNull(CacheInterface::class, $container);
            }
            $twig = ContainerHelper::getNull(Environment::class, $container);
            if (!$twig instanceof Environment) {
                $this->twig = new TwigEnvironment(
                    new FileLoader($this, $this->getFilteredViewsDir()),
                    $options
                );
            } else {
                $this->twig = $twig;
                $loader = $twig->getLoader();
                if ($loader instanceof FilesystemLoader) {
                    foreach ($this->getFilteredViewsDir() as $dir) {
                        if (!in_array($dir, $loader->getPaths())) {
                            try {
                                $loader->addPath($dir);
                            } catch (Throwable) {
                            }
                        }
                    }
                }
            }

            $manager?->dispatch('viewEngine.twigEngineLoaded', $this);
            $config = ContainerHelper::use(Config::class, $this->getView()->getContainer())
                ->get('environment');
            if ($config instanceof Config
                && $config->get('displayErrorDetails') === true
            ) {
                $this->twig->enableDebug();
            }

            if (!$this->twig->getCache() && ($options['cache']??null) instanceof CacheInterface) {
                $this->twig->setCache($options['cache']);
            }

            $this->twig->addExtension(new DebugExtension());
            foreach ($this->extensionClasses as $name) {
                $this->twig->addExtension(new $name($this));
            }
            $this->addGlobalVariables($this->twig);
            $manager?->dispatch('viewEngine.afterTwigEngineLoaded', $this);
        }

        return $this->twig;
    }

    protected function internalInclude(string $path, array $parameters): string|Stringable
    {
        $manager = $this->getView()->getManager();
        $manager?->dispatch('viewEngine.beforeTwigLoad', $path, $parameters, $this);
        try {
            $twig = clone $this->getTwig();
            $parameters = $parameters + $this->view->getParameters();
            $result = $twig->load($path)->render(['this' => $this] + $parameters);
            $manager?->dispatch('viewEngine.twigLoad', $path, $parameters, $this, $result);
            return $result;
        } finally {
            $manager?->dispatch('viewEngine.afterTwigLoad', $path, $parameters, $this, $result??null);
        }
    }

    public function __call(string $name, array $arguments)
    {
        return $this->getTwig()->$name(...$arguments);
    }

    public function clearVariableCache(): void
    {
        $fn = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)[1];
        // no execute on destruct
        if (($fn['class']??null) === parent::class
            && $fn['function'] === '__destruct'
        ) {
            parent::clearVariableCache();
            return;
        }

        $loader = $this->twig?->getLoader();
        $viewsDirectory = $this->getFilteredViewsDir();
        parent::clearVariableCache();
        if ($loader instanceof FilesystemLoader) {
            $paths = $loader->getPaths();
            $templateRule = $this->getView()
                ->getTemplateRule()
                ?->getTemplatesDirectory();
            $currentPaths = $this->getFilteredViewsDir();
            foreach ($paths as $path) {
                // no templates
                if ($templateRule && str_starts_with($path, $templateRule)) {
                    continue;
                }
                if (!in_array($path, $viewsDirectory) && !in_array($path, $currentPaths)) {
                    $currentPaths[] = $path;
                }
            }
            $loader->setPaths($currentPaths);
        }
    }

    private function addGlobalVariables(Environment $twig): void
    {
        $view = $this->getView();
        $twig->addGlobal('view', $view);
        $twig->addGlobal('request', $view->getRequest());
        $twig->addGlobal('current_uri', $view->getRequest()->getUri());
        $twig->addGlobal('current_url', (string) $view->getRequest()->getUri());
        $twig->addGlobal('base_url', (string) $view->getBaseURI());
        $twig->addGlobal('base_uri', $view->getBaseURI());
        $twig->addGlobal('manager', $view->getManager());
    }
}
