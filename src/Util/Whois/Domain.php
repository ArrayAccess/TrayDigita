<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Util\Whois;

use ArrayAccess\TrayDigita\Util\Whois\Abstracts\AddressStorage;
use function array_pop;
use function explode;
use function in_array;
use function preg_match;
use function strlen;
use function strtolower;

final class Domain extends AddressStorage
{
    /**
     * Regex Global Domain
     */
    const REGEXP_GLOBAL_IDNA = '#[^a-z0-9\-\P{Latin}\P{Hebrew}\P{Greek}'
    .'\P{Cyrillic}\P{Han}\P{Arabic}\P{Gujarati}\P{Armenian}\P{Hiragana}\P{Thai}]#';
    const REGEXP_IDNA_PATTERN = '#[^\x20-\x7f]#';

    const REGEXP_VALID_ASCII_PATTERN = '#^[a-z0-9]+(?:(?:[a-z0-9-]+)?[a-z0-9]$)?'.
    '(?:\.[a-z0-9]+(?:(?:[a-z0-9-]+)?[a-z0-9]$)?)*$#';

    /**
     * List for regex test
     *
     * @var array
     */
    const GLOBAL_IDN = [
        "com",
        "net"
    ];


    private string|null|false $asciiDomain = null;

    private string|null|false $utf8DomainName = null;

    public function isValid(): bool
    {
        return $this->getAsciiName() !== false;
    }

    public function getUtf8Name(): false|string
    {
        return $this->getAsciiName() !== false ? $this->utf8DomainName : false;
    }

    /**
     * Filer Domain Name
     * @return false|string return ascii domain name of false if failure
     */
    public function getAsciiName(): false|string
    {
        if ($this->asciiDomain !== null) {
            return $this->asciiDomain;
        }
        $this->asciiDomain = false;
        $this->utf8DomainName = false;
        if ($this->address === ''
            || strlen($this->address) > 255
            || strlen($this->address) < 3 // a.b < 3
            || ! str_contains($this->address, '.')
            || preg_match(self::REGEXP_IDNA_PATTERN, $this->address)
        ) {
            return false;
        }

        $domainName = strtolower($this->address);
        $arrayDomain = explode('.', $domainName);
        foreach ($arrayDomain as $subName) {
            // The maximum length of each label is 63 characters
            if (strlen($subName) > 63) {
                return false;
            }
        }
        $extension = array_pop($arrayDomain);
        array_pop($arrayDomain);
        if (in_array($extension, self::GLOBAL_IDN)
            && preg_match(self::REGEXP_GLOBAL_IDNA, $domainName)
        ) {
            return false;
        }

        $domainName = idn_to_ascii($domainName);
        if (strlen($domainName) > 255
            || !preg_match(self::REGEXP_VALID_ASCII_PATTERN, $domainName)
        ) {
            return false;
        }
        $this->asciiDomain = $domainName;
        $this->utf8DomainName = idn_to_utf8($this->asciiDomain);
        return $this->asciiDomain;
    }

    public function __serialize(): array
    {
        return [
            'domainName' => $this->getAddress()
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->address = $data['domainName'];
        $this->asciiDomain = null;
        $this->utf8DomainName = null;
    }
}
