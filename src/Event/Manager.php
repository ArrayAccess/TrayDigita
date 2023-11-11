<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Event;

use ArrayAccess\TrayDigita\Event\Interfaces\EventDispatchListenerInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\InvalidCallbackException;
use ArrayAccess\TrayDigita\Exceptions\Runtime\MaximumCallstackExceeded;
use ArrayAccess\TrayDigita\Util\Filter\Consolidation;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use SensitiveParameter;
use function array_map;
use function array_shift;
use function debug_backtrace;
use function end;
use function explode;
use function func_get_args;
use function is_array;
use function is_object;
use function is_string;
use function ksort;
use function spl_object_hash;
use function sprintf;
use function str_contains;
use const DEBUG_BACKTRACE_IGNORE_ARGS;

class Manager implements ManagerInterface
{
    const MAX_LOOP = 255;

    protected array $events = [];

    /**
     * @var array<string, array<int, array<string, int>>>
     */
    private array $eventOnce = [];

    protected array $currents = [];

    /**
     * @var array{string: array{int:array{string:int}}}
     */
    protected array $records = [];

    /**
     * @var ?callable|array|string
     */
    protected $currentEvent = null;

    protected ?string $currentEventId = null;

    protected ?string $currentEventName = null;

    protected ?array $currentParams = null;

    protected array $callerTraces = [];

    private int $calledDispatch = 0;

    private int $currentIncrement = 0;

    /**
     * @var ?EventDispatchListenerInterface
     */
    protected ?EventDispatchListenerInterface $dispatchListener = null;

    public function __construct(?EventDispatchListenerInterface $dispatchListener = null)
    {
        $this->setDispatchListener($dispatchListener);
    }

    public function setDispatchListener(?EventDispatchListenerInterface $dispatchListener): void
    {
        $this->dispatchListener = $dispatchListener;
    }

    public function getDispatchListener(): ?EventDispatchListenerInterface
    {
        return $this->dispatchListener;
    }

    /**
     * @param $eventCallback
     * @return ?array{0:string, 1: array{0:object|string, string}}
     */
    public static function generateCallableId($eventCallback): array|null
    {
        if (!$eventCallback) {
            return null;
        }

        if (is_string($eventCallback)) {
            try {
                $eventCallback = (new ReflectionFunction($eventCallback))->getName();
                return [
                    $eventCallback,
                    $eventCallback,
                ];
            } catch (ReflectionException) {
                return null;
            }
        }

        if (is_object($eventCallback)) {
            return [
                spl_object_hash($eventCallback),
                $eventCallback
            ];
        }

        if (!is_array($eventCallback)) {
            return null;
        }

        $obj = array_shift($eventCallback);
        $method = array_shift($eventCallback);
        if ($method !== false && !is_string($method)) {
            return null;
        }

        $visibility = null;
        if ($method) {
            try {
                $ref = (new ReflectionMethod($obj, $method));
                if (is_string($obj) && (!$ref->isPublic() || !$ref->isStatic())) {
                    return null;
                }
                $method = $ref->getName();
                if (!$ref->isPublic()) {
                    $visibility = $ref->isPrivate() ? 'protected' : 'private';
                }
            } catch (ReflectionException) {
                return null;
            }
        }

        try {
            $id = is_object($obj) ? spl_object_hash($obj) : (new ReflectionClass($obj))->getName();
        } catch (ReflectionException) {
            $id = $obj;
        }

        if (!empty($method)) {
            $id .= "::$method";
            if ($visibility) {
                $method .= "::$visibility";
            }
            return [
                $id,
                [
                    is_object($obj) ? $obj : $id,
                    $method
                ]
            ];
        }

        return [
            $id,
            is_string($obj) ? $id : $obj
        ];
    }

    public function attach(
        string $eventName,
        $eventCallback,
        int $priority = 10
    ): string {
        $callable = $this->generateCallableId($eventCallback);
        if ($callable === null) {
            throw new InvalidCallbackException(
                'Argument 2 must is not valid callback.'
            );
        }

        $id = array_shift($callable);
        $eventCallback = array_shift($callable);
        $this->events[$eventName][$priority][$id][] = $eventCallback;
        return $id;
    }

    public function attachOnce(string $eventName, $eventCallback, int $priority = 10): string
    {
        $id = $this->attach(...func_get_args());
        $this->eventOnce[$eventName][$priority][$id] ??= 0;
        $this->eventOnce[$eventName][$priority][$id]++;
        return $id;
    }

    public function has(
        string $eventName,
        $eventCallback = null
    ): bool {
        if (!isset($this->events[$eventName])) {
            return false;
        }
        if ($eventCallback === null) {
            return true;
        }
        $id = null;
        if ($eventCallback) {
            $callable = $this->generateCallableId($eventCallback);
            if ($callable === null) {
                return false;
            }
            $id = array_shift($callable);
        }
        foreach ($this->events[$eventName] as $eventCallbacks) {
            if (isset($eventCallbacks[$id])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function detach(
        string $eventName,
        $eventCallback = null,
        ?int $priority = null
    ): int {
        if (!isset($this->events[$eventName])) {
            return 0;
        }
        $deleted = 0;
        $id = null;
        if ($eventCallback) {
            $callable = $this->generateCallableId($eventCallback);
            if ($callable === null) {
                return 0;
            }
            $id = array_shift($callable);
        }

        foreach ($this->events[$eventName] as $priorityId => $eventCallback) {
            if ($priority !== null && $priorityId !== $priority) {
                continue;
            }
            if ($id === null) {
                foreach ($eventCallback as $item) {
                    $deleted += count($item);
                }
                continue;
            }
            if (!isset($eventCallback[$id])) {
                continue;
            }
            $deleted += count($eventCallback[$id]);
        }

        return $deleted;
    }

    /**
     * @inheritdoc
     */
    public function detachByEventNameId(
        string $eventName,
        string $id,
        ?int $priority = null
    ): int {
        if (!isset($this->events[$eventName])) {
            return 0;
        }
        $deleted = 0;
        foreach ($this->events[$eventName] as $priorityId => $eventCallback) {
            if ($priority !== null && $priorityId !== $priority) {
                continue;
            }
            if (!isset($eventCallback[$id])) {
                continue;
            }
            $deleted += count($eventCallback[$id]);
        }

        return $deleted;
    }

    /**
     * @inheritdoc
     */
    public function detachAll(string ...$eventNames): int
    {
        $total = 0;
        if (count($eventNames) === 0) {
            array_map(static function ($arr) use (&$total) {
                array_map(static function ($arr) use (&$total) {
                    $total += count($arr);
                }, $arr);
            }, $this->currents);
            $this->events = [];
            return $total;
        }
        foreach ($eventNames as $name) {
            $total += $this->detach($name);
        }
        return $total;
    }

    public function dispatched(
        string $eventName,
        $eventCallback = null,
        ?int $priority = null
    ): int {
        $dispatched = 0;
        if (!isset($this->records[$eventName])) {
            return $dispatched;
        }

        $id = null;
        if ($eventCallback) {
            $callable = $this->generateCallableId($eventCallback);
            if ($callable === null) {
                return 0;
            }
            $id = array_shift($callable);
        }
        if ($priority !== null) {
            if (!isset($this->records[$eventName][$priority])) {
                return $dispatched;
            }
            if ($id !== null) {
                return $this->records[$eventName][$priority][$id] ?? $dispatched;
            }
            foreach ($this->records[$eventName][$priority] as $record) {
                $dispatched += $record;
            }
            return $dispatched;
        }

        foreach ($this->records[$eventName] as $records) {
            if ($id !== null) {
                $dispatched += $records[$id] ?? 0;
                continue;
            }

            foreach ($records as $count) {
                $dispatched += $count;
            }
        }

        return $dispatched;
    }

    public function insideOf(string $eventName, $eventCallback = null): bool
    {
        if (!isset($this->currents[$eventName])) {
            return false;
        }

        if (!$eventCallback) {
            return true;
        }

        $callable = $this->generateCallableId($eventCallback);
        if ($callable === null) {
            return false;
        }
        $id = array_shift($callable);
        return isset($this->currents[$eventName][$id]);
    }

    public function getCurrentEvent(): callable|array|string|null
    {
        return $this->currentEvent;
    }

    public function getCurrentEventId(): ?string
    {
        return $this->currentEventId;
    }

    public function getCurrentEventName(): ?string
    {
        return $this->currentEventName;
    }

    /**
     * @return array|null
     */
    public function getCurrentParams(): ?array
    {
        return $this->currentParams;
    }

    /**
     * @param string $eventName
     * @param $param
     * @param ...$arguments
     *
     * @return mixed
     * @noinspection PhpMissingReturnTypeInspection
     */
    public function dispatch(
        string $eventName,
        #[SensitiveParameter]
        $param = null,
        #[SensitiveParameter]
        ...$arguments
    ) {
        $inc = ++$this->calledDispatch;
        $this->currentIncrement = $inc;
        $this->callerTraces[$eventName][$inc] = debug_backtrace(
            DEBUG_BACKTRACE_IGNORE_ARGS,
            1
        )[0] ?? null;

        try {
            if (!isset($this->events[$eventName])) {
                try {
                    $this->dispatchListener?->onBeforeDispatch(
                        $this,
                        $eventName,
                        null,
                        null,
                        $param,
                        $param,
                        ...$arguments
                    );
                    return $param;
                } finally {
                    $this->dispatchListener?->onFinishDispatch(
                        $this,
                        $eventName,
                        null,
                        null,
                        $param,
                        $param,
                        ...$arguments
                    );
                }
            }

            $this->currents[$eventName] ??= [];
            // sorting
            ksort($this->events[$eventName]);
            // make temporary to prevent remove
            $originalParam = $param;
            $events = $this->events[$eventName];
            foreach ($events as $priority => $callableList) {
                unset($events[$priority]);
                if (!isset($this->currents[$eventName][$priority])) {
                    $this->currents[$eventName][$priority] = [];
                }
                foreach ($callableList as $id => $eventCallback) {
                    unset($eventCallback[$id]);
                    if (!isset($this->records[$eventName][$id][$priority])) {
                        $this->records[$eventName][$id][$priority] = 0;
                    }

                    foreach ($eventCallback as $inc => $callable) {
                        // prevent loops to call dispatch same increment
                        if (!isset($this->currents[$eventName][$priority][$id][$inc])) {
                            $this->currents[$eventName][$priority][$id][$inc] = 0;
                        }
                        // detect looping
                        if (++$this->currents[$eventName][$priority][$id][$inc] >= self::MAX_LOOP) {
                            throw new MaximumCallstackExceeded(
                                sprintf(
                                    'Possible infinite loop detected on event : "%s"',
                                    $eventName
                                )
                            );
                        }

                        $this->records[$eventName][$id][$priority]++;
                        $this->currentParams = func_get_args();
                        $this->currentEvent = $eventCallback;
                        $this->currentEventId = $id;
                        $this->currentEventName = $eventName;

                        // @onBeforeDispatch
                        $this->dispatchListener?->onBeforeDispatch(
                            $this,
                            $eventName,
                            $priority,
                            $id,
                            $originalParam,
                            $param,
                            ...$arguments
                        );
                        try {
                            if (is_array($callable)
                                && is_object($callable[0])
                                && is_string($callable[1] ?? null)
                                && str_contains($callable[1], '::')
                            ) {
                                $obj = $callable[0];
                                $methods = explode('::', $callable[1], 2);
                                $method = array_shift($methods);
                                $visibility = array_shift($methods);
                                if ($visibility) {
                                    $param = (function ($method, $param, ...$arguments) {
                                        /**
                                         * @var object $this
                                         */
                                        return $this->$method($param, ...$arguments);
                                    })->call($obj, $method, $param, ...$arguments);
                                } else {
                                    $param = $obj->$method(
                                        $param,
                                        ...$arguments
                                    );
                                }
                            } else {
                                $param = $callable($param, ...$arguments);
                            }
                        } finally {
                            $this->currentParams = null;
                            $this->currentEvent = null;
                            $this->currentEventId = null;
                            $this->currentEventName = null;
                            // unset
                            unset($this->currents[$eventName][$priority][$id][$inc]);
                            if ($this->currents[$eventName][$priority][$id] === []) {
                                unset($this->currents[$eventName][$priority][$id]);
                            }
                            if (isset($this->eventOnce[$eventName][$priority][$id])) {
                                $this->eventOnce[$eventName][$priority][$id]--;
                                if ($this->eventOnce[$eventName][$priority][$id] <= 0) {
                                    unset($this->eventOnce[$eventName][$priority][$id]);
                                }
                                if ([] === $this->eventOnce[$eventName][$priority]) {
                                    unset($this->eventOnce[$eventName][$priority]);
                                }
                                if ([] === $this->eventOnce[$eventName]) {
                                    unset($this->eventOnce[$eventName]);
                                }
                            }

                            // @onDispatched
                            $this->dispatchListener?->onFinishDispatch(
                                $this,
                                $eventName,
                                $priority,
                                $id,
                                $originalParam,
                                $param,
                                ...$arguments
                            );
                        }
                    }

                    unset($this->currents[$eventName][$priority][$id]);
                }

                unset($this->currents[$eventName][$priority]);
            }

            if ($this->currents[$eventName] === []) {
                unset($this->currents[$eventName]);
            }
        } finally {
            $this->currentIncrement = $inc;
            unset($this->callerTraces[$eventName][$inc]);
            if ($this->callerTraces[$eventName] === []) {
                unset($this->callerTraces[$eventName]);
            }
        }

        return $param;
    }

    public function getDispatcherTrace(string $eventName): ?array
    {
        $trace = $this->callerTraces[$eventName]??[];
        return end($trace)?:null;
    }

    public function __destruct()
    {
        $this->callerTraces = [];
    }

    public function count(): int
    {
        $total = 0;
        array_map(static function ($arr) use (&$total) {
            array_map(static function ($arr) use (&$total) {
                $total += count($arr);
            }, $arr);
        }, $this->records);
        return $total;
    }

    public function __debugInfo(): ?array
    {
        return Consolidation::debugInfo(
            $this,
            ['currentParams']
        );
    }

    /** @noinspection PhpUnused */
    public function getCurrentIncrement(): int
    {
        return $this->currentIncrement;
    }
}
