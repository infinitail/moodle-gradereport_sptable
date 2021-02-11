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
require_once $CFG->libdir.'/phpexcel/PHPExcel.php';

// Related classes
// https://github.com/moodle/moodle/blob/master/mod/quiz/report/overview/overview_table.php
// https://github.com/moodle/moodle/blob/master/mod/quiz/report/attemptsreport.php
// https://github.com/moodle/moodle/blob/master/mod/quiz/report/default.php
// https://github.com/moodle/moodle/blob/master/lib/tablelib.php

class quiz_overview_sptable extends quiz_overview_table {
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

        //echo '<pre>'; var_dump($this->rawdata); echo '</pre>';
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

        //echo '<pre>'; var_dump($score_matrix); echo '</pre>';

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

        arsort($u_array);
        arsort($q_array);
        $u_order = array_keys($u_array);
        $q_order = array_keys($q_array);

        $sorted_rows = [];
        foreach ($u_order as $u_id=>$u_key) {
            preg_match('/\/user\/view.php\?id=([0-9]+)&/', $score_rows[$u_key]['fullname'], $matches);
            $sorted_rows[$u_id]['userid'] = $matches[1];
            //$sorted_rows[$u_id]['fullname'] = $score_rows[$u_key]['fullname'];
            $sorted_rows[$u_id]['score'] = $u_array[$u_key];

            foreach ($q_order as $q_id=>$q_key) {
                //$sorted_rows[$u_id]['qsgrade'.$q_key] = $score_rows[$u_key]['qsgrade'.$q_key];
                $sorted_rows[$u_id]['qsgrade'.$q_key] = $score_matrix[$u_key][$q_key];
            }
        }

        //echo '<pre>'; var_dump($q_array); echo '</pre>';
        //echo '<pre>'; var_dump($sorted_rows); echo '</pre>'; die();

        // Prepare to export Excel file
        $book = new PHPExcel;
        $sheet = $book->getActiveSheet();

        // Define variables
        define('POSITION_HEADER_ROW', 1);

        $thin_black_bottom_bordered_cell = [
            'borders' => [
                'bottom' => [
                    'style' => PHPExcel_Style_Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ];

        $thin_black_left_bordered_cell = [
            'borders' => [
                'left' => [
                    'style' => PHPExcel_Style_Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ];

        $thin_red_top_bordered_cell = [
            'borders' => [
                'top' => [
                    'style' => PHPExcel_Style_Border::BORDER_THIN,
                    'color' => ['rgb' => 'FF0000']
                ]
            ]
        ];

        $thin_red_left_bordered_cell = [
            'borders' => [
                'left' => [
                    'style' => PHPExcel_Style_Border::BORDER_THIN,
                    'color' => ['rgb' => 'FF0000']
                ]
            ]
        ];

        $dashdot_blue_bottom_bordered_cell = [
            'borders' => [
                'bottom' => [
                    'style' => PHPExcel_Style_Border::BORDER_DASHDOT,
                    'color' => ['rgb' => '0000FF']
                ]
            ]
        ];

        $dashdot_blue_left_bordered_cell = [
            'borders' => [
                'left' => [
                    'style' => PHPExcel_Style_Border::BORDER_DASHDOT,
                    'color' => ['rgb' => '0000FF']
                ]
            ]
        ];

        $user_attributes = [
            'fullname',
            'idnumber',
        ];

        // Add sheet header
        for ($i=0; $i<=count($user_attributes); $i++) {
            $sheet->setCellValueByColumnAndRow($i, POSITION_HEADER_ROW, get_string($user_attributes[$i]))
                ->getStyleByColumnAndRow($i, POSITION_HEADER_ROW)
                ->applyFromArray($thin_black_bottom_bordered_cell);
        }

        foreach ($q_order as $key=>$value) {
            $sheet->setCellValueByColumnAndRow(count($user_attributes) + $key, POSITION_HEADER_ROW, "Q{$value}")
                ->getStyleByColumnAndRow($key + count($user_attributes), POSITION_HEADER_ROW)
                ->applyFromArray($thin_black_bottom_bordered_cell);
        }

        $sheet->setCellValueByColumnAndRow(count($user_attributes) + $key + 2, POSITION_HEADER_ROW, get_string('score', 'gradereport_sptable'))
            ->getStyleByColumnAndRow(count($user_attributes) + $key +2, POSITION_HEADER_ROW)
            ->applyFromArray($thin_black_bottom_bordered_cell);

        $sheet->setCellValueByColumnAndRow(count($user_attributes) + $key + 3, POSITION_HEADER_ROW, get_string('accuracy', 'gradereport_sptable'))
            ->getStyleByColumnAndRow(count($user_attributes) + $key +3, POSITION_HEADER_ROW)
            ->applyFromArray($thin_black_bottom_bordered_cell);

        $sheet->getStyleByColumnAndRow(count($user_attributes), POSITION_HEADER_ROW)
            ->applyFromArray($thin_black_left_bordered_cell);

        $prev_score = 0;
        foreach ($sorted_rows as $row_number=>$row) {
            $user = core_user::get_user($row['userid']);

            foreach ($user_attributes as $key=>$attribute) {
                $cell_value = ($attribute === 'fullname') ? fullname($user) : $user->{$attribute};
                $sheet->setCellValueByColumnAndRow($key, $row_number + 2, $cell_value);
            }
            $sheet->getStyleByColumnAndRow(count($user_attributes), $row_number + 2)
                ->applyFromArray($thin_black_left_bordered_cell);

            // Fill question score
            $col_number = 0;
            foreach ($row as $key=>$value) {
                if (substr($key, 0, 7) !== 'qsgrade') {
                    continue;
                }

                $slot = substr($key, 7);
                $score = (int) floor($value / 100);
                $sheet->setCellValueByColumnAndRow($col_number + count($user_attributes), $row_number + 2, $score);
                $col_number++;
            }

            // Fill user total score
            $sheet->setCellValueByColumnAndRow($col_number + count($user_attributes) + 1, $row_number + 2, $row['score']);

            // Fill user average score
            $sheet->setCellValueByColumnAndRow($col_number + count($user_attributes) + 2, $row_number + 2,
                sprintf('%0.2f', $row['score']/count($q_array)));

            // Draw score line
            $sheet->getStyleByColumnAndRow($row['score'] + count($user_attributes), $row_number + 2)
                ->applyFromArray($thin_red_left_bordered_cell);

            if ($prev_score > $row['score']) {
                for ($i=$prev_score; $i>$row['score']; $i--) {
                    $sheet->getStyleByColumnAndRow($i + count($user_attributes) - 1, $row_number + 2)
                        ->applyFromArray($thin_red_top_bordered_cell);
                }
            }
            $prev_score = $row['score'];
        }

        // Fill question total score
        $sheet->setCellValueByColumnAndRow(count($user_attributes) - 1, $row_number + 4, get_string('rightanswers', 'gradereport_sptable'));
        $sheet->setCellValueByColumnAndRow(count($user_attributes) - 1, $row_number + 5, get_string('accuracy', 'gradereport_sptable'));
        $col_counter = 0;
        foreach ($q_array as $score) {
            $sheet->setCellValueByColumnAndRow(count($user_attributes) + $col_counter, $row_number + 4, $score);
            // Fill user average score
            $sheet->setCellValueByColumnAndRow(count($user_attributes) + $col_counter, $row_number + 5,
                sprintf('%0.2f', $score/count($u_array)));

            $col_counter++;
        }
        $sheet->setCellValueByColumnAndRow(count($user_attributes) + $col_counter + 1, $row_number + 4, array_sum($q_array))
            ->getStyleByColumnAndRow(count($user_attributes), $row_number + 4)
            ->applyFromArray($thin_black_left_bordered_cell);

        $sheet->getStyleByColumnAndRow(count($user_attributes), $row_number + 5)
            ->applyFromArray($thin_black_left_bordered_cell);

        // Draw problem line
        $prev_score = null;
        $col_counter = 0;
        foreach ($q_array as $score) {
            $sheet->getStyleByColumnAndRow(count($user_attributes) + $col_counter, $score + 1)
                ->applyFromArray($dashdot_blue_bottom_bordered_cell);

            if (!is_null($prev_score) && $prev_score > $score) {
                for ($i=$prev_score; $i>$score; $i--) {
                    $sheet->getStyleByColumnAndRow(count($user_attributes) + $col_counter , $i + 1)
                        ->applyFromArray($dashdot_blue_left_bordered_cell);
                }
            }

            $col_counter++;
            $prev_score = $score;
        }

        // Add Student Attention Score (attention_score = (param_a - param_b)/(param_c - param_d * param_e))
        $col_position = count($q_array) + 3 + count($user_attributes);
        $sheet->setCellValueByColumnAndRow($col_position, POSITION_HEADER_ROW, get_string('cautionscore', 'gradereport_sptable'))
            ->getStyleByColumnAndRow($col_position, POSITION_HEADER_ROW)
            ->applyFromArray($thin_black_bottom_bordered_cell);
        foreach ($sorted_rows as $row_number=>$row) {
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

            $attention_score = ($param_a - $param_b) / ($param_c - $param_d*$param_e);
            $attention_score = sprintf('%0.2f', $attention_score);
            $sheet->setCellValueByColumnAndRow($col_position, $row_number + 2, $attention_score);
        }

        // Add Question Attention Score (attention_score = (param_a - param_b)/(param_c - param_d * param_e))
        $row_position = count($u_array) + 5;
        $col_position = count($user_attributes);
        $sheet->setCellValueByColumnAndRow($col_position - 1, $row_position, get_string('cautionpoint', 'gradereport_sptable'))
            ->getStyleByColumnAndRow($col_position, $row_position)
            ->applyFromArray($thin_black_left_bordered_cell);
        foreach ($q_array as $q_key=>$q_score) {
            $q_position = $q_score;

            $param_a = 0;
            $param_b = 0;
            $param_c = 0;
            $param_d = $q_score;
            $param_e = array_sum($q_array) / count($u_array);

            $counter = 0;
            foreach ($sorted_rows as $row) {

            //echo '<pre>'; var_dump($counter); echo '</pre>';

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

            $attention_score = ($param_a - $param_b) / ($param_c - $param_d*$param_e);
            $attention_score = sprintf('%0.2f', $attention_score);
            $sheet->setCellValueByColumnAndRow($col_position, $row_position, $attention_score);
            $col_position++;
        }

        // Download Excel file
        $filename = 'sptable_' . strip_tags($this->quiz->name);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
        header('Cache-Control: max-age=0');
        $writer = PHPExcel_IOFactory::createWriter($book, 'Excel2007');
        $writer->save('php://output');
        die();
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
            //$grade = $stepdata->fraction;       // Return percentage instead of real score
            $grade = quiz_rescale_grade(
                    $stepdata->fraction * $question->maxmark, $this->quiz, 'question');
        }

        return $grade;
    }
}