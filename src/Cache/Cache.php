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
use Wedeto\Util\Dictionary;
use Wedeto\Util\LoggerAwareStaticTrait;

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

    protected $cache_name = null;

    /**
     * Create a cache
     * @param $name string The name of the cache, determines the file name
     *
     */
    public function __construct(string $name, array &$data)
    {
        $this->cache_name = $name;
        $this->values = &$data;
    }

    /**
     * Save the current cache
     * @return Cache Provides fluent interface
     */
    public function save()
    {
        Manager::getInstance()->saveCache($this->cache_name);
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
        $this->setChanged();
        return $this;
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
}
