<?php
/*
This is part of Wedeto, The WEb DEvelopment TOolkit.
It is published under the MIT Open Source License.

Copyright 2018, Egbert van der Wal

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

namespace Wedeto\Util\DI;

use PHPUnit\Framework\TestCase;
use Wedeto\Util\Hook;
use Wedeto\Util\TypedDictionary;
use Wedeto\Util\Dictionary;

/**
 * @covers Wedeto\Util\DI\Injector
 */
final class InjectorTest extends TestCase
{
    public function tearDown()
    {
        Hook::resetHook('Wedeto.Util.DI.Injector.newInstance');
    }

    public function testHookCanProvideSubclass()
    {
        $injector = new Injector;

        $instance = $injector->getInstance(InjectorTestMockClass::class);
        $this->assertEquals(InjectorTestMockClass::class, get_class($instance), "The instance should match the base class");

        Hook::subscribe('Wedeto.Util.DI.Injector.newInstance', [static::class, 'diHook']);
        $injector->clearInstance(InjectorTestMockClass::class);

        $instance = $injector->getInstance(InjectorTestMockClass::class);
        $this->assertEquals(InjectorTestMockedClass::class, get_class($instance), "The instance should match the subclass");

    }

    public function testCanSetAndClearInstance()
    {
        $injector = new Injector;

        // Check that clear instances doesn't thrown an exception when none are present
        $injector->clearInstance(InjectorTestMockClass::class);

        $instance = $injector->getInstance(Stdclass::class);
        $this->assertInstanceOf(\StdClass::class, $instance, "A Stdclass object should be returned");

        $instance2 = $injector->getInstance(Stdclass::class);
        $this->assertSame($instance, $instance2, "The same Stdclass should be returned when calling again");

        $injector->clearInstance(Stdclass::class);
        $instance3 = $injector->getInstance(Stdclass::class);
        $this->assertSame($instance, $instance2, "A new Stdclass should be returned when calling again");

        $instance4 = new Stdclass;
        $injector->setInstance(Stdclass::class, $instance4);
        $instance5 = $injector->getInstance(Stdclass::class);
        $this->assertSame($instance5, $instance4, "The instance set should be returned");
        $this->assertNotSame($instance5, $instance3, "A different instance should be returned than before");
        $this->assertNotSame($instance5, $instance2, "A different instance should be returned than before");

        $injector->clearInstance(Stdclass::class);
        $instance6 = $injector->getInstance(Stdclass::class);
        $this->assertNotSame($instance6, $instance5, "The cleared instance should not be returned");
    }

    public function testComplexConstructors()
    {
        $injector = new Injector;

        $instance = $injector->getInstance(InjectorTestComplexConstructorClass::class);
        $this->assertInstanceOf(InjectorTestComplexConstructorClass::class, $instance, "The returned object should be appropriate");
        $this->assertInstanceOf(InjectorTestMockClass::class, $instance->arg1, "The first argument should be a InjectorTestMockClass instance");
        $this->assertInstanceOf(\DateTime::class, $instance->arg2, "The second argument should be a DateTime instance");

        $instance2 = $injector->getInstance(InjectorTestComplexerClass::class);
        $this->assertInstanceOf(InjectorTestComplexerClass::class, $instance2, "The returned object should be appropriate");
        $this->assertSame($instance, $instance2->arg1, "The first argument should be the same mock object");
        $this->assertInstanceOf(Stdclass::class, $instance2->arg2, "The second argument should be Stdclass object");
        $this->assertEquals(Injector::DEFAULT_SELECTOR, $instance2->arg3, "The third argument should be the DI selector");

        // Clear injector
        $injector = new Injector;
        $instance3 = $injector->getInstance(InjectorTestComplexerClass::class);
        $this->assertInstanceOf(InjectorTestComplexerClass::class, $instance3, "The returned object should be appropriate");
        $this->assertNotSame(
            $instance, 
            $instance3->arg1, 
            "The first argument should be a new instance of InjectorTestComplexConstructorClass"
        );
        $this->assertInstanceOf(
            InjectorTestMockClass::class, 
            $instance3->arg1->arg1, 
            "The first argument to the nested object should be a InjectorTestMockClass instance"
        );
        $this->assertInstanceOf(
            \DateTime::class,
            $instance3->arg1->arg2, 
            "The second argument to the nested object should be a DateTime instance"
        );
        $this->assertInstanceOf(
            Stdclass::class, 
            $instance3->arg2, 
            "The second argument should be Stdclass object"
        );
        $this->assertEquals(
            Injector::DEFAULT_SELECTOR,
            $instance3->arg3, 
            "The third argument should be the DI selector"
        );
    }

    public function testSelectors()
    {
        $injector = new Injector();

        $instance = $injector->getInstance(Stdclass::class);
        $instance2 = $injector->getInstance(Stdclass::class);
        $instance3 = $injector->getInstance(Stdclass::class, "other");

        $this->assertSame($instance, $instance2, "Repeated calls should return the same object");
        $this->assertNotSame($instance, $instance3, "A selector should return a different object");

        $instance4 = new Stdclass;
        $injector->setInstance(Stdclass::class, $instance4, "other");

        $instance5 = $injector->getInstance(Stdclass::class);
        $instance6 = $injector->getInstance(Stdclass::class, "other");
        $this->assertSame($instance, $instance5, "Default selector should still return same instance");
        $this->assertNotSame($instance, $instance6, "The new selector should now return a different object");
        $this->assertSame($instance4, $instance6, "The new selector should return the instance set using setInstance");

        $injector->clearInstance(Stdclass::class, "other");
        $instance7 = $injector->getInstance(Stdclass::class, "other");
        $this->assertNotSame($instance, $instance7, "The new selector should return the a new instance after clearing");
        $this->assertNotSame($instance5, $instance7, "The new selector should return not return the same instance as the default selector");
    }

    public function testSharedSelector()
    {
        $injector = new Injector();

        $instance = $injector->getInstance(\Stdclass::class);
        $instance2 = $injector->getInstance(\Stdclass::class);

        $this->assertNotSame($instance, $instance2, "\Stdclass is not reusable and should thus return separate instances");

        $instance->shared = true;
        $injector->setInstance(\Stdclass::class, $instance, Injector::SHARED_SELECTOR);
        
        $instance3 = $injector->getInstance(\Stdclass::class);
        $this->assertSame($instance, $instance3, "The shared instance should be returned when a direct match is found");
        $this->assertTrue($instance3->shared);

        $instance4 = $injector->getInstance(\Stdclass::class, Injector::SHARED_SELECTOR);
        $this->assertSame($instance, $instance4, "The shared instance can also be retrieved directly");
        $this->assertTrue($instance4->shared);

        $injector->clearInstance(\Stdclass::class, Injector::SHARED_SELECTOR);
        $instance5 = $injector->getInstance(\Stdclass::class);
        $this->assertNotSame($instance, $instance5, "After clearing the shared instance, a new one should be returned");

        $this->expectException(DIException::class);
        $this->expectExceptionMessage("Refusing to instantiate");
        $injector->getInstance(\Stdclass::class, Injector::SHARED_SELECTOR);
    }

    public function testCopyConstructor()
    {
        $injector = new Injector();

        $instance = new Stdclass;
        $injector->setInstance(Stdclass::class, $instance);

        $injector2 = new Injector($injector);
        $instance2 = $injector2->getInstance(Stdclass::class);
        
        $this->assertSame($instance2, $instance, "The instance should be copied");

        $instance3 = $injector->getInstance(\DateTime::class);
        $instance4 = $injector2->getInstance(\DateTime::class);
        $this->assertNotSame($instance3, $instance4, "Instances created after copy should not be available");

        $instance6 = new \DateTimeZone("Europe/Amsterdam");
        $injector2->setInstance(\DateTimeZone::class, $instance6);

        $instance7 = $injector2->getInstance(\DateTimeZone::class);
        $this->assertSame($instance6, $instance7, "After setting the instance should be available");
    
        $this->expectException(DIException::class);
        $this->expectExceptionMessage("Unable to determine value for parameter timezone for constructor of 'DateTimeZone'");
        $injector->getInstance(\DateTimeZone::class);
    }

    public function testNonResuableClasses()
    {
        $injector = new Injector();

        $instance = $injector->getInstance(Dictionary::class);
        $this->assertInstanceOf(Dictionary::class, $instance);

        $instance2 = $injector->getInstance(Dictionary::class);
        $this->assertNotSame($instance, $instance2, "Classes without WDI_REUSABLE constant should be re-instantiated");

        $injector->setInstance(Dictionary::class, $instance2);
        $instance3 = $injector->getInstance(Dictionary::class);
        $this->assertSame($instance2, $instance3, "Non-reusable classes can explicitly be assigned in injector");
    }

    public function testCyclicDependenciesThrowException()
    {
        $injector = new Injector();

        $this->expectException(DIException::class);
        $this->expectExceptionMessage("Cyclic dependencies");
        $injector->getInstance(InjectorTestCyclicA::class);
    }
    
    public function testSetInvalidInstanceThrowsException()
    {
        $injector = new Injector();

        $this->expectException(DIException::class);
        $this->expectExceptionMessage("Instance should be a subclass");
        $injector->setInstance(Stdclass::class, new InjectorTestMockClass);
    }

    public function testNonExistingClassThrowsException()
    {
        $injector = new Injector();

        $this->expectException(DIException::class);
        $this->expectExceptionMessage("Class FooBarBaz does not exist");
        $injector->getInstance(\FooBarBaz::class);
    }

    public function testNoAutoClassShouldNotInstantiate()
    {
        $injector = new Injector();

        $this->expectException(DIException::class);
        $this->expectExceptionMessage("WDI_NO_AUTO is true");
        $injector->getInstance(InjectorTestMockClassNoAuto::class);
    }

    public function testClassWithPrivateConstructorDoesNotInstantiate()
    {
        $injector = new Injector();

        $this->expectException(DIException::class);
        $this->expectExceptionMessage("does not have a public constructor");
        $injector->getInstance(InjectorTestMockClassPrivate::class);
    }

    public function testClassRemap()
    {
        $injector = new Injector();

        $injector->remap(\Stdclass::class, Stdclass::class);
        
        $instance = $injector->newInstance(\Stdclass::class);
        $this->assertInstanceOf(Stdclass::class, $instance);
    }

    public static function diHook(Dictionary $args)
    {
        $args['instance'] = new InjectorTestMockedClass;
    }
}

class Stdclass extends \Stdclass
{
    const WDI_REUSABLE = true;
}

class InjectorTestMockClass
{
    const WDI_REUSABLE = true;
}

class InjectorTestMockClassNoAuto
{
    const WDI_REUSABLE = true;
    const WDI_NO_AUTO = true;
}

class InjectorTestMockClassPrivate
{
    private function __construct() {}
}

class InjectorTestMockedClass extends InjectorTestMockClass
{}

class InjectorTestComplexConstructorClass
{
    const WDI_REUSABLE = true;

    public $arg1;
    public $arg2;

    public function __construct(InjectorTestMockClass $arg1, \DateTime $arg2)
    {
        $this->arg1 = $arg1;
        $this->arg2 = $arg2;
    }
}

class InjectorTestComplexerClass
{
    const WDI_REUSABLE = true;

    public $arg1;
    public $arg2;

    public function __construct(InjectorTestComplexConstructorClass $arg1, Stdclass $arg2, string $wdiSelector)
    {
        $this->arg1 = $arg1;
        $this->arg2 = $arg2;
        $this->arg3 = $wdiSelector;
    }
}

class InjectorTestCyclicA
{
    public function __construct(InjectorTestCyclicB $b)
    {}
}

class InjectorTestCyclicB
{
    public function __construct(InjectorTestCyclicA $b)
    {}
}
