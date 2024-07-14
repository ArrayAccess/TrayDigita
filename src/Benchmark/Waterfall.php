<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark;

use ArrayAccess\TrayDigita\Benchmark\Aggregate\Interfaces\AggregatorInterface;
use ArrayAccess\TrayDigita\Benchmark\Formatter\HtmlFormatter;
use ArrayAccess\TrayDigita\Benchmark\Formatter\Interfaces\MetadataFormatterInterface;
use ArrayAccess\TrayDigita\Benchmark\Formatter\MetadataCollection;
use ArrayAccess\TrayDigita\Benchmark\Interfaces\GroupInterface;
use ArrayAccess\TrayDigita\Benchmark\Interfaces\ProfilerInterface;
use ArrayAccess\TrayDigita\Benchmark\Interfaces\RecordInterface;
use ArrayAccess\TrayDigita\Benchmark\Interfaces\SeverityInterface;
use ArrayAccess\TrayDigita\Container\Interfaces\ContainerAllocatorInterface;
use ArrayAccess\TrayDigita\Traits\Container\ContainerAllocatorTrait;
use ArrayAccess\TrayDigita\Traits\Service\TranslatorTrait;
use ArrayAccess\TrayDigita\Util\Filter\Consolidation;
use ArrayAccess\TrayDigita\Util\Filter\HtmlAttributes;
use Psr\Container\ContainerInterface;
use function array_filter;
use function array_map;
use function array_unique;
use function array_unshift;
use function count;
use function html_entity_decode;
use function htmlentities;
use function implode;
use function is_array;
use function is_float;
use function is_scalar;
use function is_string;
use function json_encode;
use function memory_get_peak_usage;
use function memory_get_usage;
use function microtime;
use function number_format;
use function reset;
use function round;
use function spl_object_hash;
use function sprintf;
use function uasort;
use function ucfirst;
use const JSON_UNESCAPED_SLASHES;

class Waterfall implements ContainerAllocatorInterface
{
    use TranslatorTrait,
        ContainerAllocatorTrait;

    public const UNKNOWN_SEVERITY_CLASSNAME = 'severity-unknown';

    public const SEVERITY_HTML_CLASS_NAMES = [
        SeverityInterface::CRITICAL => 'severity-critical',
        SeverityInterface::WARNING => 'severity-warning',
        SeverityInterface::NOTICE => 'severity-notice',
        SeverityInterface::INFO => 'severity-info',
        SeverityInterface::NONE => 'severity-none',
    ];

    protected string $prefix = 'waterfall-profiler-';

    protected array $severityHtmlClassesName = [];

    /**
     * @var string
     */
    protected string $unknownSeverityClassName;

    private ProfilerInterface $profiler;

    private ?MetadataFormatterInterface $formatter = null;

    public function __construct(
        ProfilerInterface $profiler,
        ContainerInterface $container = null
    ) {
        $this->profiler = $profiler;
        if ($container) {
            $this->setContainer($container);
        }
        $this->unknownSeverityClassName = $this->prefix . self::UNKNOWN_SEVERITY_CLASSNAME;
        foreach (self::SEVERITY_HTML_CLASS_NAMES as $severityName => $severityClass) {
            $this->severityHtmlClassesName[$severityName] = $this->prefix . $severityClass;
        }
    }

    public function getFormatter(): ?MetadataFormatterInterface
    {
        return $this->formatter ??= new HtmlFormatter();
    }

    public function setFormatter(?MetadataFormatterInterface $formatter): void
    {
        $this->formatter = $formatter;
    }

    /**
     * @return Profiler
     */
    public function getProfiler() : Profiler
    {
        return $this->profiler;
    }

    /**
     * @param int $type
     *
     * @return string
     */
    public function getSeverityHtmlClassName(
        int $type
    ) : string {
        return $this->severityHtmlClassesName[$type]??$this->unknownSeverityClassName;
    }

    private function filterAttribute(array $attributes) : string
    {
        if (isset($attributes['class'])) {
            if (!is_array($attributes['class'])) {
                $attributes['class'] = [$attributes['class']];
            }
            $attributes['class'] = implode(' ', $this->filterClass($attributes['class']));
        }
        $attributes = array_filter($attributes, 'is_scalar');
        return HtmlAttributes::buildAttributes($attributes);
    }

    private function createOpenTag(
        string $tag,
        array $attributes = [],
        string $content = '',
        bool $closeTag = false,
        bool $encode = true
    ) : string {
        $attributes = $this->filterAttribute($attributes);
        return sprintf(
            '<%s%s>%s',
            $tag,
            $attributes ? " " .$attributes : '',
            $content !== '' ? ($encode ? htmlentities(html_entity_decode($content)) : $content) : ''
        ) . ($closeTag ? $this->closeTag($tag) : '');
    }

    private function filterClass(array $classes) : array
    {
        $classes = array_unique(array_filter($classes, static fn ($e) => is_string($e) && $e !== ''));
        $prefix = $this->prefix;
        return array_map(
            static fn ($e) => str_starts_with($e, $prefix)
                ? $e
                : "$prefix$e",
            $classes
        );
    }

    private function appendAttributeClass(array|string $classes, array $attributes = []) : array
    {
        $classes = is_string($classes) ? [$classes] : $classes;
        $attributes['class'] = $this->filterClass($classes);
        return $attributes;
    }

    private function columnDiv(
        array|string $classes,
        array $attributes = [],
        string $content = '',
        bool $closeTag = false,
        bool $encode = true
    ) : string {
        $classes = is_string($classes) ? [$classes] : $classes;
        array_unshift($classes, 'column');
        return $this->createOpenTag(
            'div',
            $this->appendAttributeClass($classes, $attributes),
            $content,
            $closeTag,
            $encode
        );
    }

    private function columnRow(
        array|string $classes,
        array $attributes = [],
        string $content = '',
        bool $closeTag = false,
        bool $encode = true
    ) : string {
        $classes = is_string($classes) ? [$classes] : $classes;
        array_unshift($classes, 'row');
        return $this->createOpenTag(
            'div',
            $this->appendAttributeClass($classes, $attributes),
            $content,
            $closeTag,
            $encode
        );
    }

    private function closeTag(string $tag = 'div') : string
    {
        return "</$tag>";
    }

    /**
     * @param bool $showEmpty
     * @return string
     */
    public function createHtmlStructure(bool $showEmpty = false) : string
    {
        $microTimeStart = $_SERVER['REQUEST_TIME_FLOAT']
            ??$this->getProfiler()->getStartTime();
        $firstRender = $this->getProfiler()->convertMicrotime(
            microtime(true) - $microTimeStart
        );

        $memory_get_usage = memory_get_usage();
        $memory_get_real_usage = memory_get_usage(true);
        $memory_get_peak_usage = memory_get_peak_usage();
        $memory_get_real_peak_usage = memory_get_peak_usage(true);
        /* ----------------------------------------------------------------
         * ALL BENCHMARKS
         * ------------------------------------------------------------- */

        $allBenchmarks = $this->columnDiv(['tab', 'active'], ['data-tab' => 'all']); // tab
        $allBenchmarks .= $this->createSection();
        $allBenchmarks .= $this->closeTag(); // tab

        /* ----------------------------------------------------------------
         * END ALL BENCHMARKS
         * ------------------------------------------------------------- */
        $benchmarkTabGroup = [];
        foreach ($this->getProfiler()->getAggregators() as $key => $aggregator) {
            $sectionTab = $this->createSection($aggregator, $hasBenchmarks);
            if ($showEmpty === false && !$hasBenchmarks) {
                continue;
            }
            $benchmarkTabGroup[$key] = true;
            $allBenchmarks .= $this->columnDiv(['tab'], ['data-tab' => spl_object_hash($aggregator)]); // tab
            $allBenchmarks .= $sectionTab;
            $allBenchmarks .= $this->closeTag(); // aggregator
        }

        $profilerDurations = $this->getProfiler()->getDuration();
        // just start
        $html = $this->createOpenTag('div', $this->appendAttributeClass(
            'section-wrapper',
            ["data-{$this->prefix}status" => 'closed']
        ));

        /* ----------------------------------------------------------------
         * Start header
         * ------------------------------------------------------------- */

        $html .= $this->createOpenTag('div', $this->appendAttributeClass('header-section'));
        $html .= $this->columnDiv(
            ['item-section', 'header']
        );

        $html .= $this->columnRow([
            'item', 'no-border', 'no-padding', 'selector-header'
        ]);

        /* ----------------------------------------------------------------
         * Start selector
         * ------------------------------------------------------------- */
        $benchmarkMemoryUsage = 0;
        // $benchmarkRealMemoryUsage = 0;
        $totalBenchmark = 0;
        foreach ($this->getProfiler()->getGroups() as $group) {
            $benchmarkMemoryUsage += $group->getBenchmarksMemoryUsage();
            $benchmarkMemoryUsage += $group->getBenchmarksRealMemoryUsage();
            foreach ($group->getRecords() as $record) {
                $totalBenchmark += count($record);
            }
        }

        $html .= $this->columnRow(
            'item',
            [],
            (
                $this->createOpenTag(
                    'span',
                    [],
                    $this->translateContext('Memory', 'benchmark'),
                    true
                )
                . $this->createOpenTag(
                    'span',
                    [],
                    $this->createOpenTag(
                        'span',
                        $this->appendAttributeClass(
                            [
                                // 'severity-info',
                                'info'
                            ],
                            [
                                'title' => $this->translateContext(
                                    'Memory usage',
                                    'benchmark'
                                )
                            ]
                        ),
                        Consolidation::sizeFormat($memory_get_usage, 2),
                        true
                    ) . $this->createOpenTag(
                        'span',
                        $this->appendAttributeClass(
                            [
                                // 'severity-info',
                                'info'
                            ],
                            [
                                'title' => $this->translateContext(
                                    'Real memory usage',
                                    'benchmark'
                                )
                            ]
                        ),
                        Consolidation::sizeFormat($memory_get_real_usage, 2),
                        true
                    ) . $this->createOpenTag(
                        'span',
                        $this->appendAttributeClass(
                            [
                                //'severity-info',
                                'info'
                            ],
                            [
                                'title' => $this->translateContext(
                                    'Peak memory usage',
                                    'benchmark'
                                )
                            ]
                        ),
                        Consolidation::sizeFormat($memory_get_peak_usage, 2),
                        true
                    ) . $this->createOpenTag(
                        'span',
                        $this->appendAttributeClass(
                            [
                                //'severity-info',
                                'info'
                            ],
                            [
                                'title' => $this->translateContext(
                                    'Real Peak memory usage',
                                    'benchmark'
                                )
                            ]
                        ),
                        Consolidation::sizeFormat($memory_get_real_peak_usage, 2),
                        true
                    ) . $this->createOpenTag(
                        'span',
                        [],
                        $this->createOpenTag(
                            'span',
                            $this->appendAttributeClass(
                                [
                                    // 'severity-info',
                                    'info'
                                ],
                                [
                                    'title' => $this->translateContext(
                                        'Benchmark memory usage',
                                        'benchmark'
                                    )
                                ]
                            ),
                            Consolidation::sizeFormat($benchmarkMemoryUsage, 2),
                            true
                        ),
                        true,
                        false
                    ),
                    true,
                    false
                )
            ),
            true,
            false
        );

        $float = $_SERVER['REQUEST_TIME_FLOAT']??null;
        if (is_float($float)) {
            $float = $float * 1000;
        } else {
            $float = $this->getProfiler()->getStartTime();
        }
        $html .= $this->columnRow(
            'item',
            [],
            (
                $this->createOpenTag(
                    'span',
                    [],
                    $this->translateContext('Duration', 'benchmark'),
                    true
                )
                . $this->createOpenTag(
                    'span',
                    [],
                    $this->createOpenTag(
                        'span',
                        $this->appendAttributeClass(
                            [
                                // 'severity-info',
                                'info'
                            ],
                            [
                                'title' => $this->translateContext('Total duration', 'benchmark')
                            ]
                        ),
                        round(((microtime(true) * 1000) - $float), 2) .' ms',
                        true
                    ) . $this->createOpenTag(
                        'span',
                        $this->appendAttributeClass(
                            [
                                //'severity-info',
                                'info'
                            ],
                            [
                                'title' => $this->translateContext('Benchmark duration', 'benchmark')
                            ]
                        ),
                        round($profilerDurations, 2) . ' ms',
                        true
                    ) . $this->createOpenTag(
                        'span',
                        $this->appendAttributeClass(
                            [
                                //'severity-info',
                                'info'
                            ],
                            [
                                'title' => $this->translateContext('Application rendering duration', 'benchmark')
                            ]
                        ),
                        round($firstRender, 2) . ' ms',
                        true
                    ),
                    true,
                    false
                )
            ),
            true,
            false
        );
        $html .= $this->columnRow(
            'item',
            [
                'title' => $this->translateContext('Total Benchmark', 'benchmark')
            ],
            (
                $this->createOpenTag('span', [], 'Total', true)
                . $this->createOpenTag(
                    'span',
                    [],
                    $this->createOpenTag(
                        'span',
                        $this->appendAttributeClass([
                            //'severity-info',
                            'info'
                        ]),
                        (string) $totalBenchmark,
                        true
                    ),
                    true,
                    false
                )
            ),
            true,
            false
        );
        $html .= $this->columnRow(
            ['item', 'active'],
            [
                'data-target' => 'all',
                'title' => $this->translateContext('Benchmarks', 'benchmark')
            ],
            $this->translateContext('Benchmarks', 'benchmark'),
            true
        );
        foreach ($this->getProfiler()->getAggregators() as $key => $aggregator) {
            if (!isset($benchmarkTabGroup[$key])) {
                continue;
            }
            $html .= $this->columnRow(
                ['item'],
                [
                    'data-target' => spl_object_hash($aggregator),
                    'title' => $aggregator->getName()
                ],
                sprintf('%s (%d)', $aggregator->getName(), count($aggregator->getRecords())),
                true
            );
        }
        /* ----------------------------------------------------------------
         * End selector
         * ------------------------------------------------------------- */
        $html .= $this->closeTag(); // selector-header
        $html .= $this->columnRow(['item', 'no-padding', 'no-border']);
        $commands = [
            'maximize' => 'M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25'
                . ' 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15',
            'minimize' => 'M9 9V4.5M9 9H4.5M9 9L3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15'
                . ' 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5m0-4.5l5.25 5.25',
            'open' => 'M4.5 15.75l7.5-7.5 7.5 7.5',
            'close' => 'M6 18L18 6M6 6l12 12',
        ];
        foreach ($commands as $command => $svg) {
            $html .= $this->columnRow(
                ['item', 'row-square', 'right'],
                [
                    'data-command' => $command,
                    'title' => ucfirst($command),
                ],
                '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"'
                .' stroke-width="1.5" stroke="currentColor" class="w-6 h-6">'
                . '<path stroke-linecap="round" stroke-linejoin="round" d="'.$svg.'"/>'
                . '</svg>',
                true,
                false
            );
        }
        $html .= $this->closeTag();
        $html .= $this->closeTag(); // header.item-section
        // search
        $html .= $this->columnDiv(
            ['item-section', 'header', 'search']
        );
        $html .= $this->columnRow(
            ['search-section'],
            [],
            $this->createOpenTag(
                'input',
                [
                    'type' => 'search',
                    'placeholder' => $this->translateContext('Filter', 'benchmark'),
                    'autocomplete' => 'off',
                    'name' => 'waterfall-search',
                    'class' => [
                        'item-search-filter',
                        'search-input'
                    ]
                ]
            ),
            true,
            false
        );
        $html .= $this->closeTag(); // search

        $html .= $this->closeTag(); // header-section
        /* ----------------------------------------------------------------
         * End header
         * ------------------------------------------------------------- */

        /* ----------------------------------------------------------------
         * Start Content
         * ------------------------------------------------------------- */

        $html .= $this->createOpenTag('div', $this->appendAttributeClass('content-section'));
        $html .= $allBenchmarks;
        unset($allBenchmarks);
        $html .= $this->closeTag(); // content-section

        /* ----------------------------------------------------------------
         * End Content
         * ------------------------------------------------------------- */

        $html .= $this->closeTag(); // section-wrapper
        return $html;
    }

    public function render(
        bool $showEmpty = false,
        bool $darkMode = false
    ) : string {
        return $this->renderCSS($darkMode) . $this->renderJs($this->createHtmlStructure($showEmpty));
    }

    /**
     * @param RecordInterface ...$records
     * @return RecordInterface[]
     */
    public function sort(RecordInterface ...$records): array
    {
        uasort(
            $records,
            static function (RecordInterface $a, RecordInterface $b) {
                $a = $a->getStartTime();
                $b = $b->getStartTime();
                return $a === $b ? 0 : ($a < $b ? -1 : 1);
            }
        );
        return $records;
    }

    /**
     * @param ?AggregatorInterface $aggregator
     * @param $hasBenchmark
     * @return string
     */
    public function createSection(
        ?AggregatorInterface $aggregator = null,
        &$hasBenchmark = null
    ) : string {
        $profilerStartTime = $this->getProfiler()->getStartTime();
        if ($aggregator) {
            $benchmarks = $aggregator->getRecords();
        } else {
            $benchmarks = [];
            foreach ($this->getProfiler()->getGroups() as $group) {
                foreach ($group->getAllRecords() as $name => $record) {
                    $benchmarks[$name] = $record;
                }
            }
        }
        $hasBenchmark = !empty($benchmarks);
        if (!$hasBenchmark) {
            return
                $this->columnDiv(
                    ['content', 'empty'],
                    [],
                    $this->columnRow(
                        ['item'],
                        [],
                        $this->translateContext('NO BENCHMARKS', 'benchmark'),
                        true
                    ),
                    true,
                    false
                );
        }

        $benchmarks = $this->sort(...$benchmarks);

        // stop all unstopped benchmark
        array_map(
            static fn (GroupInterface $group) => $group->stopAll(),
            $this->profiler->getGroups()
        );

        $startBenchmark = reset($benchmarks)?:null;
        $profilerStartTime = $startBenchmark?->getStartTime()??$profilerStartTime;
        $totalDuration     = $this->getProfiler()->getDuration();
        $memoryUsage   = 0;
        $theDurations  = $aggregator ? 0 : $totalDuration;
        $html = $this->columnDiv(['content']);
        foreach ($benchmarks as $key => $benchmark) {
            unset($benchmarks[$key]);
            !$benchmark->isStopped() && $benchmark->stop();
            $key = spl_object_hash($benchmark);
            $duration = $benchmark->getDuration();
            $startTime = $benchmark->getStartTime();
            $severity = $benchmark->getSeverity();
            $name = $benchmark->getName();
            $groupName = $benchmark->getGroup()->getName();
            $memory = $benchmark->getUsedMemory();
            $memoryUsage += $memory;
            $info = null;
            $formatted = $this->getFormatter()->format(
                MetadataCollection::createFromBenchmarkRecord($benchmark)
            )->getData();
            if (!empty($formatted)) {
                $info = '';
                foreach ($formatted as $k => $inf) {
                    if (!is_string($k) || !is_scalar($inf)) {
                        continue;
                    }
                    $inf = (string) $inf;
                    $info .= $this->createOpenTag(
                        'div',
                        $this->appendAttributeClass('info-item')
                    );
                    $info .= $this->createOpenTag(
                        'span',
                        $this->appendAttributeClass('info-name'),
                        $k,
                        true
                    );
                    $info .= $this->createOpenTag(
                        'span',
                        $this->appendAttributeClass('info-data'),
                        $inf,
                        true,
                        false
                    );
                    $info .= $this->closeTag();
                }
                if ($info) {
                    $info = $this->createOpenTag(
                        'div',
                        $this->appendAttributeClass(
                            ['info-section', $this->getSeverityHtmlClassName($severity)],
                            ['data-info-id' => $key],
                        ),
                        $info,
                        true,
                        false
                    );
                }
            }

            $widthPercentage = number_format($duration / $totalDuration * 100, 3, '.', '');
            $offsetPercentage = number_format(
                ($startTime - $profilerStartTime) / $totalDuration * 100,
                3,
                '.',
                ''
            );
            if ($aggregator) {
                $theDurations += $duration;
            }
            $millisecond  = round($duration, 3);
            $classes = [
                'item-section',
                'item',
                'visible'
            ];
            if ($info) {
                $classes[] = 'has-info';
            }

            $html .= $this->columnDiv($classes, ['data-id' => $key]);
            $classItem = ['item'];
            if ($info) {
                $classItem[] = 'item-has-info';
            }
            $html .= $this->columnDiv($classItem);

            // name
            $html .= $this->columnRow(['item', 'item-name'], ['title' => $name], $name, true);
            // group
            $html .= $this->columnRow(['item', 'item-group'], ['title' => $groupName], $groupName, true);
            // memory
            $memoryText = Consolidation::sizeFormat($memory, 2);
            $html .= $this->columnRow(
                ['item', 'item-memory'],
                [
                    'title' => sprintf('Memory Usage: %s', $memoryText),
                    'data-memory' => $memory
                ],
                $memoryText,
                true
            );
            // duration
            $durationText = $millisecond .' ms';
            $html .= $this->columnRow(
                ['item', 'item-duration'],
                ['title' => sprintf('Time usage: %s', $durationText)],
                $durationText,
                true
            );
            // waterfall-bar
            // waterfall
            $html .= $this->columnRow(
                ['item', 'item-waterfall'],
                ['title' => sprintf('Time usage: %s', $durationText)],
                $this->createOpenTag(
                    'div',
                    $this->appendAttributeClass(
                        [
                            'waterfall-bar',
                            $this->getSeverityHtmlClassName($severity)
                        ],
                        [
                            'style' => sprintf(
                                'width:%s;left:%s;',
                                "$widthPercentage%",
                                "$offsetPercentage%"
                            )
                        ]
                    ),
                    '&nbsp;',
                    true
                ),
                true,
                false
            );
            // item
            $html .= $this->closeTag();
            $html .= $info;
            // data-id
            $html .= $this->closeTag();
        }
        $html .= $this->closeTag();
        $benchmarks = $html;

        $html = $this->createOpenTag('div', $this->appendAttributeClass('section'));
        $html .= $this->columnDiv(['item-section', 'header']);
        // header name
        $html .= $this->columnRow(
            ['item', 'item-name'],
            [],
            $this->createOpenTag('span', [], 'Name', true),
            true,
            false
        );
        $groupCount = count($this->getProfiler()->getGroups());
        // header groups
        $html .= $this->columnRow(
            ['item', 'item-group'],
            [
                'title' => !$aggregator ? sprintf(
                    $this->translateContext('Total Groups: %d', 'benchmark'),
                    $groupCount
                ) : sprintf($this->translateContext(
                    'Group : %s',
                    'benchmark'
                ), $aggregator->getGroupName())
            ],
            $this->createOpenTag(
                'span',
                [],
                $this->translateContext('Group', 'benchmark'),
                true
            ). (
            !$aggregator
                ? $this->createOpenTag(
                    'span',
                    [],
                    "($groupCount)",
                    true
                ) : ''
            ),
            true,
            false
        );
        // header memory
        $html .= $this->columnRow(
            ['item', 'item-memory'],
            [
                'title' => sprintf(
                    $this->translateContext('Total Memory: %s', 'benchmark'),
                    Consolidation::sizeFormat($memoryUsage, 2)
                )
            ],
            $this->createOpenTag(
                'span',
                [],
                $this->translateContext('Memory', 'benchmark'),
                true
            ),
            true,
            false
        );
        // header duration
        $html .= $this->columnRow(
            ['item', 'item-duration'],
            [
                'title' => sprintf(
                    $this->translateContext(
                        'Total Benchmarks Duration: %s',
                        'benchmark'
                    ),
                    round($theDurations, 2) .' ms'
                )
            ],
            $this->createOpenTag(
                'span',
                [],
                $this->translateContext('Duration', 'benchmark'),
                true
            ),
            true,
            false
        );
        // header duration
        $html .= $this->columnRow(
            ['item', 'item-waterfall'],
            [],
            $this->createOpenTag(
                'span',
                [],
                $this->translateContext('Waterfall', 'benchmark'),
                true
            ),
            true,
            false,
        );

        $html .= $this->closeTag();
        $html .= $benchmarks;
        unset($benchmarks);
        $html .= $this->closeTag(); // end
        return $html;
    }

    private function renderJs(string $html) : string
    {
        $html = json_encode(
            $html,
            JSON_UNESCAPED_SLASHES
        );
        $scriptId = $this->prefix . 'waterfall-toolbar-script';
        $toolbarId = $this->prefix . 'waterfall-toolbar';
        // @codingStandardsIgnoreStart
        return <<<HTML
<script id="$scriptId">
;(function (w) {
    const doc = w.document;
    let toolbarId = "$toolbarId";
    function load_water_fall() {
        if (doc.getElementById('#' + toolbarId)) {
            return;
        }
        let div = document.createElement('div');
        if (!document.body) {
            return;
        }
        try {
            document.documentElement.setAttribute('toolbar-profiler', 'waterfall');
        } catch (e) {
        }
        
        let storage = window.sessionStorage || window.localStorage || {
            getItem: () => {},
            setItem: () => {},
        };

        div.id = toolbarId;
        div.style.zIndex = '2147483647';
        div.innerHTML = $html;

        let wrapper = div.querySelector('.{$this->prefix}section-wrapper');
        let changeName = '{$this->prefix}open-status';
        let isResizing = false,
            offsetTop = 0,
            yetResizing = false;
        if (!wrapper) {
            return;
        }

        let closeCommand = wrapper.querySelector('[data-command=close]'),
            openCommand = wrapper.querySelector('[data-command=open]'),
            minimizeCommand = wrapper.querySelector('[data-command=minimize]'),
            maximizeCommand = wrapper.querySelector('[data-command=maximize]'),
            profilerSelector = wrapper.querySelectorAll('.{$this->prefix}item[data-target]'),
            headerSection = wrapper.querySelector('.{$this->prefix}header-section .{$this->prefix}selector-header');
        if (!headerSection
            || ! closeCommand
            || ! minimizeCommand
            || ! maximizeCommand
            || ! openCommand
            || ! profilerSelector
        ) {
            return;
        }

        const dataStatus = 'data-{$this->prefix}status';
        const selectorInfoSection = '.{$this->prefix}info-section';
        const searchSection = wrapper.querySelector('.{$this->prefix}search input');
        const selectorHasInfo = '.{$this->prefix}item-has-info';
        const activeStatus = '{$this->prefix}active';
        const hiddenStatus = '{$this->prefix}hidden';
        const visibleStatus = '{$this->prefix}visible';
        const resizeClass = '{$this->prefix}resize';
        let headerHeight = 28;
        doc.body.style.marginBottom = headerHeight + 'px';
        let tabs = wrapper.querySelectorAll('[data-tab]');
        if (storage.getItem(changeName) === 'opened'
            || storage.getItem(changeName) === 'maximized'
        ) {
            wrapper.setAttribute(dataStatus, 'opened');
            doc.body.style.marginBottom = (window.getComputedStyle(wrapper).height||250) + 'px';
        }
        document.body.append(div);
        div = null;
        const
            changeStatus = function (status) {
                wrapper.setAttribute(dataStatus, status);
                wrapper.removeAttribute('style');
                offsetTop = 0;
                isResizing = false;
                storage.setItem(changeName, status);
                if (status === 'closed') {
                    wrapper.querySelectorAll(selectorHasInfo).forEach(function (e) {
                        let info = e.parentNode.querySelector(selectorInfoSection);
                        if (!info) {
                            return;
                        }
                        e.classList.remove(activeStatus);
                        info.classList.remove(activeStatus);
                    });
                    doc.body.style.marginBottom = headerHeight + 'px';
                } else if (status === 'opened') {
                    doc.body.style.marginBottom = (window.getComputedStyle(wrapper).height||250) + 'px';
                } else {
                    doc.body.style.marginBottom = status === 'maximized'? '100vh' : window.getComputedStyle(wrapper).height + 'px';
                }

            },
            isAllowResize = function () {
                return wrapper.getAttribute(dataStatus) === 'opened';
            };
        wrapper.querySelectorAll(selectorHasInfo).forEach(function (e) {
            let info = e.parentNode.querySelector(selectorInfoSection);
            if (!info) {
                return;
            }
            e.addEventListener('click', function () {
                let hasActive = e.classList.contains(activeStatus);
                if (hasActive) {
                    e.classList.remove(activeStatus);
                    info.classList.remove(activeStatus);
                } else {
                    e.classList.add(activeStatus);
                    info.classList.add(activeStatus);
                }
            });
        });

        closeCommand.addEventListener('click', function () {
            changeStatus('closed');
        });
        maximizeCommand.addEventListener('click', function () {
            changeStatus('maximized');
        });
        openCommand.addEventListener('click', function () {
            changeStatus('opened');
        });
        minimizeCommand.addEventListener('click', function () {
            changeStatus('opened');
        });
        /*
         * Search
         */
         function restoreHide() {
           wrapper
            .querySelectorAll('.{$this->prefix}tab .{$this->prefix}item-section')
            .forEach(function (e) {
                e.classList.remove(hiddenStatus);
                e.classList.add(hiddenStatus);
            });
        }
        searchSection.addEventListener('keyup', function (e) {
            let selector = wrapper.querySelector('.{$this->prefix}tab.' + activeStatus);
                selector = selector
                    ? selector.querySelectorAll('.{$this->prefix}item-section')
                    : null;
            function clearHidden()
            {
                if (selector) {
                    selector.forEach(function (e) {
                        e.classList.remove(hiddenStatus);
                        e.classList.add(visibleStatus);
                    });
                }
            }
    
            if (e.code === 'Escape') {
                searchSection.value = '';
                clearHidden();
                return;
            }
    
            if (!selector) {
                return;
            }
            let value = searchSection.value.trim().toLowerCase();
            if (value === '') {
                clearHidden();
                return;
            }
    
            selector.forEach(function (e) {
                let text = e.querySelector('.{$this->prefix}item-name');
                if (!text) {
                    e.classList.add(hiddenStatus);
                    return;
                }
                text = text.textContent;
                if (text.toLowerCase().includes(value)) {
                    e.classList.remove(hiddenStatus);
                    e.classList.add(visibleStatus);
                } else {
                    e.classList.add(hiddenStatus);
                    e.classList.remove(visibleStatus);
                }
            });
        });
        searchSection.addEventListener('input', function (e) {
            if (e.inputType === undefined) {
                restoreHide();
            }
        });

        profilerSelector.forEach(function (selector) {
            let tab,
                target = selector.getAttribute('data-target');
            if (!target) {
                return;
            }
            try {
                tab = wrapper.querySelector('[data-tab="'+target+'"]')
            } catch (exception) {
                return;
            }
            selector.addEventListener('click', function (e) {
                e.preventDefault();
                if (wrapper.getAttribute(dataStatus) === 'closed') {
                    changeStatus('opened');
                }
                tab.classList.add(activeStatus);
                selector.classList.add(activeStatus);
                profilerSelector.forEach(function (a) {
                    if (selector === a) {
                        return;
                    }
                    a.classList.remove(activeStatus);
                });
                tabs.forEach(function (a) {
                    if (a === tab) {
                        return;
                    }
                    a.classList.remove(activeStatus);
                });
                restoreHide();
                let evt = new KeyboardEvent('keyup', {key: 'Shift', code: 'ShiftLeft'});
                searchSection.dispatchEvent(evt);
            })
        });
    
        /* ------
         * Resize
         */
         let bounding,
            posNow;
    
        wrapper.addEventListener("mousedown", function (e) {
            isResizing = isAllowResize() && e.offsetY <= 5 && e.offsetY >= -5; 
        });
    
        doc.addEventListener('mousemove', function (e) {
            // we don't want to do anything if we aren't resizing.
            if (!isResizing) {
                return;
            }
            if (!wrapper.classList.contains(resizeClass)) {
                wrapper.classList.add(resizeClass);
            }
            bounding = wrapper.getBoundingClientRect();
            posNow = (e.clientY - bounding.top); 
            offsetTop = bounding.height - posNow;
            if (offsetTop < headerHeight) {
                return;
            }
            yetResizing = true;
            wrapper.style.height = offsetTop + 'px';
            doc.body.style.marginBottom = offsetTop + 'px';
        });
    
        doc.addEventListener('mouseup', function () {
            // stop resizing
            wrapper.classList.remove(resizeClass);
            if (!isResizing || ! yetResizing) {            
                yetResizing = false;
                isResizing = false;
                return;
            }
            yetResizing = false;
            isResizing = false;
            if (offsetTop <= headerHeight) {
                changeStatus('closed');
            } else if (window.innerHeight <= offsetTop) {
                changeStatus('maximized');
            }
        });
    }

    if (doc.readyState === 'complete' || doc.readyState === 'interactive') {
        load_water_fall();
    } else {
        document.addEventListener('DOMContentLoaded', load_water_fall, false);
    }
})(window);
</script>
HTML;
        // @codingStandardsIgnoreEnd
    }

    /**
     * @noinspection CssInvalidHtmlTagReference
     * @noinspection CssUnresolvedCustomProperty
     */
    private function renderCSS(bool $darkMode) : string
    {
        $html = $darkMode
            ? <<<HTML
<style id="{$this->prefix}waterfall-toolbar-style-color">
html .{$this->prefix}section-wrapper {
    --waterfall-toolbar-color: #fff;
    --waterfall-toolbar-bg: #292a2d;
}
</style>
HTML
            : '';

        // @codingStandardsIgnoreStart
        $html .= <<<HTML
<style id="{$this->prefix}waterfall-toolbar-style">
/*# sourceURL=/{$this->prefix}style.css */
:root {
    --waterfall-toolbar-color: #555;
    --waterfall-toolbar-bg: #fafafa;
}
.{$this->prefix}section-wrapper {
    position: fixed;
    z-index: 2147483647;
    left:0;
    right:0;
    bottom:0;
    font-weight: lighter;
    margin: 0;
    padding: 0;
    border: 0;
    font-size: 13px;
    line-height: 1.15;
    -webkit-text-size-adjust: 100%;
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, 
        "Helvetica Neue", Arial, "Noto Sans", "Liberation Sans",
        sans-serif, "Apple Color Emoji", "Segoe UI Emoji",
         "Segoe UI Symbol", "Noto Color Emoji";
    vertical-align: middle;
    max-height: 100vh;
    height: 30px;
    -webkit-font-smoothing: subpixel-antialiased;
    transition: height ease .1s;
    background: var(--waterfall-toolbar-bg, #fff);
    color: var(--waterfall-toolbar-color, #555);
    /* background-color: #292a2d;
    color: #dcdddd; */
}
.{$this->prefix}section-wrapper.{$this->prefix}resize {
    transition: none;
}
.{$this->prefix}section-wrapper code,
.{$this->prefix}section-wrapper pre,
.{$this->prefix}section-wrapper kbd {
    font-size: .8em;
    font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
}
.{$this->prefix}section-wrapper code {
    color: inherit;
    white-space: nowrap;
    padding: initial;
    background: transparent;
}
.{$this->prefix}section-wrapper pre {
    word-break: break-word;
    word-wrap: break-word;
    white-space: pre-wrap;
    padding:0;
    margin:0;
    color: inherit;
    -moz-tab-size: 4;
    tab-size: 4;
    overflow: auto;
    border: none;
    background: transparent;
}

.{$this->prefix}section-wrapper[data-{$this->prefix}status=opened]::before {
    content: '';
    background-color: transparent;
    position: absolute;
    left: 0;
    width: 100%;
    height: 5px;
    z-index: 9999;
    cursor: ns-resize;
}
.{$this->prefix}section-wrapper,
.{$this->prefix}section-wrapper::before,
.{$this->prefix}section-wrapper::after,
.{$this->prefix}section-wrapper *,
.{$this->prefix}section-wrapper *::before,
.{$this->prefix}section-wrapper *::after {
    box-sizing:border-box;
}
.{$this->prefix}content-section {
    position: relative;
    height:100%;
    width:100%;
    visibility: visible;
    opacity: 1;
    transform: translateY(0);
}
.{$this->prefix}tab,
.{$this->prefix}section {
    height: 100%;
    position:relative;
    width: 100%;
}
.{$this->prefix}column.{$this->prefix}tab {
    display: none;
}
.{$this->prefix}column.{$this->prefix}tab.{$this->prefix}active,
.{$this->prefix}column {
    display: flex;
    flex-wrap: wrap;
    flex-basis: 100%;
    align-content: flex-start;
}

.{$this->prefix}column.{$this->prefix}item,
.{$this->prefix}column.{$this->prefix}header {
    display: flex;
    flex-direction: row;
    flex-wrap: nowrap;
    justify-content: space-between;
    align-items: stretch;
}
.{$this->prefix}column.{$this->prefix}item.{$this->prefix}has-info {
    flex-basis: 100%;
    width: 100%;
    flex-direction: column;
}
.{$this->prefix}column .{$this->prefix}item-has-info {
    cursor: pointer;
}
.{$this->prefix}info-section {
    display:none;
    padding: 1em;
    border-top: 1px solid rgba(0,0,0, .1);
    border-bottom: 1px solid rgba(0,0,0, .1);
    flex-direction: column;
    /* background-color: #fff; */
}
.{$this->prefix}info-section.{$this->prefix}active {
    display: flex;
    flex-direction: column;
    flex-wrap: nowrap;
    align-content: stretch;
    justify-content: space-between;
}
.{$this->prefix}info-section .{$this->prefix}info-item {
    display: flex;
    flex-direction: row;
    flex-wrap: nowrap;
    justify-content: flex-start;
    flex-basis: 100%;
    line-height: 1.5;
    align-items: flex-start;
    padding: 1em 0;
    border-bottom: 1px dotted rgba(0,0,0, .15);
}
.{$this->prefix}info-section .{$this->prefix}info-item:last-child {
    border:0;
}
.{$this->prefix}info-section .{$this->prefix}info-item .{$this->prefix}info-data {
    flex-basis: 100%;
    white-space: normal;
    word-wrap: break-word;
    word-break: break-word;
}
.{$this->prefix}info-section .{$this->prefix}info-item .{$this->prefix}info-name {
    /*flex-basis: 200px;*/
    font-weight: 500;
    flex: 0 0 200px;
}
.{$this->prefix}row {
    display: flex;
    flex-basis: 100%;
}
.{$this->prefix}header-section .{$this->prefix}column.{$this->prefix}header {
    display: flex;
    justify-content: space-between;
    flex-direction: row;
}
.{$this->prefix}header-section .{$this->prefix}column.{$this->prefix}header .{$this->prefix}row {
    justify-content: flex-start;
    flex-basis: auto;
}
.{$this->prefix}header-section .{$this->prefix}column.{$this->prefix}header {
    border-top: 1px solid rgba(0,0,0,.15);
}
.{$this->prefix}header-section .{$this->prefix}column.{$this->prefix}header .{$this->prefix}row.{$this->prefix}row-square {
    flex-basis: 100%;
    align-self: stretch;
    width: 30px;
    text-align: center;
}
.{$this->prefix}item.{$this->prefix}row {
    padding: 0.6em;
    /* border-left: 1px solid #eee; */
    border-left: 1px solid rgba(0,0,0,.06);
}

.{$this->prefix}item.{$this->prefix}no-padding,
.{$this->prefix}item.{$this->prefix}row.{$this->prefix}no-padding {
    padding:0;
}
.{$this->prefix}item.{$this->prefix}row.{$this->prefix}no-border {
    border:0;
}
.{$this->prefix}column.{$this->prefix}header {
    font-weight: bold;
    top:0;
    position: sticky;
    z-index:999;
    border-bottom: 1px solid rgba(0,0,0,.15);
    background-color: rgba(0,0,0,.05);
}

.{$this->prefix}column.{$this->prefix}header .{$this->prefix}row {
    border-color: rgba(0,0,0,.15);
    font-size:.9em;
    letter-spacing: 1px;
    display:flex;
    justify-content: space-between;
}

.{$this->prefix}item {
    overflow-x:hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    display:block;
}
/*.{$this->prefix}item[class*=profiler-item-] {*/
/*    flex: 0 0 100%;*/
/*}*/
.{$this->prefix}item.{$this->prefix}item-name {
    max-width: 200px;
}
.{$this->prefix}item.{$this->prefix}item-group {
    max-width: 150px;
}
.{$this->prefix}item.{$this->prefix}item-duration,
.{$this->prefix}item.{$this->prefix}item-memory {
    max-width: 100px;
    text-align: right;
}
.{$this->prefix}waterfall-bar {
    position: relative;
    background: rgba(0,0, 0, .3);
    padding: 0 3px;
    height: 8px;
    border-radius: 5px;
    display: inline-block;
    vertical-align: middle;
}
.{$this->prefix}content {
    /*height: calc(100% - 58px);*/
    height: calc(100% - 92px);
    position: relative;
    z-index: 900;
    overflow: auto;
}
.{$this->prefix}section-wrapper .{$this->prefix}hidden {
    display: none !important;
}
.{$this->prefix}content .{$this->prefix}item.{$this->prefix}visible:nth-child(even) {
    background: rgba(200, 200, 200, .15);
}
.{$this->prefix}content > .{$this->prefix}item:hover,
.{$this->prefix}content > .{$this->prefix}item.{$this->prefix}active {
    background: rgba(200, 200, 200, .35);
}
.{$this->prefix}content > .{$this->prefix}item:hover .{$this->prefix}item.{$this->prefix}row {
    border-left-color: rgba(0,0,0,.1);
}
.{$this->prefix}severity-none,
.{$this->prefix}waterfall-bar.{$this->prefix}severity-none {
    background-color: #379956;
    color: #fff;
}

.{$this->prefix}severity-info,
.{$this->prefix}waterfall-bar.{$this->prefix}severity-info {
    background-color: #60beae;
    color: #fff;
}
.{$this->prefix}severity-warning,
.{$this->prefix}waterfall-bar.{$this->prefix}severity-warning {
    background-color: #f4b575;
    color: #fff;
}
.{$this->prefix}severity-critical,
.{$this->prefix}waterfall-bar.{$this->prefix}severity-critical {
    background-color: #f5757b;
    color: #fff;
}
.{$this->prefix}severity-notice,
.{$this->prefix}waterfall-bar.{$this->prefix}severity-notice {
    background-color: #2e77ae;
    color: #fff;
}
.{$this->prefix}header,
.{$this->prefix}resize {
    user-select: none;
    -moz-user-select: none;
}
.{$this->prefix}header.{$this->prefix}search {
    padding: .4em 1em;
}
.{$this->prefix}header.{$this->prefix}search input {
    padding: .4em .8em;
    width: 200px;
    outline:none;
    box-shadow:none;
    border-radius: 2px;
    font-size: .9em;
    border: 1px solid rgba(0,0,0,.3);
    /*border: 1px solid var(--waterfall-toolbar-color, #555);*/
    background: var(--waterfall-toolbar-bg, #fff);
    color: var(--waterfall-toolbar-color, #555);
}

.{$this->prefix}item[data-target],
.{$this->prefix}item[data-command]{
    cursor: pointer;
    user-select: none;
    -moz-user-select: none;
}
.{$this->prefix}item[data-command]:hover,
.{$this->prefix}item[data-target]:hover,
.{$this->prefix}item[data-target].{$this->prefix}active {
    background: rgba(200, 200, 200, .35);
}
.{$this->prefix}section-wrapper[data-{$this->prefix}status=maximized] .{$this->prefix}row-square[data-command=maximize],
.{$this->prefix}section-wrapper[data-{$this->prefix}status=opened] .{$this->prefix}row-square[data-command=minimize],
.{$this->prefix}section-wrapper .{$this->prefix}row-square[data-command=open],
.{$this->prefix}section-wrapper[data-{$this->prefix}status=closed] .{$this->prefix}row-square[data-command=close],
.{$this->prefix}section-wrapper[data-{$this->prefix}status=closed] .{$this->prefix}row-square[data-command=minimize] {
    display: none;
}
.{$this->prefix}section-wrapper[data-{$this->prefix}status=closed] .{$this->prefix}row-square[data-command=open] {
    display: flex;
}
.{$this->prefix}section-wrapper[data-{$this->prefix}status=closed] .{$this->prefix}content-section {
    visibility: hidden;
    opacity: 0;
    transform: translateY(100%);
}
.{$this->prefix}section-wrapper[data-{$this->prefix}status=closed] {
    height: 30px;
}
.{$this->prefix}section-wrapper[data-{$this->prefix}status=opened] {
    /*max-height: 300px;*/
    height: 250px;
    min-height: 30px;
}
.{$this->prefix}section-wrapper[data-{$this->prefix}status=maximized] {
    height: 100vh;
    max-height: 100vh;
}
.{$this->prefix}section-wrapper .{$this->prefix}no-padding.{$this->prefix}selector-header > div {
    line-height: 28px;
    padding: 0 .6em;
}
.{$this->prefix}icon {
    width: 20px;
    display: flex;
    align-items: stretch;
    justify-content: space-evenly;
    flex-direction: row;
    padding: 0 2px;
}
.{$this->prefix}icon svg {
    width: auto;
}
.{$this->prefix}info {
    padding: 4px 5px 5px;
    margin: .3em;
    border-radius: 3px;
    background: rgba(0,0,0, .1);
    color: var(--waterfall-toolbar-color, #555);
}
.{$this->prefix}content.{$this->prefix}empty {
    height: 100%;
    letter-spacing: 1px;
    font-weight: bold;
}

.{$this->prefix}content.{$this->prefix}empty .{$this->prefix}item {
    text-align: center;
    position: absolute;
    width: 100%;
    top: 50%;
    margin-top: -2em;
    border-width: 0;
}
.{$this->prefix}content.{$this->prefix}empty .{$this->prefix}item:hover {
    background-color: transparent;
}
.{$this->prefix}header-section .{$this->prefix}selector-header {
    width: 100%;
    overflow-x: auto;
    overflow-y: hidden;
    touch-action: pan-x;
    scrollbar-width: none;
}
.{$this->prefix}header-section .{$this->prefix}selector-header::-webkit-scrollbar {
    display:none;
}
.{$this->prefix}header-section .{$this->prefix}column.{$this->prefix}header .{$this->prefix}selector-header > .{$this->prefix}row {
    display: inline-block;
    flex-basis: unset;
    justify-content: unset;
    overflow: visible;
}
</style>
HTML; // @codingStandardsIgnoreEnd
        return $html;
    }

    public function __toString() : string
    {
        return $this->render();
    }
}
