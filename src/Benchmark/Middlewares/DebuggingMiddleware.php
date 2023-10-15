<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark\Middlewares;

use ArrayAccess\TrayDigita\Benchmark\Interfaces\ProfilerInterface;
use ArrayAccess\TrayDigita\Benchmark\Waterfall;
use ArrayAccess\TrayDigita\Collection\Config;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Middleware\AbstractMiddleware;
use ArrayAccess\TrayDigita\Traits\Http\StreamFactoryTrait;
use ArrayAccess\TrayDigita\Traits\Service\TranslatorTrait;
use ArrayAccess\TrayDigita\Util\Filter\Consolidation;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use ArrayAccess\TrayDigita\Util\Filter\DataType;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use function memory_get_usage;
use function microtime;
use function preg_replace;
use function str_contains;
use const PHP_INT_MAX;
use const PHP_INT_MIN;

class DebuggingMiddleware extends AbstractMiddleware
{
    protected int $priority = PHP_INT_MIN;

    use StreamFactoryTrait,
        TranslatorTrait;

    private bool $registered = false;

    private bool $darkMode = false;

    /**
     * @var ?float
     */
    private ?float $requestFloat = null;

    protected function doProcess(
        ServerRequestInterface $request
    ): ServerRequestInterface {
        if (!$this->registered) {
            $this->requestFloat = $request->getServerParams()['REQUEST_TIME_FLOAT']??null;
            $this->registered = true;
            $this->registerBenchmarkDebugBar();
        }
        return $request;
    }

    protected function registerBenchmarkDebugBar(): void
    {
        // do not run if cli
        if (Consolidation::isCli()) {
            return;
        }
        $container = $this->getContainer();
        $config = ContainerHelper::use(Config::class, $container);
        $manager = ContainerHelper::use(ManagerInterface::class, $container);
        if (!$config || !$manager) {
            return;
        }
        if (!$config instanceof Config
            || !($config = $config->get('environment')) instanceof Config
        ) {
            return;
        }
        if ($config->get('showPerformance') === true) {
            $manager->attach(
                'response.final',
                [$this, 'printPerformance'],
                priority: PHP_INT_MAX - 5
            );
        }

        if ($config->get('profiling') !== true || $config->get('debugBar') !== true) {
            return;
        }

        $this->darkMode = $config->get('debugBarDarkMode') === true;
        // @attach(response.final)
        $manager->attach(
            'response.final',
            [$this, 'renderDebugBar'],
            priority: PHP_INT_MAX - 100
        );
    }

    private function renderDebugBar($response) : mixed
    {
        $manager = $this->getManager();

        // @detach(response.final)
        $manager?->detach(
            'response.final',
            [$this, 'renderDebugBar'],
            PHP_INT_MAX - 100
        );

        if (!$response instanceof ResponseInterface) {
            return $response;
        }

        $container = $this->getContainer();
        $config = ContainerHelper::use(Config::class, $container)?->get('environment');
        $config = $config instanceof Config ? $config : new Config();

        // maybe override? disabled!
        if ($config->get('debugBar') !== true) {
            return $response;
        }

        // if profiler disabled, stop here!
        $profiler = ContainerHelper::use(ProfilerInterface::class, $container);
        $waterfall = ContainerHelper::use(Waterfall::class, $container);
        if (!$profiler?->isEnable() || ! $waterfall || !DataType::isHtmlContentType($response)) {
            return $response;
        }

        // DO STOP BENCHMARK
        $benchmark = ($profiler->getGroup('response')
            ??$profiler->getGroup('manager'))
            ?->get('response.final');
        $benchmark?->stop([
            'duration' => $benchmark->convertMicrotime(microtime(true)) - $benchmark->getStartTime(),
            'stopped' => true
        ]);
        $benchmark = $profiler
            ->getGroup('httpKernel')
            ?->get('httpKernel.dispatch');
        $benchmark?->stop([
            'duration' => $benchmark->convertMicrotime(microtime(true)) - $benchmark->getStartTime()
        ]);
        $benchmark?->setMetadataRecord('stopped', true);
        // END BENCHMARK
        $serverParams = $this->request?->getServerParams()??$_SERVER;
        $startTime = (
            $this->requestFloat
            ??$serverParams['REQUEST_TIME_FLOAT']
            ??$profiler->getStartTime()
        );

        // get origin performance
        $performanceOrigin = microtime(true) - $startTime;
        $memoryOrigin = Consolidation::sizeFormat(memory_get_usage(), 3);

        // start
        $body = (string) $response->getBody();
        $streamFactory = $this->getStreamFactory();
        $darkMode = $this->darkMode;
        $found = false;
        if (str_contains($body, '<!--(waterfall)-->')) {
            $found = true;
            $body = preg_replace(
                '~<!--\(waterfall\)-->~',
                $waterfall->render(darkMode: $darkMode),
                $body,
                1
            );
        } else {
            $regexes = [
                '~(<(body)[^>]*>.*)(</\2>\s*</html>\s*(?:<\!\-\-.*)?)$~ism',
                '~(<(head)[^>]*>.*)(</\2>\s*<body>)~ism',
                '~(<(html)[^>]*>.*)(</\2>\s*(?:<\!\-\-.*)?)~ism',
            ];
            foreach ($regexes as $regex) {
                if (!preg_match($regex, $body)) {
                    continue;
                }
                $body = preg_replace_callback(
                    $regex,
                    static function ($match) use ($waterfall, $darkMode) {
                        return $match[1] . "\n" . $waterfall->render(darkMode: $darkMode) . "\n" . $match[3];
                    },
                    $body
                );
                $found = true;
                break;
            }
        }

        if (!$found) {
            $body .= "\n";
            $body .= $waterfall->render(darkMode: $darkMode);
            $body .= "\n";
        }
        // stop
        $waterfall = null;
        unset($waterfall);

        // doing clear
        $profiler->clear();
        $performanceEnd = microtime(true) - $startTime;
        return $response->withBody(
            $streamFactory->createStream(
                $body
                .sprintf(
                    "\n<!-- (%s : %s ms / %s: %s) ~ (+waterfall ~ %s : %s ms / %s: %s) -->",
                    $this->translateContext('rendered time', 'benchmark'),
                    $profiler->convertMicrotime($performanceOrigin),
                    $this->translateContext('memory usage', 'benchmark'),
                    $memoryOrigin,
                    $this->translateContext('rendered time', 'benchmark'),
                    $profiler->convertMicrotime($performanceEnd),
                    $this->translateContext('memory usage', 'benchmark'),
                    Consolidation::sizeFormat(memory_get_usage(), 3),
                )
            )
        );
    }

    /**
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    private function printPerformance(ResponseInterface $response): ResponseInterface
    {
        $this->getManager()?->detach(
            'response.final',
            [$this, 'printPerformance'],
            priority: PHP_INT_MAX - 5
        );
        $container = $this->getContainer();
        $config = ContainerHelper::use(Config::class, $container)?->get('environment');
        $config = $config instanceof Config ? $config : new Config();

        // maybe override? disabled!
        if ($config->get('showPerformance') !== true) {
            return $response;
        }

        // check content type
        if (!DataType::isHtmlContentType($response)
            || ! $response->getBody()->isWritable()
        ) {
            return $response;
        }
        try {
            $response->getBody()->seek($response->getBody()->getSize());
        } catch (Throwable) {
            // pass
        }
        $serverParams = $this->request?->getServerParams()??$_SERVER;
        $startTime = (
            $this->requestFloat
            ??$serverParams['REQUEST_TIME_FLOAT']
            ??$serverParams['REQUEST_TIME']
        );
        $str = sprintf(
            "\n<!-- %s -->",
            sprintf(
                '(%s: %s ms / %s: %s / %s: %s)',
                $this->translateContext('rendered time', 'benchmark'),
                round(
                    (microtime(true) * 1000 - $startTime * 1000),
                    4
                ),
                $this->translateContext('peak memory usage', 'benchmark'),
                Consolidation::sizeFormat(memory_get_peak_usage(), 3),
                $this->translateContext('memory usage', 'benchmark'),
                Consolidation::sizeFormat(memory_get_usage(), 3)
            )
        );
        $response->getBody()->write($str);
        return $response;
    }
}
