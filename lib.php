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
 * This page lists all the instances of reengagement in a particular course
 *
 * @package    mod_reengagement
 * @author     Peter Bulmer
 * @copyright  2016 Catalyst IT {@link http://www.catalyst.net.nz}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir."/completionlib.php");

define('REENGAGEMENT_EMAILUSER_NEVER', 0);
define('REENGAGEMENT_EMAILUSER_COMPLETION', 1);
define('REENGAGEMENT_EMAILUSER_TIME', 2);
define('REENGAGEMENT_EMAILUSER_RESERVED1', 3);

define('REENGAGEMENT_RECIPIENT_USER', 0);
define('REENGAGEMENT_RECIPIENT_MANAGER', 1);
define('REENGAGEMENT_RECIPIENT_BOTH', 2);

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param object $reengagement An object from the form in mod_form.php
 * @return int The id of the newly inserted reengagement record
 */
function reengagement_add_instance($reengagement) {
    global $DB;

    $reengagement->timecreated = time();
    if (!$reengagement->suppressemail) {
        // User didn't tick the box indicating they wanted to suppress email if a certain activity was complete.
        // Force the 'target activity' field to be 0 (ie no target).
        $reengagement->suppresstarget = 0;
    }
    unset($reengagement->suppressemail);

    // Check course has completion enabled, and enable it if not, and user has permission to do so.
    $course = $DB->get_record('course', array('id' => $reengagement->course));
    if (empty($course->enablecompletion)) {
        $coursecontext = context_course::instance($course->id);
        if (has_capability('moodle/course:update', $coursecontext)) {
            $data = array('id' => $course->id, 'enablecompletion' => '1');
            $DB->update_record('course', $data);
            rebuild_course_cache($course->id);
        }
    }

    return $DB->insert_record('reengagement', $reengagement);
}


/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param object $reengagement An object from the form in mod_form.php
 * @return boolean Success/Fail
 */
function reengagement_update_instance($reengagement) {
    global $DB;

    $reengagement->timemodified = time();
    $reengagement->id = $reengagement->instance;

    // If they didn't chose to suppress email, do nothing.
    if (!$reengagement->suppressemail) {
        $reengagement->suppresstarget = 0;// No target to be set.
    }
    unset($reengagement->suppressemail);
    $result = $DB->update_record('reengagement', $reengagement);
    return $result;
}


/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function reengagement_delete_instance($id) {
    global $DB;

    if (! $reengagement = $DB->get_record('reengagement', array('id' => $id))) {
        return false;
    }

    $result = true;

    // Delete any dependent records here.
    if (! $DB->delete_records('reengagement_inprogress', array('reengagement' => $reengagement->id))) {
        $result = false;
    }

    if (! $DB->delete_records('reengagement', array('id' => $reengagement->id))) {
        $result = false;
    }

    return $result;
}

/**
 * Print the grade information for this user.
 *
 * @param stdClass $course
 * @param stdClass $user
 * @param stdClass $mod
 * @param stdClass $reengagement
 */
function reengagement_user_outline($course, $user, $mod, $reengagement) {
    return;
}


/**
 * Prints the complete info about a user's interaction.
 *
 * @param stdClass $course
 * @param stdClass $user
 * @param stdClass $mod
 * @param stdClass $reengagement
 */
function reengagement_user_complete($course, $user, $mod, $reengagement) {
    return true;
}

/**
 * Obtains the automatic completion state for this forum based on any conditions
 * in forum settings.
 *
 * @param object $course Course
 * @param object $cm Course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not. (If no conditions, then return
 *   value depends on comparison type)
 */
function reengagement_get_completion_state($course, $cm, $userid, $type) {
    global $DB;
    if ($completion = $DB->get_record('course_modules_completion', array('coursemoduleid' => $cm->id, 'userid' => $userid))) {
        return $completion->completionstate == COMPLETION_COMPLETE_PASS;
    }
    return false;
}

/**
 * Prints the recent activity.
 *
 * @param stdClass $course
 * @param stdClass $isteacher
 * @param stdClass $timestart
 */
function reengagement_print_recent_activity($course, $isteacher, $timestart) {
    return false;  // True if anything was printed, otherwise false.
}


/**
 * Function to be run periodically according to the moodle cron
 * * Add users who can start this module to the 'reengagement_inprogress' table
 *   and add an entry to the activity completion table to indicate that they have started
 * * Check the reengagement_inprogress table for users who have completed thieir reengagement
 *   and mark their activity completion as being complete
 *   and send an email if the reengagement instance calls for it.
 * @return boolean
 */
function reengagement_crontask() {
    global $CFG, $DB;

    require_once($CFG->libdir."/completionlib.php");

    // Get a consistent 'timenow' value across this whole function.
    $timenow = time();

    $reengagementssql = "SELECT cm.id as id, cm.id as cmid, cm.availability, r.id as rid, r.course as courseid,
                                r.duration, r.emaildelay
                          FROM {reengagement} r
                    INNER JOIN {course_modules} cm on cm.instance = r.id
                          JOIN {modules} m on m.id = cm.module
                         WHERE m.name = 'reengagement' AND cm.deletioninprogress = 0
                      ORDER BY r.id ASC";

    $reengagements = $DB->get_recordset_sql($reengagementssql);
    if (!$reengagements->valid()) {
        // No reengagement module instances in a course
        mtrace("No reengagement instances found - nothing to do :)");
        return true;
    }

    // First: add 'in-progress' records for those users who are able to start.
    foreach ($reengagements as $reengagementcm) {
        // Get a list of users who are eligible to start this module.
        $startusers = reengagement_get_startusers($reengagementcm);

        // Prepare some objects for later db insertion.
        $reengagementinprogress = new stdClass();
        $reengagementinprogress->reengagement = $reengagementcm->rid;
        $reengagementinprogress->completiontime = $timenow + $reengagementcm->duration;
        $reengagementinprogress->emailtime = $timenow + $reengagementcm->emaildelay;
        $activitycompletion = new stdClass();
        $activitycompletion->coursemoduleid = $reengagementcm->cmid;
        $activitycompletion->completionstate = COMPLETION_INCOMPLETE;
        $activitycompletion->timemodified = $timenow;
        $userlist = array_keys($startusers);
        $newripcount = count($userlist); // Count of new reengagements-in-progress.
        if (debugging('', DEBUG_DEVELOPER) || ($newripcount && debugging('', DEBUG_ALL))) {
            mtrace("Adding $newripcount reengagements-in-progress to reengagementid " . $reengagementcm->rid);
        }

        foreach ($userlist as $userid) {
            $reengagementinprogress->userid = $userid;
            $DB->insert_record('reengagement_inprogress', $reengagementinprogress);
            $activitycompletion->userid = $userid;
            $DB->insert_record('course_modules_completion', $activitycompletion);
        }
    }
    $reengagements->close();
    // All new users have now been recorded as started.
    // See if any previous users are due to finish, &/or be emailed.

    // Get more info about the activity, & prepare to update db
    // and email users.

    $reengagementssql = "SELECT r.id as id, cm.id as cmid, r.emailcontent, r.emailcontentformat, r.emailsubject,
                                r.thirdpartyemails, r.emailcontentmanager, r.emailcontentmanagerformat, r.emailsubjectmanager,
                                r.emailcontentthirdparty, r.emailcontentthirdpartyformat, r.emailsubjectthirdparty,
                                r.emailuser, r.name, r.suppresstarget, r.remindercount, c.shortname as courseshortname,
                                c.fullname as coursefullname, c.id as courseid, r.emailrecipient, r.emaildelay
                          FROM {reengagement} r
                    INNER JOIN {course_modules} cm ON cm.instance = r.id
                    INNER JOIN {course} c ON cm.course = c.id
                          JOIN {modules} m on m.id = cm.module
                         WHERE m.name = 'reengagement'
                      ORDER BY r.id ASC";

    $reengagements = $DB->get_records_sql($reengagementssql);

    $inprogresssql = 'SELECT ri.*
                        FROM {reengagement_inprogress} ri
                        JOIN {user} u ON u.id = ri.userid
                       WHERE u.deleted = 0 AND
                       completiontime < ? AND completed = 0';
    $inprogresses = $DB->get_recordset_sql($inprogresssql, array($timenow));
    $completeripcount = 0;
    foreach ($inprogresses as $inprogress) {
        $completeripcount++;
        // A user has completed an instance of the reengagement module.
        $inprogress->timedue = $inprogress->completiontime;
        $reengagement = $reengagements[$inprogress->reengagement];
        $cmid = $reengagement->cmid; // The cm id of the module which was completed.
        $userid = $inprogress->userid; // The userid which completed the module.

        // Check if user is still enrolled in the course.
        $context = context_module::instance($reengagement->cmid);
        if (!is_enrolled($context, $userid, 'mod/reengagement:startreengagement', true)) {
            $DB->delete_records('reengagement_inprogress', array('id' => $inprogress->id));
            continue;
        }

        // Update completion record to indicate completion so the user can continue with any dependant activities.
        $completionrecord = $DB->get_record('course_modules_completion', array('coursemoduleid' => $cmid, 'userid' => $userid));
        if (empty($completionrecord)) {
            mtrace("Could not find completion record for updating to complete state - userid: $userid, cmid: $cmid - recreating record.");
            // This might happen when reset_all_state has been triggered, deleting an "in-progress" record. so recreate it.
            $completionrecord = new stdClass();
            $completionrecord->coursemoduleid = $cmid;
            $completionrecord->completionstate = COMPLETION_COMPLETE_PASS;
            $completionrecord->viewed = COMPLETION_VIEWED;
            $completionrecord->overrideby = null;
            $completionrecord->timemodified = $timenow;
            $completionrecord->userid = $userid;
            $completionrecord->id = $DB->insert_record('course_modules_completion', $completionrecord);
        } else {
            $updaterecord = new stdClass();
            $updaterecord->id = $completionrecord->id;
            $updaterecord->completionstate = COMPLETION_COMPLETE_PASS;
            $updaterecord->timemodified = $timenow;
            $DB->update_record('course_modules_completion', $updaterecord) . " \n";
        }
        $completioncache = cache::make('core', 'completion');
        $completioncache->delete($userid . '_' . $reengagement->courseid);

        $cmcontext = context_module::instance($cmid, MUST_EXIST);
        // Trigger an event for course module completion changed.
        $event = \core\event\course_module_completion_updated::create(array(
            'objectid' => $completionrecord->id,
            'context' => $cmcontext,
            'relateduserid' => $userid,
            'other' => array(
                'relateduserid' => $userid
            )
        ));
        $event->add_record_snapshot('course_modules_completion', $completionrecord);
        $event->trigger();

        $result = false;
        if (($reengagement->emailuser == REENGAGEMENT_EMAILUSER_COMPLETION) ||
                ($reengagement->emailuser == REENGAGEMENT_EMAILUSER_NEVER) ||
                ($reengagement->emailuser == REENGAGEMENT_EMAILUSER_TIME && !empty($inprogress->emailsent))) {
            // No need to keep 'inprogress' record for later emailing
            // Delete inprogress record.
            debugging('', DEBUG_DEVELOPER) && mtrace("mode $reengagement->emailuser reengagementid $reengagement->id.
                      User marked complete, deleting inprogress record for user $userid");
            $result = $DB->delete_records('reengagement_inprogress', array('id' => $inprogress->id));
        } else {
            // Update inprogress record to indicate completion done.
            debugging('', DEBUG_DEVELOPER) && mtrace("mode $reengagement->emailuser reengagementid $reengagement->id
                      updating inprogress record for user $userid to indicate completion");
            $updaterecord = new stdClass();
            $updaterecord->id = $inprogress->id;
            $updaterecord->completed = COMPLETION_COMPLETE;
            $result = $DB->update_record('reengagement_inprogress', $updaterecord);
        }
        if (empty($result)) {
            // Skip emailing. Go on to next completion record so we don't risk emailing users continuously each cron.
            debugging('', DEBUG_ALL) && mtrace("Reengagement: not sending email to $userid regarding reengagementid
                      $reengagement->id due to failuer to update db");
            continue;
        }
        if ($reengagement->emailuser == REENGAGEMENT_EMAILUSER_COMPLETION) {
            debugging('', DEBUG_ALL) && mtrace("Reengagement: sending email to $userid regarding reengagementid
                      $reengagement->id due to completion.");
            reengagement_email_user($reengagement, $inprogress);
        }
    }
    $inprogresses->close();

    if (debugging('', DEBUG_DEVELOPER) || ($completeripcount && debugging('', DEBUG_ALL))) {
        mtrace("Found $completeripcount complete reengagements.");
    }

    // Get inprogress records where the user has reached their email time, and module is email 'after delay'.
    $inprogresssql = "SELECT ip.*, ip.emailtime as timedue
                        FROM {reengagement_inprogress} ip
                  INNER JOIN {reengagement} r on r.id = ip.reengagement
                        JOIN {user} u ON u.id = ip.userid
                       WHERE ip.emailtime < :emailtime
                             AND r.emailuser = " . REENGAGEMENT_EMAILUSER_TIME . '
                             AND ip.emailsent < r.remindercount
                             AND u.deleted = 0
                    ORDER BY r.id ASC';
    $params = array('emailtime' => $timenow);

    $inprogresses = $DB->get_recordset_sql($inprogresssql, $params);
    $emailduecount = 0;
    foreach ($inprogresses as $inprogress) {
        $emailduecount++;
        $reengagement = $reengagements[$inprogress->reengagement];
        $userid = $inprogress->userid; // The userid which completed the module.

        // Check if user is still enrolled in the course.
        $context = context_module::instance($reengagement->cmid);
        if (!is_enrolled($context, $userid, 'mod/reengagement:startreengagement', true)) {
            $DB->delete_records('reengagement_inprogress', array('id' => $inprogress->id));
            continue;
        }

        if ($inprogress->completed == COMPLETION_COMPLETE) {
            debugging('', DEBUG_DEVELOPER) && mtrace("mode $reengagement->emailuser reengagementid $reengagement->id.
                      User already marked complete. Deleting inprogress record for user $userid");
            $result = $DB->delete_records('reengagement_inprogress', array('id' => $inprogress->id));
        } else {
            debugging('', DEBUG_DEVELOPER) && mtrace("mode $reengagement->emailuser reengagementid $reengagement->id.
                      Updating inprogress record to indicate email sent for user $userid");
            $updaterecord = new stdClass();
            $updaterecord->id = $inprogress->id;
            if ($reengagement->remindercount > $inprogress->emailsent) {
                $updaterecord->emailtime = $timenow + $reengagement->emaildelay;
            }
            $updaterecord->emailsent = $inprogress->emailsent + 1;
            $result = $DB->update_record('reengagement_inprogress', $updaterecord);
        }
        if (!empty($result)) {
            debugging('', DEBUG_ALL) && mtrace("Reengagement: sending email to $userid regarding reengagementid
                      $reengagement->id due to emailduetime.");
            reengagement_email_user($reengagement, $inprogress);
        }
    }
    $inprogresses->close();

    if (debugging('', DEBUG_DEVELOPER) || ($emailduecount && debugging('', DEBUG_ALL))) {
        mtrace("Found $emailduecount reengagements due to be emailed.");
    }

    return true;
}

/**
 * Email is due to be sent to reengage user in course.
 * Check if there is any reason to not send, then email user.
 *
 * @param object $reengagement db record of details for this activity
 * @param object $inprogress record of user participation in this activity.
 * @return boolean true if everything we wanted to do worked. False otherwise.
 */
function reengagement_email_user($reengagement, $inprogress) {
    global $DB, $SITE, $CFG;
    $istotara = false;
    if (file_exists($CFG->dirroot.'/totara')) {
        $istotara = true;
    }
    $user = $DB->get_record('user', array('id' => $inprogress->userid));
    if (!empty($user->deleted)) {
        // User has been deleted - don't send an e-mail.
        return true;
    }
    if (!empty($reengagement->suppresstarget)) {
        $targetcomplete = reengagement_check_target_completion($user->id, $reengagement->suppresstarget);
        if ($targetcomplete) {
            debugging('', DEBUG_DEVELOPER) && mtrace('Reengagement modules: User:'.$user->id.
                      ' has completed target activity:'.$reengagement->suppresstarget.' suppressing email.');
            return true;
        }
    }
    // Where cron isn't run regularly, we could get a glut requests to send email that are either ancient, or too late to be useful.
    if (!empty($inprogress->timedue) && (($inprogress->timedue + 2 * DAYSECS) < time())) {
        // We should have sent this email more than two days ago.
        // Don't send.
        debugging('', DEBUG_ALL) && mtrace('Reengagement: ip id ' . $inprogress->id . 'User:'.$user->id.
                  ' Email not sent - was due more than 2 days ago.');
        return true;
    }
    if (!empty($inprogress->timeoverdue) && ($inprogress->timeoverdue < time())) {
        // There's a deadline hint provided, and we're past it.
        // Don't send.
        debugging('', DEBUG_ALL) && mtrace('Reengagement: ip id ' . $inprogress->id . 'User:'.$user->id.
                  ' Email not sent - past usefulness deadline.');
        return true;
    }

    debugging('', DEBUG_DEVELOPER) && mtrace('Reengagement modules: User:'.$user->id.' Sending email.');

    $templateddetails = reengagement_template_variables($reengagement, $inprogress, $user);
    $plaintext = html_to_text($templateddetails['emailcontent']);

    $emailresult = true;
    if ($istotara &&
        ($reengagement->emailrecipient == REENGAGEMENT_RECIPIENT_MANAGER) ||
        ($reengagement->emailrecipient == REENGAGEMENT_RECIPIENT_BOTH)) {
        // We're supposed to email the user's manager(s).
        $managerids = \totara_job\job_assignment::get_all_manager_userids($user->id);
        if (empty($managerids)) {
            // User has no manager(s).
            debugging('', DEBUG_ALL) && mtrace("user $user->id has no managers - not sending any manager emails.");
        } else {
            // User has manager(s).
            foreach ($managerids as $managerid) {
                $manager = $DB->get_record('user', array('id' => $managerid));
                $managersendresult = reengagement_send_notification($manager,
                    $templateddetails['emailsubjectmanager'],
                    html_to_text($templateddetails['emailcontentmanager']),
                    $templateddetails['emailcontentmanager'],
                    $reengagement
                );
                if (!$managersendresult) {
                    mtrace("failed to send manager of user $user->id email for reengagement $reengagement->id");
                }
                $emailresult = $emailresult && $managersendresult;
            }
        }
    }
    if (($reengagement->emailrecipient == REENGAGEMENT_RECIPIENT_USER) ||
        ($reengagement->emailrecipient == REENGAGEMENT_RECIPIENT_BOTH)) {
        // We are supposed to send email to the user.
        $usersendresult = reengagement_send_notification($user,
            $templateddetails['emailsubject'],
            $plaintext,
            $templateddetails['emailcontent'],
            $reengagement
        );
        if (!$usersendresult) {
            mtrace("failed to send user $user->id email for reengagement $reengagement->id");
        }
        $emailresult = $emailresult && $usersendresult;
    }

    if (!empty($reengagement->thirdpartyemails)) {
        // Process third-party emails.
        $emails = array_map('trim', explode(',', $reengagement->thirdpartyemails));
        foreach ($emails as $emailaddress) {
            if (!validate_email($emailaddress)) {
                debugging('', DEBUG_ALL) && mtrace("invalid third-party email: $email - skipping send");
                continue;
            }
            if ($istotara) {
                $thirdpartyuser = \totara_core\totara_user::get_external_user($emailaddress);
            } else {
                $thirdpartyuser = core_user::get_noreply_user();
                $thirdpartyuser->firstname = $emailaddress;
                $thirdpartyuser->email = $emailaddress;
                $thirdpartyuser->maildisplay = 1;
                $thirdpartyuser->emailstop = 0;
            }

            debugging('', DEBUG_ALL) && mtrace("sending third-party email to: $emailaddress");

            $usersendresult = reengagement_send_notification($thirdpartyuser,
                    $templateddetails['emailsubjectthirdparty'],
                    html_to_text($templateddetails['emailcontentthirdparty']),
                    $templateddetails['emailcontentthirdparty'],
                    $reengagement
                );
            if (!$usersendresult) {
                mtrace("failed to send user $user->id email for reengagement $reengagement->id");
            }
            $emailresult = $emailresult && $usersendresult;

        }
    }

    return $emailresult;
}


/**
 * Send reengagement notifications using the messaging system.
 *
 * @param object $user
 * @param string $subject message subject
 * @param string $messageplain plain text message
 * @param string $messagehtml html message
 * @param object $reengagement database record
 */
function reengagement_send_notification($userto, $subject, $messageplain, $messagehtml, $reengagement) {
    $eventdata = new \core\message\message();
    $eventdata->courseid = $reengagement->courseid;
    $eventdata->modulename = 'reengagement';
    $eventdata->userfrom = core_user::get_support_user();
    $eventdata->userto = $userto;
    $eventdata->subject = $subject;
    $eventdata->fullmessage = $messageplain;
    $eventdata->fullmessageformat = FORMAT_HTML;
    $eventdata->fullmessagehtml = $messagehtml;
    $eventdata->smallmessage = $subject;

    // Required for messaging framework
    $eventdata->name = 'mod_reengagement';
    $eventdata->component = 'mod_reengagement';

    return message_send($eventdata);
}


/**
 * Template variables into place in supplied email content.
 *
 * @param object $reengagement db record of details for this activity
 * @param object $inprogress record of user participation in this activity - semiplanned future enhancement.
 * @param object $user record of user being reengaged.
 * @return array - the content of the fields after templating.
 */
function reengagement_template_variables($reengagement, $inprogress, $user) {
    $templatevars = array(
        '/%courseshortname%/' => $reengagement->courseshortname,
        '/%coursefullname%/' => $reengagement->coursefullname,
        '/%courseid%/' => $reengagement->courseid,
        '/%userfirstname%/' => $user->firstname,
        '/%userlastname%/' => $user->lastname,
        '/%userid%/' => $user->id,
        '/%usercity%/' => $user->city,
        '/%userinstitution%/' => $user->institution,
        '/%userdepartment%/' => $user->department,
    );
    $patterns = array_keys($templatevars); // The placeholders which are to be replaced.
    $replacements = array_values($templatevars); // The values which are to be templated in for the placeholders.

    // Array to describe which fields in reengagement object should have a template replacement.
    $replacementfields = array('emailsubject', 'emailcontent', 'emailsubjectmanager', 'emailcontentmanager',
                               'emailsubjectthirdparty', 'emailcontentthirdparty');

    $results = array();
    // Replace %variable% with relevant value everywhere it occurs in reengagement->field.
    foreach ($replacementfields as $field) {
        $results[$field] = preg_replace($patterns, $replacements, $reengagement->$field);
    }
    return $results;
}

/**
 * Must return an array of user records (all data) who are participants
 * for a given instance of reengagement. Must include every user involved
 * in the instance, independient of his role (student, teacher, admin...)
 * See other modules as example.
 *
 * @param int $reengagementid ID of an instance of this module
 * @return mixed boolean/array of students
 */
function reengagement_get_participants($reengagementid) {
    return false;
}


/**
 * Checks if a scale is being used.
 *
 * This is used by the backup code to decide whether to back up a scale
 * @param int $reengagementid
 * @param int $scaleid
 * @return boolean True if the scale is used by the assignment
 */
function reengagement_scale_used($reengagementid, $scaleid) {
    $return = false;

    return $return;
}


/**
 * This is used to find out if scale used anywhere.
 *
 * @param int $scaleid
 * @return boolean True if the scale is used by any reengagement
 */
function reengagement_scale_used_anywhere($scaleid) {
    return false;
}


/**
 * Execute post-install custom actions for the module
 * This function was added in 1.9
 *
 * @return boolean true if success, false on error
 */
function reengagement_install() {
    return true;
}


/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the choice.
 *
 * @param object $mform form passed by reference
 */
function reengagement_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'reengagementheader', get_string('modulenameplural', 'reengagement'));
}

/**
 * Course reset form defaults.
 *
 * @param object $course
 * @return array
 */
function reengagement_reset_course_form_defaults($course) {
    return array('reset_reengagement' => 1);
}

/**
 * Actual implementation of the reset course functionality, delete all the
 * choice responses for course $data->courseid.
 *
 * @param object $data the data submitted from the reset course.
 * @return array status array
 */
function reengagement_reset_userdata($data) {
    global $DB;

    $componentstr = get_string('modulenameplural', 'reengagement');
    $status = array();

    if (!empty($data->reset_reengagement)) {
        $reengagementsql = "SELECT ch.id
                       FROM {reengagement} ch
                       WHERE ch.course=?";

        $DB->delete_records_select('reengagement_inprogress', "reengagement IN ($reengagementsql)", array($data->courseid));
        $status[] = array('component' => $componentstr, 'item' => get_string('removeresponses', 'reengagement'), 'error' => false);
    }

    return $status;
}

/**
 * Get array of users who can start supplied reengagement module
 *
 * @param object $reengagement - reengagement record.
 * @return array
 */
function reengagement_get_startusers($reengagement) {
    global $DB;
    $context = context_module::instance($reengagement->cmid);

    list($esql, $params) = get_enrolled_sql($context, 'mod/reengagement:startreengagement', 0, true);

    // Get a list of people who already started this reengagement (finished users are included in this list)
    // (based on activity completion records).
    $alreadycompletionsql = "SELECT userid
                               FROM {course_modules_completion}
                              WHERE coursemoduleid = :alcmoduleid";
    $params['alcmoduleid'] = $reengagement->id;

    // Get a list of people who already started this reengagement
    // (based on reengagement_inprogress records).
    $alreadyripsql = "SELECT userid
                        FROM {reengagement_inprogress}
                       WHERE reengagement = :ripmoduleid";
    $params['ripmoduleid'] = $reengagement->rid;

    $sql = "SELECT u.*
              FROM {user} u
              JOIN ($esql) je ON je.id = u.id
             WHERE u.deleted = 0
             AND u.id NOT IN ($alreadycompletionsql)
             AND u.id NOT IN ($alreadyripsql)";

    $startusers = $DB->get_records_sql($sql, $params);

    $modinfo = get_fast_modinfo($reengagement->courseid);
    $cm = $modinfo->get_cm($reengagement->cmid);
    $ainfomod = new \core_availability\info_module($cm);
    foreach ($startusers as $startcandidate) {
        $information = '';
        if (empty($startcandidate->confirmed)) {
            // Exclude unconfirmed users. Typically this shouldn't happen, but if an unconfirmed user
            // has been enrolled to a course we shouldn't e-mail them about activities they can't access yet.
            unset($startusers[$startcandidate->id]);
            continue;
        }
        // Exclude users who can't see this activity.
        if (!$ainfomod->is_available($information, false, $startcandidate->id, $modinfo)) {
            unset($startusers[$startcandidate->id]);
        }
    }

    return $startusers;
}


/**
 * Return the list of Moodle features this module supports
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
 */
function reengagement_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:
            return false;
        case FEATURE_GROUPINGS:
            return false;
        case FEATURE_MOD_INTRO:
            return false;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return false;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_GRADE_OUTCOMES:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;

        default:
            return null;
    }
}

/**
 * Process an arbitary number of seconds, and prepare to display it as X minutes, or Y hours or Z weeks.
 *
 * @param int $duration FEATURE_xx constant for requested feature
 * @param boolean $periodstring - return period as string.
 * @return array
 */
function reengagement_get_readable_duration($duration, $periodstring = false) {
    if ($duration < 300) {
        $period = 60;
        $periodcount = 5;
    } else {
        $periods = array(604800, 86400, 3600, 60);
        foreach ($periods as $period) {
            if ((($duration % $period) == 0) || ($period == 60)) {
                // Duration divides exactly into periods, or have reached the min. sensible period.
                $periodcount = floor((int)$duration / (int)$period);
                break;
            }
        }
    }
    if ($periodstring) {
        if ($period == 60) {
            $period = get_string('minutes', 'reengagement');
        } else if ($period == 3600) {
            $period = get_string('hours', 'reengagement');
        } else if ($period == 86400) {
            $period = get_string('days', 'reengagement');
        } else if ($period == 604800) {
            $period = get_string('weeks', 'reengagement');
        }
    }
    return array($periodcount, $period); // Example 5, 60 is 5 minutes.
}

/**
 * Check if user has completed the named course moduleid
 * @param int $userid idnumber of the user to be checked.
 * @param int $targetcmid the id of the coursemodule we should be checking.
 * @return bool true if user has completed the target activity, false otherwise.
 */
function reengagement_check_target_completion($userid, $targetcmid) {
    global $DB;
    // This reengagement is focused on getting people to do a particular (ie targeted) activity.
    // Behaviour of the module changes depending on whether the target activity is already complete.
    $conditions = array('userid' => $userid, 'coursemoduleid' => $targetcmid);
    $activitycompletion = $DB->get_record('course_modules_completion', $conditions);
    if ($activitycompletion) {
        // There is a target activity, and completion is enabled in that activity.
        $userstate = $activitycompletion->completionstate;
        if (in_array($userstate, array(COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS, COMPLETION_COMPLETE_FAIL))) {
            return true;
        }
    }
    return false;
}

/**
 * Method to check if existing user is eligble and cron hasn't run yet.
 * @param stdclass $course the course record.
 * @param stdclass $cm the coursemodule we should be checking.
 * @param stdclass $reengagment the full record.
 * @return string
 */
function reengagement_checkstart($course, $cm, $reengagement) {
    global $DB, $USER, $OUTPUT;
    $output = '';
    $modinfo = get_fast_modinfo($course->id);
    $cminfo = $modinfo->get_cm($cm->id);

    $ainfomod = new \core_availability\info_module($cminfo);

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
            $output .= $OUTPUT->box($report);
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
                $targetcomplete = reengagement_check_target_completion($USER->id, $cm->id);
                if (!$targetcomplete) {
                    // Message will be sent at xyz time unless you complete target activity.
                    $emailmessage = get_string('receiveemailattimexunless', 'reengagement', $datestr);
                } else {
                    // Message scheduled for xyz time will not be sent because you have completed the target activity.
                    $emailmessage = get_string('noemailattimex', 'reengagement', $datestr);
                }
            }
            $output .= $OUTPUT->box($emailmessage);
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
        $output .= $OUTPUT->box($completionmessage);
    }
    return $output;
}