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

use Wedeto\Util\Functions as WF;

/**
 * A dependency injector. Creates new objects using their constructor,
 * automatically attempting to fill all parameters in the constructor.
 */
class Injector
{
    const DEFAULT_SELECTOR = "_DEFAULT_";
    const SHARED_SELECTOR = "_SHARED_";

    /** The list of instantiated objects */
    protected $objects = [];

    /** A stack keeping track of newInstance calls - used to detect cyclic dependencies */
    protected $instance_stack = [];

    /** A map of classnames to factories, used in place of the default instance creator */
    protected $factories = [];

    protected $default_factory;

    /**
     * Create a new injector.
     *
     * @param Injector $other You can specify another injector to copy instances from
     */
    public function __construct(Injector $other = null)
    {
        if (null !== $other)
        {
            $this->objects = $other->objects;
            $this->factories = $other->factories;
            $this->default_factory = $other->default_factory;
        }
        else
        {
            $this->default_factory = new DefaultFactory;
        }
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
            if ($selector === Injector::SHARED_SELECTOR)
                throw new DIException("Refusing to instantiate shared instance");

            if (isset($this->objects[$class][Injector::SHARED_SELECTOR]))
            {
                $instance = $this->objects[$class][Injector::SHARED_SELECTOR];
            }
            else
            {
                $instance = $this->newInstance($class, ['wdiSelector' => $selector]);
                $nclass = get_class($instance);
                $const_name = $nclass . '::WDI_REUSABLE';
                if (defined($const_name) && constant($const_name) === true)
                    $this->setInstance($class, $instance, $selector);
            }
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
     * Set a factory as the default, used when no specific factory is available.
     *
     * @param Factory $factory The factory to use as default
     * @return $this Provides fluent interface
     */
    public function setDefaultFactory(Factory $factory)
    {
        $this->default_factory = $factory;
        return $this;
    }

    /**
     * @return Factory the default factory instance
     */
    public function getDefaultFactory()
    {
        return $this->default_factory;
    }

    /**
     * Register a factory for a class name
     *
     * @param string $produced_class The class produced by the factory
     * @param Wedeto\Util\DI\Factory $factory The factory to register
     * @return $this Provides fluent interface
     */
    public function registerFactory(string $produced_class, Factory $factory)
    {
        $this->factories[$produced_class] = $factory;
        return $this;
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

        if (isset($this->factories[$class]))
            return $this->factories[$class]->produce($class, $args, $selector, $this);

        return $this->default_factory->produce($class, $args, $selector, $this);
    }
}
