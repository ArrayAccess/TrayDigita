<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Event\Interfaces;

interface EventDispatchListenerInterface
{
    public function onBeforeDispatch(
        ManagerInterface $manager,
        string $eventName,
        ?int $priority,
        ?string $id,
        $originalParam,
        $param,
        ...$arguments
    );

    public function onFinishDispatch(
        ManagerInterface $manager,
        string $eventName,
        ?int $priority,
        ?string $id,
        $originalParam,
        $param,
        ...$arguments
    );
}
