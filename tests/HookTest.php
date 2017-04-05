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
 * @covers Wedeto\Util\Hook
 */
final class HookTest extends TestCase
{
    public function setUp()
    {
        $this->tearDown();
    }

    public function tearDown()
    {
        $hooks = Hook::getRegisteredHooks();
        foreach ($hooks as $h)
            Hook::resetHook($h);

        $this->assertEmpty(Hook::getRegisteredHooks());
    }

    public function testHook()
    {
        $called = false;
        Hook::subscribe("foo.hook", function (Dictionary $resp) use ($called) {$called = true;}, 3);

        $response = Hook::execute("foo.hook", []);
        $this->assertInstanceOf(Dictionary::class, $response);

        $this->assertEquals(1, Hook::getExecuteCount("foo.hook"));

        $response = Hook::execute("foo.hook", []);
        $this->assertInstanceOf(Dictionary::class, $response);

        $this->assertEquals(2, Hook::getExecuteCount("foo.hook"));

        $subscribers = Hook::getSubscribers('foo.hook');
        $this->assertEquals(1, count($subscribers));
        foreach ($subscribers as $sub)
            $this->assertTrue(is_callable($sub));
    }

    public function testPauseAndUnpause()
    {
        $data = new \stdClass;
        $data->count = 0;
        Hook::subscribe("foo.hook", function (Dictionary $resp) use ($data) {++$data->count;}, 3);

        $response = Hook::execute("foo.hook", []);
        $this->assertInstanceOf(Dictionary::class, $response);
        $this->assertEquals(1, $data->count);

        $this->assertEquals(1, Hook::getExecuteCount("foo.hook"));

        $called = false;
        Hook::pause('foo.hook');
        $response = Hook::execute("foo.hook", []);
        $this->assertInstanceOf(Dictionary::class, $response);
        $this->assertEquals(1, $data->count);
        $this->assertEquals(2, Hook::getExecuteCount("foo.hook"));

        Hook::resume('foo.hook');
        $response = Hook::execute("foo.hook", []);
        $this->assertEquals(2, $data->count);
        $this->assertEquals(3, Hook::getExecuteCount("foo.hook"));

        Hook::pause('foo.hook');
        Hook::pause('foo.hook2');
    }

    public function testGetSubscribers()
    {
        $l = Hook::getSubscribers('foo.hook');
        $this->assertEmpty($l);

        $data = new \stdClass;
        $data->count = 0;
        Hook::subscribe("foo.hook", function (Dictionary $resp) use ($data) {++$data->count;}, 1);

        $l = Hook::getSubscribers('foo.hook');
        $this->assertEquals(1, count($l));

        $data2 = new \stdClass;
        $data2->count = 0;
        Hook::subscribe("foo.hook", function (Dictionary $resp) use ($data, $data2) {$data2->count += $data->count;}, 2);

        $l = Hook::getSubscribers('foo.hook');
        $this->assertEquals(2, count($l));

        Hook::execute("foo.hook", []);
        $this->assertEquals(1, $data->count);
        $this->assertEquals(1, $data2->count);

        Hook::execute("foo.hook", []);
        $this->assertEquals(2, $data->count);
        $this->assertEquals(3, $data2->count);

        Hook::execute("foo.hook", []);
        $this->assertEquals(3, $data->count);
        $this->assertEquals(6, $data2->count);

        // Add another one to the mix, and have it execute before data2
        Hook::subscribe("foo.hook", function (Dictionary $resp) use ($data) {$data->count += 2;}, 1);

        Hook::execute("foo.hook", []);
        $this->assertEquals(6, $data->count);
        $this->assertEquals(12, $data2->count);
    }

    public function testCallbackWithNoParameters()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Hook must accept exactly one argument of type Dictionary");
        Hook::subscribe("foo.hook", function () {}, 1);
    }

    public function testCallbackWithTwoParameters()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Hook must accept exactly one argument of type Dictionary");
        Hook::subscribe("foo.hook", function ($arg1, $arg2) {}, 1);
    }

    public function testCallbackWithOneInvalidParameter()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Hook must accept exactly one argument of type Dictionary");
        Hook::subscribe("foo.hook", function ($arg) {}, 1);
    }

    public function testHookWithNoDots()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Hook name must consist of at least two parts");
        Hook::subscribe("foohook", function ($arg) {}, 1);
    }

    public function testHookRecursion()
    {
        Hook::subscribe("foo.hook", function (Dictionary $arg) {
            Hook::execute("foo.hook", []);
        });

        $response = Hook::execute("foo.hook", []);
        $this->assertEquals(1, count($response));
        $this->assertEquals('foo.hook', $response['hook']);
    }

    public function testInterruptHook()
    {
        $data = new \stdClass;
        $data->count = 0;

        Hook::subscribe('foo.hook', function (Dictionary $arg1) use ($data) { ++$data->count; }, 5);
        Hook::subscribe('foo.hook', function (Dictionary $arg1) use ($data) { ++$data->count; }, 6);
        Hook::subscribe('foo.hook', function (Dictionary $arg1) use ($data) { throw new HookInterrupted(); }, 7);
        Hook::subscribe('foo.hook', function (Dictionary $arg1) use ($data) { ++$data->count; }, 8);
        Hook::subscribe('foo.hook', function (Dictionary $arg1) use ($data) { ++$data->count; }, 9);

        Hook::execute("foo.hook", []);
        $this->assertEquals(2, $data->count, "Hook 5 and 6 should execute but 8 and 9 are blocked by 7");
    }

    public function testTypeSafeHook()
    {
        $dict = new TypedDictionary(['string' => Type::STRING, 'int' => Type::INT]);

        $data = new \stdClass;
        $data->hook_param = null;

        Hook::subscribe('foo.hook', function (Dictionary $param) use ($data) { $data->hook_param = $param; });

        $rdict = Hook::execute('foo.hook', $dict);
        $this->assertInstanceOf(TypedDictionary::class, $rdict);
    }
}
