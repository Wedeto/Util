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
    public function __construct(Dictionary $types, $values = array())
    {
        $this->validateTypes($types);
        
        if ($values instanceof Dictionary)
            $values = $values->values;
        else
            $values = WF::to_array($values);

        $this->validateValues($values, $types);
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
                $this->validateTypes($value, $spath);
            }
            elseif (!($value instanceof Type))
            {
                throw new \InvalidArgumentException(
                    "Invalid type for " . $kpath . ": " . Functions::str($value)
                );
            }
        }
    }


    /**
     * Validate the array for valid types.
     * @param array $values The values to validate
     * @param Dictionary $types The type definitions
     * @param string $path The key path, used for reporting errors
     */
    protected function validateValues(array $values, Dictionary $types, string $path = "")
    {
        $spath = empty($path) ? "" : $path . ".";
        foreach ($values as $key => $value)
        {
            $kpath = $spath . $key;
            if (!$types->has($key))
                throw new \InvalidArgumentException("Undefined key: " . $kpath);
                
            $type = $types->get($key);
            if ($type instanceof Dictionary)
            {
                if (!Functions::is_array_like($value))
                    throw new \InvalidArgumentException("Value must be array at: " . $kpath);
                if ($value instanceof Dictionary)
                    $value = $value->values;
                else
                    $value = Functions::to_array($value);

                $this->validateValue($value, $type, $kpath);
            }
            elseif ($type instanceof Type)
            {
                if (!$type->match($value))
                    throw new \InvalidArgumentException("Value must be " . (string)$type . " at: " . $kpath);
            }
            else
                throw new \RuntimeException("Invalid type classifier");
        }
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

        if ($type === null)
            throw new \InvalidArgumentException("Undefined key: " . $kpath);

        if (!$type->match($value))
            throw new \InvalidArgumentException("Value must be " . (string)$type . " at: " . $kpath);

        return parent::set($args);
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
        if (is_array($key) && $default === null)
            $args = $key;
        else
            $args = func_get_args();

        // Get the value, without referencing, so that we actually return a copy
        $result = parent::dget($args);

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
        $this->validateValues($values, $this->types, "");
        return parent::addAll($values);
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
     * Disallow wrapping in the TypedDictionary - the wrapped array can still
     * be modified externally defeating the type safety purpose.
     *
     * @throws RuntimeException Always
     */
    public static function wrap(array &$values)
    {
        throw new \RuntimeException("Cannot wrap into a TypedDictionary");
    }
}

