<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark;

use ArrayAccess\TrayDigita\Benchmark\Interfaces\GroupInterface;
use ArrayAccess\TrayDigita\Benchmark\Interfaces\RecordInterface;
use ArrayAccess\TrayDigita\Benchmark\Traits\DurationTimeTrait;
use ArrayAccess\TrayDigita\Benchmark\Traits\MemoryTrait;
use ArrayAccess\TrayDigita\Benchmark\Traits\SeverityTrait;

class Record implements RecordInterface
{
    use SeverityTrait,
        MemoryTrait,
        DurationTimeTrait;

    private bool $stopped = false;

    public function __construct(
        protected GroupInterface $group,
        protected string $name,
        protected array $metadata = []
    ) {
        $this->startTime = $this->convertMicrotime();
        $this->startMemory = memory_get_usage();
        $this->startRealMemory = memory_get_usage(true);
    }

    /**
     * @return Group
     */
    public function getGroup(): GroupInterface
    {
        return $this->group;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }


    public function convertMicrotime(?float $microtime = null): float
    {
        return $this->group->convertMicrotime($microtime);
    }

    /**
     * @return array
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getMetadataRecord(string $key)
    {
        return $this->metadata[$key]??null;
    }

    public function removeMetadataRecord(string $key): void
    {
        unset($this->metadata[$key]);
    }

    public function addMetadataRecord(string $key, mixed $value) : bool
    {
        if ($this->hasMetadataRecord($key)) {
            return false;
        }

        $this->setMetadataRecord($key, $value);
        return true;
    }

    public function setMetadataRecord(string $key, mixed $value): void
    {
        // skip add parameter
        if (!$this->getGroup()->getProfiler()->isEnable()) {
            return;
        }
        $this->metadata[$key] = $value;
    }

    public function hasMetadataRecord(string $key): bool
    {
        return array_key_exists($key, $this->metadata);
    }

    /**
     * @return bool
     */
    public function isStopped(): bool
    {
        return $this->stopped;
    }

    public function stop(array $metadata = []): static
    {
        if ($this->stopped) {
            return $this;
        }

        $this->stopped = true;
        $this->endTime       = $this->convertMicrotime();
        $this->duration      = $this->measureTime();
        // add parameter if enabled
        if ($this->group->getProfiler()->isEnable()) {
            $this->metadata = array_merge($this->getMetadata(), $metadata);
        }

        $this->endMemory     = memory_get_usage();
        $this->endRealMemory = memory_get_usage(true);
        $this->usedMemory    = $this->measureMemory(
            $this->getStartMemory(),
            false,
            $this->endMemory
        );
        $this->usedRealMemory  = $this->measureMemory(
            $this->getStartRealMemory(),
            true,
            $this->endRealMemory
        );

        $this->group->stop($this);
        return $this;
    }

    /**
     * @return array{
     *     group:string,
     *     name: string,
     *     stopped: bool,
     *     timing: array{
     *         start: float,
     *         end: float,
     *         duration: float
     *     },
     *     memory: array{
     *         normal:array{
     *             start: int,
     *             end: int,
     *             used: int,
     *         },
     *         real:array{
     *              start: int,
     *              end: int,
     *              used: int,
     *         }
     *     },
     *     metadata: array,
     * }
     */
    public function jsonSerialize(): array
    {
        $startTime = $this->getStartTime();
        $duration = $this->getDuration();
        $startMemory = $this->getStartMemory();
        $usedMemory = $this->getUsedMemory();
        $startRealMemory=  $this->getStartRealMemory();
        $usedRealMemory = $this->getUsedRealMemory();
        return [
            'group' => $this->group->getName(),
            'name' => $this->getName(),
            'stopped' => $this->isStopped(),
            'timing' => [
                'start' => $startTime,
                'end' => max($duration - $startTime, 0),
                'duration' => $duration
            ],
            'memory' => [
                'normal' => [
                    'start' => $startMemory,
                    'end' => max($usedMemory - $startMemory, 0),
                    'used' => $usedMemory
                ],
                'real' => [
                    'start' => $startRealMemory,
                    'end' => max($usedRealMemory - $startRealMemory, 0),
                    'used' => $usedRealMemory
                ],
            ],
            'metadata' => $this->getMetadata()
        ];
    }
}
