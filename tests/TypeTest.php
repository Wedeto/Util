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
        $a = new Type(Type::TYPE_INT);

        $this->assertTrue($a->match(0));
        $this->assertTrue($a->match(1));
        $this->assertTrue($a->match(-1));
        $this->assertFalse($a->match(1.0));
        $this->assertFalse($a->match(1.5));
        $this->assertFalse($a->match("1"));
        $this->assertFalse($a->match("1.0"));
        $this->assertFalse($a->match([]));
        $this->assertFalse($a->match(null));
        $this->assertFalse($a->match(false));
        $this->assertFalse($a->match(true));

        $a = new Type(Type::TYPE_INT, ['min' => 3, 'max' => 15]);
        $this->assertFalse($a->match(1));
        $this->assertFalse($a->match(2));
        $this->assertTrue($a->match(3));
        $this->assertTrue($a->match(4));
        $this->assertTrue($a->match(14));
        $this->assertTrue($a->match(15));
        $this->assertFalse($a->match(16));

        $a = new Type(Type::TYPE_INT, ['min' => -3, 'max' => 3]);
        $this->assertFalse($a->match(-4));
        $this->assertTrue($a->match(-3));
        $this->assertTrue($a->match(-2));
        $this->assertTrue($a->match(2));
        $this->assertTrue($a->match(3));
        $this->assertFalse($a->match(4));

        $a = new Type(Type::TYPE_FLOAT);
        $this->assertTrue($a->match(0));
        $this->assertTrue($a->match(1));
        $this->assertTrue($a->match(-1));
        $this->assertTrue($a->match(1.0));
        $this->assertTrue($a->match(1.5));
        $this->assertTrue($a->match(3.14));
        $this->assertFalse($a->match("1"));
        $this->assertFalse($a->match("1.0"));
        $this->assertFalse($a->match([]));

        $a = new Type(Type::TYPE_FLOAT, ['min' => 3, 'max' => 15]);
        $this->assertFalse($a->match(1));
        $this->assertFalse($a->match(2));
        $this->assertTrue($a->match(3));
        $this->assertTrue($a->match(4));
        $this->assertTrue($a->match(14));
        $this->assertTrue($a->match(15));
        $this->assertFalse($a->match(16));

        $a = new Type(Type::TYPE_FLOAT, ['min' => -3, 'max' => 3]);
        $this->assertFalse($a->match(-4));
        $this->assertTrue($a->match(-3));
        $this->assertTrue($a->match(-2));
        $this->assertTrue($a->match(2));
        $this->assertTrue($a->match(3));
        $this->assertFalse($a->match(4));

        $a = new Type(Type::TYPE_NUMERIC);
        $this->assertTrue($a->match(0));
        $this->assertTrue($a->match(1));
        $this->assertTrue($a->match(-1));
        $this->assertTrue($a->match(1.0));
        $this->assertTrue($a->match(1.5));
        $this->assertTrue($a->match(3.14));
        $this->assertTrue($a->match("1"));
        $this->assertTrue($a->match("1.0"));
        $this->assertFalse($a->match([]));

        $a = new Type(Type::TYPE_NUMERIC, ['min' => 3, 'max' => 15]);
        $this->assertFalse($a->match(1));
        $this->assertFalse($a->match(2));
        $this->assertTrue($a->match(3));
        $this->assertTrue($a->match(4));
        $this->assertTrue($a->match(14));
        $this->assertTrue($a->match(15));
        $this->assertTrue($a->match("15"));
        $this->assertFalse($a->match(16));
        $this->assertFalse($a->match("16"));

        $a = new Type(Type::TYPE_NUMERIC, ['min' => -3, 'max' => 3]);
        $this->assertFalse($a->match("-4"));
        $this->assertFalse($a->match(-4));
        $this->assertTrue($a->match("-3"));
        $this->assertTrue($a->match(-3));
        $this->assertTrue($a->match(-2));
        $this->assertTrue($a->match(2));
        $this->assertTrue($a->match(3));
        $this->assertTrue($a->match("3"));
        $this->assertFalse($a->match(4));
        $this->assertFalse($a->match("4"));
    }

    public function testStrings()
    {
        $a = new Type(Type::TYPE_STRING);
        $this->assertFalse($a->match(0));
        $this->assertFalse($a->match(3.14));
        $this->assertFalse($a->match(null));
        $this->assertFalse($a->match(true));
        $this->assertFalse($a->match(true));

        $this->assertTrue($a->match(""));
        $this->assertTrue($a->match("foo"));
        $this->assertTrue($a->match("3.14"));

        $this->assertFalse($a->match([]));
        $this->assertFalse($a->match(new \StdClass));

        $a = new Type(Type::TYPE_STRING, ['min' => 3, 'max' => 5]);
        $this->assertTrue($a->match('123'));
        $this->assertTrue($a->match('12345'));
        $this->assertFalse($a->match('12'));
        $this->assertFalse($a->match('123456'));

        $a = new Type(Type::TYPE_STRING, ['regex' => '/^[a-zA-Z]*$/']);
        $this->assertFalse($a->match('123456'));
        $this->assertFalse($a->match('1'));
        $this->assertTrue($a->match(''));
        $this->assertTrue($a->match('abcd'));
        $this->assertTrue($a->match('xyz'));
        $this->assertFalse($a->match('xyz!'));

        $a = new Type(Type::TYPE_STRING, ['regex' => '/^\w*$/', 'min' => 1]);
        $this->assertFalse($a->match(''));
        $this->assertFalse($a->match('xyz!'));
        $this->assertTrue($a->match('a'));
        $this->assertTrue($a->match('abcd'));
    }

    public function testDates()
    {
        $a = new Type(Type::TYPE_DATE);

        $now = new DateTimeImmutable();
        $lastweek = $now->sub(new DateInterval("P7D"));
        $yesterday = $now->sub(new DateInterval("P1D"));
        $tomorrow = $now->add(new DateInterval("P1D"));
        $nextweek = $now->add(new DateInterval("P7D"));

        $this->assertTrue($a->match($now));
        $this->assertTrue($a->match($lastweek));
        $this->assertTrue($a->match($yesterday));
        $this->assertTrue($a->match($tomorrow));
        $this->assertTrue($a->match($nextweek));
        $this->assertFalse($a->match("2017-01-01"));
        $this->assertFalse($a->match(3));
        $this->assertFalse($a->match(null));

        $a = new Type(Type::TYPE_DATE, ['nullable' => true]);
        $this->assertTrue($a->match(null));

        $a = new Type(Type::TYPE_DATE, ['min' => $yesterday, 'max' => $tomorrow]);
        $this->assertTrue($a->match($now));
        $this->assertTrue($a->match($yesterday));
        $this->assertTrue($a->match($tomorrow));
        $this->assertFalse($a->match($lastweek));
        $this->assertFalse($a->match($nextweek));
    }

    public function testBool()
    {
        $a = new Type(Type::TYPE_BOOL);

        $this->assertTrue($a->match(true));
        $this->assertTrue($a->match(false));
        $this->assertFalse($a->match(null));
        $this->assertFalse($a->match(1));
        $this->assertFalse($a->match(3.14));
        $this->assertFalse($a->match("foo"));
        $this->assertFalse($a->match([]));
    }

    public function testArray()
    {
        $a = new Type(Type::TYPE_ARRAY);

        $dict = new Dictionary;
        $ao = new \ArrayObject;

        $this->assertTrue($a->match([]));
        $this->assertTrue($a->match($dict));
        $this->assertTrue($a->match($ao));
        $this->assertFalse($a->match(new \StdClass));
        $this->assertFalse($a->match(null));
        $this->assertFalse($a->match(false));
        $this->assertFalse($a->match(true));
    }

    public function testObject()
    {
        $a = new Type(Type::TYPE_OBJECT);

        $dict = new Dictionary;
        $dict2 = new TypedDictionary($dict);
        $std = new \stdClass;
        $dt = new \DateTime;
        $dti = new \DateTimeImmutable;

        $this->assertTrue($a->match($dict)); 
        $this->assertTrue($a->match($dict2)); 
        $this->assertTrue($a->match($std)); 
        $this->assertTrue($a->match($dt)); 
        $this->assertTrue($a->match($dti)); 

        $a = new Type(Type::TYPE_OBJECT, ['instanceof' => Dictionary::class]);
        $this->assertTrue($a->match($dict));
        $this->assertTrue($a->match($dict2));
        $this->assertFalse($a->match($std));
        $this->assertFalse($a->match($dt));
        $this->assertFalse($a->match($dti));

        $a = new Type(Type::TYPE_OBJECT, ['class' => Dictionary::class]);
        $this->assertTrue($a->match($dict));
        $this->assertFalse($a->match($dict2));
        $this->assertFalse($a->match($std));
        $this->assertFalse($a->match($dt));
        $this->assertFalse($a->match($dti));
    }

    public function testCustom()
    {
        $a = new Type(Type::TYPE_CUSTOM, ['nullable' => true, 'custom' => function ($val) {
            return is_scalar($val);
        }]);

        $this->assertTrue($a->match(1));
        $this->assertTrue($a->match(1.0));
        $this->assertTrue($a->match(false));
        $this->assertTrue($a->match(null));
        $this->assertTrue($a->match("string"));

        $this->assertFalse($a->match([]));
        $this->assertFalse($a->match(new \stdClass));
    }
}
