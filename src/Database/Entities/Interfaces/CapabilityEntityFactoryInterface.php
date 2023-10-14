<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database\Entities\Interfaces;

use Doctrine\ORM\EntityManagerInterface;

interface CapabilityEntityFactoryInterface
{
    public function createEntity(
        EntityManagerInterface $entityManager,
        string $identity
    ) : ?CapabilityEntityInterface;

    public function all(
        EntityManagerInterface $entityManager
    ) : iterable;

    public function getCapabilityIdentities(EntityManagerInterface $entityManager) : array;
}
