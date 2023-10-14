<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database\Entities\Interfaces;

interface UserStatusEntityInterface
{
    /**
     * Active status
     */
    const ACTIVE = 'active';

    /**
     * Deleted status
     */
    const DELETED = 'deleted';

    /**
     * Banned status
     */
    const BANNED = 'banned';

    /**
     * Suspended status
     */
    const SUSPENDED = 'suspended';

    /**
     * Expired User
     */
    const EXPIRED = 'expired';

    /**
     * Status is pending review
     */
    const PENDING = 'pending';

    /**
     * Status is unknown
     */
    const UNKNOWN = 'unknown';

    public function getStatus() : string;

    public function statusIs(string $status) : bool;

    public function isActive() : bool;

    public function isDeleted() : bool;

    public function isBanned() : bool;

    public function isPending() : bool;

    public function isExpired() : bool;

    public function isUnknown() : bool;
}
