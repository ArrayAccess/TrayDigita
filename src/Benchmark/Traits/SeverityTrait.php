<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark\Traits;

use ArrayAccess\TrayDigita\Benchmark\Interfaces\DurationInterface;
use ArrayAccess\TrayDigita\Benchmark\Interfaces\SeverityInterface;

trait SeverityTrait
{
    abstract public function getDuration() : float;

    public function getDefaultAsValueAsCritical() : int
    {
        return DurationInterface::NUMERIC_CRITICAL;
    }

    public function getDefaultAsValueAsWarning() : int
    {
        return DurationInterface::NUMERIC_WARNING;
    }

    public function getDefaultAsValueAsNotice() : int
    {
        return DurationInterface::NUMERIC_NOTICE;
    }

    public function getDefaultAsValueAsInfo() : int
    {
        return DurationInterface::NUMERIC_INFO;
    }

    /**
     * @return int
     */
    public function getSeverity() : int
    {
        $duration = round($this->getDuration());
        if ($duration >= $this->getDefaultAsValueAsCritical()) {
            return SeverityInterface::CRITICAL;
        }
        if ($duration >= $this->getDefaultAsValueAsWarning()) {
            return SeverityInterface::WARNING;
        }
        if ($duration >= $this->getDefaultAsValueAsNotice()) {
            return SeverityInterface::NOTICE;
        }
        if ($duration >= $this->getDefaultAsValueAsInfo()) {
            return SeverityInterface::INFO;
        }
        return SeverityInterface::NONE;
    }

    /**
     * @return bool
     */
    public function isCritical() : bool
    {
        return $this->getSeverity() === SeverityInterface::CRITICAL;
    }

    /**
     * @return bool
     */
    public function isWarning() : bool
    {
        return $this->getSeverity() === SeverityInterface::WARNING;
    }

    /**
     * @return bool
     */
    public function isNotice() : bool
    {
        return $this->getSeverity() === SeverityInterface::NOTICE;
    }

    /**
     * @return bool
     */
    public function isInfo() : bool
    {
        return $this->getSeverity() === SeverityInterface::INFO;
    }
}
