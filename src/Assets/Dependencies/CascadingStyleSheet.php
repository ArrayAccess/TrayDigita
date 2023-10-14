<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Assets\Dependencies;

use ArrayAccess\TrayDigita\Assets\Abstracts\AbstractUriDependency;
use ArrayAccess\TrayDigita\Assets\Traits\CascadingStyleSheetTrait;

class CascadingStyleSheet extends AbstractUriDependency
{
    use CascadingStyleSheetTrait;
}
