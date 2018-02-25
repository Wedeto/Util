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

/**
 * DI manages Dependency Injection contexts.
 *
 * Call DI::getInjector() to get an injection instance, which
 * can be used to instantiate objects.
 */
class DI
{
    /** The stack of injectors */
    protected static $injectors = [];

    /**
     * @return Injector A Injector instance
     */
    public static function getInjector()
    {
        $last = end(static::$injectors);
        if (false === $last)
        {
            $last = new Injector();
            array_push(static::$injectors, $last);
        }

        return $last;
    }

    /**
     * Start a new injection context. Useful to be able to release
     * all static context to a certain run of the software, for example
     * in testing.
     *
     * @param string $name A name for the injection context
     * @param bool $inherit True to inherit instances from the current injector,
     *                      false to start with an empty set.
     */
    public static function startNewContext(string $name, bool $inherit = true)
    {
        if ($inherit)
        {
            $current = static::getInjector();
            $new_injector = new Injector($current);
        }
        else
            $new_injector = new Injector();

        return static::$injectors[$name] = $new_injector;
    }

    /**
     * Destroy a context - the injector at the top of the stack
     * is released.
     *
     * @param string $name The name of the injection context
     * @throws DIException When the injection context is not at the top of the stack
     */
    public static function destroyContext(string $name)
    {
        end(static::$injectors);
        if ($name !== key(static::$injectors))
            throw new DIException("Injection context $name is not at top of the stack");

        $injector = array_pop(static::$injectors);
    }
}
