<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\View\Twig\TwigExtensions;

use ArrayAccess\TrayDigita\Traits\Service\TranslatorTrait;
use Twig\TwigFunction;

class Translator extends AbstractExtension
{
    use TranslatorTrait;

    public function getFunctions() : array
    {
        return [
            new TwigFunction(
                'translate',
                [$this, 'translate']
            ),
            new TwigFunction(
                'trans',
                [$this, 'trans']
            ),
            new TwigFunction(
                '__',
                [$this, 'trans']
            ),
            new TwigFunction(
                '_x',
                [$this, 'transX']
            ),
            new TwigFunction(
                '_n',
                [$this, 'transN']
            ),
            new TwigFunction(
                '_nx',
                [$this, 'transNX']
            ),
        ];
    }

    public function getFilters(): array
    {
        return [];
    }
}
