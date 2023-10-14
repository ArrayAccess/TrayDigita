<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Assets\Traits;

use ArrayAccess\TrayDigita\Assets\Interfaces\DependenciesInterface;
use ArrayAccess\TrayDigita\Util\Filter\HtmlAttributes;
use function is_array;
use function sprintf;

trait JavascriptTrait
{
    protected string $suffix = 'js';

    abstract public function getDependencies() : DependenciesInterface;

    abstract public function getSource();

    abstract public function getId() : string;

    public function setType(string $type): static
    {
        $this->attributes['type'] = $type;
        return $this;
    }

    public function getAttributes(): array
    {
        return [
                'id' => sprintf('%s-%s', $this->getId(), $this->suffix),
                'src' => $this->getSource()
            ] + $this->attributes;
    }

    public function render(): string
    {
        $attributes = $this->getAttributes();
        // @dispatch(assetsRender.jsAttributes)
        $newAttributes = $this
            ->getDependencies()
            ->getAssetsCollection()
            ->getManager()
            ?->dispatch('assetsRender.jsAttributes', $attributes);
        $attributes = is_array($newAttributes) ? $newAttributes : $attributes;
        return sprintf(
            '<script %s></script>',
            HtmlAttributes::buildAttributes($attributes)
        );
    }
}
