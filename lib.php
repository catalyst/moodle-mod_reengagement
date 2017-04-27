<?php

/**
 * Library of functions and constants for module reengagement
 * This file should have two well differenced parts:
 *   - All the core Moodle functions, neeeded to allow
 *     the module to work integrated in Moodle.
 *   - All the reengagement specific functions, needed
 *     to implement all the module logic. Please, note
 *     that, if the module become complex and this lib
 *     grows a lot, it's HIGHLY recommended to move all
 *     these module specific functions to a new php file,
 *     called "locallib.php" (see forum, quiz...). This will
 *     help to save some memory when Moodle is performing
 *     actions across all modules.
 */

defined('MOODLE_INTERNAL') || die();
/// (replace reengagement with the name of your module and delete this line)
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

    //if they didn't chose to suppress email, do nothing
    if (!$reengagement->suppressemail) {
        $reengagement->suppresstarget = 0;//no target to be set
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

    # Delete any dependent records here #
    if (! $DB->delete_records('reengagement_inprogress', array('reengagement' => $reengagement->id))) {
        $result = false;
    }

    if (! $DB->delete_records('reengagement', array('id' => $reengagement->id))) {
        $result = false;
    }

    return $result;
}


/**
 * Return a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @return null
 * @todo Finish documenting this function
 */
function reengagement_user_outline($course, $user, $mod, $reengagement) {
    return $return;
}


/**
 * Print a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @return boolean
 * @todo Finish documenting this function
 */
function reengagement_user_complete($course, $user, $mod, $reengagement) {
    return true;
}

/**
 * Obtains the automatic completion state for this forum based on any conditions
 * in forum settings.
 *
 * @global object
 * @global object
 * @param object $course Course
 * @param object $cm Course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not. (If no conditions, then return
 *   value depends on comparison type)
 */
function reengagement_get_completion_state($course, $cm, $userid, $type) {
    global $DB;
    if ($completion = $DB->get_record('course_modules_completion', array('coursemoduleid'=>$cm->id, 'userid'=>$userid))) {
        return $completion->completionstate == COMPLETION_COMPLETE_PASS;
    }
    return false;
}
/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in reengagement activities and print it out.
 * Return true if there was output, or false is there was none.
 *
 * @return boolean
 * @todo Finish documenting this function
 */
function reengagement_print_recent_activity($course, $isteacher, $timestart) {
    return false;  //  True if anything was printed, otherwise false
}


/**
 * Function to be run periodically according to the moodle cron
 * * Add users who can start this module to the 'reengagement_inprogress' table
 *   and add an entry to the activity completion table to indicate that they have started
 * * Check the reengagement_inprogress table for users who have completed thieir reengagement
 *   and mark their activity completion as being complete
 *   and send an email if the reengagement instance calls for it.
 * @return boolean
 **/
function reengagement_cron() {
    global $CFG, $SITE, $DB;

    require_once($CFG->libdir."/completionlib.php");

    //Get a consistent 'timenow' value across this whole function:
    $timenow = time();
    $reengagementmod = $DB->get_record('modules', array('name' => 'reengagement'));

    $reengagementssql =
            "SELECT cm.id as id, cm.id as cmid, cm.availability, r.id as rid, r.course as courseid, r.duration, r.emaildelay
               FROM {reengagement} r
         INNER JOIN {course_modules} cm on cm.instance = r.id
              WHERE cm.module = :moduleid
           ORDER BY r.id ASC";

    $reengagements = $DB->get_records_sql($reengagementssql, array("moduleid" => $reengagementmod->id));

    if (empty($reengagements)) {
        //No reengagement module instances in a course
        // Since there's no need to create reengagement-inprogress records, or send emails:
        mtrace("No reengagement instances found - nothing to do :)");
        return true;
    }

    // First: add 'in-progress' records for those users who are able to start.
    foreach ($reengagements as $reengagementcm) {
        // Get a list of users who are eligible to start this module.
        $startusers = reengagement_get_startusers($reengagementcm);

        // Prepare some objects for later db insertion
        $reengagement_inprogress = new stdClass();
        $reengagement_inprogress->reengagement = $reengagementcm->rid;
        $reengagement_inprogress->completiontime = $timenow + $reengagementcm->duration;
        $reengagement_inprogress->emailtime = $timenow + $reengagementcm->emaildelay;
        $activity_completion = new stdClass();
        $activity_completion->coursemoduleid = $reengagementcm->cmid;
        $activity_completion->completionstate = COMPLETION_INCOMPLETE;
        $activity_completion->timemodified = $timenow;
        $userlist = array_keys($startusers);
        $newripcount = count($userlist); // Count of new reengagements-in-progress.
        if (debugging('', DEBUG_DEVELOPER) || ($newripcount && debugging('', DEBUG_ALL))) {
            mtrace("Adding $newripcount reengagements-in-progress to reengagementid " . $reengagementcm->rid);
        }

        foreach ($userlist as $userid) {
            $reengagement_inprogress->userid = $userid;
            $DB->insert_record('reengagement_inprogress', $reengagement_inprogress);
            $activity_completion->userid = $userid;
            $DB->insert_record('course_modules_completion', $activity_completion);
        }
    }
    // All new users have now been recorded as started.
    // See if any previous users are due to finish, &/or be emailed.

    // Get more info about the activity, & prepare to update db
    // and email users.

    $reengagementssql =
            "SELECT r.id as id, cm.id as cmid, r.emailcontent, r.emailcontentformat, r.emailsubject,
              r.emailcontentmanager, r.emailcontentmanagerformat, r.emailsubjectmanager,
              r.emailuser, r.name, r.suppresstarget, c.shortname as courseshortname,
              c.fullname as coursefullname, c.id as courseid, r.emailrecipient
               FROM {reengagement} r
         INNER JOIN {course_modules} cm ON cm.instance = r.id
         INNER JOIN {course} c ON cm.course = c.id
              WHERE cm.module = :moduleid
           ORDER BY r.id ASC";

    $reengagements = $DB->get_records_sql($reengagementssql, array('moduleid' => $reengagementmod->id));

    $inprogresses = $DB->get_records_select('reengagement_inprogress', 'completiontime < ' . $timenow . ' AND completed = 0');
    $completeripcount = count($inprogresses);
    if (debugging('', DEBUG_DEVELOPER) || ($completeripcount && debugging('', DEBUG_ALL))) {
        mtrace("Found $completeripcount complete reengagements.");
    }
    foreach ($inprogresses as $inprogress) {
        // A user has completed an instance of the reengagement module.
        $inprogress->timedue = $inprogress->completiontime;
        $reengagement = $reengagements[$inprogress->reengagement];
        $cmid = $reengagement->cmid; // The cm id of the module which was completed.
        $userid = $inprogress->userid; // The userid which completed the module.

        // Check if user is still enrolled in the course.
        $context = context_module::instance($reengagement->cmid);
        if (!is_enrolled($context, $userid, 'mod/reengagement:startreengagement', true)) {
            $DB->delete_records('reengagement_inprogress', array('id' => $inprogresses->id));
            continue;
        }

        // Update completion record to indicate completion so the user can continue with any dependant activities.
        $completionrecord = $DB->get_record('course_modules_completion', array('coursemoduleid' => $cmid, 'userid' => $userid));
        if (empty($completionrecord)) {
            // Unexpected error.
            mtrace("Could not find completion record for updating to complete state - userid: $userid, cmid: $cmid");
            continue;
        }
        $updaterecord = new stdClass();
        $updaterecord->id = $completionrecord->id;
        $updaterecord->completionstate = COMPLETION_COMPLETE_PASS;
        $updaterecord->timemodified = $timenow;
        $DB->update_record('course_modules_completion', $updaterecord) . " \n";
        $result = false;
        if (($reengagement->emailuser == REENGAGEMENT_EMAILUSER_COMPLETION) ||
                ($reengagement->emailuser == REENGAGEMENT_EMAILUSER_NEVER) ||
                ($reengagement->emailuser == REENGAGEMENT_EMAILUSER_TIME && !empty($inprogress->emailsent))) {
            // No need to keep 'inprogress' record for later emailing
            // Delete inprogress record.
            debugging('', DEBUG_DEVELOPER) && mtrace("mode $reengagement->emailuser reengagementid $reengagement->id. User marked complete, deleting inprogress record for user $userid");
            $result = $DB->delete_records('reengagement_inprogress', array('id' => $inprogress->id));
        } else {
            // Update inprogress record to indicate completion done.
            debugging('', DEBUG_DEVELOPER) && mtrace("mode $reengagement->emailuser reengagementid $reengagement->id updating inprogress record for user $userid to indicate completion");
            $updaterecord = new stdClass();
            $updaterecord->id = $inprogress->id;
            $updaterecord->completed = COMPLETION_COMPLETE;
            $result = $DB->update_record('reengagement_inprogress', $updaterecord);
        }
        if (empty($result)) {
            // Skip emailing. Go on to next completion record so we don't risk emailing users continuously each cron.
            debugging('', DEBUG_ALL) && mtrace("Reengagement: not sending email to $userid regarding reengagementid $reengagement->id due to failuer to update db");
            continue;
        }
        if ($reengagement->emailuser == REENGAGEMENT_EMAILUSER_COMPLETION) {
            debugging('', DEBUG_ALL) && mtrace("Reengagement: sending email to $userid regarding reengagementid $reengagement->id due to completion.");
            reengagement_email_user($reengagement, $inprogress);
        }
    }

    // Get inprogress records where the user has reached their email time, and module is email 'after delay'.
    $inprogresssql =
            "SELECT ip.*, ip.emailtime as timedue
               FROM {reengagement_inprogress} ip
         INNER JOIN {reengagement} r on r.id = ip.reengagement
              WHERE ip.emailtime < :emailtime
                AND r.emailuser = " . REENGAGEMENT_EMAILUSER_TIME . '
                AND ip.emailsent = 0
           ORDER BY r.id ASC';
    $params = array('emailtime' => $timenow);

    $inprogresses = $DB->get_records_sql($inprogresssql, $params);
    $emailduecount = count($inprogresses);
    if (debugging('', DEBUG_DEVELOPER) || ($emailduecount && debugging('', DEBUG_ALL))) {
        mtrace("Found $emailduecount reengagements due to be emailed.");
    }
    foreach ($inprogresses as $inprogress) {
        $reengagement = $reengagements[$inprogress->reengagement];
        $userid = $inprogress->userid; // The userid which completed the module.

        // Check if user is still enrolled in the course.
        $context = context_module::instance($reengagement->cmid);
        if (!is_enrolled($context, $userid, 'mod/reengagement:startreengagement', true)) {
            $DB->delete_records('reengagement_inprogress', array('id' => $inprogresses->id));
            continue;
        }

        if ($inprogress->completed == COMPLETION_COMPLETE) {
            debugging('', DEBUG_DEVELOPER) && mtrace("mode $reengagement->emailuser reengagementid $reengagement->id. User already marked complete. Deleting inprogress record for user $userid");
            $result = $DB->delete_records('reengagement_inprogress', array('id' => $inprogress->id));
        } else {
            debugging('', DEBUG_DEVELOPER) && mtrace("mode $reengagement->emailuser reengagementid $reengagement->id. Updating inprogress record to indicate email sent for user $userid");
            $updaterecord = new stdClass();
            $updaterecord->id = $inprogress->id;
            $updaterecord->emailsent = 1;
            $result = $DB->update_record('reengagement_inprogress', $updaterecord);
        }
        if (!empty($result)) {
            debugging('', DEBUG_ALL) && mtrace("Reengagement: sending email to $userid regarding reengagementid $reengagement->id due to emailduetime.");
            reengagement_email_user($reengagement, $inprogress);
        }
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
    $usersql = "SELECT u.*, manager.id as mid, manager.firstname as mfirstname,
                        manager.lastname as mlastname, manager.email as memail,
                        manager.mailformat as mmailformat
                  FROM {user} u
             LEFT JOIN {pos_assignment} pa ON u.id = pa.userid and pa.type = " . POSITION_TYPE_PRIMARY . "
             LEFT JOIN {user} manager ON pa.managerid = manager.id
                 WHERE u.id = :userid";
    $params = array('userid' => $inprogress->userid);
    $user = $DB->get_record_sql($usersql, $params);
    if (!empty($reengagement->suppresstarget)) {
        $targetcomplete = reengagement_check_target_completion($user->id, $reengagement->suppresstarget);
        if ($targetcomplete) {
            debugging('', DEBUG_DEVELOPER) && mtrace('Reengagement modules: User:'.$user->id.' has completed target activity:'.$reengagement->suppresstarget.' suppressing email.');
            return true;
        }
    }
    // Where cron isn't run regularly, we could get a glut requests to send email that are either ancient, or too late to be useful.
    if (!empty($inprogress->timedue) && (($inprogress->timedue + 2 * DAYSECS) < time())) {
        // We should have sent this email more than two days ago.
        // Don't send.
        debugging('', DEBUG_ALL) && mtrace('Reengagement: ip id ' . $inprogress->id . 'User:'.$user->id.' Email not sent - was due more than 2 days ago.');
        return true;
    }
    if (!empty($inprogress->timeoverdue) && ($inprogress->timeoverdue < time())) {
        // There's a deadline hint provided, and we're past it.
        // Don't send.
        debugging('', DEBUG_ALL) && mtrace('Reengagement: ip id ' . $inprogress->id . 'User:'.$user->id.' Email not sent - past usefulness deadline.');
        return true;
    }

    debugging('', DEBUG_DEVELOPER) && mtrace('Reengagement modules: User:'.$user->id.' Sending email.');
    if (!empty($reengagement->emailsubject)) {
        $emailsubject = $reengagement->emailsubject;
    } else {
        $emailsubject = $SITE->shortname . ": " . $reengagement->name . " is complete";
    }
    // Create an object which discribes the 'user' who is sending the email.
    $emailsenduser = new stdClass();
    $emailsenduser->firstname = $SITE->shortname;
    $emailsenduser->lastname = '';
    $emailsenduser->email = $CFG->noreplyaddress;
    $emailsenduser->maildisplay = false;

    $templateddetails = reengagement_template_variables($reengagement, $inprogress, $user);
    $plaintext = html_to_text($templateddetails['emailcontent']);

    $emailresult = true;
    if (($reengagement->emailrecipient == REENGAGEMENT_RECIPIENT_MANAGER) || ($reengagement->emailrecipient == REENGAGEMENT_RECIPIENT_BOTH)) {
        // We're supposed to email the user's manager.
        if (empty($user->mid)) {
            // ... but the user doesn't have a manager.
            debugging('', DEBUG_ALL) && mtrace("user $user->id has no manager present - unable to send email to manager");
        } else {
            // User has a manager.
            // Create a shell user which contains what we know about the manager.
            $manager = new stdClass();
            $fieldnames = array('id', 'firstname', 'lastname', 'email', 'mailformat');
            foreach($fieldnames as $fieldname) {
                $mfieldname = 'm' . $fieldname;
                $manager->$fieldname = $user->$mfieldname;
            }
            // Actually send the email.
            $managersendresult = email_to_user($manager,
                    $emailsenduser,
                    $templateddetails['emailsubjectmanager'],
                    $plaintext,
                    $templateddetails['emailcontentmanager']);
            if (!$managersendresult) {
                mtrace("failed to send manager of user $user->id email for reengagement $reengagement->id");
            }
            $emailresult = $emailresult && $managersendresult;
        }
    }
    if (($reengagement->emailrecipient == REENGAGEMENT_RECIPIENT_USER) || ($reengagement->emailrecipient == REENGAGEMENT_RECIPIENT_BOTH)) {
        // We are supposed to send email to the user.
        $usersendresult = email_to_user($user,
                $emailsenduser,
                $templateddetails['emailsubject'],
                $plaintext,
                $templateddetails['emailcontent']);
        if (!$usersendresult) {
            mtrace("failed to send user $user->id email for reengagement $reengagement->id");
        }
        $emailresult = $emailresult && $usersendresult;
    }
    return $emailresult;
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
    );
    $patterns = array_keys($templatevars); // The placeholders which are to be replaced.
    $replacements = array_values($templatevars); // The values which are to be templated in for the placeholders.

    // Array to describe which fields in reengagement object should have a template replacement.
    $replacementfields = array('emailsubject', 'emailcontent', 'emailsubjectmanager', 'emailcontentmanager');

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
 * This function returns if a scale is being used by one reengagement
 * if it has support for grading and scales. Commented code should be
 * modified if necessary. See forum, glossary or journal modules
 * as reference.
 *
 * @param int $reengagementid ID of an instance of this module
 * @return mixed
 * @todo Finish documenting this function
 */
function reengagement_scale_used($reengagementid, $scaleid) {
    $return = false;

    return $return;
}


/**
 * Checks if scale is being used by any instance of reengagement.
 * This function was added in 1.9
 *
 * This is used to find out if scale used anywhere
 * @param $scaleid int
 * @return boolean True if the scale is used by any reengagement
 */
function reengagement_scale_used_anywhere($scaleid) {
    global $DB;

    if ($scaleid and $DB->record_exists('reengagement', array('grade' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
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
 * @return array
 */
function reengagement_reset_course_form_defaults($course) {
    return array('reset_reengagement'=>1);
}

/**
 * Actual implementation of the reset course functionality, delete all the
 * choice responses for course $data->courseid.
 *
 * @global object
 * @global object
 * @param object $data the data submitted from the reset course.
 * @return array status array
 */
function reengagement_reset_userdata($data) {
    global $CFG, $DB;

    $componentstr = get_string('modulenameplural', 'reengagement');
    $status = array();

    if (!empty($data->reset_reengagement)) {
        $reengagementsql = "SELECT ch.id
                       FROM {reengagement} ch
                       WHERE ch.course=?";

        $DB->delete_records_select('reengagement_inprogress', "reengagement IN ($reengagementsql)", array($data->courseid));
        $status[] = array('component'=>$componentstr, 'item'=>get_string('removeresponses', 'reengagement'), 'error'=>false);
    }

    return $status;
}

/* Get array of users who can start supplied reengagement module */
function reengagement_get_startusers($reengagement) {
    global $DB;
    $context = context_module::instance($reengagement->cmid);
    $startusers = get_enrolled_users($context, 'mod/reengagement:startreengagement');

    $cm = get_fast_modinfo($reengagement->courseid)->get_cm($reengagement->cmid);
    $ainfomod = new \core_availability\info_module($cm);
    foreach ($startusers as $startcandidate) {
        $information = '';
        if (!$ainfomod->is_available($information, false, $startcandidate->id)) {
            unset($startusers[$startcandidate->id]);
        }
    }

    //Get a list of people who already started this reengagement (finished users are included in this list)
    // (based on activity completion records).
    $alreadysql = "SELECT userid, userid as junk
                   FROM  {course_modules_completion}
                   WHERE coursemoduleid = :moduleid";
    $alreadyusers = $DB->get_records_sql($alreadysql, array('moduleid' => $reengagement->id));

    // Remove users who have already started the module from the starting list.
    foreach ($alreadyusers as $a_user) {
        if (isset($startusers[$a_user->userid])) {
            unset($startusers[$a_user->userid]);
        }
    }

    //Get a list of people who already started this reengagement
    // (based on reengagement_inprogress records).
    $alreadysql = "SELECT userid, userid as junk
                   FROM  {reengagement_inprogress}
                   WHERE reengagement = :moduleid";
    $alreadyusers = $DB->get_records_sql($alreadysql, array('moduleid' => $reengagement->rid));
    // Remove users who have already started the module from the starting list.
    foreach ($alreadyusers as $a_user) {
        if (isset($startusers[$a_user->userid])) {
            unset($startusers[$a_user->userid]);
        }
    }

    return $startusers;
}


/**
 * @uses FEATURE_GROUPS
 * @uses FEATURE_GROUPINGS
 * @uses FEATURE_GROUPMEMBERSONLY
 * @uses FEATURE_COMPLETION_TRACKS_VIEWS
 * @uses FEATURE_GRADE_HAS_GRADE
 * @uses FEATURE_GRADE_OUTCOMES
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
 */
function reengagement_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:                  return false;
        case FEATURE_GROUPINGS:               return false;
        case FEATURE_GROUPMEMBERSONLY:        return false;
        case FEATURE_MOD_INTRO:               return false;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return false;
        case FEATURE_COMPLETION_HAS_RULES:    return true;
        case FEATURE_GRADE_HAS_GRADE:         return false;
        case FEATURE_GRADE_OUTCOMES:          return false;
        case FEATURE_BACKUP_MOODLE2:          return true;
        case FEATURE_SHOW_DESCRIPTION:        return true;

        default: return null;
    }
}

/**
 * Process an arbitary number of seconds, and prepare to display it as X minutes, or Y hours or Z weeks.
 */
function reengagement_get_readable_duration($duration) {
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
    return array($periodcount, $period); //eg (5,60): 5 minutes.
}

/**
 * Check if user has completed the named course moduleid
 * @param userid integer idnumber of the user to be checked.
 * @param targetcmid integer the id of the coursemodule we should be checking.
 * @return bool true if user has completed the target activity, false otherwise.
 */
function reengagement_check_target_completion($userid, $targetcmid) {
    global $DB;
    // This reengagement is focused on getting people to do a particular (ie targeted) activity.
    // Behaviour of the module changes depending on whether the target activity is already complete.
    $conditions = array('userid'=>$userid, 'coursemoduleid' => $targetcmid);
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

