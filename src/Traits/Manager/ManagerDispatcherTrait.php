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

    protected function getManagerFromContainer(): ?ManagerInterface
    {
        return ContainerHelper::getNull(ManagerInterface::class, $this->getContainer());
    }

    protected function getInternalMethodManager() : ?ManagerInterface
    {
        if ($this->internalMethodManager !== null) {
            $method = $this->internalMethodManager?:null;
            return $method ? $this->$method() : null;
        }

        if ($this instanceof ManagerIndicateInterface) {
            $this->internalMethodManager = 'getManager';
            return $this->getManager();
        }

        $manager = $this->getManagerFromContainer();
        if ($manager) {
            $this->internalMethodManager = 'getManagerFromContainer';
            return $manager;
        }

        $ref = new ReflectionObject($this);
        if (!$ref->hasMethod('getManager')) {
            return null;
        }
        $method = $ref->getMethod('getManager');
        $returnType = $method->getNumberOfRequiredParameters() === 0
            ? $method->getReturnType()
            : null;
        if (!$returnType) {
            return null;
        }
        $types = [];
        if ($returnType instanceof ReflectionNamedType) {
            $types = [$returnType];
        } elseif ($returnType instanceof ReflectionUnionType) {
            $types = $returnType->getTypes();
        }
        foreach ($types as $type) {
            if ($type->isBuiltin()) {
                continue;
            }
            if ($type->getName() === ManagerInterface::class
                || is_subclass_of($type->getName(), ManagerInterface::class)
            ) {
                $this->internalMethodManager = $type->getName();
                return $this->{$this->internalMethodManager}();
            }
        }
        return null;
    }

    protected function dispatchEvent(
        string $eventName,
        ...$arguments
    ) {
        $manager = $this->getInternalMethodManager();
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
}
