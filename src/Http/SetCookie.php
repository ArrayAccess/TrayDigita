<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Http;

use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\InvalidArgumentException;
use DateTimeInterface;
use Psr\Http\Message\ResponseInterface;
use Stringable;
use function gmdate;
use function in_array;
use function is_int;
use function preg_match;
use function rawurlencode;
use function sprintf;
use function strtolower;
use function trim;

class SetCookie implements Stringable
{
    private string $name;
    private string $value;
    private int $expiresAt;
    private string $path;
    private string $domain;
    private bool $secure;
    private bool $httpOnly;
    private string $sameSite;
    public function __construct(
        string $name,
        string $value,
        int|DateTimeInterface $expiresAt = 0,
        string $path = '',
        string $domain = '',
        bool $secure = false,
        bool $httpOnly = false,
        string $sameSite = ''
    ) {
        $this->assertValidName($name);
        $this->assertValidSameSite($sameSite, $secure);
        $this->name = $name;
        $this->value = $value;
        $this->expiresAt = is_int($expiresAt) ? $expiresAt : $expiresAt->getTimestamp();
        $this->path = $path;
        $this->domain = trim($domain);
        $this->secure = $secure;
        $this->httpOnly = $httpOnly;
        $this->sameSite = strtolower(trim($sameSite));
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getExpiresAt(): int
    {
        return $this->expiresAt;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getDomain(): ?string
    {
        return $this->domain;
    }

    public function isSecure(): bool
    {
        return $this->secure;
    }

    public function isHttpOnly(): bool
    {
        return $this->httpOnly;
    }

    public function getSameSite(): string
    {
        return $this->sameSite;
    }

    private function assertValidName(string $name): void
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException(
                'Cookie name is empty or contain whitespace only'
            );
        }
        if (preg_match("/[^!#$%&'*+\-.^_`|~0-9a-zA-Z]/", $name)) {
            throw new InvalidArgumentException(
                'Cookie contain invalid name'
            );
        }
    }

    public function assertValidSameSite(string $sameSite, bool $secure): void
    {
        $sameSite = strtolower(trim($sameSite));
        if (!in_array($sameSite, ['', 'lax', 'strict', 'none'])) {
            throw new InvalidArgumentException(
                'The same site attribute must be "lax", "strict", "none" or ""'
            );
        }

        if ($sameSite === 'none' && !$secure) {
            throw new InvalidArgumentException(
                'The same site attribute "none" only allowed when secure is set to true'
            );
        }
    }

    public function toHeaderValue() : string
    {
        $header = sprintf(
            '%s=%s',
            $this->name,
            rawurlencode($this->getValue())
        );

        if ($this->expiresAt !== 0) {
            $header .= sprintf(
                '; expires=%s',
                gmdate('D, d M Y H:i:s T', $this->getExpiresAt())
            );
        }

        if (trim($this->path) !== '') {
            $header .= sprintf('; path=%s', rawurlencode($this->path));
        }

        if ($this->domain !== '') {
            $header .= sprintf('; domain=%s', rawurlencode($this->domain));
        }

        if ($this->secure) {
            $header .= '; secure';
        }

        if ($this->httpOnly) {
            $header .= '; httponly';
        }

        if ($this->sameSite !== '') {
            $header .= sprintf('; samesite=%s', $this->sameSite);
        }

        return $header;
    }

    public function appendToResponse(ResponseInterface $response) : ResponseInterface
    {
        return $response->withAddedHeader(
            'Set-Cookie',
            $this->toHeaderValue()
        );
    }

    public function __toString(): string
    {
        return $this->toHeaderValue();
    }
}
