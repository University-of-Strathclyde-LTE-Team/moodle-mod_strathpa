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
 * @package 	moodle-mod
 * @subpackage 	strathpa
 * @copyright 	2012 University of Strathclyde
 * @license    	http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */


require('../../config.php');
require_once("lib.php");
require_once($CFG->dirroot."/lib/tablelib.php");
$userid= required_param('userid', PARAM_INT);
$id = optional_param('id', false, PARAM_INT);//course module id;
$groupid = optional_param('selectedgroup', false, PARAM_INT);
$startreportperiod =optional_param('startperiod', false, PARAM_INT);

$p = optional_param('p', 0, PARAM_INT);

if ($id) {
    if (!$cm = get_coursemodule_from_id('strathpa', $id)) {
        print_error("Course Module ID was incorrect (1)");
    }

    if (!$course = $DB->get_record('course', array('id'=>$cm->course))) {
        print_error("Course is misconfigured");
    }
    if (!$peerassessment = $DB->get_record(PA_TABLE, array('id'=>$cm->instance))) {
        print_error("Course module is incorrect");
    }

} else {

    if (! $peerassessment = $DB->get_record('strathpa', array('id'=>$p))) {
        print_error('Course module is incorrect');
    }
    if (! $course = $DB->get_record('course', array('id'=>$peerassessment->course))) {
        print_error('Course is misconfigured');
    }
    if (! $cm = get_coursemodule_from_instance('strathpa', $peerassessment->id, $course->id)) {
        print_error('Course Module ID was incorrect (2)');
    }
    $id=$cm->id;
}
require_course_login($course, true, $cm);
$params = array();
if ($p) {
    $params['p']=$p;
}
$params['userid'] = $userid;
$PAGE->set_url('/mod/strathpa/comment.php', $params);

$context = context_course::instance($cm->id);
require_capability('mod/strathpa:viewreport', $context);
$ratings = $DB->get_records('strathpa_ratings', array('peerassessment'=>$peerassessment->id));

$m = $DB->get_record('user', array('id'=>$userid));


$select ="userid = {$userid} AND peerassessment={$peerassessment->id}";
$comments = $DB->get_records_select('strathpa_comments', $select);
$name = "{$m->lastname}, {$m->firstname}";// ({$m->id})";

$navigation = build_navigation('', $cm);
print_header_simple(format_string($peerassessment->name), '', $navigation,
                      '', '', true, '', navmenu($course, $cm));
//$OUTPUT->header()
echo $OUTPUT->heading(get_string('peerassessmentreportheading', 'strathpa', $peerassessment));



if ($comments) {
    $name .="<sup>";
    $table = new html_table();
    $table->head = array("Comment", 'Date');
    foreach ($comments as $comment) {
        $table->data[] = array($comment->studentcomment, userdate($comment->timemodified));
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();