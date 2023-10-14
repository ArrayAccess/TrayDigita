<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Assets\Dependencies;

use ArrayAccess\TrayDigita\Assets\Abstracts\AbstractUriDependency;
use ArrayAccess\TrayDigita\Assets\Traits\JavascriptTrait;

class Javascript extends AbstractUriDependency
{
    use JavascriptTrait;

    protected array $attributes = [
        'type' => 'text/javascript'
    ];
}
