<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Util\Filter;

use ArrayAccess\TrayDigita\Exceptions\Runtime\MaximumCallstackExceeded;
use function array_keys;
use function array_merge_recursive;
use function array_pop;
use function array_push;
use function array_reverse;
use function array_values;
use function clearstatcache;
use function explode;
use function file_exists;
use function idn_to_ascii;
use function implode;
use function in_array;
use function is_array;
use function is_dir;
use function is_iterable;
use function is_string;
use function iterator_to_array;
use function mt_rand;
use function preg_match;
use function preg_quote;
use function preg_replace;
use function preg_replace_callback;
use function realpath;
use function rtrim;
use function sprintf;
use function str_contains;
use function str_replace;
use function strlen;
use function strpos;
use function strtolower;
use function substr;
use function trim;
use const DIRECTORY_SEPARATOR;

final class DataNormalizer
{
    public const CONVERSION_TABLES = [
        'À' => 'A',
        'Á' => 'A',
        'Â' => 'A',
        'Ã' => 'A',
        'Ä' => 'A',
        'Å' => 'A',
        'Æ' => 'AE',
        'Ç' => 'C',
        'È' => 'E',
        'É' => 'E',
        'Ê' => 'E',
        'Ë' => 'E',
        'Ì' => 'I',
        'Í' => 'I',
        'Î' => 'I',
        'Ï' => 'I',
        'Ð' => 'D',
        'Ñ' => 'N',
        'Ò' => 'O',
        'Ó' => 'O',
        'Ô' => 'O',
        'Õ' => 'O',
        'Ö' => 'O',
        '×' => 'x',
        'Ø' => '0',
        'Ù' => 'U',
        'Ú' => 'U',
        'Û' => 'U',
        'Ü' => 'U',
        'Ý' => 'Y',
        'Þ' => 'b',
        'ß' => 'B',
        'à' => 'a',
        'á' => 'a',
        'â' => 'a',
        'ã' => 'a',
        'ä' => 'a',
        'å' => 'a',
        'æ' => 'ae',
        'ç' => 'c',
        'è' => 'e',
        'é' => 'e',
        'ê' => 'e',
        'ë' => 'e',
        'ì' => 'i',
        'í' => 'i',
        'î' => 'i',
        'ï' => 'i',
        'ð' => 'o',
        'ñ' => 'n',
        'ò' => 'o',
        'ó' => 'o',
        'ô' => 'o',
        'õ' => 'o',
        'ö' => 'o',
        '÷' => '+',
        'ø' => 'o',
        'ù' => 'i',
        'ú' => 'u',
        'û' => 'u',
        'ü' => 'u',
        'ý' => 'y',
        'þ' => 'B',
        'ÿ' => 'y',
    ];

    /**
     * Normalize the filename
     *
     * @param string $string
     * @param bool $allowSpace
     * @return string
     */
    public static function normalizeFileName(
        string $string,
        bool $allowSpace = false
    ): string {
        // replace whitespace except space to empty character
        // 0x00 - \x1F -> ordinal 0 - 31
        $string = preg_replace('~[\x00\x1F\-]+~', ' ', $string);
        $contains = false;
        $string = preg_replace_callback('~[\xc0-\xff]+~', function ($match) use (&$contains) {
            $contains = true;
            return StringFilter::sanitizeInvalidUtf8FromString($match[0]);
        }, $string);
        if ($contains) {
            // normalize ascii extended to ascii utf8
            $string = str_replace(
                array_keys(self::CONVERSION_TABLES),
                array_values(self::CONVERSION_TABLES),
                $string
            );
        }
        $string = preg_replace(
            '~[^0-9A-Za-z\-_()@\~.\s]+~',
            '-',
            $string
        );

        if (!$allowSpace) {
            $string = preg_replace(
                '~-+~',
                '-',
                str_replace(' ', '-', $string)
            );
        }

        return preg_replace(
            '~([\-_()@\~.\s])\1+~',
            '$1',
            $string
        );
    }

    /**
     * Splitting the string or iterable to array
     *
     * @param mixed $string
     * @param string $separator
     * @return array|null returning null if not an iterable or string
     */
    public static function splitStringToArray(mixed $string, string $separator = ' '): ?array
    {
        // don't process an array
        if (is_array($string)) {
            return $string;
        }
        if (is_string($string)) {
            return explode($separator, $string);
        }
        if (is_iterable($string)) {
            return iterator_to_array($string);
        }
        return null;
    }

    /**
     * Normalize html class
     *
     * @param string $class
     * @param string $fallback
     * @return string
     */
    public static function normalizeHtmlClass(
        string $class,
        string $fallback = ''
    ): string {
        $sanitized = trim($class);
        $classes = [];
        foreach (self::splitStringToArray($sanitized) as $className) {
            $className = trim($className);
            if ($className === '') {
                continue;
            }
            //Limit to A-Z,a-z,0-9,_,-
            $sanitized = preg_replace('/[^A-Za-z0-9_-]/', '', $sanitized);
            if ($sanitized === '') {
                continue;
            }
            // no duplicates
            $classes[$sanitized] = true;
        }

        $sanitized = implode(' ', array_keys($classes));
        if ('' === $sanitized && trim($fallback) !== '') {
            return self::normalizeHtmlClass($fallback);
        }

        return $sanitized;
    }

    /**
     * @param string $data
     * @return string
     */
    public static function removeJSContent(string $data): string
    {
        return preg_replace(
            '~<(script)[^>]+?>.*?</\1>~smi',
            '',
            $data
        );
    }

    /**
     * Balances tags of string using a modified stack.
     *
     * @param string $text Text to be balanced.
     * @return string Balanced text.
     *
     * Custom mods to be fixed to handle by system result output
     * @copyright November 4, 2001
     * @version 1.1
     *
     * Modified by Scott Reilly (coffee2code) 02 Aug 2004
     *      1.1 Fixed handling append/stack pop order of end text
     *          Added Cleaning Hooks
     *      1.0 First Version
     *
     * @author Leonard Lin <leonard@acm.org>
     * @license GPL
     */
    public static function forceBalanceTags(string $text): string
    {
        $tagStack = [];
        $stackSize = 0;
        $tagQueue = '';
        $newText = '';
        // Known single-entity/self-closing tags
        $single_tags = [
            'area',
            'base',
            'basefont',
            'br',
            'col',
            'command',
            'embed',
            'frame',
            'hr',
            'img',
            'input',
            'isindex',
            'link',
            'meta',
            'param',
            'source'
        ];
        $single_tags_2 = [
            'img',
            'meta',
            'link',
            'input'
        ];
        // Tags that can be immediately nested within themselves
        $nestable_tags = ['blockquote', 'div', 'object', 'q', 'span'];
        // check if contains <html> tag and split it
        // fix doctype
        $text = preg_replace('/<(\s+)?!(\s+)?(DOCTYPE)/i', '<!$3', $text);
        $rand = sprintf('%1$s_%2$s_%1$s', '%', mt_rand(10000, 50000));
        $randQuote = preg_quote($rand, '~');
        $text = str_replace('<!', '< ' . $rand, $text);
        // bug fix for comments - in case you REALLY meant to type '< !--'
        $text = str_replace('< !--', '<    !--', $text);
        // bug fix for LOVE <3 (and other situations with '<' before a number)
        $text = preg_replace('#<([0-9])#', '&lt;$1', $text);
        while (preg_match(
            "~<((?!\s+" . $randQuote . ")/?[\w:]*)\s*([^>]*)>~",
            $text,
            $regex
        )) {
            $newText .= $tagQueue;
            $i = strpos($text, $regex[0]);
            $l = strlen($regex[0]);
            // clear the shifter
            $tagQueue = '';
            // Pop or Push
            if (isset($regex[1][0]) && '/' === $regex[1][0]) { // End Tag
                $tag = strtolower(substr($regex[1], 1));
                // if too many closing tags
                if ($stackSize <= 0) {
                    $tag = '';
                    // or close to be safe $tag = '/' . $tag;
                } elseif ($tagStack[$stackSize - 1] === $tag) {
                    // if stack top value = tag close value then pop
                    // found closing tag
                    $tag = '</' . $tag . '>'; // Close Tag
                    // Pop
                    array_pop($tagStack);
                    $stackSize--;
                } else { // closing tag not at top, search for it
                    for ($j = $stackSize - 1; $j >= 0; $j--) {
                        if ($tagStack[$j] === $tag) {
                            // add tag to tag queue
                            for ($k = $stackSize - 1; $k >= $j; $k--) {
                                $tagQueue .= '</' . array_pop($tagStack) . '>';
                                $stackSize--;
                            }
                            break;
                        }
                    }
                    $tag = '';
                }
            } else { // Begin Tag
                $tag = strtolower($regex[1]);
                // Tag Cleaning
                // If it's an empty tag "< >", do nothing
                /** @noinspection PhpStatementHasEmptyBodyInspection */
                if ('' === $tag
                    // ElseIf it's a known single-entity tag, but it doesn't close itself, do so
                    // $regex[2] .= '';
                    || in_array($tag, $single_tags_2)
                ) {
                    // do nothing
                } elseif (str_ends_with($regex[2], '/')) {
                    // ElseIf it presents itself as a self-closing tag...
                    // ----
                    // ...but it isn't a known single-entity self-closing tag,
                    // then don't let it be treated as such and
                    // immediately close it with a closing tag (the tag will encapsulate no text as a result)
                    if (!in_array($tag, $single_tags)) {
                        $regex[2] = trim(substr($regex[2], 0, -1)) . "></$tag";
                    }
                } elseif (in_array($tag, $single_tags)) {
                    // ElseIf it's a known single-entity tag, but it doesn't close itself, do so
                    $regex[2] .= '/';
                } else {
                    // Else it's not a single-entity tag
                    // ---------
                    // If the top of the stack is the same as the tag we want to push,
                    // close the previous tag
                    if ($stackSize > 0 && !in_array($tag, $nestable_tags)
                        && $tagStack[$stackSize - 1] === $tag
                    ) {
                        $tagQueue = '</' . array_pop($tagStack) . '>';
                        /** @noinspection PhpUnusedLocalVariableInspection */
                        $stackSize--;
                    }
                    $stackSize = array_push($tagStack, $tag);
                }
                // Attributes
                $attributes = $regex[2];
                if (!empty($attributes) && $attributes[0] !== '>') {
                    $attributes = ' ' . $attributes;
                }
                $tag = '<' . $tag . $attributes . '>';
                //If already queuing a close tag, then put this tag on, too
                if (!empty($tagQueue)) {
                    $tagQueue .= $tag;
                    $tag = '';
                }
            }
            $newText .= substr($text, 0, $i) . $tag;
            $text = substr($text, $i + $l);
        }
        // Clear Tag Queue
        $newText .= $tagQueue;
        // Add Remaining text
        $newText .= $text;
        unset($text); // freed memory
        // Empty Stack
        while ($x = array_pop($tagStack)) {
            $newText .= '</' . $x . '>'; // Add remaining tags to close
        }
        // fix for the bug with HTML comments
        $newText = str_replace("< $rand", "<!", $newText);
        $newText = str_replace("< !--", "<!--", $newText);

        return str_replace("<    !--", "< !--", $newText);
    }

    /**
     * Set cookie domain with .domain.ext for multi subdomain
     *
     * @param string $domain
     * @return ?string $return domain ( .domain.com )
     */
    public static function splitCrossDomain(string $domain): ?string
    {
        // make it domain lower
        $domain = strtolower(trim($domain));
        if ($domain === '') {
            return null;
        }
        preg_match(
            '~^(?:(?:[a-z]*:)?//)?([^?#/]+)(?:[#?/]|$)~',
            $domain,
            $match
        );
        $host = $match[1]??null;
        if (!$host) {
            return null;
        }
        if ($host === '127.0.0.1' || $host === 'localhost') {
            return $host;
        }
        if (Ip::version($host) !== null) {
            return $host;
        }
        // ascii domain
        if (idn_to_ascii($host) === false) {
            return null;
        }
        if (!str_contains($host, '.')) {
            return $host;
        }
        if (preg_match('~^[^.]+\.([^.]+\..+)$~', $host, $match)) {
            $host = $match[2].$match[3];
        }
        return '.' . $host;
    }

    /**
     * Normalize slug / url suffix
     *
     * @param string $slug
     * @return string
     */
    public static function normalizeSlug(string $slug): string
    {
        $slug = str_replace(
            array_keys(self::CONVERSION_TABLES),
            array_values(self::CONVERSION_TABLES),
            $slug
        );

        $slug = preg_replace('~[^a-z0-9\-_]~i', '-', trim($slug));
        $slug = preg_replace('~([\-_])+~', '$1', $slug);

        return trim($slug, '-_');
    }

    /**
     * Create unique slug
     *
     * @param string $slug
     * @param iterable $slugCollections
     * @return string
     */
    public static function uniqueSlug(string $slug, iterable $slugCollections): string
    {
        $separator = '-';
        $inc = 1;
        $slug = self::normalizeSlug($slug);
        $baseSlug = $slug;
        $slugCollections = is_array($slugCollections)
            ? $slugCollections
            : iterator_to_array($slugCollections);
        while (in_array($slug, $slugCollections)) {
            $slug = $baseSlug . $separator . $inc++;
        }
        return $slug;
    }

    /**
     * @param string $slug
     * @param callable $callable must be returning true for valid
     * @param int $maxIteration
     * @return string
     */
    public static function uniqueSlugCallback(
        string $slug,
        callable $callable,
        int $maxIteration = 4096
    ): string {
        $maxIteration = min(128, $maxIteration);
        // maximum set to 8192
        $maxIteration = max($maxIteration, 8192);
        $separator = '-';
        $inc = 1;
        $slug = self::normalizeSlug($slug);
        $baseSlug = $slug;
        while (!$callable($slug)) {
            if ($inc++ > $maxIteration) {
                throw new MaximumCallstackExceeded(
                    'Unique slug iteration exceeded'
                );
            }
            $slug = $baseSlug . $separator . $inc;
        }

        return $slug;
    }

    /**
     * Replace `\/` with directory separator
     *
     * @param string $string
     * @param bool $removeLastSeparator
     * @return string
     */
    public static function normalizeDirectorySeparator(
        string $string,
        bool $removeLastSeparator = false
    ): string {
        $string = preg_replace('~[/\\\]+~', DIRECTORY_SEPARATOR, $string);
        return $removeLastSeparator ? rtrim($string, DIRECTORY_SEPARATOR) : $string;
    }

    /**
     * Replace `\` with slash
     *
     * @param string $string
     * @param bool $removeLastSeparator
     * @return string
     */
    public static function normalizeUnixDirectorySeparator(
        string $string,
        bool $removeLastSeparator = false
    ): string {
        $string = preg_replace('~[/\\\]+~', '/', $string);
        return $removeLastSeparator ? rtrim($string, DIRECTORY_SEPARATOR) : $string;
    }

    /**
     * @param string $fileName
     * @param string $directory
     * @param bool $allowedSpace
     * @return ?string null if directory does not exist,
     *                      returning a full path for save.
     */
    public static function resolveFileDuplication(
        string $fileName,
        string $directory,
        bool $allowedSpace = false
    ): ?string {
        if (!is_dir($directory)) {
            return null;
        }

        $directory = self::normalizeDirectorySeparator($directory, true);
        $directory = realpath($directory) ?: $directory;
        $directory .= DIRECTORY_SEPARATOR;
        $paths = explode('.', $fileName);
        $extension = null;
        if (count($paths) > 1) {
            $extension = array_pop($paths);
        }
        $fileName = implode($paths);
        if ($extension && !preg_match('/^[a-z0-9A-Z_-]+$/', $extension)) {
            $extension = null;
        }
        $extension = $extension ?: null;
        $fileName = self::normalizeFileName($fileName, $allowedSpace);
        $c = 1;
        $filePath = $extension ? "$fileName.$extension" : $fileName;
        while (file_exists($directory . $filePath)) {
            $c++;
            $newFile = "$fileName-$c";
            $filePath = $extension ? "$newFile.$extension" : $newFile;
        }

        clearstatcache(true);

        return $directory . $filePath;
    }

    /**
     * Create a notation string to array with certain delimiter
     *
     * @param array $array
     * @param string $delimiter
     *
     * @return array
     */
    public static function notationToArray(array $array, string $delimiter = '.'): array
    {
        $result = [];
        foreach ($array as $notation => $value) {
            if (!is_string($notation)) {
                $result[$notation] = $value;
                continue;
            }
            $keys = explode($delimiter, $notation);
            $keys = array_reverse($keys);
            $lastVal = $value;
            foreach ($keys as $key) {
                $lastVal = [$key => $lastVal];
            }
            // merge result
            $result = array_merge_recursive($result, $lastVal);
        }

        return Consolidation::convertNotationValue($result);
    }
}
