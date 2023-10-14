<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Assets\Traits;

use ArrayAccess\TrayDigita\Assets\Interfaces\DependenciesInterface;
use ArrayAccess\TrayDigita\Util\Filter\HtmlAttributes;
use function is_array;
use function sprintf;
use function strtolower;
use function trim;

trait CascadingStyleSheetTrait
{
    protected string $suffix = 'css';

    abstract public function getDependencies() : DependenciesInterface;

    abstract public function getSource();

    abstract public function getId() : string;

    public function setMedia(?string $media): void
    {
        if ($media === null || trim($media) === '') {
            unset($this->attributes['media']);
            return;
        }
        $this->attributes['media'] = trim(strtolower($media));
    }

    public function getAttributes(): array
    {
        return [
                'rel' => 'stylesheet',
                'id' => sprintf(
                    '%s-%s',
                    $this->getId(),
                    $this->suffix
                ),
                'href' => $this->getSource(),
            ] + $this->attributes;
    }

    public function render(): string
    {
        $attributes = $this->getAttributes();
        // @dispatch(assetsRender.cssAttributes)
        $newAttributes = $this
            ->getDependencies()
            ->getAssetsCollection()
            ->getManager()
            ?->dispatch('assetsRender.cssAttributes', $attributes);
        $attributes = is_array($newAttributes) ? $newAttributes : $attributes;
        return sprintf(
            '<link %s>',
            HtmlAttributes::buildAttributes($attributes)
        );
    }
}
