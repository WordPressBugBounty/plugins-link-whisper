<?php

namespace LWVendor\Complex;

/**
 *
 * Autoloader for Complex classes
 *
 * @package Complex
 * @copyright  Copyright (c) 2014 Mark Baker (https://github.com/MarkBaker/PHPComplex)
 * @license    https://opensource.org/licenses/MIT          MIT
 */
class Autoloader
{
    /**
     * Register the Autoloader with SPL
     *
     */
    public static function Register()
    {
        if (\function_exists('lw__autoload')) {
            //    Register any existing autoloader function with SPL, so we don't get any clashes
            \spl_autoload_register('lw__autoload');
        }
        //    Register ourselves with SPL
        return \spl_autoload_register(['LWVendor\\Complex\\Autoloader', 'Load']);
    }
    /**
     * Autoload a class identified by name
     *
     * @param    string    $pClassName    Name of the object to load
     */
    public static function Load($pClassName)
    {
        if (\class_exists($pClassName, \false) || \strpos($pClassName, 'Complex\\') !== 0) {
            // Either already loaded, or not a Complex class request
            return \false;
        }
        $pClassFilePath = __DIR__ . \DIRECTORY_SEPARATOR . 'src' . \DIRECTORY_SEPARATOR . \str_replace(['Complex\\', '\\'], ['', '/'], $pClassName) . '.php';
        if (\file_exists($pClassFilePath) === \false || \is_readable($pClassFilePath) === \false) {
            // Can't load
            return \false;
        }
        require $pClassFilePath;
    }
}
