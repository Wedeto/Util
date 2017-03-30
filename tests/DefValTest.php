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

/**
 * @covers Wedeto\DefVal
 */
final class DefValTest extends TestCase
{
    public function testDefVal()
    {
        $a = new DefVal('test');
        $this->assertEquals('test', $a->getValue());

        $a = new DefVal(3);
        $this->assertEquals(3, $a->getValue());

        $a = new DefVal(null);
        $this->assertEquals(null, $a->getValue());

        $cl = new \StdClass;
        $a = new DefVal($cl);
        $this->assertEquals($cl, $a->getValue());

        $a = new DefVal([1, 2]);
        $this->assertEquals([1, 2], $a->getValue());

        $arr = ['a' => 'foo', 'b' => 'bar'];
        $a = new DefVal($arr);
        $this->assertEquals($arr, $a->getValue());
    }
}
