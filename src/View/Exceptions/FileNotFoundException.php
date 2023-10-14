<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\View\Exceptions;

use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\InvalidArgumentException;
use Throwable;

class FileNotFoundException extends InvalidArgumentException
{
    protected string $fileName;

    public function __construct(
        string $fileName,
        string $message = "",
        int $code = 0,
        ?Throwable $previous = null
    ) {
        $this->fileName = $fileName;
        parent::__construct($message, $code, $previous);
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }
}
