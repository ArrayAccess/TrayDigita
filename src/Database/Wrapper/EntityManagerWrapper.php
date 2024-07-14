<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database\Wrapper;

use ArrayAccess\TrayDigita\Database\Connection;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerIndicateInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Traits\Manager\ManagerDispatcherTrait;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\Decorator\EntityManagerDecorator;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Internal\Hydration\AbstractHydrator;
use Doctrine\ORM\Proxy\ProxyFactory;
use Doctrine\ORM\UnitOfWork;
use function get_object_vars;

class EntityManagerWrapper extends EntityManagerDecorator implements ManagerIndicateInterface
{
    use ManagerDispatcherTrait;

    public function __construct(
        protected readonly Connection $databaseConnection,
        ?EntityManagerInterface $entityManager = null
    ) {
        $entityManager ??= $this->createEntityManager();
        parent::__construct($entityManager);
        $this->wrapped = $this->injectEntityManager($entityManager);
    }

    public function getWrappedEntity() : EntityManagerInterface
    {
        return $this->wrapped;
    }

    protected function getPrefixNameEventIdentity(): ?string
    {
        return 'entityManager';
    }

    private function createEntityManager(): EntityManager
    {
        try {
            $this->dispatchBefore($this->databaseConnection);
            $config = $this
                ->databaseConnection
                ->getConnection()
                ->getConfiguration();
            $config = $config instanceof Configuration
                ? $config
                : $this->databaseConnection->getDefaultConfiguration();
            $entity = new EntityManager(
                $this->databaseConnection->getConnection(),
                $config,
                $this->databaseConnection->getDoctrineEventManager()
            );
            $this->dispatchCurrent($this->databaseConnection, $entity);
            return $entity;
        } finally {
            $this->dispatchAfter($this->databaseConnection, $entity??null);
        }
    }

    private function injectEntityManager(
        ?EntityManagerInterface $entityManager = null
    ): EntityManagerInterface {
        $entityManager = $entityManager??$this->createEntityManager();
        try {
            $this->dispatchBefore($this->databaseConnection);
            $closure = function ($em) {
                // doing inject
                if (isset($this->em) && $this->em instanceof EntityManagerInterface) {
                    $this->em = $em;
                    return;
                }
                foreach (get_object_vars($this) as $key => $item) {
                    if ($item instanceof EntityManagerInterface) {
                        $this->$key = $em;
                        break;
                    }
                }
            };
            (function (EntityManagerInterface $em) {
                if (property_exists($this, 'unitOfWork')) {
                    $this->unitOfWork = new UnitOfWork($em);
                }
            })->call($entityManager, $this);
            (function (EntityManagerInterface $em) {
                $config = $em->getConfiguration();
                if (property_exists($this, 'proxyFactory')) {
                    $this->proxyFactory = new ProxyFactory(
                        $this,
                        $config->getProxyDir(),
                        $config->getProxyNamespace(),
                        $config->getAutoGenerateProxyClasses(),
                    );
                }
            })->call($entityManager, $this);
            // $closure->call($entityManager->getUnitOfWork(), $this);
            // $closure->call($entityManager->getProxyFactory(), $this);
            $closure->call($entityManager->getMetadataFactory(), $this);
            $this->dispatchCurrent($this->databaseConnection, $entityManager);
            return $entityManager;
        } finally {
            $this->dispatchAfter($this->databaseConnection, $entityManager);
        }
    }

    /**
     * @inheritdoc
     */
    public function getManager(): ?ManagerInterface
    {
        return $this->databaseConnection->getManager();
    }

    /**
     * @throws \Doctrine\ORM\Exception\ORMException
     * @noinspection PhpFullyQualifiedNameUsageInspection
     */
    public function getHydrator($hydrationMode): AbstractHydrator
    {
        return $this->newHydrator($hydrationMode);
    }

    public function newHydrator($hydrationMode): AbstractHydrator
    {
        try {
            // @dispatch(entityManager.beforeNewHydrator)
            $this->dispatchBefore($hydrationMode, $this->databaseConnection);

            $hydration = parent::newHydrator($hydrationMode);

            // @dispatch(entityManager.newHydrator)
            $this->dispatchCurrent(
                $hydrationMode,
                $this->databaseConnection,
                $hydration
            );
            return $hydration;
        } finally {
            // @dispatch(entityManager.afterNewHydrator)
            $this->dispatchAfter(
                $hydrationMode,
                $this->databaseConnection,
                $hydration??null
            );
        }
    }

    /**
     * @param $className
     * @return EntityRepository
     */
    public function getRepository($className) : EntityRepository
    {
        $entity = $this
            ->getConfiguration()
            ->getRepositoryFactory()
            ->getRepository(
                $this,
                $className
            );
        return new EntityRepositoryWrapper(
            $this->databaseConnection,
            $entity
        );
    }

    /**
     * @inheritdoc
     */
    public function find($className, $id, $lockMode = null, $lockVersion = null): ?object
    {
        try {
            // @dispatch(entityManager.beforeFind)
            $this->dispatchBefore(
                $className,
                $id,
                $lockMode,
                $lockVersion,
                $this->databaseConnection
            );

            $obj = parent::find($className, $id, $lockMode, $lockVersion);

            // @dispatch(entityManager.find)
            $this->dispatchCurrent(
                $className,
                $id,
                $lockMode,
                $lockVersion,
                $this->databaseConnection,
                $obj
            );
            return $obj;
        } finally {
            // @dispatch(entityManager.afterFind)
            $this->dispatchAfter(
                $className,
                $id,
                $lockMode,
                $lockVersion,
                $this->databaseConnection,
                $obj??null
            );
        }
    }
}
