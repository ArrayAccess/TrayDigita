<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark\Interfaces;

interface SeverityInterface
{
    const CRITICAL = 1;
    const WARNING = 3;
    const NOTICE = 5;
    const INFO = 6;
    const NONE = 0;

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
