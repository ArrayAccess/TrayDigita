<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark\Interfaces;

interface SeverityInterface
{
    public const CRITICAL = 1;
    public const WARNING = 3;
    public const NOTICE = 5;
    public const INFO = 6;
    public const NONE = 0;

    /**
     * @return int
     */
    public function getSeverity() : int;

    /**
     * @return bool
     */
    public function isCritical() : bool;

    /**
     * @return bool
     */
    public function isWarning() : bool;

    /**
     * @return bool
     */
    public function isNotice() : bool;

    /**
     * @return bool
     */
    public function isInfo() : bool;
}
