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

use DateTimeImmutable;
use DateInterval;

final class TypeTest extends TestCase
{
    public function testNumericTypes()
    {
        $a = new Type(Type::INT);
        $this->assertEquals(Type::INT, $a->getType());
        $this->assertFalse($a->isNullable());

        $this->assertTrue($a->validate(0));
        $this->assertTrue($a->validate(1));
        $this->assertTrue($a->validate(-1));
        $this->assertFalse($a->validate(1.0));
        $this->assertFalse($a->validate(1.5));
        $this->assertFalse($a->validate("1"));
        $this->assertFalse($a->validate("1.0"));
        $this->assertFalse($a->validate([]));
        $this->assertFalse($a->validate(null));
        $this->assertFalse($a->validate(false));
        $this->assertFalse($a->validate(true));

        $a = new Type(Type::INT, ['min_range' => 3, 'max_range' => 15]);
        $this->assertFalse($a->isNullable());
        $this->assertFalse($a->validate(1));
        $this->assertFalse($a->validate(2));
        $this->assertTrue($a->validate(3));
        $this->assertTrue($a->validate(4));
        $this->assertTrue($a->validate(14));
        $this->assertTrue($a->validate(15));
        $this->assertFalse($a->validate(16));

        $a = new Type(Type::INT, ['min_range' => -3, 'max_range' => 3]);
        $this->assertFalse($a->isNullable());
        $this->assertFalse($a->validate(-4));
        $this->assertTrue($a->validate(-3));
        $this->assertTrue($a->validate(-2));
        $this->assertTrue($a->validate(2));
        $this->assertTrue($a->validate(3));
        $this->assertFalse($a->validate(4));

        $a = new Type(Type::FLOAT);
        $this->assertFalse($a->isNullable());
        $this->assertTrue($a->validate(0));
        $this->assertTrue($a->validate(1));
        $this->assertTrue($a->validate(-1));
        $this->assertTrue($a->validate(1.0));
        $this->assertTrue($a->validate(1.5));
        $this->assertTrue($a->validate(3.14));
        $this->assertFalse($a->validate("1"));
        $this->assertFalse($a->validate("1.0"));
        $this->assertFalse($a->validate([]));

        $a = new Type(Type::FLOAT, ['min_range' => 3, 'max_range' => 15]);
        $this->assertFalse($a->isNullable());
        $this->assertFalse($a->validate(1));
        $this->assertFalse($a->validate(2));
        $this->assertTrue($a->validate(3));
        $this->assertTrue($a->validate(4));
        $this->assertTrue($a->validate(14));
        $this->assertTrue($a->validate(15));
        $this->assertFalse($a->validate(16));

        $a = new Type(Type::FLOAT, ['min_range' => -3, 'max_range' => 3]);
        $this->assertFalse($a->isNullable());
        $this->assertEquals(Type::FLOAT, $a->getType());
        $this->assertFalse($a->validate(-4));
        $this->assertTrue($a->validate(-3));
        $this->assertTrue($a->validate(-2));
        $this->assertTrue($a->validate(2));
        $this->assertTrue($a->validate(3));
        $this->assertFalse($a->validate(4));

        $a = new Type(Type::NUMERIC);
        $this->assertFalse($a->isNullable());
        $this->assertTrue($a->validate(0));
        $this->assertTrue($a->validate(1));
        $this->assertTrue($a->validate(-1));
        $this->assertTrue($a->validate(1.0));
        $this->assertTrue($a->validate(1.5));
        $this->assertTrue($a->validate(3.14));
        $this->assertTrue($a->validate("1"));
        $this->assertTrue($a->validate("1.0"));
        $this->assertFalse($a->validate([]));

        $a = new Type(Type::NUMERIC, ['min_range' => 3, 'max_range' => 15]);
        $this->assertFalse($a->isNullable());
        $this->assertFalse($a->validate(1));
        $this->assertFalse($a->validate(2));
        $this->assertTrue($a->validate(3));
        $this->assertTrue($a->validate(4));
        $this->assertTrue($a->validate(14));
        $this->assertTrue($a->validate(15));
        $this->assertTrue($a->validate("15"));
        $this->assertFalse($a->validate(16));
        $this->assertFalse($a->validate("16"));

        $a = new Type(Type::NUMERIC, ['min_range' => -3, 'max_range' => 3]);
        $this->assertFalse($a->isNullable());
        $this->assertEquals(Type::NUMERIC, $a->getType());
        $this->assertFalse($a->validate("-4"));
        $this->assertFalse($a->validate(-4));
        $this->assertTrue($a->validate("-3"));
        $this->assertTrue($a->validate(-3));
        $this->assertTrue($a->validate(-2));
        $this->assertTrue($a->validate(2));
        $this->assertTrue($a->validate(3));
        $this->assertTrue($a->validate("3"));
        $this->assertFalse($a->validate(4));
        $this->assertFalse($a->validate("4"));
    }

    public function testStrings()
    {
        $a = new Type(Type::STRING);
        $this->assertEquals(Type::STRING, $a->getType());
        $this->assertFalse($a->isNullable());
        $this->assertFalse($a->validate(0));
        $this->assertFalse($a->validate(3.14));
        $this->assertFalse($a->validate(null));
        $this->assertFalse($a->validate(true));
        $this->assertFalse($a->validate(true));

        $this->assertTrue($a->validate(""));
        $this->assertTrue($a->validate("foo"));
        $this->assertTrue($a->validate("3.14"));

        $this->assertFalse($a->validate([]));
        $this->assertFalse($a->validate(new \StdClass));

        $a = new Type(Type::STRING, ['min_range' => 3, 'max_range' => 5]);
        $this->assertTrue($a->validate('123'));
        $this->assertTrue($a->validate('12345'));
        $this->assertFalse($a->validate('12'));
        $this->assertFalse($a->validate('123456'));

        $a = new Type(Type::STRING, ['regex' => '/^[a-zA-Z]*$/']);
        $this->assertFalse($a->validate('123456'));
        $this->assertFalse($a->validate('1'));
        $this->assertTrue($a->validate(''));
        $this->assertTrue($a->validate('abcd'));
        $this->assertTrue($a->validate('xyz'));
        $this->assertFalse($a->validate('xyz!'));

        $a = new Type(Type::STRING, ['regex' => '/^\w*$/', 'min_range' => 1]);
        $this->assertFalse($a->validate(''));
        $this->assertFalse($a->validate('xyz!'));
        $this->assertTrue($a->validate('a'));
        $this->assertTrue($a->validate('abcd'));
    }

    public function testDates()
    {
        $a = new Type(Type::DATE);
        $this->assertEquals(Type::DATE, $a->getType());

        $now = new DateTimeImmutable();
        $lastweek = $now->sub(new DateInterval("P7D"));
        $yesterday = $now->sub(new DateInterval("P1D"));
        $tomorrow = $now->add(new DateInterval("P1D"));
        $nextweek = $now->add(new DateInterval("P7D"));

        $this->assertTrue($a->validate($now));
        $this->assertTrue($a->validate($lastweek));
        $this->assertTrue($a->validate($yesterday));
        $this->assertTrue($a->validate($tomorrow));
        $this->assertTrue($a->validate($nextweek));
        $this->assertFalse($a->validate("2017-01-01"));
        $this->assertFalse($a->validate(3));
        $this->assertFalse($a->validate(null));

        $a = new Type(Type::DATE, ['nullable' => true]);
        $this->assertTrue($a->isNullable());
        $this->assertTrue($a->validate(null));

        $a = new Type(Type::DATE, ['min_range' => $yesterday, 'max_range' => $tomorrow]);
        $this->assertTrue($a->validate($now));
        $this->assertTrue($a->validate($yesterday));
        $this->assertTrue($a->validate($tomorrow));
        $this->assertFalse($a->validate($lastweek));
        $this->assertFalse($a->validate($nextweek));

        if (class_exists('IntlCalendar'))
        {
            $cal = \IntlCalendar::createInstance();

            $a = new Type(Type::DATE, ['min_range' => $yesterday, 'max_range' => $tomorrow]);
            $this->assertTrue($a->validate($cal));

            $cal->add(\IntlCalendar::FIELD_DATE, 5);
            $this->assertFalse($a->validate($cal));
        }

        $str = date('Y-m-d');
        $a = new Type(Type::DATE, ['min_range' => $yesterday, 'max_range' => $tomorrow, 'unstrict' => false]);
        $this->assertFalse($a->validate($str));

        $a = new Type(Type::DATE, ['min_range' => $yesterday, 'max_range' => $tomorrow, 'unstrict' => true]);
        $this->assertTrue($a->validate($str));

        $str = date('Y-m-d', time() + 86400 * 7);
        $this->assertFalse($a->validate($str));

        $str = date('Y-m-d', time() - 86400 * 7);
        $this->assertFalse($a->validate($str));

        $str = "foo bar not a date";
        $this->assertFalse($a->validate($str));
    }

    public function testErrorMessages()
    {
        $expected = ['msg' => '', 'context' => ['min' => null, 'max' => null, 'type' => 'Integral value']];

        $a = new Type(Type::INT);
        $this->assertEquals(['msg' => 'Required field'], $a->getErrorMessage(null));
        $expected['msg'] = '{type} required';
        $this->assertEquals($expected, $a->getErrorMessage(1));

        $a = new Type(Type::INT, ['min_range' => 5, 'max_range' => 10]);
        $expected['msg'] = '{type} between {min} and {max} is required';
        $expected['context']['min'] = 5;
        $expected['context']['max'] = 10;
        $this->assertEquals($expected, $a->getErrorMessage(1));

        $a = new Type(Type::INT, ['min_range' => 5, 'max_range' => null]);
        $expected['msg'] = '{type} equal to or greater than {min} is required';
        $expected['context']['min'] = 5;
        $expected['context']['max'] = null;
        $this->assertEquals($expected, $a->getErrorMessage(1));

        $a = new Type(Type::INT, ['min_range' => null, 'max_range' => 10]);
        $expected['msg'] = '{type} less than or equal to {max} is required';
        $expected['context']['min'] = null;
        $expected['context']['max'] = 10;
        $this->assertEquals($expected, $a->getErrorMessage(1));
        
        $a = new Type(Type::FLOAT);
        $this->assertEquals(['msg' => 'Required field'], $a->getErrorMessage(null));
        $expected['context']['type'] = 'Number';
        $expected['context']['max'] = null;
        $expected['msg'] = '{type} required';
        $this->assertEquals($expected, $a->getErrorMessage(1));

        $a = new Type(Type::FLOAT, ['min_range' => 5, 'max_range' => 10]);
        $expected['msg'] = '{type} between {min} and {max} is required';
        $expected['context']['min'] = 5;
        $expected['context']['max'] = 10;
        $this->assertEquals($expected, $a->getErrorMessage(1));

        $a = new Type(Type::FLOAT, ['min_range' => 5, 'max_range' => null]);
        $expected['msg'] = '{type} equal to or greater than {min} is required';
        $expected['context']['min'] = 5;
        $expected['context']['max'] = null;
        $this->assertEquals($expected, $a->getErrorMessage(1));

        $a = new Type(Type::FLOAT, ['min_range' => null, 'max_range' => 10]);
        $expected['msg'] = '{type} less than or equal to {max} is required';
        $expected['context']['min'] = null;
        $expected['context']['max'] = 10;
        $this->assertEquals($expected, $a->getErrorMessage(1));

        $a = new Type(Type::BOOL, ['min_range' => null, 'max_range' => 10]);
        $this->assertEquals(['msg' => 'True or false required'], $a->getErrorMessage(1));

        $a = new Type(Type::STRING, ['min_range' => null, 'max_range' => null]);
        $this->assertEquals(['msg' => 'Please enter a value'], $a->getErrorMessage(''));

        $a = new Type(Type::STRING, ['min_range' => 5, 'max_range' => null]);
        $expected['msg'] = 'At least {min} characters expected';
        $expected['context']['min'] = 5;
        $expected['context']['max'] = null;
        $expected['context']['type'] = 'string';
        $this->assertEquals($expected, $a->getErrorMessage(''));

        $a = new Type(Type::STRING, ['min_range' => null, 'max_range' => 10]);
        $expected['msg'] = 'At most {max} characters expected';
        $expected['context']['min'] = null;
        $expected['context']['max'] = 10;
        $this->assertEquals($expected, $a->getErrorMessage(''));

        $a = new Type(Type::STRING, ['min_range' => 5, 'max_range' => 10]);
        $expected['msg'] = 'Between {min} and {max} characters expected';
        $expected['context']['min'] = 5;
        $expected['context']['max'] = 10;
        $this->assertEquals($expected, $a->getErrorMessage(''));

        $a = new Type(Type::SCALAR, ['min_range' => null, 'max_range' => null]);
        $this->assertEquals(['msg' => 'Please enter a value'], $a->getErrorMessage(''));

        $a = new Type(Type::SCALAR, ['min_range' => 5, 'max_range' => null]);
        $expected['msg'] = 'At least {min} characters expected';
        $expected['context']['min'] = 5;
        $expected['context']['max'] = null;
        $expected['context']['type'] = 'scalar';
        $this->assertEquals($expected, $a->getErrorMessage(''));

        $a = new Type(Type::SCALAR, ['min_range' => null, 'max_range' => 10]);
        $expected['msg'] = 'At most {max} characters expected';
        $expected['context']['min'] = null;
        $expected['context']['max'] = 10;
        $this->assertEquals($expected, $a->getErrorMessage(''));

        $a = new Type(Type::SCALAR, ['min_range' => 5, 'max_range' => 10]);
        $expected['msg'] = 'Between {min} and {max} characters expected';
        $expected['context']['min'] = 5;
        $expected['context']['max'] = 10;
        $this->assertEquals($expected, $a->getErrorMessage(''));

        $today = new DateTimeImmutable;
        $yesterday = $today->sub(new DateInterval('P1D'));
        $tomorrow = $today->add(new DateInterval('P1D'));

        $a = new Type(Type::DATE, ['min_range' => null, 'max_range' => null]);
        $this->assertEquals(['msg' => 'Date expected'], $a->getErrorMessage(''));

        $a = new Type(Type::DATE, ['min_range' => $yesterday, 'max_range' => null]);
        $expected['msg'] = 'Date after {min} expected';
        $expected['context']['min'] = $yesterday;
        $expected['context']['max'] = null;
        $expected['context']['type'] = "date";
        $this->assertEquals($expected, $a->getErrorMessage(''));

        $a = new Type(Type::DATE, ['min_range' => null, 'max_range' => $tomorrow]);
        $expected['msg'] = 'Date before {max} expected';
        $expected['context']['min'] = null;
        $expected['context']['max'] = $tomorrow;
        $this->assertEquals($expected, $a->getErrorMessage(''));

        $a = new Type(Type::DATE, ['min_range' => $yesterday, 'max_range' => $tomorrow]);
        $expected['msg'] = 'Date between {min} and {max} expected';
        $expected['context']['min'] = $yesterday;
        $expected['context']['max'] = $tomorrow;
        $this->assertEquals($expected, $a->getErrorMessage(''));

        $a = new Type(Type::ARRAY);
        $this->assertEquals(['msg' => 'Array expected'], $a->getErrorMessage(''));

        $a = new Type(Type::OBJECT);
        $expected['msg'] = "Value matching filter {type} expected";
        $expected['context']['min'] = null;
        $expected['context']['max'] = null;
        $expected['context']['type'] = 'object';
        $this->assertEquals($expected, $a->getErrorMessage(''));

        $a = new Type(Type::OBJECT, ['error' => ['msg' => 'Foo barred']]);
        $expected['msg'] = "Foo barred";
        $expected['context']['min'] = null;
        $expected['context']['max'] = null;
        $expected['context']['type'] = 'object';
        $this->assertEquals($expected, $a->getErrorMessage(''));
    }

    public function testBool()
    {
        $a = new Type(Type::BOOL);
        $this->assertEquals(Type::BOOL, $a->getType());

        $this->assertTrue($a->validate(true));
        $this->assertTrue($a->validate(false));
        $this->assertFalse($a->validate(null));
        $this->assertFalse($a->validate(1));
        $this->assertFalse($a->validate(3.14));
        $this->assertFalse($a->validate("foo"));
        $this->assertFalse($a->validate([]));
    }

    public function testArray()
    {
        $a = new Type(Type::ARRAY);
        $this->assertEquals(Type::ARRAY, $a->getType());

        $dict = new Dictionary;
        $ao = new \ArrayObject;

        $this->assertTrue($a->validate([]));
        $this->assertTrue($a->validate($dict));
        $this->assertTrue($a->validate($ao));
        $this->assertFalse($a->validate(new \StdClass));
        $this->assertFalse($a->validate(null));
        $this->assertFalse($a->validate(false));
        $this->assertFalse($a->validate(true));
    }

    public function testObject()
    {
        $a = new Type(Type::OBJECT);
        $this->assertEquals(Type::OBJECT, $a->getType());

        $dict = new Dictionary;
        $dict2 = new TypedDictionary($dict);
        $std = new \stdClass;
        $dt = new \DateTime;
        $dti = new \DateTimeImmutable;

        $this->assertTrue($a->validate($dict)); 
        $this->assertTrue($a->validate($dict2)); 
        $this->assertTrue($a->validate($std)); 
        $this->assertTrue($a->validate($dt)); 
        $this->assertTrue($a->validate($dti)); 

        $a = new Type(Type::OBJECT, ['instanceof' => Dictionary::class]);
        $this->assertTrue($a->validate($dict));
        $this->assertTrue($a->validate($dict2));
        $this->assertFalse($a->validate($std));
        $this->assertFalse($a->validate($dt));
        $this->assertFalse($a->validate($dti));

        $a = new Type(Type::OBJECT, ['class' => Dictionary::class]);
        $this->assertTrue($a->validate($dict));
        $this->assertFalse($a->validate($dict2));
        $this->assertFalse($a->validate($std));
        $this->assertFalse($a->validate($dt));
        $this->assertFalse($a->validate($dti));

        $this->assertFalse($a->validate("foobar"));
    }

    public function testCustom()
    {
        $a = new Type(Type::VALIDATE_CUSTOM, ['nullable' => true, 'custom' => function ($val) {
            return is_scalar($val);
        }]);
        $this->assertTrue($a->isNullable());

        $this->assertTrue($a->validate(1));
        $this->assertTrue($a->validate(1.0));
        $this->assertTrue($a->validate(false));
        $this->assertTrue($a->validate(null));
        $this->assertTrue($a->validate("string"));

        $this->assertFalse($a->validate([]));
        $this->assertFalse($a->validate(new \stdClass));
    }

    public function testFilters()
    {
        $a = new Type(Type::VALIDATE_FILTER, ['filter' => FILTER_VALIDATE_EMAIL]);

        $this->assertTrue($a->validate('test@example.com'));
        $this->assertFalse($a->validate('foo@bar'));
        $this->assertFalse($a->validate('testexample.com'));
    }

    public function testScalar()
    {
        $a = new Type(Type::SCALAR);
        
        $this->assertTrue($a->validate(1));
        $this->assertTrue($a->validate(1.0));
        $this->assertTrue($a->validate(false));
        $this->assertTrue($a->validate("string"));

        $this->assertFalse($a->validate([]));
        $this->assertFalse($a->validate(new \stdClass));
    }

    public function testUnknownThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown type: foo");
        $a = new Type("foo");
    }

    public function testFilter()
    {
        $a = new Type(Type::INT, ['unstrict' => true]);
        $this->assertTrue(is_int($a->filter('3')));

        $a = new Type(Type::VALIDATE_FILTER, ['filter' => FILTER_VALIDATE_BOOLEAN]);
        $this->assertTrue(is_bool($a->filter('true')));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Not a valid value for ');
        $a->filter('3');
    }
}
