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

namespace Wedeto\Util\Cache;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;

use Wedeto\Util\Cache;
use Wedeto\Util\Functions as WF;

/**
 * Cache Item, providing PSR-6 compatible caching
 */
class Pool implements CacheItemPoolInterface
{
   /**
     * Returns a Cache Item representing the specified key.
     *
     * This method must always return a CacheItemInterface object, even in case of
     * a cache miss. It MUST NOT return null.
     *
     * @param string $key The key for which to return the corresponding Cache
     *                    Item.
     *
     * @throws InvalidArgumentException If the $key string is not a legal value
     *                                  a \Psr\Cache\InvalidArgumentException
     *                                  MUST be thrown.
     *
     * @return Item The corresponding Cache Item.
     */
    public function getItem($key)
    {
        $cache = new Cache($key);
        return new Item($key, $cache);
    }

    /**
     * Returns a traversable set of cache items.
     *
     * @param string[] $keys An indexed array of keys of items to retrieve.
     *
     * @throws InvalidArgumentException If any of the keys in $keys are not a
     *                                  legal value a \Psr\Cache\InvalidArgumentException
     *                                  MUST be thrown.
     *
     * @return array A traversable collection of Cache Items keyed by the cache
     *               keys of each item. A Cache item will be returned for each
     *               key, even if that key is not found. However, if no keys
     *               are specified then an empty traversable MUST be returned
     *               instead.
     */
    public function getItems(array $keys = array())
    {
        $res = [];
        foreach ($keys as $key)
        {
            if (!is_string($key))
                throw new InvalidArgumentException("Invalid key: " . WF::str($key));

            $res[] = $this->getItem($key);
        }
        return $res;
    }

    /**
     * Confirms if the cache contains specified cache item.
     *
     * Note: This method MAY avoid retrieving the cached value for performance reasons.
     * This could result in a race condition with CacheItemInterface::get(). To avoid
     * such situation use CacheItemInterface::isHit() instead.
     *
     * @param string $key
     *   The key for which to check existence.
     *
     * @throws InvalidArgumentException
     *   If the $key string is not a legal value a \Psr\Cache\InvalidArgumentException
     *   MUST be thrown.
     *
     * @return bool
     *   True if item exists in the cache, false otherwise.
     */
    public function hasItem($key)
    {
        return Cache::exists($key);
    }

    public function clear()
    {
        return Cache::clear();
    }

    public function deleteItem($key)
    {
        return Cache::deleteCache($key);
    }

    public function deleteItems(array $keys)
    {
        foreach ($keys as $key)
            $this->deleteItem($key);
    }

    public function save(CacheItemInterface $item)
    {
        return $this->commit();
    }

    public function saveDeferred(CacheItemInterface $item)
    {
        // No-op - cache items are persisted automatically
    }
    
    public function commit()
    {
        return Cache::saveCache();
    }
}
