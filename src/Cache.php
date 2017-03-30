<?php
/*
This is part of WASP, the Web Application Software Platform.
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

namespace WASP\Util;

/**
 * Provides automatic persistent caching facilities. You can store and retrieve
 * objects in this cache. When they are available, they'll be returned,
 * otherwise null will be returned. The cache is automatically saved to PHP
 * serialized files on shutdown, and they are loaded from these files on
 * initialization.
 */ 
class Cache
{
    use LoggerAwareStaticTrait;

    protected static $cache_path = "";
    protected static $repository = array();
    protected static $expiry = null;
    protected $cache_name = 3600;

    /**
     * Add the hook after the configuration has been loaded, and apply invalidation to the
     * cache once it times out.
     * @param int $expiry The amount of seconds before the cache expires
     */
    public static function setHook(int $expiry = 60)
    {
        register_shutdown_function(array(Cache::class, 'saveCache'));

        self::$expiry = $expiry;
        foreach (self::$repository as $name => $cache)
            self::checkExpiry($name);
    }

    /**
     * Check if the cache has expired
     * @param string $name The cache to check
     */
    private static function checkExpiry(string $name)
    {
        $timeout = self::$expiry;
        $st = isset(self::$repository[$name]['_timestamp']) ? self::$repository[$name]['_timestamp'] : time();
        $expires = $st + $timeout;

        if (time() >= $expires || $timeout === 0)
        {
            self::getLogger()->debug("Cache for {0} expired - clearing", [$name]);
            self::$repository[$name] = new Dictionary();
            self::$repository[$name]['_timestamp'] = time();
        }
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
                self::$repository[$name] = new Dictionary($data);
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
        self::$repository[$name] = new Dictionary();
    }

    /**
     * Save the cache once the script terminates. Is attached as a shutdown
     * hook by calling Cache::setHook
     */
    public static function saveCache()
    {
        $cache_dir = self::$cache_path;
        foreach (self::$repository as $name => $cache)
        {
            if (empty($cache['_changed']))
                continue;

            unset($cache['_changed']);
            $cache_file = $cache_dir . '/' . $name . '.cache';
            file_put_contents($cache_file, serialize($cache->getAll()));
            Hook::execute('WASP.IO.FileCreated', ['filename' => $cache_file]);
        }
    }

    /**
     * Set the base directory for the cache files
     * @param string $path The cache directory
     */
    public static function setCachePath(string $path)
    {
        $rpath = realpath($path);
        if (empty($rpath))
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
    public function __construct($name)
    {
        // Fix the path to the current working directory if nothing has been
        // configured yet
        if (empty(self::$cache_path))
            self::setCachePath(getcwd());

        $this->cache_name = $name;
        if (!isset($this->repository[$name]))
            self::loadCache($name);
    }


    /**
     * Get a value from the cache
     *
     * @param $key scalar The key under which to store. Can be repeated to go deeper
     * @return mixed The requested value, or null if it doesn't exist
     */
    public function &get()
    {
        if (func_num_args() === 0)
            return self::$repository[$this->cache_name]->getAll();

        return self::$repository[$this->cache_name]->dget(func_get_args(), null);
    }

    /**
     * Return a full copy of the contents of the cache
     */
    public function getAll()
    {
        return self::$repository[$this->cache_name]->getAll();
    }

    /**
     * Check if the cache contains the provided value
     */
    public function has(...$params)
    {
        return call_user_func_array(array(self::$repository[$this->cache_name], 'has'), $params);
    }
    
    /**
     * Put a value in the cache
     *
     * @param $key scalar The key under which to store. Can be repeated to go deeper.
     * @param $val mixed The value to store. Should be PHP-serializable. If
     *                   this is null, the entry will be removed from the cache
     * @return Cache Provides fluent interface
     */
    public function put($key, $val)
    {
        self::$repository[$this->cache_name]->set(func_get_args(), null);
        self::$repository[$this->cache_name]['_changed'] = true;
        return $this;
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
        self::$repository[$this->cache_name]->set(func_get_args(), null);
        self::$repository[$this->cache_name]['_changed'] = true;
        return $this;
    }

    /**
     * Replace the entire contents of the cache
     *
     * @param $replacement array The replacement for the cache
     */
    public function replace(array &$replacement)
    {
        self::$repository[$this->cache_name] = Dictionary::wrap($replacement);
        self::$repository[$this->cache_name]['_changed'] = true;
        self::$repository[$this->cache_name]['_timestamp'] = time();
    }

    /**
     * Remove all contents from the cache
     */
    public function clear()
    {
        $data = &self::$repository[$this->cache_name]->getAll();
        $keys = array_keys($data);
        foreach ($keys as $key)
            unset($data[$key]);

        $data['_changed'] = true;
        $data['_timestamp'] = time();
    }
}
