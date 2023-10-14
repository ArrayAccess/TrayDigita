<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Traits\Service;

use ArrayAccess\TrayDigita\L10n\Translations\Interfaces\TranslatorInterface;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use Psr\Container\ContainerInterface;

trait TranslatorTrait
{
    protected ?TranslatorInterface $translatorObject = null;

    abstract public function getContainer() : ?ContainerInterface;

    public function getTranslator() : ?TranslatorInterface
    {
        if ($this->translatorObject) {
            return $this->translatorObject;
        }

        return $this->translatorObject = ContainerHelper::service(
            TranslatorInterface::class,
            $this->getContainer()
        );
    }

    public function translate(
        string $original,
        string $domain = TranslatorInterface::DEFAULT_DOMAIN,
        ?string $context = null
    ): string {
        $translator = $this->getTranslator();
        return $translator
            ? $this->getTranslator()->translate($original, $domain, $context)
            : $original;
    }

    public function translatePlural(
        string $singular,
        string $plural,
        int|float $number,
        string $domain = TranslatorInterface::DEFAULT_DOMAIN,
        ?string $context = null
    ): string {
        $translator = $this->getTranslator();
        if ($translator) {
            $translation = $this->getTranslator()->translatePlural(
                $singular,
                $plural,
                $number,
                $domain,
                $context
            );
        }
        return $translation ?? (
            $number === 1 || $number === 1.0 ? $singular : $plural
        );
    }
}
