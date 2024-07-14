<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database\Wrapper;

use ArrayAccess\TrayDigita\Database\Connection;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Traits\Manager\ManagerDispatcherTrait;
use Doctrine\Common\Collections\AbstractLazyCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Selectable;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Doctrine\ORM\QueryBuilder;
use function func_get_args;

/**
 * @template T of object
 * @mixin EntityRepository
 * @template-implements EntityRepository<T>
 */
class EntityRepositoryWrapper extends EntityRepository
{
    use ManagerDispatcherTrait;

    public function __construct(
        protected Connection $databaseConnection,
        protected EntityRepository $repository
    ) {
        parent::__construct(
            $databaseConnection->getEntityManager(),
            $repository->getClassMetadata()
        );
    }

    protected function getPrefixNameEventIdentity(): ?string
    {
        return 'entityRepository';
    }

    public function getDatabaseConnection(): Connection
    {
        return $this->databaseConnection;
    }

    /**
     * Get the wrapped repository
     *
     * @return EntityRepository<T>
     */
    public function getRepository(): EntityRepository
    {
        return $this->repository;
    }

    /**
     * @inheritdoc
     */
    public function getManager(): ?ManagerInterface
    {
        return $this->getDatabaseConnection()->getManager();
    }

    /**
     * @inheritdoc
     */
    protected function getEntityName(): string
    {
        return $this->getRepository()->getEntityName();
    }

    /**
     * Get entity manager
     *
     * @return EntityManagerInterface
     */
    protected function getEntityManager(): EntityManagerInterface
    {
        return $this->getRepository()->getEntityManager();
    }

    /**
     * @inheritdoc
     */
    public function createResultSetMappingBuilder(string $alias): ResultSetMappingBuilder
    {
        try {
            // @dispatch(entityRepository.beforeCreateResultSetMappingBuilder)
            $this->dispatchBefore($alias, $this->getDatabaseConnection());
            $result = $this->getRepository()->createResultSetMappingBuilder($alias);
            // @dispatch(entityRepository.createResultSetMappingBuilder)
            $this->dispatchCurrent(
                $alias,
                $this->getDatabaseConnection(),
                $result
            );
            return $result;
        } finally {
            // @dispatch(entityRepository.afterCreateResultSetMappingBuilder)
            $this->dispatchAfter(
                $alias,
                $this->getDatabaseConnection(),
                $result??null
            );
        }
    }

    public function find(mixed $id, LockMode|int|null $lockMode = null, int|null $lockVersion = null): ?object
    {
        $arguments = func_get_args();
        try {
            // @dispatch(entityRepository.beforeFind)
            $this->dispatchBefore(
                $id,
                $lockMode,
                $lockVersion,
                $this->getDatabaseConnection()
            );

            $object = $this->getRepository()->find(...$arguments);

            // @dispatch(entityRepository.find)
            $this->dispatchCurrent(
                $id,
                $lockMode,
                $lockVersion,
                $this->getDatabaseConnection(),
                $object
            );

            return $object;
        } finally {
            // @dispatch(entityRepository.afterFind)
            $this->dispatchAfter(
                $id,
                $lockMode,
                $lockVersion,
                $this->getDatabaseConnection(),
                $object??null
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function createQueryBuilder(string $alias, ?string $indexBy = null): QueryBuilder
    {
        try {
            $this->dispatchBefore(
                $alias,
                $indexBy,
                $this->getDatabaseConnection()
            );
            $result = $this->getRepository()->createQueryBuilder(...func_get_args());
            // @dispatch(entityRepository.createQueryBuilder)
            $this->dispatchCurrent(
                $this->getDatabaseConnection(),
                $result
            );
            return $result;
        } finally {
            // @dispatch(entityRepository.afterCreateQueryBuilder)
            $this->dispatchAfter(
                $this->getDatabaseConnection(),
                $result??[]
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function findAll(): array
    {
        try {
            // @dispatch(entityRepository.beforeFindAll)
            $this->dispatchBefore($this->getDatabaseConnection());

            $result = $this->getRepository()->findAll();

            // @dispatch(entityRepository.findAll)
            $this->dispatchCurrent(
                $this->getDatabaseConnection(),
                $result
            );
            return $result;
        } finally {
            // @dispatch(entityRepository.afterFindAll)
            $this->dispatchAfter(
                $this->getDatabaseConnection(),
                $result??[]
            );
        }
    }

    /**
     * @inheritdoc
     */
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
                $this->getDatabaseConnection()
            );
            $find = $this->getRepository()->findBy(
                ...$arguments
            );
            // @dispatch(entityRepository.findBy)
            $this->dispatchCurrent(
                $criteria,
                $orderBy,
                $limit,
                $offset,
                $this->getDatabaseConnection(),
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
                $this->getDatabaseConnection()
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function findOneBy(array $criteria, ?array $orderBy = null): ?object
    {
        try {
            // @dispatch(entityRepository.beforeFindOneBy)
            $this->dispatchBefore(
                $criteria,
                $orderBy,
                $this->getDatabaseConnection()
            );
            $obj = $this->getRepository()->findOneBy(...func_get_args());
            // @dispatch(entityRepository.findOneBy)
            $this->dispatchCurrent(
                $criteria,
                $orderBy,
                $this->getDatabaseConnection(),
                $obj
            );
            return $obj;
        } finally {
            // @dispatch(entityRepository.afterFindOneBy)
            $this->dispatchAfter(
                $criteria,
                $orderBy,
                $this->getDatabaseConnection(),
                $obj??null
            );
        }
    }

    /**
     * @inheritdoc
     */
    protected function getClassMetadata(): ClassMetadata
    {
        return $this->getRepository()->getClassMetadata();
    }

    /**
     * @inheritdoc
     */
    public function matching(Criteria $criteria): AbstractLazyCollection&Selectable
    {
        try {
            // @dispatch(entityRepository.beforeMatching)
            $this->dispatchBefore($criteria, $this->getDatabaseConnection());
            $collection = $this->getRepository()->matching($criteria);
            // @dispatch(entityRepository.matching)
            $this->dispatchCurrent(
                $criteria,
                $this->getDatabaseConnection(),
                $collection
            );
            return $collection;
        } finally {
            // @dispatch(entityRepository.afterMatching)
            $this->dispatchAfter(
                $criteria,
                $this->getDatabaseConnection(),
                $collection??null
            );
        }
    }

    /**
     * @inheritdoc
     */
    public function __call(string $method, array $arguments): mixed
    {
        return call_user_func_array([$this->getRepository(), $method], $arguments);
    }

    /**
     * @inheritdoc
     */
    public function count(array $criteria = []): int
    {
        try {
            // @dispatch(entityRepository.beforeCount)
            $this->dispatchBefore($criteria, $this->getDatabaseConnection());
            $count = $this->getRepository()->count($criteria);
            // @dispatch(entityRepository.count)
            $this->dispatchCurrent(
                $criteria,
                $this->getDatabaseConnection(),
                $count
            );
            return $count;
        } finally {
            // @dispatch(entityRepository.afterCount)
            $this->dispatchAfter(
                $criteria,
                $this->getDatabaseConnection(),
                $count??0
            );
        }
    }
}
