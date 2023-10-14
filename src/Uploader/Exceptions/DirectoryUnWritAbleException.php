<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Uploader\Exceptions;

use ArrayAccess\TrayDigita\Exceptions\Runtime\RuntimeException;
use Throwable;

class DirectoryUnWritAbleException extends RuntimeException
{
    public function __construct(
        protected string $directory,
        string $message = "",
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getDirectory(): string
    {
        return $this->directory;
    }
}
