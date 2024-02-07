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
 * Definition of the grade_user_report class is defined
 *
 * @package    grade_report_sptable
 * @author     infinitail
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once $CFG->dirroot.'/mod/quiz/report/overview/overview_table.php';
require_once $CFG->dirroot.'/grade/report/sptable/locallib.php';
require_once $CFG->dirroot.'/grade/report/sptable/lib/SpTable/DefereceCoefficient.php';

use PhpOffice\PhpSpreadsheet\Chart\Layout;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\Axis as ChartAxis;
use PhpOffice\PhpSpreadsheet\Chart\ChartColor;
use PhpOffice\PhpSpreadsheet\Chart\GridLines;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\TrendLine;
use PhpOffice\PhpSpreadsheet\Chart\Properties;
use Grade\Report\SpTable as SpTable;
use Grade\Report\SpTable\DefereceCoefficient;

// Related classes
// https://github.com/moodle/moodle/blob/master/mod/quiz/report/overview/overview_table.php
// https://github.com/moodle/moodle/blob/master/mod/quiz/report/attemptsreport.php
// https://github.com/moodle/moodle/blob/master/mod/quiz/report/default.php
// https://github.com/moodle/moodle/blob/master/lib/tablelib.php

class quiz_overview_sptable extends quiz_overview_table {
    public $allowed_user_attributes = [
        'username',
        'fullname',
        'firstname',
        'lastname',
        'idnumber',
        'institution',
        'department',
    ];

    public function out($pagesize, $useinitialsbar, $downloadhelpbutton='') {
        global $CFG, $DB;
        if (!$this->columns) {
            $onerow = $DB->get_record_sql("SELECT {$this->sql->fields} FROM {$this->sql->from} WHERE {$this->sql->where}",
                $this->sql->params, IGNORE_MULTIPLE);
            //if columns is not set then define columns as the keys of the rows returned
            //from the db.
            $this->define_columns(array_keys((array)$onerow));
            $this->define_headers(array_keys((array)$onerow));
        }
        $this->pagesize = $pagesize;
        $this->setup();
        $this->query_db($pagesize, $useinitialsbar);
        //$this->build_table(); // Disable output table
        $this->close_recordset();
        //$this->finish_output();

        //echo '<pre>'; var_dump($this->rawdata); echo '</pre>';die();
        $score_rows = [];
        foreach ($this->rawdata as $row) {
            // Get Users and scores
            $formattedrow = $this->format_row($row);
            //echo '<pre>'; var_dump($formattedrow); echo '</pre>';
            $score_rows[] = $formattedrow;
        }

        $questions = quiz_report_get_significant_questions($this->quiz);
        //echo '<pre>'; var_dump($questions); echo '</pre>';

        // Create normalized score matrix for sorting
        $score_matrix = [];
        foreach ($score_rows as $user_key=>$score_row) {
            foreach ($score_row as $key=>$value) {
                if (substr($key, 0, 7) !== 'qsgrade') {
                    continue;
                }
                $slot = substr($key, 7);
                $maxmark = $questions[$slot]->maxmark;
                //echo '<pre>'; var_dump($key . ' - ' . $value . ' / ' . $maxmark); echo '</pre>';

                // Normalize question score to percentage
                $percentage = (int) floor($value / $maxmark * 100);

                $score_matrix[$user_key][$slot] = $percentage;
            }
        }

        //echo '<pre>'; var_dump($score_matrix); echo '</pre>';die();

        // Create rate data for sorting
        $u_array = [];
        $q_array = [];
        foreach ($score_matrix as $user_key=>$scores) {
            $q_rate = 0;
            $u_rate = 0;
            foreach ($scores as $question_key=>$value) {
                // TODO: Treat partial score
                $rate = ($value === 100) ? 1 : 0;

                @$u_array[$user_key]     += $rate;
                @$q_array[$question_key] += $rate;
            }
        }

        // ORDER BY Score, UserId.
        $u_score = array_values($u_array);
        $u_key   = array_keys($u_array);
        array_multisort($u_score, SORT_DESC, SORT_NUMERIC, $u_key);
        $u_order = $u_key;

        // ORDER BY Score, Question No.
        $q_score = array_values($q_array);
        $q_key   = array_keys($q_array);
        array_multisort($q_score, SORT_DESC, SORT_NUMERIC, $q_key);
        $q_order = $q_key;

        arsort($u_array);
        arsort($q_array);

        $sorted_rows = [];
        foreach ($u_order as $u_id=>$u_key) {
            preg_match('/\/user\/view.php\?id=([0-9]+)&/', $score_rows[$u_key]['fullname'], $matches);
            $sorted_rows[$u_id]['userid'] = $matches[1];
            $sorted_rows[$u_id]['score'] = $u_array[$u_key];

            foreach ($q_order as $q_id=>$q_key) {
                $sorted_rows[$u_id]['qsgrade'.$q_key] = $score_matrix[$u_key][$q_key];
            }
        }

        //echo '<pre>'; var_dump($q_array); echo '</pre>'; die();
        //echo '<pre>'; var_dump($sorted_rows); echo '</pre>'; die();

        require_once $CFG->libdir.'/phpspreadsheet/vendor/autoload.php';
        require_once __DIR__.'/SpreadSheet/WorkSheet.php';
        $work_book =  new \PhpOffice\PhpSpreadsheet\Spreadsheet;

        // Remove Default Sheet
        $work_book->removeSheetByIndex(0);
        $sheet_id = 0;

        // Create S-P Table Sheet
        $sptable_sheet = new \moodle\grade\report\sptable\Spreadsheet\WorkSheet($work_book, 'S-P Table');
        $work_book->addSheet($sptable_sheet, $sheet_id);

        $user_attributes = get_config('gradereport_sptable', 'user_attributes');
        $user_attributes = explode(' ', $user_attributes);
        $user_attributes = array_intersect($user_attributes, $this->allowed_user_attributes);   // filter allowed attributes
        $user_attributes = array_values($user_attributes);                                      // remove empty element
        
        // Add sheet header
        // Print user attributes
        $row_pos = 1;
        $col_pos = 1;
        foreach ($user_attributes as $user_attribute) {
            $sptable_sheet->setCellValueByColumnAndRow($col_pos, $row_pos, get_string($user_attribute));
            $sptable_sheet->drawCellBorder($col_pos, $row_pos, 'bottom', 'thin', '000000');
            $col_pos++;
        }
        $sptable_sheet->drawCellBorder(count($user_attributes), $row_pos, 'right', 'thin', '000000');

        // Print question number
        foreach ($q_order as $key=>$value) {
            $col_pos = count($user_attributes) + 1 + $key;
            $sptable_sheet->setCellValueByColumnAndRow($col_pos, $row_pos, "Q{$value}");
            $sptable_sheet->drawCellBorder($col_pos, $row_pos, 'bottom', 'thin', '000000');
        }

        // Print total score header
        $col_pos = count($user_attributes) + count($q_order) + 2;
        $sptable_sheet->setCellValueByColumnAndRow($col_pos, $row_pos,get_string('score', 'gradereport_sptable'));
        $sptable_sheet->drawCellBorder($col_pos, $row_pos, 'bottom', 'thin', '000000');

        // Print accuracy header
        $col_pos = count($user_attributes) + count($q_order) + 3;
        $sptable_sheet->setCellValueByColumnAndRow($col_pos, $row_pos, get_string('accuracy', 'gradereport_sptable'));
        $sptable_sheet->drawCellBorder($col_pos, $row_pos, 'bottom', 'thin', '000000');
        
        $prev_score = 0;
        foreach ($sorted_rows as $row_key=>$row) {
            $row_pos = $row_key + 2;
            $col_pos = 1;

            // Print user info
            $user = core_user::get_user($row['userid']);
            foreach ($user_attributes as $attribute) {
                $cell_value = ($attribute === 'fullname') ? fullname($user) : $user->{$attribute};
                $sptable_sheet->setCellValueByColumnAndRow($col_pos, $row_pos, $cell_value);
                $col_pos++;
            }
            $sptable_sheet->drawCellBorder(count($user_attributes), $row_pos, 'right', 'thin', '000000');

            // Fill question score
            //$col_pos = count($user_attributes) + 3;
            foreach ($row as $key=>$value) {
                if (substr($key, 0, 7) !== 'qsgrade') {
                    continue;
                }

                $slot = substr($key, 7);
                $score = (int) floor($value / 100);
                $sptable_sheet->setCellValueByColumnAndRow($col_pos, $row_pos, $score);
                $col_pos++;
            }

            // Fill user total score
            $col_pos++;
            $sptable_sheet->setCellValueByColumnAndRow($col_pos, $row_pos, $row['score']);
            $col_pos++;

            // Fill user average score
            $sptable_sheet->setCellValueByColumnAndRow($col_pos, $row_pos, sprintf('%0.2f', $row['score']/count($q_array)));

            // Draw student-score line (RED)
            $sptable_sheet->drawCellBorder($row['score'] + count($user_attributes) + 1, $row_pos, 'left', 'thin', 'FF0000');
            if ($prev_score > $row['score']) {
                for ($i=$prev_score; $i>$row['score']; $i--) {
                    $col_pos = $i + count($user_attributes);
                    $sptable_sheet->drawCellBorder($col_pos, $row_pos, 'top', 'thin', 'FF0000');
                }
            }
            $prev_score = $row['score'];
        }
        $row_pos += 2;

        // Fill question total score headers
        $col_pos = count($user_attributes);
        $sptable_sheet->setCellValueByColumnAndRow($col_pos, $row_pos, get_string('rightanswers', 'gradereport_sptable'));
        $sptable_sheet->drawCellBorder($col_pos, $row_pos, 'right', 'thin', '000000');
        $sptable_sheet->setCellValueByColumnAndRow($col_pos, $row_pos + 1, get_string('accuracy', 'gradereport_sptable'));
        $sptable_sheet->drawCellBorder($col_pos, $row_pos + 1, 'right', 'thin', '000000');

        $col_pos = count($user_attributes) + 1;
        foreach ($q_array as $score) {
            // Fill user / average score
            $sptable_sheet->setCellValueByColumnAndRow($col_pos, $row_pos, $score);
            $sptable_sheet->setCellValueByColumnAndRow($col_pos, $row_pos + 1, sprintf('%0.2f', $score/count($u_array)));

            $col_pos++;
        }

        // Fill question total score
        $col_pos = count($user_attributes) + count($q_array) + 2;
        $sptable_sheet->setCellValueByColumnAndRow($col_pos, $row_pos, array_sum($q_array));

        // Draw problem-score line (BLUE)
        $prev_score = 0;
        $col_pos = count($user_attributes) + 1;
        foreach ($q_array as $score) {
            $sptable_sheet->drawCellBorder($col_pos, $score + 1, 'bottom', 'dashDot', '0000FF');
            if ($prev_score > $score) {
                for ($i=$prev_score; $i>$score; $i--) {
                    $sptable_sheet->drawCellBorder($col_pos, $i + 1, 'left', 'dashDot', '0000FF');
                }
            }

            $col_pos++;
            $prev_score = $score;
        }

        // Add Student Attention Score (caution_score = (param_a - param_b)/(param_c - param_d * param_e))
        $col_pos = count($user_attributes) + count($q_array) + 4;
        $row_pos = 1;

        $sptable_sheet->setCellValueByColumnAndRow($col_pos, $row_pos, get_string('cautionscore', 'gradereport_sptable'));
        $sptable_sheet->drawCellBorder($col_pos, $row_pos, 'bottom', 'thin', '000000');

        foreach ($sorted_rows as $row) {
            $row_pos++;
            $s_position = $row['score'];

            $param_a = 0;
            $param_b = 0;
            $param_c = 0;
            $param_d = $row['score'];
            $param_e = array_sum($q_array) / count($q_array);

            $counter = 0;
            foreach ($q_array as $q_key=>$q_score) {
                if ($counter < $s_position && $row['qsgrade'.$q_key] !== 100) {
                    $param_a += $q_score;
                }

                if ($counter >= $s_position && $row['qsgrade'.$q_key] === 100) {
                    $param_b += $q_score;
                }

                if ($counter < $s_position) {
                    $param_c += $q_score;
                }

                $counter++;
            }

            if (($param_c - $param_d*$param_e) > 0) {
                $caution_score = @(($param_a - $param_b) / ($param_c - $param_d*$param_e));
            } else {
                $caution_score = 0;
            }
            $caution_score = sprintf('%0.2f', $caution_score);
            $sptable_sheet->setCellValueByColumnAndRow($col_pos, $row_pos, $caution_score);
        }

        // Add Problem Attention Score (caution_score = (param_a - param_b)/(param_c - param_d * param_e))
        $row_pos = count($u_array) + 5;
        $col_pos = count($user_attributes);
        $sptable_sheet->setCellValueByColumnAndRow($col_pos, $row_pos, get_string('cautionpoint', 'gradereport_sptable'));
        $sptable_sheet->drawCellBorder($col_pos, $row_pos, 'right', 'thin', '000000');

        foreach ($q_array as $q_key=>$q_score) {
            $col_pos++;
            $q_position = $q_score;

            $param_a = 0;
            $param_b = 0;
            $param_c = 0;
            $param_d = $q_score;
            $param_e = array_sum($q_array) / count($u_array);

            $counter = 0;
            foreach ($sorted_rows as $row) {
                if ($counter < $q_position && $row['qsgrade'.$q_key] !== 100) {
                    $param_a += $row['score'];
                }

                if ($counter >= $q_position && $row['qsgrade'.$q_key] === 100) {
                    $param_b += $row['score'];
                }

                if ($counter < $q_position) {
                    $param_c += $row['score'];
                }

                $counter++;
            }
            
            if (($param_c - $param_d*$param_e) > 0) {
                $caution_score = @(($param_a - $param_b) / ($param_c - $param_d*$param_e));
            } else {
                $caution_score = 0;
            }
            $caution_score = sprintf('%0.2f', $caution_score);
            $sptable_sheet->setCellValueByColumnAndRow($col_pos, $row_pos, $caution_score);
        }

        /**
         * Add Student Scatter Chart 
         */
        // Change sheet
        $sheet_name = 'Scatter_Student';
        $chart_sheet_s = new \moodle\grade\report\sptable\Spreadsheet\WorkSheet($work_book, $sheet_name);
        $sheet_id++;
        $work_book->addSheet($chart_sheet_s, $sheet_id);

        // Create Summary
        // Add Headers
        $row_pos = 1;
        $col_pos = 1;
        foreach ($user_attributes as $user_attribute) {
            $chart_sheet_s->setCellValueByColumnAndRow($col_pos, $row_pos, get_string($user_attribute));
            $chart_sheet_s->drawCellBorder($col_pos, $row_pos, 'bottom', 'thin', '000000');
            $col_pos++;
        }
        $chart_sheet_s->setCellValueByColumnAndRow($col_pos, $row_pos, get_string('accuracy', 'gradereport_sptable'));
        $chart_sheet_s->drawCellBorder($col_pos, $row_pos, 'bottom', 'thin', '000000');

        $row_pos = 2;
        foreach ($sorted_rows as $row) {
            $col_pos = 1;

            // Print user info
            $user = core_user::get_user($row['userid']);
            foreach ($user_attributes as $attribute) {
                $cell_value = ($attribute === 'fullname') ? fullname($user) : $user->{$attribute};
                $chart_sheet_s->setCellValueByColumnAndRow($col_pos, $row_pos, $cell_value);
                $col_pos++;
            }
            $chart_sheet_s->drawCellBorder(count($user_attributes), $row_pos, 'right', 'thin', '000000');
            
            // Fill user average score
            $chart_sheet_s->setCellValueByColumnAndRow($col_pos, $row_pos, sprintf('%0.2f', $row['score']/count($q_array)));

            $row_pos++;
        }

        // Add Student Attention Score (caution_score = (param_a - param_b)/(param_c - param_d * param_e))
        $col_pos = count($user_attributes) + 2;
        $row_pos = 1;

        $chart_sheet_s->setCellValueByColumnAndRow($col_pos, $row_pos, get_string('cautionscore', 'gradereport_sptable'));
        $chart_sheet_s->drawCellBorder($col_pos, $row_pos, 'bottom', 'thin', '000000');

        $caution_score_max = 0;
        foreach ($sorted_rows as $row) {
            $row_pos++;
            $s_position = $row['score'];

            $param_a = 0;
            $param_b = 0;
            $param_c = 0;
            $param_d = $row['score'];
            $param_e = array_sum($q_array) / count($q_array);

            $counter = 0;
            foreach ($q_array as $q_key=>$q_score) {
                if ($counter < $s_position && $row['qsgrade'.$q_key] !== 100) {
                    $param_a += $q_score;
                }

                if ($counter >= $s_position && $row['qsgrade'.$q_key] === 100) {
                    $param_b += $q_score;
                }

                if ($counter < $s_position) {
                    $param_c += $q_score;
                }

                $counter++;
            }

            if (($param_c - $param_d*$param_e) > 0) {
                $caution_score = @(($param_a - $param_b) / ($param_c - $param_d*$param_e));
            } else {
                $caution_score = 0;
            }
            $caution_score = sprintf('%0.2f', $caution_score);
            $chart_sheet_s->setCellValueByColumnAndRow($col_pos, $row_pos, $caution_score);

            $caution_score_max = max($caution_score_max, $caution_score);
        }

        
        $col_min = count($user_attributes) + 1;
        $row_min = 2;   
        $col_max = $col_min;
        $row_max = 1 + count($sorted_rows);
        $range = SpTable::getRangeByColumnAndRow($col_min, $row_min, $col_max, $row_max, $sheet_name);
        $dataSeriesValues = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, $range, Properties::FORMAT_CODE_NUMBER, 5),
        ];
        
        //$dataSeriesValues[0]->getMarkerFillColor()
        //->setColorProperties('accent1', null, ChartColor::EXCEL_COLOR_TYPE_SCHEME);

        $col_min = 4;
        $row_min = 2;
        $col_max = 4;
        $row_max = 1 + count($sorted_rows);
        $range = SpTable::getRangeByColumnAndRow($col_min, $row_min, $col_max, $row_max, $sheet_name);
        $xAxisTickValues = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, $range, Properties::FORMAT_CODE_NUMBER, 5),
        ];

        $trend_line = new TrendLine(TrendLine::TRENDLINE_LINEAR, null, null, true, false);
        $dataSeriesValues[0]->setTrendLines([$trend_line]);
        $dataSeriesValues[0]->getTrendLines()[0]->getLineColor()->setColorProperties('accent2', null, ChartColor::EXCEL_COLOR_TYPE_SCHEME);
        $dataSeriesValues[0]->getTrendLines()[0]->setLineStyleProperties(0.5, null, Properties::LINE_STYLE_DASH_SQUARE_DOT);


        $dataSeriesValues[0]->setScatterLines(false);

        $series = new DataSeries(
            DataSeries::TYPE_SCATTERCHART,
            null, // plotGrouping
            range(0, count($dataSeriesValues) - 1), // plotOrder
            [], // plotLabel
            $xAxisTickValues, // plotCategory
            $dataSeriesValues, // plotValues
            null, // plotDirection
            false, // smooth line
            DataSeries::STYLE_LINEMARKER    // plotStyle
        );

        $layout = new Layout();
        //$layout->setShowPercent(true);
        $plotArea = new PlotArea($layout, [$series]);
               
        $legend = new Legend(Legend::POSITION_RIGHT, null, false);

        $title = new Title('Scatter Chart by Student');

        $xAxis = new ChartAxis();
        $xAxis->setAxisType(ChartAxis::AXIS_TYPE_VALUE);
        $xAxis->setAxisOptionsProperties(
            Properties::AXIS_LABELS_NEXT_TO,
            null, // horizontalCrossesValue
            null, // horizontalCrosses
            null, // axisOrientation
            Properties::TICK_MARK_OUTSIDE, // minorTmt
            Properties::TICK_MARK_OUTSIDE, // minorTmt
            0, // minimum
            ceil($caution_score_max * 2) / 2, // maximum
            0.5, // majorUnit
        );

        $yAxis = new ChartAxis();
        $yAxis->setAxisOptionsProperties(
            Properties::AXIS_LABELS_NEXT_TO,
            null,
            null,
            null,
            Properties::TICK_MARK_OUTSIDE, // minorTmt
            Properties::TICK_MARK_OUTSIDE, // minorTmt
            0,  // minimum
            1, // maximum
            0.25
        );

        $grid_line = new GridLines();
        $grid_line->getLineColor()->setColorProperties('c0c0c0', null, ChartColor::EXCEL_COLOR_TYPE_RGB);
        $grid_line->setLineStyleProperties(0.5, null, Properties::LINE_STYLE_DASH_SQUARE_DOT);
        $xAxis->setMajorGridlines($grid_line);
        $yAxis->setMajorGridlines($grid_line);

        $chart_s = new Chart(
            'chart1', // name
            $title, // title
            null, // legend
            $plotArea, // plotArea
            true, // plotVisibleOnly
            DataSeries::EMPTY_AS_GAP, // displayBlanksAs
            new Title('C.S.'), // xAxisLabel
            new Title('Accuracy'), // yAxisLabel
            $xAxis,
            $yAxis
        );

        $chart_s->setTopLeftPosition('H3');
        $chart_s->setBottomRightPosition('P24');

        $chart_sheet_s->addChart($chart_s);

        /**
         * Add Problem Scatter Chart 
         */
        // Change sheet
        $sheet_name = 'Scatter_Problem';
        $chart_sheet = new \moodle\grade\report\sptable\Spreadsheet\WorkSheet($work_book, $sheet_name);
        $sheet_id++;
        $work_book->addSheet($chart_sheet, $sheet_id);

        // Create Summary
        // Add Headers
        $row_pos = 1;
        $col_pos = 1;

        $chart_sheet->setCellValueByColumnAndRow($col_pos, $row_pos, 'Problem');
        $chart_sheet->drawCellBorder($col_pos, $row_pos, 'bottom', 'thin', '000000');
        $col_pos++;

        $chart_sheet->setCellValueByColumnAndRow($col_pos, $row_pos, get_string('accuracy', 'gradereport_sptable'));
        $chart_sheet->drawCellBorder($col_pos, $row_pos, 'bottom', 'thin', '000000');
        $col_pos++;

        $chart_sheet->setCellValueByColumnAndRow($col_pos, $row_pos, get_string('cautionscore', 'gradereport_sptable'));
        $chart_sheet->drawCellBorder($col_pos, $row_pos, 'bottom', 'thin', '000000');

        $row_pos = 2;
        $col_pos = 1;
        foreach ($q_array as $q_key=>$q_score) {
            // Add Question No.
            $chart_sheet->setCellValueByColumnAndRow($col_pos, $row_pos, 'Q'.$q_key);

            // Add Accuracy
            $chart_sheet->setCellValueByColumnAndRow($col_pos + 1, $row_pos, sprintf('%.2f', $q_score / count($u_array)));

            $q_position = $q_score;
            $param_a = 0;
            $param_b = 0;
            $param_c = 0;
            $param_d = $q_score;
            $param_e = array_sum($q_array) / count($u_array);

            $counter = 0;
            foreach ($sorted_rows as $row) {
                if ($counter < $q_position && $row['qsgrade'.$q_key] !== 100) {
                    $param_a += $row['score'];
                }

                if ($counter >= $q_position && $row['qsgrade'.$q_key] === 100) {
                    $param_b += $row['score'];
                }

                if ($counter < $q_position) {
                    $param_c += $row['score'];
                }

                $counter++;
            }
            
            if (($param_c - $param_d*$param_e) > 0) {
                $caution_score = @(($param_a - $param_b) / ($param_c - $param_d*$param_e));
            } else {
                $caution_score = 0;
            }
            $caution_score = sprintf('%0.2f', $caution_score);
            $chart_sheet->setCellValueByColumnAndRow($col_pos + 2, $row_pos, $caution_score);

            $sptable_sheet->drawCellBorder($col_pos, $row_pos, 'right', 'thin', '000000');
            
            $row_pos++;
        }

        $col_min = 2;
        $row_min = 2;
        $col_max = 2;
        $row_max = 1 + count($sorted_rows);
        $range = SpTable::getRangeByColumnAndRow($col_min, $row_min, $col_max, $row_max, $sheet_name);
        $dataSeriesValues = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, $range, Properties::FORMAT_CODE_NUMBER, 5),
        ];
        
        //$dataSeriesValues[0]->getMarkerFillColor()
        //->setColorProperties('accent1', null, ChartColor::EXCEL_COLOR_TYPE_SCHEME);

        $col_min = 3;
        $row_min = 2;
        $col_max = 3;
        $row_max = 1 + count($sorted_rows);
        $range = SpTable::getRangeByColumnAndRow($col_min, $row_min, $col_max, $row_max, $sheet_name);
        $xAxisTickValues = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, $range, Properties::FORMAT_CODE_NUMBER, 5),
        ];

        $trend_line = new TrendLine(TrendLine::TRENDLINE_LINEAR, null, null, true, false);
        $dataSeriesValues[0]->setTrendLines([$trend_line]);
        $dataSeriesValues[0]->getTrendLines()[0]->getLineColor()->setColorProperties('accent2', null, ChartColor::EXCEL_COLOR_TYPE_SCHEME);
        $dataSeriesValues[0]->getTrendLines()[0]->setLineStyleProperties(0.5, null, Properties::LINE_STYLE_DASH_SQUARE_DOT);


        $dataSeriesValues[0]->setScatterLines(false);

        $series = new DataSeries(
            DataSeries::TYPE_SCATTERCHART,
            null, // plotGrouping
            range(0, count($dataSeriesValues) - 1), // plotOrder
            [], // plotLabel
            $xAxisTickValues, // plotCategory
            $dataSeriesValues, // plotValues
            null, // plotDirection
            false, // smooth line
            DataSeries::STYLE_LINEMARKER    // plotStyle
        );

        $layout = new Layout();
        //$layout->setShowPercent(true);
        $plotArea = new PlotArea($layout, [$series]);
               
        $legend = new Legend(Legend::POSITION_RIGHT, null, false);

        $title = new Title('Scatter Chart by Student');

        $xAxis = new ChartAxis();
        $xAxis->setAxisType(ChartAxis::AXIS_TYPE_VALUE);
        $xAxis->setAxisOptionsProperties(
            Properties::AXIS_LABELS_NEXT_TO,
            null, // horizontalCrossesValue
            null, // horizontalCrosses
            null, // axisOrientation
            Properties::TICK_MARK_OUTSIDE, // minorTmt
            Properties::TICK_MARK_OUTSIDE, // minorTmt
            0, // minimum
            ceil($caution_score_max * 2) / 2, // maximum
            0.5, // majorUnit
        );

        $yAxis = new ChartAxis();
        $yAxis->setAxisOptionsProperties(
            Properties::AXIS_LABELS_NEXT_TO,
            null,
            null,
            null,
            Properties::TICK_MARK_OUTSIDE, // minorTmt
            Properties::TICK_MARK_OUTSIDE, // minorTmt
            0,  // minimum
            1, // maximum
            0.25
        );

        $grid_line = new GridLines();
        $grid_line->getLineColor()->setColorProperties('c0c0c0', null, ChartColor::EXCEL_COLOR_TYPE_RGB);
        $grid_line->setLineStyleProperties(0.5, null, Properties::LINE_STYLE_DASH_SQUARE_DOT);
        $xAxis->setMajorGridlines($grid_line);
        $yAxis->setMajorGridlines($grid_line);

        $chart = new Chart(
            'chart1', // name
            $title, // title
            null, // legend
            $plotArea, // plotArea
            true, // plotVisibleOnly
            DataSeries::EMPTY_AS_GAP, // displayBlanksAs
            new Title('C.S.'), // xAxisLabel
            new Title('Accuracy'), // yAxisLabel
            $xAxis,
            $yAxis
        );

        $chart->setTopLeftPosition('H3');
        $chart->setBottomRightPosition('P24');

        $chart_sheet->addChart($chart);

        /**
         * Add Analytics 
         */
        // Change sheet
        $analytics_sheet = new \moodle\grade\report\sptable\Spreadsheet\WorkSheet($work_book, 'Analytics');
        $sheet_id++;
        $work_book->addSheet($analytics_sheet, $sheet_id);

        $col_pos = 1;
        $row_pos = 1;

        try {
            $dc = new DefereceCoefficient($u_array, $q_array);
            $dc_data = $dc->getDetail();

            $analytics_sheet->setCellValueByColumnAndRow($col_pos, $row_pos, get_string('numberstudent', 'gradereport_sptable'));
            $analytics_sheet->setCellValueByColumnAndRow($col_pos+1, $row_pos, $dc_data['number_student']);
            $row_pos++;
            $analytics_sheet->setCellValueByColumnAndRow($col_pos, $row_pos, get_string('numberquestion', 'gradereport_sptable'));
            $analytics_sheet->setCellValueByColumnAndRow($col_pos+1, $row_pos, $dc_data['number_question']);
            $row_pos++;
            $analytics_sheet->setCellValueByColumnAndRow($col_pos, $row_pos, get_string('answerrate', 'gradereport_sptable'));
            $analytics_sheet->setCellValueByColumnAndRow($col_pos+1, $row_pos, $dc_data['anwer_rate']);
            $row_pos++;
            $analytics_sheet->setCellValueByColumnAndRow($col_pos, $row_pos, get_string('mindex', 'gradereport_sptable'));
            $analytics_sheet->setCellValueByColumnAndRow($col_pos+1, $row_pos, $dc_data['m_index']);
            $row_pos++;
            $analytics_sheet->setCellValueByColumnAndRow($col_pos, $row_pos, get_string('dbm', 'gradereport_sptable'));
            $analytics_sheet->setCellValueByColumnAndRow($col_pos+1, $row_pos, $dc_data['dbm']);
            $row_pos++;
            $analytics_sheet->setCellValueByColumnAndRow($col_pos, $row_pos, get_string('dc', 'gradereport_sptable'));
            $analytics_sheet->setCellValueByColumnAndRow($col_pos+1, $row_pos, $dc_data['dc']);

        } catch (Exception $e) {
            $analytics_sheet->setCellValueByColumnAndRow($col_pos, $row_pos, get_string('dcexception', 'gradereport_sptable'));
        }


        /**
         * Download Excel file
         */ 
        // Export
        $filename = 'sptable_' . strip_tags($this->quiz->name).'.xlsx';
        $this->_export_excel($work_book, 'sptable-'.date("Y-m-d_His").'.xlsx');
    }


    /**
     * @param object $attempt the row of data - see the SQL in display() in
     * mod/quiz/report/overview/report.php to see what fields are present,
     * and what they are called.
     * @return string the contents of the cell.
     */
    public function col_sumgrades($attempt) {
        if ($attempt->state != quiz_attempt::FINISHED) {
            return '-';
        }

        $grade = quiz_rescale_grade($attempt->sumgrades, $this->quiz);

        return $grade;
    }

    /**
     * @param string $colname the name of the column.
     * @param object $attempt the row of data - see the SQL in display() in
     * mod/quiz/report/overview/report.php to see what fields are present,
     * and what they are called.
     * @return string the contents of the cell.
     */
    public function other_cols($colname, $attempt) {
        if (!preg_match('/^qsgrade(\d+)$/', $colname, $matches)) {
            return null;
        }
        $slot = $matches[1];

        $question = $this->questions[$slot];
        if (!isset($this->lateststeps[$attempt->usageid][$slot])) {
            return '-';
        }

        $stepdata = $this->lateststeps[$attempt->usageid][$slot];
        $state = question_state::get($stepdata->state);

        if ($question->maxmark == 0) {
            $grade = '-';
        } else if (is_null($stepdata->fraction)) {
            if ($state == question_state::$needsgrading) {
                $grade = get_string('requiresgrading', 'question');
            } else {
                $grade = '-';
            }
        } else {
            $grade = $stepdata->fraction;       // Return percentage instead of real score
        }

        return $grade;
    }

    private function _export_excel(\PhpOffice\PhpSpreadsheet\Spreadsheet $work_book, string $file_name)
    {
        $file_name = addslashes($file_name);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.$file_name.'"');    // $filename
        header('Cache-Control: max-age=0');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($work_book);
        $writer->setIncludeCharts(true);
        $writer->save('php://output');
        die();
    }
}
