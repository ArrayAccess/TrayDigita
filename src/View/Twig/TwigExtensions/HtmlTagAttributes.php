<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\View\Twig\TwigExtensions;

use ArrayAccess\TrayDigita\L10n\Languages\Locale;
use ArrayAccess\TrayDigita\Util\Filter\HtmlAttributes as FilterAttributes;
use Twig\TwigFunction;
use function is_array;
use function is_string;

class HtmlTagAttributes extends AbstractExtension
{
    public function doFilterHtmlAttributes($context = []): string
    {
        $language = null;
        if (is_array($context)) {
            $language = $context['language']??null;
        }
        if ($language === null || !($language = Locale::normalizeLocale($language))) {
            $language = $this->engine->getView()->getParameter('language');
            if (is_string($language)) {
                $language = Locale::normalizeLocale($language);
            }
        }

        $attributes = [
            'lang' => $language??'en'
        ];

        $attributes = $this->engine->getView()->getManager()?->dispatch(
            'view.htmlAttributes',
            $attributes
        )??$attributes;

        $attributes = is_array($attributes)
            ? FilterAttributes::buildAttributes($attributes)
            : null;
        return $attributes ? " $attributes" : '';
    }

    public function doFilterBodyAttributes(): string
    {
        $attributes = [];
        $attributes = $this->engine->getView()->getManager()->dispatch(
            'view.bodyAttributes',
            $attributes
        )??$attributes;

        $attributes = is_array($attributes)
            ? FilterAttributes::buildAttributes($attributes)
            : null;
        return $attributes ? " $attributes" : '';
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'html_attributes',
                [$this, 'doFilterHtmlAttributes'],
                [
                    'is_safe' => ['html'],
                    'need_context' => true,
                ]
            ),
            new TwigFunction(
                'body_attributes',
                [$this, 'doFilterBodyAttributes'],
                [
                    'is_safe' => ['html'],
                    'need_context' => true,
                ]
            )
        ];
    }
}
