<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database\Entities\Traits;

use DateTimeInterface;
use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use function method_exists;
use function str_starts_with;

trait ParentIdEventStateTrait
{
    abstract public function getId() : int;

    abstract public function getParentId() : ?int;

    abstract public function setParentId(?int $parent_id);

    protected function parentIdCheck(
        PrePersistEventArgs|PostLoadEventArgs|PreUpdateEventArgs $event
    ) : void {
        if ($event instanceof PreUpdateEventArgs
            && $event->hasChangedField('parent_id')
            && $event->getNewValue('parent_id') === $this->getId()
        ) {
            $oldValue = $event->getOldValue('parent_id');
            if ($oldValue) {
                $parent = $event
                    ->getObjectManager()
                    ->getRepository($this::class)
                    ->find($this->getId())
                    ?->getParent();
                if ($parent?->getId() === $parent?->getParentId()) {
                    $parent = null;
                    $oldValue = null;
                }
            }

            if (method_exists($this, 'setParent')) {
                $this->setParent($parent ?? null);
            }
            $this->setParentId($oldValue);
            $event->setNewValue('parent_id', $oldValue);
        } elseif (!$event instanceof PreUpdateEventArgs
            && $this->getParentId() === $this->getId()
        ) {
            $parent = $event
                ->getObjectManager()
                ->getRepository($this::class)
                ->find($this->getId())
                ?->getParent();
            if ($parent?->getId() === $parent?->getParentId()) {
                $parent = null;
            }
            // prevent
            $this->setParentId($parent?->getId());
            if (method_exists($this, 'setParent')) {
                $this->setParent($parent ?? null);
            }
            $q = $event
                ->getObjectManager()
                ->createQueryBuilder()
                ->update($this::class, 'x')
                ->set('x.parent_id', ':val')
                ->where('x.id = :id')
                ->setParameters([
                    'val' => null,
                    'id' => $this->getId(),
                ]);
            if (method_exists($this, 'getUpdatedAt')) {
                $date = $this->getUpdatedAt();
                /** @noinspection PhpConditionAlreadyCheckedInspection */
                if ($date instanceof DateTimeInterface) {
                    $date = str_starts_with($date->format('Y'), '-')
                        ? '0000-00-00 00:00:00'
                        : $date->format('Y-m-d H:i:s');
                }
                $q
                    ->set('x.updated_at', ':updated_at')
                    ->setParameter('updated_at', $date);
            }
            $q->getQuery()->execute();
        }
    }
}
