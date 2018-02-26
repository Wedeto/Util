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

use PHPUnit\Framework\TestCase;

use Wedeto\Util\DI\DI;
use Wedeto\Util\ErrorInterceptor;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamWrapper;
use org\bovigo\vfs\vfsStreamDirectory;

if (!defined('WEDETO_TEST')) define('WEDETO_TEST', 1);

/**
 * @covers Wedeto\Util\Cache\Cache
 * @covers Wedeto\Util\Cache\Manager
 */
final class CacheTest extends TestCase
{
    private $dir;
    private $mgr;

    public function setUp()
    {
        vfsStreamWrapper::register();
        vfsStreamWrapper::setRoot(new vfsStreamDirectory('cachedir'));
        $this->dir = vfsStream::url('cachedir');

        DI::startNewContext('test', false);
        
        $this->mgr = Manager::getInstance();
        $this->mgr->setCachePath($this->dir);
        $this->mgr->setDefaultExpiry(0);
        ErrorInterceptor::registerErrorHandler();
    }
    
    public function tearDown()
    {
        DI::destroyContext('test');
        $this->mgr->unsetHook();
        ErrorInterceptor::unregisterErrorHandler();
    }

    /**
     * @covers Wedeto\Util\Cache\Manager::loadCache
     * @covers Wedeto\Util\Cache\Manager::saveCache
     * @covers Wedeto\Util\Cache\Manager::setDefaultExpiry
     * @covers Wedeto\Util\Cache\Manager::getDefaultExpiry
     * @covers Wedeto\Util\Cache\Manager::setCachePath
     * @covers Wedeto\Util\Cache\Manager::getCachePath
     * @covers Wedeto\Util\Cache\Cache::__construct
     * @covers Wedeto\Util\Cache\Cache::get
     * @covers Wedeto\Util\Cache\Cache::set
     */
    public function testConstruct()
    {
        $this->mgr->setDefaultExpiry(60);
        $this->assertEquals(60, $this->mgr->getDefaultExpiry());
        $this->assertEquals($this->dir, $this->mgr->getCachePath());

        $data = array('test' => array('a' => true, 'b' => false, 'c' => true), 'test2' => array(1, 2, 3));
        $file = $this->dir . '/testcache.cache';

        $dataser = serialize($data);
        file_put_contents($file, $dataser);
        unset($dataser);

        $c = $this->mgr->getCache('testcache');
        $this->assertEquals($c->get('test')->toArray(), $data['test']);
        $this->assertEquals($c->get('test', 'a'), true);
        $this->assertEquals($c->get('test', 'b'), false);
        $this->assertEquals($c->get('test', 'c'), true);
        $this->assertEquals($c->get('test2')->toArray(), $data['test2']);

        $c->set('test2', 'foobar');
        $this->mgr->saveCache();

        $dataser = file_get_contents($file);
        $dataunser = unserialize($dataser);
        $this->assertEquals($dataunser['test2'], 'foobar');

        // Check if saving again doesn't actually save
        $this->assertEquals(0, $this->mgr->saveCache());

        // Force a re-save
        $c->setChanged();

        $this->assertEquals(1, $this->mgr->saveCache());
    }

    /**
     * @covers Wedeto\Util\Cache\Manager::loadCache
     * @covers Wedeto\Util\Cache\Manager::setHook
     * @covers Wedeto\Util\Cache\Cache::__construct
     * @covers Wedeto\Util\Cache\Cache::get
     */
    public function testHook()
    {
        $cc = $this->mgr->getCache('resolve');
        $this->mgr->setHook();
        $class = $cc->get('class');
        $this->assertEmpty($class);
    }

    /**
     * @covers Wedeto\Util\Cache\Manager::loadCache
     * @covers Wedeto\Util\Cache\Cache::__construct
     * @covers Wedeto\Util\Cache\Cache::get
     */
    public function testUnreadable()
    {
        $testdata = array('var1' => 'val1', 'var2' => 'var2');
        $data = serialize($testdata);

        $file = $this->dir . '/testcache.cache';
        $fh = fopen($file, 'w');
        fputs($fh, $data);
        fclose($fh);
        chmod($file, 000);

        $cc = $this->mgr->getCache('testcache');

        $contents = $cc->get();
        unset($contents['_timestamp']);
        $this->assertEmpty($contents);

        chmod($file, 666);
        unlink($file);
    }

    /**
     * @covers Wedeto\Util\Cache\Manager::loadCache
     * @covers Wedeto\Util\Cache\Cache::__construct
     * @covers Wedeto\Util\Cache\Cache::get
     */
    public function testInvalidCache()
    {
        $file = $this->dir . '/testcache.cache';
        $fh = fopen($file, 'w');
        fputs($fh, 'garbage-data');
        fclose($fh);

        $this->mgr->unloadCache('testcache');
        $cc = $this->mgr->getCache('testcache');

        $contents = $cc->get();
        unset($contents['_timestamp']);
        $this->assertEmpty($contents);

        $file = $this->dir . '/testcache2.cache';
        $fh = fopen($file, 'w');
        fputs($fh, serialize(new \DateTime()));
        fclose($fh);

        $cc = $this->mgr->getCache('testcache2');
        $contents = $cc->get();
        unset($contents['_timestamp']);
        $this->assertEmpty($contents);
    }

    /**
     * @covers Wedeto\Util\Cache\Cache::__construct
     * @covers Wedeto\Util\Cache\Manager::loadCache
     */
    public function testNewCache()
    {
        $cc = $this->mgr->getCache('testcache2');

        $contents = $cc->get();
        unset($contents['_timestamp']);
        $this->assertEmpty($contents);

        $cc->set('test', 'bar', true);
        $this->assertTrue($cc->has('test', 'bar'));
        $this->assertFalse($cc->has('test', 'foo'));

        $all = $cc->getAll();
        unset($all['_timestamp']);
        $this->assertEquals([
            'test' => ['bar' => true],
            '_changed' => true
        ], $all);

        $cc->set('test', 'bar2', true);
        $all = $cc->getAll();
        unset($all['_timestamp']);
        $this->assertEquals([
            'test' => ['bar' => true, 'bar2' => true],
            '_changed' => true
        ], $all);
    }

    /**
     * @covers Wedeto\Util\Cache\Cache::__construct
     * @covers Wedeto\Util\Cache\Cache::get
     * @covers Wedeto\Util\Cache\Cache::set
     * @covers Wedeto\Util\Cache\Cache::clear
     */
    public function testClearCache()
    {
        $c = $this->mgr->getCache('testcache');

        $c->set('test', 'mock');
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
        $this->mgr->setCachePath($this->dir . '/foo/bar');
    }

    public function testExpiry()
    {
        $a = $this->mgr->getCache('foocache');
        $a->setExpiry(1);

        $this->mgr->saveCache();
        $b = $this->mgr->getCache('foocache');
        $this->assertEquals(1, $a['_expiry']);
        $this->assertEquals(1, $b['_expiry']);

        $b->clear();
        $this->assertEquals(1, $a['_expiry']);
        $this->assertEquals(1, $b['_expiry']);

        $a->setExpiry(-1);
        $this->assertEquals(-1, $a['_expiry']);
        $this->assertEquals(-1, $b['_expiry']);

        $b = $this->mgr->getCache('foocache');
        $this->assertEquals(-1, $a['_expiry']);
        $this->assertEquals(-1, $b['_expiry']);
    }

    public function testUnreadableCache()
    {
        $p = $this->dir . '/testcache3.cache';
        touch($p);
        chmod($p, 0000);
        $this->assertFalse(is_readable($p));

        $c = $this->mgr->getCache('testcache3');
        $all = $c->getAll();
        unset($all['_timestamp']);
        $this->assertEmpty($all);
    }

    public function testSaveCache()
    {
        $cache = $this->mgr->getCache('savecachetest');
        $cache->set('foo', 'bar');
        $this->assertEquals($cache, $cache->save());
    }
}

