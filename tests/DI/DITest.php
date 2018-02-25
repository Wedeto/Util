<?php
/*
This is part of Wedeto, The WEb DEvelopment TOolkit.
It is published under the MIT Open Source License.

Copyright 2018, Egbert van der Wal

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

namespace Wedeto\Util\DI;

use PHPUnit\Framework\TestCase;
use Stdclass;

/**
 * @covers Wedeto\Util\DI\DI
 */
final class DITest extends TestCase
{
    public function testDIContextsStack()
    {
        $injector = DI::getInjector();
        $this->assertInstanceOf(Injector::class, $injector, "getInjector should provide an injector");

        $injector2 = DI::getInjector();
        $this->assertSame($injector, $injector2, "getInjector should return the same Injector instance");

        $injector3 = DI::startNewContext('test');
        $this->assertInstanceOf(Injector::class, $injector2, "startNewContext should provide an injector");

        $this->assertNotSame($injector, $injector3, "startNewContext should provide a different injector");
        $injector4 = DI::getInjector();

        $this->assertSame($injector3, $injector4, "getInjector should return new injector");

        $thrown = false;
        try
        {
            DI::destroyContext('foo');
        }
        catch (DIException $e)
        {
            $thrown = true;
            $msg = $e->getMessage();
            $this->assertTrue(strpos($msg, "foo is not at top of the stack") !== false, "Stack error");
        }

        $this->assertTrue($thrown, "A DIException should be thrown");

        $thrown = false;
        try
        {
            DI::destroyContext('test');
        }
        catch (DIException $e)
        {
            $thrown = true;
        }

        $this->assertFalse($thrown, "The context should be destroyed");

        $injector5 = DI::getInjector();
        $this->assertSame($injector, $injector5, "The original injector should be returned");
    }

    public function testIfInheritingWorks()
    {
        $injector = DI::getInjector();
        $instance = $injector->getInstance(Stdclass::class);

        $this->assertInstanceOf(Stdclass::class, $instance, "A Stdclass should be returned");
        $instance2 = $injector->getInstance(Stdclass::class);
        $this->assertSame($instance, $instance2, "The same instance should be returned");

        $injector2 = DI::startNewContext('test', true);
        $instance3 = $injector2->getInstance(Stdclass::class);
        $this->assertSame($instance, $instance2, "The same instance should be returned");

        $injector3 = DI::startNewContext('test2', false);
        $instance4 = $injector3->getInstance(Stdclass::class);
        $this->assertNotSame($instance, $instance4, "A new instance should be returned");

        DI::destroyContext('test2');
        DI::destroyContext('test');
    }
}
