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
 * @covers Wedeto\Util\ErrorInterceptor
 */
final class ErrorInterceptorTest extends TestCase
{
    public function setUp()
    {
        ErrorInterceptor::registerErrorHandler();
    }

    public function tearDown()
    {
        ErrorInterceptor::unregisterErrorHandler();
    }

    /**
     * @covers Wedeto\Util\ErrorInterceptor::__construct
     * @covers Wedeto\Util\ErrorInterceptor::registerError
     * @covers Wedeto\Util\ErrorInterceptor::getInterceptedErrors
     * @covers Wedeto\Util\ErrorInterceptor::intercept
     */
    public function testInterceptUserNotice()
    {
        $a = new ErrorInterceptor(function () {
            trigger_error("Test error", E_USER_NOTICE);
        });

        $a->registerError(E_USER_NOTICE, 'Test error');
        $a->execute();

        $errors = $a->getInterceptedErrors();
        $this->assertEquals(1, count($errors));
    }

    /**
     * @covers Wedeto\Util\ErrorInterceptor::__construct
     * @covers Wedeto\Util\ErrorInterceptor::registerError
     * @covers Wedeto\Util\ErrorInterceptor::getInterceptedErrors
     * @covers Wedeto\Util\ErrorInterceptor::intercept
     */
    public function testInterceptWithUnregisteredError()
    {
        $a = new ErrorInterceptor(function () {
            trigger_error("Test warning", E_USER_NOTICE);
        });

        $a->registerError(E_USER_NOTICE, 'Test error');
        $this->expectException(\ErrorException::class);
        $this->expectExceptionMessage("Test warning");
        $a->execute();
    }

    public function testWithoutErrorHandler()
    {
        ErrorInterceptor::unregisterErrorHandler();

        $a = new ErrorInterceptor(function () {
            trigger_error("Test warning", E_USER_NOTICE);
        });

        $a->registerError(E_USER_NOTICE, 'Test error');
        $this->expectException(\ErrorException::class);
        $this->expectExceptionMessage("Test warning");
        $a->execute();
    }

    /**
     * @covers Wedeto\Util\ErrorInterceptor::__construct
     */
    public function testInvalidCallable()
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage("must be callable");
        $a = new ErrorInterceptor("1foo");
    }
}
