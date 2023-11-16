<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database\Entities\Interfaces;

interface UserStatusEntityInterface
{
    /**
     * Active status
     */
    public const ACTIVE = 'active';

    /**
     * Deleted status
     */
    public const DELETED = 'deleted';

    /**
     * Banned status
     */
    public const BANNED = 'banned';

    /**
     * Suspended status
     */
    public const SUSPENDED = 'suspended';

    /**
     * Expired User
     */
    public const EXPIRED = 'expired';

    /**
     * Status is pending review
     */
    public const PENDING = 'pending';

    /**
     * Status is unknown
     */
    public const UNKNOWN = 'unknown';

    public function getStatus() : string;

    public function statusIs(string $status) : bool;

    public function isActive() : bool;

    public function isDeleted() : bool;

    public function isBanned() : bool;

    public function isPending() : bool;

    public function isExpired() : bool;

    public function isUnknown() : bool;
}
