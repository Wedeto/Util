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

use PHPUnit\Framework\TestCase;

use DateTime;
use DateTimeImmutable;
use DateInterval;
use IntlCalendar;

/**
 * @covers Wedeto\Util\Date
 */
final class DateTest extends TestCase
{
    /**
     * @covers Wedeto\Util\Date::copy
     */
    public function testCopy()
    {
        $a = new DateTime("2017-01-01 00:00:00");
        $b = Date::copy($a);
        $this->assertEquals($a->getTimestamp(), $b->getTimestamp());

        $a = new DateTimeImmutable("2017-01-01 00:00:00");
        $b = Date::copy($a);
        $this->assertEquals($a->getTimestamp(), $b->getTimestamp());

        $a = new DateInterval('P5Y');
        $b = Date::copy($a);
        $this->assertEquals($a, $b);

        $a = new DateInterval('P5YT3M');
        $b = Date::copy($a);
        $this->assertEquals($a, $b);

        $a = new \StdClass();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid argument");
		Date::copy($a);
    }

    /**
     * @covers Wedeto\Util\Date::lessThan
     * @covers Wedeto\Util\Date::lessThanOrEqual
     * @covers Wedeto\Util\Date::equal
     * @covers Wedeto\Util\Date::greaterThan
     * @covers Wedeto\Util\Date::greaterThanOrEqual
     */
    public function testCompareIntervals()
    {
        $a = new DateInterval('P40D');
        $b = new DateInterval('P20D');
        $c = new DateInterval('P60D');
        $d = new DateInterval('P60DT10S');
        $e = new DateInterval('P59DT23H59M50S');

        $this->assertTrue(Date::lessThan($b, $a));
        $this->assertTrue(Date::lessThan($a, $c));
        $this->assertTrue(Date::lessThan($a, $d));
        $this->assertTrue(Date::lessThan($a, $e));
        $this->assertFalse(Date::greaterThan($b, $a));
        $this->assertFalse(Date::greaterThan($a, $c));
        $this->assertFalse(Date::greaterThan($a, $d));
        $this->assertFalse(Date::greaterThan($a, $e));
        $this->assertTrue(Date::lessThanOrEqual($a, $a));
        $this->assertTrue(Date::greaterThanOrEqual($a, $a));
        $this->assertTrue(Date::equal($a, $a));
        $this->assertFalse(Date::equal($a, $b));
        $this->assertFalse(Date::equal($a, $c));
        $this->assertFalse(Date::equal($a, $d));
        $this->assertFalse(Date::equal($a, $e));

        $this->assertTrue(Date::lessThan($b, $c));
        $this->assertTrue(Date::lessThan($b, $d));
        $this->assertTrue(Date::lessThan($b, $e));
        $this->assertFalse(Date::greaterThan($b, $c));
        $this->assertFalse(Date::greaterThan($b, $d));
        $this->assertFalse(Date::greaterThan($b, $e));
        $this->assertTrue(Date::lessThanOrEqual($b, $b));
        $this->assertTrue(Date::greaterThanOrEqual($b, $b));
        $this->assertFalse(Date::equal($b, $a));
        $this->assertTrue(Date::equal($b, $b));
        $this->assertFalse(Date::equal($b, $c));
        $this->assertFalse(Date::equal($b, $d));
        $this->assertFalse(Date::equal($b, $e));

        $this->assertTrue(Date::lessThan($c, $d));
        $this->assertTrue(Date::lessThan($e, $c));
        $this->assertFalse(Date::greaterThan($c, $d));
        $this->assertFalse(Date::greaterThan($e, $c));
        $this->assertTrue(Date::lessThanOrEqual($c, $c));
        $this->assertTrue(Date::greaterThanOrEqual($c, $c));
        $this->assertFalse(Date::equal($c, $a));
        $this->assertFalse(Date::equal($c, $b));
        $this->assertTrue(Date::equal($c, $c));
        $this->assertFalse(Date::equal($c, $d));
        $this->assertFalse(Date::equal($c, $e));

        $this->assertTrue(Date::lessThan($e, $d));
        $this->assertFalse(Date::greaterThan($e, $d));
        $this->assertTrue(Date::lessThanOrEqual($d, $d));
        $this->assertTrue(Date::greaterThanOrEqual($d, $d));
        $this->assertFalse(Date::equal($d, $a));
        $this->assertFalse(Date::equal($d, $b));
        $this->assertFalse(Date::equal($d, $c));
        $this->assertTrue(Date::equal($d, $d));
        $this->assertFalse(Date::equal($d, $e));

        $this->assertTrue(Date::lessThanOrEqual($e, $e));
        $this->assertTrue(Date::greaterThanOrEqual($e, $e));
        $this->assertFalse(Date::equal($e, $a));
        $this->assertFalse(Date::equal($e, $b));
        $this->assertFalse(Date::equal($e, $c));
        $this->assertFalse(Date::equal($e, $d));
        $this->assertTrue(Date::equal($e, $e));
    }

    /**
     * @covers Wedeto\Util\Date::copy
     * @covers Wedeto\Util\Date::isBefore
     * @covers Wedeto\Util\Date::isAfter
     * @covers Wedeto\Util\Date::isPast
     * @covers Wedeto\Util\Date::isFuture
     */
    public function testDateCompare()
    {
        $a = new DateTime();
        $past = Date::copy($a);
        $future = Date::copy($a);

        $offset = new DateInterval("P60D");
        $future->add($offset);

        $offset->invert = 1;
        $past->add($offset);

        $this->assertTrue(Date::isBefore($past, $future));
        $this->assertTrue(Date::isAfter($future, $past));
        $this->assertTrue(Date::isPast($past));
        $this->assertFalse(Date::isPast($future));
        $this->assertFalse(Date::isFuture($past));
        $this->assertTrue(Date::isFuture($future));
    }

    /**
     * @covers Wedeto\Util\Date::copy
     * @covers Wedeto\Util\Date::isBefore
     * @covers Wedeto\Util\Date::isAfter
     * @covers Wedeto\Util\Date::isPast
     * @covers Wedeto\Util\Date::isFuture
     */
    public function testDateCompareWithIntlCalendarAndIntAndString()
    {
        $a = new DateTime();
        $past = Date::copy($a);
        $future = Date::copy($a);

        $offset = new DateInterval("P60D");
        $future->add($offset);

        $offset->invert = 1;
        $past->add($offset);

        $past = IntlCalendar::fromDateTime($past);
        $this->assertInstanceOf(IntlCalendar::class, $past);

        $this->assertTrue(Date::isBefore($past, $future));
        $this->assertTrue(Date::isAfter($future, $past));
        $this->assertTrue(Date::isPast($past));
        $this->assertFalse(Date::isPast($future));
        $this->assertFalse(Date::isFuture($past));
        $this->assertTrue(Date::isFuture($future));

        $future = $future->format('Y-m-d H:i:s');
        $this->assertTrue(is_string($future));
        $this->assertTrue(Date::isBefore($past, $future));
        $this->assertTrue(Date::isAfter($future, $past));
        $this->assertTrue(Date::isPast($past));
        $this->assertFalse(Date::isPast($future));
        $this->assertFalse(Date::isFuture($past));
        $this->assertTrue(Date::isFuture($future));

        $past = $past->toDateTime()->getTimestamp();
        $this->assertTrue(is_int($past));
        $this->assertTrue(is_string($future));
        $this->assertTrue(Date::isBefore($past, $future));
        $this->assertTrue(Date::isAfter($future, $past));
        $this->assertTrue(Date::isPast($past));
        $this->assertFalse(Date::isPast($future));
        $this->assertFalse(Date::isFuture($past));
        $this->assertTrue(Date::isFuture($future));

        $past = new \StdClass;
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid date');
        Date::isPast($past);
    }

    public function testCreateFromFloat()
    {
        $m = microtime(true);
        $dt = Date::now();

        $fl = Date::dateToFloat($dt);

        $this->assertTrue($fl - $m < 0.001);
    }

    public function testDiffDates()
    {
        $now = new DateTime();
        $tomorrow = clone $now;
        $tomorrow->add(new DateInterval("P1D"));

        $diff = Date::diff($tomorrow, $now);
        $this->assertEquals($diff, Date::SECONDS_IN_DAY);
    }
}

