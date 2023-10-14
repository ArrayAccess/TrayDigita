<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Auth\Roles;

use ArrayAccess\TrayDigita\Auth\Roles\Interfaces\CapabilityInterface;
use ArrayAccess\TrayDigita\Auth\Roles\Interfaces\RoleInterface;
use ArrayAccess\TrayDigita\Util\Filter\Consolidation;
use ArrayAccess\TrayDigita\Util\Filter\IterableHelper;
use Countable;
use Serializable;
use Stringable;
use function is_object;
use function is_string;
use function serialize;
use function sprintf;
use function strtolower;
use function unserialize;

abstract class AbstractCapability implements CapabilityInterface, Serializable, Stringable, Countable
{
    /**
     * @var array<string, RoleInterface>
     */
    private array $roles = [];

    protected string $identity = '';

    protected string $name = '';

    protected ?string $description = null;

    protected array $initRoles = [];

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
                'Capability %s',
                Consolidation::classShortName($this::class)
            );
        }
        foreach ($this->initRoles as $role => $item) {
            if ($item instanceof RoleInterface) {
                $role = $item;
            } else {
                $role = is_string($role) ? $role : $item;
            }
            if (is_string($role) || $role instanceof RoleInterface) {
                $this->add($role);
            }
        }
    }

    protected function onConstruct()
    {
    }

    public function identify(RoleInterface|string $role): string
    {
        return is_string($role) ? $role : $role->getRole();
    }

    public function add(RoleInterface|string $role): ?RoleInterface
    {
        $roleId = $this->identify($role);

        if (isset($this->roles[$roleId])) {
            if (is_object($role)
                && (
                    $this->roles[$roleId]::class === InstanceRole::class
                    || $role::class !== InstanceRole::class
                )
            ) {
                $this->roles[$roleId] = $role;
            }
        } else {
            $this->roles[$roleId] = $role instanceof RoleInterface
                ? $role
                : InstanceRole::create($role, $role);
        }

        return $this->roles[$roleId];
    }

    public function remove(RoleInterface|string $role): void
    {
        unset($this->roles[$this->identify($role)]);
    }

    public function has(RoleInterface|string $role): bool
    {
        return isset($this->roles[$this->identify($role)]);
    }

    /**
     * @return array<string, RoleInterface>
     */
    public function getRoles(): array
    {
        return $this->roles;
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

    public function serialize(): string
    {
        return serialize($this->__serialize());
    }

    public function unserialize(string $data): void
    {
        $this->__unserialize(unserialize($data));
    }

    public function __toString(): string
    {
        return $this->getIdentity();
    }

    public function __serialize(): array
    {
        return [
            'identity' => $this->getIdentity(),
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'roles' => IterableHelper::all($this->getRoles())
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->identity = $data['identity'];
        $this->name = $data['name'];
        $this->description = $data['description'];
        foreach ($data['roles'] as $role) {
            $this->roles[$role] = $role;
        }
    }

    public function count(): int
    {
        return count($this->getRoles());
    }
}
