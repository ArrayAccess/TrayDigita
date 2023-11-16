<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Auth\Generator\Interfaces;

use SensitiveParameter;

interface NonceInterface
{
    public const NONCE_VALID = true;
    public const NONCE_EXPIRED = 0;
    public const NONCE_INVALID = false;

    public const DEFAULT_PERIOD_SECONDS = 21600; // 6 hours

    public const MAXIMUM_PERIOD_SECONDS = 604800; // 7 days

    public const MINIMUM_PERIOD_SECONDS = 30;

    /**
     * Create a new nonce object
     *
     * @param string $nonceKey
     * @param string $saltKey
     * @param int $nonceExpiration
     */
    public function __construct(
        #[SensitiveParameter]
        string $nonceKey,
        #[SensitiveParameter]
        string $saltKey,
        int $nonceExpiration = self::DEFAULT_PERIOD_SECONDS
    );

    /**
     * Get nonce expiration
     *
     * @return int
     */
    public function getExpiration(): int;

    /**
     * Generate nonce, returning string if succeed otherwise null
     *
     * @param string $action
     * @return ?string
     */
    public function generate(
        string $action
    ): ?string;

    /**
     * Validate nonce data
     *
     * @param string $nonce generated nonce
     * @param string $action action name
     * @return bool|int
     * @uses self::NONCE_INVALID
     * @uses self::NONCE_EXPIRED
     * @uses self::NONCE_VALID
     */
    public function validate(string $nonce, string $action): bool|int;

    /**
     * Use nonce clone with custom expiration
     *
     * @param int $expiration
     * @return self
     */
    public function withExpiration(int $expiration): self;

    /**
     * use nonce clone with custom nonce key
     *
     * @param string $nonceKey
     * @return self
     */
    public function withNonceKey(string $nonceKey) : self;

    /**
     * use nonce clone with custom nonce key
     *
     * @param string $saltKey
     * @return self
     */
    public function withSaltKey(string $saltKey) : self;
}
