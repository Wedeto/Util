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
use Wedeto\Util\Validation\Type;
use Psr\Log\NullLogger;

/**
 * Provide hook interface.
 * 
 * Modules that want to be modifiable can call
 *
 * Hook::execute("my.hook.name", ['my' => 'parameters']);
 *
 * To have their hooks executed, and inspect the resulting parameter
 * Dictionary. Execute returns the Dictionary containing the resulting
 * parameters.
 *
 * To hook into the module, call:
 *
 * Hook::subscribe('my.hook.name', function (Dictionary $params) { // your code });
 *
 * Somewhere in your initialization code. Any Hook subscriber can throw HookInterrupted 
 * to stop execution directly, skipping remaining other subscribers. Any other exceptions
 * will be caught, logged and ignored, continuing with other subscribers.
 */
class Hook
{
    use LoggerAwareStaticTrait;

    /** Precendence value to suggest the hook should run last */
    const RUN_FIRST = PHP_INT_MIN;

    /** Precedence value to suggest hook should run first */
    const RUN_LAST = PHP_INT_MAX;

    /** The hook run at shutdown */
    const SHUTDOWN_HOOK = 'Wedeto.Util.ShutdownHook';

    protected static $logger = null;

    /** The registered hooks */
    protected static $hooks = array();

    /** The sequence number */
    protected static $sequence = 0;

    /** A list of hooks that have been paused */
    protected static $paused = array();

    /** The number of times each hook has been executed */
    protected static $counters = array();

    /** Hooks currently in execution */
    protected static $in_progress = array();

    /** True once the shutdown hook as been registered */
    protected static $shutdown_hook = false;

    /**
     * Subscribe to a hook.
     *
     * @param string $hook The hook to hook into. Must contain of at least 2
     *                     parts separated by dots: vendor.hookname
     * @param callable $callback The callback that will be called when the hook
     *                           is executed.  The function should have the
     *                           following signature: function (Dictionary $params);
     * @param int $precedence The lower this number, the sooner it will be
     *                        called, the higher the later. Default is 0.
     *                        Subscribers with equal precedence will be called
     *                        in the order they were registered.
     * @return int The hook reference number. Can be used to unsubscribe
     */
    public static function subscribe(string $hook, callable $callback, int $precedence = 0)
    {
        $parts = explode(".", $hook);
        if (count($parts) < 2)
            throw new InvalidArgumentException("Hook name must consist of at least two parts");

        if ($hook === Hook::SHUTDOWN_HOOK)
            self::registerShutdownHook();

        // Make sure the callback is appropriate
        if (is_object($callback))
            $callback = [$callback, '__invoke'];

        $refl = is_array($callback) ? new \ReflectionMethod($callback[0], $callback[1]) : new \ReflectionFunction($callback);
        $params = $refl->getParameters();
        if (count($params) !== 1)
            throw new InvalidArgumentException("Hook must accept exactly one argument of type Dictionary");
        
        if ($params[0]->getType() === null || $params[0]->getType()->__toString() !== Dictionary::class)
            throw new InvalidArgumentException("Hook must accept exactly one argument of type Dictionary");

        $ref = ++self::$sequence;
        self::$hooks[$hook][] = ['precedence' => $precedence, 'callback' => $callback, 'ref' => $ref];
        usort(
            self::$hooks[$hook], 
            function (array $l, array $r) { 
                if ($l['precedence'] !== $r['precedence'])
                    return $l['precedence'] - $r['precedence']; 
                return $l['ref'] - $r['ref'];
            }
        );

        return $ref;
    }

    /**
     * Unsubscribe from a hook.
     *
     * @param string $hook The hook to unsubscribe from
     * @param int The hook reference
     * @return bool True when the hook was removed, false if it was not present
     */
    public static function unsubscribe(string $hook, int $hook_reference)
    {
        $parts = explode(".", $hook);
        if (count($parts) < 2)
            throw new InvalidArgumentException("Hook name must consist of at least two parts");

        if (!isset(self::$hooks[$hook]))
            throw new InvalidArgumentException("Hook was not set");

        foreach (self::$hooks[$hook] as $idx => $hook_data)
        {
            if ($hook_data['ref'] === $hook_reference)
            {
                unset(self::$hooks[$hook][$idx]);
                return true;
            }
        }
        return false;
    }
    
    /**
     * Called to register the shutdown hook
     */
    private static function registerShutdownHook()
    {
        if (self::$shutdown_hook === false)
        {
            self::$shutdown_hook = true;
            register_shutdown_function([static::class, 'executeShutdownHook']);
        }
    }

    /**
     * Called by PHP when the script is about to terminate. 
     * Executes any registered shutdown hook.
     */
    public static function executeShutdownHook()
    {
        static::execute(Hook::SHUTDOWN_HOOK, []);
    }

    /**
     * @return bool True if the shutdown hook is registered, false if not
     */
    public static function isShutdownHookRegistered()
    {
        return self::$shutdown_hook;
    }

    /**
     * Call the specified hook with the provided parameters.
     * @param array $params The parameters for the hook. You can pass in
     *                      an array or any traversable object. If it
     *                      is not yet an instance of Dictionary, it will
     *                      be converted to a TypedDictionary to fix the types.
     * @return Dictionary The collected responses of the hooks.
     */
    public static function execute(string $hook, $params)
    {
        if (!($params instanceof Dictionary))
            $params = TypedDictionary::wrap($params);

        if (isset(self::$in_progress[$hook]))
            throw new RecursionException("Recursion in hooks is not supported");

        self::$in_progress[$hook] = true;

        // Count invoked hooks
        if (!isset(self::$counters[$hook]))
            self::$counters[$hook] = 1;
        else
            ++self::$counters[$hook];

        // Add the name of the hook to the parameters
        if ($params instanceof TypedDictionary)
            $params->setType('hook', Type::STRING);
        $params['hook'] = $hook;

        // Check if the hook has any subscribers and if it hasn't been paused
        if (isset(self::$hooks[$hook]) && empty(self::$paused[$hook]))
        {
            // Call hooks and collect responses
            foreach (self::$hooks[$hook] as $subscriber)
            {
                $cb = $subscriber['callback'];

                try
                {
                    $cb($params);
                }
                catch (HookInterrupted $e)
                {
                    break;
                }
                catch (\Throwable $e)
                {
                    $logger = self::getLogger();
                    if (!($logger instanceof NullLogger))
                    { 
                        // Log the error when a logger is available
                        self::getLogger()->error("Callback to {0} throw an exception: {1}", [$hook, $e]);
                    }
                    else
                    { 
                        // Otherwise debug. Doing this because otherwise exceptions go completely unnoticed
                        Functions::debug($e);
                    }
                }
            }
        }

        unset(self::$in_progress[$hook]);

        return $params;
    }

    /**
     * Pause the specified hook: its subscribers will no longer be called,
     * until it is resumed.
     * @param string $hook The hook to pause
     * @param bool $state True to pause, false to unpause
     */
    public static function pause(string $hook, bool $state = true)
    {
        if (!isset(self::$hooks[$hook]))
            return;

        if ($state)
            self::$paused[$hook] = true;
        else if (isset(self::$paused[$hook]))
            unset(self::$paused[$hook]);
    }

    /**
     * Unpause the specified hook: its subscribers will be called again.
     *
     * @param string $hook The hook to unpause
     */
    public static function resume(string $hook)
    {
        self::pause($hook, false);
    }

    /**
     * Return the subscribers for the hook.
     * @param string $hook The hook name
     * @return array The list of subscribers to that hook
     */
    public static function getSubscribers(string $hook)
    {
        if (!isset(self::$hooks[$hook]))
            return array();

        $subs = [];
        foreach (self::$hooks[$hook] as $h)
            $subs[] = $h['callback'];

        return $subs;
    }

    /**
     * Return the amount of times the hook has been executed
     * @param string $hook The hook name
     * @return int The hook execution counter for this hook
     */
    public static function getExecuteCount(string $hook)
    {
        return isset(self::$counters[$hook]) ? self::$counters[$hook] : 0;
    }

    /**
     * Forget about a hook entirely. This will remove all subscribers,
     * reset the counter to 0 and remove the paused state for the hook.
     *
     * @param string $hook The hook to forget
     */
    public static function resetHook(string $hook)
    {
        if (isset(self::$hooks[$hook]))
            unset(self::$hooks[$hook]);
        if (isset(self::$counters[$hook]))
            unset(self::$counters[$hook]);
        if (isset(self::$paused[$hook]))
            unset(self::$paused[$hook]);
    }

    /**
     * Get all hooks that either have subscribers or have been executed
     * @return array The list of hooks that have been called or subscribed to.
     */
    public static function getRegisteredHooks()
    {
        $subscribed = array_keys(self::$hooks);
        $called = array_keys(self::$counters);
        $all = array_merge($subscribed, $called);
        return array_unique($all);
    }
}
