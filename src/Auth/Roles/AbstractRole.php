<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Auth\Roles;

use ArrayAccess\TrayDigita\Auth\Roles\Interfaces\RoleInterface;
use ArrayAccess\TrayDigita\Util\Filter\Consolidation;
use function serialize;
use function sprintf;
use function strtolower;
use function unserialize;

class AbstractRole implements RoleInterface
{
    protected string $identity = '';

    protected string $name = '';

    protected ?string $description = null;

    public function __construct()
    {
        $this->onConstruct();

        if ($this->identity === '') {
            $this->identity = strtolower(
                Consolidation::classShortName($this::class)
            );
        }

        if ($this->name === '') {
            $this->name = sprintf(
                'Role %s',
                Consolidation::classShortName($this::class)
            );
        }
    }

    protected function onConstruct()
    {
        // pass
    }

    public function getRole(): string
    {
        return $this->identity;
    }

    public function getIdentity(): string
    {
        return $this->identity;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function serialize(): ?string
    {
        return serialize($this->__serialize());
    }

    public function unserialize(string $data): void
    {
        $this->unserialize(unserialize($data));
    }

    public function __serialize(): array
    {
        return [
            'identity' => $this->identity,
            'name' => $this->name,
            'description' => $this->description,
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->identity = $data['identity'];
        $this->name = $data['name'];
        $this->description = $data['description'];
    }

    public function __toString(): string
    {
        return $this->getIdentity();
    }
}
