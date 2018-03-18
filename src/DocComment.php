<?php
/*
This is part of Wedeto, the WEb DEvelopment TOolkit.
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

namespace Wedeto\Util;

/**
 * Parse DocComments from PHP source files, and extract annotations
 */
class DocComment
{
    protected $comment;

    protected $preamble = [];
    protected $annotations = [];

    /**
     * Create the DocComment parser for a certain comment
     */
    public function __construct(string $comment)
    {
        $this->comment = $comment;
        $this->parse();
    }

    /**
     * Helper method that parses the doc comment
     */
    protected function parse()
    {
        $lines = explode("\n", $this->comment);

        $current_annotation = null;
        $value = null;
        foreach ($lines as $line)
        {
            $line = trim(ltrim($line, "/* \t"));

            if (preg_match("/^@(\w+)( (.*))?$/", $line, $matches) === 1)
            {
                if (!empty($value))
                    $this->annotations[$current_annotation][] = $value;

                $current_annotation = $matches[1];
                $value = $matches[3] ?? '';
            }
            elseif ($current_annotation !== null)
            {
                $value .= "\n" . $line;
            }
            else
            {
                $this->preamble[] = $line;
            }
        }

        if (!empty($value))
            $this->annotations[$current_annotation][] = rtrim($value);
    }

    /**
     * Get an annotated value for the DocComment
     *
     * @param string $name The name of the annotation
     * @param bool $single Return either the first or all values
     * @return string|array The string when single = true, otherwise all annotations. When single is true, and
     *                      no annotation was found, null is returned.
     */
    public function getAnnotation(string $name, bool $single = true)
    {
        $val = $this->annotations[$name] ?? [];
        return $single ? (count($val) ? reset($val) : null) : $val;
    }

    /**
     * Get all values for an annotation.
     * @param string $name The name of the annotation
     * @return array All values for the annotation
     */
    public function getAnnotations(string $name)
    {
        return $this->getAnnotation($name, false);
    }

    /**
     * Get the tokenized annotation - split by whitespace
     *
     * @param string $name The annotation to get the first word from
     * @return array The tokens in the annotation
     */
    public function getAnnotationTokens(string $name)
    {
        $val = $this->getAnnotation($name, true);
        $val = trim(preg_replace("/\s{1,}/", " ", $val));

        return !empty($val) ? explode(" ", $val) : [];
    }

    /**
     * @return array all annotations for this comment
     */
    public function getAll()
    {
        return $this->annotations;
    }

    /**
     * @return string the text preceeding any annotation
     */
    public function getPreamble()
    {
        return implode("\n", $this->preamble);
    }
}
