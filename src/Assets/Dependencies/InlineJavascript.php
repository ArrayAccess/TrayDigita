<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Assets\Dependencies;

use ArrayAccess\TrayDigita\Assets\Abstracts\AbstractInlineDependency;
use ArrayAccess\TrayDigita\Assets\Interfaces\DependenciesInterface;
use ArrayAccess\TrayDigita\Assets\Traits\JavascriptTrait;
use ArrayAccess\TrayDigita\Util\Filter\HtmlAttributes;
use function is_array;
use function sprintf;

class InlineJavascript extends AbstractInlineDependency
{
    use JavascriptTrait;

    protected array $attributes = [
        'type' => 'text/javascript'
    ];

    public function __construct(DependenciesInterface $dependencies)
    {
        parent::__construct($dependencies);
        $this->suffix = 'inline-js';
    }

    public function getAttributes(): array
    {
        return [
                'id' => sprintf('%s-%s', $this->getId(), $this->suffix),
            ] + $this->attributes;
    }

    public function render(): string
    {
        $attributes = $this->getAttributes();
        // @dispatch(assetsRender.inlineJsAttributes)
        $newAttributes = $this
            ->getDependencies()
            ->getAssetsCollection()
            ->getManager()
            ?->dispatch('assetsRender.inlineJsAttributes', $attributes);
        $attributes = is_array($newAttributes) ? $newAttributes : $attributes;
        unset($attributes['src']); // no src
        return sprintf(
            '<script %s>%s</script>',
            HtmlAttributes::buildAttributes($attributes),
            $this->getSource()
        );
    }
}
