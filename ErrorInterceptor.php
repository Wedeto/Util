<?php
/*
This is part of WASP, the Web Application Software Platform.
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
namespace WASP\Util;

use ErrorException;
use InvalidArgumentException;

/**
 * ErrorInterceptor registers itself to receive all PHP errors. Any errors
 * caught are thrown as ErrorExceptions exceptions. However, you can create a
 * ErrorInterceptor object that wraps a function call, and any errors generated
 * within are collected rather than thrown. This allows code that generates warnings
 * to run and complete, while still catching the errors. One example is 'session_start()',
 * which starts a session even if the cookie can not be sent.
 * 
 * The main idea is: no errors or warnings should be generated within WASP in normal operation,
 * but in certain situations you can't avoid them and need to continue.
 */
class ErrorInterceptor
{
    /** The function or method to call */
    protected $func;

    /** The intercepted PHP errors during execution */
    protected $intercepted = array();

    /** The expected PHP errors */
    protected $expected = array();

    /**
     * Create the interceptor wrapping a specific function call
     *
     * @param callable $func What to wrap
     */
    public function __construct(callable $func)
    {
        if (self::$interceptor_stack === null)
            self::registerErrorHandler();

        $this->func = $func;
    }

    /**
     * Register an error to be intercepted when produced by the callback. Any
     * error matching the $errno and containing $errstr as part of their error string,
     * will be intercepted.
     *
     * @param int $errno The error type to register
     * @param string $errstr A part of the expected error message
     * @return WASP\Util\ErrorInterceptor Provides fluent interface
     */
    public function registerError($errno, $errstr)
    {
        $this->expected[] = array($errno, $errstr);
        return $this;
    }

    /**
     * Execute the configured callback
     * @params Any parameters to pass to the callback
     * @return mixed The return value of the callback
     */
    public function execute()
    {
        array_push(self::$interceptor_stack, $this);
        $response = null;
        try
        {
            $response = call_user_func_array($this->func, func_get_args());
        }
        finally
        {
            array_pop(self::$interceptor_stack);
        }
        return $response;
    }
    
    /**
     * @return array A list of ErrorExceptions that were intercepted before being thrown
     */
    public function getInterceptedErrors()
    {
        return $this->intercepted;
    }

    /**
     * Check if the produced error should be intercepted by the interceptor, based on the
     * defined expected errors.
     *
     * @param int $errno Error type
     * @param string $errstr The error string
     * @param string $errfile The filfilfilfile the error occured in
     * @param int $errline The line the error occured on
     * @param array $errcontext Local variables
     * @return bool True if the message was intercepted, false if not
     */
    protected function intercept($errno, $errstr, $errfile, $errline, $errcontext)
    {
        foreach ($this->expected as $warning)
        {
            if ($errno & $warning[0] && strpos($errstr, $warning[1]) !== false)
            {
                $this->intercepted[] = new \ErrorException($errstr, 0, $errno, $errfile, $errline);
                return true;
            }
        }
        return false;
    }

    /** 
     * A stack of interceptors in effect
     */
    protected static $interceptor_stack = array();

    /**
     * Catch all PHP errors, notices and throw them as an exception instead. If
     * an interceptor was registered, the message is passed to the interceptor instead.
     *
     * @param int $errno Error number
     * @param string $errstr Error description
     * @param string $errfile The file where the error occured
     * @param int $errline The line where the error occured
     * @param mixed $errcontext Erro context
     */
    public static function errorHandler($errno, $errstr, $errfile, $errline, $errcontext)
    {
        if (count(self::$interceptor_stack) > 0)
        {
            $interceptor = end(self::$interceptor_stack);
            if ($interceptor->intercept($errno, $errstr, $errfile, $errline, $errcontext))
            {
                return;
            }
        }

        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }

    /**
     * Set the error handler as the PHP Error Handler
     */
    public static function registerErrorHandler()
    {
        self::$interceptor_stack = array();
        set_error_handler(array(statis::class, "errorHandler"), E_ALL);
    }

    /**
     * Restore the error handler to the previous error handler
     */
    public static function unregisterErrorHandler()
    {
        if (is_array(self::$interceptor_stack))
        {
            self::$interceptor_stack = null;
            restore_error_handler();
        }
    }
}
