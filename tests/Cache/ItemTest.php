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

use PHPUnit\Framework\TestCase;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;

/**
 * @covers Wedeto\Util\Cache\Item
 */
final class ItemTest extends TestCase
{
    public function testItem()
    {
        $item = new Item("foo", "bar", true);
        $this->assertTrue($item->isHit());

        $item = new Item("foo", "bar", false);
        $this->assertFalse($item->isHit());

        $item = new Item("foo", "bar", true);
        $now = new \DateTimeImmutable;
        $yesterday = $now->sub(new \DateInterval('P1D'));
        $item->expiresAt($yesterday);
        $this->assertFalse($item->isHit());

        $tomorrow = $now->add(new \DateInterval('P1D'));
        $item->expiresAt($tomorrow);
        $this->assertTrue($item->isHit());

        $item->expiresAt(null);
        $this->assertTrue($item->isHit());

        $expire = new \DateInterval("PT5H");
        $item->expiresAfter($expire);
        $this->assertTrue($item->isHit());

        $expire->invert = 1;
        $item->expiresAfter($expire);
        $this->assertFalse($item->isHit());

        $item->expiresAfter(3600);
        $this->assertTrue($item->isHit());

        $item->expiresAfter(-3600);
        $this->assertFalse($item->isHit());

        $item->expiresAfter(null);
        $this->assertTrue($item->isHit());
    }

    public function testSerialize()
    {
        $item = new Item('foo', 'bar', true);
        $ser = serialize($item);
        $item_unser = unserialize($ser);
        $this->assertEquals($item, $item_unser);

        $item = new Item('foo', 'bar', false);
        $ser = serialize($item);
        $item_unser = unserialize($ser);
        $this->assertEquals($item, $item_unser);

        $item = new Item('35', '78', false);
        $ser = serialize($item);
        $item_unser = unserialize($ser);
        $this->assertEquals($item, $item_unser);
    }

    public function testGetSet()
    {
        $item = new Item('foo', 'bar', true);
        $this->assertEquals('foo', $item->getKey());
        $this->assertEquals('bar', $item->get());
        $this->assertTrue($item->isHit());

        $item = new Item('foo', 'bar', false);
        $this->assertEquals('foo', $item->getKey());
        $this->assertNull($item->get());
        $this->assertFalse($item->isHit());

        $this->assertEquals($item, $item->set('baz'));
        $this->assertEquals('foo', $item->getKey());
        $this->assertEquals('baz', $item->get());
        $this->assertTrue($item->isHit());
    }
}
