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
 * @package    gradereport_sptable
 * @author     infinitail
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once $CFG->libdir.'/tablelib.php';
require_once $CFG->dirroot.'/mod/quiz/locallib.php';
require_once $CFG->dirroot.'/mod/quiz/report/default.php';
require_once $CFG->dirroot.'/mod/quiz/report/overview/report.php';
require_once $CFG->dirroot.'/grade/report/lib.php';

/**
 * Class providing an API for the user report building and displaying.
 * @uses grade_report
 * @package gradereport_user
 */
class grade_report_sptable extends grade_report {

    protected $tabledata = [];
    protected $tablecolumns = [];
    protected $tableheaders = [];

    public function __construct($courseid, $gpr, $context, $page=null)
    {
        parent::__construct($courseid, $gpr, $context, $page);

        $this->gtree = new grade_tree($courseid, false, false);

        $this->setup_table_header();
        $this->setup_table_body();
    }

    /**
     * Prepares the headers and attributes of the flexitable.
     */
    private function setup_table_header()
    {
        /*
         * Table has 1-8 columns
         *| All columns except for itemname/description are optional
         */

        // setting up table headers

        $this->tablecolumns = ['itemname'];
        $this->tableheaders = [$this->get_lang_string('gradeitem', 'grades')];

        $this->tablecolumns[] = 'weight';
        $this->tableheaders[] = $this->get_lang_string('weightuc', 'grades');

        $this->tablecolumns[] = 'grade';
        $this->tableheaders[] = $this->get_lang_string('grade', 'grades');
    }

    private function setup_table_body()
    {
        $tbody = [];
        $modinfo = $this->gtree->modinfo;
        $cminstances = $modinfo->get_instances_of('quiz');

        echo html_writer::start_tag('ul');

        foreach ($this->gtree->items as $item) {
            if ($item->itemmodule !== 'quiz') {
                continue;
            }

            $cmid = $cminstances[$item->iteminstance]->id;
            $url = new moodle_url("/grade/report/sptable/export.php?cmid={$cmid}");
            $link = html_writer::link($url, $item->itemname);
            echo html_writer::tag('li', $link);
        }

        echo html_writer::end_tag('ul');
    }

    public function process_data($data) {}

    public function process_action($target, $action) {}
}