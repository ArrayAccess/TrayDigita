<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Auth\Generator\Traits;

use ArrayAccess\TrayDigita\Auth\Generator\Interfaces\NonceInterface;
use ArrayAccess\TrayDigita\Exceptions\Logical\OutOfRangeException;
use ArrayAccess\TrayDigita\Util\Filter\Consolidation;
use ArrayAccess\TrayDigita\Util\Generator\RandomString;
use SensitiveParameter;
use function dechex;
use function hash_equals;
use function hash_hmac;
use function hexdec;
use function is_int;
use function is_numeric;
use function max;
use function min;
use function preg_match;
use function serialize;
use function sprintf;
use function strlen;
use function substr;
use function substr_replace;
use function time;

trait NonceTrait
{
    protected int $expiration = NonceInterface::DEFAULT_PERIOD_SECONDS;

    public function __construct(
        #[SensitiveParameter]
        protected string $nonceKey,
        #[SensitiveParameter]
        protected string $saltKey,
        int $nonceExpiration = NonceInterface::DEFAULT_PERIOD_SECONDS
    ) {
        $nonceExpiration  = max($nonceExpiration, NonceInterface::MINIMUM_PERIOD_SECONDS);
        $this->expiration = min(NonceInterface::MAXIMUM_PERIOD_SECONDS, $nonceExpiration);
    }

    /**
     * Get salt key
     *
     * @return string
     */
    protected function getSaltKey(): string
    {
        return $this->saltKey;
    }

    /**
     * Get nonce key
     *
     * @return string
     */
    protected function getNonceKey(): string
    {
        return $this->nonceKey;
    }

    /**
     * {@inheritdoc}
     */
    public function getExpiration(): int
    {
        return $this->expiration;
    }

    /**
     * {@inheritdoc}
     */
    public function withExpiration(int $expiration): self
    {
        if ($expiration <= NonceInterface::MINIMUM_PERIOD_SECONDS) {
            throw new OutOfRangeException(
                sprintf(
                    'Expiration could not being %d or less',
                    NonceInterface::MINIMUM_PERIOD_SECONDS
                )
            );
        }

        $obj = clone $this;
        // set
        $obj->expiration = min(NonceInterface::MAXIMUM_PERIOD_SECONDS, $expiration);
        return $obj;
    }

    /**
     * {@inheritdoc}
     */
    public function withNonceKey(string $nonceKey) : self
    {
        $obj = clone $this;
        $obj->nonceKey = $nonceKey;
        return $obj;
    }

    /**
     * {@inheritdoc}
     */
    public function withSaltKey(string $saltKey) : self
    {
        $obj = clone $this;
        $obj->saltKey = $saltKey;
        return $obj;
    }

    protected function generateInternalNonce($action): string
    {
        $random  = RandomString::randomHex(16);
        $expired = time() + $this->getExpiration();
        $expired += (int) hexdec(substr($random, -8));
        $data = [
            'random' => $random,
            'expiration' => $expired,
            'action' => $action,
            'salt' => $this->getSaltKey(),
            'nonce' => $this->getNonceKey(),
        ];
        $hash = hash_hmac(
            'md5',
            serialize($data),
            $random
        );
        $hashDec = (int) hexdec(substr($hash, -6));
        $expired += $hashDec;
        $length = strlen((string) $hashDec);
        $hash = $hash . dechex($expired) . $random;
        $hash = substr_replace($hash, (string) $hashDec, $length, 0);
        return $hash . $length;
    }

    /**
     * @param string $nonce
     * @param $action
     * @return false|int|array{random: string, expiration: int, action: string}
     */
    protected function extractHash(string $nonce, $action): false|array|int
    {
        $hashDecLength = substr($nonce, -1);
        if (!is_numeric($hashDecLength)) {
            return NonceInterface::NONCE_INVALID;
        }

        $hashDecLength = (int) $hashDecLength;
        $nonceLength = strlen($nonce) - 1 - $hashDecLength;
        // max 12 + 32 + 16
        // min 8 + 32 + 16
        if ($nonceLength < 50 || $nonceLength > 60) {
            return NonceInterface::NONCE_INVALID;
        }
        preg_match(
            '~
            ^
            (?P<hash_1>[a-f0-9]{'.$hashDecLength.'})
            (?P<hash_expired>[0-9]{'.$hashDecLength.'})
            (?P<hash_2>[a-f0-9]{'.(32 - $hashDecLength).'})
            (?P<expire>[a-f0-9]+)
            (?P<random>[a-f0-9]{16})
            $~x',
            substr($nonce, 0, -1),
            $match
        );
        if (empty($match)) {
            return NonceInterface::NONCE_INVALID;
        }

        $hash     = $match['hash_1'] . $match['hash_2'];
        $hashExpired = (int) $match['hash_expired'];
        $expired  = $match['expire'];
        $random   = $match['random'];
        $expiration = hexdec($expired);
        // expiration should be integer
        if (!is_int($expiration)) {
            return NonceInterface::NONCE_INVALID;
        }
        $decimalHash   = (int) hexdec(substr($hash, -6));
        if ($decimalHash !== $hashExpired) {
            return NonceInterface::NONCE_INVALID;
        }

        $time     = time();
        $decimalRandom = (int) hexdec(substr($random, -8));
        $expiration -= $decimalHash;
        $expirationHash = $expiration;
        $expiration -= $decimalRandom;

        // invalid
        if (NonceInterface::MAXIMUM_PERIOD_SECONDS > $expiration) {
            return NonceInterface::NONCE_INVALID;
        }

        // expired
        if ($time > $expiration) {
            return NonceInterface::NONCE_EXPIRED;
        }
        $data = [
            'random' => $random,
            'expiration' => $expirationHash,
            'action' => $action,
            'salt' => $this->getSaltKey(),
            'nonce' => $this->getNonceKey(),
        ];
        $hmac = hash_hmac(
            'md5',
            serialize($data),
            $random
        );
        if (hash_equals($hmac, $hash) === true) {
            return [
                'random' => $random,
                'expiration' => $expirationHash,
                'action' => $action
            ];
        }

        return NonceInterface::NONCE_INVALID;
    }

    public function __debugInfo(): ?array
    {
        return Consolidation::debugInfo($this, ['nonceKey', 'saltKey']);
    }
}
