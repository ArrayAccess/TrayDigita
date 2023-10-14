<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\L10n\Exceptions;

use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\InvalidArgumentException;
use Throwable;
use function sprintf;

class FileNotFoundException extends InvalidArgumentException
{
    /**
     * @var string
     */
    protected string $fileName;

    public function __construct(string $file, $message = "", $code = 0, Throwable $previous = null)
    {
        $this->fileName = $file;
        if (!$message) {
            $message = sprintf('File %s has not found', $file);
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
