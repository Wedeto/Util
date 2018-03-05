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

use ReflectionClass;

/**
 * DefaultFactory is a DI injecting factory that uses the injector to
 * obtain arguments for all parameters in the constructor.
 */
class DefaultFactory implements Factory
{
    /**
     * The Factory interface: call the provided function to
     * do the factory job.
     */
    public function produce(string $class, array $args, string $selector = Injector::DEFAULT_SELECTOR, Injector $injector)
    {
        $const_name = $class . '::WDI_NO_AUTO';
        if (defined($const_name) && constant($const_name) === true)
        {
            throw new DIException("Cannot instantiate $class because $class::WDI_NO_AUTO is true");
        }

        $reflect = new ReflectionClass($class);
        $constructor = $reflect->getConstructor();

        if (null === $constructor)
            return $reflect->newInstance();

        if (!$constructor->isPublic())
            throw new DIException("Class $class does not have a public constructor");

        $params = $constructor->getParameters();
        $constructor_args = [];

        // Determine values for each parameter
        $used_optional = false;
        foreach ($params as $param)
        {
            $name = $param->getName();

            if (array_key_exists($name, $args))
            {
                $constructor_args[] = $args[$name];
                continue;
            }

            $pclass = $param->getClass();
            if (null !== $pclass)
            {
                $instance = $injector->getInstance($pclass->getName(), $selector);
                $constructor_args[] = $instance;
                continue;
            }

            if ($param->isDefaultValueAvailable())
            {
                $default = $param->getDefaultValue();
                $constructor_args[] = $default;
                continue;
            }

            if ($param->isOptional())
            {
                // This and all remaining parameters have a default value
                break;
            }

            throw new DIException("Unable to determine value for parameter $name for constructor of '$class'");

        }

        // There should be a argument for every parameter
        $instance = $reflect->newInstanceArgs($constructor_args);
        return $instance;
    }
}
