<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Auth\Roles;

use ArrayAccess\TrayDigita\Auth\Roles\Interfaces\CapabilityInterface;
use ArrayAccess\TrayDigita\Auth\Roles\Interfaces\PermissionInterface;
use ArrayAccess\TrayDigita\Auth\Roles\Interfaces\RoleInterface;
use ArrayAccess\TrayDigita\Auth\Roles\Interfaces\UserRoleInterface;
use ArrayAccess\TrayDigita\Container\Interfaces\ContainerAllocatorInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerAllocatorInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Traits\Container\ContainerAllocatorTrait;
use ArrayAccess\TrayDigita\Traits\Manager\ManagerAllocatorTrait;
use ArrayAccess\TrayDigita\Util\Filter\Consolidation;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use Psr\Container\ContainerInterface;
use function is_bool;
use function is_string;

final class Permission implements
    PermissionInterface,
    ManagerAllocatorInterface,
    ContainerAllocatorInterface
{
    use ManagerAllocatorTrait,
        ContainerAllocatorTrait;

    /**
     * @var array<string, CapabilityInterface>
     */
    protected array $capabilities = [];

    public function __construct(
        ?ContainerInterface $container = null,
        ?ManagerInterface $manager = null
    ) {
        if ($container) {
            $this->setContainer($container);
            $manager ??= ContainerHelper::use(
                ManagerInterface::class,
                $container
            );
        }
        if ($manager) {
            $this->setManager($manager);
        }

        $this->add(new SuperAdminCapability());
        $this->onConstruct();
    }

    protected function onConstruct()
    {
        // pass override
    }

    public function getOrCreate(
        string $identity,
        string $name,
        ?string $description = null
    ): CapabilityInterface {
        if (isset($this->capabilities[$identity])) {
            return $this->capabilities[$identity];
        }
        return InstanceCapability::create(
            $identity,
            $name,
            $description
        );
    }

    public function add(CapabilityInterface $capability): CapabilityInterface
    {
        $currentCapability = $this->capabilities[$this->identify($capability)]??null;
        return $currentCapability??(
        $this->capabilities[$this->identify($capability)] = $capability
        );
    }

    public function replace(CapabilityInterface $capability): void
    {
        if ($this->capabilities[$this->identify($capability)]??null instanceof SuperAdminCapability) {
            return;
        }
        $this->capabilities[$this->identify($capability)] = $capability;
    }

    public function has(string|CapabilityInterface $identity): bool
    {
        return isset($this->capabilities[$this->identify($identity)]);
    }

    public function get(string|CapabilityInterface $identity): ?CapabilityInterface
    {
        return $this->capabilities[$this->identify($identity)]??null;
    }

    public function remove(string|CapabilityInterface $identity): ?CapabilityInterface
    {
        $identity = $this->identify($identity);
        $old = $this->capabilities[$identity]??null;
        unset($this->capabilities[$identity]);
        return $old;
    }

    public function identify(string|CapabilityInterface $capability): string
    {
        return is_string($capability)
            ? $capability
            : $capability->getIdentity();
    }

    /**
     * @return array<string, CapabilityInterface>
     */
    public function getCapabilities(): array
    {
        return $this->capabilities;
    }

    public function permitted(
        RoleInterface|UserRoleInterface $role,
        CapabilityInterface|string $capability
    ): bool {
        $role = $role instanceof UserRoleInterface
            ? $role->getObjectRole()
            : $role;
        $capability = is_string($capability)
            ? $this->get($capability)
            : $capability;
        $permitted = $capability?->has($role)??false;
        $eventManagerValue = $this
            ->getManager()
            ?->dispatch(
                'permission.permitted',
                $permitted,
                $role,
                $capability,
                $this
            );
        $permitted = is_bool($eventManagerValue) ? $eventManagerValue : $permitted;
        return $this->doPermit(
            $permitted,
            $role,
            $capability
        );
    }

    /**
     * Doing permissive if object class is on children
     *
     * @param bool $allowed
     * @param RoleInterface $role
     * @param ?AbstractCapability $capability
     * @return bool
     * @noinspection PhpUnusedParameterInspection
     */
    protected function doPermit(
        bool $allowed,
        RoleInterface $role,
        ?CapabilityInterface $capability
    ) : bool {
        return $allowed;
    }

    public function __debugInfo(): ?array
    {
        return Consolidation::debugInfo($this, excludeKeys: ['capabilities']);
    }
}
