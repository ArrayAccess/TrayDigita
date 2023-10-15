# Code Translation

Translation prefer using gettext (po, mo, pot) adapter.

## Translation Keywords

Please sort order by context argument

> Single Translation

```php
$translator->translate($singular, ?$domain, ?$context)
```

```txt
translate:1,3c
__
translate
trans
```

> Plural Translation

```php
$translator->translatePlural($singular, $plural, $number, ?$domain, ?$context)
```

```txt
translatePlural:1,2,5c
translatePlural:1,2
transN:1,2
_n:1,2
```

> Single Context Translation

```php
$translator->translateContext($singular, $context, ?$domain)
```

```txt
translateContext:1,2c
transX:1,2c
_x:1,2c
```

> Plural Context Translation


```php
$translator->translatePluralContext($singular, $plural, $domain, $context, ?$domain)
```

```txt
translatePluralContext:1,2,4c
transNX:1,2,4c
_nx:1,2,4c
```

## Poedit Keywords

Generate translation can use [poedit](https://poedit.net/)

```txt
"X-Poedit-KeywordsList: translate:1,3c;__;translate;trans;"
"translatePlural:1,2,5c;translatePlural:1,2;transN:1,2;_n:1,2;"
"translateContext:1,2c;transX:1,2c;_x:1,2c;"
"translatePluralContext:1,2,4c;transNX:1,2,4c;_nx:1,2,4c\n"
```

## Poedit custom extrator for `twig` file

- Go to `preference` > `extractor`
- Select add (**+**)
    - Language: `Twig`
    - List of extension: `*.twig`
    - Command to extract translation: `xgettext --language=Python --add-comments=TRANSLATORS --force-po -o %o %C %K %F`
    - An item in keyword list: `-k%k`
    - An item in input files list: `%f`
    - Source code charset: `--from-code=%c`
- Save
