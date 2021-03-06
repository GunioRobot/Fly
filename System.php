<?php
// This Package is based upon PEAR::System(ver 1.9)
//  Please visit http://pear.php.net/
//  
// +----------------------------------------------------------------------+
// | PHP Version 5                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2003 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.02 of the PHP license,      |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Original Author:  Tomas V.V. Cox <cox@idecnet.com>                   |
// | Original Author:  Stig Bakken <ssb@php.net>                          |
// +----------------------------------------------------------------------+
/**
* System offers cross plattform compatible system functions
*
* Static functions for different operations. Should work under
* Unix and Windows. The names and usage has been taken from its respectively
* GNU commands. The functions will return (bool) false on error and will
* trigger the error with the PHP trigger_error() function (you can silence
* the error by prefixing a '@' sign after the function call, but this
* is not recommended practice.  Instead use an error handler with
* {@link set_error_handler()}).
*
* Documentation on this class you can find in:
* http://pear.php.net/manual/
*
* Example usage:
* if (!@System::rm('-r file1 dir1')) {
*    print "could not delete file1 or dir1";
* }
*
* In case you need to to pass file names with spaces,
* pass the params as an array:
*
* System::rm(array('-r', $file1, $dir1));
*
* @category   pear
* @package    System
* @copyright  1997-2009 The PHP Group
* @license    http://opensource.org/licenses/bsd-license.php New BSD License
* @link       http://pear.php.net/package/PEAR
* @since      Class available since Release 0.1
* @static
*/
class Fly_System
{
    /**
     * Output errors with PHP trigger_error(). You can silence the errors
     * with prefixing a "@" sign to the function call: @System::mkdir(..);
     *
     * @param mixed $error a PEAR error or a string with the error message
     * @return bool false
     * @static
     * @access private
     */
    function raiseError($error)
    {
        if (self::isError($error)) {
            $error = $error->getMessage();
        }
        trigger_error($error, E_USER_WARNING);
        return false;
    }
    
    /**
     * Tell whether a value is a PEAR error.
     *
     * @param   mixed $data   the value to test
     * @param   int   $code   if $data is an error object, return true
     *                        only if $code is a string and
     *                        $obj->getMessage() == $code or
     *                        $code is an integer and $obj->getCode() == $code
     * @access  public
     * @return  bool    true if parameter is an error
     */
    public static function isError($data, $code = null)
    {
        if (!($data instanceof PEAR_Error)) {
            return false;
        }

        if (is_null($code)) {
            return true;
        } elseif (is_string($code)) {
            return $data->getMessage() == $code;
        }

        return $data->getCode() == $code;
    }
    
    /**
     * Creates a nested array representing the structure of a directory
     *
     * System::_dirToStruct('dir1', 0) =>
     *   Array
     *    (
     *    [dirs] => Array
     *        (
     *            [0] => dir1
     *        )
     *
     *    [files] => Array
     *        (
     *            [0] => dir1/file2
     *            [1] => dir1/file3
     *        )
     *    )
     * @param    string  $sPath      Name of the directory
     * @param    integer $maxinst    max. deep of the lookup
     * @param    integer $aktinst    starting deep of the lookup
     * @param    bool    $silent     if true, do not emit errors.
     * @return   array   the structure of the dir
     * @static
     * @access   private
     */
    function _dirToStruct($sPath, $maxinst, $aktinst = 0, $silent = false)
    {
        $struct = array('dirs' => array(), 'files' => array());
        if (($dir = @opendir($sPath)) === false) {
            if (!$silent) {
                System::raiseError("Could not open dir $sPath");
            }
            return $struct; // XXX could not open error
        }

        $struct['dirs'][] = $sPath = realpath($sPath); // XXX don't add if '.' or '..' ?
        $list = array();
        while (false !== ($file = readdir($dir))) {
            if ($file != '.' && $file != '..') {
                $list[] = $file;
            }
        }

        closedir($dir);
        natsort($list);
        if ($aktinst < $maxinst || $maxinst == 0) {
            foreach ($list as $val) {
                $path = $sPath . DIRECTORY_SEPARATOR . $val;
                if (is_dir($path) && !is_link($path)) {
                    $tmp    = System::_dirToStruct($path, $maxinst, $aktinst+1, $silent);
                    $struct = array_merge_recursive($struct, $tmp);
                } else {
                    $struct['files'][] = $path;
                }
            }
        }

        return $struct;
    }

    /**
     * Creates a nested array representing the structure of a directory and files
     *
     * @param    array $files Array listing files and dirs
     * @return   array
     * @static
     * @see System::_dirToStruct()
     */
    function _multipleToStruct($files)
    {
        $struct = array('dirs' => array(), 'files' => array());
        settype($files, 'array');
        foreach ($files as $file) {
            if (is_dir($file) && !is_link($file)) {
                $tmp    = System::_dirToStruct($file, 0);
                $struct = array_merge_recursive($tmp, $struct);
            } else {
                if (!in_array($file, $struct['files'])) {
                    $struct['files'][] = $file;
                }
            }
        }
        return $struct;
    }

    /**
     * The rm command for removing files.
     * Supports multiple files and dirs and also recursive deletes
     *
     * @param    string  $args   the arguments for rm
     * @return   mixed   PEAR_Error or true for success
     * @static
     * @access   public
     */
    function rm($args)
    {
        $opts = System::_parseArgs($args, 'rf'); // "f" does nothing but I like it :-)
        if (self::isError($opts)) {
            return System::raiseError($opts);
        }
        foreach ($opts[0] as $opt) {
            if ($opt[0] == 'r') {
                $do_recursive = true;
            }
        }
        $ret = true;
        if (isset($do_recursive)) {
            $struct = System::_multipleToStruct($opts[1]);
            foreach ($struct['files'] as $file) {
                if (!@unlink($file)) {
                    $ret = false;
                }
            }

            rsort($struct['dirs']);
            foreach ($struct['dirs'] as $dir) {
                if (!@rmdir($dir)) {
                    $ret = false;
                }
            }
        } else {
            foreach ($opts[1] as $file) {
                $delete = (is_dir($file)) ? 'rmdir' : 'unlink';
                if (!@$delete($file)) {
                    $ret = false;
                }
            }
        }
        return $ret;
    }

    /**
     * Make directories.
     *
     * The -p option will create parent directories
     * @param    string  $args    the name of the director(y|ies) to create
     * @return   bool    True for success
     * @static
     * @access   public
     */
    static function mkDir($args)
    {
        $mode = 0777; // default mode
        $dirs = array();
        for ($i = 0, $count = count($args); $i < $count; $i++){
            switch ($args[$i]){
            case '-p':
                $create_parents = true;
                break;
            case '-m':
                $mode = $args[++$i];
                break;
            default:
                $dirs[] = $args[$i];
                break;
            }
        }

        $ret = true;
        if (isset($create_parents)) {
            foreach ($dirs as $dir) {
                $dirstack = array();
                while ((!file_exists($dir) || !is_dir($dir)) &&
                        $dir != DIRECTORY_SEPARATOR) {
                    array_unshift($dirstack, $dir);
                    $dir = dirname($dir);
                }

                while ($newdir = array_shift($dirstack)) {
                    if (!is_writeable(dirname($newdir))) {
                        $ret = false;
                        break;
                    }

                    if (!mkdir($newdir, $mode)) {
                        $ret = false;
                    }
                }
            }
        } else {
            foreach($dirs as $dir) {
                if ((@file_exists($dir) || !is_dir($dir)) && !mkdir($dir, $mode)) {
                    $ret = false;
                }
            }
        }

        return $ret;
    }

    /**
     * Concatenate files
     *
     * Usage:
     * 1) $var = System::cat('sample.txt test.txt');
     * 2) System::cat('sample.txt test.txt > final.txt');
     * 3) System::cat('sample.txt test.txt >> final.txt');
     *
     * Note: as the class use fopen, urls should work also (test that)
     *
     * @param    string  $args   the arguments
     * @return   boolean true on success
     * @static
     * @access   public
     */
    function &cat($args)
    {
        $ret = null;
        $files = array();
        if (!is_array($args)) {
            $args = preg_split('/\s+/', $args, -1, PREG_SPLIT_NO_EMPTY);
        }

        $count_args = count($args);
        for ($i = 0; $i < $count_args; $i++) {
            if ($args[$i] == '>') {
                $mode = 'wb';
                $outputfile = $args[$i+1];
                break;
            } elseif ($args[$i] == '>>') {
                $mode = 'ab+';
                $outputfile = $args[$i+1];
                break;
            } else {
                $files[] = $args[$i];
            }
        }
        $outputfd = false;
        if (isset($mode)) {
            if (!$outputfd = fopen($outputfile, $mode)) {
                $err = System::raiseError("Could not open $outputfile");
                return $err;
            }
            $ret = true;
        }
        foreach ($files as $file) {
            if (!$fd = fopen($file, 'r')) {
                System::raiseError("Could not open $file");
                continue;
            }
            while ($cont = fread($fd, 2048)) {
                if (is_resource($outputfd)) {
                    fwrite($outputfd, $cont);
                } else {
                    $ret .= $cont;
                }
            }
            fclose($fd);
        }
        if (is_resource($outputfd)) {
            fclose($outputfd);
        }
        return $ret;
    }

    /**
     * The "which" command (show the full path of a command)
     *
     * @param string $program The command to search for
     * @param mixed  $fallback Value to return if $program is not found
     *
     * @return mixed A string with the full path or false if not found
     * @static
     */
    function which($program, $fallback = false)
    {
        // enforce API
        if (!is_string($program) || '' == $program) {
            return $fallback;
        }

        // full path given
        if (basename($program) != $program) {
            $path_elements[] = dirname($program);
            $program = basename($program);
        } else {
            // Honor safe mode
            if (!ini_get('safe_mode') || !$path = ini_get('safe_mode_exec_dir')) {
                $path = getenv('PATH');
                if (!$path) {
                    $path = getenv('Path'); // some OSes are just stupid enough to do this
                }
            }
            $path_elements = explode(PATH_SEPARATOR, $path);
        }

        if (OS_WINDOWS) {
            $exe_suffixes = getenv('PATHEXT')
                                ? explode(PATH_SEPARATOR, getenv('PATHEXT'))
                                : array('.exe','.bat','.cmd','.com');
            // allow passing a command.exe param
            if (strpos($program, '.') !== false) {
                array_unshift($exe_suffixes, '');
            }
            // is_executable() is not available on windows for PHP4
            $pear_is_executable = (function_exists('is_executable')) ? 'is_executable' : 'is_file';
        } else {
            $exe_suffixes = array('');
            $pear_is_executable = 'is_executable';
        }

        foreach ($exe_suffixes as $suff) {
            foreach ($path_elements as $dir) {
                $file = $dir . DIRECTORY_SEPARATOR . $program . $suff;
                if (@$pear_is_executable($file)) {
                    return $file;
                }
            }
        }
        return $fallback;
    }

    /**
     * The "find" command
     *
     * Usage:
     *
     * System::find($dir);
     * System::find("$dir -type d");
     * System::find("$dir -type f");
     * System::find("$dir -name *.php");
     * System::find("$dir -name *.php -name *.htm*");
     * System::find("$dir -maxdepth 1");
     *
     * Params implmented:
     * $dir            -> Start the search at this directory
     * -type d         -> return only directories
     * -type f         -> return only files
     * -maxdepth <n>   -> max depth of recursion
     * -name <pattern> -> search pattern (bash style). Multiple -name param allowed
     *
     * @param  mixed Either array or string with the command line
     * @return array Array of found files
     * @static
     *
     */
    function find($args)
    {
        if (!is_array($args)) {
            $args = preg_split('/\s+/', $args, -1, PREG_SPLIT_NO_EMPTY);
        }
        $dir = realpath(array_shift($args));
        if (!$dir) {
            return array();
        }
        $patterns = array();
        $depth = 0;
        $do_files = $do_dirs = true;
        $args_count = count($args);
        for ($i = 0; $i < $args_count; $i++) {
            switch ($args[$i]) {
                case '-type':
                    if (in_array($args[$i+1], array('d', 'f'))) {
                        if ($args[$i+1] == 'd') {
                            $do_files = false;
                        } else {
                            $do_dirs = false;
                        }
                    }
                    $i++;
                    break;
                case '-name':
                    $name = preg_quote($args[$i+1], '#');
                    // our magic characters ? and * have just been escaped,
                    // so now we change the escaped versions to PCRE operators
                    $name = strtr($name, array('\?' => '.', '\*' => '.*'));
                    $patterns[] = '('.$name.')';
                    $i++;
                    break;
                case '-maxdepth':
                    $depth = $args[$i+1];
                    break;
            }
        }
        $path = System::_dirToStruct($dir, $depth, 0, true);
        if ($do_files && $do_dirs) {
            $files = array_merge($path['files'], $path['dirs']);
        } elseif ($do_dirs) {
            $files = $path['dirs'];
        } else {
            $files = $path['files'];
        }
        if (count($patterns)) {
            $dsq = preg_quote(DIRECTORY_SEPARATOR, '#');
            $pattern = '#(^|'.$dsq.')'.implode('|', $patterns).'($|'.$dsq.')#';
            $ret = array();
            $files_count = count($files);
            for ($i = 0; $i < $files_count; $i++) {
                // only search in the part of the file below the current directory
                $filepart = basename($files[$i]);
                if (preg_match($pattern, $filepart)) {
                    $ret[] = $files[$i];
                }
            }
            return $ret;
        }
        return $files;
    }
}