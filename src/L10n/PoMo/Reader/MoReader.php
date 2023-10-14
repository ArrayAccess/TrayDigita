<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\L10n\PoMo\Reader;

use ArrayAccess\TrayDigita\Http\Stream;
use ArrayAccess\TrayDigita\L10n\PoMo\Exceptions\FileNotFoundException;
use ArrayAccess\TrayDigita\L10n\PoMo\Exceptions\InvalidStreamDataException;
use ArrayAccess\TrayDigita\L10n\PoMo\Exceptions\UnreadableException;
use ArrayAccess\TrayDigita\L10n\PoMo\Translations;
use Psr\Http\Message\StreamInterface;
use function array_filter;
use function array_shift;
use function explode;
use function file_exists;
use function fopen;
use function implode;
use function is_readable;
use function reset;
use function substr;
use function trim;
use function unpack;

class MoReader extends AbstractReader
{
    /**
     * low endian
     */
    private const MAGIC1 = 0x950412de;
    /**
     * big endian
     */
    private const MAGIC2 = 0xde120495;

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
            throw new UnReadableException($fileName);
        }
        $stream = new Stream(fopen($fileName, 'rb'));
        return $this->fromStream($stream, $translations);
    }

    /**
     * @param StreamInterface $stream
     * @param ?Translations $translations
     *
     * @return Translations
     */
    public function fromStream(
        StreamInterface $stream,
        ?Translations $translations = null
    ) : Translations {
        $stream->isSeekable() && $stream->rewind();
        $magic  = $this->readInt($stream, 'V');
        // to make sure it works for 64-bit platforms
        if (($magic === self::MAGIC1) || ($magic === self::MAGIC1 & 0xffffffff)) {
            $endianFormat = 'V';
        } elseif ($magic === self::MAGIC2 & 0xffffffff) {
            $endianFormat = 'N';
        } else {
            throw new InvalidStreamDataException(
                'Stream is not gettext mo data'
            );
        }

        $revision = $this->readInt($stream, $endianFormat);
        $total = $this->readInt($stream, $endianFormat); //total string count
        $originalOffset = $this->readInt($stream, $endianFormat); //offset of original table
        $translationOffset = $this->readInt($stream, $endianFormat); //offset of translation table

        $stream->seek($originalOffset);
        $originalTable = $this->readIntArray($stream, $endianFormat, $total * 2);

        $stream->seek($translationOffset);
        $translationTable = $this->readIntArray($stream, $endianFormat, $total * 2);

        $translations ??= new Translations();
        $translations->setRevision($revision);
        for ($i = 0; $i < $total; ++$i) {
            $next = $i * 2;
            $stream->seek($originalTable[$next + 2]);
            $original = $originalTable[$next + 1] === 0
                ? '' :
                $stream->read($originalTable[$next + 1]);
            $stream->seek($translationTable[$next + 2]);
            $translated = $stream->read($translationTable[$next + 1]);

            // Headers
            if ($original === '') {
                foreach (explode("\n", $translated) as $header) {
                    if (trim($header) === '') {
                        continue;
                    }
                    $header = explode(':', trim($header));
                    $key    = trim(array_shift($header));
                    $header = trim(implode(':', $header));
                    $translations->getHeaders()->add($key, $header);
                }
                continue;
            }
            $context = $plural = null;
            // Look for context, separated by \4.
            $chunks = explode("\4", $original, 2);
            if (isset($chunks[1])) {
                $original = $chunks[1];
                $context = $chunks[0];
            }

            // Look for plural original.
            $chunks = explode("\0", $original, 2);
            $original = $chunks[0];
            if (isset($chunks[1])) {
                $plural = $chunks[1];
            }
            $translation = $this->translationFactory->createTranslation(
                $context,
                $original
            )->setPlural($plural);
            if ($translated || $translated === '' && $original === '') {
                $translation->setTranslation($translated);
            }
            $translations->add($translation);
            if ($translated === '' || $plural === null) {
                continue;
            }

            $translation->setPluralForm($translations->getHeaders()->getPluralForm());
            $v = explode("\0", $translated);
            array_shift($v);
            $translation->setPluralTranslations(...array_filter($v));
        }

        // set plural
        $translations->setTranslationsPluralForm(
            $translations->getHeaders()->getPluralForm()
        );

        return $translations;
    }

    /**
     * @param string $data
     * @param Translations|null $translations
     *
     * @return Translations
     */
    public function fromString(string $data, ?Translations $translations = null) : Translations
    {
        $stream = Stream::fromFile('php://temp', 'rb+');
        while ($data !== '') {
            $stream->write(substr($data, 0, 4096));
            $data = substr($data, 4096);
        }

        return $this->fromStream($stream, $translations);
    }

    private function readInt(StreamInterface $stream, string $mode) : int
    {
        $data = unpack($mode, $stream->read(4));
        return reset($data);
    }

    private function readIntArray(StreamInterface $stream, string $mode, int $count) : array
    {
        return unpack(
            $mode . $count,
            $stream->read(4 * $count)
        );
    }

    /**
     * @return string
     */
    public function getFileName() : string
    {
        return $this->fileName;
    }
}
