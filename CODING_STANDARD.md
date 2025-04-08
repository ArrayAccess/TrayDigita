# Coding Standard

## PHP

- Follow the [PSR2 Coding Standard](https://www.php-fig.org/psr/psr-2/)
- Using FQDN & import the :
  - `function`
  - `class/trait/interface`
  - `constant`
- Reduce unused variable name
- Php Code Sniffer Config [phpcs.xml](phpcs.xml)
- Always use validation about:
  - `variables`
  - `array[index]`
  - and code existences
- Do not use private injection to core files (`src/`) outside `src/` / core files
- Do not use global variables using register globals ```$GLOBALS/global $var```, the global object must be used static object variables.
- See the **[Code Of Conduct](CODE_OF_CONDUCT.md)**

## Performance & Security Guide

- Focus to performance & security of the codes. Avoid to use dangerous functions & direct activity eg: `eval/passthru/exec ...etc`. 
- Does not perform direct file uploads without mime-type validation.
- Renaming extension for dangerous extension for uploaded files, eg: `.php`, `.js` etc. to safe extension like `txt` eg : `file.js.txt`

## Translation

Core translation always use `default` / `TranslatorInterface:DEFAULT_DOMAIN` or leave empty on text domain. And always use `context` as determine core translation

### Singular

```php
  $translator->translateContext('singular', 'context_name');
```

### Plural

```php
  $translator->translatePluralContext('singular', 'plural', 'integer<number>' , 'context_name');
```

### Trait Usage

```php
use ArrayAccess\TrayDigita\Container\Interfaces\ContainerIndicateInterface;
use ArrayAccess\TrayDigita\Traits\Service\TranslatorTrait;
use Psr\Container\ContainerInterface;

class someClass implements ContainerIndicateInterface
{
    use TranslatorTrait;

    protected ?ContainerInterface $container;

    public function __construct(?ContainerInterface $container)
    {
        $this->container = $container;
    }

    protected function getContainer() : ?ContainerInterface
    {
        return $this->container;
    }
    
    public function doingJobTranslate()
    {
        $this->translateContext('original', 'core_context');
        $this->translatePluralContext('original', 'plural', 2, 'core_context');
    }
    ....
}
```
