<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Traits\Manager;

use ArrayAccess\TrayDigita\Event\Interfaces\ManagerIndicateInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use ReflectionNamedType;
use ReflectionObject;
use ReflectionUnionType;
use function debug_backtrace;
use function is_subclass_of;
use function reset;
use function ucfirst;
use const DEBUG_BACKTRACE_IGNORE_ARGS;

trait ManagerDispatcherTrait
{
    private ?string $internalMethodManager = null;

    protected function getPrefixNameEventIdentity(): ?string
    {
        return null;
    }

    abstract public function getManager() : ?ManagerInterface;

    protected function dispatchEvent(
        string $eventName,
        ...$arguments
    ) {
        $manager = $this->getManager();
        if (!$manager) {
            return count($arguments) > 0
                ? reset($arguments)
                : null;
        }

        $identity = $this->getPrefixNameEventIdentity();
        $identity = $identity ? "$identity.$eventName" : $eventName;

        // @dispatch($identity)
        return $manager->dispatch($identity, ...$arguments);
    }

    private function doDispatchMethod(?string $type, ...$arguments)
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)[2]??[];
        $clasName = $trace['class']??null;
        $fn = $trace['function']??null;
        $name = ! $clasName || !$fn || !$this instanceof $clasName ? null : $fn;
        if (!$name) {
            return count($arguments) > 0
                ? reset($arguments)
                : $this;
        }

        $arguments = count($arguments) === 0 ? [] : $arguments;
        // append
        $arguments[] = $this;
        // $lastname = $name;
        $name = $type ? ucfirst($name) : $name;
        return $this->dispatchEvent(
            $type.$name,
            ...$arguments
        );
    }

    protected function dispatchCurrent(...$arguments)
    {
        return $this->doDispatchMethod(null, ...$arguments);
    }

    protected function dispatchBefore(...$arguments)
    {
        return $this->doDispatchMethod('before', ...$arguments);
    }

    protected function dispatchAfter(...$arguments)
    {
        return $this->doDispatchMethod('after', ...$arguments);
    }

    protected function dispatchWrap(callable $callback, ...$arguments)
    {
        try {
            $this->doDispatchMethod('before', ...$arguments);
            $arguments[] = $callback();
            $this->doDispatchMethod(null, ...$arguments);
            return end($arguments);
        } finally {
            $this->doDispatchMethod('after', ...$arguments);
        }
    }
}
