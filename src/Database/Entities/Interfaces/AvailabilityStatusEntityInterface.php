<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database\Entities\Interfaces;

interface AvailabilityStatusEntityInterface
{
    /**
     * Active status
     */
    public const ACTIVE = 'active';

    /**
     * Disabled status
     */
    public const DISABLED = 'disabled';

    /**
     * Closed status
     */
    public const CLOSED = 'closed';

    /**
     * Published status
     */
    public const PUBLISHED = 'published';

    /**
     * Deleted status
     */
    public const DELETED = 'deleted';

    /**
     * Draft status
     */
    public const DRAFT = 'draft';

    /**
     * Status is pending review
     */
    public const PENDING = 'pending';

    /**
     * Status is expired
     */
    public const EXPIRED = 'expired';

    /**
     * Status is unknown
     */
    public const UNKNOWN = 'unknown';

    public function getStatus() : string;

    public function statusIs(string $status) : bool;

    public function isActive() : bool;

    public function isDisabled() : bool;

    public function isClosed() : bool;

    public function isPublished() : bool;

    public function isDeleted() : bool;

    public function isDraft() : bool;

    public function isPending() : bool;

    public function isExpired() : bool;

    public function isUnknown() : bool;
}
