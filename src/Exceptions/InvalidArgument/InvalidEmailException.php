<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Exceptions\InvalidArgument;

use Throwable;

class InvalidEmailException extends InvalidArgumentException
{
    protected ?string $email;

    public function __construct(
        ?string $email,
        string $message = "",
        int $code = 0,
        ?Throwable $previous = null
    ) {
        $this->email = $email;
        parent::__construct($message, $code, $previous);
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }
}
