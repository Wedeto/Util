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
              Encoding::toUTF8(file_get_contents($this->data . "/test1Latin.txt")),
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
            Encoding::fixUTF8(utf8_encode(file_get_contents($this->data . "/test1.txt"))),
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
            utf8_encode(file_get_contents($this->data . "/test1Latin.txt")),
            utf8_encode(file_get_contents($this->data . "/test1.txt")),
            utf8_encode(file_get_contents($this->data . "/test1Latin.txt"))
        );

        $arr2 = array(
          file_get_contents($this->data . "/test1.txt"),
          file_get_contents($this->data . "/test1.txt"),
          file_get_contents($this->data . "/test1.txt")
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

    public function testUTF8FixWin1252Chars()
    {
        $faulty_chars = file_get_contents($this->data . '/utf8-from-win1252-as-iso8859-1.txt');
        $correct_utf8 = file_get_contents($this->data . '/utf8-correct-contents.txt');

        $this->assertNotEquals($faulty_chars, $correct_utf8);
        $fixed = Encoding::UTF8FixWin1252Chars($faulty_chars);
        $this->assertEquals($correct_utf8, $fixed);
    }

    public function testUTF8RemoveBom()
    {
        $databom = file_get_contents($this->data . '/utf8-with-bom.txt'); 
        $datanobom = file_get_contents($this->data . '/utf8-without-bom.txt'); 

        $this->assertNotEquals($databom, $datanobom);

        $databom_removed = Encoding::removeBOM($databom);
        $this->assertEquals($datanobom, $databom_removed);
    }

    public function testNormalizeEncodings()
    {
        $this->assertEquals("ISO-8859-1", Encoding::normalizeEncoding("ISO88591"));
        $this->assertEquals("ISO-8859-1", Encoding::normalizeEncoding("ISO8859"));
        $this->assertEquals("ISO-8859-1", Encoding::normalizeEncoding("ISO"));
        $this->assertEquals("ISO-8859-1", Encoding::normalizeEncoding("LATIN1"));
        $this->assertEquals("ISO-8859-1", Encoding::normalizeEncoding("LATIN"));
        $this->assertEquals("ISO-8859-1", Encoding::normalizeEncoding("WIN1252"));
        $this->assertEquals("ISO-8859-1", Encoding::normalizeEncoding("WINDOWS1252"));
        $this->assertEquals("UTF-8", Encoding::normalizeEncoding("UTF8"));
        $this->assertEquals("UTF-8", Encoding::normalizeEncoding("UTF"));
        $this->assertEquals("UTF-8", Encoding::normalizeEncoding("FOO"));
    }

    public function testEncode()
    {
        $data_utf8 = file_get_contents($this->data . '/test-encoding-utf8.txt'); 
        $data_win1252 = file_get_contents($this->data . '/test-encoding-windows-1252.txt'); 

        $this->assertNotEquals($data_utf8, $data_win1252);

        $to_utf8 = Encoding::encode("UTF-8", $data_win1252);
        $to_win1252 = Encoding::encode("Windows-1252", $data_win1252);

        $this->assertEquals($data_utf8, $to_utf8);

        $c1 = Encoding::encode("UTF-8", $data_win1252);
        $c2 = Encoding::encode("UTF-8", $to_win1252);
        $this->assertEquals($c1, $c2);
        $this->assertEquals($data_win1252, $to_win1252);
    }

    public function testUTF8Decode()
    {
        $data_utf8 = file_get_contents($this->data . '/test-encoding-utf8.txt');

        $decode1 = Encoding::toWin1252($data_utf8, Encoding::WITHOUT_ICONV);
        $decode2 = Encoding::toWin1252($data_utf8, Encoding::ICONV_TRANSLIT);
        $decode3 = Encoding::toWin1252($data_utf8, Encoding::ICONV_IGNORE);

        $recode1 = iconv("Windows-1252", "UTF-8", $decode1);
        $recode2 = iconv("Windows-1252", "UTF-8", $decode2);
        $recode3 = iconv("Windows-1252", "UTF-8", $decode3);

        $this->assertEquals($recode1, $recode2);
        $this->assertEquals($recode1, $recode3);

        $this->assertEquals($decode1, $decode2);
        $this->assertEquals($decode1, $decode3);
    }

    public function testDoubleEncode()
    {
        $data_win1252 = file_get_contents($this->data . '/test-encoding-windows-1252.txt');
        $correct_utf8 = file_get_contents($this->data . '/test-encoding-utf8.txt');

        $enc1 = iconv("Windows-1252", "UTF-8", $data_win1252);
        $enc2 = iconv("Windows-1252", "UTF-8", $enc1);

        $this->assertEquals($correct_utf8, $enc1);
        $this->assertNotEquals($correct_utf8, $enc2);

        $enc3 = Encoding::fixUTF8($enc1);
        $enc4 = Encoding::fixUTF8($enc2);

        $this->assertEquals($correct_utf8, $enc3);
        $this->assertEquals($correct_utf8, $enc4);
    }

    public function testEncodeWin1252Array()
    {
        $sample1 = file_get_contents($this->data . '/test-encoding-utf8.txt');
        $sample2 = file_get_contents($this->data . '/test1.txt');

        $encoded = Encoding::toWin1252([$sample1, $sample2], Encoding::ICONV_TRANSLIT);

        $this->assertNotEquals($sample1, $encoded[0]);
        $this->assertNotEquals($sample2, $encoded[1]);

        $correct1 = iconv("UTF-8", "Windows-1252", $sample1);
        $correct2 = iconv("UTF-8", "Windows-1252", $sample2);

        $this->assertEquals($correct1, $encoded[0]);
        $this->assertEquals($correct2, $encoded[1]);

        $num = Encoding::toWin1252(3);
        $this->assertEquals(3, $num);
    }

    public function testUTF8NonString()
    {
        $this->assertEquals(5, Encoding::toUTF8(5));
    }

    public function testMultiByteUTF8()
    {
        $str = "𠜎 𠜱 𠝹";
        $encoded = Encoding::toUTF8($str);

        $this->assertEquals($str, $encoded);

        $str = "‎ 	ﬡ‎ 	ﬢ‎ 	ﬣ‎ 	ﬤ‎ 	ﬥ‎ 	ﬦ";
        $encoded = Encoding::toUTF8($str);
        $this->assertEquals($str, $encoded);
    }
}
