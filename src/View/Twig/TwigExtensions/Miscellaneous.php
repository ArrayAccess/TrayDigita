<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\View\Twig\TwigExtensions;

use ArrayAccess\TrayDigita\Kernel\Interfaces\KernelInterface;
use ArrayAccess\TrayDigita\PossibleRoot;
use ArrayAccess\TrayDigita\Util\Filter\Consolidation;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;
use function is_string;

class Miscellaneous extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'print_r',
                static fn ($data = null) => print_r($data, true),
                [
                    'is_safe' => ['html']
                ]
            ),
            new TwigFunction(
                'container',
                fn ($containerName = null) => is_string($containerName) ? ContainerHelper::getNull(
                    $containerName,
                    $this->getContainer()
                ) : null
            ),
            new TwigFunction(
                'base_uri',
                fn ($path = '') => $this->engine->getView()->getBaseURI((string) $path)
            ),
            new TwigFunction(
                'base_url',
                fn ($path = '') => (string) $this->engine->getView()->getBaseURI((string) $path)
            ),
            new TwigFunction(
                'request',
                [$this->engine->getView(), 'getRequest']
            ),
            new TwigFunction(
                'template_uri',
                fn ($path = '') => $this->engine->getView()->getTemplateURI((string) $path)
            ),
            new TwigFunction(
                'template_url',
                fn ($path = '') => (string) $this->engine->getView()->getTemplateURI((string) $path)
            ),
        ];
    }

    /** @noinspection PhpInternalEntityUsedInspection */
    public function getTests(): array
    {
        return [
            new TwigTest(
                'function_loaded',
                fn ($fn) => (bool) (is_string($fn) && $this->engine->getTwig()->getFunction($fn)
                    ? '(true)'
                    : '(false)'
                ),
            ),
        ];
    }

    /**
     * @return TwigFilter[]
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter(
                'protect_path',
                function ($data) {
                    $root = ContainerHelper::use(KernelInterface::class, $this->getContainer())
                        ?->getRootDirectory()??PossibleRoot::getPossibleRootDirectory();
                    if (!$root) {
                        return $data;
                    }
                    return Consolidation::protectMessage($data, $root);
                }
            ),
        ];
    }
}
