<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database\Entities\Traits;

use ArrayAccess\TrayDigita\Util\Filter\DataType;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Mapping\PostLoad;
use Doctrine\ORM\Mapping\PostPersist;
use Doctrine\ORM\Mapping\PostUpdate;
use Doctrine\ORM\Mapping\PrePersist;
use Doctrine\ORM\Mapping\PreUpdate;

// unused
trait EncodedDecodedValuesTrait
{
    private mixed $decodedValue = null;

    private mixed $lastValue = null;

    private bool $changed = false;

    private bool $decoded = false;

    protected mixed $value = null;

    public function setValue(mixed $value): void
    {
        $this->decoded = true;
        $this->decodedValue = $value;
        $this->value = $value;
    }

    public function getValue(): mixed
    {
        return $this->decoded ? $this->decodedValue : $this->value;
    }

    #[PostLoad]
    public function unserializeData(): void
    {
        $this->decoded = true;
        $this->decodedValue = DataType::shouldUnSerialize($this->value);
    }

    #[
        PrePersist,
        PreUpdate
    ]
    public function serializeData(PrePersistEventArgs|PreUpdateEventArgs $event): void
    {
        if ($event instanceof PrePersistEventArgs || $event->hasChangedField('value')) {
            $this->changed = true;
            $this->lastValue = $this->value;
            $this->value = DataType::shouldSerialize($this->value);
            if ($event instanceof PreUpdateEventArgs && $event->hasChangedField('value')) {
                $event->setNewValue('value', $this->value);
            }
        }
    }

    #[
        PostPersist,
        PostUpdate
    ]
    public function restorePreviousData(): void
    {
        if (!$this->changed) {
            return;
        }
        $this->changed = false;
        $this->value = $this->lastValue;
        $this->lastValue = null;
    }
}
