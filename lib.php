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

    $reengagementssql = "SELECT cm.id as id, cm.id as cmid, r.id as rid, r.duration, r.emaildelay
                      FROM {reengagement} r
                      INNER JOIN {course_modules} cm on cm.instance = r.id
                      WHERE cm.module = :moduleid";

    $reengagements = $DB->get_records_sql($reengagementssql, array("moduleid" => $reengagementmod->id));

    if (empty($reengagements)) {
        //No reengagement module instances in a course
        // Since there's no need to create reengagement-inprogress records, or send emails:
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
        mtrace("Adding " . count($userlist) . " reengagements-in-progress to reengagementid " . $reengagementcm->rid);

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

    $reengagementssql = "SELECT r.id as id, cm.id as cmid, r.emailcontent, r.emailcontentformat, r.emailsubject,
                          r.emailuser, r.name, r.suppresstarget
                      FROM {reengagement} r
                      INNER JOIN {course_modules} cm ON cm.instance = r.id
                      WHERE cm.module = :moduleid";

    $reengagements = $DB->get_records_sql($reengagementssql, array('moduleid' => $reengagementmod->id));

    $inprogresses = $DB->get_records_select('reengagement_inprogress', 'completiontime < ' . $timenow . ' AND completed = 0');
    mtrace("Found " . count($inprogresses) . " complete reengagements, emailing as appropriate");
    foreach ($inprogresses as $inprogress) {
        // A user has completed an instance of the reengagement module.
        $inprogress->timedue = $inprogress->completiontime;
        $reengagement = $reengagements[$inprogress->reengagement];
        $cmid = $reengagement->cmid; // The cm id of the module which was completed.
        $userid = $inprogress->userid; // The userid which completed the module.

        // Update completion record to indicate completion so the user can continue with any dependant activities.
        $completionrecord = $DB->get_record('course_modules_completion', array('coursemoduleid' => $cmid, 'userid' => $userid));
        if (empty($completionrecord)) {
            // Unexpected error.
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
            mtrace("mode $reengagement->emailuser reengagement $reengagement->id deleting inprogress record for user $userid");
            $result = $DB->delete_records('reengagement_inprogress', array('id' => $inprogress->id));
        } else {
            // Update inprogress record to indicate completion done.
            mtrace("mode $reengagement->emailuser reengagement $reengagement->id updating inprogress record for user $userid to indicate completion");
            $updaterecord = new stdClass();
            $updaterecord->id = $inprogress->id;
            $updaterecord->completed = COMPLETION_COMPLETE;
            $result = $DB->update_record('reengagement_inprogress', $updaterecord);
        }
        if (empty($result)) {
            // Skip emailing. Go on to next completion record so we don't risk emailing users continuously each cron.
            continue;
        }
        if ($reengagement->emailuser == REENGAGEMENT_EMAILUSER_COMPLETION) {
            reengagement_email_user($reengagement, $inprogress);
        }
    }

    // Get inprogress records where the user has reached their email time, and module is email 'after delay'.
    $inprogresssql = "SELECT ip.*, ip.emailtime as timedue
                      FROM {reengagement_inprogress} ip
                          INNER JOIN {reengagement} r on r.id = ip.reengagement
                      WHERE ip.emailtime < :emailtime
                          AND r.emailuser = " . REENGAGEMENT_EMAILUSER_TIME . '
                          AND ip.emailsent = 0';
    $params = array('emailtime' => $timenow);

    $inprogresses = $DB->get_records_sql($inprogresssql, $params);
    foreach ($inprogresses as $inprogress) {
        $reengagement = $reengagements[$inprogress->reengagement];
        if ($inprogress->completed == COMPLETION_COMPLETE) {
            $result = $DB->delete_records('reengagement_inprogress', array('id' => $inprogress->id));
        } else {
            $updaterecord = new stdClass();
            $updaterecord->id = $inprogress->id;
            $updaterecord->emailsent = 1;
            $result = $DB->update_record('reengagement_inprogress', $updaterecord);
        }
        if (!empty($result)) {
            reengagement_email_user($reengagement, $inprogress);
        }
    }

    return true;
}

function reengagement_email_user($reengagement, $inprogress) {
    global $DB, $SITE, $CFG;
    $user = $DB->get_record('user', array('id' =>  $inprogress->userid));
    $sendemail = true;
    if (!empty($reengagement->suppresstarget)) {
        // This reengagement is focused on getting people to do a particular (ie targeted) activity.
        // If that target activity is already complete, suppress the would-be email.
        $conditions = array('userid'=>$user->id, 'coursemoduleid'=>$reengagement->suppresstarget);
        $activitycompletion = $DB->get_record('course_modules_completion', $conditions);
        if ($activitycompletion) {
            // There is a target activity, and completion is enabled in that activity.
            $userstate = $activitycompletion->completionstate;
            if (in_array($userstate, array(COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS, COMPLETION_COMPLETE_FAIL))) {
                mtrace('Reengagement modules: User:'.$user->id.' has completed target activity:'.$reengagement->suppresstarget.' suppressing email.');
                return true;
            }
        }
    }
    // Where cron isn't run regularly, we could get a glut requests to send email that are either ancient, or too late to be useful.
    if (!empty($inprogress->timedue) && (($inprogress->timedue + 2 * DAYSECS) < time())) {
        // We should have sent this email more than two days ago.
        // Don't send.
        mtrace('Reengagement: ip id ' . $inprogress->id . 'User:'.$user->id.' Email not sent - was due more than 2 days ago.');
        return true;
    }
    if (!empty($inprogress->timeoverdue) && ($inprogress->timeoverdue < time())) {
        // There's a deadline hint provided, and we're past it.
        // Don't send.
        mtrace('Reengagement: ip id ' . $inprogress->id . 'User:'.$user->id.' Email not sent - past usefulness deadline.');
        return true;
    }

    mtrace('Reengagement modules: User:'.$user->id.' Sending email.');
    if (!empty($reengagement->emailsubject)) {
        $emailsubject = $reengagement->emailsubject;
    } else {
        $emailsubject = $SITE->shortname . ": " . $reengagement->name . " is complete";
    }
    $emailsenduser = new stdClass();
    $emailsenduser->firstname = $SITE->shortname;
    $emailsenduser->lastname = '';
    $emailsenduser->email = $CFG->noreplyaddress;
    $emailsenduser->maildisplay = false;
    $plaintext = html_to_text($reengagement->emailcontent);
    $result = email_to_user($user, $emailsenduser, $emailsubject, $plaintext, $reengagement->emailcontent);
    return $result;
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
    Take a condition from course_modules_availability table, and examine state to see which users comply
    return array of compliant userid numbers
*/
function get_compliant_users($condition) {
    global $CFG, $DB;

    require_once($CFG->libdir."/completionlib.php");

    $params = array();
    if (!empty($condition->sourcecmid)) {
        // This condition relates to the completion status of another cm
        $completionsql = "SELECT cmc.userid, cmc.userid AS junk
                          FROM {course_modules_completion} cmc
                          WHERE coursemoduleid = :coursemoduleid ";

        $params["coursemoduleid"] = $condition->sourcecmid;

        if (isset($condition->requiredcompletion)) {
            if ($condition->requiredcompletion == COMPLETION_COMPLETE) {
                $completionsql .= ' AND (completionstate = ' . COMPLETION_COMPLETE .
                        ' OR completionstate=' . COMPLETION_COMPLETE_PASS .
                        ' OR completionstate = ' . COMPLETION_COMPLETE_FAIL .
                        ' )';
            } else {
                $completionsql .= ' AND completionstate = ' . $condition->requiredcompletion;
                $params["completionstate"] = $condition->requiredcompletion;
            }
        }

        $compliantusers = $DB->get_records_sql($completionsql, $params);

    } else {
        //This condition relates to the grade attained in a grade item
        $gradessql = "SELECT *
                      FROM {grade_grades} gg
                      WHERE gg.itemid = :gradeitemid ";

        $params["gradeitemid"] = $condition->gradeitemid;

        if (isset($condition->grademin)) {
            $gradessql .= "AND gg.finalgrade >= :grademin ";
            $params["grademin"] = $condition->grademin;
        }
        if (isset($condition->grademax)) {
            $gradessql .= "AND gg.finalgrade <= :grademax ";
            $params["grademax"] = $condition->grademax;
        }
        $compliantusers = $DB->get_records_sql($gradessql, $params);
    }
    return $compliantusers;
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
    $context = get_context_instance(CONTEXT_MODULE, $reengagement->cmid);
    $startusers = get_users_by_capability($context, 'mod/reengagement:startreengagement',
            'u.id, email, firstname, lastname, emailstop', '', '', '', '', '', false);

    $conditions = $DB->get_records('course_modules_availability', array('coursemoduleid' => $reengagement->cmid));
    while (!empty($conditions)) {
        $condition = array_shift($conditions);
        //Get a list of users who are compliant with this condition.
        $compliantusers = get_compliant_users($condition);
        // Run over list of startable users, and remove those that aren't compliant with this condition.
        $userlist = array_keys($startusers);
        foreach ($userlist as $userid) {
            if (!isset($compliantusers[$userid])) {
                unset($startusers[$userid]);
            }
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
