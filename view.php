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
require_once('pagelib.php');

require_once($CFG->dirroot.'/lib/grouplib.php');

$id = optional_param('id', 0, PARAM_INT);      //course module id;
$p = optional_param('p', 0, PARAM_INT);     /*  allows this page to work if we get
                                              the peer assessment id rather than
                                              cmid.
                                          */

if ($id) {
    if (!$cm = get_coursemodule_from_id('strathpa', $id)) {
        error("Course Module ID was incorrect");
    }

    if (!$course = $DB->get_record('course', array('id'=>$cm->course))) {
        error("Course is misconfigured");
    }

    if (!$peerassessment =  $DB->get_record(PA_TABLE, array('id'=>$cm->instance))) {
        error("Course module is incorrect");
    }

} else {

    if (! $peerassessment =  $DB->get_record('strathpa', array('id'=>$p))) {
        print_error('Course module is incorrect');
    }
    if (! $course = $DB->get_record('course', array('id'=>$peerassessment->course))) {
        print_error('Course is misconfigured');
    }
    if (! $cm = get_coursemodule_from_instance('strathpa', $peerassessment->id, $course->id)) {
        print_error('Course Module ID was incorrect');
    }
    $id=$cm->id;
}

require_course_login($course, true, $cm);

$context = context_module::instance($cm->id);

// show some info for guests
if (isguestuser()) {
    $navigation = build_navigation('', $cm);
    echo $OUTPUT->header(format_string($peerassessment->name));

    $wwwroot = $CFG->wwwroot.'/login/index.php';
    if (!empty($CFG->loginhttps)) {
        $wwwroot = str_replace('http:', 'https:', $wwwroot);
    }

    notice_yesno(get_string('noguests', 'chat').'<br /><br />'.get_string('liketologin'),
            $wwwroot, $CFG->wwwroot.'/course/view.php?id='.$course->id);

    echo $OUTPUT->footer($course);
    exit;

}

$alreadycompleted = false;

$comparetime = time();
switch($peerassessment->frequency) {
    case PA_FREQ_ONCE:
        //find out if the user has completed this acitivy AT ALL
        if (
            $ratings = $DB->get_records_select('strathpa_ratings',
            "ratedby = {$USER->id} AND peerassessment={$peerassessment->id}")
        ) {
            $alreadycompleted = PA_COMPLETED;
        }
        break;
    case PA_FREQ_WEEKLY:
        $oneweekago =$comparetime - PA_ONE_WEEK;
        //find out if the user has completed this acitivy within the last week
        if ($ratings = $DB->get_records_select('strathpa_ratings',
                "timemodified>{$oneweekago} AND peerassessment={$peerassessment->id}")
        ) {
            //we've got a rating record(s) that are were modified more recenly than a week ago
            $alreadycompleted = PA_COMPLETED_THIS_WEEK;
        }
        break;
    case PA_FREQ_UNLIMITED:
    break;
}


$data = data_submitted();
if ($data) {
    if (!empty($data->cancel)) {
        //user clicked on the cancel button;
        redirect($CFG->wwwroot.'/course/view.php?id='.$course->id);
        exit();
    }
    require_capability('mod/strathpa:recordrating', $context);
    if ($alreadycompleted && $peerassessment->canedit) {
        //we probably have to do an update on each of the existing ratings
        $comments = $data->comments;
        $submittime = time();
        foreach ((array)$data as $name => $value) {

            if (substr(strtolower($name), 0, 7) =='rating_') {
                //have a user rating
                //fetch the existing rating
                $userid = substr($name, 7);
                $select = "SELECT *
                             FROM {$CFG->prefix}strathpa_ratings
                            WHERE peerassessment = {$peerassessment->id}
                              AND ratedby = {$USER->id} AND userid={$userid}";
                $ratings = $DB->get_records_sql($select);
                if ($ratings === false) {
                    //we have a form but for a user that we' haven't got a previous rating for so we need to insert it.
                    echo 'Inserting rating for a new member to group.';
                    $ins = new stdClass;
                    $ins->ratedby = $USER->id;
                    $ins->peerassessment = $peerassessment->id;
                    $ins->userid=$userid;
                    $ins->rating = $value;
                    $ins->timemodified = $submittime;
                    if (!$result = $DB->insert_record('strathpa_ratings', $ins)) {
                        $PAGE->set_url('/mod/strathpa/view.php');
                        echo $OUTPUT->header();
                        print_error('failedtosaverating', 'strathpa');
                        echo $OUTPUT->footer();
                        exit();
                    }
                } else {
                    echo 'Updating existing';
                    foreach ($ratings as $rating) {

                        $ins = new stdClass;
                        $ins->id = $rating->id;
                        $ins->rating = $value;
                        $ins->timemodified = $submittime;
                        if (!$result = $DB->update_record('strathpa_ratings', $ins)) {
                            $PAGE->set_url('/mod/strathpa/view.php');
                            echo $OUTPUT->header();
                            print_error('failedtosaverating', 'strathpa');
                            echo $OUTPUT->footer();
                            exit();
                        }
                    }
                }
            }
        }
        if ($comments !='') {
            $co = $DB->get_record('strathpa_comments', array('userid' => $USER->id, 'peerassessment' => $peerassessment->id));
            $co->timemodified= $submittime;
            $co->studentcomment=$comments;
            if (!$DB->update_record('strathpa_comments', $co)) {
                $PAGE->set_url('/mod/strathpa/view.php');
                echo $OUTPUT->header();
                print_error('failedtosaverating', 'strathpa');
                echo $OUTPUT->footer();
                exit();
            }
        }
    } else if (!$alreadycompleted) {
        $comments = $data->comments;            //TODO WE NEED SAVE THIS
        $success = true;
        $submittime = time();// so the ratings are all at the same time
        foreach ((array)$data as $name => $value) {
            if (substr(strtolower($name), 0, 7) == 'rating_') {
                //have a user rating
                $userid = substr($name, 7);
                $ins = new stdClass;
                $ins->ratedby = $USER->id;
                $ins->peerassessment = $peerassessment->id;
                $ins->userid=$userid;
                $ins->rating = $value;
                $ins->timemodified = $submittime;
                //$ins->studentcomment  = $comments;  //this will overwrite
                $result = $DB->insert_record('strathpa_ratings', $ins);
                if (!$result) {
                    $PAGE->set_url('/mod/strathpa/view.php');
                    echo $OUTPUT->header();
                    print_error('failedtosaverating', 'strathpa');
                    echo $OUTPUT->footer();
                    exit();
                }
            }
        }
        if ($comments !='') {
            $co = new stdClass;
            $co->userid=$USER->id;
            $co->peerassessment=$peerassessment->id;
            $co->timecreated= $submittime;
            $co->timemodified= $submittime;
            $co->studentcomment=$comments;
            if (!$co_result = $DB->insert_record('strathpa_comments', $co)) {
                $PAGE->set_url('/mod/strathpa/view.php');
                echo $OUTPUT->header();
                print_error('failedtosaverating', 'strathpa');
                echo $OUTPUT->footer();
                exit();
            }
        }
    }
    /*
     * else we are already completed but can't edit we really shoulnd't do anything
    */
    strathpa_update_grades($peerassessment);
    //mark as completed
    $completion = new completion_info($course);
    //get the cm for the peer assessment
    $completion_cm = get_coursemodule_from_instance('strathpa', $peerassessment->id);
    $completion->set_module_viewed($completion_cm);
    redirect($CFG->wwwroot."/course/view.php?id={$course->id}");
    exit();
}


$params = array();
$PAGE->set_title($course->shortname.': ' .$course->fullname);
$PAGE->set_url('/mod/strathpa/view.php', $params);
//check what frequency this is running at and if it should be displayed for the user.
$ratings = false;
echo $OUTPUT->header();

add_to_log($course->id, 'strathpa', 'view', "view.php?id=$cm->id", $peerassessment->id, $cm->id);

// Initialize $PAGE, compute blocks

$assignment_cm = false;
$group = false;
$groupmode = false;
$group_context=false;
if ($peerassessment->assignment) {
    if (!$assignment_cm = get_coursemodule_from_id('assignment', $peerassessment->assignment)) {
        die('Couldn\'t get cm for assignment');
    }
    $groupmode = groups_get_activity_groupmode($assignment_cm, $course);
    $groupid = groups_get_activity_group($assignment_cm);
    $group_context = get_context_instance(CONTEXT_MODULE, $assignment_cm->id);
    if ($groupmode == NOGROUPS) {
        if (has_capability('moodle/course:manageactivities', $context, $USER->id)) {
            notice(get_string('associatedactivitynogroupsstaff', 'strathpa'));
        } else {
            notice(get_string('associatedactivitynogroups', 'strathpa'));
        }
        echo $OUTPUT->footer($course);
    }
} else {
    $groupmode = groups_get_activity_groupmode($cm);
    $groupid = groups_get_activity_group($cm, true);
    $group_context = get_context_instance(CONTEXT_MODULE, $cm->id);

}

$canrecordrating = has_capability('mod/strathpa:recordrating', $context, $USER->id);
$canviewreport = has_capability('mod/strathpa:viewreport', $context, $USER->id);

if (!$group = groups_get_group($groupid)  ) {
    if (!$canviewreport) {
        notice(get_string('nogroup', 'strathpa'));

        echo $OUTPUT->footer($course);
        exit();
    }
}
/*
 *  else we couldn't get a group
 *  This could be due to the fact that we've got "DOANYTHING" rights
 *  or we're just not in a group
*/
if (!$canrecordrating & !$canviewreport) {
    $a = new stdClass;
    $a->id = $cm->id;
    notice(get_string('mustbestudent', 'strathpa', $cm->id));
    echo $OUTPUT->footer($course);
    exit();
}

if ($members = groups_get_members($groupid)) {
    if (is_array($members) && !in_array($USER->id, array_keys($members))) {//$USER->id)) {
        notice(get_string('usernotactuallyingroup', 'strathpa'));
        add_to_log($course->id, '
                strathpa',
                'rate other',
                "",
                "Attempted to record attempt for group user wasn't a member of.",
                $cm->id
        );
        echo $OUTPUT->footer($course);
        exit();
    }
}
if ($group) {
    $a = new stdClass();
    $a->peerassessmentname = $peerassessment->name;
    $a->groupname = "";
    if (substr(strtolower($group->name), 0, 6) == 'group ') {
        $a->groupname =substr($group->name, 6);
    } else {
        $a->groupname =$group->name;
    }
    echo $OUTPUT->heading(get_string('peerassessmentactivityheadingforgroup', 'strathpa', $a));
} else {
    echo $OUTPUT->heading(get_string('modulename', 'strathpa'));
}

$groups = groups_get_activity_allowed_groups($cm);
echo '<table id="layout-table"><tr>';
$lt = (empty($THEME->layouttable)) ? array('left', 'middle', 'right') : $THEME->layouttable;
foreach ($lt as $column) {
    switch ($column) {
        case 'left':

          break;
        case 'middle':
          {
            if ($peerassessment->intro != '') {
                echo $OUTPUT->box_start();
                echo format_text($peerassessment->intro, $peerassessment->introformat);
                echo $OUTPUT->box_end();
            }
            if ($canviewreport) {
                if (!$group) {
                    echo $OUTPUT->box_start();
                    print_report_select_form($id, $groups, $groupid);
                    echo $OUTPUT->box_end();
                } else {
                    echo '<div class="reportlink">';
                    print_report_select_form($id, $groups, $groupid);
                    echo '</div>';
                }
            }
            $editresponses = $alreadycompleted && $peerassessment->canedit;
            switch($alreadycompleted) {
                case PA_COMPLETED:
                    if ($peerassessment->canedit) {
                        echo $OUTPUT->box(get_string('alreadycompletedcanedit', 'strathpa'));
                    } else {
                        notice(get_string('alreadycompleted', 'strathpa'));
                    }
                    break;
                case PA_COMPLETED_THIS_WEEK:
                    if ($peerassessment->canedit) {
                        echo $OUTPUT->box(get_string('notenoughtimepassedcanedit', 'strathpa'));
                    } else {
                        notice(get_string('notenoughtimepassed', 'strathpa'));
                    }
                    break;
            }
            $co = false;
            if (!$alreadycompleted | $editresponses) {
                $co = $DB->get_record('strathpa_comments',
                        array(
                                'userid' => $USER->id,
                                'peerassessment' => $peerassessment->id
                                )
                );
                //check that the opening / due times are still OK
                $ctime = time();
                if ($peerassessment->timeavailable!=0 &&
                    $peerassessment->timeavailable > time() &&
                    !has_capability('mod/strathpa:viewreport', $context)
                ) {
                    //and !has_capability('mod/assignment:grade', $this->context)      // grading user can see it anytime
                    //and $this->assignment->var3) {                                   // force hiding before available date
                    print_simple_box_start('center', '', '', 0, 'generalbox', 'intro');
                    print_string('notavailableyet', 'strathpa');
                    print_simple_box_end();
                } else if (
                        $peerassessment->timedue!=0
                        && $peerassessment->timedue < time()
                        && !has_capability('mod/strathpa:viewreport', $context)
                ) {
                    print_simple_box_start('center', '', '', 0, 'generalbox', 'intro');
                    print_string('expired', 'strathpa');
                    print_simple_box_end();
                } else {
                    if ($canrecordrating && $group) {
                        print_container_start();
                        echo '<form  method="post">';
                        echo "<input type='hidden' name='cmid' value='{$cm->id}'/>";
                        echo '<table id="members">';
                        $strlo = get_string('low', 'strathpa');
                        $strho = get_string('high', 'strathpa');
                        $strname = get_string('name', 'strathpa');
                        $strcommenthelp = get_string('commenthelp', 'strathpa');
                        echo "<tr><th></th><th>$strlo</th><th></th><th/><th></th><th colspan='2'>$strho</th></tr>";
                        echo "<tr><th>$strname</th><th>1</th><th>2</th><th>3</th><th>4</th><th>5</th></tr>";
                        if ($members) {
                            foreach ($members as $user) {
                                if (!has_capability('mod/strathpa:recordrating', $context, $user->id)) {
                                    continue;
                                }
                                echo "<tr class='strathpa_row'>";
                                echo '<td>';
                                echo "<a href='{$CFG->wwwroot}/user/view.php?id={$user->id}' target='_blank'>";
                                if ($user->id == $USER->id) {
                                    echo "<strong>{$user->lastname}, {$user->firstname}</strong>";
                                } else {
                                    echo "{$user->lastname}, {$user->firstname}";
                                }
                                echo "</a>";
                                echo '</td>';
                                if ($editresponses) {
                                    $select = "peerassessment={$peerassessment->id}
                                            AND ratedby ={$USER->id}
                                            AND userid={$user->id}";
                                    $lastratingtime = $DB->get_field_select(
                                            'strathpa_ratings',
                                            'max(timemodified) as Timestamp',
                                            $select
                                    );
                                    $previousresponses = false;
                                    if ($lastratingtime !='') {
                                        $previousresponses = $DB->get_records_select(
                                                'strathpa_ratings',
                                                $select . " AND timemodified =$lastratingtime"
                                        );
                                    }
                                    if ($previousresponses !== false) {
                                        foreach ($previousresponses as $prev) {
                                            echo "<td class='strathpa_center'>";
                                            echo "<input type='radio' name='rating_{$prev->userid}' ";
                                            if ($prev->rating == 1) {
                                                echo 'checked ';
                                            }
                                            echo "value='1'></td>";
                                            echo "<td class='strathpa_center'>";
                                            echo "<input type='radio' name='rating_{$prev->userid}' ";
                                            if ($prev->rating == 2) {
                                                echo 'checked ';
                                            }
                                            echo "value='2'></td>";
                                            echo "<td class='strathpa_center'>";
                                            echo "<input type='radio' name='rating_{$prev->userid}' ";
                                            if ($prev->rating == 3) {
                                                echo 'checked ';
                                            }
                                            echo "value='3'></td>";
                                            echo "<td class='strathpa_center'>";
                                            echo "<input type='radio' name='rating_{$prev->userid}' ";
                                            if ($prev->rating == 4) {
                                                echo 'checked ';
                                            }
                                            echo "value='4'></td>";
                                            echo "<td class='strathpa_center'>";
                                            echo "<input type='radio' name='rating_{$prev->userid}' ";
                                            if ($prev->rating == 5) {
                                                echo 'checked ';
                                            }
                                            echo "value='5'></td>";
                                        }
                                    } else {
                                        echo "<td class='strathpa_center'>";
                                        echo "<input type='radio' name='rating_{$user->id}' value='1'></td>";
                                        echo "<td class='strathpa_center'>";
                                        echo "<input type='radio' name='rating_{$user->id}' value='2'></td>";
                                        echo "<td class='strathpa_center'>";
                                        echo "<input type='radio' name='rating_{$user->id}' value='3'></td>";
                                        echo "<td class='strathpa_center'>";
                                        echo "<input type='radio' name='rating_{$user->id}' value='4'></td>";
                                        echo "<td class='strathpa_center'>";
                                        echo "<input type='radio' name='rating_{$user->id}' value='5'></td>";
                                        echo "<td class='strathpa_center'>*</td>";
                                    }
                                } else {
                                    echo "<td class='strathpa_center'>";
                                    echo "<input type='radio' name='rating_{$user->id}' value='1'></td>";
                                    echo "<td class='strathpa_center'>";
                                    echo "<input type='radio' name='rating_{$user->id}' value='2'></td>";
                                    echo "<td class='strathpa_center'>";
                                    echo "<input type='radio' name='rating_{$user->id}' value='3'></td>";
                                    echo "<td class='strathpa_center'>";
                                    echo "<input type='radio' name='rating_{$user->id}' value='4'></td>";
                                    echo "<td class='strathpa_center'>";
                                    echo "<input type='radio' name='rating_{$user->id}' value='5'></td>";
                                }
                                echo '</tr>';
                            }
                        } else {
                            echo "<tr><td>".get_string('nomembersfound', 'strathpa').'</td></tr>';
                        }
                        echo "<tr><th colspan='6'>Comments</th></tr>";
                        echo "<tr><td colspan='6'>$strcommenthelp</td></tr>";

                        echo "<tr><td colspan='6'>";
                        echo "<textarea name='comments' rows='5' columns='40' class='strathpa_fullwidth'>";
                        //really should display existing comment
                        if ($co) {
                            echo $co->studentcomment;
                        }
                        echo "</textarea></td></tr>";
                        echo "<tr><th colspan='6'>";
                        echo "<input type='submit' value='Save'/>";
                        echo "<input type='submit' name='cancel' value='Cancel'/>";
                        echo "</td></tr>";
                        echo '</table>';
                        echo '</form>';
                        print_container_end();
                    } else {
                        if (!$group) {
                            echo $OUTPUT->box(get_string('nogroup', 'strathpa'));
                        }
                    }
                }
            }
        } echo '<!--end middle//-->';
        break;
        case 'right':
            break;
    }
}
echo '</tr></table>';//should fix #1009
echo $OUTPUT->footer($course);