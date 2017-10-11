<?php

/**
 * Nette Framework
 *
 * Copyright (c) 2004, 2007 David Grudl aka -dgx- (http://www.dgx.cz)
 *
 * This source file is subject to the "Nette license" that is bundled
 * with this package in the file license.txt.
 *
 * For more information please see http://nettephp.com/
 *
 * @copyright  Copyright (c) 2004, 2007 David Grudl
 * @license    http://nettephp.com/license  Nette license
 * @link       http://nettephp.com/
 * @category   Nette
 * @package    Nette-Debug
 */



/**
 * Debug static class
 *
 * @author     David Grudl
 * @copyright  Copyright (c) 2004, 2007 David Grudl
 * @package    Nette-Debug
 * @version    $Revision: 86 $ $Date: 2007-11-13 02:51:44 +0100 (út, 13 XI 2007) $
 */
final class NDebug
{
    /** @var bool  Is output HTML page or textual terminal? */
    public static $html;



    /**
     * Static class - cannot be instantiated
     */
    final public function __construct()
    {
        throw new LogicException("Cannot instantiate static class " . get_class($this));
    }



    /**
     * Static class constructor
     */
    public static function constructStatic()
    {
        self::$html = PHP_SAPI !== 'cli';
    }



    /**
     * Dumps information about a variable in readable format
     *
     * @param  mixed  variable to dump.
     * @param  bool   return output instead of printing it?
     * @return string
     */
    public static function dump($var, $return = FALSE)
    {
        ob_start();
        var_dump($var);
        $output = ob_get_clean();

        if (self::$html) {
            $output = htmlspecialchars($output, ENT_NOQUOTES);
            $output = preg_replace('#\]=&gt;\n\ +([a-z]+)#i', '] => <span>$1</span>', $output);
            $output = preg_replace('#^([a-z]+)#i', '<span>$1</span>', $output);
            $output = "<pre class=\"dump\">$output</pre>\n";
        } else {
            $output = preg_replace('#\]=>\n\ +#i', '] => ', $output) . "\n";
        }

        if (!$return) echo $output;

        return $output;
    }



    /**
     * Starts/stops stopwatch
     * @return elapsed microseconds
     */
    public static function timer()
    {
        static $time = 0;
        $now = microtime(TRUE);
        $delta = $now - $time;
        $time = $now;
        return $delta;
    }



    /**
     * Register error handler routine
     * @param  int   error_reporting level
     * @return void
     */
    public static function handleErrors($level = NULL)
    {
        if ($level !== NULL) error_reporting($level);

        set_error_handler(array(__CLASS__, 'errorHandler'));

        // buggy in PHP 5.2.1
        set_exception_handler(array(__CLASS__, 'exceptionHandler'));
    }



    /**
     * Unregister error handler routine
     * @return void
     */
    public static function unhandleErrors()
    {
        restore_error_handler();
        restore_exception_handler();
    }



    /**
     * NDebug exception handler
     *
     * @param  Exception
     * @return void
     */
    public static function exceptionHandler(Exception $e)
    {
        self::unhandleErrors();
        self::printFatalError(
            "Exception '" . get_class($e) . "' #" . $e->getCode() . " " . $e->getMessage(),
            $e->getTrace()
        );
    }



    /**
     * NDebug error handler
     *
     * @param  int    level of the error raised
     * @param  string error message
     * @param  string filename that the error was raised in
     * @param  int    line number the error was raised at
     * @param  array  an array of variables that existed in the scope the error was triggered in
     * @return void
     */
    public static function errorHandler($code, $message, $file, $line, $context)
    {
        if ($code === E_USER_ERROR) {
            self::unhandleErrors();

            $trace = debug_backtrace();
            array_shift($trace);

            self::printFatalError(
                "User error '$message' in $file on line $line",
                $trace
            );
        }

        if (($code & error_reporting()) === $code) {
            $types = array(
                E_RECOVERABLE_ERROR => 'Recoverable error',  // PHP 5.2
                E_WARNING => 'Warning',
                E_NOTICE => 'Notice',
                E_USER_WARNING => 'User warning',
                E_USER_NOTICE => 'User notice',
                E_STRICT => 'Strict',
            );
            $type = isset($types[$code]) ? $types[$code] : 'Unknown error';
            if (self::$html) {
                echo "<b>$type:</b> $message in <b>$file</b> on line <b>$line</b>\n<br />";
            } else {
                echo "$type: $message in $file on line $line\n";
            }
        }
    }



    /**
     * Prints error message with stack trace
     * @param  string
     * @param  array
     * @return void
     */
    private static function printFatalError($message, $trace)
    {
        while (ob_get_level()) ob_end_clean();

        if (!headers_sent()) {
            header('HTTP/1.1 500 Internal Server Error');
        }

        if (self::$html) {
            echo "<h1>Server Error</h1><hr />\n";
            echo "<p>", htmlSpecialChars($message), "</p>\n";
            echo "<h3>Stack Trace</h3>\n";
            self::printTrace($trace);
            echo "<p>PHP version ", PHP_VERSION, "</p>\n";

            // fix IE
            $s = " \t\r\n";
            for ($i = 2e3; $i; $i--) echo $s{rand(0, 3)};

        } else {
            echo "SERVER ERROR\n";
            echo "$message\n";
            echo "STACK TRACE:\n";
            self::printTrace($trace);
            echo "PHP version ", PHP_VERSION, "\n";
        }
        exit;
    }




    /**
     * Prints stack trace in readable form
     * @param  array trace
     * @return void
     */
    private static function printTrace($trace)
    {
        if (self::$html) echo '<pre class="dump">';

        $index = 0;
        foreach ($trace as $key => $row) {
            $index++;
            printf('#%-2s ', $index);

            // file
            $source = FALSE;
            if (isset($row['file'])) {
                if (self::$html) {
                    printf('%-46s',
                        htmlSpecialChars(basename(dirname($row['file'])))
                       . '/<b>' . htmlSpecialChars(basename($row['file']))
                       . '</b>(' . $row['line'] . ')');
                } else {
                    printf('%-46s', $row['file'] . '(' . $row['line'] . ')');
                }

                // try to receive source code snippet
                if (is_readable($row['file'])) {
                    $file = file($row['file']);
                    if (isset($file[ $row['line']-1 ])) {
                       $source = trim($file[ $row['line']-1 ]);
                       if ($source > 100) $source = substr(0, 100) . '...';
                    }
                    unset($file);
                }
            } else {
                printf('%-46s', self::$html ? '&lt;PHP inner-code&gt;' : '<PHP inner-code>');
            }

            // class, method, function
            if (isset($trace[$key + 1])) {
                $nextRow = $trace[$key + 1];

                echo ' in ';

                if (isset($nextRow['class'])) {
                    echo $nextRow['class'] . $nextRow['type'];
                }

                echo $nextRow['function'];

                // and arguments
                if (isset($nextRow['args']) && count($nextRow['args']) > 0) {
                    foreach ($nextRow['args'] as &$arg) {
                        if (is_null($arg)) $arg = 'NULL';
                        elseif (is_bool($arg)) $arg = $arg ? 'TRUE' : 'FALSE';
                        elseif (is_array($arg)) $arg = 'array['.count($arg).']';
                        elseif (is_object($arg)) $arg = 'object('.get_class($arg).')';
                        else {
                            $arg = preg_replace('#\s#', ' ', (string) $arg);
                            if (strlen($arg) > 40) {
                                $arg = substr($arg, 0, 37) . '...';
                            }
                            $arg = self::$html ? "'" . htmlSpecialChars($arg) . "'" : "'$arg'";
                        }
                    }

                    echo '(' . implode(', ', $nextRow['args']) .  ')';

                } else {
                    echo '()';
                }
            }

            // source code snippet
            if ($source) {
                echo "\n    ";
                echo self::$html ? '<span style="color:gray">' . htmlSpecialChars($source) . '</span>' : $source;
            }

            echo "\n\n";
        }

        if (self::$html) echo "</pre>\n";
    }

}


NDebug::constructStatic();
