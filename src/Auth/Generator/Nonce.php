<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Auth\Generator;

use ArrayAccess\TrayDigita\Auth\Generator\Interfaces\NonceInterface;
use ArrayAccess\TrayDigita\Auth\Generator\Traits\NonceTrait;
use function is_array;

final class Nonce implements NonceInterface
{
    use NonceTrait;

    protected array $cachedHash = [];

    protected array $cachedResult = [];

    /**
     * {@inheritdoc}
     */
    public function generate(string $action): string
    {
        if (isset($this->cachedHash[$action])) {
            return $this->cachedHash[$action];
        }

        // cache key as action
        $hash = $this->generateInternalNonce($action);
        $this->cachedHash[$action] = $hash;
        $this->cachedResult[$hash][$action] = self::NONCE_VALID;
        return $hash;
    }

    /**
     * {@inheritdoc}
     */
    public function validate(string $nonce, string $action): bool|int
    {
        /** @noinspection DuplicatedCode */
        $valid = $this->cachedResult[$nonce][$action]??null;
        if ($valid === self::NONCE_VALID
            || $valid === self::NONCE_INVALID
            || $valid === self::NONCE_EXPIRED
        ) {
            return $valid;
        }
        $result = $this->extractHash($nonce, $action);
        if (!is_array($result)) {
            return $this->cachedResult[$nonce][$action] = $result;
        }
        return $this->cachedResult[$nonce][$action] = self::NONCE_VALID;
    }
}
