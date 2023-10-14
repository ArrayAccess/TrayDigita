<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Util\Generator\Menu;

use ArrayAccess\TrayDigita\Auth\Roles\Interfaces\RoleInterface;
use ArrayAccess\TrayDigita\Container\Interfaces\ContainerIndicateInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerAllocatorInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Http\ServerRequest;
use ArrayAccess\TrayDigita\Http\Uri;
use ArrayAccess\TrayDigita\Traits\Container\ContainerAllocatorTrait;
use ArrayAccess\TrayDigita\Traits\Manager\ManagerAllocatorTrait;
use ArrayAccess\TrayDigita\Util\Filter\Consolidation;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use ArrayAccess\TrayDigita\Util\Filter\DataNormalizer;
use ArrayAccess\TrayDigita\Util\Filter\HtmlAttributes;
use ArrayAccess\TrayDigita\Util\Generator\Menu\Abstracts\AbstractMenu;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriInterface;
use function is_array;
use function sprintf;
use function str_contains;
use function uasort;

class Menus implements ContainerIndicateInterface, ManagerAllocatorInterface
{
    use ContainerAllocatorTrait,
        ManagerAllocatorTrait;

    /**
     * @var array<string, AbstractMenu>
     */
    private array $menus = [];

    private static int $menusIncrement = 0;

    public function __construct(
        ContainerInterface $container,
        ?ManagerInterface $manager = null
    ) {
        $this->setContainer($container);
        $manager ??= ContainerHelper::use(ManagerInterface::class, $this->getContainer());
        if ($manager) {
            $this->setManager($manager);
        }
    }

    public function getContainer(): ContainerInterface
    {
        return $this->containerObject;
    }

    public function getMenus(): array
    {
        uasort(
            $this->menus,
            function (AbstractMenu $a, AbstractMenu $b) {
                $a = $a->getPriority();
                $b = $b->getPriority();
                return $a === $b ? 0 : ($a < $b ? -1 : 1);
            }
        );

        return $this->menus;
    }

    public function addMenu(AbstractMenu $menu) : bool
    {
        $currentMenu = $this->menus[$menu->getId()]??null;
        if ($currentMenu) {
            return false;
        }
        $this->menus[$menu->getId()] = $menu;
        return true;
    }

    public function replaceMenu(AbstractMenu $menu) : ?AbstractMenu
    {
        $previousMenu = $this->menus[$menu->getId()]??null;
        $this->menus[$menu->getId()] = $menu;
        return $previousMenu;
    }

    public function hasMenu(string $menuId) : bool
    {
        return isset($this->menus[$menuId]);
    }

    public function removeMenu(string $menuId): ?AbstractMenu
    {
        $menu = null;
        if (isset($this->menus[$menuId])) {
            $menu = $this->menus[$menuId];
            unset($this->menus[$menuId]);
        }
        return $menu;
    }

    /**
     * @param AbstractMenu $menu
     * @return string
     */
    public function createLinkTag(AbstractMenu $menu) : string
    {
        $uri = $menu->getLink();
        $linkText = $menu->getLinkText();
        if ($uri instanceof UriInterface) {
            $uri = (string) $uri;
        }
        if ($linkText === null && $uri === null) {
            return '';
        }
        $originalAttribute = [
            'class' => [
                'menu-link'
            ],
        ];
        $attributes = $this->getManager()?->dispatch(
            'menus.linkAttributes',
            $originalAttribute,
            $menu,
            $this
        )??$originalAttribute;
        $attributes = is_array($attributes)
            ? $attributes
            : $originalAttribute;
        $linkText = $linkText
            && str_contains($linkText, '<') // contain tags
            ? DataNormalizer::forceBalanceTags($linkText)
            : ($linkText??'');
        return sprintf(
            '<%1$s href="%2$s" %3$s>%4$s</%1$s>',
            'a',
            $uri,
            HtmlAttributes::buildAttributes($attributes),
            $linkText
        );
    }

    private function createMenuAttributeId(AbstractMenu $menu, int $depth = 0): string
    {
        $menuId = 'menu-';
        if (self::$menusIncrement > 1) {
            $menuId .= sprintf('inc-%d-', self::$menusIncrement -1);
        }
        $menuId .= DataNormalizer::normalizeHtmlClass($menu->getId());
        if ($depth > 0) {
            $menuId .= '-depth-' . $depth;
        }

        return $menuId;
    }

    protected function appendAttributeListRequest(
        array $attributes,
        ?ServerRequestInterface $request,
        AbstractMenu $menu,
        &$hasCurrent = null
    ) : array {
        unset($attributes['data-current']);
        $link = $menu->getLink();
        $attributes['class'] = DataNormalizer::splitStringToArray($attributes['class']??[])??[];
        $attributes['class'][] = 'menu-list';
        if (!$request || $link === null) {
            return $attributes;
        }
        if (!$link instanceof UriInterface) {
            $link = new Uri($link);
            if ($link->getHost() === '') {
                $link = $link->withHost($request->getUri()->getHost());
            }
        }
        $uri = $request->getUri();
        if ($link->getHost() === $uri->getHost()
            && trim($uri->getPath(), '/') === trim($link->getPath(), '/')
        ) {
            $attributes['class'][] = 'menu-list-current';
            $attributes['data-current'] = true;
            $hasCurrent = true;
        }

        return $attributes;
    }

    private function renderMenu(
        ?ServerRequestInterface $request,
        AbstractMenu $menu,
        string $tag,
        int $maxDepth,
        int $depth = 0,
        ?RoleInterface $role = null,
        &$hasCurrent = null
    ) : string {
        if (!$menu->permitted($role)
            || $depth < 0
            || $maxDepth < $depth
        ) {
            return '';
        }
        $subListTag = $tag === 'div' ? 'div' : 'li';

        $html = '';
        $countMenu = 0;
        foreach ($menu->getSubMenus() as $subMenu) {
            if (!$subMenu->permitted($role, $request)) {
                continue;
            }
            $countMenu++;
            $originalAttribute = [
                    'id' => $this->createMenuAttributeId($subMenu, $depth + 1),
                ] + $menu->getAttributes();
            $attributes = $this->getManager()?->dispatch(
                'menus.submenuAttributes',
                $originalAttribute,
                $subMenu,
                $menu,
                $depth,
                $this
            )??$originalAttribute;
            $containCurrent = false;
            $subMenuHtml = $this->renderMenu(
                $request,
                $menu,
                $tag,
                $maxDepth,
                $depth+1,
                $role,
                $containCurrent
            );

            $attributes = $this->appendAttributeListRequest($attributes, $request, $subMenu, $hasCurrent);
            if ($containCurrent || $subMenuHtml) {
                $attributes['class'][] = 'has-submenu';
                $attributes['data-has-submenu'] = true;
            }
            if ($containCurrent) {
                $hasCurrent = true;
                $attributes['data-has-current-submenu'] = true;
                $attributes['class'][] = 'has-current-submenu';
            }

            $html .= sprintf(
                '<%1$s %2$s>%3$s%4$s</%1$s>',
                $subListTag,
                HtmlAttributes::buildAttributes($attributes),
                $this->createLinkTag($subMenu),
                $subMenuHtml
            );
        }

        $parentAttributes = [
            'class' => ['submenu'],
            'data-depth' => $depth+1
        ];
        if ($html !== '') {
            $parentAttributes['class'][] = 'contain-menu';
            $parentAttributes['data-has-submenu'] = true;
            $parentAttributes['data-submenu-count'] = $countMenu;
        } else {
            $parentAttributes['class'][] = 'empty-menu';
        }
        return sprintf(
            '<%1$s %2$s>',
            $tag,
            HtmlAttributes::buildAttributes($parentAttributes)
        )
            . $html
            . sprintf('</%s>', $tag);
    }

    /**
     * @param ServerRequestInterface|null $request
     * @param RoleInterface|null $role
     * @param string $listTag
     * @param int $maxDepth
     * @param array $attributes
     * @return string
     */
    public function display(
        ?ServerRequestInterface $request = null,
        ?RoleInterface $role = null,
        string $listTag = 'ul',
        int $maxDepth = 0,
        array $attributes = [],
    ) : string {
        self::$menusIncrement++;
        if ($maxDepth < 0) {
            return '';
        }
        $request ??= ServerRequest::fromGlobals(
            ContainerHelper::use(
                ServerRequestFactoryInterface::class,
                $this->getContainer()
            ),
            ContainerHelper::use(
                StreamFactoryInterface::class,
                $this->getContainer()
            )
        );
        $tag = match ($listTag) {
            'div' => 'div',
            'ol' => 'ol',
            default => 'ul'
        };
        $subListTag = $tag === 'div' ? 'div' : 'li';

        // filter classes
        $attributes['class'] = DataNormalizer::splitStringToArray($attributes['class']??[])??[];
        $attributes['data-depth'] = 0;
        $attributes['class'][] = 'parent-menu';
        $parentAttributes = $attributes;
        $html = '';
        $countMenu = 0;
        foreach ($this->getMenus() as $menu) {
            if (!$menu->permitted($role, $request)) {
                continue;
            }
            $countMenu++;
            $originalAttribute = [
                'id' => $this->createMenuAttributeId($menu)
            ] + $menu->getAttributes();
            $attributes = $this->getManager()?->dispatch(
                'menus.menuAttributes',
                $originalAttribute,
                $menu,
                $this
            )??$originalAttribute;
            $hasCurrent = false;
            $subMenu = $this->renderMenu(
                $request,
                $menu,
                $tag,
                $maxDepth,
                0,
                $role,
                $hasCurrent
            );
            $attributes = $this->appendAttributeListRequest($attributes, $request, $menu, $hasCurrent);
            if ($hasCurrent || $subMenu) {
                $attributes['class'][] = 'has-submenu';
                $attributes['data-has-submenu'] = true;
            }
            if ($hasCurrent) {
                $attributes['class'][] = 'has-current-submenu';
                $attributes['data-current-submenu'] = true;
            }

            $html .= sprintf(
                '<%1$s %2$s>%3$s%4$s</%1$s>',
                $subListTag,
                HtmlAttributes::buildAttributes($attributes),
                $this->createLinkTag($menu),
                $subMenu
            );
        }

        if ($html !== '') {
            $parentAttributes['class'][] = 'contain-menu';
            $parentAttributes['data-has-submenu'] = true;
            $parentAttributes['data-submenu-count'] = $countMenu;
        } else {
            $parentAttributes['class'][] = 'empty-menu';
        }
        return
            sprintf('<%1$s %2$s>', $tag, HtmlAttributes::buildAttributes($parentAttributes))
            . $html
            . sprintf('</%s>', $tag);
    }

    public function __debugInfo(): ?array
    {
        return Consolidation::debugInfo(
            $this,
            excludeKeys: ['menus']
        );
    }
}
