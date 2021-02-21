<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Excel writer abstraction layer.
 *
 * @copyright
 * @license
 * @package     gradereport_sptable
 */

defined('MOODLE_INTERNAL') || die();
require_once $CFG->libdir.'/excellib.class.php';

class MoodleExcelWorkbookSP extends MoodleExcelWorkbook {
    public function add_worksheet($name = '') {
        return new MoodleExcelWorksheetSP($name, $this->objPHPExcel);
    }
}

class MoodleExcelWorksheetSP extends MoodleExcelWorksheet {
    public function draw_line($row, $col, $pos, $style='thin', $color='000000') {
        $this->worksheet
            ->getStyleByColumnAndRow($col, $row+1)
            ->applyFromArray([
                'borders' => [
                    $pos => [
                        'style' => $style,
                        'color' => ['rgb' => $color]
                    ]
                ]
            ]);
    }
}