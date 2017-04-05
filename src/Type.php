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
    const EXISTS = "EXISTS";
    const BOOL = "BOOL";
    const NUMERIC = "NUMERIC";
    const FLOAT = "FLOAT";
    const INT = "INT";
    const STRING = "STRING";
    const SCALAR = "SCALAR";
    const DATE = "DATE";
    const ARRAY = "ARRAY";
    const OBJECT = "OBJECT";
    const ISSET = "ISSET";

    const VALIDATE_CUSTOM = "VALIDATE_CUSTOM";
    const VALIDATE_FILTER = "VALIDATE_FILTER";

    protected $type;
    protected $options = array();

    /** 
     * Create a type constraint.
     * @param string $type The type, one of the class constants
     * @param array $options The options. Supported:
     *                       min_range  => minimum value for INT, FLOAT, NUMERIC types, 
     *                                     minimum length for STRING, 
     *                                     minimum date for DATE
     *                       max_range  => maximum value for INT, FLOAT, NUMERIC types, 
     *                                     maximum length for STRING, 
     *                                     maximum date for DATE 
     *                       class      => exact classname for OBJECT
     *                       instanceof => class, ancestor class or interface for OBJECT
     *                       regex      => regular expression to match for STRING
     *                       custom     => Custom callback for all types
     */                        
    public function __construct(string $type, array $options = [])
    {
        $const_name = "static::" . $type;
        if (!defined($const_name))
            throw new \InvalidArgumentException("Unknown type: " . $type);

        $this->type = $type;
        $this->options = $options;
    }
    
    /**
     * Return a properly typed value
     *
     * @param mixed $value The value to match and correct
     * @return mixed The filtered value
     * @throws InvalidArgumentException When the value is incompatible
     */
    public function filter($value)
    {
        $filtered = null;
        if (!$this->validate($value, $filtered))
        {
            throw new \InvalidArgumentException(
                "Not a valid value for " . $this->__toString() . ": " . Functions::str($value)
            );
        }

        return $filtered;
    }

    /**
     * Check if the value matches the expected value
     * @param mixed $value
     * @return bool True if the value matches all constraints, false if it does not
     */
    public function validate($value, &$filtered = null)
    {
        if ($value === null)
            return !empty($this->options['nullable']);
        
        $filtered = $value;
        if ($this->type === Type::EXISTS)
            return true;
        
        $o = $this->options;
        if ($this->type !== Type::VALIDATE_CUSTOM && !$this->matchType($value, $filtered))
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
    protected function matchType($value, &$filtered)
    {
        $o = $this->options;
        $min = $o['min_range'] ?? null;
        $max = $o['max_range'] ?? null;
        $strict = !($o['unstrict'] ?? false);

        switch ($this->type)
        {
            case Type::BOOL:
                if ($strict && !is_bool($value))
                    return false;
                $filtered = Functions::parse_bool($value);
                return true;
            case Type::NUMERIC:
                if (!is_numeric($value))
                    return false;
                $filtered = Functions::is_int_val($value) ? (int)$value : (float)$value;
                return $this->numRangeCheck($filtered, $min, $max);
            case Type::FLOAT:
                if ($strict && !is_float($value) && !is_int($value))
                    return false;
                $filtered = filter_var($value, FILTER_VALIDATE_FLOAT);
                return ($filtered !== false) && $this->numRangeCheck($filtered, $min, $max);
            case Type::INT:
                if (!is_int($value) && ($strict || !Functions::is_int_val($value)))
                    return false;
                $filtered = (int)$value;
                return $this->numRangeCheck($filtered, $min, $max);
            case Type::STRING:
                if (!is_string($value) && ($strict || !is_scalar($value)))
                    return false;
                $filtered = (string)$value;
                if ($min !== null && strlen($value) < $min)
                    return false;
                if ($max !== null && strlen($value) > $max)
                    return false;
                if (isset($o['regex']) && !preg_match($o['regex'], $value))
                    return false;
                return true;
            case Type::SCALAR:
                return is_scalar($value);
            case Type::DATE:
                if (!($value instanceof \DateTimeInterface))
                    return false;
                if ($min instanceof \DateTimeInterface && $value < $min)
                    return false;
                if ($max instanceof \DateTimeInterface && $value > $max)
                    return false;
                return true;
            case Type::ARRAY:
                if (!Functions::is_array_like($value))
                    return false;
                $filtered = Functions::to_array($value);
                return true;
            case Type::VALIDATE_FILTER:
                $ft = $o['filter'];
                unset($o['filter']);
                if ($ft === FILTER_VALIDATE_BOOLEAN)
                    $o['flags'] = FILTER_NULL_ON_FAILURE;

                $filtered = filter_var($value, $ft, $o);
                if ($ft === FILTER_VALIDATE_BOOLEAN)
                    return !($filtered === null);
                return $filtered !== false;
            case Type::OBJECT:
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
                // @codeCoverageIgnoreStart
                return false;
                // @codeCoverageIgnoreEnd
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

    public function __toString()
    {
        $desc = $this->type;
        if (!empty($this->options))
            $desc .= Functions::str($this->options);
        return $desc;
    }
}
