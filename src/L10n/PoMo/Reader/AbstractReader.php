<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\L10n\PoMo\Reader;

use ArrayAccess\TrayDigita\Http\Stream;
use ArrayAccess\TrayDigita\L10n\PoMo\Exceptions\FileNotFoundException;
use ArrayAccess\TrayDigita\L10n\PoMo\Exceptions\UnreadableException;
use ArrayAccess\TrayDigita\L10n\PoMo\Factory\TranslationFactory;
use ArrayAccess\TrayDigita\L10n\PoMo\Interfaces\TranslationFactoryInterface;
use ArrayAccess\TrayDigita\L10n\PoMo\Translations;
use Psr\Http\Message\StreamInterface;
use function file_exists;
use function is_readable;

abstract class AbstractReader
{
    protected string $fileName;

    /**
     * @var TranslationFactoryInterface
     */
    protected TranslationFactoryInterface $translationFactory;

    /**
     * @var ?Translations
     */
    protected ?Translations $translations;

    /**
     * @return Translations
     */
    public function getTranslations() : Translations
    {
        return $this->translations??new Translations();
    }

    /**
     * @param ?TranslationFactoryInterface $translationFactory
     */
    public function __construct(?TranslationFactoryInterface $translationFactory = null)
    {
        $this->translationFactory = $translationFactory??new TranslationFactory();
    }

    /**
     * @param string $fileName
     * @param ?Translations $translations
     *
     * @return Translations
     */
    public function fromFile(
        string $fileName,
        ?Translations $translations = null
    ) : Translations {
        if (!file_exists($fileName)) {
            throw new FileNotFoundException($fileName);
        }
        if (!is_readable($fileName)) {
            throw new UnreadableException($fileName);
        }

        return $this->fromStream(Stream::fromFile($fileName, 'rb'), $translations);
    }

    /**
     * @param StreamInterface $stream
     * @param ?Translations $translations
     *
     * @return Translations
     */
    abstract public function fromStream(
        StreamInterface $stream,
        ?Translations $translations = null
    ) : Translations;

    /**
     * @param string $data
     * @param ?Translations $translations
     *
     * @return Translations
     */
    abstract public function fromString(
        string $data,
        ?Translations $translations = null
    ) : Translations;
}
