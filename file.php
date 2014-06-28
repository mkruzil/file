<?php

/*

file.php

A library for reading a file into an array and writing an array to a file.

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

define("SEPARATOR", ",");

function open($filename) {
    $str = readf($filename);
    $lines = strToLines($str);
    $rows = linesToRows($lines);
    $rows = pushKeys($rows);
    $rows = code($rows, "decode");
    return $rows;
}

function save($rows, $filename) {
    $bytes = 0;
    $rows = tableize($rows);
    $rows = code($rows, "encode");
    $rows = popKeys($rows);
    $lines = rowsToLines($rows);
    $str = linesToStr($lines);
    $bytes = writef($str, $filename);
    return $bytes;
}

function readf($filename, $mode = "r", $position = 0) {
    $contents = "";
    $is_valid_mode = ($mode === "r") || ($mode === "r+") || ($mode === "w") || ($mode === "w+") || ($mode === "a") || ($mode === "a+") || ($mode === "x") ||($mode === "x+") || ($mode === "c") || ($mode === "c+");
    //Valid modes other than r or r+ are able to create a new file if fopen fails due to the file not existing
    $creates_if_not_exists = !(!is_file($filename) && ($mode === "r" || $mode === "r+"));
    if ($is_valid_mode && $creates_if_not_exists) {
        $pointer = @fopen($filename, $mode . "b"); 
        if (is_resource($pointer)) {
            flock($pointer, $mode === "r" ? LOCK_SH : LOCK_EX); //Apply lock
            clearstatcache(); //To get most up-to-date file size
            $bytes = filesize($filename);
            if ($bytes) {
                if ($position === 0 && (ftell($pointer) > 0)) {
                    rewind($pointer);
                } else {
                    fseek($pointer, $position);
                }
                $contents = fread($pointer, $bytes);
            }
        }
    }
    return $contents;
}

function movef($original_filename, $new_filename) {
    if (is_file($original_filename)) {
        rename($original_filename, $new_filename);
    }
}

function copyf($original_filename, $new_filename) {
    if (is_file($original_filename)) {
        $str = readf($original_filename);
        writef($str, $new_filename);
    }
}

function writef($str, $filename, $mode = "w") {
    $bytes = 0;
    //chmod o+w filename.ext
    if (!is_dir($filename)) {
        $pointer = @fopen($filename, $mode . "b");
        if (is_resource($pointer)) {
            $bytes = fwrite($pointer, $str);
        }
    }
    return $bytes;
}

function strToLines($str, $eol = "\n") {
    $lines = array();
    if ($str && is_string($str)) {
        /* Newlines (http://en.wikipedia.org/wiki/End_of_line) */
        /* --------------------------------------------------- */
        $lf = "\n";     //Unix, Mac (OS10+)
        $cr = "\r";     //Mac (up to OS9)
        $crlf = "\r\n"; //Windows
        $lfcr = "\n\r"; //Acorn, RISC
        $str = str_replace(array($lfcr, $crlf, $cr, $lf), $eol, $str);
        //If str is null, explode will still create a line
        $lines = explode($eol, $str);
    }
    return $lines;
}

function linesToStr($lines) {
    $str = "";
    $lines_length = count($lines);
    $lines_counter = 0;
    foreach ($lines as $line) {
        $str .= $line . (++$lines_counter < $lines_length ? "\n" : "");        
    }
    return $str;
}

function linesToRows($lines) {
    $rows = array();
    if (is_array($lines)) {
        foreach ($lines as $line_index => $line) {
            $row = explode(SEPARATOR, $line);
            array_push($rows, $row);
        }
    }
    return $rows;
}

function rowsToLines($rows) {
    $lines = array();
    if (is_array($rows)) {
        foreach ($rows as $row) {
            $line = "";
            $cells_length = count($row);
            $cell_counter = 0;
            foreach ($row as $cell_index => $cell_value) {
                $line .= $cell_value . (++$cell_counter === $cells_length ? "" : SEPARATOR);
            }
            array_push($lines, $line);          
        }
    }
    return $lines;
}

//Code: http://en.wikipedia.org/wiki/Encoding
function code($rows, $process) {
    $search = "";
    $replace = "";
    $decoded = array(SEPARATOR, "\t", "\n", "\r");
    $encoded = array("(\\" . ord(SEPARATOR) . ")", "(\\9)", "(\\10)", "(\\13)");
    if (is_array($rows)) {
        //Encode
        if ($process === "encode") {
            $search = $decoded;
            $replace = $encoded;
        //Decode
        } else if ($process === "decode") {
            $search = $encoded;
            $replace = $decoded;
        }
        foreach ($rows as $index => $row) {
            if (is_array($row)) {
                foreach ($row as $name => $value) {
                    $rows[$index][$name] = str_replace($search, $replace, $value);
                }
            }
        }
    }
    return $rows;
}

function pushKeys($rows) {
    if (is_array($rows)) {
        $keyed = array();
        $fields = array_shift($rows);
        if ($rows) {
            foreach ($rows as $row_index => $row) {
                foreach ($row as $cell_index => $cell_value) {
                    $cell_key = isset($fields[$cell_index]) ? $fields[$cell_index] : "undefined";
                    $keyed[$row_index][formatKey($cell_key)] = $cell_value;
                }
            }
            $rows = $keyed;
        }
    }
    return $rows;
}

function popKeys($rows) {
    if (is_array($rows)) {
        $new_rows = array();
        $new_rows[0] = array();
        if (isset($rows[0])) {
            $row = $rows[0];
            foreach ($row as $key => $value) {
                array_push($new_rows[0], $key);
            }
            $rows_length = count($rows);
            foreach ($rows as $row_index => $row) {
                $new_row = array();
                $i = 0;
                foreach ($row as $cell_key => $cell_value) {
                    $new_row[$i++] = $cell_value;
                }
                array_push($new_rows, $new_row);
            }
            $rows = $new_rows;
        }
    }
    return $rows;
}

//Makes sure the array key is in a valid format
function formatKey($value) {
    $key = strtolower($value);
    $key = str_replace(" ", "_" , $key);
    return $key;
}

//Make sure the rows data is in a tabular format
function tableize($rows) {
    if (is_array($rows)) {
        if (!isset($rows[0])) {
            $tmp = array();
            $tmp[0] = $rows; 
            $rows = $tmp;
        }
    } else {
        $rows = array($rows);
    }
    foreach ($rows as $index => $row) {
        if (!is_array($row)) {
            $tmp = array();
            $tmp["field"] = $row;
            $rows[$index] = $tmp;
        }
    }
    return $rows;   
}

?>
