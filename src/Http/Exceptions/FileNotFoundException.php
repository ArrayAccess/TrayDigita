<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Http\Exceptions;

use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\InvalidArgumentException;
use Throwable;
use function sprintf;

class FileNotFoundException extends InvalidArgumentException
{
    public function __construct(
        protected string $fileName,
        string $message = "",
        int $code = 0,
        Throwable $previous = null
    ) {
        if (!$message) {
            $message = sprintf('File %s has not found', $this->fileName);
        }
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return string
     */
    public function getFileName() : string
    {
        return $this->fileName;
    }
}
