<?php
/*
This is part of Wedeto, The WEb DEvelopment TOolkit.
It is published under the MIT Open Source License.

Copyright 2017, Egbert van der Wal

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files (the "Software"), to deal in
the Software without restriction, including without limitation the rights to
use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
the Software, and to permit persons to whom the Software is furnished to do so,
subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

namespace Wedeto\Util;

use DateTimeInterface;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use DateInterval;
use InvalidArgumentException;
use Wedeto\Util\Functions as WF;

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

    public static function createFromFloat(float $timestamp, DateTimeZone $zone = null)
    {
        $timestamp = sprintf("%.6f", $timestamp); 
        $dt = DateTime::createFromFormat('U.u', $timestamp);
        $dt->setTimeZone($zone ?? new DateTimeZone(date_default_timezone_get()));
        return $dt;
    }
    
    public static function dateToFloat($date)
    {
        return (float)(self::toDT($date)->format('U.u'));
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

    public static function isBefore($l, $r)
    {
        return self::toDT($l) < self::toDT($r);
    }

    public static function isAfter($l, $r)
    {
        return self::toDT($l) > self::toDT($r);
    }

    public static function isPast($l)
    {
        $now = new DateTime();
        return self::toDT($l) < $now;
    }

    public static function isFuture($l)
    {
        $now = new DateTime();
        return self::toDT($l) > $now;
    }

    public static function now()
    {
        return self::createFromFloat(microtime(true));
    }

    public static function diff($l, $r)
    {
        $lf = self::dateToFloat($l);
        $rf = self::dateToFloat($r);

        return $lf - $rf;
    }

    public static function toDT($date)
    {
        if ($date instanceof DateTimeInterface)
            return $date;
        if ($date instanceof IntlCalendar)
            return $date->toDateTime();
        if (WF::is_int_val($date))
            return new DateTime("@" . $date);
        if (is_string($date))
            return new DateTime($date);
        throw new \InvalidArgumentException("Invalid date: " . WF::str($date));
    }
}
