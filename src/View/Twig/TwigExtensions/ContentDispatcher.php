<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\View\Twig\TwigExtensions;

use Twig\TwigFunction;
use function func_get_args;
use function is_bool;
use function is_string;

class ContentDispatcher extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'content_header',
                fn ($allowMultiple = true) => $this
                    ->engine
                    ->getView()
                    ->dispatchHeader(is_bool($allowMultiple) ? $allowMultiple : true)
            ),
            new TwigFunction(
                'content_footer',
                fn ($allowMultiple = true) => $this
                    ->engine
                    ->getView()
                    ->dispatchFooter(is_bool($allowMultiple) ? $allowMultiple : true)
            ),
            new TwigFunction(
                'body_open',
                function () {
                    $this
                        ->engine
                        ->getView()
                        ->getManager()
                        ->dispatch('view.bodyOpen', $this->engine->getView());
                }
            ),
            new TwigFunction(
                'body_close',
                function () {
                    $this
                        ->engine
                        ->getView()
                        ->getManager()
                        ?->dispatch('view.bodyClose', $this->engine->getView());
                }
            ),
            new TwigFunction(
                'dispatchEvent',
                function ($eventName = null) {
                    if (!$eventName || !is_string($eventName)) {
                        return;
                    }
                    $this
                        ->engine
                        ->getView()
                        ->getManager()
                        ?->dispatch(...func_get_args());
                }
            )
        ];
    }
}
