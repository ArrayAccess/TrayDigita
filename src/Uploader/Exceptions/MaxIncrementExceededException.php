<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Uploader\Exceptions;

use ArrayAccess\TrayDigita\Exceptions\Runtime\RuntimeException;
use Throwable;
use function sprintf;

class MaxIncrementExceededException extends RuntimeException
{
    public function __construct(
        protected string $targetFile,
        protected int $maxIncrement,
        string $message = "",
        int $code = 0,
        ?Throwable $previous = null
    ) {
        $message = $message ?: sprintf(
            'Could not determine increment target file for : %s',
            $this->targetFile
        );
        parent::__construct($message, $code, $previous);
    }

    public function getTargetFile(): string
    {
        return $this->targetFile;
    }

    public function getMaxIncrement(): int
    {
        return $this->maxIncrement;
    }
}
