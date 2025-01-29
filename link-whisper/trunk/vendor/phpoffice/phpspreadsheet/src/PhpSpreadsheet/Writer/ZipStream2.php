<?php

namespace LWVendor\PhpOffice\PhpSpreadsheet\Writer;

use LWVendor\ZipStream\Option\Archive;
use LWVendor\ZipStream\ZipStream;
class ZipStream2
{
    /**
     * @param resource $fileHandle
     */
    public static function newZipStream($fileHandle) : ZipStream
    {
        $options = new Archive();
        $options->setEnableZip64(\false);
        $options->setOutputStream($fileHandle);
        return new ZipStream(null, $options);
    }
}
