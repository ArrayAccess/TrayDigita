<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\L10n\PoMo\Metadata;

class Attributes
{
    const MESSAGE_ID = 'msgid';
    const PLURAL_ID = 'msgid_plural';
    const TRANSLATION = 'msgstr';
    const CONTEXT = 'msgctx';
    const COMMENTS = '#';
    const COMMENTED_TRANSLATIONS = '#~';
    const EXTRACTED_COMMENTS = '#.';
    const FLAGS = '#,';
    const REFERENCES = '#:';

    const FLAG_LISTS = [
        'fuzzy',
        'c-format',
        'no-c-format',
        'objc-format',
        'no-objc-format',
        'python-format',
        'no-python-format',
        'python-brace-format',
        'no-python-brace-format',
        'java-format',
        'no-java-format',
        'java-printf-format',
        'no-java-printf-format',
        'csharp-format',
        'no-csharp-format',
        'javascript-format',
        'no-javascript-format',
        'scheme-format',
        'no-scheme-format',
        'lisp-format',
        'no-lisp-format',
        'elisp-format',
        'no-elisp-format',
        'librep-format',
        'no-librep-format',
        'ruby-format',
        'no-ruby-format',
        'sh-format',
        'no-sh-format',
        'awk-format',
        'no-awk-format',
        'lua-format',
        'no-lua-format',
        'object-pascal-format',
        'no-object-pascal-format',
        'smalltalk-format',
        'no-smalltalk-format',
        'qt-format',
        'no-qt-format',
        'qt-plural-format',
        'no-qt-plural-format',
        'kde-format',
        'no-kde-format',
        'boost-format',
        'no-boost-format',
        'tcl-format',
        'no-tcl-format',
        'perl-format',
        'no-perl-format',
        'perl-brace-format',
        'no-perl-brace-format',
        'php-format',
        'no-php-format',
        'gcc-internal-format',
        'no-gcc-internal-format',
        'gfc-internal-format',
        'no-gfc-internal-format',
        'ycp-format',
        'no-ycp-format',
        'range:',
    ];
}
