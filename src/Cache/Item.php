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

use Psr\Cache\CacheItemInterface;

use Wedeto\Util\Cache;
use Wedeto\Util\Type;
use Wedeto\Util\ErrorInterceptor;

/**
 * Cache Item, providing PSR-6 compatible caching
 */
class Item implements CacheItemInterface
{
    protected $key;
    protected $value;

    /**
     * Construct the Cache Item, using a cache and a key
     * @param string $key The key
     * @param Cache $value The cache instance containing the value
     */
    public function __construct(string $key, Cache $value)
    {
        $this->key = $key;
        $this->value = $value;
    }

    /**
     * Returns the key for the current cache item.
     *
     * The key is loaded by the Implementing Library, but should be available
     * to the higher level callers when needed.
     *
     * @return string The key string for this cache item.
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * Retrieves the value of the item from the cache associated with this object's key.
     *
     * The value returned must be identical to the value originally stored by set().
     *
     * If isHit() returns false, this method MUST return null. Note that null
     * is a legitimate cached value, so the isHit() method SHOULD be used to
     * differentiate between "null value was found" and "no value was found."
     *
     * @return mixed The value corresponding to this cache item's key, or null
     *               if not found.
     */
    public function get()
    {
        if (!$this->isHit())
            return null;

        $unserialize = new ErrorInterceptor('unserialize');
        $unserialize->register(E_NOTICE, 'unserialize');

        $val = $this->value->getString('_cached_item');
        $val = $unserialize->execute($val);

        $errors = $unserialize->getInterceptedErrors();
        return count($errors) === 0 ? $val : null;
    }

    /**
     * Confirms if the cache item lookup resulted in a cache hit.
     *
     * Note: This method MUST NOT have a race condition between calling isHit()
     * and calling get().
     *
     * @return bool True if the request resulted in a cache hit. False
     *              otherwise.
     */
    public function isHit()
    {
        if ($this->value->isExpired())
            return false;

        return $this->value->has('_cached_item', Type::STRING);
    }

    /**
     * Sets the value represented by this cache item.
     *
     * The $value argument may be any item that can be serialized by PHP,
     * although the method of serialization is left up to the Implementing
     * Library.
     *
     * @param mixed $value The serializable value to be stored.
     *
     * @return Item Provides fluent interface
     */
    public function set($value)
    {
        $this->value->set('_cached_item', serialize($value));
        $this->value->resetExpired();
        return $this;
    }

   /**
     * Sets the expiration time for this cache item.
     *
     * @param \DateTimeInterface|null $expiration
     *   The point in time after which the item MUST be considered expired.
     *   If null is passed explicitly, a default value MAY be used. If none is set,
     *   the value should be stored permanently or for as long as the
     *   implementation allows.
     *
     * @return Item Provides fluent interface
     */
    public function expiresAt($expiration)
    {
        if ($expiration instanceof \DateTimeInterface)
        {
            $diff = $expiration->getTimestamp() - time();
            $this->value->setExpiry($diff);
        }
        elseif ($expiration === null)
        {
            $this->value->setExpiry(null);
        }
        return $this;
    }

    /**
     * Sets the expiration time for this cache item.
     *
     * @param int|\DateInterval|null $time
     *   The period of time from the present after which the item MUST be
     *   considered expired. An integer parameter is understood to be the time
     *   in seconds until expiration. If null is passed explicitly, a default
     *   value MAY be used.  If none is set, the value should be stored
     *   permanently or for as long as the implementation allows.
     *
     * @return Item Provides fluent interface
     */
    public function expiresAfter($time)
    {
        if (is_int($time))
            $time = new \DateInterval("PT" . $time . "S");

        if ($time instanceof \DateInterval)
        {
            $now = new \DateTimeImmutable();
            $expires = $now->add($time);
            $this->expiresAt($expires);
        }
        elseif ($expiration === null)
        {
            $this->expiresAt(null);
        }
        return $this;
    }
}
