<?php
/*
This is part of WASP, the Web Application Software Platform.
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

namespace WASP\Util;

class File
{
    private $dir;
    private $path;
    private $filename;
    private $basename;
    private $ext;
    private $mime;

    private static $file_group = null;
    private static $file_mode = null;

    public function __construct(string $filename, $mime = null)
    {
        $this->path = $filename;
        $this->dir = dirname($filename);
        if ($this->dir == ".")
            $this->dir = "";

        $this->filename = $filename = basename($filename);
        if (!empty($mime))
            $this->mime = $mime;
        $extpos = strrpos($filename, ".");

        if ($extpos !== false)
        {
            $this->ext = strtolower(substr($filename, $extpos + 1));
            $this->basename = substr($filename, 0, $extpos);
        }
        else
        {
            $this->basename = $name;
            $this->ext = null;
        }
    }

    public static function setFileGroup(string $group)
    {
        self::$file_group = $group;
    }

    public static function setFileMode(int $mode)
    {
        self::$file_mode = $mode;
    }

    public function touch()
    {
        touch($this->path);
        $this->setPermissions();
    }

    public function setPermissions()
    {
        if (isset(self::$file_group))
            @chgrp($this->path, self::$file_group);
        if (isset(self::$file_mode))
            @chmod($this->path, self::$file_mode);
    }

    public function getExt()
    {
        return $this->ext;
    }

    public function setExt($ext)
    {
        if ($this->dir)
            return $this->dir . "/" . $this->basename . "." . $ext;
        return $this->basename . "." . $ext;
    }

    public function getMime()
    {
        if (!$this->mime)
        {
            $mime = null;
            if ($this->ext === "css")
                $mime = "text/css";
            elseif ($this->ext == "json")
                $mime = "application/json";
            elseif ($this->ext == "js")
                $mime = "application/javascript";

            $mime = mime_content_type($this->path . "/" . $this->filename);
            $this->mime = $mime;
        }
        return $this->mime;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function addSuffix($suffix)
    {
        if ($this->dir)
            return $this->dir . "/" . $this->basename . $suffix . "." . $this->ext;
        return $this->basename . $suffix . "." . $this->ext;
    }

    public function getFilename()
    {
        return $this->filename; 
    }

    public function getDir()
    {
        return $this->dir;
    }
    
    public function getBaseName()
    {
        return $this->basename;
    }
}
