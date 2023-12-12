<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Util\Filter;

use function str_replace;
use function strtolower;
use function trim;

/**
 * Character encoding / charsets
 *
 * @link https://www.iana.org/assignments/character-sets/character-sets.xhtml
 */
final class CharacterEncoding
{
    /**
     * @var ?array list of lower encoding keys
     */
    private static ?array $lowerEncoding = null;

    /**
     * @param string $encoding
     * @return ?string
     */
    public static function filterEncoding(string $encoding) : ?string
    {
        $encoding = strtolower(trim($encoding));
        if (isset(self::ENCODING_LIST[$encoding])) {
            return self::ENCODING_LIST[$encoding];
        }
        $removedSeparator = str_replace(['-', '_', ' '], '', $encoding);
        if (self::$lowerEncoding === null) {
            // create list encoding
            foreach (self::ENCODING_LIST as $key => $encoding) {
                $theKeyEncoding = str_replace('-', '', $key);
                $theEncoding = str_replace(['-', '_'], '', $encoding);
                self::$lowerEncoding[$theEncoding] = $key;
                self::$lowerEncoding[$theKeyEncoding] = $key;
            }
        }
        // check the key
        $key = self::$lowerEncoding[$removedSeparator] ?? null;
        return $key ? (self::$lowerEncoding[$key] ?? null) : null;
    }

    /**
     * @var array<string, string>
     */
    public const ENCODING_LIST = [
        'big5' => 'Big5',
        'big5-hkscs' => 'Big5-HKSCS',
        'cp037' => 'IBM037',
        'cp424' => 'IBM424',
        'cp437' => 'IBM437',
        'cp500' => 'IBM500',
        'cp720' => 'windows-1256',
        'cp737' => 'x-IBM737',
        'cp775' => 'IBM775',
        'cp850' => 'IBM850',
        'cp852' => 'IBM852',
        'cp855' => 'IBM855',
        'cp856' => 'IBM856',
        'cp857' => 'IBM857',
        'cp858' => 'IBM858',
        'cp860' => 'IBM860',
        'cp861' => 'IBM861',
        'cp862' => 'IBM862',
        'cp863' => 'IBM863',
        'cp864' => 'IBM864',
        'cp865' => 'IBM865',
        'cp866' => 'IBM866',
        'cp869' => 'IBM869',
        'cp874' => 'windows-874',
        'cp875' => 'cp875',
        'cp932' => 'Shift_JIS',
        'cp936' => 'GBK',
        'cp949' => 'EUC-KR',
        'cp950' => 'Big5',
        'cp1006' => 'cp1006',
        'cp1026' => 'IBM1026',
        'cp1140' => 'IBM01140',
        'cp1250' => 'windows-1250',
        'cp1251' => 'windows-1251',
        'cp1252' => 'windows-1252',
        'cp1253' => 'windows-1253',
        'cp1254' => 'windows-1254',
        'cp1255' => 'windows-1255',
        'cp1256' => 'windows-1256',
        'cp1257' => 'windows-1257',
        'cp1258' => 'windows-1258',
        'euc-jp' => 'EUC-JP',
        'euc-kr' => 'EUC-KR',
        'gb18030' => 'GB18030',
        'gb2312' => 'GB2312', // continue list many / all next
        'gbk' => 'GBK',
        'hz-gb-2312' => 'HZ-GB-2312',
        'ibm-thai' => 'IBM-Thai',
        'ibm00858' => 'IBM00858',
        'ibm01140' => 'IBM01140',
        'ibm01141' => 'IBM01141',
        'ibm01142' => 'IBM01142',
        'ibm01143' => 'IBM01143',
        'ibm01144' => 'IBM01144',
        'ibm01145' => 'IBM01145',
        'ibm01146' => 'IBM01146',
        'ibm01147' => 'IBM01147',
        'ibm01148' => 'IBM01148',
        'ibm01149' => 'IBM01149',
        'ibm037' => 'IBM037',
        'ibm1026' => 'IBM1026',
        'ibm1047' => 'IBM1047',
        'ibm273' => 'IBM273',
        'ibm277' => 'IBM277',
        'ibm278' => 'IBM278',
        'ibm280' => 'IBM280',
        'ibm284' => 'IBM284',
        'ibm285' => 'IBM285',
        'ibm290' => 'IBM290',
        'ibm297' => 'IBM297',
        'ibm420' => 'IBM420',
        'ibm423' => 'IBM423',
        'ibm424' => 'IBM424',
        'ibm437' => 'IBM437',
        'ibm500' => 'IBM500',
        'ibm775' => 'IBM775',
        'ibm850' => 'IBM850',
        'ibm852' => 'IBM852',
        'ibm855' => 'IBM855',
        'ibm857' => 'IBM857',
        'ibm860' => 'IBM860',
        'ibm861' => 'IBM861',
        'ibm862' => 'IBM862',
        'ibm863' => 'IBM863',
        'ibm864' => 'IBM864',
        'ibm865' => 'IBM865',
        'ibm866' => 'IBM866',
        'ibm869' => 'IBM869',
        'ibm870' => 'IBM870',
        'ibm871' => 'IBM871',
        'ibm918' => 'IBM918',
        'iso-2022-jp' => 'ISO-2022-JP',
        'iso-2022-jp-2' => 'ISO-2022-JP-2',
        'iso-2022-kr' => 'ISO-2022-KR',
        'iso-8859-1' => 'ISO-8859-1',
        'iso-8859-2' => 'ISO-8859-2',
        'iso-8859-3' => 'ISO-8859-3',
        'iso-8859-4' => 'ISO-8859-4',
        'iso-8859-5' => 'ISO-8859-5',
        'iso-8859-6' => 'ISO-8859-6',
        'iso-8859-7' => 'ISO-8859-7',
        'iso-8859-8' => 'ISO-8859-8',
        'iso-8859-9' => 'ISO-8859-9',
        'iso-8859-10' => 'ISO-8859-10',
        'iso-8859-13' => 'ISO-8859-13',
        'iso-8859-14' => 'ISO-8859-14',
        'iso-8859-15' => 'ISO-8859-15',
        'iso-8859-16' => 'ISO-8859-16',
        'koi8-r' => 'KOI8-R',
        'koi8-u' => 'KOI8-U',
        'macintosh' => 'macintosh',
        'shift_jis' => 'Shift_JIS',
        'utf-16be' => 'UTF-16BE',
        'utf-16le' => 'UTF-16LE',
        'utf-16' => 'UTF-16',
        'utf-32be' => 'UTF-32BE',
        'utf-32le' => 'UTF-32LE',
        'utf-32' => 'UTF-32',
        'utf-7' => 'UTF-7',
        'utf8' => 'UTF-8',
        'windows-1250' => 'windows-1250',
        'windows-1251' => 'windows-1251',
        'windows-1252' => 'windows-1252',
        'windows-1253' => 'windows-1253',
        'windows-1254' => 'windows-1254',
        'windows-1255' => 'windows-1255',
        'windows-1256' => 'windows-1256',
        'windows-1257' => 'windows-1257',
        'windows-1258' => 'windows-1258',
        'windows-874' => 'windows-874',
        'cp50220' => 'ISO-2022-JP',
        'tis-620' => 'windows-874',
    ];
}
