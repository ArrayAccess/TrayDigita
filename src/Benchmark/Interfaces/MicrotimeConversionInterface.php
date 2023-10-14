<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Benchmark\Interfaces;

interface MicrotimeConversionInterface
{
    const EXPONENT = 1000;

    /**
     * @return float calculate and exponent to 1000 that means on milliseconds
     */
    public function convertMicrotime(?float $microtime = null): float;
}
