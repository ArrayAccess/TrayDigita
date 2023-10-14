<?php
/** @noinspection ALL */
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
use ArrayAccess\TrayDigita\Util\Filter\Consolidation;
use function array_filter;
use function array_map;
use function array_unique;
use function array_unshift;
use function count;
use function html_entity_decode;
use function htmlentities;
use function htmlspecialchars;
use function implode;
use function is_float;
use function is_scalar;
use function is_string;
use function json_encode;
use function memory_get_peak_usage;
use function memory_get_usage;
use function microtime;
use function number_format;
use function preg_match;
use function reset;
use function round;
use function spl_object_hash;
use function sprintf;
use function uasort;
use function ucfirst;
use const ARRAY_FILTER_USE_KEY;
use const JSON_UNESCAPED_SLASHES;

class Waterfall
{
    const UNKNOWN_SEVERITY_CLASSNAME = 'waterfall-profiler-severity-unknown';

    const SEVERITY_HTML_CLASS_NAMES = [
        SeverityInterface::CRITICAL => 'waterfall-profiler-severity-critical',
        SeverityInterface::WARNING => 'waterfall-profiler-severity-warning',
        SeverityInterface::NOTICE => 'waterfall-profiler-severity-notice',
        SeverityInterface::INFO => 'waterfall-profiler-severity-info',
        SeverityInterface::NONE => 'waterfall-profiler-severity-none',
    ];

    private ProfilerInterface $profiler;

    private ?MetadataFormatterInterface $formatter = null;

    public function __construct(ProfilerInterface $profiler)
    {
        $this->profiler = $profiler;
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
    public static function getSeverityHtmlClassName(
        int $type
    ) : string {
        return self::SEVERITY_HTML_CLASS_NAMES[$type]??self::UNKNOWN_SEVERITY_CLASSNAME;
    }

    private function filterAttribute(array $attributes) : array
    {
        $attributes = array_filter(
            $attributes,
            static fn ($e) => is_string($e) && preg_match('~^[a-z](?:[a-z0-9_-]*[a-z0-9])?$~i', $e),
            ARRAY_FILTER_USE_KEY
        );
        if (isset($attributes['class'])) {
            $attributes['class'] = implode(' ', $this->filterClass($attributes['class']));
        }
        $attributes = array_filter($attributes, 'is_scalar');
        $attribute  = [];
        foreach ($attributes as $key => $v) {
            $v = (string) $v;
            if ($v === '') {
                $attribute[] = $key;
                continue;
            }
            $attribute[] = sprintf(
                '%s="%s"',
                htmlspecialchars($key),
                htmlspecialchars($v)
            );
        }
        return $attribute;
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
            !empty($attributes) ? " " .implode(' ', $attributes) : '',
            $content !== '' ? ($encode ? htmlentities(html_entity_decode($content)) : $content) : ''
        ) . ($closeTag ? $this->closeTag($tag) : '');
    }

    private function filterClass(array $classes) : array
    {
        $classes = array_unique(array_filter($classes, static fn ($e) => is_string($e) && $e !== ''));
        return array_map(
            static fn ($e) => str_starts_with($e, 'waterfall-profiler-')
                ? $e
                : "waterfall-profiler-$e",
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
            ['data-waterfall-profiler-status' => 'closed']
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
                $this->createOpenTag('span', [], 'Memory', true)
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
                                'title' => 'Memory usage'
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
                                'title' => 'Real memory usage'
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
                                'title' => 'Peak memory usage'
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
                                'title' => 'Real Peak memory usage'
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
                                    'title' => 'Benchmark memory usage'
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
                $this->createOpenTag('span', [], 'Duration', true)
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
                                'title' => 'Total duration'
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
                                'title' => 'Benchmark duration'
                            ]
                        ),
                        round($profilerDurations, 2) . ' ms',
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
                'title' => 'Total Benchmark'
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
                'title' => 'Benchmarks'
            ],
            'Benchmarks',
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
                    'placeholder' => 'Filter',
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
                        'NO BENCHMARKS',
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
                            ['info-section', self::getSeverityHtmlClassName($severity)],
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
                            self::getSeverityHtmlClassName($severity)
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
                    'Total Groups: %d',
                    $groupCount
                ) : sprintf('Group : %s', $aggregator->getGroupName())
            ],
            $this->createOpenTag(
                'span',
                [],
                'Group',
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
                    'Total Memory: %s',
                    Consolidation::sizeFormat($memoryUsage, 2)
                )
            ],
            $this->createOpenTag('span', [], 'Memory', true),
            true,
            false
        );
        // header duration
        $html .= $this->columnRow(
            ['item', 'item-duration'],
            [
                'title' => sprintf(
                    'Total Benchmarks Duration: %s',
                    round($theDurations, 2) .' ms'
                )
            ],
            $this->createOpenTag('span', [], 'Duration', true),
            true,
            false
        );
        // header duration
        $html .= $this->columnRow(
            ['item', 'item-waterfall'],
            [],
            $this->createOpenTag('span', [], 'Waterfall', true),
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
        // @codingStandardsIgnoreStart
        return <<<HTML
<script id="waterfall-profiler-waterfall-toolbar-script">
;(function (w) {
    const doc = w.document;
    function load_water_fall() {
        if (doc.getElementById('#waterfall-profiler-waterfall-toolbar')) {
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
        div.id = 'waterfall-profiler-waterfall-toolbar';
        div.style.zIndex = '2147483647';
        div.innerHTML = $html;

        let wrapper = div.querySelector('.waterfall-profiler-section-wrapper');
        let changeName = 'waterfall-profiler-open-status';
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
            profilerSelector = wrapper.querySelectorAll('.waterfall-profiler-item[data-target]'),
            headerSection = wrapper.querySelector('.waterfall-profiler-header-section .waterfall-profiler-selector-header');
        if (!headerSection
            || ! closeCommand
            || ! minimizeCommand
            || ! maximizeCommand
            || ! openCommand
            || ! profilerSelector
        ) {
            return;
        }
        const searchSection = wrapper.querySelector('.waterfall-profiler-search input');
        let headerHeight = 28;
        doc.body.style.marginBottom = headerHeight + 'px';
        let tabs = wrapper.querySelectorAll('[data-tab]');
        if (storage.getItem(changeName) === 'opened'
            || storage.getItem(changeName) === 'maximized'
        ) {
            wrapper.setAttribute('data-waterfall-profiler-status', 'opened');
            doc.body.style.marginBottom = (window.getComputedStyle(wrapper).height||250) + 'px';
        }
        document.body.append(div);
        div = null;
        const
            changeStatus = function (status) {
                wrapper.setAttribute('data-waterfall-profiler-status', status);
                wrapper.removeAttribute('style');
                offsetTop = 0;
                isResizing = false;
                storage.setItem(changeName, status);
                if (status === 'closed') {
                    wrapper.querySelectorAll('.waterfall-profiler-item-has-info').forEach(function (e) {
                        let info = e.parentNode.querySelector('.waterfall-profiler-info-section');
                        if (!info) {
                            return;
                        }
                        e.classList.remove('waterfall-profiler-active');
                        info.classList.remove('waterfall-profiler-active');
                    });
                    doc.body.style.marginBottom = headerHeight + 'px';
                } else if (status === 'opened') {
                    doc.body.style.marginBottom = (window.getComputedStyle(wrapper).height||250) + 'px';
                } else {
                    doc.body.style.marginBottom = status === 'maximized'? '100vh' : window.getComputedStyle(wrapper).height + 'px';
                }

            },
            isAllowResize = function () {
                return wrapper.getAttribute('data-waterfall-profiler-status') === 'opened';
            };
        wrapper.querySelectorAll('.waterfall-profiler-item-has-info').forEach(function (e) {
            let info = e.parentNode.querySelector('.waterfall-profiler-info-section');
            if (!info) {
                return;
            }
            e.addEventListener('click', function () {
                let hasActive = e.classList.contains('waterfall-profiler-active');
                if (hasActive) {
                    e.classList.remove('waterfall-profiler-active');
                    info.classList.remove('waterfall-profiler-active');
                } else {
                    e.classList.add('waterfall-profiler-active');
                    info.classList.add('waterfall-profiler-active');
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
            .querySelectorAll('.waterfall-profiler-tab .waterfall-profiler-item-section')
            .forEach(function (e) {
                e.classList.remove('waterfall-profiler-hidden');
                e.classList.add('waterfall-profiler-visible');
            });
        }
        searchSection.addEventListener('keyup', function (e) {
            let selector = wrapper.querySelector('.waterfall-profiler-tab.waterfall-profiler-active');
                selector = selector
                    ? selector.querySelectorAll('.waterfall-profiler-item-section')
                    : null;
            function clearHidden()
            {
                if (selector) {
                    selector.forEach(function (e) {
                        e.classList.remove('waterfall-profiler-hidden');
                        e.classList.add('waterfall-profiler-visible');
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
                let text = e.querySelector('.waterfall-profiler-item-name');
                if (!text) {
                    e.classList.add('waterfall-profiler-hidden');
                    return;
                }
                text = text.textContent;
                if (text.toLowerCase().includes(value)) {
                    e.classList.remove('waterfall-profiler-hidden');
                    e.classList.add('waterfall-profiler-visible');
                } else {
                    e.classList.add('waterfall-profiler-hidden');
                    e.classList.remove('waterfall-profiler-visible');
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
                if (wrapper.getAttribute('data-waterfall-profiler-status') === 'closed') {
                    changeStatus('opened');
                }
                tab.classList.add('waterfall-profiler-active');
                selector.classList.add('waterfall-profiler-active');
                profilerSelector.forEach(function (a) {
                    if (selector === a) {
                        return;
                    }
                    a.classList.remove('waterfall-profiler-active');
                });
                tabs.forEach(function (a) {
                    if (a === tab) {
                        return;
                    }
                    a.classList.remove('waterfall-profiler-active');
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
            if (!wrapper.classList.contains('waterfall-profiler-resize')) {
                wrapper.classList.add('waterfall-profiler-resize');
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
            wrapper.classList.remove('waterfall-profiler-resize');
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

    private function renderCSS(bool $darkMode) : string
    {
        $html = $darkMode
            ? <<<'HTML'
<style id="waterfall-profiler-waterfall-toolbar-style-color">
html .waterfall-profiler-section-wrapper {
    --waterfall-toolbar-color: #fff;
    --waterfall-toolbar-bg: #292a2d;
}
</style>
HTML
            : '';

        // @codingStandardsIgnoreStart
        $html .= <<<'HTML'
<style id="waterfall-profiler-waterfall-toolbar-style">
/*# sourceURL=/waterfall-profiler.css */
:root {
    --waterfall-toolbar-color: #555;
    --waterfall-toolbar-bg: #fafafa;
}
.waterfall-profiler-section-wrapper {
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
.waterfall-profiler-section-wrapper.waterfall-profiler-resize {
    transition: none;
}
.waterfall-profiler-section-wrapper code,
.waterfall-profiler-section-wrapper pre,
.waterfall-profiler-section-wrapper kbd {
    font-size: .8em;
    font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
}
.waterfall-profiler-section-wrapper code {
    color: inherit;
    white-space: nowrap;
    padding: initial;
    background: transparent;
}
.waterfall-profiler-section-wrapper pre {
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

.waterfall-profiler-section-wrapper[data-waterfall-profiler-status=opened]::before {
    content: '';
    background-color: transparent;
    position: absolute;
    left: 0;
    width: 100%;
    height: 5px;
    z-index: 9999;
    cursor: ns-resize;
}
.waterfall-profiler-section-wrapper,
.waterfall-profiler-section-wrapper::before,
.waterfall-profiler-section-wrapper::after,
.waterfall-profiler-section-wrapper *,
.waterfall-profiler-section-wrapper *::before,
.waterfall-profiler-section-wrapper *::after {
    box-sizing:border-box;
}
.waterfall-profiler-content-section {
    position: relative;
    height:100%;
    width:100%;
    visibility: visible;
    opacity: 1;
    transform: translateY(0);
}
.waterfall-profiler-tab,
.waterfall-profiler-section {
    height: 100%;
    position:relative;
    width: 100%;
}
.waterfall-profiler-column.waterfall-profiler-tab {
    display: none;
}
.waterfall-profiler-column.waterfall-profiler-tab.waterfall-profiler-active,
.waterfall-profiler-column {
    display: flex;
    flex-wrap: wrap;
    flex-basis: 100%;
    align-content: flex-start;
}

.waterfall-profiler-column.waterfall-profiler-item,
.waterfall-profiler-column.waterfall-profiler-header {
    display: flex;
    flex-direction: row;
    flex-wrap: nowrap;
    justify-content: space-between;
    align-items: stretch;
}
.waterfall-profiler-column.waterfall-profiler-item.waterfall-profiler-has-info {
    flex-basis: 100%;
    width: 100%;
    flex-direction: column;
}
.waterfall-profiler-column .waterfall-profiler-item-has-info {
    cursor: pointer;
}
.waterfall-profiler-info-section {
    display:none;
    padding: 1em;
    border-top: 1px solid rgba(0,0,0, .1);
    border-bottom: 1px solid rgba(0,0,0, .1);
    flex-direction: column;
    /* background-color: #fff; */
}
.waterfall-profiler-info-section.waterfall-profiler-active {
    display: flex;
    flex-direction: column;
    flex-wrap: nowrap;
    align-content: stretch;
    justify-content: space-between;
}
.waterfall-profiler-info-section .waterfall-profiler-info-item {
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
.waterfall-profiler-info-section .waterfall-profiler-info-item:last-child {
    border:0;
}
.waterfall-profiler-info-section .waterfall-profiler-info-item .waterfall-profiler-info-data {
    flex-basis: 100%;
    white-space: normal;
    word-wrap: break-word;
    word-break: break-word;
}
.waterfall-profiler-info-section .waterfall-profiler-info-item .waterfall-profiler-info-name {
    /*flex-basis: 200px;*/
    font-weight: 500;
    flex: 0 0 200px;
}
.waterfall-profiler-row {
    display: flex;
    flex-basis: 100%;
}
.waterfall-profiler-header-section .waterfall-profiler-column.waterfall-profiler-header {
    display: flex;
    justify-content: space-between;
    flex-direction: row;
}
.waterfall-profiler-header-section .waterfall-profiler-column.waterfall-profiler-header .waterfall-profiler-row {
    justify-content: flex-start;
    flex-basis: auto;
}
.waterfall-profiler-header-section .waterfall-profiler-column.waterfall-profiler-header {
    border-top: 1px solid rgba(0,0,0,.15);
}
.waterfall-profiler-header-section .waterfall-profiler-column.waterfall-profiler-header .waterfall-profiler-row.waterfall-profiler-row-square {
    flex-basis: 100%;
    align-self: stretch;
    width: 30px;
    text-align: center;
}
.waterfall-profiler-item.waterfall-profiler-row {
    padding: 0.6em;
    /* border-left: 1px solid #eee; */
    border-left: 1px solid rgba(0,0,0,.06);
}

.waterfall-profiler-item.waterfall-profiler-no-padding,
.waterfall-profiler-item.waterfall-profiler-row.waterfall-profiler-no-padding {
    padding:0;
}
.waterfall-profiler-item.waterfall-profiler-row.waterfall-profiler-no-border {
    border:0;
}
.waterfall-profiler-column.waterfall-profiler-header {
    font-weight: bold;
    top:0;
    position: sticky;
    z-index:999;
    border-bottom: 1px solid rgba(0,0,0,.15);
    background-color: rgba(0,0,0,.05);
}

.waterfall-profiler-column.waterfall-profiler-header .waterfall-profiler-row {
    border-color: rgba(0,0,0,.15);
    font-size:.9em;
    letter-spacing: 1px;
    display:flex;
    justify-content: space-between;
}

.waterfall-profiler-item {
    overflow-x:hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    display:block;
}
/*.waterfall-profiler-item[class*=profiler-item-] {*/
/*    flex: 0 0 100%;*/
/*}*/
.waterfall-profiler-item.waterfall-profiler-item-name {
    max-width: 200px;
}
.waterfall-profiler-item.waterfall-profiler-item-group {
    max-width: 150px;
}
.waterfall-profiler-item.waterfall-profiler-item-duration,
.waterfall-profiler-item.waterfall-profiler-item-memory {
    max-width: 100px;
    text-align: right;
}
.waterfall-profiler-waterfall-bar {
    position: relative;
    background: rgba(0,0, 0, .3);
    padding: 0 3px;
    height: 8px;
    border-radius: 5px;
    display: inline-block;
    vertical-align: middle;
}
.waterfall-profiler-content {
    /*height: calc(100% - 58px);*/
    height: calc(100% - 92px);
    position: relative;
    z-index: 900;
    overflow: auto;
}
.waterfall-profiler-section-wrapper .waterfall-profiler-hidden {
    display: none !important;
}
.waterfall-profiler-content .waterfall-profiler-item.waterfall-profiler-visible:nth-child(even) {
    background: rgba(200, 200, 200, .15);
}
.waterfall-profiler-content > .waterfall-profiler-item:hover,
.waterfall-profiler-content > .waterfall-profiler-item.waterfall-profiler-active {
    background: rgba(200, 200, 200, .35);
}
.waterfall-profiler-content > .waterfall-profiler-item:hover .waterfall-profiler-item.waterfall-profiler-row {
    border-left-color: rgba(0,0,0,.1);
}
.waterfall-profiler-severity-none,
.waterfall-profiler-waterfall-bar.waterfall-profiler-severity-none {
    background-color: #379956;
    color: #fff;
}

.waterfall-profiler-severity-info,
.waterfall-profiler-waterfall-bar.waterfall-profiler-severity-info {
    background-color: #60beae;
    color: #fff;
}
.waterfall-profiler-severity-warning,
.waterfall-profiler-waterfall-bar.waterfall-profiler-severity-warning {
    background-color: #f4b575;
    color: #fff;
}
.waterfall-profiler-severity-critical,
.waterfall-profiler-waterfall-bar.waterfall-profiler-severity-critical {
    background-color: #f5757b;
    color: #fff;
}
.waterfall-profiler-severity-notice,
.waterfall-profiler-waterfall-bar.waterfall-profiler-severity-notice {
    background-color: #2e77ae;
    color: #fff;
}
.waterfall-profiler-header,
.waterfall-profiler-resize {
    user-select: none;
    -moz-user-select: none;
}
.waterfall-profiler-header.waterfall-profiler-search {
    padding: .4em 1em;
}
.waterfall-profiler-header.waterfall-profiler-search input {
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

.waterfall-profiler-item[data-target],
.waterfall-profiler-item[data-command]{
    cursor: pointer;
    user-select: none;
    -moz-user-select: none;
}
.waterfall-profiler-item[data-command]:hover,
.waterfall-profiler-item[data-target]:hover,
.waterfall-profiler-item[data-target].waterfall-profiler-active {
    background: rgba(200, 200, 200, .35);
}
.waterfall-profiler-section-wrapper[data-waterfall-profiler-status=maximized] .waterfall-profiler-row-square[data-command=maximize],
.waterfall-profiler-section-wrapper[data-waterfall-profiler-status=opened] .waterfall-profiler-row-square[data-command=minimize],
.waterfall-profiler-section-wrapper .waterfall-profiler-row-square[data-command=open],
.waterfall-profiler-section-wrapper[data-waterfall-profiler-status=closed] .waterfall-profiler-row-square[data-command=close],
.waterfall-profiler-section-wrapper[data-waterfall-profiler-status=closed] .waterfall-profiler-row-square[data-command=minimize] {
    display: none;
}
.waterfall-profiler-section-wrapper[data-waterfall-profiler-status=closed] .waterfall-profiler-row-square[data-command=open] {
    display: flex;
}
.waterfall-profiler-section-wrapper[data-waterfall-profiler-status=closed] .waterfall-profiler-content-section {
    visibility: hidden;
    opacity: 0;
    transform: translateY(100%);
}
.waterfall-profiler-section-wrapper[data-waterfall-profiler-status=closed] {
    height: 30px;
}
.waterfall-profiler-section-wrapper[data-waterfall-profiler-status=opened] {
    /*max-height: 300px;*/
    height: 250px;
    min-height: 30px;
}
.waterfall-profiler-section-wrapper[data-waterfall-profiler-status=maximized] {
    height: 100vh;
    max-height: 100vh;
}
.waterfall-profiler-section-wrapper .waterfall-profiler-no-padding.waterfall-profiler-selector-header > div {
    line-height: 28px;
    padding: 0 .6em;
}
.waterfall-profiler-icon {
    width: 20px;
    display: flex;
    align-items: stretch;
    justify-content: space-evenly;
    flex-direction: row;
    padding: 0 2px;
}
.waterfall-profiler-icon svg {
    width: auto;
}
.waterfall-profiler-info {
    padding: 4px 5px 5px;
    margin: .3em;
    border-radius: 3px;
    background: rgba(0,0,0, .1);
    color: var(--waterfall-toolbar-color, #555);
}
.waterfall-profiler-content.waterfall-profiler-empty {
    height: 100%;
    letter-spacing: 1px;
    font-weight: bold;
}

.waterfall-profiler-content.waterfall-profiler-empty .waterfall-profiler-item {
    text-align: center;
    position: absolute;
    width: 100%;
    top: 50%;
    margin-top: -2em;
    border-width: 0;
}
.waterfall-profiler-content.waterfall-profiler-empty .waterfall-profiler-item:hover {
    background-color: transparent;
}
.waterfall-profiler-header-section .waterfall-profiler-selector-header {
    width: 100%;
    overflow-x: auto;
    overflow-y: hidden;
    touch-action: pan-x;
    scrollbar-width: none;
}
.waterfall-profiler-header-section .waterfall-profiler-selector-header::-webkit-scrollbar {
    display:none;
}
.waterfall-profiler-header-section .waterfall-profiler-column.waterfall-profiler-header .waterfall-profiler-selector-header > .waterfall-profiler-row {
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
