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


define('PA_TABLE', 'strathpa');
define('PA_FREQ_ONCE', 0);
define('PA_FREQ_WEEKLY', 1);
define('PA_FREQ_UNLIMITED', 2);
define('PA_COMPLETED', 1);
define('PA_COMPLETED_THIS_WEEK', 2);
define('PA_UPPER_THRESHOLD', 3.5);
define('PA_LOWER_THRESHOLD', 2.5);
/**
 *  One week in seconds!
 **/
define('PA_ONE_WEEK', 604800);
//define('PA_ONE_WEEK',60*60*24); // 5 seconds (for testing only!)
/**
 * This function enables our "test role" to work
 *
 * Returns a list of "types" which are displayed in the add fields.
 */
function strathpa_get_types() {
    global $DB, $USER, $COURSE;
    $context =  get_context_instance(CONTEXT_COURSE, $COURSE->id);
    //if (has_capability('mod/strathpa:usepeerassessment', $context)) {
    $type = new stdclass;
    $type->modclass=MOD_CLASS_ACTIVITY;
    $type->type='strathpa';
    $type->typestr=get_string('fullmoduleame', 'strathpa');
    return array($type);
    //}
    return array();
}

function strathpa_add_instance($pa) {
    global $DB, $USER;
    if (!$returnid = $DB->insert_record('strathpa', $pa)) {
        return false;
    }
    $pa->id=$returnid;
    strathpa_grade_item_update($pa);
    return $returnid;
}

function strathpa_update_instance($pa) {
    global $DB;
    $pa->id = $pa->instance;
    unset($pa->introformat);
    if (!$returnid = $DB->update_record('strathpa', $pa)) {
        return false;
    }
    strathpa_grade_item_update($pa);
    return $returnid;
}

function strathpa_delete_instance($id) {
    global $DB;
    if (! $pa = $DB->get_record('strathpa', array('id'=>$id))) {
        return false;
    }
    $result = true;
    if (! $DB->delete_records('strathpa', array('id'=>$pa->id))) {
        $result = false;
    }
    if (! $DB->delete_records('strathpa_ratings', array('strathpa'=>$pa->id))) {
        $result = false;
    }
    if ($events = $DB->get_records_select('event', "modulename = 'strathpa' and instance = '{$pa->id}'")) {
        foreach ($events as $event) {
            delete_event($event->id);
        }
    }
    strathpa_grade_item_delete($pa);
    return $result;
}
function strathpa_get_user_grades($pa, $userid=0) {

    global $CFG, $DB;
    $user = $userid ? "AND userid=$userid" :"";

    $sql = "SELECT userid as userid, AVG(rating) AS rawgrade FROM ";
    $sql .="{$CFG->prefix}strathpa_ratings WHERE peerassessment={$pa->id} ";
    $sql .="$user GROUP BY userid";

    //TODO we still need to sling the comment into the grade object.

    $grades = $DB->get_records_sql($sql);
    $i=0;
    return $grades;
}

$pugcounter = 0;    //What was this for?
/**
 * Updates the grades in the Gradebook
 */
function strathpa_update_grades($pa = null, $userid=0, $nullifnone=true) {
    global $CFG, $pugcounter;

    $pugcounter++;
    if (!function_exists('grade_update')) {
        require_once($CFG->libdir.'/gradelib.php');
    }
    if ($pa != null) {
        if ($grades = strathpa_get_user_grades($pa, $userid)) {
            strathpa_grade_item_update($pa, $grades);
        } else if ($userid and $nullifnone) {
            $grade = new Stdclass;
            $grade->userid=$userid;
            $grade->rawgrade = null;
              strathpa_grade_item_update($pa, $grade);
        } else {
            strathpa_grade_item_update($pa);
        }
    }
}

function strathpa_grade_item_delete($data) {
    global $CFG;
    if (!function_exists('grade_update')) {
        require_once($CFG->libdir.'/gradelib.php');
    }
    return grade_update('mod/strathpa',
        $data->course,
        'mod',
        'strathpa',
        $data->id,
        0,
        null,
        array('deleted'=>1)
    );
}

/**
 * Creates a grade item for a particular strathpa activity
 */
function strathpa_grade_item_update($pa, $grades=null) {
    global $CFG;

    if (!function_exists('grade_update')) {
        require_once($CFG->libdir.'/gradelib.php');
    }

    $params =array('itemname'=>$pa->name);
    $params['gradetype'] = GRADE_TYPE_VALUE;
    $params['grademax'] = 5;
    $params['grademin'] = 1;
    if ($grades ==='reset') {
        $params['reset'] = true;
        $grades= null;
    } else if (!empty($grades)) {
        if (is_object($grades)) {
            $grades = array($grades->userid =>$grades);
        } else if (array_key_exists('userid', $grades)) {
            $grades = array($grades['userid'] => $grades);
        }
        foreach ($grades as $key => $grade) {
            if (!is_array($grade)) {
                $grades[$key]= $grade = (array)$grade;
            }
            $grades[$key]['rawgrade'] = ($grade['rawgrade']);
        }
    }
    $r = grade_update('mod/strathpa', $pa->course, 'mod', 'strathpa', $pa->id, 0, $grades, $params);
    return $r;
}

function strathpa_reset_gradebook() {

}


/*
 * From here on is all custom code, above is "Moodle" required code
 */
function get_week_start_from_time($start_date) {
    return mktime(0, 0, 0, date('m', $start_date), date('d', $start_date), date('Y', $start_date))
    -
    ((date("w", $start_date) ==0) ? 0 : (86400 * date("w", $start_date)));
}

/**
 * Returns a table displaying the results for SINGLE frequency strathpa
 */
function strathpa_get_table_single_frequency($peerassessment, $group) {
    global $CFG, $DB, $OUTPUT;

    $cm = get_coursemodule_from_instance('strathpa', $peerassessment->id);
    $context = context_course::instance($cm->course);//@TODO check this works.

    $members = groups_get_members($group->id);
    $table= new html_table();
    $table->head = array();
    $table->head[] ="Student <img src='". $OUTPUT->pix_url('t/expanded','moodle')."'>\Recipient<img src='".$OUTPUT->pix_url('t/collapsed','moodle')."'>";
    foreach ($members as $m2) {
        if (!has_capability('mod/strathpa:recordrating', $context, $m2->id)) {
            continue;
        }
        $table->head[] = "{$m2->lastname}, {$m2->firstname}";// ({$m2->id})";
    }
    $table->head[] = "Average Rating Given";
    $recieved_totals = array();
    $recieved_counts = array();

    $timemodified = -1;

    foreach ($members as $m) {
        if (!has_capability('mod/strathpa:recordrating', $context, $m->id)) {
            continue;
        }
        $a = array();
        $select ="userid = {$m->id} AND peerassessment={$peerassessment->id}";
        $comments = $DB->get_records_select('strathpa_comments', $select);
        $name = "{$m->lastname}, {$m->firstname}";// ({$m->id})";
        if ($comments) {
            $name .="<sup>";
            $c='';
            foreach ($comments as $comment) {
                $c = "$comment->studentcomment\n". $c;
            }
            $name.="<span class='popup' title=\"{$c}\">";
            $name.="<a href='{$CFG->wwwroot}/mod/strathpa/comments.php?p={$peerassessment->id}&userid={$m->id}'>";
            $name.="<img src='{$CFG->wwwroot}/mod/strathpa/comment.gif' alt='Comment'></img></a></span>";
        }
        $a[] = $name;
        $t1 = 0;
        $c=0;
        $hasentries = false;
        foreach ($members as $m2) {
            if (!has_capability('mod/strathpa:recordrating', $context, $m2->id)) {
                continue;
            }
            $sql ="SELECT * FROM {$CFG->prefix}strathpa_ratings
                    WHERE peerassessment={$peerassessment->id} AND ratedby={$m->id} AND userid={$m2->id}";
            $rating = $DB->get_record_sql($sql);
            if ($rating) {
                $hasentries = true;
                $timemodified = $rating->timemodified;

                $a[] = $rating->rating;
                $t1 = $t1+$rating->rating;
                $c++;
                if (!isset( $recieved_totals[$m2->id]) ) {
                    $recieved_totals[$m2->id] =0;
                }
                $recieved_totals[$m2->id] = $recieved_totals[$m2->id]+$rating->rating;
                if (!isset( $recieved_counts[$m2->id]) ) {
                    $recieved_counts[$m2->id] = 0;
                }
                $recieved_counts[$m2->id] = $recieved_counts[$m2->id]+1;
            } else {
                $a[] ='-';
            }
        }
        $a['avggiven'] = get_average_rating_by_student($peerassessment, $m->id);
        if (has_capability('mod/strathpa:deleteratings', $context)) {
            if ($hasentries) {
                $a[''] = print_delete_attempt_form($peerassessment, $group, $m->id, null, $timemodified);
            }
        }
        $table->data[] = $a;
    }
    //output the average grade received by top of column
    $a = array();
    $a['avgrecieved'] = 'Average Rating Received';
    foreach ($members as $m) {
        if (!has_capability('mod/strathpa:recordrating', $context, $m->id)) {
            continue;
        }
        $a[] = get_average_rating_for_student($peerassessment, $m->id);
    }
    $table->data['avgrow'] = $a;
    return $table;
}

function strathpa_get_table_weekly_frequency($peerassessment, $group, $showdetails=true) {
    global $CFG, $DB;
    $cm = get_coursemodule_from_instance('strathpa', $peerassessment->id);
    $context = context_course::instance($cm->id);
    $user_can_delete_ratings = has_capability('mod/strathpa:deleteratings', $context);

    $members = $members = groups_get_members($group->id);
    $table=new stdClass;
    $table->head = array();
    $recieved_totals = array();
    $recieved_counts = array();

    $heading = array();
    $heading[]='';
    $heading[]='';

    $earliest_sql = "SELECT min(timemodified) AS timemodifed
                       FROM {$CFG->prefix}strathpa_ratings
                      WHERE  peerassessment ={$peerassessment->id} ";
    $earliest_rs = $DB->get_record_sql($earliest_sql);

    if (!isset($earliest_rs->timemodifed)) {
        $table->data[] = array('No Entries');
        return $table;
    }
    $earliest_date=strtotime("last monday", $earliest_rs->timemodifed);

    $last_sql ="SELECT max(timemodified) AS timemodifed
                  FROM {$CFG->prefix}strathpa_ratings
                 WHERE  peerassessment ={$peerassessment->id}";

    $last_rs = get_record_sql($last_sql);
    $last_date =strtotime("next sunday", $last_rs->timemodifed);

    $duration_secs = $last_date -$earliest_date; //gives number of seconds entries have been made over
    $duration_weeks = ceil($duration_secs/PA_ONE_WEEK);
    echo "Duration of entries: ".$duration_weeks.' periods ';

    $startdate = $earliest_date;
    $entries_for_week = array();
    for ($i = 0; $i < $duration_weeks; $i++) {
        //get all of the entries for the given week and each member
        $offset="+ ".PA_ONE_WEEK .' seconds';
        $enddate = strtotime($offset, $startdate); //this is the sunday/monday midnight
        $entries_for_week_sql ="SELECT * FROM {$CFG->prefix}strathpa_ratings
        WHERE peerassessment={$peerassessment->id} AND timemodified >= $startDate and timemodified<=$enddate";
        $entries = $DB->get_records_sql($entries_for_week_sql);
        $entries_for_week[$startdate] = array();//
        if ($entries) {
            foreach ($entries as $e) {
                if (!isset($entries_for_week[$startdate][$e->ratedby])) {
                    $entries_for_week[$startdate][$e->ratedby]= array();
                }
                $entries_for_week[$startdate][$e->ratedby][$e->userid]= $e;
            }
        }
        $startdate = $enddate;
    }
    /*we should now have an array of "mondays", containing an array of
    userids (representing the user who MADE the rating), with each value
    containing an array of the ratings they actually made.
    */
    $doneheadings = false;
    $userheadings = array();
    $done_user_headings =false;
    foreach ($members as $m1) {
        $t1 = 0;
        $c = 0;
        $row=array();
        $row[] = $m1->lastname .', '.$m1->firstname;
        $userheadings[] = 'Student';
        $userheadings[] = 'Average Rating Given';  //we merged the average to the first column
        foreach ($entries_for_week as $week => $value) {
            if (!$doneheadings && $showdetails) {
                $heading[]='Week Starting ' .date('D d-M-Y', $week);
                for ($j= 0; $j<count($members)-1; $j++) {
                    $heading[] ='';
                }
            } else {
                $heading[] ='';
            }
            $user_entries = $value;
            foreach ($members as $m2) {
                if (!$done_user_headings) {
                    if ($showdetails) {
                        $userheadings[] = $m2->lastname .', '.$m2->firstname;
                    }
                }
                if (isset($user_entries[$m1->id][$m2->id])) {
                    $entry = $user_entries[$m1->id][$m2->id];
                    if (true){//$showdetails) {
                        $content =$entry->rating;
                        if ($user_can_delete_ratings) {
                            $content .= print_delete_attempt_form($peerassessment,
                                    $group,
                                    $m1->id,
                                    $entry,
                                    $entry->timemodified,
                                    true
                            );
                        }
                        $row[]  = $content;
                    }
                    $t1 = $t1+$entry->rating;
                    $c++;
                    if (!isset( $recieved_totals[$m2->id]) ) {
                        $recieved_totals[$m2->id] =0;
                    }
                    $recieved_totals[$m2->id] = $recieved_totals[$m2->id]+$entry->rating;
                    if (!isset( $recieved_counts[$m2->id]) ) {
                        $recieved_counts[$m2->id] = 0;
                    }
                    $recieved_counts[$m2->id] = $recieved_counts[$m2->id]+1;
                } else {
                    if ($showdetails) {
                        $row[]='-';
                    }
                }
            }
        }

        if (!$doneheadings) {
            $heading[] = '';
            if ($showdetails) {
                $table->data[] = $heading;
            }
            $doneheadings = true;
        }
        if (!$done_user_headings) {
            if ($showdetails) {
                $table->data[] = $userheadings;
            } else {
                $table->head = $userheadings;
            }
            $done_user_headings = true;
        }
        $name = array_slice($row, 0, 1);
        $values = array_slice($row, 1);
        $ave = array(get_average_rating_by_student($peerassessment, $m1->id));
        $row = array_merge($name, $ave, $values);
        $table->data[$m1->id] = $row;
    }
    $a = array();
    $a[]='';
    $a[] ='Average rating recieved';
    foreach ($members as $m1) {
        $a[] = get_average_rating_for_student($peerassessment, $m1->id);
    }
    $table->data[] = $a;
    return $table;
}

function strathpa_get_table_unlimited_frequency($peerassessment, $group) {
    $table=new stdClass;
    $table->head = array();
    $members = $members = groups_get_members($group->id);
    $table->head[] ="Student\Recipient &gt;";
    foreach ($members as $m2) {
        $table->head[] = "{$m2->lastname}, {$m2->firstname}";//({$m2->id})";
    }
    $recieved_totals = array();
    $recieved_counts = array();

    return $table;
}

function get_average_rating_for_student($peerassessment, $userid) {
    global $CFG, $DB;
    $sql = "SELECT AVG(rating) AS average
              FROM {$CFG->prefix}strathpa_ratings
             WHERE peerassessment={$peerassessment->id} AND userid={$userid}";
    $rs = $DB->get_record_sql($sql);
    if ($rs->average >PA_UPPER_THRESHOLD) {
        return "<span style='color:green'><sup>+</sup>".$rs->average."</span>";
    }
    if ($rs->average < PA_LOWER_THRESHOLD) {
        return "<span style='color:red'><sup style='color:red'>-</sup>".$rs->average."</span>";
    }
    return $rs->average;
}
function get_average_rating_by_student($peerassessment, $userid) {
    global $CFG, $DB;
    $sql = "SELECT AVG(rating) AS average
              FROM {$CFG->prefix}strathpa_ratings
             WHERE peerassessment={$peerassessment->id} AND ratedby={$userid}";
    $rs = $DB->get_record_sql($sql);
    if ($rs->average >PA_UPPER_THRESHOLD) {
        return "<span style='color:green'><sup style='color:green'>+</sup>".$rs->average."</span>";
    }
    if ($rs->average <PA_LOWER_THRESHOLD) {
        return "<span style='color:red'><sup style='color:red'>-</sup>".$rs->average ."</span>";
    }
    return $rs->average;
}

/**
 * Displays a form that allows an appropriate user to delete a rating or ratings.
 **/
function print_delete_attempt_form($peerassessment, $group, $userid, $rating=null, $timemodified=null, $return = true) {
    global $CFG, $USER;
    $cm = get_coursemodule_from_instance('strathpa', $peerassessment->id);
    $context = get_context_instance(CONTEXT_MODULE, $cm->id);
    if (!has_capability('mod/strathpa:deleteratings', $context)) {
        return '';  //don't return a form
    }
    $out = "<form action='report.php' method='post'>";

    $ratingid='';
    if (!is_null($rating)) {
        $ratingid=$rating->id;
    }
    $out.="<input type='hidden' name='ratingid' value='{$ratingid}'/>";
    $out.="<input type='hidden' name='ratingtime' value='{$timemodified}'/>";
    $out.="<input type='hidden' name='peerassessment' value='{$peerassessment->id}'/>";
    $out.="<input type='hidden' name='userid' value='{$userid}'/>";
    $out.="<input type='hidden' name='selectedgroup' value='{$group->id}'/>";
    $out.="<input type='submit' name='delete' value='Delete'/>";
    $out.="</form>";

    if ($return) {
        return $out;
    }
    echo $out;
}

function print_report_select_form($id, $groups, $selectedgroupid) {
    $displaygroups= array();
    //display a list of groups to display
    if (!$groups) {
        notify (get_string('nogroups', 'strathpa'));
        return;
    }
    foreach ($groups as $g) {
        $displaygroups[$g->id] =$g->name;
    }
    ksort($displaygroups, SORT_STRING);

    echo "<form action='report.php' method='get'><p>". get_string('viewreportgroup', 'strathpa');
    echo html_writer::select($displaygroups, 'selectedgroup', $selectedgroupid);
    echo "<input type='hidden' name='id' value='{$id}'/><input type='submit' value='Select'/></p>";
    echo '</form>';
}
/**
 *
 * Peer assessment currently supports all features except Adv. Grading and Backup moodle2.
 * @param string $feature
 */
function strathpa_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:                  return true;
        case FEATURE_GROUPINGS:               return true;
        case FEATURE_GROUPMEMBERSONLY:        return true;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_GRADE_HAS_GRADE:         return true;
        case FEATURE_GRADE_OUTCOMES:          return true;
        case FEATURE_GRADE_HAS_GRADE:         return true;
        case FEATURE_BACKUP_MOODLE2:          return false;
        case FEATURE_SHOW_DESCRIPTION:        return true;
        case FEATURE_ADVANCED_GRADING:        return false;
        case FEATURE_RATE:                    return true;

        default: return null;
    }
}