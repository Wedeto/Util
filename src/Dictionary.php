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
use Wedeto\Log\Logger;

/**
 * Dictionary provides a flexible way to use arrays as objects. The getters and
 * setters support multi-level retrieval and setting and provide 'null' values or
 * default values if they are absent. It also provides type checking and type casting.
 *
 * Its interface closely mimicks that of the standard ArrayObject. The major difference
 * is the existence of the static function wrap() that creates a dictionary that is bound
 * to an existing array, so that external changes to that array are reflected within the
 * Dictionary and vice versa.
 */
class Dictionary implements \Iterator, \ArrayAccess, \Countable, \Serializable, \JsonSerializable
{
    protected $values;
    protected $keys = null;
    protected $iterator = null;

    public function __construct($values = array())
    {
        if ($values instanceof Dictionary)
            $this->values = $values->values;
        else
            $this->values = WF::to_array($values);
    }

    public static function wrap(array &$values)
    {
        $dict = new Dictionary();
        $dict->values = &$values;
        return $dict;
    }

    /**
     * Check if a key exists
     * 
     * @param $key The key to get. May be repeated to go deeper
     * @param $type The type check. Defaults to Type::NOTEMPTY
     * @return boolean If the key exists
     */
    public function has($key, $type = Type::EXISTS)
    {
        $args = WF::flatten_array(func_get_args());

        $last = end($args);     
        $type = Type::EXISTS;
        if ((is_string($last) && defined(Type::class . '::' . $last)) || $last instanceof Type)
            $type = array_pop($args);

        foreach ($args as $arg)
            if (!is_scalar($arg))
                throw new \InvalidArgumentException("Keys must be scalar, not: " . WF::str($arg));

        $val = $this->values;
        foreach ($args as $arg)
        {
            if (!WF::is_array_like($val) || !isset($val[$arg]))
                return false;
            $val = $val[$arg];
        }

        // Check type
        if (is_string($type))
        {
            $unstrict = in_array($type, [Type::INT, Type::FLOAT, Type::BOOL]);
            $checker = new Type($type, ['unstrict' => $unstrict]);
        }
        else
            $checker = $type;

        return $checker->validate($val);
    }

    /**
     * Get a value from the dictionary, with a default value when the key does
     * not exist. The default value may be specified as-is or wrapped in a
     * DefVal object. The latter is useful to combine with Dictionary::get()
     * or any of the other getters.
     * 
     * @param $key scalar The key to get. May be repeated to go deeper
     * @param $default mixed What to return when key doesn't exist
     * @return mixed The value from the dictionary
     */
    public function &dget($key, $default = null)
    {
        if (is_array($key) && $default === null)
        {
            $args = WF::flatten_array($key);
            if (end($args) instanceof DefVal)
                $default = array_pop($args);
        }
        else
        {
            $args = WF::flatten_array(func_get_args());
            if (count($args) >= 2)
                $default = array_pop($args);
        }

        if (count($args) === 0)
            return $this->getAll();

        if ($default instanceof DefVal)
            $default = $default->value;

        $ref = &$this->values;
        foreach ($args as $arg)
        {
            if (!is_scalar($arg))
                throw new \InvalidArgumentException("Keys must be scalar, not: " . WF::str($arg));

            if (!is_array($ref) || !isset($ref[$arg]))
                return $default;
            $ref = &$ref[$arg];
        }

        if (is_array($ref))
        {
            $temp = Dictionary::wrap($ref);
            return $temp;
        }

        return $ref;
    }

    /**
     * Get a value from the dictionary. When the value does not exist, null will be returned.
     * 
     * @param $key scalar The key to get. May be repeated to go deeper
     * @return mixed The value from the dictionary
     */
    public function &get(...$key)
    {
        return $this->dget($key);
    }

    /**
     * Get a value cast to a specific type.
     * @param $key scalar The key to get. May be repeated to go deeper
     * @param $type The type (one of the constants in Type)
     * @return mixed The type as requested
     */
    public function getType($key, $type)
    {
        if (is_array($key))
        {
            $args = $key;
        }
        else
        {
            $args = func_get_args();
            $type = array_pop($args); // Type
        }
        $val = $this->dget($args);

        if ($val === null)
            throw new \OutOfRangeException("Key " . implode('.', $args) . " does not exist");

        $checker = $type instanceof Type ? $type : new Type($type, ['unstrict' => true]);
        try
        {
            $value = $checker->filter($val);
        }
        catch (\InvalidArgumentException $e)
        {
            throw new \DomainException("Key " . implode('.', $args) . " is not " . (string)$checker);
        }

        return $value;
    }

    /**
     * Get the key as a bool
     * @param $key scalar The key to get. May be repeated to go deeper
     * @return bool The value as bool
     */
    public function getBool($key, $default = null)
    {
        return $this->getType(func_get_args(), Type::BOOL);
    }

    /**
     * Get the key as an int
     * @param $key scalar The key to get. May be repeated to go deeper
     * @return int The value as an int
     */
    public function getInt($key)
    {
        return $this->getType(func_get_args(), Type::INT);
    }

    /**
     * Get the key as a float
     * @param $key scalar The key to get. May be repeated to go deeper
     * @return int The value as a float
     */
    public function getFloat($key)
    {
        return $this->getType(func_get_args(), Type::FLOAT);
    }

    /**
     * Get the key as a string
     * @param $key scalar The key to get. May be repeated to go deeper
     * @return int The value as a string
     */
    public function getString($key)
    {
        return $this->getType(func_get_args(), Type::STRING);
    }

    /**
     * Get the parameter as a Dictionary.
     * @param $key scalar The key to get. May be repeated to go deeper
     * @return Dictionary The section as a Dictionary. If the key does not
     *                    exist, an empty Dictionay is returned. If the key
     *                    is not array-like, it will be wrapped in an array.
     */
    public function getSection($key)
    {
        $val = $this->dget(func_get_args());
        if ($val instanceof Dictionary)
            return $val;
        $val = WF::cast_array($val);
        return Dictionary::wrap($val);
    }

    /**
     * Get the key as an array
     * @param $key scalar The key to get. May be repeated to go deeper
     * @return int The value as an array
     */
    public function getArray($key)
    {
        return $this->getType(func_get_args(), Type::ARRAY);
    }

    /**
     * Get the key as an object
     * @param $key scalar The key to get. May be repeated to go deeper
     * @return int The value as an object
     */
    public function getObject($key)
    {
        return $this->getType(func_get_args(), Type::OBJECT);
    }

    /**
     * Get all values from the dictionary as an associative array.
     *
     * @return array A reference to the array of all values
     */
    public function &getAll()
    {
        return $this->values;
    }

    /**
     * @return array an array with the same contents as this Dictionary
     */
    public function toArray()
    {
        return $this->values;
    }

    /**
     * Set a value in the dictionary
     *
     * @param $key scalar The key to set. May be repeated to go deeper
     * @param $value mixed The value to set
     * @return Dictionary Provides fluent interface
     */
    public function set($key, $value)
    {
        if (is_array($key) && $value === null)
            $args = $key;
        else
            $args = func_get_args();

        $value = array_pop($args);
        
        $parent = null;
        $key = null;
        $ref = &$this->values;
        foreach ($args as $arg)
        {
            if (!is_scalar($arg))
                throw new \InvalidArgumentException("Keys must be scalar, not: " . WF::str($arg));

            if (!is_array($ref))
            {
                if ($parent !== null)
                    $parent[$key] = array();
                $ref = &$parent[$key];
            }
                
            if (!isset($ref[$arg]))
                $ref[$arg] = array();

            $parent = &$ref;
            $key = $arg;
            $ref = &$ref[$arg];
        }

        // Unwrap Dictionary objects
        $cl = is_object($value) ? get_class($value) : null;
        if ($cl === Dictionary::class)
            $ref = $value->getAll();
        else
            $ref = $value;

        return $this;
    }

    /**
     * @return bool True if the Dictionary is shallow: it has only non-array
     * elements, false if it contains sub-arrays.
     */
    public function isShallow()
    {
        foreach ($this->values as $k => $v)
        {
            if (is_array($v))
                return false;
        }
        return true;
    }

    /**
     * Add all elements in the provided array-like object to the dictionary.
     * @param Traversable $values The values to add
     * @return Wedeto\Util\Dictionary Provides fluent interface
     */
    public function addAll($values)
    {
        if (!WF::is_array_like($values))
            throw new \DomainException("Invalid value to merge: " . WF::str($values));
        $this->addAllRecursive($values, $this);
        return $this;
    }

    /**
     * Recursive function to merge all values from a source dictionary or array
     * into a target dictionary.
     */
    private function addAllRecursive($source, $target, array $path = [])
    {
        foreach ($source as $key => $value)
        {
            if (!isset($target[$key]))
            {
                $target[$key] = $value;
            }
            else
            {
                $tgt = $target[$key];

                if (is_array($source) || $source instanceof Dictionary)
                {
                    if ($tgt instanceof Dictionary)
                        $this->addAllRecursive($value, $tgt);
                    else
                        $target[$key] = $value;
                }
                else
                    $target[$key] = $value;
            }
        }
    }

    /**
     * Remove all elements from the dictionary
     * @return Wedeto\Util\Dictionary Provides fluent interface
     */
    public function clear()
    {
        $keys = array_keys($this->values);
        foreach ($keys as $key)
            unset($this->values[$key]);
        return $this;
    }

    /**
     * Remove and return the last element of the dictionary
     * @return mixed The last element
     */
    public function pop()
    {
        return array_pop($this->values);
    }

    /**
     * Add an element to the end of the dictionary
     * @param mixed $element The element to add to the end
     * @return Wedeto\Util\Dictionary Provides fluent interface
     */
    public function push($element)
    {
        array_push($this->values, $element);
        return $this;
    }

    /**
     * Add an element to the end of the dictionary, wrapper of Dictionary#push
     * @param mixed $element The element to add to the end
     * @return Wedeto\Util\Dictionary Provides fluent interface
     */
    public function append($element)
    {
        return $this->push($element);
    }

    /** 
     * Remove and return the first element of the dictionary
     * @return mixed The first element
     */
    public function shift()
    {
        return array_shift($this->values);
    }

    /**
     * Add an element to the beginning of the dictionary
     * @param mixed $element The element to add to the begin
     * @return Wedeto\Util\Dictionary Provides fluent interface
     */
    public function unshift($element)
    {
        array_unshift($this->values, $element);
        return $this;
    }

    /**
     * Add an element to the beginning of the dictionary. Wraps
     * Dictionary#unshift
     * @param mixed $element The element to add to the begin
     * @return Wedeto\Util\Dictionary Provides fluent interface
     */
    public function prepend($element)
    {
        return $this->unshift($element);
    }
    
    // Iterator implementation
    public function current()
    {
        return $this->get($this->key());
    }

    public function key()
    {
        return $this->keys[$this->iterator];
    }

    public function rewind()
    {
        $this->keys = array_keys($this->values);
        $this->iterator = 0;
    }

    public function next()
    {
        ++$this->iterator;
    }

    public function valid()
    {
        return array_key_exists($this->iterator, $this->keys);
    }

    // ArrayAccess implementation
    public function offsetGet($offset)
    {
        return $this->dget($offset);
    }

    public function offsetSet($offset, $value)
    {
        if ($offset === null)
            $this->values[] = $value;
        else
            $this->set($offset, $value);
    }

    public function offsetUnset($offset)
    {
        unset($this->values[$offset]);
        $this->iterator = null;
    }

    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->values);
    }

    // Countable implementation
    public function count()
    {
        return count($this->values);
    }

    // JsonSerializable implementation
    public function jsonSerialize()
    {
        return $this->values;
    }

    // Serializable implementation
    public function serialize()
    {
        return serialize($this->values);
    }

    public function unserialize($data)
    {
        $this->values = unserialize($data);
    }

    // Sorting
    public function ksort()
    {
        ksort($this->values);
        return $this;
    }

    public function asort()
    {
        asort($this->values); 
    }

    public function uasort($callback)
    {
        uasort($this->values, $callback);
        return $this;
    }

    public function uksort($callback)
    {
        uksort($this->values, $callback);
        return $this;
    }

    public function natcasesort()
    {
        uasort($this->values, "strnatcasecmp");
        return $this;
    }

    public function natsort()
    {
        uasort($this->values, "strnatcmp");
        return $this;
    }

    public function __toString()
    {
        return WF::str($this->values);
    }
}
