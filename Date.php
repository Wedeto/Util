<?php

namespace WASP\Util;

use DateTime;
use DateTimeImmutable;
use DateInterval;
use InvalidArgumentException;
use WASP\Util\Functions as WF;

class Date
{
    const SECONDS_IN_MINUTE =                 60;
    const SECONDS_IN_HOUR   =            60 * 60;
    const SECONDS_IN_DAY    =       24 * 60 * 60;
    const SECONDS_IN_WEEK   =   7 * 24 * 60 * 60;
    const SECONDS_IN_MONTH  =  30 * 24 * 60 * 60;
    const SECONDS_IN_YEAR   = 365 * 24 * 60 * 60;

    public static function copy($str)
    {
        if ($str instanceof DateTime)
            return DateTime::createFromFormat(DateTime::ATOM, $str->format(DateTime::ATOM));
        if ($str instanceof DateTimeImmutable)
            return DateTimeImmutable::createFromFormat(DateTime::ATOM, $str->format(DateTime::ATOM));

        if ($str instanceof DateInterval)
        {
            $fmt = 'P' . $str->y . 'Y' . $str->m . 'M' . $str->d . 'DT' . $str->h . 'H' . $str->i . 'M' . $str->s . 'S';
            $int = new DateInterval($fmt);
            $int->invert = $str->invert;
            $int->days = $str->days;
            return $int;
        }

        throw new InvalidArgumentException("Invalid argument: " . WF::str($str));
    }

    public static function compareInterval(DateInterval $l, DateInterval $r)
    {
        $now = new \DateTimeImmutable();
        $a = $now->add($l);
        $b = $now->add($r);

        if ($a < $b)
            return -1;
        if ($a > $b)
            return 1;
        return 0;
    }

    public static function lessThan(DateInterval $l, DateInterval $r)
    {
        return self::compareInterval($l, $r) < 0;
    }

    public static function lessThanOrEqual(DateInterval $l, DateInterval $r)
    {
        return self::compareInterval($l, $r) <= 0;
    }

    public static function equal(DateInterval $l, DateInterval $r)
    {
        return self::compareInterval($l, $r) === 0;
    }

    public static function greaterThan(DateInterval $l, DateInterval $r)
    {
        return self::compareInterval($l, $r) > 0;
    }

    public static function greaterThanOrEqual(DateInterval $l, DateInterval $r)
    {
        return self::compareInterval($l, $r) >= 0;
    }

    public static function isBefore(DateTime $l, DateTime $r)
    {
        return $l < $r;
    }

    public static function isAfter(DateTime $l, DateTime $r)
    {
        return $l > $r;
    }

    public static function isPast(DateTime $l)
    {
        $now = new DateTime();
        return $l < $now;
    }

    public static function isFuture(DateTime $l)
    {
        $now = new DateTime();
        return $l > $now;
    }
}
