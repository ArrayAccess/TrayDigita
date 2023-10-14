<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Assets\Interfaces;

use ArrayAccess\TrayDigita\Event\Interfaces\ManagerAllocatorInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\ObjectMismatchException;

interface AssetsCollectionInterface extends ManagerAllocatorInterface
{
    public function __construct(ManagerInterface $manager);

    public function has(string $dependenciesId): bool;

    public function get(string $dependenciesId): ?DependenciesInterface;

    public function getCollections() : array;

    public function contains(DependenciesInterface $dependencies): bool;

    /**
     * @param DependenciesInterface $dependencies
     * @return bool
     * @throws ObjectMismatchException
     */
    public function register(DependenciesInterface $dependencies): bool;

    public function deregister(DependenciesInterface|string $dependencies): ?DependenciesInterface;
}
