<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database\Entities\Traits;

use ArrayAccess\TrayDigita\Database\Entities\Interfaces\AvailabilityStatusEntityInterface;
use function strtolower;
use function trim;

/**
 * @property-read string $availability_status
 */
trait AvailabilityStatusTrait
{
    abstract public function getStatus() : string;

    public function statusIs(string $status) : bool
    {
        return $this->getNormalizedStatus() === strtolower(trim($status));
    }

    public function getNormalizedStatus(): string
    {
        return $this->normalizeStatus($this->getStatus());
    }

    public function normalizeStatus(string $status): string
    {
        $status = strtolower(trim($status));
        $status = match ($status) {
            'close' => AvailabilityStatusEntityInterface::CLOSED,
            'disable' => AvailabilityStatusEntityInterface::DISABLED,
            'delete' => AvailabilityStatusEntityInterface::DELETED,
            'expire' => AvailabilityStatusEntityInterface::EXPIRED,
            'publish' => AvailabilityStatusEntityInterface::PUBLISHED,
            default => $status
        };

        return match ($status) {
            AvailabilityStatusEntityInterface::ACTIVE,
            AvailabilityStatusEntityInterface::DISABLED,
            AvailabilityStatusEntityInterface::CLOSED,
            AvailabilityStatusEntityInterface::DELETED,
            AvailabilityStatusEntityInterface::DRAFT,
            AvailabilityStatusEntityInterface::PENDING,
            AvailabilityStatusEntityInterface::PUBLISHED,
            AvailabilityStatusEntityInterface::EXPIRED => $status,
            default => self::UNKNOWN
        };
    }

    public function isActive() : bool
    {
        return $this->statusIs(AvailabilityStatusEntityInterface::ACTIVE);
    }

    public function isDisabled() : bool
    {
        return $this->statusIs(AvailabilityStatusEntityInterface::DISABLED);
    }
    public function isClosed() : bool
    {
        return $this->statusIs(AvailabilityStatusEntityInterface::CLOSED);
    }

    public function isPublished() : bool
    {
        return $this->statusIs(AvailabilityStatusEntityInterface::PUBLISHED);
    }
    
    public function isDeleted() : bool
    {
        return $this->statusIs(AvailabilityStatusEntityInterface::DELETED);
    }

    public function isDraft() : bool
    {
        return $this->statusIs(AvailabilityStatusEntityInterface::DRAFT);
    }

    public function isPending() : bool
    {
        return $this->statusIs(AvailabilityStatusEntityInterface::PENDING);
    }

    public function isExpired() : bool
    {
        return $this->statusIs(AvailabilityStatusEntityInterface::EXPIRED);
    }

    public function isUnknown() : bool
    {
        return $this->statusIs(AvailabilityStatusEntityInterface::UNKNOWN);
    }

    public function get(string $name, &$found = null)
    {
        $result = parent::get($name, $found);
        if ($name === 'availability_status' && !$found) {
            $found = true;
            return $this->getNormalizedStatus();
        }
        return $result;
    }
}
