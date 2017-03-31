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

use Psr\Log\NullLogger;
use Psr\Log\LoggerInterface;

/**
 * Static variant of Psr\Log\LoggerAwareTrait
 */
trait LoggerAwareStaticTrait
{
    /**
     * The logger instance.
     *
     * @var LoggerInterface
     */
    protected static $logger;

    /**
     * Sets a logger.
     *
     * @param LoggerInterface $logger
     */
    public static function setLogger(LoggerInterface $logger)
    {
        self::$logger = $logger;
    }

    /**
     * Set the logger to null, and call getLogger to initialize a new one
     */
    public static function resetLogger()
    {
        static::$logger = null;
        self::getLogger();
    }

    /**
     * Get a logger. If not available yet, it will be created using a Hook, or
     * a NullLogger is instantiated.
     */
    public static function getLogger()
    {
        if (self::$logger === null)
        {
            $result = Hook::execute(
                "Wedeto.Util.GetLogger", 
                ["logger" => null, "class" => static::class]
            );

            self::$logger = $result['logger'] ?? new NullLogger();
        }
        return self::$logger;
    }
}
