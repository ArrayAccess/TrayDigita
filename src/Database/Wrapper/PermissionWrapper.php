<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database\Wrapper;

use ArrayAccess\TrayDigita\Auth\Roles\Interfaces\CapabilityInterface;
use ArrayAccess\TrayDigita\Auth\Roles\Interfaces\PermissionInterface;
use ArrayAccess\TrayDigita\Auth\Roles\Interfaces\RoleInterface;
use ArrayAccess\TrayDigita\Auth\Roles\Interfaces\UserRoleInterface;
use ArrayAccess\TrayDigita\Auth\Roles\Permission;
use ArrayAccess\TrayDigita\Database\Connection;
use ArrayAccess\TrayDigita\Database\Entities\Interfaces\CapabilityEntityFactoryInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Util\Filter\Consolidation;
use ArrayAccess\TrayDigita\Util\Filter\IterableHelper;
use Psr\Container\ContainerInterface;
use Throwable;
use function array_filter;
use function count;
use function strtolower;

class PermissionWrapper implements PermissionInterface
{
    protected ?CapabilityEntityFactoryInterface $capabilityEntityFactory = null;

    private array $cachedInvalidEntities = [];

    private ?array $availableIdentities = null;

    private PermissionInterface $permission;

    public function __construct(
        protected Connection $connection,
        ?ContainerInterface $container = null,
        ?ManagerInterface $manager = null,
        ?PermissionInterface $permission = null
    ) {
        $this->permission = $permission??new Permission(
            $container,
            $manager
        );
        if (!$this->capabilityEntityFactory
            && $container?->has(CapabilityEntityFactoryInterface::class)
        ) {
            try {
                $factory = $container->get(
                    CapabilityEntityFactoryInterface::class
                );
                if ($factory instanceof CapabilityEntityFactoryInterface) {
                    $this->setCapabilityEntityFactory($factory);
                }
            } catch (Throwable) {
            }
        }
    }

    public function getCapabilityEntityFactory(): ?CapabilityEntityFactoryInterface
    {
        return $this->capabilityEntityFactory;
    }

    public function setCapabilityEntityFactory(CapabilityEntityFactoryInterface $capabilityEntityFactory): void
    {
        if ($capabilityEntityFactory !== $this->capabilityEntityFactory) {
            $this->availableIdentities = null;
        }
        $this->capabilityEntityFactory = $capabilityEntityFactory;
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }

    public function getManager(): ?ManagerInterface
    {
        return $this->permission->getManager();
    }

    public function permitted(RoleInterface|UserRoleInterface $role, CapabilityInterface|string $capability): bool
    {
        return $this->permission->permitted($role, $capability);
    }

    public function add(CapabilityInterface $capability): CapabilityInterface
    {
        return $this->permission->add($capability);
    }

    public function replace(CapabilityInterface $capability): void
    {
        $this->permission->replace($capability);
    }

    public function has(CapabilityInterface|string $identity): bool
    {
        if ($this->has($identity)) {
            return true;
        }
        return $this->get($identity) !== null;
    }

    public function remove(string|CapabilityInterface $identity): ?CapabilityInterface
    {
        $identity = $this->identify($identity);
        if ($this->availableIdentities !== null && isset($this->availableIdentities[$identity])) {
            $this->availableIdentities[$identity] = false;
        }

        return $this->permission->remove($identity);
    }

    public function get(CapabilityInterface|string $identity): ?CapabilityInterface
    {
        if (!$this->permission->has($identity)) {
            $factory = $this->getCapabilityEntityFactory();
            if (!$factory) {
                return null;
            }

            $identity = $this->identify($identity);
            if (isset($this->cachedInvalidEntities[$identity])) {
                return null;
            }

            $em = $this->getConnection()->getEntityManager();
            $this->availableIdentities ??= IterableHelper::each(
                $factory->getCapabilityIdentities($em),
                static function (&$key, $value) {
                    $key = strtolower($value);
                    return false;
                }
            );

            if (!isset($this->availableIdentities[$identity])) {
                return null;
            }

            $entity = $factory->createEntity(
                $this->getConnection()->getEntityManager(),
                $identity
            );
            if (!$entity) {
                $this->cachedInvalidEntities[$identity] = false;
                return null;
            }
            $this->availableIdentities[$identity] = true;
            $this->add($entity);
        }

        return $this->permission->get($identity);
    }

    public function identify(CapabilityInterface|string $capability): string
    {
        return strtolower($this->permission->identify($capability));
    }

    public function getCapabilities(): array
    {
        if ($this->availableIdentities === null
            || !$this->capabilityEntityFactory
        ) {
            return $this->permission->getCapabilities();
        }

        if (empty($this->availableIdentities)) {
            return [];
        }
        $total = count($this->availableIdentities);
        $count = count(array_filter($this->availableIdentities));
        if ($total !== $count) {
            IterableHelper::every(
                $this
                    ->getCapabilityEntityFactory()
                    ->all($this->getConnection()->getEntityManager()),
                function ($e, CapabilityInterface $capability) {
                    $identity = $this->identify($capability);
                    $this->availableIdentities[$identity] = true;
                    $this->permission->add($capability);
                }
            );
        }

        return $this->permission->getCapabilities();
    }

    public function __debugInfo(): ?array
    {
        return Consolidation::debugInfo(
            $this,
            excludeKeys: ['permission']
        );
    }
}
