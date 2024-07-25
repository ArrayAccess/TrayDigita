<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Database\Types\Converters;

trait DateConversionTrait
{

    /**
     * @template P of mixed
     * @param P $value
     * @return mixed|P
     */
    private function convertDateString($value)
    {
        if (is_array($value)) {
            foreach ($value as $key => $val) {
                $value[$key] = $this->convertDateString($val);
            }
            return $value;
        }
        if ($value && is_string($value) && str_starts_with($value, '-000')) {
            $value = preg_replace_callback(
                '/^-(0001)([^0-9]+)?(?:(0[0-9]|1[1-2])([^0-9]+)([1-2][0-9]|3[0-1]))?/',
                static function ($match) {
                    if ($match[0] === '0001-11-30 00:00:00') {
                        return '0000-00-00 00:00:00';
                    }
                    if ($match[0] === '0001-11-30') {
                        return '0000-00-00';
                    }
                    if ($match[0] === '0001') {
                        return '0000';
                    }
                    if (str_starts_with($match[0], '0001-11-')) {
                        return substr_replace($match[0], '0000-00-00', 0, 10);
                    }
                    if (str_starts_with($match[0], '0001-11')) {
                        return substr_replace($match[0], '0000-00', 0, 7);
                    }
                    if (!isset($match[3])) {
                        return $match[1] . ($match[2]??'');
                    }
                    if (intval($match[3]) >= 11) {
                        $year = intval($match[1]) - 1;
                        $match[1] = str_pad((string)$year, 4, '0', STR_PAD_LEFT);
                        $match[3] = '00';
                        $match[5] = '00';
                    } else {
                        $month = $match[3];
                        $intMonth = intval($month);
                        $match[3] = str_pad((string)($intMonth + 1), 2, '0', STR_PAD_LEFT);
                        $day = intval($match[5]) + 1;
                        $_31_days_month = [1, 3, 5, 7, 8, 10, 12];
                        if (in_array($intMonth, $_31_days_month, true) && $day > 31) {
                            $match[5] = '01';
                        } elseif ($intMonth === 2 && $day > 29) {
                            $match[5] = '01';
                        } elseif ($day > 30) {
                            $match[5] = '01';
                        } else {
                            $match[5] = str_pad((string)$day, 2, '0', STR_PAD_LEFT);
                        }
                    }
                    return $match[1] . $match[2] . $match[3] . $match[4] . $match[5];
                },
                $value
            );
        }
        return $value;
    }
}
