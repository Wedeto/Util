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

class Type
{
    const TYPE_BOOL = -2;
    const TYPE_NUMERIC = -3;
    const TYPE_FLOAT = -4;
    const TYPE_INT = -5;
    const TYPE_STRING = -6;
    const TYPE_DATE = -7;
    const TYPE_ARRAY = -8;
    const TYPE_OBJECT = -9;
    const TYPE_CUSTOM = -10;

    protected $type;
    protected $options = array();

    /** 
     * Create a type constraint.
     * @param int $type The type, one of Type::TYPE_*
     * @param array $options The options. Supported:
     *                       min => minimum value for numeric types, minimum length for TYPE_STRING, minimum date for TYPE_DATE
     *                       max => maximum value for numeric types, maximum length for TYPE_STRING, maximum date for TYPE_DATE 
     *                       class => exact classname for TYPE_OBJECT
     *                       instanceof => class, ancestor class or interface for TYPE_OBJECT
     *                       regex => regular expression to match for TYPE_STRING
     *                       custom => Custom callback for all types
     */                        
    public function __construct(int $type, array $options = [])
    {
        switch ($type)
        {
            case Type::TYPE_BOOL:
            case Type::TYPE_NUMERIC:
            case Type::TYPE_FLOAT:
            case Type::TYPE_INT:
            case Type::TYPE_STRING:
            case Type::TYPE_DATE:
            case Type::TYPE_ARRAY:
            case Type::TYPE_OBJECT:
            case Type::TYPE_CUSTOM:
                $this->type = $type;
                break;
            default:
                throw new \InvalidArgumentException("Unknown type: " . $type);
        }

        $this->options = $options;
    }
    
    /**
     * Check if the value matches the expected value
     * @param mixed $value
     * @return bool True if the value matches all constraints, false if it does not
     */
    public function match($value)
    {
        if ($value === null)
            return !empty($this->options['nullable']);
        
        $o = $this->options;
        if ($this->type !== Type::TYPE_CUSTOM && !$this->matchType($value))
            return false;

        if (isset($o['custom']) && is_callable($o['custom']) && !$o['custom']($value))
            return false;

        return true;
    }

    /**
     * Check if the type of a value is ok
     * @param mixed $value The value to validate
     * @return bool True if the value validates, false if it does not
     */
    protected function matchType($value)
    {
        $o = $this->options;
        $min = $o['min'] ?? null;
        $max = $o['max'] ?? null;

        switch ($this->type)
        {
            case Type::TYPE_BOOL:
                return is_bool($value);
            case Type::TYPE_NUMERIC:
                if (!is_numeric($value))
                    return false;
                return $this->numRangeCheck($value, $min, $max);
            case Type::TYPE_FLOAT:
                if (!is_float($value) && !is_int($value))
                    return false;
                return $this->numRangeCheck($value, $min, $max);
            case Type::TYPE_INT:
                if (!is_int($value))
                    return false;
                return $this->numRangeCheck($value, $min, $max);
            case Type::TYPE_STRING:
                if (!is_string($value))
                    return false;
                if ($min !== null && strlen($value) < $min)
                    return false;
                if ($max !== null && strlen($value) > $max)
                    return false;
                if (isset($o['regex']) && !preg_match($o['regex'], $value))
                    return false;
                return true;
            case Type::TYPE_DATE:
                if (!($value instanceof \DateTimeInterface))
                    return false;
                if ($min instanceof \DateTimeInterface && $value < $min)
                    return false;
                if ($max instanceof \DateTimeInterface && $value > $max)
                    return false;
                return true;
            case Type::TYPE_ARRAY:
                return Functions::is_array_like($value);
            case Type::TYPE_OBJECT:
                if (!is_object($value))
                    return false;

                // Class, subclass or interface?
                if (isset($o['instanceof']) && !is_a($value, $o['instanceof']))
                    return false;

                // Specific class?
                if (isset($o['class']) && get_class($value) !== $o['class'])
                    return false;

                return true;
            default:
                return false;
        }
        return true;
    }

    /** 
     * Check if the numeric value is between the configured minimum and maximum
     * @param numeric $value The value to compare
     * @param numeric $min The minimum value
     * @param numeric $max The maximum value
     * @return bool True when the value is in range, false if it is out of range
     */
    protected function numRangeCheck($value, $min, $max)
    {
        if ($min !== null && $value < $min)
            return false;
        if ($max !== null && $value > $max)
            return false;
        return true;
    }
}
