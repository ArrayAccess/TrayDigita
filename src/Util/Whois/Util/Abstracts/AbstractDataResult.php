<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Util\Whois\Util\Abstracts;

abstract class AbstractDataResult
{
    const TYPE_IP = 'IP';
    const TYPE_DOMAIN = 'DOMAIN';

    protected string $type;

    protected string $address;

    protected ?string $provider;

    protected array $data = [];

    public function __construct(
        bool $isIP,
        string $address,
        ?string $registrar,
        array $data
    ) {
        $this->address = $address;
        $this->provider = $registrar;
        $this->type = $isIP ? self::TYPE_IP : self::TYPE_DOMAIN;
        $this->data = $data;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }
}
