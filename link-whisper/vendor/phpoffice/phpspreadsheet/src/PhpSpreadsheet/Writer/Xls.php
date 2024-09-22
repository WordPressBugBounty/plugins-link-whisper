<?php

namespace LWVendor\PhpOffice\PhpSpreadsheet\Writer;

use LWVendor\PhpOffice\PhpSpreadsheet\Calculation\Calculation;
use LWVendor\PhpOffice\PhpSpreadsheet\Calculation\Functions;
use LWVendor\PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use LWVendor\PhpOffice\PhpSpreadsheet\RichText\RichText;
use LWVendor\PhpOffice\PhpSpreadsheet\RichText\Run;
use LWVendor\PhpOffice\PhpSpreadsheet\Shared\Drawing as SharedDrawing;
use LWVendor\PhpOffice\PhpSpreadsheet\Shared\Escher;
use LWVendor\PhpOffice\PhpSpreadsheet\Shared\Escher\DgContainer;
use LWVendor\PhpOffice\PhpSpreadsheet\Shared\Escher\DgContainer\SpgrContainer;
use LWVendor\PhpOffice\PhpSpreadsheet\Shared\Escher\DgContainer\SpgrContainer\SpContainer;
use LWVendor\PhpOffice\PhpSpreadsheet\Shared\Escher\DggContainer;
use LWVendor\PhpOffice\PhpSpreadsheet\Shared\Escher\DggContainer\BstoreContainer;
use LWVendor\PhpOffice\PhpSpreadsheet\Shared\Escher\DggContainer\BstoreContainer\BSE;
use LWVendor\PhpOffice\PhpSpreadsheet\Shared\Escher\DggContainer\BstoreContainer\BSE\Blip;
use LWVendor\PhpOffice\PhpSpreadsheet\Shared\OLE;
use LWVendor\PhpOffice\PhpSpreadsheet\Shared\OLE\PPS\File;
use LWVendor\PhpOffice\PhpSpreadsheet\Shared\OLE\PPS\Root;
use LWVendor\PhpOffice\PhpSpreadsheet\Spreadsheet;
use LWVendor\PhpOffice\PhpSpreadsheet\Worksheet\BaseDrawing;
use LWVendor\PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use LWVendor\PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;
use RuntimeException;
class Xls extends BaseWriter
{
    /**
     * PhpSpreadsheet object.
     *
     * @var Spreadsheet
     */
    private $spreadsheet;
    /**
     * Total number of shared strings in workbook.
     *
     * @var int
     */
    private $strTotal = 0;
    /**
     * Number of unique shared strings in workbook.
     *
     * @var int
     */
    private $strUnique = 0;
    /**
     * Array of unique shared strings in workbook.
     *
     * @var array
     */
    private $strTable = [];
    /**
     * Color cache. Mapping between RGB value and color index.
     *
     * @var array
     */
    private $colors;
    /**
     * Formula parser.
     *
     * @var \PhpOffice\PhpSpreadsheet\Writer\Xls\Parser
     */
    private $parser;
    /**
     * Identifier clusters for drawings. Used in MSODRAWINGGROUP record.
     *
     * @var array
     */
    private $IDCLs;
    /**
     * Basic OLE object summary information.
     *
     * @var array
     */
    private $summaryInformation;
    /**
     * Extended OLE object document summary information.
     *
     * @var array
     */
    private $documentSummaryInformation;
    /**
     * @var \PhpOffice\PhpSpreadsheet\Writer\Xls\Workbook
     */
    private $writerWorkbook;
    /**
     * @var \PhpOffice\PhpSpreadsheet\Writer\Xls\Worksheet[]
     */
    private $writerWorksheets;
    /**
     * Create a new Xls Writer.
     *
     * @param Spreadsheet $spreadsheet PhpSpreadsheet object
     */
    public function __construct(Spreadsheet $spreadsheet)
    {
        $this->spreadsheet = $spreadsheet;
        $this->parser = new Xls\Parser();
    }
    /**
     * Save Spreadsheet to file.
     *
     * @param resource|string $pFilename
     */
    public function save($pFilename) : void
    {
        // garbage collect
        $this->spreadsheet->garbageCollect();
        $saveDebugLog = Calculation::getInstance($this->spreadsheet)->getDebugLog()->getWriteDebugLog();
        Calculation::getInstance($this->spreadsheet)->getDebugLog()->setWriteDebugLog(\false);
        $saveDateReturnType = Functions::getReturnDateType();
        Functions::setReturnDateType(Functions::RETURNDATE_EXCEL);
        // initialize colors array
        $this->colors = [];
        // Initialise workbook writer
        $this->writerWorkbook = new Xls\Workbook($this->spreadsheet, $this->strTotal, $this->strUnique, $this->strTable, $this->colors, $this->parser);
        // Initialise worksheet writers
        $countSheets = $this->spreadsheet->getSheetCount();
        for ($i = 0; $i < $countSheets; ++$i) {
            $this->writerWorksheets[$i] = new Xls\Worksheet($this->strTotal, $this->strUnique, $this->strTable, $this->colors, $this->parser, $this->preCalculateFormulas, $this->spreadsheet->getSheet($i));
        }
        // build Escher objects. Escher objects for workbooks needs to be build before Escher object for workbook.
        $this->buildWorksheetEschers();
        $this->buildWorkbookEscher();
        // add 15 identical cell style Xfs
        // for now, we use the first cellXf instead of cellStyleXf
        $cellXfCollection = $this->spreadsheet->getCellXfCollection();
        for ($i = 0; $i < 15; ++$i) {
            $this->writerWorkbook->addXfWriter($cellXfCollection[0], \true);
        }
        // add all the cell Xfs
        foreach ($this->spreadsheet->getCellXfCollection() as $style) {
            $this->writerWorkbook->addXfWriter($style, \false);
        }
        // add fonts from rich text eleemnts
        for ($i = 0; $i < $countSheets; ++$i) {
            foreach ($this->writerWorksheets[$i]->phpSheet->getCoordinates() as $coordinate) {
                $cell = $this->writerWorksheets[$i]->phpSheet->getCell($coordinate);
                $cVal = $cell->getValue();
                if ($cVal instanceof RichText) {
                    $elements = $cVal->getRichTextElements();
                    foreach ($elements as $element) {
                        if ($element instanceof Run) {
                            $font = $element->getFont();
                            $this->writerWorksheets[$i]->fontHashIndex[$font->getHashCode()] = $this->writerWorkbook->addFont($font);
                        }
                    }
                }
            }
        }
        // initialize OLE file
        $workbookStreamName = 'Workbook';
        $OLE = new File(OLE::ascToUcs($workbookStreamName));
        // Write the worksheet streams before the global workbook stream,
        // because the byte sizes of these are needed in the global workbook stream
        $worksheetSizes = [];
        for ($i = 0; $i < $countSheets; ++$i) {
            $this->writerWorksheets[$i]->close();
            $worksheetSizes[] = $this->writerWorksheets[$i]->_datasize;
        }
        // add binary data for global workbook stream
        $OLE->append($this->writerWorkbook->writeWorkbook($worksheetSizes));
        // add binary data for sheet streams
        for ($i = 0; $i < $countSheets; ++$i) {
            $OLE->append($this->writerWorksheets[$i]->getData());
        }
        $this->documentSummaryInformation = $this->writeDocumentSummaryInformation();
        // initialize OLE Document Summary Information
        if (isset($this->documentSummaryInformation) && !empty($this->documentSummaryInformation)) {
            $OLE_DocumentSummaryInformation = new File(OLE::ascToUcs(\chr(5) . 'DocumentSummaryInformation'));
            $OLE_DocumentSummaryInformation->append($this->documentSummaryInformation);
        }
        $this->summaryInformation = $this->writeSummaryInformation();
        // initialize OLE Summary Information
        if (isset($this->summaryInformation) && !empty($this->summaryInformation)) {
            $OLE_SummaryInformation = new File(OLE::ascToUcs(\chr(5) . 'SummaryInformation'));
            $OLE_SummaryInformation->append($this->summaryInformation);
        }
        // define OLE Parts
        $arrRootData = [$OLE];
        // initialize OLE Properties file
        if (isset($OLE_SummaryInformation)) {
            $arrRootData[] = $OLE_SummaryInformation;
        }
        // initialize OLE Extended Properties file
        if (isset($OLE_DocumentSummaryInformation)) {
            $arrRootData[] = $OLE_DocumentSummaryInformation;
        }
        $root = new Root(\time(), \time(), $arrRootData);
        // save the OLE file
        $this->openFileHandle($pFilename);
        $root->save($this->fileHandle);
        $this->maybeCloseFileHandle();
        Functions::setReturnDateType($saveDateReturnType);
        Calculation::getInstance($this->spreadsheet)->getDebugLog()->setWriteDebugLog($saveDebugLog);
    }
    /**
     * Build the Worksheet Escher objects.
     */
    private function buildWorksheetEschers() : void
    {
        // 1-based index to BstoreContainer
        $blipIndex = 0;
        $lastReducedSpId = 0;
        $lastSpId = 0;
        foreach ($this->spreadsheet->getAllsheets() as $sheet) {
            // sheet index
            $sheetIndex = $sheet->getParent()->getIndex($sheet);
            $escher = null;
            // check if there are any shapes for this sheet
            $filterRange = $sheet->getAutoFilter()->getRange();
            if (\count($sheet->getDrawingCollection()) == 0 && empty($filterRange)) {
                continue;
            }
            // create intermediate Escher object
            $escher = new Escher();
            // dgContainer
            $dgContainer = new DgContainer();
            // set the drawing index (we use sheet index + 1)
            $dgId = $sheet->getParent()->getIndex($sheet) + 1;
            $dgContainer->setDgId($dgId);
            $escher->setDgContainer($dgContainer);
            // spgrContainer
            $spgrContainer = new SpgrContainer();
            $dgContainer->setSpgrContainer($spgrContainer);
            // add one shape which is the group shape
            $spContainer = new SpContainer();
            $spContainer->setSpgr(\true);
            $spContainer->setSpType(0);
            $spContainer->setSpId($sheet->getParent()->getIndex($sheet) + 1 << 10);
            $spgrContainer->addChild($spContainer);
            // add the shapes
            $countShapes[$sheetIndex] = 0;
            // count number of shapes (minus group shape), in sheet
            foreach ($sheet->getDrawingCollection() as $drawing) {
                ++$blipIndex;
                ++$countShapes[$sheetIndex];
                // add the shape
                $spContainer = new SpContainer();
                // set the shape type
                $spContainer->setSpType(0x4b);
                // set the shape flag
                $spContainer->setSpFlag(0x2);
                // set the shape index (we combine 1-based sheet index and $countShapes to create unique shape index)
                $reducedSpId = $countShapes[$sheetIndex];
                $spId = $reducedSpId | $sheet->getParent()->getIndex($sheet) + 1 << 10;
                $spContainer->setSpId($spId);
                // keep track of last reducedSpId
                $lastReducedSpId = $reducedSpId;
                // keep track of last spId
                $lastSpId = $spId;
                // set the BLIP index
                $spContainer->setOPT(0x4104, $blipIndex);
                // set coordinates and offsets, client anchor
                $coordinates = $drawing->getCoordinates();
                $offsetX = $drawing->getOffsetX();
                $offsetY = $drawing->getOffsetY();
                $width = $drawing->getWidth();
                $height = $drawing->getHeight();
                $twoAnchor = \LWVendor\PhpOffice\PhpSpreadsheet\Shared\Xls::oneAnchor2twoAnchor($sheet, $coordinates, $offsetX, $offsetY, $width, $height);
                $spContainer->setStartCoordinates($twoAnchor['startCoordinates']);
                $spContainer->setStartOffsetX($twoAnchor['startOffsetX']);
                $spContainer->setStartOffsetY($twoAnchor['startOffsetY']);
                $spContainer->setEndCoordinates($twoAnchor['endCoordinates']);
                $spContainer->setEndOffsetX($twoAnchor['endOffsetX']);
                $spContainer->setEndOffsetY($twoAnchor['endOffsetY']);
                $spgrContainer->addChild($spContainer);
            }
            // AutoFilters
            if (!empty($filterRange)) {
                $rangeBounds = Coordinate::rangeBoundaries($filterRange);
                $iNumColStart = $rangeBounds[0][0];
                $iNumColEnd = $rangeBounds[1][0];
                $iInc = $iNumColStart;
                while ($iInc <= $iNumColEnd) {
                    ++$countShapes[$sheetIndex];
                    // create an Drawing Object for the dropdown
                    $oDrawing = new BaseDrawing();
                    // get the coordinates of drawing
                    $cDrawing = Coordinate::stringFromColumnIndex($iInc) . $rangeBounds[0][1];
                    $oDrawing->setCoordinates($cDrawing);
                    $oDrawing->setWorksheet($sheet);
                    // add the shape
                    $spContainer = new SpContainer();
                    // set the shape type
                    $spContainer->setSpType(0xc9);
                    // set the shape flag
                    $spContainer->setSpFlag(0x1);
                    // set the shape index (we combine 1-based sheet index and $countShapes to create unique shape index)
                    $reducedSpId = $countShapes[$sheetIndex];
                    $spId = $reducedSpId | $sheet->getParent()->getIndex($sheet) + 1 << 10;
                    $spContainer->setSpId($spId);
                    // keep track of last reducedSpId
                    $lastReducedSpId = $reducedSpId;
                    // keep track of last spId
                    $lastSpId = $spId;
                    $spContainer->setOPT(0x7f, 0x1040104);
                    // Protection -> fLockAgainstGrouping
                    $spContainer->setOPT(0xbf, 0x80008);
                    // Text -> fFitTextToShape
                    $spContainer->setOPT(0x1bf, 0x10000);
                    // Fill Style -> fNoFillHitTest
                    $spContainer->setOPT(0x1ff, 0x80000);
                    // Line Style -> fNoLineDrawDash
                    $spContainer->setOPT(0x3bf, 0xa0000);
                    // Group Shape -> fPrint
                    // set coordinates and offsets, client anchor
                    $endCoordinates = Coordinate::stringFromColumnIndex($iInc);
                    $endCoordinates .= $rangeBounds[0][1] + 1;
                    $spContainer->setStartCoordinates($cDrawing);
                    $spContainer->setStartOffsetX(0);
                    $spContainer->setStartOffsetY(0);
                    $spContainer->setEndCoordinates($endCoordinates);
                    $spContainer->setEndOffsetX(0);
                    $spContainer->setEndOffsetY(0);
                    $spgrContainer->addChild($spContainer);
                    ++$iInc;
                }
            }
            // identifier clusters, used for workbook Escher object
            $this->IDCLs[$dgId] = $lastReducedSpId;
            // set last shape index
            $dgContainer->setLastSpId($lastSpId);
            // set the Escher object
            $this->writerWorksheets[$sheetIndex]->setEscher($escher);
        }
    }
    /**
     * Build the Escher object corresponding to the MSODRAWINGGROUP record.
     */
    private function buildWorkbookEscher() : void
    {
        $escher = null;
        // any drawings in this workbook?
        $found = \false;
        foreach ($this->spreadsheet->getAllSheets() as $sheet) {
            if (\count($sheet->getDrawingCollection()) > 0) {
                $found = \true;
                break;
            }
        }
        // nothing to do if there are no drawings
        if (!$found) {
            return;
        }
        // if we reach here, then there are drawings in the workbook
        $escher = new Escher();
        // dggContainer
        $dggContainer = new DggContainer();
        $escher->setDggContainer($dggContainer);
        // set IDCLs (identifier clusters)
        $dggContainer->setIDCLs($this->IDCLs);
        // this loop is for determining maximum shape identifier of all drawing
        $spIdMax = 0;
        $totalCountShapes = 0;
        $countDrawings = 0;
        foreach ($this->spreadsheet->getAllsheets() as $sheet) {
            $sheetCountShapes = 0;
            // count number of shapes (minus group shape), in sheet
            if (\count($sheet->getDrawingCollection()) > 0) {
                ++$countDrawings;
                foreach ($sheet->getDrawingCollection() as $drawing) {
                    ++$sheetCountShapes;
                    ++$totalCountShapes;
                    $spId = $sheetCountShapes | $this->spreadsheet->getIndex($sheet) + 1 << 10;
                    $spIdMax = \max($spId, $spIdMax);
                }
            }
        }
        $dggContainer->setSpIdMax($spIdMax + 1);
        $dggContainer->setCDgSaved($countDrawings);
        $dggContainer->setCSpSaved($totalCountShapes + $countDrawings);
        // total number of shapes incl. one group shapes per drawing
        // bstoreContainer
        $bstoreContainer = new BstoreContainer();
        $dggContainer->setBstoreContainer($bstoreContainer);
        // the BSE's (all the images)
        foreach ($this->spreadsheet->getAllsheets() as $sheet) {
            foreach ($sheet->getDrawingCollection() as $drawing) {
                if (!\extension_loaded('gd')) {
                    throw new RuntimeException('Saving images in xls requires gd extension');
                }
                if ($drawing instanceof Drawing) {
                    $filename = $drawing->getPath();
                    [$imagesx, $imagesy, $imageFormat] = \getimagesize($filename);
                    switch ($imageFormat) {
                        case 1:
                            // GIF, not supported by BIFF8, we convert to PNG
                            $blipType = BSE::BLIPTYPE_PNG;
                            \ob_start();
                            \imagepng(\imagecreatefromgif($filename));
                            $blipData = \ob_get_contents();
                            \ob_end_clean();
                            break;
                        case 2:
                            // JPEG
                            $blipType = BSE::BLIPTYPE_JPEG;
                            $blipData = \file_get_contents($filename);
                            break;
                        case 3:
                            // PNG
                            $blipType = BSE::BLIPTYPE_PNG;
                            $blipData = \file_get_contents($filename);
                            break;
                        case 6:
                            // Windows DIB (BMP), we convert to PNG
                            $blipType = BSE::BLIPTYPE_PNG;
                            \ob_start();
                            \imagepng(SharedDrawing::imagecreatefrombmp($filename));
                            $blipData = \ob_get_contents();
                            \ob_end_clean();
                            break;
                        default:
                            continue 2;
                    }
                    $blip = new Blip();
                    $blip->setData($blipData);
                    $BSE = new BSE();
                    $BSE->setBlipType($blipType);
                    $BSE->setBlip($blip);
                    $bstoreContainer->addBSE($BSE);
                } elseif ($drawing instanceof MemoryDrawing) {
                    switch ($drawing->getRenderingFunction()) {
                        case MemoryDrawing::RENDERING_JPEG:
                            $blipType = BSE::BLIPTYPE_JPEG;
                            $renderingFunction = 'imagejpeg';
                            break;
                        case MemoryDrawing::RENDERING_GIF:
                        case MemoryDrawing::RENDERING_PNG:
                        case MemoryDrawing::RENDERING_DEFAULT:
                            $blipType = BSE::BLIPTYPE_PNG;
                            $renderingFunction = 'imagepng';
                            break;
                    }
                    \ob_start();
                    \call_user_func($renderingFunction, $drawing->getImageResource());
                    $blipData = \ob_get_contents();
                    \ob_end_clean();
                    $blip = new Blip();
                    $blip->setData($blipData);
                    $BSE = new BSE();
                    $BSE->setBlipType($blipType);
                    $BSE->setBlip($blip);
                    $bstoreContainer->addBSE($BSE);
                }
            }
        }
        // Set the Escher object
        $this->writerWorkbook->setEscher($escher);
    }
    /**
     * Build the OLE Part for DocumentSummary Information.
     *
     * @return string
     */
    private function writeDocumentSummaryInformation()
    {
        // offset: 0; size: 2; must be 0xFE 0xFF (UTF-16 LE byte order mark)
        $data = \pack('v', 0xfffe);
        // offset: 2; size: 2;
        $data .= \pack('v', 0x0);
        // offset: 4; size: 2; OS version
        $data .= \pack('v', 0x106);
        // offset: 6; size: 2; OS indicator
        $data .= \pack('v', 0x2);
        // offset: 8; size: 16
        $data .= \pack('VVVV', 0x0, 0x0, 0x0, 0x0);
        // offset: 24; size: 4; section count
        $data .= \pack('V', 0x1);
        // offset: 28; size: 16; first section's class id: 02 d5 cd d5 9c 2e 1b 10 93 97 08 00 2b 2c f9 ae
        $data .= \pack('vvvvvvvv', 0xd502, 0xd5cd, 0x2e9c, 0x101b, 0x9793, 0x8, 0x2c2b, 0xaef9);
        // offset: 44; size: 4; offset of the start
        $data .= \pack('V', 0x30);
        // SECTION
        $dataSection = [];
        $dataSection_NumProps = 0;
        $dataSection_Summary = '';
        $dataSection_Content = '';
        // GKPIDDSI_CODEPAGE: CodePage
        $dataSection[] = [
            'summary' => ['pack' => 'V', 'data' => 0x1],
            'offset' => ['pack' => 'V'],
            'type' => ['pack' => 'V', 'data' => 0x2],
            // 2 byte signed integer
            'data' => ['data' => 1252],
        ];
        ++$dataSection_NumProps;
        // GKPIDDSI_CATEGORY : Category
        if ($this->spreadsheet->getProperties()->getCategory()) {
            $dataProp = $this->spreadsheet->getProperties()->getCategory();
            $dataSection[] = ['summary' => ['pack' => 'V', 'data' => 0x2], 'offset' => ['pack' => 'V'], 'type' => ['pack' => 'V', 'data' => 0x1e], 'data' => ['data' => $dataProp, 'length' => \strlen($dataProp)]];
            ++$dataSection_NumProps;
        }
        // GKPIDDSI_VERSION :Version of the application that wrote the property storage
        $dataSection[] = ['summary' => ['pack' => 'V', 'data' => 0x17], 'offset' => ['pack' => 'V'], 'type' => ['pack' => 'V', 'data' => 0x3], 'data' => ['pack' => 'V', 'data' => 0xc0000]];
        ++$dataSection_NumProps;
        // GKPIDDSI_SCALE : FALSE
        $dataSection[] = ['summary' => ['pack' => 'V', 'data' => 0xb], 'offset' => ['pack' => 'V'], 'type' => ['pack' => 'V', 'data' => 0xb], 'data' => ['data' => \false]];
        ++$dataSection_NumProps;
        // GKPIDDSI_LINKSDIRTY : True if any of the values for the linked properties have changed outside of the application
        $dataSection[] = ['summary' => ['pack' => 'V', 'data' => 0x10], 'offset' => ['pack' => 'V'], 'type' => ['pack' => 'V', 'data' => 0xb], 'data' => ['data' => \false]];
        ++$dataSection_NumProps;
        // GKPIDDSI_SHAREDOC : FALSE
        $dataSection[] = ['summary' => ['pack' => 'V', 'data' => 0x13], 'offset' => ['pack' => 'V'], 'type' => ['pack' => 'V', 'data' => 0xb], 'data' => ['data' => \false]];
        ++$dataSection_NumProps;
        // GKPIDDSI_HYPERLINKSCHANGED : True if any of the values for the _PID_LINKS (hyperlink text) have changed outside of the application
        $dataSection[] = ['summary' => ['pack' => 'V', 'data' => 0x16], 'offset' => ['pack' => 'V'], 'type' => ['pack' => 'V', 'data' => 0xb], 'data' => ['data' => \false]];
        ++$dataSection_NumProps;
        // GKPIDDSI_DOCSPARTS
        // MS-OSHARED p75 (2.3.3.2.2.1)
        // Structure is VtVecUnalignedLpstrValue (2.3.3.1.9)
        // cElements
        $dataProp = \pack('v', 0x1);
        $dataProp .= \pack('v', 0x0);
        // array of UnalignedLpstr
        // cch
        $dataProp .= \pack('v', 0xa);
        $dataProp .= \pack('v', 0x0);
        // value
        $dataProp .= 'Worksheet' . \chr(0);
        $dataSection[] = ['summary' => ['pack' => 'V', 'data' => 0xd], 'offset' => ['pack' => 'V'], 'type' => ['pack' => 'V', 'data' => 0x101e], 'data' => ['data' => $dataProp, 'length' => \strlen($dataProp)]];
        ++$dataSection_NumProps;
        // GKPIDDSI_HEADINGPAIR
        // VtVecHeadingPairValue
        // cElements
        $dataProp = \pack('v', 0x2);
        $dataProp .= \pack('v', 0x0);
        // Array of vtHeadingPair
        // vtUnalignedString - headingString
        // stringType
        $dataProp .= \pack('v', 0x1e);
        // padding
        $dataProp .= \pack('v', 0x0);
        // UnalignedLpstr
        // cch
        $dataProp .= \pack('v', 0x13);
        $dataProp .= \pack('v', 0x0);
        // value
        $dataProp .= 'Feuilles de calcul';
        // vtUnalignedString - headingParts
        // wType : 0x0003 = 32 bit signed integer
        $dataProp .= \pack('v', 0x300);
        // padding
        $dataProp .= \pack('v', 0x0);
        // value
        $dataProp .= \pack('v', 0x100);
        $dataProp .= \pack('v', 0x0);
        $dataProp .= \pack('v', 0x0);
        $dataProp .= \pack('v', 0x0);
        $dataSection[] = ['summary' => ['pack' => 'V', 'data' => 0xc], 'offset' => ['pack' => 'V'], 'type' => ['pack' => 'V', 'data' => 0x100c], 'data' => ['data' => $dataProp, 'length' => \strlen($dataProp)]];
        ++$dataSection_NumProps;
        //         4     Section Length
        //        4     Property count
        //        8 * $dataSection_NumProps (8 =  ID (4) + OffSet(4))
        $dataSection_Content_Offset = 8 + $dataSection_NumProps * 8;
        foreach ($dataSection as $dataProp) {
            // Summary
            $dataSection_Summary .= \pack($dataProp['summary']['pack'], $dataProp['summary']['data']);
            // Offset
            $dataSection_Summary .= \pack($dataProp['offset']['pack'], $dataSection_Content_Offset);
            // DataType
            $dataSection_Content .= \pack($dataProp['type']['pack'], $dataProp['type']['data']);
            // Data
            if ($dataProp['type']['data'] == 0x2) {
                // 2 byte signed integer
                $dataSection_Content .= \pack('V', $dataProp['data']['data']);
                $dataSection_Content_Offset += 4 + 4;
            } elseif ($dataProp['type']['data'] == 0x3) {
                // 4 byte signed integer
                $dataSection_Content .= \pack('V', $dataProp['data']['data']);
                $dataSection_Content_Offset += 4 + 4;
            } elseif ($dataProp['type']['data'] == 0xb) {
                // Boolean
                if ($dataProp['data']['data'] == \false) {
                    $dataSection_Content .= \pack('V', 0x0);
                } else {
                    $dataSection_Content .= \pack('V', 0x1);
                }
                $dataSection_Content_Offset += 4 + 4;
            } elseif ($dataProp['type']['data'] == 0x1e) {
                // null-terminated string prepended by dword string length
                // Null-terminated string
                $dataProp['data']['data'] .= \chr(0);
                ++$dataProp['data']['length'];
                // Complete the string with null string for being a %4
                $dataProp['data']['length'] = $dataProp['data']['length'] + (4 - $dataProp['data']['length'] % 4 == 4 ? 0 : 4 - $dataProp['data']['length'] % 4);
                $dataProp['data']['data'] = \str_pad($dataProp['data']['data'], $dataProp['data']['length'], \chr(0), \STR_PAD_RIGHT);
                $dataSection_Content .= \pack('V', $dataProp['data']['length']);
                $dataSection_Content .= $dataProp['data']['data'];
                $dataSection_Content_Offset += 4 + 4 + \strlen($dataProp['data']['data']);
            } elseif ($dataProp['type']['data'] == 0x40) {
                // Filetime (64-bit value representing the number of 100-nanosecond intervals since January 1, 1601)
                $dataSection_Content .= $dataProp['data']['data'];
                $dataSection_Content_Offset += 4 + 8;
            } else {
                // Data Type Not Used at the moment
                $dataSection_Content .= $dataProp['data']['data'];
                $dataSection_Content_Offset += 4 + $dataProp['data']['length'];
            }
        }
        // Now $dataSection_Content_Offset contains the size of the content
        // section header
        // offset: $secOffset; size: 4; section length
        //         + x  Size of the content (summary + content)
        $data .= \pack('V', $dataSection_Content_Offset);
        // offset: $secOffset+4; size: 4; property count
        $data .= \pack('V', $dataSection_NumProps);
        // Section Summary
        $data .= $dataSection_Summary;
        // Section Content
        $data .= $dataSection_Content;
        return $data;
    }
    /**
     * Build the OLE Part for Summary Information.
     *
     * @return string
     */
    private function writeSummaryInformation()
    {
        // offset: 0; size: 2; must be 0xFE 0xFF (UTF-16 LE byte order mark)
        $data = \pack('v', 0xfffe);
        // offset: 2; size: 2;
        $data .= \pack('v', 0x0);
        // offset: 4; size: 2; OS version
        $data .= \pack('v', 0x106);
        // offset: 6; size: 2; OS indicator
        $data .= \pack('v', 0x2);
        // offset: 8; size: 16
        $data .= \pack('VVVV', 0x0, 0x0, 0x0, 0x0);
        // offset: 24; size: 4; section count
        $data .= \pack('V', 0x1);
        // offset: 28; size: 16; first section's class id: e0 85 9f f2 f9 4f 68 10 ab 91 08 00 2b 27 b3 d9
        $data .= \pack('vvvvvvvv', 0x85e0, 0xf29f, 0x4ff9, 0x1068, 0x91ab, 0x8, 0x272b, 0xd9b3);
        // offset: 44; size: 4; offset of the start
        $data .= \pack('V', 0x30);
        // SECTION
        $dataSection = [];
        $dataSection_NumProps = 0;
        $dataSection_Summary = '';
        $dataSection_Content = '';
        // CodePage : CP-1252
        $dataSection[] = [
            'summary' => ['pack' => 'V', 'data' => 0x1],
            'offset' => ['pack' => 'V'],
            'type' => ['pack' => 'V', 'data' => 0x2],
            // 2 byte signed integer
            'data' => ['data' => 1252],
        ];
        ++$dataSection_NumProps;
        //    Title
        if ($this->spreadsheet->getProperties()->getTitle()) {
            $dataProp = $this->spreadsheet->getProperties()->getTitle();
            $dataSection[] = [
                'summary' => ['pack' => 'V', 'data' => 0x2],
                'offset' => ['pack' => 'V'],
                'type' => ['pack' => 'V', 'data' => 0x1e],
                // null-terminated string prepended by dword string length
                'data' => ['data' => $dataProp, 'length' => \strlen($dataProp)],
            ];
            ++$dataSection_NumProps;
        }
        //    Subject
        if ($this->spreadsheet->getProperties()->getSubject()) {
            $dataProp = $this->spreadsheet->getProperties()->getSubject();
            $dataSection[] = [
                'summary' => ['pack' => 'V', 'data' => 0x3],
                'offset' => ['pack' => 'V'],
                'type' => ['pack' => 'V', 'data' => 0x1e],
                // null-terminated string prepended by dword string length
                'data' => ['data' => $dataProp, 'length' => \strlen($dataProp)],
            ];
            ++$dataSection_NumProps;
        }
        //    Author (Creator)
        if ($this->spreadsheet->getProperties()->getCreator()) {
            $dataProp = $this->spreadsheet->getProperties()->getCreator();
            $dataSection[] = [
                'summary' => ['pack' => 'V', 'data' => 0x4],
                'offset' => ['pack' => 'V'],
                'type' => ['pack' => 'V', 'data' => 0x1e],
                // null-terminated string prepended by dword string length
                'data' => ['data' => $dataProp, 'length' => \strlen($dataProp)],
            ];
            ++$dataSection_NumProps;
        }
        //    Keywords
        if ($this->spreadsheet->getProperties()->getKeywords()) {
            $dataProp = $this->spreadsheet->getProperties()->getKeywords();
            $dataSection[] = [
                'summary' => ['pack' => 'V', 'data' => 0x5],
                'offset' => ['pack' => 'V'],
                'type' => ['pack' => 'V', 'data' => 0x1e],
                // null-terminated string prepended by dword string length
                'data' => ['data' => $dataProp, 'length' => \strlen($dataProp)],
            ];
            ++$dataSection_NumProps;
        }
        //    Comments (Description)
        if ($this->spreadsheet->getProperties()->getDescription()) {
            $dataProp = $this->spreadsheet->getProperties()->getDescription();
            $dataSection[] = [
                'summary' => ['pack' => 'V', 'data' => 0x6],
                'offset' => ['pack' => 'V'],
                'type' => ['pack' => 'V', 'data' => 0x1e],
                // null-terminated string prepended by dword string length
                'data' => ['data' => $dataProp, 'length' => \strlen($dataProp)],
            ];
            ++$dataSection_NumProps;
        }
        //    Last Saved By (LastModifiedBy)
        if ($this->spreadsheet->getProperties()->getLastModifiedBy()) {
            $dataProp = $this->spreadsheet->getProperties()->getLastModifiedBy();
            $dataSection[] = [
                'summary' => ['pack' => 'V', 'data' => 0x8],
                'offset' => ['pack' => 'V'],
                'type' => ['pack' => 'V', 'data' => 0x1e],
                // null-terminated string prepended by dword string length
                'data' => ['data' => $dataProp, 'length' => \strlen($dataProp)],
            ];
            ++$dataSection_NumProps;
        }
        //    Created Date/Time
        if ($this->spreadsheet->getProperties()->getCreated()) {
            $dataProp = $this->spreadsheet->getProperties()->getCreated();
            $dataSection[] = [
                'summary' => ['pack' => 'V', 'data' => 0xc],
                'offset' => ['pack' => 'V'],
                'type' => ['pack' => 'V', 'data' => 0x40],
                // Filetime (64-bit value representing the number of 100-nanosecond intervals since January 1, 1601)
                'data' => ['data' => OLE::localDateToOLE($dataProp)],
            ];
            ++$dataSection_NumProps;
        }
        //    Modified Date/Time
        if ($this->spreadsheet->getProperties()->getModified()) {
            $dataProp = $this->spreadsheet->getProperties()->getModified();
            $dataSection[] = [
                'summary' => ['pack' => 'V', 'data' => 0xd],
                'offset' => ['pack' => 'V'],
                'type' => ['pack' => 'V', 'data' => 0x40],
                // Filetime (64-bit value representing the number of 100-nanosecond intervals since January 1, 1601)
                'data' => ['data' => OLE::localDateToOLE($dataProp)],
            ];
            ++$dataSection_NumProps;
        }
        //    Security
        $dataSection[] = [
            'summary' => ['pack' => 'V', 'data' => 0x13],
            'offset' => ['pack' => 'V'],
            'type' => ['pack' => 'V', 'data' => 0x3],
            // 4 byte signed integer
            'data' => ['data' => 0x0],
        ];
        ++$dataSection_NumProps;
        //         4     Section Length
        //        4     Property count
        //        8 * $dataSection_NumProps (8 =  ID (4) + OffSet(4))
        $dataSection_Content_Offset = 8 + $dataSection_NumProps * 8;
        foreach ($dataSection as $dataProp) {
            // Summary
            $dataSection_Summary .= \pack($dataProp['summary']['pack'], $dataProp['summary']['data']);
            // Offset
            $dataSection_Summary .= \pack($dataProp['offset']['pack'], $dataSection_Content_Offset);
            // DataType
            $dataSection_Content .= \pack($dataProp['type']['pack'], $dataProp['type']['data']);
            // Data
            if ($dataProp['type']['data'] == 0x2) {
                // 2 byte signed integer
                $dataSection_Content .= \pack('V', $dataProp['data']['data']);
                $dataSection_Content_Offset += 4 + 4;
            } elseif ($dataProp['type']['data'] == 0x3) {
                // 4 byte signed integer
                $dataSection_Content .= \pack('V', $dataProp['data']['data']);
                $dataSection_Content_Offset += 4 + 4;
            } elseif ($dataProp['type']['data'] == 0x1e) {
                // null-terminated string prepended by dword string length
                // Null-terminated string
                $dataProp['data']['data'] .= \chr(0);
                ++$dataProp['data']['length'];
                // Complete the string with null string for being a %4
                $dataProp['data']['length'] = $dataProp['data']['length'] + (4 - $dataProp['data']['length'] % 4 == 4 ? 0 : 4 - $dataProp['data']['length'] % 4);
                $dataProp['data']['data'] = \str_pad($dataProp['data']['data'], $dataProp['data']['length'], \chr(0), \STR_PAD_RIGHT);
                $dataSection_Content .= \pack('V', $dataProp['data']['length']);
                $dataSection_Content .= $dataProp['data']['data'];
                $dataSection_Content_Offset += 4 + 4 + \strlen($dataProp['data']['data']);
            } elseif ($dataProp['type']['data'] == 0x40) {
                // Filetime (64-bit value representing the number of 100-nanosecond intervals since January 1, 1601)
                $dataSection_Content .= $dataProp['data']['data'];
                $dataSection_Content_Offset += 4 + 8;
            }
            // Data Type Not Used at the moment
        }
        // Now $dataSection_Content_Offset contains the size of the content
        // section header
        // offset: $secOffset; size: 4; section length
        //         + x  Size of the content (summary + content)
        $data .= \pack('V', $dataSection_Content_Offset);
        // offset: $secOffset+4; size: 4; property count
        $data .= \pack('V', $dataSection_NumProps);
        // Section Summary
        $data .= $dataSection_Summary;
        // Section Content
        $data .= $dataSection_Content;
        return $data;
    }
}
