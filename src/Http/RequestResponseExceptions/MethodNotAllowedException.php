<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Http\RequestResponseExceptions;

use function implode;

class MethodNotAllowedException extends RequestSpecializedCodeException
{

    protected $code = 405;

    protected string $description = 'The request method is not supported for the requested resource.';

    protected array $allowedMethods = [];

    /**
     * @return array
     */
    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }

    public function setAllowedMethods(array $allowedMethods): self
    {
        $this->allowedMethods = $allowedMethods;
        $this->message = 'Method not allowed. Must be one of: ' . implode(', ', $allowedMethods);
        return $this;
    }
}
