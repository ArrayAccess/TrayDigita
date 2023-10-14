<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\L10n\PoMo\Exceptions;

use Throwable;
use function sprintf;

class UnreadableException extends FileNotFoundException
{
    public function __construct(
        string $fileName,
        string $message = "",
        int $code = 0,
        Throwable $previous = null
    ) {
        if (!$message) {
            $message = sprintf('File %s is not readable.', $fileName);
        }
        parent::__construct($fileName, $message, $code, $previous);
    }
}
