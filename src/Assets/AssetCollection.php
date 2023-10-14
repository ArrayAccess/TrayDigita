<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Assets;

use ArrayAccess\TrayDigita\Assets\Interfaces\AssetsCollectionInterface;
use ArrayAccess\TrayDigita\Assets\Interfaces\DependenciesInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\ObjectMismatchException;
use ArrayAccess\TrayDigita\Traits\Manager\ManagerAllocatorTrait;
use ArrayAccess\TrayDigita\Util\Filter\Consolidation;
use function in_array;

class AssetCollection implements AssetsCollectionInterface
{
    use ManagerAllocatorTrait;

    /**
     * @var array<string, DependenciesInterface>
     */
    protected array $collections = [];

    public function __construct(?ManagerInterface $manager = null)
    {
        if ($manager) {
            $this->setManager($manager);
        }
        $this->registerDefaultDependencies();
    }

    private function registerDefaultDependencies(): void
    {
        $this->register(new Js($this));
        $this->register(new Css($this));
    }

    public function get(string $dependenciesId): ?DependenciesInterface
    {
        return $this->collections[$dependenciesId]??null;
    }

    /**
     * @return array<string, DependenciesInterface>
     */
    public function getCollections(): array
    {
        return $this->collections;
    }

    public function has(string $dependenciesId): bool
    {
        return isset($this->collections[$dependenciesId]);
    }

    public function contains(DependenciesInterface $dependencies): bool
    {
        return in_array($dependencies, $this->collections);
    }

    public function register(DependenciesInterface $dependencies): bool
    {
        $id = $dependencies->getId();
        if (isset($this->collections[$id])) {
            return false;
        }
        if ($dependencies->getAssetsCollection() !== $this) {
            throw new ObjectMismatchException(
                'Object assets collection is mismatch'
            );
        }
        $this->collections[$id] = $dependencies;
        return true;
    }

    public function deregister(DependenciesInterface|string $dependencies): ?DependenciesInterface
    {
        $id = $dependencies->getId();
        if (!isset($this->collections[$id])) {
            return null;
        }
        $dependencies = $this->collections[$id];
        unset($this->collections[$id]);
        return $dependencies;
    }

    public function __debugInfo(): ?array
    {
        return Consolidation::debugInfo($this, excludeKeys: ['collections']);
    }
}
