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
 * Prints information about the reengagement to the user.
 *
 * @package    mod_reengagement
 * @author     Peter Bulmer <peter.bulmer@catlayst.net.nz>
 * @copyright  2016 Catalyst IT {@link http://www.catalyst.net.nz}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');

$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or
$a  = optional_param('a', 0, PARAM_INT);  // reengagement instance ID.

$params = array();

if ($id) {
    $params['id'] = $id;
} else {
    $params['a'] = $a;
}

$PAGE->set_url('/mod/reengagement/view.php', $params);

if ($id) {
    $cm = get_coursemodule_from_id('reengagement', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $reengagement = $DB->get_record('reengagement', array('id' => $cm->instance), '*', MUST_EXIST);

} else if ($a) {
    $reengagement = $DB->get_record('reengagement', array('id' => $a), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $reengagement->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('reengagement', $reengagement->id, $course->id, false, MUST_EXIST);
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


// Print the page header.
$strreengagements = get_string('modulenameplural', 'reengagement');
$strreengagement  = get_string('modulename', 'reengagement');


$PAGE->set_title(format_string($reengagement->name));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
// Print the main part of the page.

$PAGE->set_context($context);

$canstart = has_capability('mod/reengagement:startreengagement', $context, null, false);
$canedit = has_capability('mod/reengagement:editreengagementduration', $context);

if (empty($canstart) && empty($canedit)) {
    error("This reengagement module is not enabled for your account.
      Please contact your administrator if you feel this is in error");
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
            $reengagementinprogress = new stdClass();
            $reengagementinprogress->reengagement = $reengagement->id;
            $reengagementinprogress->completiontime = time() + $reengagement->duration;
            $reengagementinprogress->emailtime = time() + $reengagement->emaildelay;
            $reengagementinprogress->userid = $USER->id;
            $DB->insert_record('reengagement_inprogress', $reengagementinprogress);

            // Set activity completion in-progress record to fit in with normal activity completion requirements.
            $activitycompletion = new stdClass();
            $activitycompletion->coursemoduleid = $cm->id;
            $activitycompletion->completionstate = COMPLETION_INCOMPLETE;
            $activitycompletion->timemodified = time();
            $activitycompletion->userid = $USER->id;
            $DB->insert_record('course_modules_completion', $activitycompletion);
            // Re-load that same info.
            $completion = $DB->get_record('course_modules_completion', array('userid' => $USER->id, 'coursemoduleid' => $cm->id));

        } else {
            // The user has permission to start a reengagement, but not this one (likely due to incomplete prerequiste activities).
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
    if (!empty($completion) && !empty($rip)) {
        // User is genuinely in-progress.
        if ($reengagement->emailuser == REENGAGEMENT_EMAILUSER_TIME && empty($rip->emailsent)) {
            $emailpending = true;
            $emailtime = $rip->emailtime;
        } else if ($reengagement->emailuser == REENGAGEMENT_EMAILUSER_COMPLETION && empty($rip->completed)) {
            $emailpending = true;
            $emailtime = $rip->completiontime;
        } else {
            $emailpending = false;
        }

        $datestr = userdate($rip->emailtime, $dateformat);
        if ($emailpending) {
            if (empty($reengagement->suppresstarget)) {
                // You'll get an email at xyz time.
                $emailmessage = get_string('receiveemailattimex', 'reengagement', $datestr);
            } else {
                // There is a target activity, if the target activity is complete, we won't send the email.
                $targetcomplete = reengagement_check_target_completion($USER->id, $id);
                if (!$targetcomplete) {
                    // Message will be sent at xyz time unless you complete target activity.
                    $emailmessage = get_string('receiveemailattimexunless', 'reengagement', $datestr);
                } else {
                    // Message scheduled for xyz time will not be sent because you have completed the target activity.
                    $emailmessage = get_string('noemailattimex', 'reengagement', $datestr);
                }
            }
            echo $OUTPUT->box($emailmessage);
        }

        // Activity completion can be independent of email time. Show completion time too.
        if ($completion->completionstate == COMPLETION_INCOMPLETE) {
            $datestr = userdate($rip->completiontime, $dateformat);
            // This activity will complete at XYZ time.
            $completionmessage = get_string('completeattimex', 'reengagement', $datestr);
        } else {
            // This activity has been marked as complete.
            $completionmessage = get_string('activitycompleted', 'reengagement');
        }
        echo $OUTPUT->box($completionmessage);
    }
}


if ($canedit) {
    // User is able to see admin-type features of this plugin - ie not just their own re-engagement status.
    $sql = "SELECT *
              FROM {reengagement_inprogress} rip
        INNER JOIN {user} u ON u.id = rip.userid
             WHERE rip.reengagement = :reengagementid
               AND u.deleted = 0
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
                if ($rip->emailsent > $reengagement->remindercount) {
                    // Email has already been sent - don't show a time in the past.
                    print '<td></td>';
                } else {
                    // Email will be sent, but hasn't been yet.
                    print '<td>' . userdate($rip->emailtime, get_string('strftimedatetimeshort', 'langconfig')) . "</td>";
                }
            }
            if ($rip->completed) {
                // User has completed the activity, but email hasn't been sent yet.
                // Show an empty completion time.
                print '<td></td>';
            } else {
                // User hasn't complted activity yet.
                print '<td>' . userdate($rip->completiontime, get_string('strftimedatetimeshort', 'langconfig')) . "</td>";
            }
            print '</tr>';
        }
        print "</table>\n";
    } else {
        echo $OUTPUT->box('No reengagements in progress');
    }
}

// Finish the page.
echo $OUTPUT->footer($course);
