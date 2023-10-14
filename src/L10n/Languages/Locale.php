<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\L10n\Languages;

use function preg_match;
use function strlen;
use function strtolower;
use function trim;

class Locale
{
    private static array $cachedLocales = [];

    /**
     * @param string $locale
     *
     * @return ?array{"id":string,"name":string,"count":int,"expression":string}
     */
    public static function getInfo(string $locale) : ?array
    {
        $locale = self::normalizeLocale($locale);
        return $locale ? (self::LANGUAGES[$locale] ?? null) : null;
    }

    /**
     * @param string $locale
     *
     * @return ?string
     */
    public static function normalizeLocale(
        string $locale
    ) : ?string {
        if ('' === $locale || '.' === $locale[0]) {
            return null;
        }
        $length = strlen($locale);
        if ($length < 2 || $length > 10) {
            return null;
        }
        $lowerLocale = strtolower(trim($locale));
        if (isset(self::$cachedLocales[$lowerLocale])) {
            return self::$cachedLocales[$lowerLocale]?:null;
        }

        self::$cachedLocales[$lowerLocale] = false;
        if (isset(self::LANGUAGES[$lowerLocale])) {
            $currentLocale = self::LANGUAGES[$lowerLocale]['id'];
            self::$cachedLocales[$lowerLocale] = $currentLocale;
            return $currentLocale;
        }
        if (!preg_match('/^([a-z]{2})(?:[-_]([a-z]{2}))?(?:([a-z]{2})(?:[-_]([a-z]{2}))?)?(?:\..*)?$/i', $locale, $m)) {
            return null;
        }

        if (!empty($m[4])) {
            $currentLocale = strtolower($m[1] . '_' . $m[2] . $m[3] . '_' . $m[4]);
            if (isset(self::LANGUAGES[$currentLocale])) {
                $currentLocale = self::LANGUAGES[$lowerLocale]['id'];
                self::$cachedLocales[$lowerLocale] = $currentLocale;
                return $currentLocale;
            }
        }

        if (!empty($m[3])) {
            $currentLocale = strtolower($m[1] . '_' . $m[2] . $m[3]);
            if (isset(self::LANGUAGES[$currentLocale])) {
                $currentLocale = self::LANGUAGES[$lowerLocale]['id'];
                self::$cachedLocales[$lowerLocale] = $currentLocale;
                return $currentLocale;
            }
        }

        if (!empty($m[2])) {
            $currentLocale = strtolower($m[1] . '_' . $m[2]);
            if (isset(self::LANGUAGES[$currentLocale])) {
                $currentLocale = self::LANGUAGES[$lowerLocale]['id'];
                self::$cachedLocales[$lowerLocale] = $currentLocale;
                return $currentLocale;
            }
        }

        $currentLocale = strtolower($m[1]);
        $currentLocale = isset(self::LANGUAGES[$currentLocale])
            ? self::LANGUAGES[$currentLocale]['id']
            : false;
        self::$cachedLocales[$lowerLocale] = $currentLocale;
        return $currentLocale?:null;
    }

    final const LANGUAGES = [
        "af" => [
            "id" => "af",
            "name" => "Afrikaans",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "ak" => [
            "id" => "ak",
            "name" => "Akan",
            "count" => 2,
            "expression" => "n > 1"
        ],
        "am" => [
            "id" => "am",
            "name" => "Amharic",
            "count" => 2,
            "expression" => "n > 1"
        ],
        "an" => [
            "id" => "an",
            "name" => "Aragonese",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "ar" => [
            "id" => "ar",
            "name" => "Arabic",
            "count" => 6,
            "expression" => "(n == 0) ? 0  => ((n == 1) ? 1  => ((n == 2) ? 2  => ((n % 100 >= 3 && n % 100 <= 10) ? 3 "
                ."=> ((n % 100 >= 11 && n % 100 <= 99) ? 4  => 5))))"
        ],
        "ar_001" => [
            "id" => "ar_001",
            "name" => "Modern Standard Arabic",
            "count" => 6,
            "expression" => "(n == 0) ? 0  => ((n == 1) ? 1  => ((n == 2) ? 2  => ((n % 100 >= 3 && n % 100 <= 10) ? 3 "
                ."=> ((n % 100 >= 11 && n % 100 <= 99) ? 4  => 5))))"
        ],
        "ars" => [
            "id" => "ars",
            "name" => "Najdi Arabic",
            "count" => 6,
            "expression" => "(n == 0) ? 0  => ((n == 1) ? 1  => ((n == 2) ? 2  => ((n % 100 >= 3 && n % 100 <= 10) ? 3 "
                ."=> ((n % 100 >= 11 && n % 100 <= 99) ? 4  => 5))))"
        ],
        "as" => [
            "id" => "as",
            "name" => "Assamese",
            "count" => 2,
            "expression" => "n > 1"
        ],
        "asa" => [
            "id" => "asa",
            "name" => "Asu",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "ast" => [
            "id" => "ast",
            "name" => "Asturian",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "az" => [
            "id" => "az",
            "name" => "Azerbaijani",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "bal" => [
            "id" => "bal",
            "name" => "Baluchi",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "be" => [
            "id" => "be",
            "name" => "Belarusian",
            "count" => 3,
            "expression" => "(n % 10 == 1 && n % 100 != 11) ? 0  => ((n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 12 || n "
                ."% 100 > 14)) ? 1  => 2)"
        ],
        "bem" => [
            "id" => "bem",
            "name" => "Bemba",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "bez" => [
            "id" => "bez",
            "name" => "Bena",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "bg" => [
            "id" => "bg",
            "name" => "Bulgarian",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "bho" => [
            "id" => "bho",
            "name" => "Bhojpuri",
            "count" => 2,
            "expression" => "n > 1"
        ],
        "bm" => [
            "id" => "bm",
            "name" => "Bambara",
            "count" => 1,
            "expression" => "0"
        ],
        "bn" => [
            "id" => "bn",
            "name" => "Bangla",
            "count" => 2,
            "expression" => "n > 1"
        ],
        "bo" => [
            "id" => "bo",
            "name" => "Tibetan",
            "count" => 1,
            "expression" => "0"
        ],
        "br" => [
            "id" => "br",
            "name" => "Breton",
            "count" => 5,
            "expression" => "(n % 10 == 1 && n % 100 != 11 && n % 100 != 71 && n % 100 != 91) ? 0  => ((n % 10 == 2 && "
                . "n % 100 != 12 && n % 100 != 72 && n % 100 != 92) ? 1  => ((((n % 10 == 3 || n % 10 == 4) || n % "
                . "10 == 9) && (n % 100 < 10 || n % 100 > 19) && (n % 100 < 70 || n % 100 > 79) && (n % 100 < 90 ||"
                . " n % 100 > 99)) ? 2  => ((n != 0 && n % 1000000 == 0) ? 3  => 4)))"
        ],
        "brx" => [
            "id" => "brx",
            "name" => "Bodo",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "bs" => [
            "id" => "bs",
            "name" => "Bosnian",
            "count" => 3,
            "expression" => "(n % 10 == 1 && n % 100 != 11) ? 0  => ((n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 12 || n "
                ."% 100 > 14)) ? 1  => 2)"
        ],
        "ca" => [
            "id" => "ca",
            "name" => "Catalan",
            "count" => 3,
            "expression" => "(n == 1) ? 0  => ((n != 0 && n % 1000000 == 0) ? 1  => 2)"
        ],
        "ce" => [
            "id" => "ce",
            "name" => "Chechen",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "ceb" => [
            "id" => "ceb",
            "name" => "Cebuano",
            "count" => 2,
            "expression" => "n != 1 && n != 2 && n != 3 && (n % 10 == 4 || n % 10 == 6 || n % 10 == 9)"
        ],
        "cgg" => [
            "id" => "cgg",
            "name" => "Chiga",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "chr" => [
            "id" => "chr",
            "name" => "Cherokee",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "ckb" => [
            "id" => "ckb",
            "name" => "Central Kurdish",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "cs" => [
            "id" => "cs",
            "name" => "Czech",
            "count" => 3,
            "expression" => "(n == 1) ? 0  => ((n >= 2 && n <= 4) ? 1  => 2)"
        ],
        "cy" => [
            "id" => "cy",
            "name" => "Welsh",
            "count" => 6,
            "expression" => "(n == 0) ? 0  => ((n == 1) ? 1 => ((n == 2) ? 2 => ((n == 3) ? 3 => ((n == 6) ? 4 => 5))))"
        ],
        "da" => [
            "id" => "da",
            "name" => "Danish",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "de" => [
            "id" => "de",
            "name" => "German",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "de_at" => [
            "id" => "de_AT",
            "name" => "Austrian German",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "de_ch" => [
            "id" => "de_CH",
            "name" => "Swiss High German",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "doi" => [
            "id" => "doi",
            "name" => "Dogri",
            "count" => 2,
            "expression" => "n > 1"
        ],
        "dsb" => [
            "id" => "dsb",
            "name" => "Lower Sorbian",
            "count" => 4,
            "expression" => "(n % 100 == 1) ? 0  => ((n % 100 == 2) ? 1  => ((n % 100 == 3 || n % 100 == 4) ? 2  => 3))"
        ],
        "dv" => [
            "id" => "dv",
            "name" => "Divehi",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "dz" => [
            "id" => "dz",
            "name" => "Dzongkha",
            "count" => 1,
            "expression" => "0"
        ],
        "ee" => [
            "id" => "ee",
            "name" => "Ewe",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "el" => [
            "id" => "el",
            "name" => "Greek",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "en" => [
            "id" => "en",
            "name" => "English",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "en_au" => [
            "id" => "en_AU",
            "name" => "Australian English",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "en_ca" => [
            "id" => "en_CA",
            "name" => "Canadian English",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "en_gb" => [
            "id" => "en_GB",
            "name" => "British English",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "en_us" => [
            "id" => "en_US",
            "name" => "American English",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "eo" => [
            "id" => "eo",
            "name" => "Esperanto",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "es" => [
            "id" => "es",
            "name" => "Spanish",
            "count" => 3,
            "expression" => "(n == 1) ? 0  => ((n != 0 && n % 1000000 == 0) ? 1  => 2)"
        ],
        "es_419" => [
            "id" => "es_419",
            "name" => "Latin American Spanish",
            "count" => 3,
            "expression" => "(n == 1) ? 0  => ((n != 0 && n % 1000000 == 0) ? 1  => 2)"
        ],
        "es_es" => [
            "id" => "es_ES",
            "name" => "European Spanish",
            "count" => 3,
            "expression" => "(n == 1) ? 0  => ((n != 0 && n % 1000000 == 0) ? 1  => 2)"
        ],
        "es_mx" => [
            "id" => "es_MX",
            "name" => "Mexican Spanish",
            "count" => 3,
            "expression" => "(n == 1) ? 0  => ((n != 0 && n % 1000000 == 0) ? 1  => 2)"
        ],
        "et" => [
            "id" => "et",
            "name" => "Estonian",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "eu" => [
            "id" => "eu",
            "name" => "Basque",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "fa" => [
            "id" => "fa",
            "name" => "Persian",
            "count" => 2,
            "expression" => "n > 1"
        ],
        "fa_af" => [
            "id" => "fa_AF",
            "name" => "Dari",
            "count" => 2,
            "expression" => "n > 1"
        ],
        "ff" => [
            "id" => "ff",
            "name" => "Fula",
            "count" => 2,
            "expression" => "n > 1"
        ],
        "fi" => [
            "id" => "fi",
            "name" => "Finnish",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "fil" => [
            "id" => "fil",
            "name" => "Filipino",
            "count" => 2,
            "expression" => "n != 1 && n != 2 && n != 3 && (n % 10 == 4 || n % 10 == 6 || n % 10 == 9)"
        ],
        "fo" => [
            "id" => "fo",
            "name" => "Faroese",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "fr" => [
            "id" => "fr",
            "name" => "French",
            "count" => 3,
            "expression" => "(n == 0 || n == 1) ? 0  => ((n != 0 && n % 1000000 == 0) ? 1  => 2)"
        ],
        "fr_ca" => [
            "id" => "fr_CA",
            "name" => "Canadian French",
            "count" => 3,
            "expression" => "(n == 0 || n == 1) ? 0  => ((n != 0 && n % 1000000 == 0) ? 1  => 2)"
        ],
        "fr_ch" => [
            "id" => "fr_CH",
            "name" => "Swiss French",
            "count" => 3,
            "expression" => "(n == 0 || n == 1) ? 0  => ((n != 0 && n % 1000000 == 0) ? 1  => 2)"
        ],
        "fur" => [
            "id" => "fur",
            "name" => "Friulian",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "fy" => [
            "id" => "fy",
            "name" => "Western Frisian",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "ga" => [
            "id" => "ga",
            "name" => "Irish",
            "count" => 5,
            "expression" => "(n == 1) ? 0 => ((n == 2) ? 1 => ((n >= 3 && n <= 6) ? 2 => ((n >= 7 && n <= 10) "
                . "? 3 => 4)))"
        ],
        "gd" => [
            "id" => "gd",
            "name" => "Scottish Gaelic",
            "count" => 4,
            "expression" => "(n == 1 || n == 11) ? 0  => ((n == 2 || n == 12) ? 1  => ((n >= 3 && n <= 10 || n "
                . ">= 13 && n <= 19) ? 2  => 3))"
        ],
        "gl" => [
            "id" => "gl",
            "name" => "Galician",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "gsw" => [
            "id" => "gsw",
            "name" => "Swiss German",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "gu" => [
            "id" => "gu",
            "name" => "Gujarati",
            "count" => 2,
            "expression" => "n > 1"
        ],
        "guw" => [
            "id" => "guw",
            "name" => "Gun",
            "count" => 2,
            "expression" => "n > 1"
        ],
        "gv" => [
            "id" => "gv",
            "name" => "Manx",
            "count" => 4,
            "expression" => "(n % 10 == 1) ? 0  => ((n % 10 == 2) ? 1  => ((n % 100 == 0 || n % 100 == 20 || n % 100 =="
                . " 40 || n % 100 == 60 || n % 100 == 80) ? 2  => 3))"
        ],
        "ha" => [
            "id" => "ha",
            "name" => "Hausa",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "haw" => [
            "id" => "haw",
            "name" => "Hawaiian",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "he" => [
            "id" => "he",
            "name" => "Hebrew",
            "count" => 3,
            "expression" => "(n == 1) ? 0  => ((n == 2) ? 1  => 2)"
        ],
        "hi" => [
            "id" => "hi",
            "name" => "Hindi",
            "count" => 2,
            "expression" => "n > 1"
        ],
        "hi_latn" => [
            "id" => "hi_Latn",
            "name" => "Hindi (Latin)",
            "count" => 2,
            "expression" => "n > 1"
        ],
        "hnj" => [
            "id" => "hnj",
            "name" => "Hmong Njua",
            "count" => 1,
            "expression" => "0"
        ],
        "hr" => [
            "id" => "hr",
            "name" => "Croatian",
            "count" => 3,
            "expression" => "(n % 10 == 1 && n % 100 != 11) ? 0 => ((n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 12 || n %"
                . " 100 > 14)) ? 1 => 2)"
        ],
        "hsb" => [
            "id" => "hsb",
            "name" => "Upper Sorbian",
            "count" => 4,
            "expression" => "(n % 100 == 1) ? 0  => ((n % 100 == 2) ? 1  => ((n % 100 == 3 || n % 100 == 4) ? 2  => 3))"
        ],
        "hu" => [
            "id" => "hu",
            "name" => "Hungarian",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "hy" => [
            "id" => "hy",
            "name" => "Armenian",
            "count" => 2,
            "expression" => "n > 1"
        ],
        "ia" => [
            "id" => "ia",
            "name" => "Interlingua",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "id" => [
            "id" => "id",
            "name" => "Indonesian",
            "count" => 1,
            "expression" => "0"
        ],
        "ig" => [
            "id" => "ig",
            "name" => "Igbo",
            "count" => 1,
            "expression" => "0"
        ],
        "ii" => [
            "id" => "ii",
            "name" => "Sichuan Yi",
            "count" => 1,
            "expression" => "0"
        ],
        "io" => [
            "id" => "io",
            "name" => "Ido",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "is" => [
            "id" => "is",
            "name" => "Icelandic",
            "count" => 2,
            "expression" => "n % 10 != 1 || n % 100 == 11"
        ],
        "it" => [
            "id" => "it",
            "name" => "Italian",
            "count" => 3,
            "expression" => "(n == 1) ? 0  => ((n != 0 && n % 1000000 == 0) ? 1  => 2)"
        ],
        "iu" => [
            "id" => "iu",
            "name" => "Inuktitut",
            "count" => 3,
            "expression" => "(n == 1) ? 0  => ((n == 2) ? 1  => 2)"
        ],
        "ja" => [
            "id" => "ja",
            "name" => "Japanese",
            "count" => 1,
            "expression" => "0"
        ],
        "jbo" => [
            "id" => "jbo",
            "name" => "Lojban",
            "count" => 1,
            "expression" => "0"
        ],
        "jgo" => [
            "id" => "jgo",
            "name" => "Ngomba",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "jmc" => [
            "id" => "jmc",
            "name" => "Machame",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "jv" => [
            "id" => "jv",
            "name" => "Javanese",
            "count" => 1,
            "expression" => "0"
        ],
        "jw" => [
            "id" => "jw",
            "name" => "Javanese",
            "count" => 1,
            "expression" => "0"
        ],
        "ka" => [
            "id" => "ka",
            "name" => "Georgian",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "kab" => [
            "id" => "kab",
            "name" => "Kabyle",
            "count" => 2,
            "expression" => "n > 1"
        ],
        "kaj" => [
            "id" => "kaj",
            "name" => "Jju",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "kcg" => [
            "id" => "kcg",
            "name" => "Tyap",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "kde" => [
            "id" => "kde",
            "name" => "Makonde",
            "count" => 1,
            "expression" => "0"
        ],
        "kea" => [
            "id" => "kea",
            "name" => "Kabuverdianu",
            "count" => 1,
            "expression" => "0"
        ],
        "kk" => [
            "id" => "kk",
            "name" => "Kazakh",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "kkj" => [
            "id" => "kkj",
            "name" => "Kako",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "kl" => [
            "id" => "kl",
            "name" => "Kalaallisut",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "km" => [
            "id" => "km",
            "name" => "Khmer",
            "count" => 1,
            "expression" => "0"
        ],
        "kn" => [
            "id" => "kn",
            "name" => "Kannada",
            "count" => 2,
            "expression" => "n > 1"
        ],
        "ko" => [
            "id" => "ko",
            "name" => "Korean",
            "count" => 1,
            "expression" => "0"
        ],
        "ks" => [
            "id" => "ks",
            "name" => "Kashmiri",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "ksb" => [
            "id" => "ksb",
            "name" => "Shambala",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "ksh" => [
            "id" => "ksh",
            "name" => "Colognian",
            "count" => 3,
            "expression" => "(n == 0) ? 0  => ((n == 1) ? 1  => 2)"
        ],
        "ku" => [
            "id" => "ku",
            "name" => "Kurdish",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "kw" => [
            "id" => "kw",
            "name" => "Cornish",
            "count" => 6,
            "expression" => "(n == 0) ? 0  => ((n == 1) ? 1  => (((n % 100 == 2 || n % 100 == 22 || n % 100 == 42 || n "
                . "% 100 == 62 || n % 100 == 82) || n % 1000 == 0 && (n % 100000 >= 1000 && n % 100000 <= 20000 || n % "
                . "100000 == 40000 || n % 100000 == 60000 || n % 100000 == 80000) || n != 0 && n % 1000000 == 100000) ?"
                . " 2  => ((n % 100 == 3 || n % 100 == 23 || n % 100 == 43 || n % 100 == 63 || n % 100 == 83) ? 3  => "
                . "((n != 1 && (n % 100 == 1 || n % 100 == 21 || n % 100 == 41 || n % 100 == 61 || n % 100 == 81)) ? 4"
                . "  => 5))))"
        ],
        "ky" => [
            "id" => "ky",
            "name" => "Kyrgyz",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "lag" => [
            "id" => "lag",
            "name" => "Langi",
            "count" => 3,
            "expression" => "(n == 0) ? 0  => ((n == 1) ? 1  => 2)"
        ],
        "lb" => [
            "id" => "lb",
            "name" => "Luxembourgish",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "lg" => [
            "id" => "lg",
            "name" => "Ganda",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "lij" => [
            "id" => "lij",
            "name" => "Ligurian",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "lkt" => [
            "id" => "lkt",
            "name" => "Lakota",
            "count" => 1,
            "expression" => "0"
        ],
        "ln" => [
            "id" => "ln",
            "name" => "Lingala",
            "count" => 2,
            "expression" => "n > 1"
        ],
        "lo" => [
            "id" => "lo",
            "name" => "Lao",
            "count" => 1,
            "expression" => "0"
        ],
        "lt" => [
            "id" => "lt",
            "name" => "Lithuanian",
            "count" => 3,
            "expression" => "(n % 10 == 1 && (n % 100 < 11 || n % 100 > 19)) ? 0  => ((n % 10 >= 2 && n % 10 <= 9 && (n"
                . " % 100 < 11 || n % 100 > 19)) ? 1  => 2)"
        ],
        "lv" => [
            "id" => "lv",
            "name" => "Latvian",
            "count" => 3,
            "expression" => "(n % 10 == 0 || n % 100 >= 11 && n % 100 <= 19) ? 0  => ((n % 10 == 1 && n % 100 != 11) ? "
                . "1  => 2)"
        ],
        "mas" => [
            "id" => "mas",
            "name" => "Masai",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "mg" => [
            "id" => "mg",
            "name" => "Malagasy",
            "count" => 2,
            "expression" => "n > 1"
        ],
        "mgo" => [
            "id" => "mgo",
            "name" => "Meta\u02bc",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "mk" => [
            "id" => "mk",
            "name" => "Macedonian",
            "count" => 2,
            "expression" => "n % 10 != 1 || n % 100 == 11"
        ],
        "ml" => [
            "id" => "ml",
            "name" => "Malayalam",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "mn" => [
            "id" => "mn",
            "name" => "Mongolian",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "mo" => [
            "id" => "mo",
            "name" => "Moldavian",
            "count" => 3,
            "expression" => "(n == 1) ? 0  => ((n == 0 || n != 1 && n % 100 >= 1 && n % 100 <= 19) ? 1  => 2)"
        ],
        "mr" => [
            "id" => "mr",
            "name" => "Marathi",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "ms" => [
            "id" => "ms",
            "name" => "Malay",
            "count" => 1,
            "expression" => "0"
        ],
        "mt" => [
            "id" => "mt",
            "name" => "Maltese",
            "count" => 5,
            "expression" => "(n == 1) ? 0  => ((n == 2) ? 1  => ((n == 0 || n % 100 >= 3 && n % 100 <= 10) ? 2  => ((n"
                . " % 100 >= 11 && n % 100 <= 19) ? 3  => 4)))"
        ],
        "my" => [
            "id" => "my",
            "name" => "Burmese",
            "count" => 1,
            "expression" => "0"
        ],
        "nah" => [
            "id" => "nah",
            "name" => "Nahuatl",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "naq" => [
            "id" => "naq",
            "name" => "Nama",
            "count" => 3,
            "expression" => "(n == 1) ? 0  => ((n == 2) ? 1  => 2)"
        ],
        "nb" => [
            "id" => "nb",
            "name" => "Norwegian Bokm\u00e5l",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "nd" => [
            "id" => "nd",
            "name" => "North Ndebele",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "ne" => [
            "id" => "ne",
            "name" => "Nepali",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "nl" => [
            "id" => "nl",
            "name" => "Dutch",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "nl_be" => [
            "id" => "nl_BE",
            "name" => "Flemish",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "nn" => [
            "id" => "nn",
            "name" => "Norwegian Nynorsk",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "nnh" => [
            "id" => "nnh",
            "name" => "Ngiemboon",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "no" => [
            "id" => "no",
            "name" => "Norwegian",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "nqo" => [
            "id" => "nqo",
            "name" => "N\u2019Ko",
            "count" => 1,
            "expression" => "0"
        ],
        "nr" => [
            "id" => "nr",
            "name" => "South Ndebele",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "nso" => [
            "id" => "nso",
            "name" => "Northern Sotho",
            "count" => 2,
            "expression" => "n > 1"
        ],
        "ny" => [
            "id" => "ny",
            "name" => "Nyanja",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "nyn" => [
            "id" => "nyn",
            "name" => "Nyankole",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "om" => [
            "id" => "om",
            "name" => "Oromo",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "or" => [
            "id" => "or",
            "name" => "Odia",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "os" => [
            "id" => "os",
            "name" => "Ossetic",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "osa" => [
            "id" => "osa",
            "name" => "Osage",
            "count" => 1,
            "expression" => "0"
        ],
        "pa" => [
            "id" => "pa",
            "name" => "Punjabi",
            "count" => 2,
            "expression" => "n > 1"
        ],
        "pap" => [
            "id" => "pap",
            "name" => "Papiamento",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "pcm" => [
            "id" => "pcm",
            "name" => "Nigerian Pidgin",
            "count" => 2,
            "expression" => "n > 1"
        ],
        "pl" => [
            "id" => "pl",
            "name" => "Polish",
            "count" => 3,
            "expression" => "(n == 1) ? 0  => ((n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 12 || n % 100 > 14)) ? 1 => 2)"
        ],
        "prg" => [
            "id" => "prg",
            "name" => "Prussian",
            "count" => 3,
            "expression" => "(n % 10 == 0 || n % 100 >= 11 && n % 100 <= 19) ? 0 => ((n % 10 == 1 && n % 100 != 11) ? "
                . "1 => 2)"
        ],
        "ps" => [
            "id" => "ps",
            "name" => "Pashto",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "pt" => [
            "id" => "pt",
            "name" => "Portuguese",
            "count" => 3,
            "expression" => "(n == 0 || n == 1) ? 0  => ((n != 0 && n % 1000000 == 0) ? 1  => 2)"
        ],
        "pt_br" => [
            "id" => "pt_BR",
            "name" => "Brazilian Portuguese",
            "count" => 3,
            "expression" => "(n == 0 || n == 1) ? 0  => ((n != 0 && n % 1000000 == 0) ? 1  => 2)"
        ],
        "pt_pt" => [
            "id" => "pt_PT",
            "name" => "European Portuguese",
            "count" => 3,
            "expression" => "(n == 1) ? 0  => ((n != 0 && n % 1000000 == 0) ? 1  => 2)"
        ],
        "rm" => [
            "id" => "rm",
            "name" => "Romansh",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "ro" => [
            "id" => "ro",
            "name" => "Romanian",
            "count" => 3,
            "expression" => "(n == 1) ? 0  => ((n == 0 || n != 1 && n % 100 >= 1 && n % 100 <= 19) ? 1  => 2)"
        ],
        "ro_md" => [
            "id" => "ro_MD",
            "name" => "Moldavian",
            "count" => 3,
            "expression" => "(n == 1) ? 0  => ((n == 0 || n != 1 && n % 100 >= 1 && n % 100 <= 19) ? 1  => 2)"
        ],
        "rof" => [
            "id" => "rof",
            "name" => "Rombo",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "ru" => [
            "id" => "ru",
            "name" => "Russian",
            "count" => 3,
            "expression" => "(n % 10 == 1 && n % 100 != 11) ? 0  => ((n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 12 || "
                . "n % 100 > 14)) ? 1  => 2)"
        ],
        "rwk" => [
            "id" => "rwk",
            "name" => "Rwa",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "sah" => [
            "id" => "sah",
            "name" => "Yakut",
            "count" => 1,
            "expression" => "0"
        ],
        "saq" => [
            "id" => "saq",
            "name" => "Samburu",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "sat" => [
            "id" => "sat",
            "name" => "Santali",
            "count" => 3,
            "expression" => "(n == 1) ? 0  => ((n == 2) ? 1  => 2)"
        ],
        "sc" => [
            "id" => "sc",
            "name" => "Sardinian",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "scn" => [
            "id" => "scn",
            "name" => "Sicilian",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "sd" => [
            "id" => "sd",
            "name" => "Sindhi",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "sdh" => [
            "id" => "sdh",
            "name" => "Southern Kurdish",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "se" => [
            "id" => "se",
            "name" => "Northern Sami",
            "count" => 3,
            "expression" => "(n == 1) ? 0  => ((n == 2) ? 1  => 2)"
        ],
        "seh" => [
            "id" => "seh",
            "name" => "Sena",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "ses" => [
            "id" => "ses",
            "name" => "Koyraboro Senni",
            "count" => 1,
            "expression" => "0"
        ],
        "sg" => [
            "id" => "sg",
            "name" => "Sango",
            "count" => 1,
            "expression" => "0"
        ],
        "sh" => [
            "id" => "sh",
            "name" => "Serbo-Croatian",
            "count" => 3,
            "expression" => "(n % 10 == 1 && n % 100 != 11) ? 0  => ((n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 12 || n "
                . "% 100 > 14)) ? 1  => 2)"
        ],
        "shi" => [
            "id" => "shi",
            "name" => "Tachelhit",
            "count" => 3,
            "expression" => "(n == 0 || n == 1) ? 0  => ((n >= 2 && n <= 10) ? 1  => 2)"
        ],
        "si" => [
            "id" => "si",
            "name" => "Sinhala",
            "count" => 2,
            "expression" => "n > 1"
        ],
        "sk" => [
            "id" => "sk",
            "name" => "Slovak",
            "count" => 3,
            "expression" => "(n == 1) ? 0  => ((n >= 2 && n <= 4) ? 1  => 2)"
        ],
        "sl" => [
            "id" => "sl",
            "name" => "Slovenian",
            "count" => 4,
            "expression" => "(n % 100 == 1) ? 0  => ((n % 100 == 2) ? 1  => ((n % 100 == 3 || n % 100 == 4) ? 2  => 3))"
        ],
        "sma" => [
            "id" => "sma",
            "name" => "Southern Sami",
            "count" => 3,
            "expression" => "(n == 1) ? 0  => ((n == 2) ? 1  => 2)"
        ],
        "smi" => [
            "id" => "smi",
            "name" => "Sami",
            "count" => 3,
            "expression" => "(n == 1) ? 0  => ((n == 2) ? 1  => 2)"
        ],
        "smj" => [
            "id" => "smj",
            "name" => "Lule Sami",
            "count" => 3,
            "expression" => "(n == 1) ? 0  => ((n == 2) ? 1  => 2)"
        ],
        "smn" => [
            "id" => "smn",
            "name" => "Inari Sami",
            "count" => 3,
            "expression" => "(n == 1) ? 0  => ((n == 2) ? 1  => 2)"
        ],
        "sms" => [
            "id" => "sms",
            "name" => "Skolt Sami",
            "count" => 3,
            "expression" => "(n == 1) ? 0  => ((n == 2) ? 1  => 2)"
        ],
        "sn" => [
            "id" => "sn",
            "name" => "Shona",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "so" => [
            "id" => "so",
            "name" => "Somali",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "sq" => [
            "id" => "sq",
            "name" => "Albanian",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "sr" => [
            "id" => "sr",
            "name" => "Serbian",
            "count" => 3,
            "expression" => "(n % 10 == 1 && n % 100 != 11) ? 0  => ((n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 12 || n "
                . "% 100 > 14)) ? 1  => 2)"
        ],
        "sr_me" => [
            "id" => "sr_ME",
            "name" => "Montenegrin",
            "count" => 3,
            "expression" => "(n % 10 == 1 && n % 100 != 11) ? 0  => ((n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 12 || n "
                ."% 100 > 14)) ? 1  => 2)"
        ],
        "ss" => [
            "id" => "ss",
            "name" => "Swati",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "ssy" => [
            "id" => "ssy",
            "name" => "Saho",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "st" => [
            "id" => "st",
            "name" => "Southern Sotho",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "su" => [
            "id" => "su",
            "name" => "Sundanese",
            "count" => 1,
            "expression" => "0"
        ],
        "sv" => [
            "id" => "sv",
            "name" => "Swedish",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "sw" => [
            "id" => "sw",
            "name" => "Swahili",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "sw_cd" => [
            "id" => "sw_CD",
            "name" => "Congo Swahili",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "syr" => [
            "id" => "syr",
            "name" => "Syriac",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "ta" => [
            "id" => "ta",
            "name" => "Tamil",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "te" => [
            "id" => "te",
            "name" => "Telugu",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "teo" => [
            "id" => "teo",
            "name" => "Teso",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "th" => [
            "id" => "th",
            "name" => "Thai",
            "count" => 1,
            "expression" => "0"
        ],
        "ti" => [
            "id" => "ti",
            "name" => "Tigrinya",
            "count" => 2,
            "expression" => "n > 1"
        ],
        "tig" => [
            "id" => "tig",
            "name" => "Tigre",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "tk" => [
            "id" => "tk",
            "name" => "Turkmen",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "tl" => [
            "id" => "tl",
            "name" => "Tagalog",
            "count" => 2,
            "expression" => "n != 1 && n != 2 && n != 3 && (n % 10 == 4 || n % 10 == 6 || n % 10 == 9)"
        ],
        "tn" => [
            "id" => "tn",
            "name" => "Tswana",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "to" => [
            "id" => "to",
            "name" => "Tongan",
            "count" => 1,
            "expression" => "0"
        ],
        "tpi" => [
            "id" => "tpi",
            "name" => "Tok Pisin",
            "count" => 1,
            "expression" => "0"
        ],
        "tr" => [
            "id" => "tr",
            "name" => "Turkish",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "ts" => [
            "id" => "ts",
            "name" => "Tsonga",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "tzm" => [
            "id" => "tzm",
            "name" => "Central Atlas Tamazight",
            "count" => 2,
            "expression" => "n >= 2 && (n < 11 || n > 99)"
        ],
        "ug" => [
            "id" => "ug",
            "name" => "Uyghur",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "uk" => [
            "id" => "uk",
            "name" => "Ukrainian",
            "count" => 3,
            "expression" => "(n % 10 == 1 && n % 100 != 11) ? 0  => ((n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 12 || n"
                . " % 100 > 14)) ? 1  => 2)"
        ],
        "ur" => [
            "id" => "ur",
            "name" => "Urdu",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "uz" => [
            "id" => "uz",
            "name" => "Uzbek",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "ve" => [
            "id" => "ve",
            "name" => "Venda",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "vec" => [
            "id" => "vec",
            "name" => "Venetian",
            "count" => 3,
            "expression" => "(n == 1) ? 0  => ((n != 0 && n % 1000000 == 0) ? 1  => 2)"
        ],
        "vi" => [
            "id" => "vi",
            "name" => "Vietnamese",
            "count" => 1,
            "expression" => "0"
        ],
        "vo" => [
            "id" => "vo",
            "name" => "Volap\u00fck",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "vun" => [
            "id" => "vun",
            "name" => "Vunjo",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "wa" => [
            "id" => "wa",
            "name" => "Walloon",
            "count" => 2,
            "expression" => "n > 1"
        ],
        "wae" => [
            "id" => "wae",
            "name" => "Walser",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "wo" => [
            "id" => "wo",
            "name" => "Wolof",
            "count" => 1,
            "expression" => "0"
        ],
        "xh" => [
            "id" => "xh",
            "name" => "Xhosa",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "xog" => [
            "id" => "xog",
            "name" => "Soga",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "yi" => [
            "id" => "yi",
            "name" => "Yiddish",
            "count" => 2,
            "expression" => "n != 1"
        ],
        "yo" => [
            "id" => "yo",
            "name" => "Yoruba",
            "count" => 1,
            "expression" => "0"
        ],
        "yue" => [
            "id" => "yue",
            "name" => "Cantonese",
            "count" => 1,
            "expression" => "0"
        ],
        "zh" => [
            "id" => "zh",
            "name" => "Chinese",
            "count" => 1,
            "expression" => "0"
        ],
        "zh_Hans" => [
            "id" => "zh_Hans",
            "name" => "Simplified Chinese",
            "count" => 1,
            "expression" => "0"
        ],
        "zh_Hant" => [
            "id" => "zh_Hant",
            "name" => "Traditional Chinese",
            "count" => 1,
            "expression" => "0"
        ],
        "zu" => [
            "id" => "zu",
            "name" => "Zulu",
            "count" => 2,
            "expression" => "n > 1"
        ]
    ];
}
