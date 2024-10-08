<?php

namespace LWVendor\PhpOffice\PhpSpreadsheet\Writer\Ods;

use LWVendor\PhpOffice\PhpSpreadsheet\Spreadsheet;
class Thumbnails extends WriterPart
{
    /**
     * Write Thumbnails/thumbnail.png to PNG format.
     *
     * @param Spreadsheet $spreadsheet
     *
     * @return string XML Output
     */
    public function writeThumbnail(?Spreadsheet $spreadsheet = null)
    {
        return '';
    }
}
