<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Scheduler\Interfaces;

use Serializable;
use Stringable;

interface MessageInterface extends Stringable, Serializable
{
    public function __construct(null|string|Stringable $message);

    public function getMessage() : string|Stringable;

    /**
     * @return int<\ArrayAccess\TrayDigita\Scheduler\Runner::STATUS_SUCCESS>
     * @return int<\ArrayAccess\TrayDigita\Scheduler\Runner::STATUS_FAILURE>
     * @return int<\ArrayAccess\TrayDigita\Scheduler\Runner::STATUS_SKIPPED>
     * @return int<\ArrayAccess\TrayDigita\Scheduler\Runner::STATUS_UNKNOWN>
     */
    public function getStatusCode() : int;
}
