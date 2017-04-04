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

/**
 * @covers Wedeto\Util\Encoding
 */
final class EncodingTest extends TestCase
{
    public function setUp()
    {
        $this->data = __DIR__ . '/data';
    }

    public function testEncoding()
    {
        // ForceUTF8 tests.
        $this->assertNotEquals(
            file_get_contents($this->data . "/test1.txt"),
            file_get_contents($this->data . "/test1Latin.txt"),
            "Source files must not use the same encoding before conversion."
        );

        $this->assertEquals(
              file_get_contents($this->data . "/test1.txt"),
              Encoding::toUTF8(file_get_contents(dirname(__FILE__)."/data/test1Latin.txt")),
              "Simple Encoding works."
        );
    }

    public function testArraysAreDifferent()
    {
        $arr1 = array(
            file_get_contents($this->data . "/test1Latin.txt"),
            file_get_contents($this->data . "/test1.txt"),
            file_get_contents($this->data . "/test1Latin.txt")
        );

        $arr2 = array(
            file_get_contents($this->data . "/test1.txt"),
            file_get_contents($this->data . "/test1.txt"),
            file_get_contents($this->data . "/test1.txt")
        );

        $this->assertNotEquals($arr1, $arr2, "Source arrays are different.");
    }

    public function test_encoding_of_arrays(){
        $arr1 = array(
            file_get_contents($this->data . "/test1Latin.txt"),
            file_get_contents($this->data . "/test1.txt"),
            file_get_contents($this->data . "/test1Latin.txt")
        );
        
        $arr2 = array(
            file_get_contents($this->data . "/test1.txt"),
            file_get_contents($this->data . "/test1.txt"),
            file_get_contents($this->data . "/test1.txt")
        );

        $this->assertEquals($arr2, Encoding::toUTF8($arr1), "Encoding of array works.");
    }

    public function testUTF8Maintained()
    {
        $this->assertEquals(
            file_get_contents($this->data . "/test1.txt"),
            Encoding::fixUTF8(file_get_contents($this->data . "/test1.txt")),
            "fixUTF8() maintains UTF-8 string."
        );


        $this->assertNotEquals(
            file_get_contents($this->data . "/test1.txt"),
            utf8_encode(file_get_contents($this->data . "/test1.txt")),
            "An UTF-8 double encoded string differs from a correct UTF-8 string."
        );

        $this->assertEquals(
            file_get_contents($this->data . "/test1.txt"),
            Encoding::fixUTF8(utf8_encode(file_get_contents(dirname(__FILE__)."/data/test1.txt"))),
            "fixUTF8() reverts to UTF-8 a double encoded string."
        );
    }

    public function test_double_encoded_arrays_are_different(){
        $arr1 = array(
            utf8_encode(file_get_contents($this->data . "/test1Latin.txt")),
            utf8_encode(file_get_contents($this->data . "/test1.txt")),
            utf8_encode(file_get_contents($this->data . "/test1Latin.txt"))
        );

        $arr2 = array(
            file_get_contents($this->data . "/test1.txt"),
            file_get_contents($this->data . "/test1.txt"),
            file_get_contents($this->data . "/test1.txt")
        );

        $this->assertNotEquals($arr1, $arr2, "Source arrays are different (fixUTF8).");
    }

    public function test_double_encoded_arrays_fix()
    {
        $arr1 = array(
            utf8_encode(file_get_contents(dirname(__FILE__)."/data/test1Latin.txt")),
            utf8_encode(file_get_contents(dirname(__FILE__)."/data/test1.txt")),
            utf8_encode(file_get_contents(dirname(__FILE__)."/data/test1Latin.txt"))
        );

        $arr2 = array(
          file_get_contents(dirname(__FILE__)."/data/test1.txt"),
          file_get_contents(dirname(__FILE__)."/data/test1.txt"),
          file_get_contents(dirname(__FILE__)."/data/test1.txt")
        );

        $this->assertEquals($arr2, Encoding::fixUTF8($arr1), "Fixing of double encoded array works.");
    }
    
    public function testStrings()
    {
        $this->assertEquals(
            "Fédération Camerounaise de Football\n",
            Encoding::fixUTF8("FÃÂ©dération Camerounaise de Football\n"),
            "fixUTF8() Example 1 still working."
        );

        $this->assertEquals(
            "Fédération Camerounaise de Football\n",
            Encoding::fixUTF8("FÃ©dÃ©ration Camerounaise de Football\n"),
            "fixUTF8() Example 2 still working."
        );

        $this->assertEquals(
            "Fédération Camerounaise de Football\n",
            Encoding::fixUTF8("FÃÂ©dÃÂ©ration Camerounaise de Football\n"),
            "fixUTF8() Example 3 still working."
        );

        $this->assertEquals(
            "Fédération Camerounaise de Football\n",
            Encoding::fixUTF8("FÃÂÂÂÂ©dÃÂÂÂÂ©ration Camerounaise de Football\n"),
            "fixUTF8() Example 4 still working."
        );
    }
}
