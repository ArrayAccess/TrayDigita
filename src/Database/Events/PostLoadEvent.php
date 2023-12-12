<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database\Events;

use ArrayAccess\TrayDigita\Container\Interfaces\ContainerAllocatorInterface;
use ArrayAccess\TrayDigita\Database\Attributes\SubscribeEvent;
use ArrayAccess\TrayDigita\Database\DatabaseEvent;
use ArrayAccess\TrayDigita\Database\Entities\Abstracts\AbstractEntity;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerAllocatorInterface;
use ArrayAccess\TrayDigita\Event\Interfaces\ManagerInterface;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Events;
use Doctrine\Persistence\ObjectManager;
use ReflectionObject;
use ReflectionUnionType;
use function is_a;

/**
 * Event for @postLoad & add container & manager
 */
#[SubscribeEvent]
class PostLoadEvent extends DatabaseEvent implements EventSubscriber
{
    protected function getManager() : ?ManagerInterface
    {
        return ContainerHelper::use(ManagerInterface::class, $this->getConnection()->getContainer());
    }

    /**
     * Event postLoad
     *
     * @param PostLoadEventArgs $eventArgs
     * @return void
     */
    public function postLoad(PostLoadEventArgs $eventArgs): void
    {
        $manager = $this->getManager();
        $manager
            ?->dispatch(
                'database.beforeEventPostLoad',
                $eventArgs
            );
        try {
            $object = $eventArgs->getObject();
            $container = $this->getConnection()->getContainer();
            if ($object instanceof ContainerAllocatorInterface) {
                $object->setContainer($container);
            }
            $manager = ContainerHelper::use(ManagerInterface::class, $container);
            if ($object instanceof ManagerAllocatorInterface
                && $container->has(ManagerInterface::class)
            ) {
                $object->setManager($manager);
            }
            if ($object instanceof AbstractEntity) {
                $em = $object->getEntityManager()??$eventArgs->getObjectManager();
                $object->setEntityManager(
                    $this->getConnection()->wrapEntity($em)
                );
            } else {
                $ref = new ReflectionObject($object);
                $method = $ref->hasMethod('setEntityManager')
                    ? $ref->getMethod('setEntityManager')
                    : (
                    $ref->hasMethod('setObjectManager')
                        ? $ref->getMethod('setObjectManager')
                        : null
                    );
                if ($method) {
                    $refMethod = $method->getNumberOfParameters() === 1
                        ? ($method->getParameters()[0] ?? null)
                        : null;
                    /**
                     * @var \ReflectionNamedType[] $type
                     * @noinspection PhpFullyQualifiedNameUsageInspection
                     */
                    $type = $refMethod
                        ? (
                        !$refMethod->getType() instanceof ReflectionUnionType
                            ? [$refMethod->getType()]
                            : $refMethod->getType()
                        )
                        : [];
                    $em = $eventArgs->getObjectManager();
                    foreach ($type as $t) {
                        if (!$t->isBuiltin() && is_a($t->getName(), ObjectManager::class)) {
                            $object->{$method}(
                                $em instanceof EntityManagerInterface
                                    ? $this
                                    ->getConnection()
                                    ->wrapEntity($eventArgs->getObjectManager())
                                    : $em
                            );
                            break;
                        }
                    }
                }
            }
            $manager
                ?->dispatch(
                    'database.eventPostLoad',
                    $eventArgs
                );
        } finally {
            $manager
                ?->dispatch(
                    'database.afterEventPostLoad',
                    $eventArgs
                );
        }
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::postLoad
        ];
    }
}
