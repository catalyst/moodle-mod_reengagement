<?php  // $Id: view.php,v 1.6.2.3 2009/04/17 22:06:25 skodak Exp $

/**
 * This page prints a particular instance of reengagement
 * Depending on whether the user has a reengagement in progress (RIP) or not, it prints different content.
 *
 * @author  Your Name <your@email.address>
 * @version $Id: view.php,v 1.6.2.3 2009/04/17 22:06:25 skodak Exp $
 * @package mod/reengagement
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');

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

$context = context_module::instance($cm->id);

$event = \mod_reengagement\event\course_module_viewed::create(array(
    'objectid' => $reengagement->id,
    'context' => $context,
));
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('reengagement', $reengagement);
$event->trigger();


/// Print the page header
$strreengagements = get_string('modulenameplural', 'reengagement');
$strreengagement  = get_string('modulename', 'reengagement');


$PAGE->set_title(format_string($reengagement->name));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
/// Print the main part of the page

$PAGE->set_context($context);

$canstart = has_capability('mod/reengagement:startreengagement', $context, NULL, false);
$canedit = has_capability('mod/reengagement:editreengagementduration', $context);

if (empty($canstart) && empty($canedit)) {
    error("This reengagement module is not enabled for your account.  Please contact your administrator if you feel this is in error");
}

$modinfo = get_fast_modinfo($course->id);
$cminfo = $modinfo->get_cm($cm->id);

$ainfomod = new \core_availability\info_module($cminfo);

if ($canstart) {
    // User could have arrived here eligible to start, but before cron had a chance to start them in the activity.
    // Check for that scenario.
    $completion = $DB->get_record('course_modules_completion', array('userid' => $USER->id, 'coursemoduleid' => $cm->id));
    if (empty($completion)) {
        // User hasn't yet started this activity.
        $availabilityinfo = '';
        if (!$ainfomod->is_available($availabilityinfo)) {
            // User has satisfied all activity completion preconditions, start them on this activity.
            // Set a RIP record, so we know when to send an email/mark activity as complete by cron later.
            $reengagement_inprogress = new stdClass();
            $reengagement_inprogress->reengagement = $reengagement->id;
            $reengagement_inprogress->completiontime = time() + $reengagement->duration;
            $reengagement_inprogress->emailtime = time() + $reengagement->emaildelay;
            $reengagement_inprogress->userid = $USER->id;
            $DB->insert_record('reengagement_inprogress', $reengagement_inprogress);

            // Set activity completion in-progress record to fit in with normal activity completion requirements.
            $activity_completion = new stdClass();
            $activity_completion->coursemoduleid = $cm->id;
            $activity_completion->completionstate = COMPLETION_INCOMPLETE;
            $activity_completion->timemodified = time();
            $activity_completion->userid = $USER->id;
            $DB->insert_record('course_modules_completion', $activity_completion);
            // Re-load that same info.
            $completion = $DB->get_record('course_modules_completion', array('userid' => $USER->id, 'coursemoduleid' => $cm->id));

        } else {
            // The user has permission to start a reengagement, but not this one right now. (likely due to incomplete prerequiste activities).
            $report = "This reengagement is not available";
            if ($availabilityinfo) {
                $report .= " ( $availabilityinfo ) ";
            }
            echo $OUTPUT->box($report);
        }
    }
    if (!empty($completion)) {
        $rip = $DB->get_record('reengagement_inprogress', array('userid' => $USER->id, 'reengagement' => $reengagement->id));
    }

    $dateformat = get_string('strftimedatetime', 'langconfig'); // Description of how to format times in user's language.
    if (!empty($completion && !empty($rip))) {
        // User is genuinely in-progress
        if ($reengagement->emailuser == REENGAGEMENT_EMAILUSER_TIME && empty($rip->emailsent)) {
            $emailpending = true;
            $emailtime = $rip->emailtime;
        } elseif ($reengagement->emailuser == REENGAGEMENT_EMAILUSER_COMPLETION && empty($rip->completed)) {
            $emailpending = true;
            $emailtime = $rip->completiontime;
        } else {
            $emailpending = false;
        }

        $datestr = userdate($rip->emailtime, $dateformat);
        if ($emailpending) {
            if (empty($reengagement->suppresstarget)) {
                // 'You'll get an email at xyz time.'
                $emailmessage = get_string('receiveemailattimex', 'reengagement', $datestr);
            } else {
                // There is a target activity, if the target activity is complete, we won't send the email.
                $targetcomplete = reengagement_check_target_completion($USER->id, $id);
                if (!$targetcomplete) {
                    // 'Message will be sent at xyz time unless you complete target activity'.
                    $emailmessage = get_string('receiveemailattimexunless', 'reengagement', $datestr);
                } else {
                    // 'Message scheduled for xyz time will not be sent because you have completed the target activity'.
                    $emailmessage = get_string('noemailattimex', 'reengagement', $datestr);
                }
            }
            echo $OUTPUT->box($emailmessage);
        }
    }

    // Activity completion can be independent of email time. Show completion time too.
    if ($completion->completionstate == COMPLETION_INCOMPLETE) {
        $datestr = userdate($rip->completiontime, $dateformat);
        // 'This activity will complete at XYZ time'.
        $completionmessage = get_string('completeattimex', 'reengagement', $datestr);
    } else {
        // 'This activity has been marked as complete'.
        $completionmessage = get_string('activitycompleted', 'reengagement');
    }
    echo $OUTPUT->box($completionmessage);
}


if ($canedit) {
    // User is able to see admin-type features of this plugin - ie not just their own re-engagement status.
    $sql =
            "SELECT *
               FROM {reengagement_inprogress} rip
         INNER JOIN {user} u ON u.id = rip.userid
              WHERE rip.reengagement = :reengagementid
           ORDER BY rip.completiontime ASC, u.lastname ASC, u.firstname ASC";

    $rips = $DB->get_records_sql($sql, array('reengagementid' => $reengagement->id));

    if ($rips) {
        // There are re-engagements in progress.
        if (!in_array($reengagement->emailuser, array(REENGAGEMENT_EMAILUSER_NEVER, REENGAGEMENT_EMAILUSER_COMPLETION))) {
            // Include an extra column to show the time the user will be emailed.
            $showemailtime = true;
        } else {
            $showemailtime = false;
        }
        print '<table class="reengagementlist">' . "\n";
        print "<tr><th>" . get_string('user') . "</th>";
        if ($showemailtime) {
            print "<th>" . get_string('emailtime', 'reengagement') . '</th>';
        }
        print "<th>" . get_string('completiontime', 'reengagement') . '</th>';
        print "</tr>";
        foreach ($rips as $rip) {
            $fullname = fullname($rip);
            print '<tr><td>' . $fullname . '</td>';
            if ($showemailtime) {
                if ($rip->emailsent) {
                    // Email has already been sent - don't show a time in the past.
                    print '<td></td>';
                } else {
                    // Email will be sent, but hasn't been yet.
                    print '<td>' . date("g:i a d/m/Y", $rip->emailtime) . "</td>";
                }
            }
            if ($rip->completed) {
                // User has completed the activity, but email hasn't been sent yet.
                // Show an empty completion time.
                print '<td></td>';
            } else {
                // User hasn't complted activity yet.
                print '<td>' . date("g:i a d/m/Y", $rip->completiontime) . "</td>";
            }
            print '</tr>';
        }
        print "</table>\n";
    } else {
        echo $OUTPUT->box('No reengagements in progress');
    }
}


/// Finish the page
echo $OUTPUT->footer($course);
