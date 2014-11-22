<?php

/*

File.php

A library for reading a file and writing to a file.

Copyright (c) 2014 Mike Kruzil

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

*/

class File {

    private $path;
    private $mode;
    private $handle;
    private $contents;

    function __construct($path) {
        $this->path = $path;
        $this->contents = "";
    }

    public function open($mode = "r+") {
        if ($mode !== $this->mode) {
            $path = $this->path;
            //Check to make sure this is a valid mode, and if the file doesn't exist, it can be created without error
            if ($this->isValidMode($mode) && !(!is_file($this->path) && !$this->isCreatableMode($mode))) {
                $handle = @fopen($path, $mode . "b"); 
                if (is_resource($handle)) {
                    flock($handle, $mode === "r" ? LOCK_SH : LOCK_EX); //Apply lock
                    $this->mode = $mode;
                    if ($this->handle) {
                        $this->close();
                    }
                    $this->handle = $handle;
                }
            }
        }
    }

    public function read($position = 0) {
        $contents = "";
        $handle = $this->handle;
        if (is_resource($handle) && $this->isReadableMode($this->mode)) {
            clearstatcache(); //To get most up-to-date file size
            $bytes = filesize($this->path);
            if ($bytes) {
                if ($position === 0 && (ftell($handle) > 0)) {
                    rewind($handle);
                } else {
                    fseek($handle, $position);
                }
                $contents = fread($handle, $bytes);
            }
        }
        $this->contents = $contents;
    }

    public function write($replace = true) {
        $bytes = 0;
        $path = $this->path;
        if (!is_dir($path)) {
            $handle = $this->handle;
            if (is_resource($handle) && $this->isWritableMode($this->mode)) {
                if ($replace) {
                    ftruncate($handle, 0);
                    //Move the pointer back to the beginning of the file
                    rewind($handle);
                }
                //chmod o+w filename.ext
                $bytes = fwrite($handle, $this->contents);
            }
        }
        return $bytes;
    }

    private function isValidMode($mode) {
        return ($mode === "r") || ($mode === "r+") || ($mode === "w") || ($mode === "w+") || ($mode === "a") || ($mode === "a+") || ($mode === "x") ||($mode === "x+") || ($mode === "c") || ($mode === "c+");
    }

    private function isReadableMode($mode) {
        return $this->isValidMode($mode) && ($mode !== "w") && ($mode !== "a") && ($mode !== "x") && ($mode !== "c");
    }

    private function isWritableMode($mode) {
        return $this->isValidMode($mode) && ($mode !== "r");
    }

    private function isCreatableMode($mode) {
        return $this->isValidMode($mode) && ($mode !== "r") && ($mode !== "r+");
    }

    public function getPath() {
        return $this->path;
    }

    public function getMode() {
        return $this->mode;
    }

    public function getContents() {
        return $this->contents;
    }

    public function setMode($mode) {
        $this->mode = $this->isValidMode($mode) ? $mode : "r+";
    }

    public function setContents($contents) {
        $this->contents = $contents;
    }

    public function delete() {
        $this->close();
        unlink($this->path);
    }

    public function close() {
        $this->mode = "";
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
    }
}
?>
