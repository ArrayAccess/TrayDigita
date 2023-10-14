<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Exceptions\InvalidArgument;

use Throwable;

class InvalidUsernameException extends InvalidArgumentException
{
    protected ?string $username;

    public function __construct(
        ?string $email,
        string $message = "",
        int $code = 0,
        ?Throwable $previous = null
    ) {
        $this->username = $email;
        parent::__construct($message, $code, $previous);
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }
}
