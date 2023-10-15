<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Traits\Service;

use ArrayAccess\TrayDigita\L10n\Translations\Interfaces\TranslatorInterface;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use Psr\Container\ContainerInterface;
use function is_numeric;
use function is_string;
use function str_contains;

trait TranslatorTrait
{
    protected ?TranslatorInterface $translatorObject = null;

    abstract public function getContainer() : ?ContainerInterface;

    /**
     * @return ?TranslatorInterface
     */
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

    /**
     * @param string $original
     * @param string $domain
     * @param ?string $context
     * @return string
     */
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

    /**
     * @param string $singular
     * @param string $plural
     * @param int|float|numeric-string $number safe with numeric-string
     * @param string $domain
     * @param string|null $context
     * @return string
     */
    public function translatePlural(
        string $singular,
        string $plural,
        int|float|string $number,
        string $domain = TranslatorInterface::DEFAULT_DOMAIN,
        ?string $context = null
    ): string {
        if (is_string($number)) {
            if (!is_numeric($number)) {
                return $singular;
            }
            $number = str_contains($number, '.') ? (float) $number : (int) $number;
        }
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

    /**
     * Translate context
     *
     * @param string $original
     * @param string $context
     * @param string $domain
     * @return string
     * @see TranslatorInterface::translateContext()
     */
    public function translateContext(
        string $original,
        string $context,
        string $domain = TranslatorInterface::DEFAULT_DOMAIN,
    ): string {
        $translator = $this->getTranslator();
        return $translator
            ? $this->getTranslator()->translateContext($original, $context, $domain)
            : $original;
    }

    /**
     * Translate plural context
     *
     * @param string $singular
     * @param string $plural
     * @param int|float|numeric-string $number
     * @param string $context
     * @param string $domain
     * @return string
     */
    public function translatePluralContext(
        string $singular,
        string $plural,
        int|float|string $number,
        string $context,
        string $domain = TranslatorInterface::DEFAULT_DOMAIN
    ) : string {
        if (is_string($number)) {
            if (!is_numeric($number)) {
                return $singular;
            }
            $number = str_contains($number, '.') ? (float) $number : (int) $number;
        }
        $translator = $this->getTranslator();
        if ($translator) {
            $translation = $this->getTranslator()->translatePluralContext(
                $singular,
                $plural,
                $number,
                $context,
                $domain
            );
        }

        return $translation ?? (
        $number === 1 || $number === 1.0 ? $singular : $plural
        );
    }

    /**
     * Translate without context
     *
     * @param string $original
     * @param string $domain
     * @return string
     */
    public function trans(
        string $original,
        string $domain = TranslatorInterface::DEFAULT_DOMAIN,
    ): string {
        return $this->translate($original, $domain);
    }

    /**
     * Translate with context
     *
     * @param string $original
     * @param string $context
     * @param string $domain
     * @return string
     */
    public function transX(
        string $original,
        string $context,
        string $domain = TranslatorInterface::DEFAULT_DOMAIN,
    ): string {
        return $this->translateContext($original, $context, $domain);
    }

    /**
     * Translate plural without context
     *
     * @param string $singular
     * @param string $plural
     * @param int|float|numeric-string $number
     * @param string $domain
     * @return string
     */
    public function transN(
        string $singular,
        string $plural,
        int|float|string $number,
        string $domain = TranslatorInterface::DEFAULT_DOMAIN,
    ): string {
        return $this->translatePlural($singular, $plural, $number, $domain);
    }

    /**
     * Translate plural without context
     *
     * @param string $singular
     * @param string $plural
     * @param int|float|numeric-string $number
     * @param string $context
     * @param string $domain
     * @return string
     */
    public function transNX(
        string $singular,
        string $plural,
        int|float|string $number,
        string $context,
        string $domain = TranslatorInterface::DEFAULT_DOMAIN
    ): string {
        return $this->translatePluralContext($singular, $plural, $number, $context, $domain);
    }
}
