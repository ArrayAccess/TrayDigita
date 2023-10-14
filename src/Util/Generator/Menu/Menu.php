<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Util\Generator\Menu;

use ArrayAccess\TrayDigita\Auth\Roles\Interfaces\RoleInterface;
use ArrayAccess\TrayDigita\Util\Generator\Menu\Abstracts\AbstractMenu;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use function call_user_func;
use function is_callable;

class Menu extends AbstractMenu
{
    private $callablePermission;

    public function __construct(
        Menus $menus,
        string $id,
        array $attributes = [],
        int $priority = 10,
        null|string|UriInterface $link = null,
        ?string $linkText = null,
        ?callable $callablePermission = null
    ) {
        $this->linkText = $linkText;
        $this->id = $id;
        $this->priority = $priority;
        $this->attributes = $attributes;
        $this->link = $link;
        $this->callablePermission = $callablePermission;
        parent::__construct($menus);
    }

    public function permitted(
        ?RoleInterface $role = null,
        ?ServerRequestInterface $request = null
    ) : bool {
        $res = is_callable($this->callablePermission)
            ? call_user_func($this->callablePermission, $role, $request, $this)
            : true;
        return $res === true;
    }
}
