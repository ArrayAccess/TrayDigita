<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\View\Twig;

use ArrayAccess\TrayDigita\Util\Filter\Consolidation;
use Twig\Environment;

class TwigEnvironment extends Environment
{
    public function __debugInfo(): ?array
    {
        return Consolidation::debugInfo($this);
    }
}
