<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark;

use ArrayAccess\TrayDigita\Benchmark\Interfaces\ProfilerInterface;
use ArrayAccess\TrayDigita\Benchmark\Interfaces\RecordInterface;
use ArrayAccess\TrayDigita\Kernel\Decorator;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;

/**
 * @uses Profiler::hasGroup()
 * @method static bool hasGroup(string $name)
 * @uses Profiler::group()
 * @method static \ArrayAccess\TrayDigita\Benchmark\Interfaces\GroupInterface group(string $id)
 * @uses Profiler::getGroups()
 * @method static array<string, \ArrayAccess\TrayDigita\Benchmark\Interfaces\GroupInterface> getGroups()
 * @mixin Profiler
 * @noinspection PhpFullyQualifiedNameUsageInspection
 */
final class Benchmark
{
    private static ?Benchmark $timer = null;

    private Profiler $profiler;

    private function __construct()
    {
        self::$timer = $this;
        $profiler = ContainerHelper::use(ProfilerInterface::class);
        $profiler ??= Decorator::benchmark() ?? new Profiler();
        $this->profiler = $profiler;
    }

    /**
     * @param ProfilerInterface $collector
     *
     * @return ProfilerInterface the old collector
     */
    public static function setProfiler(ProfilerInterface $collector): ProfilerInterface
    {
        $oldCollector = self::profiler();
        self::internalBenchmark()->profiler = $collector;
        return $oldCollector;
    }

    private static function internalBenchmark() : Benchmark
    {
        self::$timer ??= new self();
        return self::$timer;
    }

    public static function profiler() : ProfilerInterface
    {
        return self::internalBenchmark()->profiler;
    }

    public static function start(
        string $name,
        string $group = ProfilerInterface::DEFAULT_NAME,
        array $context = []
    ): RecordInterface {
        return self::profiler()->start($name, $group, $context);
    }

    public static function stop(
        string $name,
        string $group = ProfilerInterface::DEFAULT_NAME,
        array $context = []
    ) : ?RecordInterface {
        return self::profiler()->stop($name, $group, $context);
    }

    public static function __callStatic(string $name, array $arguments)
    {
        return self::profiler()->$name(...$arguments);
    }

    public function __call(string $name, array $arguments)
    {
        return self::profiler()->$name(...$arguments);
    }
}
