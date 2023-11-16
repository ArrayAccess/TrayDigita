<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Traits\Service;

use ArrayAccess\TrayDigita\Exceptions\Runtime\MaximumCallstackExceeded;
use function debug_backtrace;
use function explode;
use function sprintf;
use const DEBUG_BACKTRACE_IGNORE_ARGS;

trait CallStackTraceTrait
{
    public const MAX_CALLSTACK = 256;

    protected array $callStackIncrement = [];

    private function getInternalCallStackName() : ?string
    {
        $callstackDetail = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)[2]??[];
        $className = $callstackDetail['class']??null;
        $function = $callstackDetail['function']??null;
        $line = $callstackDetail['line'];
        if (!$className || !$function) {
            return null;
        }
        return sprintf('%s::%s/%d', $className, $function, $line);
    }

    protected function assertCallstack() : void
    {
        $callStackName = $this->getInternalCallStackName();
        if (!$callStackName) {
            return;
        }
        $this->callStackIncrement[$callStackName] ??= 0;
        if (++$this->callStackIncrement[$callStackName] >= self::MAX_CALLSTACK) {
            $named = explode('/', $callStackName, 2);
            throw new MaximumCallstackExceeded(
                sprintf(
                    'Maximum call stack exceeded on: %s',
                    $named[0]
                )
            );
        }
    }

    protected function resetCallstack(): void
    {
        $callStackName = $this->getInternalCallStackName();
        if (!$callStackName) {
            return;
        }
        unset($this->callStackIncrement[$callStackName]);
    }

    protected function clearCallstack() : void
    {
        $this->callStackIncrement = [];
    }
}
