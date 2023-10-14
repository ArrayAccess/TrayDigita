<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Responder;

use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\InvalidArgumentException;
use ArrayAccess\TrayDigita\Http\Code;
use ArrayAccess\TrayDigita\Responder\Interfaces\JsonDataResponderInterface;
use function array_key_exists;
use function sprintf;

class JsonDataResponder implements JsonDataResponderInterface
{
    protected int $statusCode = Code::OK;
    protected mixed $message = null;
    protected mixed $error = null;
    protected array $metadata = [];

    public function setStatusCode(int $code) : static
    {
        if (!Code::statusMessage($code)) {
            throw new InvalidArgumentException(
                sprintf('Status code "%d" is invalid', $code)
            );
        }
        $this->statusCode = $code;
        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function setMessage($message) : static
    {
        $this->message = $message;
        return $this;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function setErrorMessage($message) : static
    {
        $this->error = $message;
        return $this;
    }

    public function getErrorMessage()
    {
        return $this->error;
    }

    public function setMetadata(array $meta) : static
    {
        $this->metadata = $meta;
        return $this;
    }

    public function setMeta($name, $value) : static
    {
        $this->metadata[$name] = $value;
        return $this;
    }

    public function removeMeta($name): void
    {
        unset($this->metadata[$name]);
    }

    public function hasMeta($name) : bool
    {
        return array_key_exists($name, $this->metadata);
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }
}
