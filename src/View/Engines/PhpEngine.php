<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\View\Engines;

use Stringable;
use function ob_get_clean;
use function ob_start;

class PhpEngine extends AbstractEngine
{
    protected array $extensions = [
        'php',
        'phtml',
    ];

    /** @noinspection PhpUnusedParameterInspection */
    protected function internalInclude(string $path, array $parameters): string|Stringable
    {
        ob_start();
        (function ($_include_file, $parameters) {
            include $_include_file;
        })->call(
            $this,
            $path,
            $parameters + $this->getView()->getParameters()
        );
        return ob_get_clean();
    }
}
