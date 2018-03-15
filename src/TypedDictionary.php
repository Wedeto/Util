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

use Wedeto\Util\Functions as WF;
use Wedeto\Util\Validation\Type;
use Wedeto\Util\Validation\Validator;

class TypedDictionary extends Dictionary
{
    protected $types;

    /**
     * Create a typed dictionary. It can only contain values of the specified types,
     * and only predefined keys.
     *
     * @param Dictionary $types The keys and types to allow. Can be nested
     * @param array $values The initial values to set
     */
    public function __construct($types, $values = [])
    {
        if (!($types instanceof Dictionary))
            $types = new Dictionary($types);

        $this->validateTypes($types);
        
        if ($values instanceof Dictionary)
            $values = $values->values;
        else
            $values = WF::to_array($values);

        $this->types = $types;
        $this->values = [];

        $this->addAll($values);
    }

    /**
     * Validate that all provided types are actually type validators
     * @param Dictionary $types The supplied type validators
     * @param string $path The key path, used for reporting errors
     */
    protected function validateTypes(Dictionary $types, $path = "")
    {
        $spath = empty($path) ? "" : $path . ".";
        foreach ($types as $key => $value)
        {
            $kpath = $spath . $key;
            if ($value instanceof Dictionary)
            {
                $this->validateTypes($value, $kpath);
            }
            elseif (is_string($value))
            {
                $types[$key] = new Validator($value);
            }
            elseif (!($value instanceof Validator))
            {
                throw new \InvalidArgumentException(
                    "Unknown type: " . Functions::str($value) . " for " . $kpath
                );
            }
        }
    }

    /** 
     * Add a type for a parameter
     * @param string $key The key to set a type for. Can be repeated to go deeper
     * @param Validator $type The type for the parameter
     * @return TypedDictionary Provides fluent interface
     */
    public function setType($key, $type)
    {
        $args = func_get_args();
        $type = array_pop($args);

        if (!($type instanceof Validator))
            $type = new Validator($type);

        if ($this->types->has($args))
        {
            $old_type = $this->types->get($args); 
            if ($old_type != $type)
                throw new \LogicException("Duplicate key: " . WF::str($args));
        }
        else
        {
            $args[] = $type;
            $this->types->set($args, null);
        }
        return $this;
    }

    /**
     * Set a value, after type checking
     * @param string $key The key to set. Can be repeated to go deeper
     * @param mixed $value Whatever to set. Will be type checked
     * @return TypedDictionary Provides fluent interface
     */
    public function set($key, $value)
    {
        if (is_array($key) && $value === null)
            $args = $key;
        else
            $args = func_get_args();

        $path = $args;
        $value = array_pop($path);
        $type = $this->types->dget($path);
        $kpath = implode('.', $path);

        // Key is undefined, but it may be in a untyped sub-array
        if ($type === null)
        {
            $cpy = $path;
            while (count($cpy))
            {
                $last = array_pop($cpy);
                $subtype = $this->types->dget($cpy);
                if ($subtype instanceof Validator && $subtype->getType() === Type::ARRAY)
                    return parent::set($args, null);
            }
        }

        if ($type === null)
            throw new \InvalidArgumentException("Undefined key: " . $kpath);

        if ($type instanceof Dictionary)
        {
            // Subfiltering required - extract values
            if (!Functions::is_array_like($value))
                throw new \InvalidArgumentException("Value must be array at: " . $kpath);

            foreach ($value as $subkey => $subval)
            {
                $nargs = $path;
                $nargs[] = $subkey;
                $nargs[] = $subval;
                $this->set($nargs, null);
            }
            return;
        }

        if (!$type->validate($value))
            throw new \InvalidArgumentException("Value must be " . (string)$type . " at: " . $kpath);

        return parent::set($args, null);
    }

    /**
     * We override dget as dget returns a reference, allowing the
     * TypedDictionary to be modified from the outside. This avoids the checks,
     * so this needs to be disallowed.
     *
     * @param string $key The key to set. Can be repeated to go deeper
     * @param mixed $default A default value to return in absence
     */
    public function &dget($key, $default = null)
    {
        $args = WF::flatten_array(func_get_args());
        if (func_num_args() > 1)
        {
            $default = array_pop($args);
            if (!($default instanceof DefVal))
                $default = new DefVal($default);
            $args[] = $default;
        }

        // Get the value, without referencing, so that we actually return a copy
        $result = parent::dget($args, null);

        if ($result instanceof Dictionary)
        {
            // Sub-arrays are returned as dictionary - which allow
            // modification without type checking. Re-wrap into
            // a TypedDictionary including the correct types
            $path = $args;
            $types = $this->types->dget($path);
            
            if (WF::is_array_like($types))
            {
                $dict = new TypedDictionary($types);
                $dict->values = &$result->values;
                $result = $dict;
            }
        }

        // Return the copy to keep it unmodifiable
        return $result;
    }

    /** 
     * Add all provided values, checking their types
     * @param array $values Array with values.
     * @return TypedDictionary Provides fluent interface
     */
    public function addAll($values)
    {
        foreach ($values as $key => $value)
            $this->set($key, $value);
        return $this;
    }

    /** 
     * Disallowed - TypedDictionary cannot be used as stacks
     * @param $val
     */
    public function append($val)
    {
        throw new \RuntimeException("TypedDictionary cannot be used as a stack");
    }

    /** 
     * Disallowed - TypedDictionary cannot be used as stacks
     * @param $val
     */
    public function push($val)
    {
        throw new \RuntimeException("TypedDictionary cannot be used as a stack");
    }

    /** 
     * Disallowed - TypedDictionary cannot be used as stacks
     * @param $val
     */
    public function unshift($val)
    {
        throw new \RuntimeException("TypedDictionary cannot be used as a stack");
    }

    /** 
     * Disallowed - TypedDictionary cannot be used as stacks
     * @param $val
     */
    public function shift()
    {
        throw new \RuntimeException("TypedDictionary cannot be used as a stack");
    }

    /** 
     * Disallowed - TypedDictionary cannot be used as stacks
     * @param $val
     */
    public function pop()
    {
        throw new \RuntimeException("TypedDictionary cannot be used as a stack");
    }

    /**
     * Even though this method returns a reference, a reference to a copy is
     * returned to prevent external modification.
     */
    public function &getAll()
    {
        $all = parent::getAll();
        return $all;
    }

    /**
     * Customize wrapping of the TypedDictionary - the wrapped array can still
     * be modified externally so we need to make sure the appropriate types are
     * propagated
     *
     * @throws RuntimeException Always
     */
    public static function wrap(array &$values)
    {
        $types = new Dictionary;
        self::determineTypes($values, $types);

        return new TypedDictionary($types, $values);
    }

    protected static function determineTypes(array $values, Dictionary $types)
    {
        foreach ($values as $key => $value)
        {
            if (WF::is_array_like($value))
            {
                $subarray = WF::to_array($value);
                $subtypes = new Dictionary;
                self::determineTypes($subarray, $subtypes);
                $types[$key] = $subtypes;
            }
            else
            {
                $tp = strtoupper(gettype($value));

                $opts = [];
                if ($tp === "NULL")
                {
                    $tp = "EXISTS";
                    $opts['nullable'] = true;
                }
                else
                    $tp = constant(Type::class . '::' . $tp);

                if ($tp === "OBJECT")
                    $opts['instanceof'] = get_class($value);
                
                $type = new Validator($tp, $opts);
                $types[$key] = $type;
            }
        }
        return $types;
    }

    /**
     * @return string The TypedDictionary as a string 
     */
    public function __toString()
    {
        $val = WF::str($this->values);
        $tp = WF::str($this->types->values);
        return "$val (Type: " . $tp . ")";
    }
}

