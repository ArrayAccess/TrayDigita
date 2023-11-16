<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database;

use ArrayAccess\TrayDigita\Database\Attributes\Event;
use ArrayAccess\TrayDigita\Database\Events\CreateSchemaToolsEvent;
use ArrayAccess\TrayDigita\Database\Events\PostLoadEvent;
use ArrayAccess\TrayDigita\Database\Interfaces\DatabaseEventInterface;
use ArrayAccess\TrayDigita\Util\Filter\ContainerHelper;
use Doctrine\Common\EventSubscriber;
use ReflectionObject;
use Throwable;
use function array_values;
use function is_object;
use function is_string;
use function is_subclass_of;
use function strtolower;

class DatabaseEventsCollector
{
    public const REGISTERED_NONE = 0;

    public const REGISTERED_SUBSCRIBER = 1;

    public const REGISTERED_EVENT = 2;

    public const PROVIDERS = [
        CreateSchemaToolsEvent::class,
        PostLoadEvent::class,
    ];

    private bool $defaultEventRegistered = false;

    protected array $registeredEvents = [];

    protected array $subscriber = [];

    protected array $registeredEventsStatus = [];

    public function __construct(protected Connection $connection)
    {
    }

    public function registerDefaultEvent(): void
    {
        if ($this->defaultEventRegistered) {
            return;
        }
        $this->defaultEventRegistered = true;
        foreach (static::PROVIDERS as $className) {
            try {
                $this->add($className);
            } catch (Throwable) {
            }
        }
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * @template T of ?DatabaseEventInterface
     * @param class-string<T> $className
     * @return T|DatabaseEventInterface
     * @throws Throwable
     */
    public function createFromClassName(string $className) : ?DatabaseEventInterface
    {
        if (!is_subclass_of($className, DatabaseEventInterface::class)) {
            return null;
        }
        $container = $this->connection->getContainer();
        try {
            return ContainerHelper::resolveCallable(
                $className,
                $container
            );
        } catch (Throwable) {
            return null;
        }
    }

    public function isRegistered(DatabaseEventInterface $doctrineEvent) : bool
    {
        return isset($this->registeredEventsStatus[$doctrineEvent::class]);
    }

    /**
     * @param DatabaseEventInterface|class-string<DatabaseEventInterface> $doctrineEvent
     * @return int
     * @throws Throwable
     */
    public function add(DatabaseEventInterface|string $doctrineEvent) : int
    {
        $keyName = is_string($doctrineEvent)
            ? strtolower($doctrineEvent)
            : $doctrineEvent::class;
        if (isset($this->registeredEventsStatus[$keyName])) {
            return $this->registeredEventsStatus[$keyName];
        }
        if (!is_object($doctrineEvent)) {
            $doctrineEvent = $this->createFromClassName($doctrineEvent);
        }
        if (!$doctrineEvent) {
            return self::REGISTERED_NONE;
        }
        $keyName = strtolower($doctrineEvent::class);
        if (isset($this->registeredEventsStatus[$keyName])) {
            return $this->registeredEventsStatus[$keyName];
        }
        $this->registeredEventsStatus[$keyName] = self::REGISTERED_NONE;
        $ref = new ReflectionObject($doctrineEvent);
        $events = [];
        foreach ($ref->getAttributes(Event::class) as $attribute) {
            $name = $attribute->newInstance()->name;
            if (!$ref->hasMethod($name) || !$ref->getMethod($name)->isPublic()) {
                continue;
            }
            $lower = strtolower($name);
            $events[$lower] = $name;
        }

        $em = $this->connection->getEntityManager();
        $manager = $em->getEventManager();
        if ($doctrineEvent instanceof EventSubscriber
            && count($ref->getAttributes(Attributes\SubscribeEvent::class)) > 0
        ) {
            $manager->addEventSubscriber($doctrineEvent);
            $this->subscriber[] = $doctrineEvent;
            $this->registeredEventsStatus[$keyName] |= self::REGISTERED_SUBSCRIBER;
        }

        if (empty($events)) {
            return $this->registeredEventsStatus[$keyName];
        }

        $this->registeredEventsStatus[$keyName] |= self::REGISTERED_EVENT;
        $events = array_values($events);
        $manager->addEventListener($events, $doctrineEvent);
        foreach ($events as $name) {
            $this->registeredEvents[$name] = $doctrineEvent;
        }

        return $this->registeredEventsStatus[$keyName];
    }
}
