<?php
/** @noinspection PhpUnused */
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Assets;

use ArrayAccess\TrayDigita\Assets\Interfaces\AssetsCollectionInterface;
use function array_filter;
use function array_merge;
use function array_unique;
use function is_string;

final class AssetsJsCssQueue
{
    /**
     * @var array<string, array<string, array>>
     */
    protected array $queue = [];

    /**
     * @var array<string. array<string, bool>>
     */
    protected array $queuedRecord = [];

    /**
     * @var array<string, array<string, bool>>
     */
    protected array $rendered = [];

    /**
     * @var array<string, array<string, array<string>>>
     */
    protected array $extended = [];

    /**
     * @var ?string
     */
    protected ?string $header = null;

    /**
     * @var ?string
     */
    protected ?string $footer = null;

    /**
     * @var array<string>
     */
    protected array $lastStyle = [];

    /**
     * @var array<string>
     */
    protected array $lastScript = [];

    public function __construct(public readonly AssetsCollectionInterface $collection)
    {
    }

    /**
     * @return array<string, array<string, array>>
     */
    public function getQueue(): array
    {
        return $this->queue;
    }

    /**
     * @return array<string, true>
     */
    public function getRendered(): array
    {
        return $this->rendered;
    }

    /**
     * @return ?string
     */
    public function getHeader(): ?string
    {
        return $this->header;
    }

    /**
     * @return ?string
     */
    public function getFooter(): ?string
    {
        return $this->footer;
    }

    /**
     * @return array<string>
     */
    public function getLastStyle(): array
    {
        return $this->lastStyle;
    }

    /**
     * @return array<string>
     */
    public function getLastScript(): array
    {
        return $this->lastScript;
    }

    /**
     * @return AssetsCollectionInterface
     */
    public function getCollection(): AssetsCollectionInterface
    {
        return $this->collection;
    }

    /**
     * @return Css
     */
    public function getCSS(): Css
    {
        $collection = $this->collection->get('css');
        if ($collection instanceof Css) {
            return $collection;
        }
        if (!$collection) {
            $collection = new Css($this->collection);
            $this->collection->register($collection);
            return $collection;
        }

        $this->collection->deregister($collection);
        $collection = new Css($this->collection);
        $this->collection->register($collection);
        return $collection;
    }

    public function getJS(): Js
    {
        $collection = $this->collection->get('js');
        if ($collection instanceof Js) {
            return $collection;
        }
        if (!$collection) {
            $collection = new Js($this->collection);
            $this->collection->register($collection);
            return $collection;
        }

        $this->collection->deregister($collection);
        $collection = new Js($this->collection);
        $this->collection->register($collection);
        return $collection;
    }

    /**
     * Unregister / DeQueue js assets
     * @param string $name
     * @return void
     */
    public function deQueueJs(string $name): void
    {
        unset(
            $this->queue['js_header'][$name],
            $this->queue['js_footer'][$name],
            $this->queuedRecord['js'][$name]
        );
    }

    /**
     * DeQueue css assets
     *
     * @param string $name
     * @return void
     */
    public function deQueueCss(string $name): void
    {
        if (isset($this->queuedRecord['css'][$name])) {
            unset(
                $this->queuedRecord['css'][$name],
                $this->queue['css'][$name]
            );
        }
    }

    /**
     * @param string $jsName
     * @param array<string, string[]> $includeCss
     * @param array $includeJS
     * @return void
     */
    public function registerPackage(
        string $jsName,
        array $includeJS = [],
        array $includeCss = []
    ): void {
        $this->extended[$jsName] = [];
        foreach (['js' => $includeJS, 'css' => $includeCss] as $type => $item) {
            foreach ($item as $name => $inherit) {
                if (!is_string($name)) {
                    if (is_string($inherit)) {
                        $this->extended[$jsName][$type][$inherit] = [];
                    }
                    continue;
                }
                if (!is_array($inherit)) {
                    continue;
                }
                $inherit = array_filter($inherit, 'is_string');
                $this->extended[$jsName][$type][$name] = $inherit;
            }
        }
    }

    public function deregisterPackage(string $jsName): void
    {
        unset($this->extended[$jsName]);
    }

    public function getPackage(string $jsName): ?array
    {
        return $this->extended[$jsName]??null;
    }

    public function queueHeaderCss(
        string $name,
        string ...$inherits
    ): void {
        $this->queuedRecord['css'][$name] = true;
        $this->queue['css'][$name] = $inherits;
    }

    private function doRegisterExtendedCss(string $name) : void
    {
        // if contain on extended
        if (!isset($this->extended[$name])) {
            return;
        }
        foreach ($this->extended[$name]['css']??[] as $cssName => $inherit) {
            $this->queue['css'][$cssName] ??= $inherit;
        }
    }

    public function queueHeaderJs(
        string $name,
        string ...$inherits
    ): void {
        $this->queuedRecord['js'][$name] = true;
        $this->queue['js_header'][$name] = $inherits;
        unset($this->queue['js_footer'][$name]);
        $this->doRegisterExtendedCss($name);
    }

    public function queueFooterJs(
        string $name,
        string ...$inherits
    ): void {
        $this->queuedRecord['js'][$name] = true;
        $this->queue['js_footer'][$name] = $inherits;
        unset($this->queue['js_header'][$name]);
        $this->doRegisterExtendedCss($name);
    }

    public function renderHeader(): string
    {
        if ($this->header !== null) {
            return $this->header;
        }

        $this->header = '';
        $manager = $this->getCollection()->getManager();
        // @dispatch(assetsJsCssQueue.beforeRenderHeader)
        $manager?->dispatch('assetsJsCssQueue.beforeRenderHeader', $this);
        try {
            $jsLists = $this->queue['js_header'] ?? [];
            $jssFooter = $this->queue['js_footer'] ?? [];
            $cssLists = $this->queue['css'] ?? [];
            foreach ($this->extended as $jsName => $list) {
                // does not contain js skip!
                if (!isset($jsLists[$jsName]) && !isset($jssFooter[$jsName])) {
                    continue;
                }
                // sorting
                $jsHeader = [];
                $jsAfter = [];
                foreach ($jsLists as $name => $js) {
                    if (isset($jsHeader[$name])) {
                        $jsAfter[$name] = $js;
                        continue;
                    }
                    $jsHeader[$name] = $js;
                }
                foreach (($list['js'] ?? []) as $js => $inherits) {
                    if (isset($jsLists[$js])) {
                        $jsAfter[$js] = array_unique($jsLists[$js] + $inherits);
                        continue;
                    }
                    if (isset($jssFooter[$js])) {
                        $jsAfter[$js] = array_unique($jssFooter[$js] + $inherits);
                        continue;
                    }
                    $jsHeader[$js] = $inherits;
                }
                $jsLists = $jsHeader + $jsAfter;

                foreach (($list['css'] ?? []) as $cssName => $inherits) {
                    // include in header if contain js
                    if (!isset($cssLists[$cssName])) {
                        $cssLists[$cssName] = $inherits;
                        continue;
                    }
                    $cssLists[$cssName] = array_unique($cssLists[$cssName] + $inherits);
                }
            }

            // @dispatch(assetsJsCssQueue.beforeRenderHeaderCss)
            $manager?->dispatch('assetsJsCssQueue.beforeRenderHeaderCss', $this);
            try {
                foreach ($cssLists as $name => $inherits) {
                    // skip
                    if (isset($this->rendered['css'][$name])) {
                        continue;
                    }
                    if (isset($extendedCss[$name])) {
                        $inherits = array_merge($extendedCss[$name], $inherits);
                    }
                    $css = $this->getCSS()->render($name, true, ...$inherits);
                    if ($css) {
                        $this->header .= "\n" . $css;
                    }
                    $this->rendered['css'][$name] = true;
                    unset($this->queue['css'][$name]);
                }
                // @dispatch(assetsJsCssQueue.renderHeaderCss)
                $manager?->dispatch('assetsJsCssQueue.renderHeaderCss', $this);
            } finally {
                // @dispatch(assetsJsCssQueue.afterRenderHeaderCss)
                $manager?->dispatch('assetsJsCssQueue.afterRenderHeaderCss', $this);
            }

            // @dispatch(assetsJsCssQueue.beforeRenderHeaderJs)
            $manager?->dispatch('assetsJsCssQueue.beforeRenderHeaderJs', $this);
            try {
                foreach ($jsLists as $name => $inherits) {
                    if (isset($this->rendered['js_footer'][$name])) {
                        unset($this->rendered['js_footer'][$name]);
                    }
                    if (isset($this->rendered['js'][$name])) {
                        continue;
                    }
                    $js = $this->getJS()->render($name, true, ...$inherits);
                    if ($js) {
                        $this->header .= "\n" . $js;
                    }
                    $this->rendered['js'][$name] = true;
                    unset($this->queue['js_header'][$name]);
                }
                // @dispatch(assetsJsCssQueue.renderHeaderJs)
                $manager?->dispatch('assetsJsCssQueue.renderHeaderJs', $this);
            } finally {
                // @dispatch(assetsJsCssQueue.afterRenderHeaderJs)
                $manager?->dispatch('assetsJsCssQueue.afterRenderHeaderJs', $this);
            }

            // @dispatch(assetsJsCssQueue.renderHeader)
            $manager?->dispatch('assetsJsCssQueue.renderHeader', $this);
        } finally {
            // @dispatch(assetsJsCssQueue.afterRenderHeader)
            $manager?->dispatch('assetsJsCssQueue.afterRenderHeader', $this);
        }
        return $this->header;
    }

    public function renderFooter(): string
    {
        if ($this->footer !== null) {
            return $this->footer;
        }
        $this->footer = '';
        $manager = $this->getCollection()->getManager();
        // @dispatch(assetsJsCssQueue.beforeRenderFooter)
        $manager?->dispatch('assetsJsCssQueue.beforeRenderFooter', $this);
        try {
            $jssFooter = $this->queue['js_footer'] ?? [];
            foreach ($this->extended as $jsName => $list) {
                // does not contain js skip!
                if (!isset($jssFooter[$jsName])) {
                    continue;
                }
                $jsLists = [];
                $jsAfter = [];
                foreach ($jssFooter as $name => $js) {
                    if (isset($jsLists[$name])) {
                        $jsAfter[$name] = $js;
                        continue;
                    }
                    $jsLists[$name] = $js;
                }
                foreach (($list['js'] ?? []) as $js => $inherits) {
                    if (isset($jssFooter[$js])) {
                        $jsAfter[$js] = array_unique($jssFooter[$js] + $inherits);
                        continue;
                    }
                    $jsLists[$js] = $inherits;
                }
                $jssFooter = $jsLists + $jsAfter;
            }

            // @dispatch(assetsJsCssQueue.beforeRenderFooterJs)
            $manager?->dispatch('assetsJsCssQueue.beforeRenderFooterJs', $this);
            try {
                foreach ($jssFooter as $name => $inherits) {
                    if (isset($this->rendered['js_header'][$name])) {
                        unset($this->rendered['js_header'][$name]);
                    }
                    if (isset($this->rendered['js'][$name])) {
                        continue;
                    }
                    $js = $this->getJS()->render($name, true, ...$inherits);
                    if ($js) {
                        $this->footer .= "\n" . $js;
                    }
                    $this->rendered['js'][$name] = true;
                    unset($this->queue['js_footer'][$name]);
                }
                // @dispatch(assetsJsCssQueue.renderFooterJs)
                $manager?->dispatch('assetsJsCssQueue.renderFooterJs', $this);
            } finally {
                // @dispatch(assetsJsCssQueue.afterRenderFooterJs)
                $manager?->dispatch('assetsJsCssQueue.afterRenderFooterJs', $this);
            }
            // @dispatch(assetsJsCssQueue.renderFooter)
            $manager?->dispatch('assetsJsCssQueue.renderFooter', $this);
        } finally {
            // @dispatch(assetsJsCssQueue.afterRenderFooter)
            $manager?->dispatch('assetsJsCssQueue.afterRenderFooter', $this);
        }
        return $this->footer;
    }

    public function renderLastCss(): string
    {
        $style = '';
        foreach ($this->queue['css']??[] as $name => $inherits) {
            unset($this->queue['css'][$name]);
            if (isset($this->rendered['css'][$name])) {
                continue;
            }
            $css = $this->getCSS()->render($name, true, ...$inherits);
            if ($css) {
                $style .= "\n".$css;
            }
            $this->rendered['css'][$name] = true;
        }
        if ($style) {
            $this->lastStyle[] = $style;
        }
        return $style;
    }

    public function renderLastScript(): string
    {
        $script = '';
        $merge = array_merge(
            $this->queue['js_header']??[],
            $this->queue['js_footer']??[]
        );
        foreach ($merge as $name => $inherits) {
            unset(
                $this->queue['js_footer'][$name],
                $this->queue['js_header'][$name]
            );
            if (isset($this->rendered['js'][$name])) {
                continue;
            }
            $js = $this->getJS()->render($name, true, ...$inherits);
            if ($js) {
                $script .= "\n".$js;
            }
            $this->rendered['js'][$name] = true;
        }
        if ($script) {
            $this->lastScript[] = $script;
        }
        return $script;
    }
}
