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

$id = optional_param('id', false, PARAM_INT);//course module id;
$groupid = optional_param('selectedgroup', false, PARAM_INT);
$startreportperiod = optional_param('startperiod', false, PARAM_INT);
$p = optional_param('peerassessment', 0, PARAM_INT);
$params = array();
if ($id) {
    $params['id']=$id;
}
if ($groupid) {
    $params['selectedgroup'] = $groupid;
}
if ($startreportperiod) {
    $params['startReportPeriod'] = $startreportperiod;
}
if ($p != 0) {
    $params['peerassessment'] = $p;
}

if ($id) {
    if (!$cm = get_coursemodule_from_id('strathpa', $id)) {
        error("Course Module ID was incorrect (1)");
    }

    if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
        error("Course is misconfigured");
    }
    if (!$peerassessment = $DB->get_record(PA_TABLE, array('id' => $cm->instance))) {
        error("Course module is incorrect");
    }

} else {

    if (! $peerassessment = $DB->get_record('strathpa', array('id' => $p))) {
        error('Course module is incorrect');
    }
    if (! $course = $DB->get_record('course', array('id'=>$peerassessment->course))) {
        error('Course is misconfigured');
    }
    if (! $cm = get_coursemodule_from_instance('strathpa', $peerassessment->id, $course->id)) {
        error('Course Module ID was incorrect (2)');
    }
    $id=$cm->id;
}
require_course_login($course, true, $cm);

$PAGE->set_url('/mod/strathpa/report.php', $params);

$context = get_context_instance(CONTEXT_MODULE, $cm->id);
require_capability('mod/strathpa:viewreport', $context);
$ratings = $DB->get_records('strathpa_ratings', array('peerassessment' => $peerassessment->id));

$group = false;
$groupmode = false;
$group_context=false;

if ($peerassessment->assignment) {
    if (!$assignment_cm = get_coursemodule_from_id('assignment', $peerassessment->assignment)) {
        die('Couldn\'t get cm for assignment');
    }
} else {
    $assignment_cm = $cm;
}

$data = data_submitted();

if ($data) {
    if (!empty($data->delete)) {
        //add_to_log('requested deletion of rating')
        //echo "deleting a rating\n";
        //if ($rating = $DB->get_records('strathpa_ratings', array('id' => $data->ratingid))) {
        if ($rating = $DB->get_records('strathpa_ratings', array('id' => $data->ratingid))) {
            if (!$DB->delete_records('strathpa_ratings', array('id' => $rating->id))) {
                notice("Could not delete rating");
            }
        } else {
            if ($rating = $DB->get_records_select('strathpa_ratings',
                    "peerassessment={$data->peerassessment} AND timemodified={$data->ratingtime} AND ratedby={$data->userid} ")
            ) {
                if (!$DB->delete_records_select('strathpa_ratings',
                        "peerassessment={$data->peerassessment}
                        AND timemodified={$data->ratingtime}
                        AND ratedby={$data->userid} ")
                ) {
                    notice("Could not delete rating");
                }
            } else {
                notice("Couldn't locate a rating for specified user");
            }
        }
        if (!($DB->delete_records('strathpa_comments', array(
                'peerassessment'=> $data->peerassessment,
                'userid' => $data->userid)
        ))) {
            notice("Could not delete comment");
        }
        strathpa_update_grades($peerassessment); //update the grade book since we've deleted some entries
        redirect($CFG->wwwroot."/mod/strathpa/report.php?selectedgroup={$groupid}&id={$id}");
    }
}

$displaygroups= array();
  //display a list of groups to display
$groups = groups_get_activity_allowed_groups($assignment_cm);
foreach ($groups as $g) {
    $displaygroups[$g->id] =$g->name;
}
ksort($displaygroups, SORT_STRING);      /*  This is a hack based on the fact that
                                            groups are created in numeric/
                                            alphabetical order.
                                        */

$members = groups_get_members($groupid);

$group = groups_get_group($groupid);

/*
if ($strathpa->frequency > PA_FREQ_ONCE) {
    // TODO (future) we have to display a list of dates

}
*/
$str_title_details = new stdClass();
$str_title_details->peerassessmentname = $peerassessment->name;
$str_title_details->groupname = '';
if ($groupid) {
    $str_title_details->groupname = $group->name;
    $str_title = get_string('peerassessmentactivityheadingforgroup','strathpa',$str_title_details);
}
$PAGE->set_title($str_title);
echo $OUTPUT->header();
$navigation = build_navigation('', $cm);
//print_header_simple(format_string($peerassessment->name), '', $navigation,
//'', '', true, '', navmenu($course, $cm));
echo $OUTPUT->heading($str_title);

$OUTPUT->heading($str_title);//get_string('peerassessmentreportheading', 'strathpa', $peerassessment));
echo '<div class="reportlink">';
print_string('displaygroup', 'strathpa');
print_report_select_form($id, $groups, $groupid);
echo '</div>';

if ($groupid) {
    switch ($peerassessment->frequency) {
        case PA_FREQ_ONCE:
            $table = strathpa_get_table_single_frequency($peerassessment, $group);
            break;
        case PA_FREQ_WEEKLY:
            $overview_table = new Stdclass;
            $overview_table->head[] = '';
            $a = array(); // average rating
            $b = array(); //average given rating
            $a[] = get_string('averageratingreceived', 'strathpa');
            $b[] = get_string('averageratinggiven', 'strathpa');
            foreach ($members as $m) {
                $overview_table->head[] = $m->lastname . ', '.$m->firstname;
                $a[] = get_average_rating_for_student($peerassessment, $m->id);
                $b[] = get_average_rating_by_student($peerassessment, $m->id);
            }
            $overview_table->data[] = $a;
            $overview_table->data[] = $b;
            print_heading(get_string('overview', 'strathpa'));
            print_table($overview_table);
            $table = strathpa_get_table_weekly_frequency($peerassessment, $group);
            print_heading(get_string('details', 'strathpa'));
            break;
        case PA_FREQ_UNLIMITED:
            $table = strathpa_get_table_unlimited_frequency($peerassessment, $group);
            break;
            break;
    }
    echo html_writer::table($table);
} else {
    echo $OUTPUT->box_start();
    echo("Please choose a group to display.");
    echo $OUTPUT->single_select(
            $CFG->wwwroot."/mod/strathpa/report.php?id={$id}&selectedgroup=",
            'reportgroupjump',
            $displaygroups,
            $groupid
    );
    echo $OUTPUT->box_end();
}

echo $OUTPUT->footer();