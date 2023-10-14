<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Scheduler\Messages;

use ArrayAccess\TrayDigita\Scheduler\Interfaces\MessageInterface;
use ArrayAccess\TrayDigita\Scheduler\Messages\Traits\MessageTrait;
use ArrayAccess\TrayDigita\Scheduler\Runner;

class Stopped implements MessageInterface
{
    use MessageTrait;

    /**
     * @return int
     */
    public function getStatusCode(): int
    {
        return Runner::STATUS_STOPPED;
    }
}
