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
 * BasicFactory is a class that facilitates easy creation of custom factories
 * for the Injector. You provide a callable (lambda or function) to the
 * constructor and this will be used to generate instances.
 */
class BasicFactory implements Factory
{
    protected $function;

    /**
     * Create the factory
     *
     * @param callabl $fn The function to use as factory
     */
    public function __construct(callable $fn)
    {
        $this->function = $fn;
    }

    /**
     * The Factory interface: call the provided function to
     * do the factory job.
     */
    public function produce(array $args, string $selector = Injector::DEFAULT_INJECTOR)
    {
        return call_user_func($this->function, $args);
    }
}
