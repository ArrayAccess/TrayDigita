<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Util\Whois;

use Serializable;
use function serialize;
use function sha1;
use function strtolower;
use function trim;
use function unserialize;

final class WhoisExtensionCache implements Serializable
{
    protected string $extension;

    protected string $hash;

    protected string|false $server;

    private ?string $currentHash = null;

    public function __construct(
        string $extension,
        string|false $server
    ) {
        $this->extension = strtolower(trim($extension));
        $this->server = $server;
        $this->hash   = sha1($extension . ($server?:''));
    }

    public function getExtension(): string
    {
        return $this->extension;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function getServer(): false|string
    {
        return $this->server;
    }

    public function isValid() : bool
    {
        if (!$this->currentHash) {
            $this->currentHash = sha1($this->extension . ($this->server?:''));
        }
        return $this->hash === $this->currentHash;
    }

    public function serialize() : string
    {
        return serialize($this->__serialize());
    }

    public function unserialize(string $data): void
    {
        $this->__unserialize(unserialize($data));
    }

    public function __serialize(): array
    {
        return [
            'extension' => $this->extension,
            'server' => $this->server,
            'hash' => $this->hash
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->extension = $data['extension'];
        $this->server = $data['server'];
        $this->hash = $data['hash'];
    }
}
