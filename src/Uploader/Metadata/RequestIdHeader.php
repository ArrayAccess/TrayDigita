<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Uploader\Metadata;

use ArrayAccess\TrayDigita\Util\Generator\UUID;
use function preg_match;
use function sprintf;
use function strlen;

// phpcs:disable PSR1.Files.SideEffects
readonly class RequestIdHeader
{
    public string $header;

    public ?string $uuid;

    public ?int $uuidVersion;

    public ?string $hash;

    public ?string $hashAlgo;

    public bool $valid;

    public function __construct(string $header)
    {
        $header = trim($header);
        $this->header = $header;
        preg_match(
            '~^
                (?P<uuid>[0-9a-f]{8}-[0-9a-f]{4}-(?P<uuid_version>[345])[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12})
                -(?P<hash>[0-9a-f]{32}|[0-9a-f]{40}|[0-9a-f]{64}|[0-9a-f]{128})
            $~x',
            $header,
            $match
        );

        $this->valid = !empty($match);
        $this->uuid = $match['uuid']??null;
        $this->uuidVersion = $this->valid ? (int) $match['uuid_version'] : null;
        $this->hash = $match['hash']??null;
        $this->hashAlgo = match ($this->hash ? strlen($this->hash) : 0) {
            128 => 'sha512',
            64 => 'sha256',
            40 => 'sha1',
            32 => 'md5',
            default => null
        };
    }

    public static function createRequestId(string $fileName) : string
    {
        return sprintf('%s-%s', UUID::v4(), md5($fileName));
    }
}
