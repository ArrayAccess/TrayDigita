<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Util\Generator\Menu\Abstracts;

use ArrayAccess\TrayDigita\Auth\Roles\Interfaces\RoleInterface;
use ArrayAccess\TrayDigita\Util\Generator\Menu\Menu;
use ArrayAccess\TrayDigita\Util\Generator\Menu\Menus;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use function array_key_exists;
use function is_string;
use function uasort;

abstract class AbstractMenu
{
    protected Menus $menus;

    private static int $menuIncrement = 0;

    protected string $id = '';

    protected int $priority = 10;

    protected null|string|UriInterface $link = null;

    protected ?string $linkText = null;

    /**
     * @var array
     */
    protected array $attributes = [];

    /**
     * @var array<string, AbstractMenu>
     */
    private array $subMenus = [];

    public function __construct(Menus $menus)
    {
        $this->menus = $menus;
        // reset
        $this->setAttributes($this->attributes);
    }

    public function getMenus(): Menus
    {
        return $this->menus;
    }

    public function permitted(
        ?RoleInterface $role = null,
        ?ServerRequestInterface $request = null
    ) : bool {
        return true;
    }

    public function getLink(): UriInterface|string|null
    {
        return $this->link;
    }

    public function setLink(UriInterface|string|null $link): static
    {
        $this->link = $link;
        return $this;
    }

    public function getLinkText(): ?string
    {
        return $this->linkText;
    }

    public function setLinkText(?string $linkText): static
    {
        $this->linkText = $linkText;
        return $this;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function setAttributes(array $attributes): void
    {
        $this->attributes = [];
        foreach ($attributes as $key => $item) {
            if (is_string($key)) {
                $this->setAttribute($key, $item);
            }
        }
    }

    public function setAttribute(string $attributeName, $attributeValue): static
    {
        $this->attributes[$attributeName] = $attributeValue;
        return $this;
    }

    public function getAttribute(string $attributeName) : mixed
    {
        return $this->attributes[$attributeName]??null;
    }

    public function hasAttribute(string $attributeName) : bool
    {
        return array_key_exists($attributeName, $this->getAttributes());
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        if ($this->id === '') {
            $this->id = 'menu-' . ++self::$menuIncrement;
        }
        return $this->id;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function addSubmenu(AbstractMenu $menu) : static
    {
        $this->subMenus[$menu->getId()] = $menu;
        return $this;
    }

    public function getSubMenus(): array
    {
        uasort(
            $this->subMenus,
            function (AbstractMenu $a, AbstractMenu $b) {
                $a = $a->getPriority();
                $b = $b->getPriority();
                return $a === $b ? 0 : ($a < $b ? -1 : 1);
            }
        );

        return $this->subMenus;
    }

    public function hasSubmenu(string $menuId) : bool
    {
        return isset($this->subMenus[$menuId]);
    }

    public function removeSubMenu(string $menuId): ?AbstractMenu
    {
        $menu = null;
        if (isset($this->subMenus[$menuId])) {
            $menu = $this->subMenus[$menuId];
            unset($this->subMenus[$menuId]);
        }

        return $menu;
    }

    /**
     * @param Menus $menus
     * @param string $id
     * @param array $attributes
     * @param int $priority
     * @param string|UriInterface|null $link
     * @param string|null $linkText
     * @param callable|null $callablePermission
     * @return AbstractMenu
     */
    public static function create(
        Menus $menus,
        string $id,
        array $attributes = [],
        int $priority = 10,
        null|string|UriInterface $link = null,
        ?string $linkText = null,
        ?callable $callablePermission = null
    ): AbstractMenu {
        return new Menu(
            $menus,
            $id,
            $attributes,
            $priority,
            $link,
            $linkText,
            $callablePermission
        );
    }
}
