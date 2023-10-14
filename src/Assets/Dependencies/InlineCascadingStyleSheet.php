<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Assets\Dependencies;

use ArrayAccess\TrayDigita\Assets\Abstracts\AbstractInlineDependency;
use ArrayAccess\TrayDigita\Assets\Interfaces\DependenciesInterface;
use ArrayAccess\TrayDigita\Assets\Traits\CascadingStyleSheetTrait;
use ArrayAccess\TrayDigita\Util\Filter\HtmlAttributes;
use function is_array;
use function sprintf;

class InlineCascadingStyleSheet extends AbstractInlineDependency
{
    use CascadingStyleSheetTrait;

    public function __construct(DependenciesInterface $dependencies)
    {
        parent::__construct($dependencies);
        $this->suffix = 'inline-css';
    }

    public function getAttributes(): array
    {
        return [
                'id' => sprintf(
                    '%s-%s',
                    $this->getId(),
                    $this->suffix
                ),
            ] + $this->attributes;
    }

    public function render(): string
    {
        $attributes = $this->getAttributes();
        // @dispatch(assetsRender.inlineCssAttributes)
        $newAttributes = $this
            ->getDependencies()
            ->getAssetsCollection()
            ->getManager()
            ?->dispatch('assetsRender.inlineCssAttributes', $attributes);
        $attributes = is_array($newAttributes) ? $newAttributes : $attributes;
        // unset($attributes['type']); // style does not need text/css
        return sprintf(
            '<style %s>%s</style>',
            HtmlAttributes::buildAttributes($attributes),
            $this->getSource()
        );
    }
}
