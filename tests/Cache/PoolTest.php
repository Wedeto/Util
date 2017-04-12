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

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamWrapper;
use org\bovigo\vfs\vfsStreamDirectory;

use Wedeto\Util\Cache;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\CacheException;
use Psr\Cache\InvalidArgumentException;

/**
 * @covers Wedeto\Util\Cache\Pool
 */
final class PoolTest extends TestCase
{
    private $dir;

    public function setUp()
    {
        vfsStreamWrapper::register();
        vfsStreamWrapper::setRoot(new vfsStreamDirectory('cachedir'));
        $this->dir = vfsStream::url('cachedir');

        Cache::setCachePath($this->dir);
    }

    public function testConstructWithInvalidName()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid identifier");
        $pool = new Pool('');
    }

    public function testGetItemWithInvalidKey()
    {
        $pool = new Pool('mypool');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid key");
        $pool->getItem(3.5);
    }

    public function testGetItemsWithInvalidKey()
    {
        $pool = new Pool('mypool');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid key");
        $pool->getItems(['foo', 3.5]);
    }

    public function testHasItemsWithInvalidKey()
    {
        $pool = new Pool('mypool');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid key");
        $pool->hasItem(3.5);
    }

    public function testDeleteItemWithInvalidKey()
    {
        $pool = new Pool('mypool');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid key");
        $pool->deleteItem(3.5);
    }

    public function testPool()
    {
        $pool = new Pool('mypool');
        $pool->clear();
        
        $this->assertFalse($pool->hasItem('my_item'));
        $item = $pool->getItem('my_item');
        $this->assertInstanceOf(CacheItemInterface::class, $item);
        $this->assertFalse($item->isHit());
        $this->assertNull($item->get());

        $item->set('myvalue');
        $this->assertTrue($pool->save($item));
        $this->assertTrue($pool->hasItem('my_item'));

        $new_item = $pool->getItem('my_item');
        $this->assertInstanceOf(CacheItemInterface::class, $new_item);
        $this->assertTrue($item->isHit());
        $this->assertEquals('myvalue', $item->get());

        $this->assertFalse($pool->hasItem('my_second_item'));
        $item2 = $pool->getItem('my_second_item');
        $this->assertFalse($item2->isHit());

        $item2->set('foobar');
        $this->assertTrue($pool->save($item2));
        $this->assertTrue($pool->hasItem('my_second_item'));

        $items = $pool->getItems(['my_item', 'my_second_item']);
        $this->assertEquals(2, count($items));

        $this->assertTrue($items[0]->isHit());
        $this->assertEquals('my_item', $items[0]->getKey());
        $this->assertEquals('myvalue', $items[0]->get());

        $this->assertTrue($items[1]->isHit());
        $this->assertEquals('my_second_item', $items[1]->getKey());
        $this->assertEquals('foobar', $items[1]->get());

        $this->assertTrue($pool->clear());

        $pool->save($item);
        $pool->save($item2);

        $this->assertTrue($pool->hasItem('my_item'));
        $this->assertTrue($pool->hasItem('my_second_item'));
        $this->assertTrue($pool->deleteItem('my_item'));
        $this->assertFalse($pool->hasItem('my_item'));
        $this->assertTrue($pool->hasItem('my_second_item'));

        $this->assertTrue($pool->deleteItems(['my_item', 'my_second_item']));
        $this->assertFalse($pool->hasItem('my_item'));
        $this->assertFalse($pool->hasItem('my_second_item'));

        $this->assertTrue($pool->saveDeferred($item));
        $this->assertTrue($pool->hasItem('my_item'));
        $this->assertTrue($pool->commit());
    }
}
