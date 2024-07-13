<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Util\Filter;

use JsonSerializable;
use Stringable;
use function array_filter;
use function explode;
use function htmlspecialchars;
use function implode;
use function in_array;
use function intval;
use function is_bool;
use function is_iterable;
use function is_numeric;
use function is_scalar;
use function is_string;
use function json_encode;
use function preg_match;
use function sprintf;
use function strtolower;
use function trim;
use const ENT_QUOTES;
use const ENT_SUBSTITUTE;
use const JSON_UNESCAPED_SLASHES;

class HtmlAttributes
{
    final public const HTML_ATTRIBUTES = [
        "accept" => "accept",
        "accept-charset" => "accept-charset",
        "accesskey" => "accesskey",
        "action" => "action",
        "align" => "align",
        "alt" => "alt",
        "async" => "async",
        "autocomplete" => "autocomplete",
        "autofocus" => "autofocus",
        "autoplay" => "autoplay",
        "bgcolor" => "bgcolor",
        "border" => "border",
        "charset" => "charset",
        "checked" => "checked",
        "cite" => "cite",
        "class" => "class",
        "color" => "color",
        "cols" => "cols",
        "colspan" => "colspan",
        "content" => "content",
        "contenteditable" => "contenteditable",
        "controls" => "controls",
        "coords" => "coords",
        "data" => "data",
        // "data-*" => "data-*",
        "datetime" => "datetime",
        "default" => "default",
        "defer" => "defer",
        "dir" => "dir",
        "dirname" => "dirname",
        "disabled" => "disabled",
        "download" => "download",
        "draggable" => "draggable",
        "enctype" => "enctype",
        "for" => "for",
        "form" => "form",
        "formaction" => "formaction",
        "headers" => "headers",
        "height" => "height",
        "hidden" => "hidden",
        "high" => "high",
        "href" => "href",
        "hreflang" => "hreflang",
        "http-equiv" => "http-equiv",
        "id" => "id",
        "ismap" => "ismap",
        "kind" => "kind",
        "label" => "label",
        "lang" => "lang",
        "list" => "list",
        "loop" => "loop",
        "low" => "low",
        "max" => "max",
        "maxlength" => "maxlength",
        "media" => "media",
        "method" => "method",
        "min" => "min",
        "multiple" => "multiple",
        "muted" => "muted",
        "name" => "name",
        "novalidate" => "novalidate",
        "onabort" => "onabort",
        "onafterprint" => "onafterprint",
        "onbeforeprint" => "onbeforeprint",
        "onbeforeunload" => "onbeforeunload",
        "onblur" => "onblur",
        "oncanplay" => "oncanplay",
        "oncanplaythrough" => "oncanplaythrough",
        "onchange" => "onchange",
        "onclick" => "onclick",
        "oncontextmenu" => "oncontextmenu",
        "oncopy" => "oncopy",
        "oncuechange" => "oncuechange",
        "oncut" => "oncut",
        "ondblclick" => "ondblclick",
        "ondrag" => "ondrag",
        "ondragend" => "ondragend",
        "ondragenter" => "ondragenter",
        "ondragleave" => "ondragleave",
        "ondragover" => "ondragover",
        "ondragstart" => "ondragstart",
        "ondrop" => "ondrop",
        "ondurationchange" => "ondurationchange",
        "onemptied" => "onemptied",
        "onended" => "onended",
        "onerror" => "onerror",
        "onfocus" => "onfocus",
        "onhashchange" => "onhashchange",
        "oninput" => "oninput",
        "oninvalid" => "oninvalid",
        "onkeydown" => "onkeydown",
        "onkeypress" => "onkeypress",
        "onkeyup" => "onkeyup",
        "onload" => "onload",
        "onloadeddata" => "onloadeddata",
        "onloadedmetadata" => "onloadedmetadata",
        "onloadstart" => "onloadstart",
        "onmousedown" => "onmousedown",
        "onmousemove" => "onmousemove",
        "onmouseout" => "onmouseout",
        "onmouseover" => "onmouseover",
        "onmouseup" => "onmouseup",
        "onmousewheel" => "onmousewheel",
        "onoffline" => "onoffline",
        "ononline" => "ononline",
        "onpagehide" => "onpagehide",
        "onpageshow" => "onpageshow",
        "onpaste" => "onpaste",
        "onpause" => "onpause",
        "onplay" => "onplay",
        "onplaying" => "onplaying",
        "onpopstate" => "onpopstate",
        "onprogress" => "onprogress",
        "onratechange" => "onratechange",
        "onreset" => "onreset",
        "onresize" => "onresize",
        "onscroll" => "onscroll",
        "onsearch" => "onsearch",
        "onseeked" => "onseeked",
        "onseeking" => "onseeking",
        "onselect" => "onselect",
        "onstalled" => "onstalled",
        "onstorage" => "onstorage",
        "onsubmit" => "onsubmit",
        "onsuspend" => "onsuspend",
        "ontimeupdate" => "ontimeupdate",
        "ontoggle" => "ontoggle",
        "onunload" => "onunload",
        "onvolumechange" => "onvolumechange",
        "onwaiting" => "onwaiting",
        "onwheel" => "onwheel",
        "open" => "open",
        "optimum" => "optimum",
        "pattern" => "pattern",
        "placeholder" => "placeholder",
        "poster" => "poster",
        "preload" => "preload",
        "readonly" => "readonly",
        "rel" => "rel",
        "required" => "required",
        "reversed" => "reversed",
        "rows" => "rows",
        "rowspan" => "rowspan",
        "sandbox" => "sandbox",
        "scope" => "scope",
        "selected" => "selected",
        "shape" => "shape",
        "size" => "size",
        "sizes" => "sizes",
        "span" => "span",
        "spellcheck" => "spellcheck",
        "src" => "src",
        "srcdoc" => "srcdoc",
        "srclang" => "srclang",
        "srcset" => "srcset",
        "start" => "start",
        "step" => "step",
        "style" => "style",
        "tabindex" => "tabindex",
        "target" => "target",
        "title" => "title",
        "translate" => "translate",
        "type" => "type",
        "usemap" => "usemap",
        "value" => "value",
        "width" => "width",
        "wrap" => "wrap"
    ];

    public const ATTRIBUTES_BOOLEAN_TRUE_TYPES = [
        "checked" => "",
        "readonly" => "",
        "selected" => "",
        "disabled" => "",
        "hidden" => "",
        "autofocus" => "",
        "autoplay" => "",
        "controls" => "",
        "default" => "",
        "defer" => "",
        "multiple" => "",
        "muted" => "",
        "novalidate" => "",
        "open" => "",
        "required" => "",
        "reversed" => "",
        "async" => "",
        "ismap" => "",
        "loop" => "",
    ];

    public const ATTRIBUTES_NUMERIC_TYPES = [
        'width' => true,
        'height' => true,
        'size' => true,
        "high" => true,
    ];

    public const ATTRIBUTES_INTEGER_TYPES = [
        'rowspan' => true,
        'rows' => true,
        'cols' => true,
        'colspan' => true,
        'maxlength' => true,
        'minlength' => true,
    ];

    public const NO_ATTRIBUTES = [
        'class',
        'id'
    ];

    public static function buildAttributes(array $attributes) : string
    {
        return implode(' ', self::buildAttributesArray($attributes));
    }

    /**
     * Returning build attribute lists
     *
     * @param array $attributes
     * @return array<string>
     */
    public static function buildAttributesArray(array $attributes): array
    {
        $attr = [];
        foreach ($attributes as $key => $value) {
            if (!is_string($key) || ($key = trim($key)) === '') {
                continue;
            }
            // trim
            $key = trim($key);
            // attribute key does not allow whitespace
            // skip!
            if (preg_match('~\s~i', $key)) {
                continue;
            }
            $lowerKey = strtolower($key);
            if (is_bool($value)) {
                if (isset(self::ATTRIBUTES_BOOLEAN_TRUE_TYPES[$lowerKey])) {
                    // skip if false
                    if (!$value) {
                        continue;
                    }
                    $value = self::ATTRIBUTES_BOOLEAN_TRUE_TYPES[$lowerKey];
                } else {
                    $value = $value ? 'true' : 'false';
                }
            } elseif ($value instanceof Stringable
                || is_scalar($value)
            ) {
                $value = (string) $value;
            } /** @noinspection PhpConditionAlreadyCheckedInspection */ elseif ($value instanceof JsonSerializable) {
                $value = json_encode($value, JSON_UNESCAPED_SLASHES);
            } elseif ($value === null) {
                // null is true
                $value = self::ATTRIBUTES_BOOLEAN_TRUE_TYPES[$lowerKey] ?? '';
            }
            if ($lowerKey === 'class') {
                $values = [];
                $value = is_string($value)
                    ? explode(' ', $value)
                    : (is_iterable($value) ? $value : []);
                foreach ($value as $val) {
                    if (!is_string($val)) {
                        continue;
                    }
                    $values[] = DataNormalizer::normalizeHtmlClass($val);
                }
                $values = array_filter($values);
                $value = implode(' ', $values);
            }

            if (!is_string($value)) {
                continue;
            }

            // filter!
            if (isset(self::ATTRIBUTES_NUMERIC_TYPES[$lowerKey])) {
                $value = is_numeric($value) ? $value : '';
            } elseif (isset(self::ATTRIBUTES_INTEGER_TYPES[$lowerKey])) {
                if (is_numeric($value)) {
                    $value = (string) intval($value);
                } else {
                    $value = '';
                }
            }

            $key = self::HTML_ATTRIBUTES[$lowerKey]??$key;
            if ($value === '') {
                if (in_array($key, self::NO_ATTRIBUTES)) {
                    continue;
                }
                $attr[$key] = $key;
            } else {
                if ($key === 'id') {
                    $value = DataNormalizer::normalizeHtmlClass($value);
                }
                $value = htmlspecialchars(
                    $value,
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    'UTF-8'
                );
                $attr[$key] = sprintf('%s="%s"', $key, $value);
            }
        }

        return $attr;
    }
}
