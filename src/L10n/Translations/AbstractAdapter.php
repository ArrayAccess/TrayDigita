<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\L10n\Translations;

use ArrayAccess\TrayDigita\L10n\Translations\Interfaces\AdapterInterface;
use ArrayAccess\TrayDigita\L10n\Translations\Interfaces\TranslatorInterface;

abstract class AbstractAdapter implements AdapterInterface
{
    /**
     * The constructor
     *
     * @param TranslatorInterface $translator
     */
    public function __construct(protected TranslatorInterface $translator)
    {
    }

    /**
     * @inheritdoc
     */
    public function getTranslator(): TranslatorInterface
    {
        return $this->translator;
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return $this::class;
    }

    /**
     * Generate id
     *
     * @param string|null $context
     * @param string $original
     * @return string
     * @final
     */
    final public static function generateId(?string $context, string $original) : string
    {
        return Entry::generateId($context, $original);
    }
}
