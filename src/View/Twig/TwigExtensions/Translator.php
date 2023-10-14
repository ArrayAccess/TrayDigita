<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\View\Twig\TwigExtensions;

use ArrayAccess\TrayDigita\L10n\Translations\Interfaces\TranslatorInterface;
use ArrayAccess\TrayDigita\Traits\Service\TranslatorTrait;
use Twig\TwigFilter;
use Twig\TwigFunction;
use function is_array;
use function is_numeric;
use function is_scalar;
use function is_string;
use function round;

class Translator extends AbstractExtension
{
    use TranslatorTrait;

    public function doTranslate(
        $original = '',
        $domain = null,
        $context = null
    ): string {
        $original = is_scalar($original) ? (string) $original : '';
        if ($original === '') {
            return '';
        }
        $context = !is_array($context) ? null : $context;
        $domain = !is_string($domain) ? null : $domain;
        $domain ??= TranslatorInterface::DEFAULT_DOMAIN;
        return $this->translate($original, $domain, $context);
    }

    public function doTranslatePlural(
        $singular = '',
        $plural = null,
        $number = 1,
        $domain = null,
        $context = null
    ): string {
        $number = is_numeric($number) ? (int) (str_contains($number, '.') ? round($number) : $number): 1;
        $singular = is_scalar($singular) ? (string) $singular : '';
        if ($singular === '') {
            return '';
        }
        if (!is_scalar($plural)) {
            return $singular;
        }
        $plural = (string) $plural;
        $context = !is_array($context) ? null : $context;
        $domain = !is_string($domain) ? null : $domain;
        $domain ??= TranslatorInterface::DEFAULT_DOMAIN;
        return $this->translatePlural(
            $singular,
            $plural,
            $number,
            $domain,
            $context
        );
    }
    
    public function getFunctions() : array
    {
        return [
            new TwigFunction(
                'translate',
                [$this, 'doTranslate']
            ),
            new TwigFunction(
                '__',
                [$this, 'doTranslate']
            ),
            new TwigFunction(
                'translate_plural',
                [$this, 'doTranslatePlural']
            ),
            new TwigFunction(
                '_n',
                [$this, 'doTranslatePlural']
            ),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter(
                'translate',
                [$this, 'doTranslate']
            ),
            new TwigFilter(
                'translate_plural',
                [$this, 'doTranslatePlural']
            )
        ];
    }
}
