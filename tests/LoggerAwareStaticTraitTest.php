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
use Psr\Log\NullLogger;
use Psr\Log\AbstractLogger;

/**
 * @covers Wedeto\Util\LoggerAwareStaticTrait
 * @covers Wedeto\Util\EmergencyLogger
 */
final class LoggerAwareStaticTraitTest extends TestCase
{
    public function testLoggerAwareness()
    {
        $a = new TestLoggerAwareStaticTrait;

        // Test that NullLogger is default
        $l = $a->getLogger();
        $this->assertInstanceOf(NullLogger::class, $l);

        // Create a mock logger
        $mock = $this->prophesize(AbstractLogger::class);
        $mock->debug("foo", [])->shouldBeCalled();
        $l = $mock->reveal();

        $a->setLogger($l);
        $ln = $a->getLogger();
        $this->assertEquals($l, $ln);
        $ln->debug("foo", []);

        // Back to Null
        $a->resetLogger();
        $l = $a->getLogger();
        $this->assertInstanceOf(NullLogger::class, $l);
    }

    public function testEmergencyLogger()
    {
        Hook::subscribe('Wedeto.Util.GetLogger', [$this, 'getLogger']);

        ob_start();
        $log = TestLoggerAwareStaticTrait2::getLogger();
        $res = ob_get_contents();
        ob_end_clean();

        $this->assertContains('Falling back to emergency logger', $res);
    }

    public function getLogger(Dictionary $args)
    {
        Hook::resetLogger();
        try
        {
            $res = Hook::execute('Wedeto.Util.GetLogger', ['class' => $this]);
        }
        catch (\Exception $e){
        }
        throw new RecursionException('Oops');
    }

    public function tearDown()
    {
        Hook::resetHook("Wedeto.Util.GetLogger");
    }
}

class TestLoggerAwareStaticTrait
{
    use LoggerAwareStaticTrait;
}

class TestLoggerAwareStaticTrait2
{
    use LoggerAwareStaticTrait;
}
