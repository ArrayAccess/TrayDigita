<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Uploader\Exceptions;

use Throwable;

class InvalidOffsetPositionException extends ContentRangeIsNotFulFilledException
{
    public function __construct(
        protected int $position,
        protected ?int $size,
        string $message = "",
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }
}
