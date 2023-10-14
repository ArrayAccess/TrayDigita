<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database\Wrapper;

use ArrayAccess\TrayDigita\Database\Connection;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Traits\Manager\ManagerDispatcherTrait;
use Doctrine\Common\Collections\AbstractLazyCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Selectable;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ObjectRepository;
use Throwable;
use function func_get_args;

/**
 * @template T of object
 * @mixin EntityRepository|ObjectRepository
 * @template-implements ObjectRepository<T>
 */
class EntityRepositoryWrapper implements ObjectRepository, Selectable
{
    use ManagerDispatcherTrait;

    public function __construct(
        protected Connection $databaseConnection,
        protected EntityRepository|ObjectRepository $repository
    ) {
    }

    protected function getPrefixNameEventIdentity(): ?string
    {
        return 'entityRepository';
    }

    public function getDatabaseConnection(): Connection
    {
        return $this->databaseConnection;
    }

    public function getRepository(): EntityRepository|ObjectRepository
    {
        return $this->repository;
    }

    protected function getManagerFromContainer(): ?ManagerInterface
    {
        $container = $this->databaseConnection->getContainer();
        try {
            $manager = $container->has(ManagerInterface::class)
                ? $container->get(ManagerInterface::class)
                : null;
        } catch (Throwable) {
            $manager = null;
        }
        return $manager instanceof ManagerInterface ? $manager : null;
    }

    public function find($id, $lockMode = null, $lockVersion = null)
    {
        $arguments = func_get_args();
        try {
            // @dispatch(entityRepository.beforeFind)
            $this->dispatchBefore(
                $id,
                $lockMode,
                $lockVersion,
                $this->databaseConnection
            );

            $object = $this->repository->find(...$arguments);

            // @dispatch(entityRepository.find)
            $this->dispatchCurrent(
                $id,
                $lockMode,
                $lockVersion,
                $this->databaseConnection,
                $object
            );

            return $object;
        } finally {
            // @dispatch(entityRepository.afterFind)
            $this->dispatchAfter(
                $id,
                $lockMode,
                $lockVersion,
                $this->databaseConnection,
                $object??null
            );
        }
    }

    public function findAll(): array
    {
        try {
            // @dispatch(entityRepository.beforeFindAll)
            $this->dispatchBefore($this->databaseConnection);

            $result = $this->repository->findAll();

            // @dispatch(entityRepository.findAll)
            $this->dispatchCurrent(
                $this->databaseConnection,
                $result
            );
            return $result;
        } finally {
            // @dispatch(entityRepository.afterFindAll)
            $this->dispatchAfter(
                $this->databaseConnection,
                $result??[]
            );
        }
    }

    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        $arguments = func_get_args();
        try {
            // @dispatch(entityRepository.beforeFindBy)
            $this->dispatchBefore(
                $criteria,
                $orderBy,
                $limit,
                $offset,
                $this->databaseConnection
            );
            $find = $this->repository->findBy(
                ...$arguments
            );
            // @dispatch(entityRepository.findBy)
            $this->dispatchCurrent(
                $criteria,
                $orderBy,
                $limit,
                $offset,
                $this->databaseConnection,
                $find
            );
            return $find;
        } finally {
            // @dispatch(entityRepository.afterFindBy)
            $this->dispatchAfter(
                $criteria,
                $orderBy,
                $limit,
                $offset,
                $this->databaseConnection
            );
        }
    }

    public function findOneBy(array $criteria, ?array $orderBy = null)
    {
        try {
            // @dispatch(entityRepository.beforeFindOneBy)
            $this->dispatchBefore(
                $criteria,
                $orderBy,
                $this->databaseConnection
            );
            $obj = $this->repository->findOneBy(...func_get_args());
            // @dispatch(entityRepository.findOneBy)
            $this->dispatchCurrent(
                $criteria,
                $orderBy,
                $this->databaseConnection,
                $obj
            );
            return $obj;
        } finally {
            // @dispatch(entityRepository.afterFindOneBy)
            $this->dispatchAfter(
                $criteria,
                $orderBy,
                $this->databaseConnection,
                $obj??null
            );
        }
    }

    public function getClassName()
    {
        return $this->repository->getClassName();
    }

    /**
     * @param Criteria $criteria
     * @return AbstractLazyCollection&Selectable
     * @psalm-return AbstractLazyCollection<int, T>&Selectable<int, T>
     */
    public function matching(Criteria $criteria): AbstractLazyCollection&Selectable
    {
        try {
            // @dispatch(entityRepository.beforeMatching)
            $this->dispatchBefore($criteria, $this->databaseConnection);
            $collection = $this->repository->matching($criteria);
            // @dispatch(entityRepository.matching)
            $this->dispatchCurrent(
                $criteria,
                $this->databaseConnection,
                $collection
            );
            return $collection;
        } finally {
            // @dispatch(entityRepository.afterMatching)
            $this->dispatchAfter(
                $criteria,
                $this->databaseConnection,
                $collection??null
            );
        }
    }

    public function __call(string $name, array $arguments)
    {
        return $this->repository->$name(...$arguments);
    }
}
