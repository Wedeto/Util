<?php
/*
This is part of Wedeto, The WEb DEvelopment TOolkit.
It is published under the MIT Open Source License.

Copyright 2017-2018, Egbert van der Wal

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

namespace Wedeto\Util\Cache;

use InvalidArgumentException;
use Wedeto\Util\DI\InjectionTrait;
use Wedeto\Util\LoggerAwareStaticTrait;
use Wedeto\Util\Hook;
use Wedeto\Util\Dictionary;

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
class Manager 
{
    use LoggerAwareStaticTrait;
    use InjectionTrait;
    
    /** All users should use the same Manager */
    const WDI_REUSABLE = true;

    protected $cache_path;
    protected $repository = array();
    protected $expiry = 3600;
    protected $hook_reference = null;

    /**
     * Create the cache manager
     */
    public function __construct()
    {
        $this->cache_path = getcwd();
    }

    /**
     * Set the expiry period
     * @param int $expiry The amount of seconds before the cache expires
     */
    public function setDefaultExpiry(int $expiry)
    {
        $this->expiry = $expiry;
    }

    /**
     * @return int The amount of seconds before the cache expires
     */
    public function getDefaultExpiry()
    {
        return $this->expiry;
    }

    /**
     * Set the base directory for the cache files
     * @param string $path The cache directory
     */
    public function setCachePath(string $path)
    {
        $rpath = substr($path, 0, 6) === "vfs://" ? $path : realpath($path);
        if (empty($rpath) || !file_exists($path))
            throw new InvalidArgumentException("Path does not exist: " . $rpath);
        $this->cache_path = $rpath;
    }

    /**
     * Get the base directory for the cache files
     */
    public function getCachePath()
    {
        return $this->cache_path;
    }

    /**
     * Add the hook after the configuration has been loaded, and apply invalidation to the
     * cache once it times out.
     */
    public function setHook()
    {
        Hook::subscribe(Hook::SHUTDOWN_HOOK, [$this, 'saveCacheHook'], 10);

        foreach ($this->repository as $name => $cache)
            $this->checkExpiry($name);
    }

    /**
     * Remove the hook - cancelling automatic save on termination
     */
    public function unsetHook()
    {
        if ($this->hook_reference !== null)
        {
            Hook::unsubscribe(Hook::SHUTDOWN_HOOK, $this->hook_reference);
        }
    }

    /**
     * Check if the cache has expired
     * @param string $name The cache to check
     */
    protected function checkExpiry(string $name)
    {
        $expiry = $this->repository[$name]['_expiry'] ?? null;
        $timeout = $expiry ?? $this->expiry;
        $st = $this->repository[$name]['_timestamp'] ?? time();
        $expires = $st + $timeout;

        if (time() >= $expires || $timeout === 0)
        {
            self::getLogger()->debug("Cache for {0} expired - clearing", [$name]);
            $keys = array_keys($this->repository[$name]);
            foreach ($keys as $k)
                unset($this->repository[$name][$k]);

            $this->repository[$name]['_timestamp'] = time();
            if ($expiry)
                $this->repository[$name]['_expiry'] = $expiry;
        }
    }

    /**
     * Load the cache from the cache files. The data will be stored in the class-internal cache storage
     *
     * @param $name string The name of the cache to load
     */
    protected function loadCache(string $name)
    {
        $path = $this->cache_path;
        $cache_file = $path . '/' . $name  . '.cache';

        if (file_exists($cache_file))
        {
            if (!is_readable($cache_file))
            {
                self::getLogger()->error("Cannot read cache from {0}", [$cache_file]);
            }
            else
            {
                try
                {
                    $contents = file_get_contents($cache_file);
                    $data = unserialize($contents);
                    if (!is_array($data))
                        $data = [];
                    $this->repository[$name] = $data;
                    $this->repository[$name]['_changed'] = false;
                    $this->checkExpiry($name);
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
        }
        self::getLogger()->debug("Cache {0} does not exist - creating", [$cache_file]);
        $this->repository[$name] = [];
    }

    /**
     * Shutdown hook called when the script terminates
     *
     * @param Dictionary $params The parameters received from Hook. Not used
     */
    public function saveCacheHook(Dictionary $params)
    {
        $this->saveCache();
    }

    /**
     * Save the cache once the script terminates. Is attached as a shutdown
     * hook by calling Cache::setHook
     *
     * @param string $cache_name Can be specified to save one specific cache
     */
    public function saveCache(string $cache_name = null)
    {
        $cache_dir = $this->cache_path;
        $cnt = 0;
        foreach ($this->repository as $name => &$cache)
        {
            if ($cache_name !== null && $name !== $cache_name)
                continue;

            if (empty($cache['_changed']))
                continue;
            
            ++$cnt;
            unset($cache['_changed']);
            $cache_file = $cache_dir . '/' . $name . '.cache';
            file_put_contents($cache_file, serialize($cache));
            Hook::execute('Wedeto.IO.FileCreated', ['path' => $cache_file]);
        }
        return $cnt;
    }

    /**
     * Unload a cache completely. Can only be used when WEDETO_TEST is set to true.
     *
     * @param string $name The cache to unload
     */
    public function unloadCache(string $name)
    {
        if (!defined('WEDETO_TEST') || WEDETO_TEST === 0) throw new \RuntimeException('Refusing to run outside of tests');
        unset($this->repository[$name]);
    }

    /**
     * Return a cache
     */
    public function getCache(string $name)
    {
        if (!isset($this->repository[$name]))
            $this->loadCache($name);

        return new Cache($name, $this->repository[$name]);
    }
    
}
