<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\HttpKernel\Traits;

use ArrayAccess\TrayDigita\Collection\Config;
use ArrayAccess\TrayDigita\Container\Container;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\L10n\Translations\Adapter\Gettext\PoMoAdapter;
use ArrayAccess\TrayDigita\L10n\Translations\Adapter\Json\JsonAdapter;
use ArrayAccess\TrayDigita\L10n\Translations\Interfaces\TranslatorInterface;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use Throwable;
use function is_dir;
use function is_string;

trait HttpKernelProviderTrait
{
    protected function registerProviders(): void
    {
        if ($this->providerRegistered) {
            return;
        }
        $this->providerRegistered = true;
        $container = $this->getHttpKernel()->getContainer();
        $manager = ContainerHelper::use(ManagerInterface::class, $container);
        $manager?->dispatch('kernel.beforeRegisterProviders', $this);
        try {
            $config = ContainerHelper::use(Config::class, $container)?->get('path');
            $config = $config instanceof Config ? $config : null;
            $translator = ContainerHelper::use(TranslatorInterface::class, $container);
            $translator = $translator instanceof TranslatorInterface
                ? $translator
                : null;
            if ($translator) {
                if ($container instanceof Container) {
                    try {
                        $poMoAdapter = $container->decorate(PoMoAdapter::class);
                        $jsonAdapter = $container->decorate(JsonAdapter::class);
                    } catch (Throwable) {
                    }
                }
                $poMoAdapter ??= new PoMoAdapter();
                $jsonAdapter ??= new JsonAdapter();
                $translator->addAdapter($poMoAdapter);
                $translator->addAdapter($jsonAdapter);

                $languageDir = $config?->get('language');
                if (is_string($languageDir) && is_dir($languageDir)) {
                    $poMoAdapter->registerDirectory(
                        $languageDir,
                        TranslatorInterface::DEFAULT_DOMAIN
                    );
                    $jsonAdapter->registerDirectory(
                        $languageDir,
                        TranslatorInterface::DEFAULT_DOMAIN
                    );
                }
            }
            $manager?->dispatch('kernel.registerProviders', $this);
        } finally {
            $manager?->dispatch('kernel.afterRegisterProviders', $this);
        }
    }
}
