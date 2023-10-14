<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\L10n\PoMo\Generator;

use function array_unshift;
use function array_values;
use function explode;
use function implode;
use function is_int;
use function sprintf;

class PoGenerator extends AbstractGenerator
{
    public function generate() : string
    {
        $translations = [];
        $description = $this->getTranslations()->getDescription();
        $flags       = $this->getTranslations()->getFlags()->toArray();
        if ($description) {
            foreach (explode("\n", $description) as $comment) {
                $translations[] = "# $comment";
            }
        }
        if (!empty($flags)) {
            $translations[] = '#, '.implode(', ', $flags);
        }

        $translations[] = 'msgid ""';
        $translations[] = 'msgstr ""';
        $headers = $this->getTranslations()->getHeaders();
        $maxPluralCount = $headers->getPluralForm()->getPluralCount();
        $translations[] = $headers->toPoHeader();
        $translations[] = '';

        foreach ($this->getTranslations()->getTranslations() as $translation) {
            foreach ($translation->getComments() as $comment) {
                $translations[] = "# $comment";
            }
            foreach ($translation->getExtractedComments() as $extractedComment) {
                $translations[] = "#. $extractedComment";
            }
            foreach ($translation->getReferences()->toArray() as $source => $reference) {
                foreach ($reference as $ref) {
                    $translations[] = "#: $source".(is_int($ref) ? ":$ref" : '');
                }
            }
            if (count($translation->getFlags()) > 0) {
                $translations[] = "#, ".implode(", ", $translation->getFlags()->toArray());
            }
            $prefix = $translation->isEnable() ? '' : '#~ ';
            if ($translation->getContext()) {
                $translations[] = $prefix . sprintf(
                    'msgctxt "%s"',
                    $this->normalize($translation->getContext())
                );
            }

            $translations[] = $prefix . sprintf(
                'msgid "%s"',
                $this->normalize($translation->getOriginal())
            );
            $hasPlural = $translation->getPlural() !== null;
            if ($hasPlural) {
                $translations[] = $prefix . sprintf(
                    'msgid_plural "%s"',
                    $this->normalize($translation->getPlural())
                );
                $plural = $translation->getPluralTranslations(
                    $maxPluralCount - 1
                );
                array_unshift($plural, $translation->getTranslation());
                $plural = array_values($plural);
                $totalPlural = count($plural);
                for ($i = 0; $totalPlural > $i; $i++) {
                    $translations[] = $prefix . sprintf(
                        'msgstr[%d] "%s"',
                        $i,
                        $this->normalize($plural[$i])
                    );
                }
            } else {
                $translations[] = $prefix . sprintf(
                    'msgstr "%s"',
                    $this->normalize($translation->getTranslation())
                );
            }

            $translations[] = "";
        }

        return implode("\n", $translations);
    }

    private function normalize(string $trans) : string
    {
        return strtr(
            $trans,
            [
                "\t" => '\\t',
                "\r" => '\\r',
                "\n" => '\\n',
                "\v" => '\\v',
                "\f" => '\\f',
                "\e" => '\\e',
                "\x08" => '\\b',
                "\x07" => '\\a',
                '\\' => '\\\\',
                '"' => '\\"',
            ],
        );
    }
}
