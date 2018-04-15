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

use InvalidArgumentException;
use RuntimeException;
use DomainException;
use DateInterval;
use DateTimeImmutable;
use ArrayAccess;
use Traversable;
use Throwable;

/**
 * Functions contains some stand alone utility functions.
 */
class Functions
{
    const CONTINUE = 0;
    const BREAK = 1;

    protected static $debug_stream = null;

    /**
     * Check if the provided value contains an integer value.
     * The value may be an int, or anything convertable to an int.
     * After conversion, the string representation of the value before
     * and after conversion are compared, and if they are equal, the
     * value is considered a proper integral value.
     * 
     * @param mixed $val The value to test
     * @return boolean True when $val is considered an integral value, false
     *                 otherwise
     */
    public static function is_int_val($val)
    {
        if (is_int($val)) return true;
        if (is_bool($val)) return false;
        if (!is_string($val)) return false;

        return (string)((int)$val) === $val;
    }

    /** Convert any value to a bool, somewhat more intelligently than PHP does
      * itself: this function will also take strings, and it will convert 
      * English versions of the words 'off', 'no', 'disable', 'disabled' to false.
      * 
      * @param mixed $val Any scalar or object at all
      * @param float $float_delta Used for float comparison
      * @return boolean True when the value can be considered true, false if not
      */
    public static function parse_bool($val, float $float_delta = 0.0001)
    {
        // For booleans, the value is already known
        if (is_bool($val))
            return $val;

        // Consider some 'empty' values as false
        if (empty($val))
            return false;

        // For numeric types, consider near-0 to be false
        if (is_numeric($val))
            return abs($val) > $float_delta;

        // Non-empty arrays are considered true
        if (is_array($val))
            return true;

        // Parse some textual values representing a boolean
        if (is_string($val))
        {
            $lc = strtolower(trim($val));

            $words = array("disable", "disabled", "false", "no", "negative", "off");

            // The empty string and some words are considered false
            // Any other non-empty string is considered to be true
            return !in_array($lc, $words);
        }

        // Try to call some methods on the object if they are available
        if (is_object($val))
        {
            $opts = array(
                'bool', 'to_bool', 'tobool', 'get_bool', 'getbool', 'boolean',
                'toboolean', 'to_boolean', 'get_boolean', 'getboolean', 'val',
                'getval', 'get_val', '__tostring'
            );
            foreach ($opts as $fn)
                if (method_exists($val, $fn))
                {
                    $ret = $val->$fn();

                    // One last possibility to use scalar booleans as no, false, off etc
                    if (is_scalar($ret))
                        return self::parse_bool($ret, $float_delta);
            
                    // Otherwise leave it to PHP
                    return $ret == true;
                }
        }

        // Don't know what it is, but it definitely is not something you would
        // consider false, such as 0, null, false and the like.
        return true;
    }

    public static function is_array_like($arg)
    {
        return is_array($arg) || $arg instanceof Traversable;
    }

    public static function is_numeric_array($arg)
    {
        if (!self::is_array_like($arg))
            return false;
        foreach ($arg as $key => $v)
            if (!is_int($key))
                return false;
        return true;
    }

    public static function is_sequential_array($arg)
    {
        if (!self::is_array_like($arg))
            return false;

        $expected = 0;
        foreach ($arg as $key => $v)
            if (!is_int($key) || $key !== $expected++)
                return false;
        return true;
    }

    public static function to_array($arg)
    {
        if (!self::is_array_like($arg))
            throw new DomainException("Cannot convert argument to array");

        if (is_array($arg))
            return $arg;
        $arr = array();
        foreach ($arg as $key => $value)
            $arr[$key] = $value;
        return $arr;
    }

    public static function cast_array($arg)
    {
        try
        {
            return self::to_array($arg);
        }
        catch (DomainException $e)
        {
            return empty($arg) ? array() : array($arg);
        }
    }

    public static function flat_array_gen($arg)
    {
        if (!self::is_array_like($arg))
            throw new InvalidArgumentException("Not an array");

        $arg = self::to_array($arg);
        foreach ($arg as $arg_l2)
        {
            if (self::is_numeric_array($arg_l2))
            {
                $arg_l2 = self::flat_array_gen($arg_l2);
                foreach ($arg_l2 as $arg_l3)
                    yield $arg_l3;
            }
            else
                yield $arg_l2;
        }
    }

    public static function flatten_array($arg)
    {
        $tgt = array();
        foreach (self::flat_array_gen($arg) as $val)
            $tgt[] = $val;
        return $tgt;
    }

    public static function check_extension($extension, $class = null, $function = null)
    {
        if ($class !== null && !class_exists($class, false))
        {
            throw new RuntimeException(
                "A required class does not exist: {$class}. " .
                "Check if the extension $extension is installed and enabled"
            );
        }

        if ($function !== null && !function_exists($function))
        {
            throw new RuntimeException(
                "A required function does not exist: {$class}. " .
                "Check if the extension $extension is installed and enabled"
            );
        }
    }

    /**
     * fee (ForEachElse) is a ForEach / Else implementation
     * It will iterate over the iterable, and call the $loop function for each
     * iterator.  If the loop does not execute at least once, it the provided
     * else function will be called.
     *
     * Because this is not an actual loop, continue and break will not work. This
     * is replaced by the return value. Return Functions::CONTINUE (or null) to continue,
     * or Functions::BREAK to stop the iteration.
     * 
     * @param array|Traversable The data to be traversed
     * @param callable $loop The loop function.
     * @param callable $else The else function, called when the iterable is empty
     * @return int The number of iterations
     */
    public static function fee(&$iterable, callable $loop, callable $else = null)
    {
        if (is_object($loop))
            $refl = new \ReflectionMethod($loop, '__invoke');
        elseif (is_array($loop))
            $refl = new \ReflectionMethod($loop[0], $loop[1]);
        else
            $refl = new \ReflectionFunction($loop);

        $key_val = $refl->getNumberOfParameters() > 1;

        $in_loop = 0;
        foreach ($iterable as $key => &$element)
        {
            ++$in_loop;
            $ret = null;
            if ($key_val)
                $ret = $loop($key, $element);
            else
                $ret = $loop($element);

            if ($ret === Functions::BREAK)
                break;
        }

        if ($in_loop === 0 && $else !== null)
            $else($iterable);

        return $in_loop;
    }

    /**
     * Convert any object to a string representation.
     *
     * @param mixed $obj The variable to convert to a string
     * @param bool $html True to add line breaks as <br>, false to add them as \n
     * @param int $depth The recursion counter. When this increases above 1, '...'
     *                   is returned
     * @return string The value converted to a string
     */
    public static function str($obj, $html = false, $depth = 0)
    {
        if (is_null($obj))
            return "NULL";

        if (is_bool($obj))
            return $obj ? "TRUE" : "FALSE";

        if (is_scalar($obj))
            return (string)$obj;


        $str = "";
        if ($obj instanceof Throwable)
        {
            $str = self::exceptionToString($obj);
        }
        else if (is_object($obj) && method_exists($obj, '__toString'))
        {
            $str = (string)$obj;
        }
        else if (is_object($obj) && $obj instanceof \JsonSerializable)
        {
            $obj = $obj->jsonSerialize();
        }

        if (empty($str) && is_array($obj))
        {
            if ($depth > 1)
                return '[...]';
            $vals = [];
            foreach ($obj as $k => $v)
            {
                $repr = "";
                if (!is_int($k))
                    $repr = "'$k' => ";
                
                $repr .= self::str($v, $html, $depth + 1);
                $vals[] = $repr;
            }
            return '[' . implode(', ', $vals) . ']';
        }
        elseif (empty($str))
        {
            $str = self::sprint_r($obj);
        }

        if ($html)
        {
            $str = nl2br($str);
            $str = str_replace('  ', '&nbsp;&nbsp;', $str);
        }

        return $str;
    }

    public static function html($obj)
    {
        return self::str($obj, true);
    }

    /**
     * Format an object using print_r, formatting to a singe line object notation
     */
    public static function sprint_r($obj)
    {
        ob_start();
        print_r($obj);
        $str = ob_get_contents();
        ob_end_clean();

        $str = preg_replace("/\s+\\(\s+/", " ( ", $str);
        $str = preg_replace("/\n\s+/", ", ", $str);
        $str = preg_replace("/\n\s*\\)/", " )", $str);
        return $str;
    }

    public static function exceptionToString(Throwable $ex, $buf = null, int $depth = 0)
    {
        $buf_created = false;
        if ($buf === null)
        {
            $buf_created = true;
            $buf = fopen("php://memory", "rw");
        }

        if ($depth >= 5)
            fprintf($buf, "    ** Recursion limit reached at Exception of class " . get_class($ex) . " **\n");

        fprintf($buf, "Exception: %s [%d] %s\n", get_class($ex), $ex->getCode(), $ex->getMessage());
        fprintf($buf, "In %s(%d)\n", $ex->getFile(), $ex->getLine());
        self::printIndent($buf, $ex->getTraceAsString(), 4);

        $prev = $ex->getPrevious();
        if ($prev !== null)
        {
            fprintf($buf, "\nCaused by: \n");
            self::exceptionToString($prev, $buf, $depth + 1);
        }


        if ($buf_created)
        {
            $length = ftell($buf);
            fseek($buf, 0);
            $contents = fread($buf, $length);
            return $contents;
        }

        return $buf;
    }

    public static function printIndent($buf, string $text, int $indent = 4)
    {
        $parts = explode("\n", $text);
        $indent = str_repeat(' ', $indent);
        foreach ($parts as $p)
            fprintf($buf, "%s%s\n", $indent, $p);
    }

    /**
     * Log an output message to a specified stream
     * @param string $format The formatting string
     * @param ... Arguments to fprintf. Non-scalar arguments will be converted to a string using WF::str
     */
    public static function debug(string $format)
    {
        if (!is_resource(self::$debug_stream))
            self::$debug_stream = fopen('php://output', 'w');

        $args = func_get_args();
        foreach ($args as $idx => &$arg)
        {
            if ($idx === 0)
                $arg .= PHP_SAPI === 'cli' ? "\n" : "<br>\n";

            if (!is_scalar($arg))
                $arg = static::str($arg);
        }

        array_unshift($args, self::$debug_stream);
        call_user_func_array('fprintf', $args);
    }

    /**
     * Set the output stream used by WF::debug
     */
    public static function setDebugStream($stream)
    {
        self::$debug_stream = is_resource($stream) ? $stream : null;
    }

    /**
     * Fill a placeholder string
     * 
     * @param string $msg The message to replace placeholder in
     * @param array $values The assocative array containing the placeholders
     * @return string The filled string
     */
    public static function fillPlaceholders(string $msg, array $values)
    {
        foreach ($values as $key => $value)
            $msg = str_replace('{' . $key . '}', self::str($value), $msg);
        return $msg;
    }

    /**
     * Restrict the value to a specific valid range.
     * 
     * @param int $value The value to clamp
     * @param int $min THe minimum allowable value
     * @param int $max The maximum allowable value
     * @return int The value, $min when $value is less than $min, or $max when $value is more than max.
     */
    public static function clamp(int $value, int $min, int $max)
    {
        return $value < $min ? $min : ($value > $max ? $max : $value);
    }
}
