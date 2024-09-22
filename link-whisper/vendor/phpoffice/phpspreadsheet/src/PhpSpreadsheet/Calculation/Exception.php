<?php

namespace LWVendor\PhpOffice\PhpSpreadsheet\Calculation;

use LWVendor\PhpOffice\PhpSpreadsheet\Exception as PhpSpreadsheetException;
class Exception extends PhpSpreadsheetException
{
    /**
     * Error handler callback.
     *
     * @param mixed $code
     * @param mixed $string
     * @param mixed $file
     * @param mixed $line
     * @param mixed $context
     */
    public static function errorHandlerCallback($code, $string, $file, $line, $context) : void
    {
        $e = new self($string, $code);
        $e->line = $line;
        $e->file = $file;
        throw $e;
    }
}
