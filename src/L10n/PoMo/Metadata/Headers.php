<?php
/** @noinspection PhpUndefinedClassInspection */
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\L10n\PoMo\Metadata;

use ArrayAccess;
use ArrayIterator;
use ArrayAccess\TrayDigita\L10n\Languages\Locale;
use ArrayAccess\TrayDigita\L10n\PoMo\Helper\PluralParser;
use Countable;
use IteratorAggregate;
use Traversable;
use function array_keys;
use function array_merge;
use function array_values;
use function implode;
use function is_numeric;
use function is_scalar;
use function is_string;
use function preg_replace;
use function sprintf;
use function str_replace;
use function strtolower;
use function trim;

final class Headers implements ArrayAccess, Countable, IteratorAggregate
{
    public const HEADER_LANGUAGE = 'Language';
    public const HEADER_PLURAL = PluralForm::HEADER_KEY;
    public const HEADER_DOMAIN = 'X-Domain';

    /**
     * @var array<string,string>
     */
    const PRESERVE_HEADER_NAME = [
        'project-id-version' => 'Project-Id-Version',
        'last-translator' => 'Last-Translator',
        'language-team' => 'Language-Team',
        'language' => self::HEADER_LANGUAGE,
        'mime-version' => 'MIME-Version',
        'content-type' => 'Content-Type',
        'content-transfer-encoding' => 'Content-Transfer-Encoding',
        PluralForm::HEADER_KEY_LOWERCASE => self::HEADER_PLURAL,
    ];

    const DEFAULT_HEADERS = [
        'Project-Id-Version' => '',
        'Last-Translator' => '',
        'Language-Team' => '',
        self::HEADER_LANGUAGE => 'en',
        PluralForm::HEADER_KEY => PluralForm::DEFAULT_PLURAL_FORMS,
        'MIME-Version' => '1.0'
    ];

    protected array $headerNames = [
        'project-id-version' => 'Project-Id-Version',
        'last-translator' => 'Last-Translator',
        'language-team' => 'Language-Team',
        'language' => self::HEADER_LANGUAGE,
        'plural-forms' => 'Plural-Forms',
        'mime-version' => 'MIME-Version',
    ];

    private ?PluralForm $pluralForm = null;

    /**
     * @var array<string,string>
     */
    protected array $headers = self::DEFAULT_HEADERS;

    /**
     * @param array<string,string> $headers
     */
    public function __construct(array $headers = [])
    {
        foreach ($headers as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            $this->add($key, $value);
        }
    }

    /**
     * @return ?string
     */
    public function getLanguage() :?string
    {
        return $this->getHeader(self::HEADER_LANGUAGE);
    }

    public function getDomain() : ?string
    {
        return $this->getHeader(self::HEADER_DOMAIN);
    }

    /**
     * @param string $domain
     *
     * @return $this
     */
    public function setDomain(string $domain) : self
    {
        return $this->add(self::HEADER_DOMAIN, $domain);
    }

    /**
     * @return Headers
     */
    public function useDefaultPluralForm() : Headers
    {
        $originalLanguage = $this->getLanguage();
        $language = $originalLanguage ? Locale::getInfo($originalLanguage) : null;
        if (!$language) {
            return $this;
        }
        if ($originalLanguage === $language['id']
            && $language['expression'] === $this->getPluralForm()->getExpression()
        ) {
            return $this;
        }

        $this->setLanguage($language['id']);
        $this->setPluralForms($language['count'], $language['expression']);
        return $this;
    }

    /**
     * @param string $language
     *
     * @return $this
     */
    public function setLanguage(string $language) : self
    {
        return $this->add(self::HEADER_LANGUAGE, $language);
    }

    /**
     * @param int $count
     * @param string $pluralExpression
     *
     * @return $this
     */
    public function setPluralForms(int $count, string $pluralExpression) : self
    {
        $this->pluralForm = new PluralForm($count, $pluralExpression);
        $this->headerNames[strtolower(PluralForm::HEADER_KEY)] = PluralForm::HEADER_KEY;
        $this->headers[PluralForm::HEADER_KEY] = $this->pluralForm->toPluralFormHeaderValue();
        return $this;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function has(string $name) : bool
    {
        $name = strtolower(trim($name));
        return isset($this->headerNames[$name]);
    }

    protected function normalizeKey(string $name) : string
    {
        $name = trim($name);
        $name = preg_replace('~[ ]~', ' ', $name);
        if (isset(self::PRESERVE_HEADER_NAME[strtolower($name)])) {
            return self::PRESERVE_HEADER_NAME[strtolower($name)];
        }
        return $name;
    }

    /**
     * @param scalar $value
     *
     * @return string
     */
    private function normalizeValue(float|bool|int|string $value) : string
    {
        $replacer = [
            "\t" => ' ',
            "\r" => '',
            "\n" => ' ',
            "\v" => '',
            "\f" => '',
            "\e" => '',
            "\x08" => '',
            "\x07" => '',
        ];
        return str_replace(array_keys($replacer), array_values($replacer), (string) $value);
    }

    /**
     * @return PluralForm
     */
    public function getPluralForm() : PluralForm
    {

        if (!$this->pluralForm) {
            $locales = Locale::getInfo($this->getLanguage());
            $this->pluralForm = $locales ? new PluralForm(
                $locales['count'],
                $locales['expression']
            ) : new PluralForm(2, 'n != 1');
        }

        return $this->pluralForm;
    }

    /**
     * @param string $name
     *
     * @return ?string
     */
    public function getHeader(string $name) : ?string
    {
        $name = trim($name);
        if (isset($this->headers[$name])) {
            return $this->headers[$name];
        }
        $name = strtolower($name);
        if (!isset($this->headerNames[$name])) {
            return null;
        }

        $name = $this->headerNames[$name];
        return $this->headers[$name];
    }

    /**
     * @param string $name
     * @param $value
     *
     * @return Headers
     */
    public function add(string $name, $value) : self
    {
        // header does not accept numeric only
        if (!is_scalar($value) || is_numeric(trim($name))) {
            return $this;
        }
        $name = trim($name);
        if ($name === '') {
            return $this;
        }

        $normalized = $this->normalizeKey($name);
        $value = $this->normalizeValue($value);
        $name = strtolower($name);
        $this->headerNames[$name] = $normalized;
        if ($normalized === PluralForm::HEADER_KEY) {
            $definitions = PluralParser::getPluralFormDefinitions($value);
            $language    = $this->getLanguage();
            if (!$definitions && $language) {
                $info = Locale::getInfo($language);
                if (!empty($info)) {
                    $this->pluralForm = new PluralForm($info['count'], $info['expression']);
                } else {
                    $this->pluralForm = new PluralForm(
                        PluralForm::DEFAULT_PLURAL_COUNT,
                        PluralForm::DEFAULT_EXPRESSION
                    );
                }
            } else {
                $this->pluralForm = new PluralForm(
                    $definitions[0]??PluralForm::DEFAULT_PLURAL_COUNT,
                    $definitions[1]??PluralForm::DEFAULT_EXPRESSION
                );
            }
            $this->headers[$normalized] = $this->pluralForm->toPluralFormHeaderValue();
        } else {
            $this->headers[$normalized] = $value;
        }
        return $this;
    }

    public function remove(string $name) : self
    {
        $name = strtolower(trim($name));
        if ($name === '') {
            return $this;
        }
        if (!isset($this->headerNames[$name])) {
            return $this;
        }
        $name = $this->headerNames[$name];
        unset($this->headers[$name]);
        foreach ($this->headerNames as $key => $headerName) {
            if ($name === $headerName) {
                unset($this->headers[$key]);
            }
        }
        if ($name === 'Plural-Forms') {
            $this->pluralForm = null;
        }
        return $this;
    }

    /**
     * @return array<string,string>
     */
    public function toArray() : array
    {
        return array_merge(self::DEFAULT_HEADERS, $this->headers);
    }

    public function toPoHeader() : string
    {
        $headers = $this->toArray();
        $data = [];
        foreach ($headers as $key => $header) {
            $data[] = sprintf(
                '"%s: %s\n"',
                $key,
                $header
            );
        }
        return implode("\n", $data);
    }

    public function offsetExists($offset) : bool
    {
        return is_string($offset) && $this->has($offset);
    }

    #[ReturnTypeWillChange] public function offsetGet($offset) : ?string
    {
        return is_string($offset)
            ? $this->getHeader($offset)
            : null;
    }

    public function offsetSet($offset, $value) : void
    {
        $this->add($offset, $value);
    }

    public function offsetUnset($offset) : void
    {
        if (!is_string($offset)) {
            return;
        }
        $this->remove($offset);
    }

    public function count() : int
    {
        return count($this->headers);
    }

    public function getIterator() : Traversable
    {
        return new ArrayIterator($this->headers);
    }
}
