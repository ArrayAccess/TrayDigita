<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Assets\Interfaces;

use ArrayAccess\TrayDigita\Event\Interfaces\ManagerAllocatorInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;

/**
 * @template ObjectMismatchException of \ArrayAccess\TrayDigita\Exceptions\InvalidArgument\ObjectMismatchException
 * @noinspection PhpFullyQualifiedNameUsageInspection
 */
interface AssetsCollectionInterface extends ManagerAllocatorInterface
{
    /**
     * AssetsCollectionInterface constructor.
     *
     * @param ManagerInterface $manager The event manager
     */
    public function __construct(ManagerInterface $manager);

    /**
     * Check if has the dependencies
     *
     * @param string $dependenciesId
     * @return bool
     */
    public function has(string $dependenciesId): bool;

    /**
     * Get the dependencies by id
     *
     * @param string $dependenciesId
     * @return ?DependenciesInterface
     */
    public function get(string $dependenciesId): ?DependenciesInterface;

    /**
     * Get all dependencies
     *
     * @return array<string, DependenciesInterface>
     */
    public function getCollections() : array;

    /**
     * Check if the dependencies is contains
     *
     * @param DependenciesInterface $dependencies
     * @return bool
     */
    public function contains(DependenciesInterface $dependencies): bool;

    /**
     * Register a dependencies
     *
     * @param DependenciesInterface $dependencies
     * @return bool
     * @throws ObjectMismatchException
     */
    public function register(DependenciesInterface $dependencies): bool;

    /**
     * Deregister a dependencies
     *
     * @param DependenciesInterface|string $dependencies
     * @return ?DependenciesInterface The dependencies that deregistered
     */
    public function deregister(DependenciesInterface|string $dependencies): ?DependenciesInterface;
}
