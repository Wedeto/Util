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

/**
 * Provides automatic persistent caching facilities. You can store and retrieve
 * objects in this cache. When they are available, they'll be returned,
 * otherwise null will be returned. The cache is automatically saved to PHP
 * serialized files on shutdown, and they are loaded from these files on
 * initialization.
 *
 * Changes are monitored, and only when something changes, the cache is
 * written.  However, due to the implementation of Dictionary it is possible to
 * get a reference to the inner values. This allows changing them without the
 * Cache knowing. This may lead to unsaved changed. When you use this
 * functionality, be sure to call Cache->setChanged() after your changes to
 * update the changed flag manually.
 */ 
class Cache extends Dictionary
{
    use LoggerAwareStaticTrait;

    protected static $cache_path = "";
    protected static $repository = array();
    protected static $expiry = 3600;
    protected $cache_name = null;

    /**
     * Set the expiry period
     * @param int $expiry The amount of seconds before the cache expires
     */
    public static function setDefaultExpiry(int $expiry)
    {
        self::$expiry = $expiry;
    }

    /**
     * Add the hook after the configuration has been loaded, and apply invalidation to the
     * cache once it times out.
     */
    public static function setHook()
    {
        register_shutdown_function(array(Cache::class, 'saveCache'));

        foreach (self::$repository as $name => $cache)
            self::checkExpiry($name);
    }

    /**
     * Check if the cache has expired
     * @param string $name The cache to check
     */
    private static function checkExpiry(string $name)
    {
        $expiry = self::$repository[$name]['_expiry'] ?? null;
        $timeout = $expiry ?? self::$expiry;
        $st = self::$repository[$name]['_timestamp'] ?? time();
        $expires = $st + $timeout;

        if (time() >= $expires || $timeout === 0)
        {
            self::getLogger()->debug("Cache for {0} expired - clearing", [$name]);
            $keys = array_keys(self::$repository[$name]);
            foreach ($keys as $k)
                unset(self::$repository[$name][$k]);

            self::$repository[$name]['_timestamp'] = time();
            self::$repository[$name]['_expired'] = true;
            if ($expiry)
                self::$repository[$name]['_expiry'] = $expiry;
        }
        else
            self::$repository[$name]['_expired'] = true;
    }

    /**
     * Load the cache from the cache files. The data will be stored in the class-internal cache storage
     *
     * @param $name string The name of the cache to load
     */
    private static function loadCache(string $name)
    {
        $path = self::$cache_path;
        $cache_file = $path . '/' . $name  . '.cache';

        if (file_exists($cache_file))
        {
            if (!is_readable($cache_file))
            {
                self::getLogger()->error("Cannot read cache from {0}", [$cache_file]);
                return;
            }

            try
            {
                $contents = file_get_contents($cache_file);
                $data = unserialize($contents);
                if (!is_array($data))
                    $data = [];
                self::$repository[$name] = $data;
                self::$repository[$name]['_changed'] = false;
                self::checkExpiry($name);
                return;
            }
            catch (\Throwable $t)
            {
                self::getLogger()->error("Failure loading cache {0} - removing", [$name]);
                self::getLogger()->error("Error: {0}", [$t]);
                if (is_writable($cache_file))
                    unlink($cache_file);
            }
        }
        self::getLogger()->debug("Cache {0} does not exist - creating", [$cache_file]);
        self::$repository[$name] = [];
    }

    /**
     * Save the cache once the script terminates. Is attached as a shutdown
     * hook by calling Cache::setHook
     */
    public static function saveCache()
    {
        $cache_dir = self::$cache_path;
        $cnt = 0;
        foreach (self::$repository as $name => &$cache)
        {
            if (empty($cache['_changed']))
                continue;

            ++$cnt;
            unset($cache['_changed']);
            $cache_file = $cache_dir . '/' . $name . '.cache';
            file_put_contents($cache_file, serialize($cache));
            Hook::execute('Wedeto.IO.FileCreated', ['filename' => $cache_file]);
        }
        return $cnt;
    }

    /**
     * Set the base directory for the cache files
     * @param string $path The cache directory
     */
    public static function setCachePath(string $path)
    {
        $rpath = substr($path, 0, 6) === "vfs://" ? $path : realpath($path);
        if (empty($rpath) || !file_exists($path))
            throw new InvalidArgumentException("Path does not exist: " . $rpath);
        self::$cache_path = $rpath;
    }

    /**
     * Get the base directory for the cache files
     */
    public static function getCachePath()
    {
        return self::$cache_path;
    }

    /**
     * Create a cache
     * @param $name string The name of the cache, determines the file name
     *
     */
    public function __construct(string $name)
    {
        // Fix the path to the current working directory if nothing has been
        // configured yet
        // @codeCoverageIgnoreStart
        if (empty(self::$cache_path))
            self::setCachePath(getcwd());
        // @codeCoverageIgnoreEnd

        $this->cache_name = $name;
        if (!isset(self::$repository[$name]))
            self::loadCache($name);
        else
            self::checkExpiry($name);

        // Attach to the correct repository
        $this->values = &self::$repository[$name];
    }

    /**
     * Set a value in the cache
     *
     * @param $key scalar The key under which to store. Can be repeated to go deeper.
     * @param $val mixed The value to store. Should be PHP-serializable. If
     *                   this is null, the entry will be removed from the cache
     * @return Cache Provides fluent interface
     */
    public function set($key, $val)
    {
        parent::set(func_get_args(), null); 
        $this->values['_changed'] = true;
        return $this;
    }

    /**
     * Set the changed flag to true, triggering a save on exit. Be sure
     * to call this if you extract values by reference and change them.
     *
     * @return Cache Provides fluent interface
     */
    public function setChanged()
    {
        $this->values['_changed'] = true;
        return $this;
    }

    /**
     * Set the amount of seconds before this cache expires
     * @param int $expiry The amount of seconds
     *
     * @return Cache Provides fluent interface
     */
    public function setExpiry(int $expiry)
    {
        $this->values['_expiry'] = $expiry;
        unset($this->values['_expired']);
        $this->setChanged();
        return $this;
    }

    /**
     * Reset the expired state of the cache
     * @return Cache Provides fluent interface
     */
    public function resetExpired()
    {
        unset($this->values['_expired']);
        return $this;
    }

    /**
     * @return bool True if the cache was expired after loading, false if it was not
     */
    public function isExpired()
    {
        return !empty($this->get('_expired']));
    }

    /**
     * Remove all contents from the cache
     *
     * @return Cache Provides fluent interface
     */
    public function clear()
    {
        $expiry = $this->values['_expiry'] ?? null;
        parent::clear();
        $this->set('_changed', true);
        $this->set('_timestamp', time());
        if ($expiry)
            $this->setExpiry($expiry);
        return $this;
    }

    public static function unloadCache(string $name)
    {
        if (!defined('WEDETO_TEST') || WEDETO_TEST === 0) throw new \RuntimeException('Refusing to run outside of tests');
        unset(self::$repository[$name]);
    }
}
