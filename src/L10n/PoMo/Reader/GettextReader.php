<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\L10n\PoMo\Reader;

use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\EmptyArgumentException;
use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\InvalidArgumentException;
use ArrayAccess\TrayDigita\L10n\PoMo\Factory\TranslationFactory;
use ArrayAccess\TrayDigita\L10n\PoMo\Interfaces\TranslationFactoryInterface;
use function is_string;
use function is_subclass_of;
use function sprintf;
use function strtolower;
use function trim;

class GettextReader
{
    public const MO = 'mo';

    public const PO = 'po';

    private TranslationFactoryInterface $translationFactory;

    /**
     * @var array<string, class-string<AbstractReader>>|AbstractReader[]
     */
    private array $readers = [
        self::MO => MoReader::class,
        self::PO => PoReader::class,
    ];

    public function __construct(?TranslationFactoryInterface $translationFactory = null)
    {
        $this->translationFactory = $translationFactory??new TranslationFactory();
    }

    /**
     * @return TranslationFactoryInterface
     */
    public function getTranslationFactory() : TranslationFactoryInterface
    {
        return $this->translationFactory;
    }

    /**
     * @param TranslationFactoryInterface $translationFactory
     */
    public function setTranslationFactory(TranslationFactoryInterface $translationFactory) : void
    {
        $this->translationFactory = $translationFactory;
    }

    /**
     * @param string $extension
     * @param class-string<AbstractReader>|AbstractReader $classNameOrReader
     * @throws InvalidArgumentException
     */
    public function setReader(string $extension, string|AbstractReader $classNameOrReader): void
    {
        if (is_string($classNameOrReader) && !is_subclass_of($classNameOrReader, AbstractReader::class)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Reader must be subclass of %s but %s given',
                    AbstractReader::class,
                    $classNameOrReader
                )
            );
        }
        $extension = strtolower(trim($extension));
        if ($extension === '') {
            throw new EmptyArgumentException(
                'Extension could not be empty or contains whitespace only'
            );
        }
        $this->readers[$extension] = $classNameOrReader;
    }

    /**
     * @param string $extension
     *
     * @return ?AbstractReader
     */
    public function getReader(string $extension) : ?AbstractReader
    {
        $extension = strtolower(trim($extension));
        $reader = $this->readers[$extension]??null;
        if (is_string($reader)) {
            $reader = new $reader($this->getTranslationFactory());
            $this->readers[$extension] = $reader;
        }
        return $reader;
    }
}
