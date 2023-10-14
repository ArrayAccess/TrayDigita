<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Uploader\Exceptions;

use ArrayAccess\TrayDigita\Exceptions\Runtime\RuntimeException;
use Throwable;

class FileUnWritAbleException extends RuntimeException
{
    public function __construct(
        protected string $fileName,
        string $message = "",
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }
}
