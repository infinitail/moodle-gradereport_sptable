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
 * Extend grade report
 *
 * @package    grade_report_sptable
 * @author     infinitail
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once __DIR__.'/../../../config.php';
require_once $CFG->libdir.'/gradelib.php';
require_once $CFG->libdir.'/tablelib.php';
require_once $CFG->dirroot.'/grade/lib.php';
require_once __DIR__.'/lib.php';
require_once $CFG->dirroot.'/mod/quiz/report/reportlib.php';

$courseid = required_param('id', PARAM_INT);

if (!$course = $DB->get_record('course', ['id'=>$courseid])) {
    print_error('invalidcourseid');
}

require_login($course);
$coursecontext = context_course::instance($course->id);
require_capability('gradereport/sptable:view', $coursecontext);

$PAGE->set_url(new moodle_url('/grade/report/sptable/index.php'), ['id'=>$course->id]);

// Print header menu
// /grade/<type>/<plugin>/index.php?id=2
// type: ['report', 'edit', 'import', 'export']
print_grade_page_head($course->id, 'report', 'sptable');

// Print Table of categories and items
$gpr = new grade_plugin_return(['type'=>'report', 'plugin'=>'sptable', 'courseid'=>$course->id]);
$gtree = new grade_tree($course->id, false, false);

$tbody = [];
$modinfo = $gtree->modinfo;
$cminstances = $modinfo->get_instances_of('quiz');

$html  = '<table class="generaltable boxaligncenter" width="90%" cellspacing="1" cellpadding="5">';
$html .= '<tr>';
$html .= '<th class="header c0" scope="col">' . get_string('pluginname', 'quiz') . '</th>';
$html .= '<th class="header c1" scope="col">' . get_string('questions', 'quiz') . '</th>';
$html .= '<th class="header c3" scope="col">' . get_string('numberofgrades', 'grades') . '</th>';
$html .= '</tr>';

foreach ($gtree->items as $item) {
    if ($item->itemmodule !== 'quiz') {
        continue;
    }

    $quiz = $DB->get_record('quiz', ['id' => $item->iteminstance]);
    $cm = $cminstances[$item->iteminstance];
    $url = new moodle_url("/grade/report/sptable/export.php?cmid={$cm->id}");

    $html .= '<tr>';
    $html .= html_writer::tag('td', html_writer::link($url, $item->itemname));
    //$html .= '<td>' . quiz_has_grades($quiz) . '</td>';
    $html .= html_writer::tag('td', count(quiz_report_get_significant_questions($quiz)));
    $html .= html_writer::tag('td', count(quiz_get_user_grades($quiz)));
    $html .= '</tr>';
}

$html .= '</table>';

echo $html;

echo $OUTPUT->footer();
