<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database\Types\Converters;

use DateTime;
use JsonSerializable;

class DatetimeConversion extends DateTime implements JsonSerializable
{
    public const FORMAT = 'Y-m-d\TH:i:s.v';

    use DateConversionTrait;

    public function jsonSerialize(): array
    {

        $tz = json_decode(json_encode($this->getTimezone()), true);
        return [
            'date' => $this->convertDateString($this->format(self::FORMAT)),
            ...$tz
        ];
    }
}
