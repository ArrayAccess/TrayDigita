<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Uploader\Metadata;

use function is_numeric;
use function preg_match;
use function strtolower;
use function trim;

// phpcs:disable PSR1.Files.SideEffects
readonly class ContentRangeHeader
{
    public string $header;

    public array $acceptedUnits;

    /**
     * @var string|null string unit "bytes"
     */
    public ?string $unit;
    /**
     * @var array|string|null string as wildcard array as range
     */
    public array|string|null $ranges;
    /**
     * @var int|string|null string as wildcard int as size
     */
    public int|string|null $size;

    public bool $valid;

    public function __construct(
        string $header
    ) {
        $header = trim($header);
        $this->header = $header;
        $this->acceptedUnits = ['bytes'];
        preg_match(
            '~
                (?P<unit>[a-zA-Z]+\s+)?
                (?:
                    (?P<range>(?P<start>[0-9]+)-(?P<end>[0-9]+))/(?P<size>[0-9]+|\*)
                    |\*/(?P<size_wildcard>[0-9]+)
                )
                \s*$
                ~x',
            $header,
            $match
        );
        $this->valid = !empty($match);
        $this->unit = $this->valid
            ? strtolower(trim($match['unit']??'bytes')?:'bytes')
            : null;
        $this->ranges = !$this->valid ? null : (!empty($match['range'])
            ? [(int) $match['start'], (int) $match['end']]
            : '*');
        $this->size = !$this->valid ? null : (
            is_numeric($match['size'])
                ? (int) $match['size']
                : (is_numeric($match['size_wildcard']) ? (int) $match['size_wildcard'] : '*')
            );
    }
}
