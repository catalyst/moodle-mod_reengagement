<?php  // $Id: view.php,v 1.6.2.3 2009/04/17 22:06:25 skodak Exp $

/**
 * This page prints a particular instance of reengagement
 *
 * @author  Your Name <your@email.address>
 * @version $Id: view.php,v 1.6.2.3 2009/04/17 22:06:25 skodak Exp $
 * @package mod/reengagement
 */

/// (Replace reengagement with the name of your module and remove this line)

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once($CFG->dirroot . '/lib/conditionlib.php');

$id = optional_param('id', 0, PARAM_INT); // course_module ID, or
$a  = optional_param('a', 0, PARAM_INT);  // reengagement instance ID

$params = array();

if ($id) {
    $params['id'] = $id;
} else {
    $params['a'] = $a;
}

$PAGE->set_url('/mod/reengagement/view.php', $params);

if ($id) {
    if (! $cm = get_coursemodule_from_id('reengagement', $id)) {
        error('Course Module ID was incorrect');
    }

    if (! $course = $DB->get_record('course', array('id' => $cm->course))) {
        error('Course is misconfigured');
    }

    if (! $reengagement = $DB->get_record('reengagement', array('id' => $cm->instance))) {
        error('Course module is incorrect');
    }

} else if ($a) {
    if (! $reengagement = $DB->get_record('reengagement', array('id' =>  $a))) {
        error('Course module is incorrect');
    }
    if (! $course = $DB->get_record('course', array('id' => $reengagement->course))) {
        error('Course is misconfigured');
    }
    if (! $cm = get_coursemodule_from_instance('reengagement', $reengagement->id, $course->id)) {
        error('Course Module ID was incorrect');
    }

} else {
    error('You must specify a course_module ID or an instance ID');
}

require_login($course, true, $cm);

add_to_log($course->id, "reengagement", "view", "view.php?id=$cm->id", "$reengagement->id");

/// Print the page header
$strreengagements = get_string('modulenameplural', 'reengagement');
$strreengagement  = get_string('modulename', 'reengagement');


$PAGE->set_title(format_string($reengagement->name));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
/// Print the main part of the page
$context = get_context_instance(CONTEXT_MODULE, $cm->id);

$PAGE->set_context($context);

$canstart = has_capability('mod/reengagement:startreengagement', $context, NULL, false);
$canedit = has_capability('mod/reengagement:editreengagementduration', $context);

if (empty($canstart) && empty($canedit)) {
    error("This reengagement module is not enabled for your account.  Please contact your administrator if you feel this is in error");
}

$modinfo = get_fast_modinfo($course);


$ci = new condition_info($modinfo->instances['reengagement'][$reengagement->id]);

if ($canstart) {
    $completion = $DB->get_record('course_modules_completion', array('userid' => $USER->id, 'coursemoduleid' => $cm->id));
    if (empty($completion)) {
        $availabilityinfo='';
        if ($ci->is_available($availabilityinfo)) {
            $reengagement_inprogress = new stdClass();
            $reengagement_inprogress->reengagement = $reengagement->id;
            $reengagement_inprogress->completiontime = time() + $reengagement->duration;
            $reengagement_inprogress->userid = $USER->id;
            $DB->insert_record('reengagement_inprogress', $reengagement_inprogress);

            $activity_completion = new stdClass();
            $activity_completion->coursemoduleid = $cm->id;
            $activity_completion->completionstate = COMPLETION_INCOMPLETE;
            $activity_completion->timemodified = time();
            $activity_completion->userid = $USER->id;
            $DB->insert_record('course_modules_completion', $activity_completion);

            if ($sip = $DB->get_record('reengagement_inprogress', array('userid' => $USER->id, 'reengagement' => $reengagement->id))) {
                $report = "Your reengagement is in progress and is due to be complete at ";
                $report .= date("g:i a", $sip->completiontime) . " on " . date("d/m/Y", $sip->completiontime);
                echo $OUTPUT->box($report);
            }
        } else {
            $report = "This reengagement is not available";
            if ($availabilityinfo) {
                $report .= " ( $reengagement )";
            }
            echo $OUTPUT->box($report);
        }
    } else {
        if (empty($completion->completionstate)) {
            if ($sip = $DB->get_record('reengagement_inprogress', array('userid' => $USER->id, 'reengagement' => $reengagement->id))) {
                $report = "Your reengagement is in progress";
                if ($sip->completiontime > time()) {
                    $report .= " and is due to be complete at ";
                    $report .= date("g:i a", $sip->completiontime) . " on " . date("d/m/Y", $sip->completiontime);
                }
                echo $OUTPUT->box($report);
            }
        } else {
            $report = "You have completed this reengagement.";
            echo $OUTPUT->box($report);
        }
    }
}


if ($canedit) {
    $sql = "SELECT * 
            FROM {reengagement_inprogress} sip
                INNER JOIN {user} u ON u.id = sip.userid
            WHERE sip.reengagement = :reengagementid";

    $sips = $DB->get_records_sql($sql, array('reengagementid' => $reengagement->id));

    if ($sips) {
        print '<table class="reengagementlist">' . "\n";
        print "<tr><th span=2>Standdowns in progress</th></tr>\n";
        foreach ($sips as $sip) {
            $fullname = fullname($sip);
            print '<tr><td>' . $fullname . '</td><td>' . date("g:i a d/m/Y", $sip->completiontime) . "</td></tr>\n";
        }
        print "</table>\n";
    } else {
        echo $OUTPUT->box('No reengagements in progress');
    }
}


/// Finish the page
echo $OUTPUT->footer($course);
