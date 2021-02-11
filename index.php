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

$id  = required_param('id', PARAM_INT);     // course id

if (!$course = $DB->get_record('course', ['id'=>$id])) {
    print_error('invalidcourseid');
}

require_login();
$coursecontext = context_course::instance($course->id);
require_capability('gradereport/sptable:view', $coursecontext);
//require_capability('moodle/grade:viewall', $coursecontext);

// Return tracking object
$gpr = new grade_plugin_return(['type'=>'report', 'plugin'=>'sptable', 'courseid'=>$course->id]);
$returnurl = $gpr->get_return_url(null);

//$PAGE->set_pagelayout('report');
$PAGE->set_pagelayout('admin');
$PAGE->set_url(new moodle_url('/report/sptable/index.php'), ['cid'=>$course->id]);
$PAGE->set_title(format_string($course->shortname, true, ['context' => $coursecontext]));
$PAGE->set_heading(format_string($course->fullname, true, ['context' => $coursecontext]));

// Print header menu
// /grade/<type>/<plugin>/index.php?id=2
// type: ['report', 'edit', 'import', 'export']
print_grade_page_head($course->id, 'report', 'sptable', get_string('gradebooksetup', 'grades'));

// Print Table of categories and items
echo $OUTPUT->box_start('gradetreebox generalbox');
$report = new grade_report_sptable($course->id, $gpr, $coursecontext);
echo $OUTPUT->box_end();


//echo $OUTPUT->box_start('generalbox', 'notice');
//echo html_writer::label(get_string('withselectedusers'), 'formactionid');
//$users = $DB->get_records_menu('user', null, null, 'id, username');
//echo html_writer::select($users, 'name', '', ['' => 'choosedots'], ['id' => 'formactionid']);
//echo $OUTPUT->box_end();
echo $OUTPUT->footer();
