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

use InvalidArgumentException;
use ReflectionClass;

use Wedeto\Util\Hook;
use Wedeto\Util\TypedDictionary;
use Wedeto\Util\Type;
use Wedeto\Util\Functions as WF;

/**
 * A dependency injector. Creates new objects using their constructor,
 * automatically attempting to fill all parameters in the constructor.
 */
class Injector
{
    const DEFAULT_SELECTOR = "_DEFAULT_";

    /** The list of instantiated objects */
    protected $objects = [];

    /** A stack keeping track of newInstance calls - used to detect cyclic dependencies */
    protected $instance_stack = [];

    /**
     * Create a new injector.
     *
     * @param Injector $other You can specify another injector to copy instances from
     */
    public function __construct(Injector $other = null)
    {
        if (null !== $other)
            $this->objects = $other->objects;
    }

    /**
     * Get an instance of the object
     * @param string $class The class to get an instance of
     * @param string $selector Where multiple instances may exist, they can be categorized by a selector
     * @return Object an instance of the class
     */
    public function getInstance(string $class, string $selector = Injector::DEFAULT_SELECTOR)
    {
        if (array_search($class, $this->instance_stack, true))
            throw new DIException("Cyclic dependencies in creating $class: " . WF::str($this->instance_stack));

        array_push($this->instance_stack, $class);
        if (!isset($this->objects[$class]) || !isset($this->objects[$class][$selector]))
        {
            $instance = $this->newInstance($class, ['wdiSelector' => $selector]);
            $nclass = get_class($instance);
            $const_name = $nclass . '::WDI_REUSABLE';
            if (defined($const_name) && constant($const_name) === true)
                $this->setInstance($class, $instance, $selector);
        }
        else
        {
            $instance = $this->objects[$class][$selector];
        }

        if ($class !== array_pop($this->instance_stack))
        {
            // @codeCoverageIgnoreStart
            throw new DIException("Unexpected class at top of stack");
            // @codeCoverageIgnoreEnd
        }

        return $instance;
    }

    /**
     * Set a specific instance of class
     * 
     * @param string $class The name of the class
     * @param string $selector Where multiple instances may exist, they can be caterogized by a selector
     * @param object $instance The instance to set for this class
     */
    public function setInstance(string $class, $instance, string $selector = Injector::DEFAULT_SELECTOR)
    {
        if (!is_a($instance, $class))
            throw new DIException("Instance should be a subclass of $class");

        if (!isset($this->objects[$class]))
            $this->objects[$class] = [];

        $this->objects[$class][$selector] = $instance;
    }

    /** 
     * Remove an instance from the repository
     * @param string $class The name of the class
     * @param string $selector The selector to clear
     */
    public function clearInstance(string $class, string $selector = Injector::DEFAULT_SELECTOR)
    {
        if (!isset($this->objects[$class]))
            return;
        
        unset($this->objects[$class][$selector]);
    }

    /**
     * Create a new instance of a class
     *
     * @param string $class The name of the class to instantiate
     * @param array $args An associative array of mappings of
     *                    parameter names in the constructor to values.
     * @return Object A new instance of the object
     */
    public function newInstance(string $class, $args = [], string $selector = Injector::DEFAULT_SELECTOR)
    {
        if (!class_exists($class))
            throw new DIException("Class $class does not exist");

        // Execute hook to allow plugins to provide instances
        $instance_type = new Type(Type::OBJECT, ['instanceof' => $class]);
        $hook_data = new TypedDictionary(
            ['instance' => $instance_type, 'class' => Type::STRING, 'args' => Type::ARRAY, 'selector' => Type::STRING],
            ['class' => $class, 'args' => $args, 'selector' => $selector]
        );
        Hook::execute('Wedeto.Util.DI.Injector.newInstance', $hook_data);
        if ($hook_data->has('instance', Type::OBJECT))
        {
            $instance = $hook_data->get('instance');
            return $instance;
        }

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
                $instance = $this->getInstance($pclass->getName(), $selector);
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
