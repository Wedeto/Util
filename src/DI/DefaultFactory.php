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
use ReflectionMethod;
use Wedeto\Util\DocComment;

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

        try
        {
            $constructor_args = $this->determineArgumentsFor($constructor, $class, "constructor", $args, $selector, $injector);
            $instance = $reflect->newInstanceArgs($constructor_args);
        }
        catch (DIException $e)
        {
            $instance = $this->attemptGeneratorMethod($reflect, $class, $args, $selector, $injector);
            if (null === $instance)
                throw $e;
        }

        return $instance;
    }

    /**
     * Attempt to create an instance using a generator method supplied by a 
     * annotation in the class' DocComment:
     *
     * @generator MyFactoryMethodName
     *
     * This method will be attempted to invoke, the arguments should therefore be
     * instantiatable by the injector.
     */
    protected function attemptGeneratorMethod(ReflectionClass $reflect, string $class, array $args, string $selector, Injector $injector)
    {
        $docComment = $reflect->getDocComment();
        if (!empty($docComment))
        {
            $docComment = new DocComment($docComment);

            $tokens = $docComment->getAnnotationTokens("generator");
            $fn_name = $tokens[0] ?? "";

            if (method_exists($class, $fn_name))
            {
                $method = new ReflectionMethod($class, $fn_name);
                if ($method->isStatic() && $method->isPublic())
                {
                    $method_args = $this->determineArgumentsFor($method, $class, $fn_name, $args, $selector, $injector);
                    $instance = $method->invokeArgs(null, $method_args);
                    return $instance;
                }
            }
        }
        return null;
    }
        
    /**
     * Find arguments for a method
     */
    protected function determineArgumentsFor(
        ReflectionMethod $method, 
        string $class, 
        string $method_name, 
        array $args, 
        string $selector, 
        Injector $injector
    )
    {
        $params = $method->getParameters();
        $method_args = [];

        // Determine values for each parameter
        $used_optional = false;
        foreach ($params as $param)
        {
            $name = $param->getName();

            if (array_key_exists($name, $args))
            {
                $method_args[] = $args[$name];
                continue;
            }

            $pclass = $param->getClass();
            if (null !== $pclass)
            {
                $instance = $injector->getInstance($pclass->getName(), $selector);
                $method_args[] = $instance;
                continue;
            }

            if ($param->isDefaultValueAvailable())
            {
                $default = $param->getDefaultValue();
                $method_args[] = $default;
                continue;
            }

            if ($param->isOptional())
            {
                // This and all remaining parameters have a default value
                break;
            }

            throw new DIException("Unable to determine value for parameter $name for $method_name of '$class'");
        }

        // There should be a argument for every parameter
        return $method_args;
    }
}
