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

use Serializable;

/**
 * Cache Item, providing PSR-6 compatible caching
 */
class Item implements CacheItemInterface, Serializable
{
    protected $key;
    protected $value;
    protected $expires = null;
    protected $hit;

    /**
     * Construct the Cache Item, using a cache and a key
     *
     * @param string $key The key
     * @param Cache $value The cache instance containing the value
     */
    public function __construct(string $key, $value, bool $hit)
    {
        $this->key = $key;
        $this->value = $value;
        $this->hit = $hit;
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

        return $this->value;
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
        if (!$this->hit)
            return false;
        
        if ($this->expires === null)
            return true;

        return $this->expires->getTimestamp() > time();
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
        $this->value = $value;
        $this->hit = true;
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
            $this->expires = $expiration;
        elseif ($expiration === null)
            $this->expires = null;

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
        {
            $neg = $time < 0;
            $abs = abs($time);
            $time = new \DateInterval("PT" . $abs . "S");
            $time->invert = $neg;
        }

        if ($time instanceof \DateInterval)
        {
            $now = new \DateTimeImmutable();
            $expires = $now->add($time);

            $this->expires = $expires;
        }
        elseif ($time === null)
        {
            $this->expires = null;
        }
        return $this;
    }

    /**
     * Serialize the item. Required for storing in the containing cache
     */
    public function serialize()
    {
        return serialize([
            'key' => $this->key,
            'value' => $this->value,
            'expires' => $this->expires,
            'hit' => $this->hit
        ]);
    }

    /**
     * Unserialize the item. Required for storing in the containing cache
     * @param string $data The data to unserialize
     */
    public function unserialize($data)
    {
        $data = unserialize($data);
        $this->key = $data['key'];
        $this->value = $data['value'];
        $this->expires = $data['expires'];
        $this->hit = $data['hit'];
    }
}
