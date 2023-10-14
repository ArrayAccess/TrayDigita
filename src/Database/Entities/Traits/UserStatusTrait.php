<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database\Entities\Traits;

use ArrayAccess\TrayDigita\Database\Entities\Interfaces\UserStatusEntityInterface;

trait UserStatusTrait
{
    abstract public function getStatus() : string;

    public function statusIs(string $status) : bool
    {
        $status = strtolower(trim($status));
        return $this->normalizeStatus($this->getStatus()) === $status;
    }

    public function normalizeStatus(string $status): string
    {
        $status = strtolower(trim($status));
        // fix typo
        $status = match ($status) {
            'delete' => UserStatusEntityInterface::DELETED,
            'expire' => UserStatusEntityInterface::EXPIRED,
            'suspend' => UserStatusEntityInterface::SUSPENDED,
            'ban'    => UserStatusEntityInterface::BANNED,
            default => $status
        };
        return match ($status) {
            UserStatusEntityInterface::ACTIVE,
            UserStatusEntityInterface::BANNED,
            UserStatusEntityInterface::PENDING,
            UserStatusEntityInterface::SUSPENDED,
            UserStatusEntityInterface::DELETED,
            UserStatusEntityInterface::EXPIRED => $status,
            default => UserStatusEntityInterface::UNKNOWN
        };
    }


    public function isDeleted() : bool
    {
        return $this->statusIs(UserStatusEntityInterface::DELETED);
    }

    public function isActive() : bool
    {
        return $this->statusIs(UserStatusEntityInterface::ACTIVE);
    }

    public function isBanned() : bool
    {
        return $this->statusIs(UserStatusEntityInterface::BANNED);
    }

    public function isPending() : bool
    {
        return $this->statusIs(UserStatusEntityInterface::PENDING);
    }
    public function isExpired() : bool
    {
        return $this->statusIs(UserStatusEntityInterface::EXPIRED);
    }

    public function isUnknown() : bool
    {
        return $this->statusIs(UserStatusEntityInterface::UNKNOWN);
    }
}
