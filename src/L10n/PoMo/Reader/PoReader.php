<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\L10n\PoMo\Reader;

use ArrayAccess\TrayDigita\L10n\PoMo\Translations;
use Psr\Http\Message\StreamInterface;
use function array_map;
use function array_shift;
use function current;
use function explode;
use function implode;
use function intval;
use function next;
use function preg_match;
use function preg_split;
use function substr;
use function trim;

class PoReader extends AbstractReader
{
    /**
     * @param StreamInterface $stream
     * @param Translations|null $translations
     *
     * @return Translations
     */
    public function fromStream(
        StreamInterface $stream,
        ?Translations $translations = null
    ) : Translations {
        return $this->fromString((string) $stream, $translations);
    }

    /**
     * @param string $data
     * @param ?Translations $translations
     *
     * @return Translations
     */
    public function fromString(
        string $data,
        ?Translations $translations = null
    ) : Translations {
        $translations ??= new Translations();
        $data         = explode("\n", $data);
        $line         = current($data);
        $translation  = $this->translationFactory->createTranslation(null, '');
        while ($line !== false) {
            $line = trim($line);
            $nextLine = next($data);

            //Multiline
            while (str_ends_with($line, '"')
                && $nextLine !== false
                && ($nextLine = trim($nextLine)) !== ''
                && (str_starts_with($nextLine, '"')
                    || str_starts_with($nextLine, '#~ "'))
            ) {
                if (str_starts_with(trim($nextLine), '"')) {
                    // Normal multiline
                    $line = substr($line, 0, -1) . substr($nextLine, 1);
                } elseif (str_starts_with(trim($nextLine), '#~ "')) { // Disabled multiline
                    $line = substr($line, 0, -1) . substr($nextLine, 4);
                }
                $nextLine = next($data);
            }

            //End of translation
            if ($line === '') {
                // translation should not empty
                if ($translation->getOriginal() && $translation->getTranslation()) {
                    $translations->add($translation);
                }
                $translation = $this->translationFactory->createTranslation(null, '');
                $line = $nextLine;
                continue;
            }

            $splitLine = preg_split('/\s+/', $line, 2);
            $key = $splitLine[0];
            $trans = $splitLine[1] ?? '';

            if ($key === '#~') {
                $translation->disable();

                $splitLine = preg_split('/\s+/', $trans, 2);
                $key = $splitLine[0];
                $trans = $splitLine[1] ?? '';
            }

            if ($trans === '') {
                $line = $nextLine;
                continue;
            }

            switch ($key) {
                case '#': // comments
                    $translation->getComments()->add($trans);
                    break;
                case '#.': // extracted comments
                    $translation->getExtractedComments()->add($trans);
                    break;
                case '#,': // flags
                    foreach (array_map('trim', explode(',', trim($trans))) as $value) {
                        $translation->getFlags()->add($value);
                    }
                    break;
                case '#:': // reference
                    foreach (preg_split('/\s+/', trim($trans)) as $value) {
                        if (preg_match('/^(.+)(:(\d*))?$/U', $value, $matches)) {
                            $line = isset($matches[3]) ? intval($matches[3]) : null;
                            $translation->getReferences()->add($matches[1], $line);
                        }
                    }
                    break;
                case 'msgctxt': // context
                    $translation = $translation->withContext($this->normalize($trans));
                    break;
                case 'msgid': // original message id
                    $translation = $translation->withOriginal($this->normalize($trans));
                    break;
                case 'msgid_plural': // plural message
                    $translation->setPlural($this->normalize($trans));
                    break;
                case 'msgstr': // translation
                case 'msgstr[0]': // translation on plural
                    $translation->setTranslation($this->normalize($trans));
                    break;
                default:
                    if (str_starts_with($key, 'msgstr[')) {
                        $p = $translation->getPluralTranslations();
                        $p[] = $this->normalize($trans);
                        $translation->setPluralTranslations(...$p);
                        break;
                    }
                    break;
            }

            $line = $nextLine;
        }

        if ($translation->getOriginal() || $translation->getTranslation()) {
            $translations->add($translation);
        }

        // Headers
        $translation = $translations->find('');
        if (!$translation) {
            return $translations;
        }

        $translations->remove($translation);

        $description = $translation->getComments()->toArray();
        if (!empty($description)) {
            $translations->setDescription(implode("\n", $description));
        }

        $flags = $translation->getFlags()->toArray();

        if (!empty($flags)) {
            $translations->getFlags()->add(...$flags);
        }
        foreach ($this->parseHeaders($translation->getTranslation()) as $name => $value) {
            $translations->getHeaders()->add($name, $value);
        }
        $translations->setTranslationsPluralForm($translations->getHeaders()->getPluralForm());
        return $translations;
    }

    private function parseHeaders(?string $string) : array
    {
        if (empty($string)) {
            return [];
        }

        $headers = [];
        $lines = explode("\n", $string);
        $name = null;
        foreach ($lines as $line) {
            $line = $this->normalize($line);
            if ($line === '') {
                continue;
            }

            // Checks if it is a header definition line.
            // Useful for distinguishing between header definitions and possible continuations of a header entry.
            if (preg_match('/^[\w-]+:/', $line)) {
                $pieces = array_map('trim', explode(':', $line, 2));
                $name = array_shift($pieces);
                $headers[$name] = implode(':', $pieces);
                continue;
            }

            $value = $headers[$name] ?? '';
            $headers[$name] = $value . $line;
        }

        return $headers;
    }

    /**
     * Convert a string from its PO representation.
     */
    private function normalize(string $value) : string
    {
        if (!$value) {
            return '';
        }

        if ($value[0] === '"') {
            $value = substr($value, 1, -1);
        }

        return strtr(
            $value,
            [
                '\\t' => "\t",
                '\\r' => "\r",
                '\\n' => "\n",
                '\\v' => "\v",
                '\\f' => "\f",
                '\\e' => "\e",
                '\\a' => "\x07",
                "\\b" => "\x08",
                '\\\\' => "\\",
                '\\"' => '"',
            ]
        );
    }
}
