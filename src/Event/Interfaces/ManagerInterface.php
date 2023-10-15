<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Event\Interfaces;

use Countable;
use SensitiveParameter;

interface ManagerInterface extends Countable
{

    public function setDispatchListener(?EventDispatchListenerInterface $dispatchListener);

    public function getDispatchListener(): ?EventDispatchListenerInterface;

    /**
     * Attach the event
     *
     * @param string $eventName
     * @param $eventCallback
     * @param int $priority
     * @return string
     */
    public function attach(string $eventName, $eventCallback, int $priority = 10) : string;

    public function has(string $eventName, $eventCallback = null) : bool;

    /**
     * Detach event
     *
     * @param string $eventName
     * @param $eventCallback
     * @param int|null $priority
     * @return int
     */
    public function detach(
        string $eventName,
        $eventCallback = null,
        ?int $priority = null
    ): int;

    /**
     * Detach by event name & id
     *
     * @param string $eventName
     * @param string $id
     * @param int|null $priority
     * @return int
     */
    public function detachByEventNameId(
        string $eventName,
        string $id,
        ?int $priority = null
    ): int;

    /**
     * Detach all events by event name
     *
     * @param string ...$eventNames
     * @return int
     */
    public function detachAll(string ...$eventNames): int;

    /**
     * Check whether event is dispatched
     *
     * @param string $eventName
     * @param $eventCallback
     * @param int|null $priority
     * @return int total dispatched
     */
    public function dispatched(
        string $eventName,
        $eventCallback = null,
        ?int $priority = null
    ): int;

    /**
     * Check whether event is on
     *
     * @param string $eventName
     * @param $eventCallback
     * @return bool
     */
    public function insideOf(string $eventName, $eventCallback = null): bool;

    /**
     * Get current running event
     *
     * @return callable|array|string|null
     */
    public function getCurrentEvent(): callable|array|string|null;

    /**
     * @return ?string
     */
    public function getCurrentEventId(): ?string;

    public function getCurrentEventName(): ?string;

    /**
     * Get current parameters
     *
     * @return ?array
     */
    public function getCurrentParams(): ?array;

    /**
     * Dispatch the events
     *
     * @param string $eventName
     * @param mixed $param result to dispatch
     * @param ...$arguments
     *
     * @return mixed
     * @noinspection PhpMissingReturnTypeInspection
     * @noinspection PhpMissingParamTypeInspection
     */
    public function dispatch(
        string $eventName,
        #[SensitiveParameter]
        $param = null,
        #[SensitiveParameter]
        ...$arguments
    );

    public function getDispatcherTrace(string $eventName) : ?array;

    /**
     * Count the events
     *
     * @return int
     */
    public function count(): int;
}
