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
 *
 *
 * @package    gradereport_sptable
 * @author     infinitail
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once __DIR__.'/../../../config.php';
require_once __DIR__.'/lib.php';
require_once $CFG->libdir.'/gradelib.php';
require_once $CFG->libdir.'/tablelib.php';
require_once $CFG->dirroot.'/grade/lib.php';
require_once $CFG->dirroot.'/mod/quiz/report/reportlib.php';

$cmid = required_param('cmid', PARAM_INT);

if (!$cm = get_coursemodule_from_id('quiz', $cmid)) {
    print_error('invalidcoursemodule');
}

if (!$course = $DB->get_record('course', ['id' => $cm->course])) {
    print_error('invalidcourseid');
}
require_course_login($course);

if (!$quiz = $DB->get_record('quiz', ['id' => $cm->instance])) {
    print_error('invalidquizid', 'quiz');
}
// Throw Error if quiz has no question
if (quiz_has_questions($quiz->id) === false) {
    print_error('invalidquizid', 'quiz');
}

$PAGE->set_url(new moodle_url('/grade/report/sptable/table.php', ['cmid' => $cmid]));
$coursecontext = context_course::instance($course->id);
require_capability('gradereport/sptable:view', $coursecontext);
$PAGE->set_context($coursecontext);

// Get questions in Quiz
//$questions = quiz_report_get_significant_questions($quiz);
//echo '<pre>'; var_dump($questions); echo '</pre>';

//$context = context_module::instance($cm->id);

$sptable = new quiz_attempts_report_sptable();
$sptable->display($quiz, $cm, $course);
