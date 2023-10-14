<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Scheduler\Interfaces;

use ArrayAccess\TrayDigita\Scheduler\Runner;
use Serializable;
use Stringable;

interface MessageInterface extends Stringable, Serializable
{
    public function __construct(null|string|Stringable $message);

    public function getMessage() : string|Stringable;

    /**
     * @return int<Runner::STATUS_SUCCESS>
     * @return int<Runner::STATUS_FAILURE>
     * @return int<Runner::STATUS_SKIPPED>
     * @return int<Runner::STATUS_UNKNOWN>
     */
    public function getStatusCode() : int;
}
