<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database\Entities\Interfaces;

interface AvailabilityStatusEntityInterface
{
    /**
     * Active status
     */
    const ACTIVE = 'active';

    /**
     * Disabled status
     */
    const DISABLED = 'disabled';

    /**
     * Closed status
     */
    const CLOSED = 'closed';

    /**
     * Published status
     */
    const PUBLISHED = 'published';

    /**
     * Deleted status
     */
    const DELETED = 'deleted';

    /**
     * Draft status
     */
    const DRAFT = 'draft';

    /**
     * Status is pending review
     */
    const PENDING = 'pending';

    /**
     * Status is expired
     */
    const EXPIRED = 'expired';

    /**
     * Status is unknown
     */
    const UNKNOWN = 'unknown';

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
