<?php

namespace WASP\Util;

use InvalidArgumentException;

/**
 * Provide hook interface.
 * 
 * Modules that want to be modifiable can call
 *
 * Hook::execute("my.hook.name", ['my' => 'parameters']);
 *
 * To have their hooks executed, and inspect the resulting parameter array. Execute
 * will also return an array containing the non-empty responses from the callbacks.
 *
 * To hook into the module, call:
 *
 * Hook::subscribe('my.hook.name', function (array &params) { // my code });
 *
 * Somewhere in your initialization code.
 */
class Hook
{
    use LoggerAwareStaticTrait;

    /** Precendence value to suggest the hook should run last */
    const RUN_FIRST = PHP_INT_MIN;

    /** Precedence value to suggest hook should run first */
    const RUN_LAST = PHP_INT_MAX;

    protected static $logger = null;

    /** The registered hooks */
    protected static $hooks = array();

    /** The sequence number */
    protected static $sequence = 0;

    /** A list of hooks that have been paused */
    protected static $paused = array();

    /** The number of times each hook has been executed */
    protected static $counters = array();

    /**
     * Subscribe to a hook.
     *
     * @param string $hook The hook to hook into. Must contain of at least 2
     *                     parts separated by dots: vendor.hookname
     * @param callable $callback The callback that will be called when the hook
     *                           is executed.  The function should have the
     *                           following signature: function (array
     *                           &$params);
     * @param int $precedence The lower this number, the sooner it will be
     *                        called, the higher the later. Default is 0.
     *                        Subscribers with equal precendece will be called
     *                        in the order they were registered.
     */
    public static function subscribe(string $hook, callable $callback, int $precedence = 0)
    {
        $parts = explode(".", $hook);
        if (count($parts) < 2)
            throw new InvalidArgumentException("Hook name must consist of at least two parts");

        self::$hooks[$hook][] = ['precedence' => $precedence, 'callback' => $callback, 'seq' => ++self::$sequence];
        usort(
            self::$hooks[$hook], 
            function (array $l, array $r) { 
                if ($l['precedence'] !== $r['precendence'])
                    return $l['precedence'] - $r['precedence']; 
                return $l['seq'] - $r['seq'];
            }
        );
    }

    /**
     * Call the specified hook with the provided parameters.
     * @param array &$params The parameters for the hook. This is passed as a reference
     *                       to the subsribers so it can be modified.
     * @return array The collected responses of the hooks.
     */
    public static function execute(string $hook, array $params)
    {
        // Count invoked hooks
        if (!isset(self::$counters[$hook]))
            self::$counters[$hook] = 1;
        else
            ++self::$counters[$hook];

        // Add the name of the hook to the parameters
        $params['hook'] = $hook;

        // Check if the hook has any subscribers and if it hasn't been paused
        $response = $params;
        if (!isset(self::$hooks[$hook]) || isset(self::$paused[$hook]))
            return $response;

        // Call hooks and collect responses
        foreach (self::$hooks[$hook] as $cb)
        {
            try
            {
                $r = $cb($params);
                if (is_array($r))
                    $response = array_merge($response, $r); 
            }
            catch (HookInterrupted $e)
            {
                return $e->getResponse() ?? $response;
            }
            catch (\Throwable $e)
            {
                self::getLogger()->error("Callback to {0} throw an exception: {1}", [$hook, $e]);
            }
        }

        return $response;
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
        return isset(self::$hooks[$hook]) ? self::$hooks[$hook] : array();
    }

    /**
     * Return the amount of times the hook has been executed
     * @param string $hook The hook name
     * @return int The hook execution counter for this hook
     */
    public static function getExcecuteCount(string $hook)
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
