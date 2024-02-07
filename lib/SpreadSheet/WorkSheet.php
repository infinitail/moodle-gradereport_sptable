<?php

namespace moodle\grade\report\sptable\SpreadSheet;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet as PhpSpreadsheetWorksheet;

class WorkSheet extends PhpSpreadsheetWorksheet
{   
    /**
     * Draw cell border line
     *
     * @param int $col      - target col number
     * @param int $row      - target row number
     * @param string $pos   - border postion [top, bottom, left, right]
     * @param string $style - border line style
     *      [none, dashDot, dashDotDot, dashed, dotted, double, hair, medium,
     *      mediumDashDot, mediumDashDotDot, mediumDashed, slantDashDot, thick, thin]
     * @param string $color - RGB color code
     * @return void
     */
    public function drawCellBorder(int $col, int $row, string $pos, string $style='thin', string $color='000000')
    {
        $this->getStyleByColumnAndRow($col, $row)
            ->applyFromArray([
                'borders' => [
                    $pos => [
                        'borderStyle' => $style,
                        'color' => ['rgb' => $color]
                    ]
                ]
            ]);
    }
}