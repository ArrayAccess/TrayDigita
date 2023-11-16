<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Util\Filter;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use function floor;

class Conversion
{
    /**
     * Compare time zone
     *
     * @param DateTimeInterface $from
     * @param DateTimeInterface $to
     * @return string
     */
    public static function compareDateToSQLTimezone(
        DateTimeInterface $from,
        DateTimeInterface $to
    ): string {
        $seconds = ($from->getTimestamp() - $to->getTimestamp());
        return self::convertOffsetToSQLTimezone($seconds);
    }

    /**
     * Convert the date to sql set for timezone
     *
     * @param DateTimeZone $timeZone
     * @return string
     */
    public static function convertDateTimeZoneToSQLTimezone(DateTimeZone $timeZone) : string
    {
        return self::convertDateToSQLTimezone(
            (new DateTimeImmutable())->setTimezone($timeZone)
        );
    }

    /**
     * Convert the date sql timezone
     *
     * @param DateTimeInterface $date
     * @return string
     * @see Conversion::convertDateTimeZoneToSQLTimezone()
     */
    public static function convertDateToSQLTimezone(DateTimeInterface $date) : string
    {
        return self::convertOffsetToSQLTimezone($date->getOffset());
    }

    /**
     * Converting seconds to sql timezone offset
     *
     * @param int $seconds
     * @return string
     */
    public static function convertOffsetToSQLTimezone(int $seconds) : string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor($seconds / 60 % 60);
        $hours = $hours < 10 && $hours >= 0
            ? "+0$hours"
            : ($hours < 0 && $hours > -10 ? "-0" . (-$hours) : "+$hours");
        $minutes = $minutes < 10 ? "0$minutes" : $minutes;
        return "$hours:$minutes";
    }
}
