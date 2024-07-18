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
    /**
     * @var ?CapabilityEntityFactoryInterface
     */
    protected ?CapabilityEntityFactoryInterface $capabilityEntityFactory = null;

    /**
     * @var array<string, bool>
     */
    private array $cachedInvalidEntities = [];

    /**
     * @var ?array<string, bool>
     */
    private ?array $availableIdentities = null;

    /**
     * @var ?PermissionInterface $permission
     */
    private PermissionInterface $permission;

    /**
     * PermissionWrapper constructor.
     *
     * @param Connection $connection
     * @param ContainerInterface|null $container
     * @param ManagerInterface|null $manager
     * @param PermissionInterface|null $permission
     */
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

    /**
     * Get the capability entity factory.
     *
     * @return CapabilityEntityFactoryInterface|null
     */
    public function getCapabilityEntityFactory(): ?CapabilityEntityFactoryInterface
    {
        return $this->capabilityEntityFactory;
    }

    /**
     * Set the capability entity factory.
     *
     * @param CapabilityEntityFactoryInterface $capabilityEntityFactory
     * @return void
     */
    public function setCapabilityEntityFactory(CapabilityEntityFactoryInterface $capabilityEntityFactory): void
    {
        if ($capabilityEntityFactory !== $this->capabilityEntityFactory) {
            $this->cachedInvalidEntities = [];
            $this->availableIdentities = null;
        }
        $this->capabilityEntityFactory = $capabilityEntityFactory;
    }

    /**
     * @return Connection
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * @inheritdoc
     */
    public function getManager(): ?ManagerInterface
    {
        return $this->permission->getManager();
    }

    /**
     * @inheritdoc
     */
    public function permitted(RoleInterface|UserRoleInterface $role, CapabilityInterface|string $capability): bool
    {
        $capability = $this->get($capability);
        if (!$capability) {
            return false;
        }
        return $this->permission->permitted($role, $capability);
    }

    /**
     * @inheritdoc
     */
    public function add(CapabilityInterface $capability): CapabilityInterface
    {
        return $this->permission->add($capability);
    }

    /**
     * @inheritdoc
     */
    public function replace(CapabilityInterface $capability): void
    {
        $this->permission->replace($capability);
    }

    /**
     * @inheritdoc
     */
    public function has(CapabilityInterface|string $identity): bool
    {
        if ($this->permission->has($identity)) {
            return true;
        }
        return $this->get($identity) !== null;
    }

    /**
     * @inheritdoc
     */
    public function remove(string|CapabilityInterface $identity): ?CapabilityInterface
    {
        $identity = $this->identify($identity);
        if ($this->availableIdentities !== null && isset($this->availableIdentities[$identity])) {
            $this->availableIdentities[$identity] = false;
        }

        return $this->permission->remove($identity);
    }

    /**
     * @inheritdoc
     */
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
            if (!isset($this->getCapabilities()[$identity])) {
                $this->cachedInvalidEntities[$identity] = false;
                return null;
            }
        }

        return $this->permission->get($identity);
    }

    /**
     * @inheritdoc
     */
    public function identify(CapabilityInterface|string $capability): string
    {
        return strtolower($this->permission->identify($capability));
    }

    /**
     * @inheritdoc
     */
    public function getCapabilities(): array
    {
        $factory = $this->getCapabilityEntityFactory();
        if ($this->availableIdentities === null && $factory) {
            $this->availableIdentities = [];
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

        if ($this->availableIdentities === null || !$factory) {
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
