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

use PHPUnit\Framework\TestCase;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamWrapper;
use org\bovigo\vfs\vfsStreamDirectory;
use Wedeto\IO\DirReader;

/**
 * @covers Wedeto\Util\Cache
 */
final class CacheTest extends TestCase
{
    private $dir;

    public function setUp()
    {
        vfsStreamWrapper::register();
        vfsStreamWrapper::setRoot(new vfsStreamDirectory('cachedir'));
        $this->dir = vfsStream::url('cachedir');

        Cache::setCachePath($this->dir);
    }
    
    public function tearDown()
    {
        Cache::setCachePath($this->dir);
    }

    /**
     * @covers Wedeto\Util\Cache::__construct
     * @covers Wedeto\Util\Cache::loadCache
     * @covers Wedeto\Util\Cache::get
     * @covers Wedeto\Util\Cache::put
     * @covers Wedeto\Util\Cache::saveCache
     */
    public function testConstruct()
    {
        $this->assertEquals($this->dir, Cache::getCachePath());

        $data = array('test' => array('a' => true, 'b' => false, 'c' => true), 'test2' => array(1, 2, 3));
        $file = $this->dir . '/testcache.cache';

        $dataser = serialize($data);
        file_put_contents($file, $dataser);
        unset($dataser);

        $c = new Cache('testcache');
        $this->assertEquals($c->get('test')->toArray(), $data['test']);
        $this->assertEquals($c->get('test', 'a'), true);
        $this->assertEquals($c->get('test', 'b'), false);
        $this->assertEquals($c->get('test', 'c'), true);
        $this->assertEquals($c->get('test2')->toArray(), $data['test2']);

        $c->put('test2', 'foobar');
        Cache::saveCache();

        $dataser = file_get_contents($file);
        $dataunser = unserialize($dataser);
        $this->assertEquals($dataunser['test2'], 'foobar');

        $emptyarr = array();
        $c->replace($emptyarr);
        Cache::saveCache();

        $dataser = file_get_contents($file);
        $dataunser = unserialize($dataser);
        unset($dataunser['_timestamp']); // Added by cache
        unset($emptyarr['_timestamp']);
        $this->assertEquals($emptyarr, $dataunser);

        Cache::saveCache();
    }

    /**
     * @covers Wedeto\Util\Cache::__construct
     * @covers Wedeto\Util\Cache::loadCache
     * @covers Wedeto\Util\Cache::setHook
     * @covers Wedeto\Util\Cache::get
     */
    public function testHook()
    {
        $cc = new Cache('resolve');
        Cache::setHook(0);
        $class = $cc->get('class');
        $this->assertEmpty($class);
    }

    /**
     * @covers Wedeto\Util\Cache::__construct
     * @covers Wedeto\Util\Cache::loadCache
     * @covers Wedeto\Util\Cache::get
     */
    public function testUnreadable()
    {
        $config = new Dictionary();
        $config->set('cache', 'expire', 0);

        $testdata = array('var1' => 'val1', 'var2' => 'var2');
        $data = serialize($testdata);

        $file = $this->dir . '/testcache.cache';
        $fh = fopen($file, 'w');
        fputs($fh, $data);
        fclose($fh);
        chmod($file, 000);

        $cc = new Cache('testcache');

        $contents = $cc->get();
        unset($contents['_timestamp']);
        $this->assertEmpty($contents);

        chmod($file, 666);
        unlink($file);
    }

    /**
     * @covers Wedeto\Util\Cache::__construct
     * @covers Wedeto\Util\Cache::loadCache
     * @covers Wedeto\Util\Cache::get
     */
    public function testInvalidCache()
    {
        $config = new Dictionary();
        $config->set('cache', 'expire', 0);

        $file = $this->dir . '/testcache.cache';
        $fh = fopen($file, 'w');
        fputs($fh, 'garbage-data');
        fclose($fh);

        $cc = new Cache('testcache');

        $contents = $cc->get();
        unset($contents['_timestamp']);
        $this->assertEmpty($contents);

        if (file_exists($file))
            unlink($file);
    }

    /**
     * @covers Wedeto\Util\Cache::__construct
     * @covers Wedeto\Util\Cache::loadCache
     */
    public function testNewCache()
    {
        $config = new Dictionary();
        $config->set('cache', 'expire', 0);

        $cc = new Cache('testcache2');

        $contents = $cc->get();
        unset($contents['_timestamp']);
        $this->assertEmpty($contents);

        $cc->put('test', 'bar', true);
        $this->assertTrue($cc->has('test', 'bar'));
        $this->assertFalse($cc->has('test', 'foo'));

        $all = $cc->getAll();
        $this->assertEquals([
            'test' => ['bar' => true],
            '_changed' => true
        ], $all);

        $cc->set('test', 'bar2', true);
        $all = $cc->getAll();
        $this->assertEquals([
            'test' => ['bar' => true, 'bar2' => true],
            '_changed' => true
        ], $all);
    }

    /**
     * @covers Wedeto\Util\Cache::__construct
     * @covers Wedeto\Util\Cache::get
     * @covers Wedeto\Util\Cache::put
     * @covers Wedeto\Util\Cache::clear
     */
    public function testClearCache()
    {
        $c = new Cache('testcache');

        $c->put('test', 'mock');
        $this->assertEquals('mock', $c->get('test'));

        $c->clear();
        $this->assertEquals(null, $c->get('test'));

        $a = $c->get();
        unset($a['_changed']);
        unset($a['_timestamp']);
        $this->assertEmpty($a);
    }

    public function testInvalidCachePath()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Path does not exist");
        Cache::setCachePath($this->dir . '/foo/bar');
    }
}

