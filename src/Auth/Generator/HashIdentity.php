<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Auth\Generator;

use ArrayAccess\TrayDigita\Http\ServerRequest;
use ArrayAccess\TrayDigita\Util\Filter\Consolidation;
use SensitiveParameter;
use Throwable;
use WhichBrowser\Parser;
use function chr;
use function dechex;
use function hash_equals;
use function hash_hmac;
use function hexdec;
use function implode;
use function mt_rand;
use function preg_match;
use function random_bytes;
use function sha1;
use function strlen;
use function strtolower;
use function strtotime;
use function substr;
use function time;
use function trim;
use const PREG_UNMATCHED_AS_NULL;

class HashIdentity
{
    protected string $userAgent;

    private array $cached = [];

    public function __construct(
        #[SensitiveParameter]
        protected string $secretKey,
        #[SensitiveParameter]
        protected string $saltKey,
        ?string $userAgent = null
    ) {
        $userAgent ??= ServerRequest::fromGlobals()->getHeaderLine('User-Agent');
        $this->userAgent = $userAgent;
    }

    public function getUserAgent(): string
    {
        return $this->userAgent;
    }

    public function setUserAgent(string $userAgent): void
    {
        $this->userAgent = $userAgent;
    }

    /**
     * For note only
     *
     * @return array{system:string,browser:string}
     */
    public function getBrowserNameBased(): array
    {
        $ua = $this->getUserAgent();
        if (trim($ua) === '') {
            return [
                'system' => 'unknown',
                'browser' => 'unknown',
            ];
        }
        $parser = new Parser($ua);
        return [
            'system' => strtolower($parser->os->getName())?:'unknown',
            'browser' => strtolower($parser->browser->getName())?:'unknown',
        ];
    }

    public function generateUserAgentHash(
        string $randomKey
    ): string {
        return hash_hmac(
            'sha1',
            sha1(implode('|', $this->getBrowserNameBased())),
            $this->secretKey . $randomKey . $this->saltKey
        );
    }

    public function generate(int $id): string
    {
        $bytes = 16;
        try {
            $random = random_bytes($bytes);
        } catch (Throwable) {
            $random = '';
            while (strlen($random) < $bytes) {
                $random .= chr(mt_rand(0, 255));
            }
        }

        $randomKey = sha1($random);
        // get last 10 hex chars
        $randomHexNum = hexdec(substr($randomKey, -10));
        $randomHexNum = $randomHexNum < 100000000000 ? $randomHexNum * 10 : $randomHexNum;
        $time = time();
        $timeHex   = dechex($randomHexNum - $time);
        $idHex = dechex($randomHexNum + $id);
        $idHash = hash_hmac(
            'sha1',
            $idHex,
            $this->secretKey . $randomKey . $this->saltKey
        );

        $hash = hash_hmac(
            'sha1',
            "$idHash|$randomKey|$idHex|$timeHex",
            $this->secretKey . $randomKey . $this->saltKey
        );
        $agentHash = $this->generateUserAgentHash($randomKey);
        return "$hash$idHash$timeHex$randomKey$idHex$agentHash";
    }

    /**
     * @param string $generatedHash
     *
     * @return ?array{
     *     hash: string,
     *     id_hash: string,
     *     random_key: string,
     *     time_hex: string,
     *     id_hex: string,
     *     user_agent_hash: string,
     *     time: int,
     *     user_id: int,
     *     user_agent_match: boolean
     * }
     */
    public function extract(string $generatedHash): ?array
    {
        if (isset($this->cached[$generatedHash])) {
            return $this->cached[$generatedHash]?:null;
        }

        preg_match(
            '~^([a-f0-9]{40})  # hash
            ([a-f0-9]{40}) # userIdHash
            ([a-f0-9]{10}) # timeHex
            ([a-f0-9]{40}) # randomKey
            ([a-f0-9]+)    # IdHex
            ([a-f0-9]{40}) # UAHash
        $~x',
            $generatedHash,
            $match,
            PREG_UNMATCHED_AS_NULL
        );

        if ($match === null) {
            return null;
        }
        $this->cached[$generatedHash] = false;
        $hash = $match[1];
        $IdHash = $match[2];
        $timeHex = $match[3];
        $randomKey = $match[4];
        $idHex = $match[5];
        $UAHash = $match[6];
        $time = hexdec($timeHex);

        $randomHexNum = hexdec(substr($randomKey, -10));
        $randomHexNum = $randomHexNum < 100000000000 ? $randomHexNum * 10 : $randomHexNum;
        $time = $randomHexNum - $time;
        $fiveYearsAgo = strtotime('-5 Years');

        /**
         * Validate sign
         */
        // check if time less than 5 years ago
        // or the time greater than current time
        if ($fiveYearsAgo >= $time || time() < $time) {
            return null;
        }

        $newHash = hash_hmac(
            'sha1',
            "$IdHash|$randomKey|$idHex|$timeHex",
            $this->secretKey . $randomKey . $this->saltKey
        );

        $userId = (int) (hexdec($idHex) - $randomHexNum);
        $agentHash = $this->generateUserAgentHash($randomKey);
        $result = [
            'hash' => $hash,
            'id_hash' => $IdHash,
            'time_hex' => $timeHex,
            'random_key' => $randomKey,
            'id_hex' => $idHex,
            'user_agent_hash' => $UAHash,
            'time' => $time,
            'user_id' => $userId,
            'user_agent_match' => hash_equals($agentHash, $UAHash),
        ];

        if (!hash_equals($hash, $newHash)) {
            return null;
        }

        return $this->cached[$generatedHash] = $result;
    }

    /** @noinspection PhpUnused */
    public function withUserAgent(string $userAgent) : static
    {
        $obj = clone $this;
        $obj->userAgent = $userAgent;
        return $obj;
    }

    /** @noinspection PhpUnused */
    public function withKey(
        #[SensitiveParameter] string $secretKey,
        #[SensitiveParameter] string $saltKey
    ) : static {
        $obj = clone $this;
        $obj->secretKey = $secretKey;
        $obj->saltKey = $saltKey;
        return $obj;
    }

    /** @noinspection PhpUnused */
    public function withSecretKey(#[SensitiveParameter] string $secretKey) : static
    {
        return $this->withKey($secretKey, $this->saltKey);
    }

    /** @noinspection PhpUnused */
    public function withSaltKey(#[SensitiveParameter] string $saltKey) : static
    {
        return $this->withKey($this->secretKey, $saltKey);
    }

    public function __debugInfo(): ?array
    {
        return Consolidation::debugInfo($this, ['secretKey', 'saltKey']);
    }
}
