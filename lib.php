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

/// (replace reengagement with the name of your module and delete this line)
require_once($CFG->libdir."/completionlib.php");

$reengagement_EXAMPLE_CONSTANT = 42;     /// for example


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
    //if they didn't chose to supress email, do nothing
    if(!$reengagement->supressemail) {
        $reengagement->supresstarget = 0;//no target to be set
    }
    unset($reengagement->supressemail);

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

    //if they didn't chose to supress email, do nothing
    if(!$reengagement->supressemail) {
        $reengagement->supresstarget = 0;//no target to be set
    }
    unset($reengagement->supressemail);

    return $DB->update_record('reengagement', $reengagement);
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
    if($completion = $DB->get_record('course_modules_completion', array('coursemoduleid'=>$cm->id, 'userid'=>$userid))) {
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

    $reengagementssql = "SELECT cm.id as id, cm.id as cmid, s.id as sid, s.duration
                      FROM {reengagement} s
                      INNER JOIN {course_modules} cm on cm.instance = s.id
                      WHERE cm.module = :moduleid";

    $reengagements = $DB->get_records_sql($reengagementssql, array("moduleid" => $reengagementmod->id));

    if (empty($reengagements)) {
        //No reengagement module instances in a course
        // Since there's no need to create reengagement-inprogress records, or send emails:
        return true;
    }

    foreach ($reengagements as $reengagementcm) {
        $context = get_context_instance(CONTEXT_MODULE, $reengagementcm->cmid);
        $startableusers = get_users_by_capability($context, 'mod/reengagement:startreengagement',
                'u.id, email, firstname, lastname, emailstop', '', '', '', '', '', false);

        $conditions = $DB->get_records('course_modules_availability', array('coursemoduleid' => $reengagementcm->cmid));

        if(!empty($conditions)) {
            $condition = array_shift($conditions);
            //Get a list of users who will be starting the reengagement
            $compliantusers = get_compliant_users($condition);

            if (empty($compliantusers)) {
                // No users are compliant with the 1st condition
                continue;
            } else {
                $startusers = $compliantusers;
            }

            foreach ($conditions as $condition) {
                $compliantusers = get_compliant_users($condition);
                //If user in overall list is not in new list, remove them from overall list
                $userlist = array_keys($startusers);

                foreach ($userlist as $userid) {
                    if (!isset($compliantusers[$userid])) {
                        unset($startusers[$userid]);
                    }
                }
            }

            //Remove users from the startlist that don't have the startreengagement capability
            $userlist = array_keys($startusers);
            foreach($userlist as $userid) {
                if (!isset($startableusers[$userid])) {
                    unset($startusers[$userid]);
                }
            }

        } else {
            //There are no conditions, so anyone with the capability can start:
            if (!empty($startableusers)) {
                $startusers = array_keys($startableusers);
            } else {
                $startusers = array();
            }
        }

        //Get a list of people who already started this reengagement (finished users are included in this list)
        $alreadysql = "SELECT userid, userid as junk
                       FROM  {course_modules_completion}
                       WHERE coursemoduleid = :moduleid";

        $alreadyusers = $DB->get_records_sql($alreadysql, array('moduleid' => $reengagementcm->id));

        if (!empty($alreadyusers)) {
            foreach ($alreadyusers as $a_user) {
                if (isset($startusers[$a_user->userid])) {
                    unset($startusers[$a_user->userid]);
                }
            }
        }

        if (empty($startusers)) {
            //No users to start for this reengagement
            // go on to next reengagement.
            continue;
        }

        // Prepare some objects for later db insertion
        $reengagement_inprogress = new stdClass();
        $reengagement_inprogress->reengagement = $reengagementcm->sid;
        $reengagement_inprogress->completiontime = time() + $reengagementcm->duration;
        $activity_completion = new stdClass();
        $activity_completion->coursemoduleid = $reengagementcm->cmid;
        $activity_completion->completionstate = COMPLETION_INCOMPLETE;
        $activity_completion->timemodified = $timenow;
        $userlist = array_keys($startusers);
        print "Adding " . count($userlist) .
                " reengagements-in-progress to reengagementid " . $reengagementcm->sid . "\n";

        foreach ($userlist as $userid) {
            $reengagement_inprogress->userid = $userid;
            $DB->insert_record('reengagement_inprogress', $reengagement_inprogress);
            $activity_completion->userid = $userid;
            $DB->insert_record('course_modules_completion', $activity_completion);
        }
    }

    $completereengagements = $DB->get_records_select('reengagement_inprogress', 'completiontime < ' . $timenow);
    if (empty($completereengagements)) {
        return true;
    }

    // There are reengagements 'in progress' that need to be finished
    // Get more info about the stand down, & prepare to update db
    // and email users
    $emailsenduser = new stdClass();
    $emailsenduser->firstname = $SITE->shortname;
    $emailsenduser->lastname = '';
    $emailsenduser->email = $CFG->noreplyaddress;
    $emailsenduser->maildisplay = false;
    $reengagementssql = "SELECT s.id as id, cm.id as cmid, s.admintext, s.usertext, s.emailuser, s.name, s.supresstarget
                      FROM {reengagement} s
                      INNER JOIN {course_modules} cm ON cm.instance = s.id
                      WHERE cm.module = :moduleid";

    $reengagements = $DB->get_records_sql($reengagementssql, array('moduleid' => $reengagementmod->id));

    $reengagementadmins = array();
    mtrace("Found " . count($completereengagements) . " complete reengagements, emailing as appropriate");

    foreach ($completereengagements as $completereengagement) {
        $reengagement = $reengagements[$completereengagement->reengagement];
        $cmid = $reengagement->cmid;
        $userid = $completereengagement->userid;

        //Get the list of reengagement admins for this standown:
        if (!isset($reengagementadmins[$cmid])) {
            $context = get_context_instance(CONTEXT_MODULE, $cmid);
            $reengagementadmins[$cmid] = get_users_by_capability($context,
                    'mod/reengagement:getnotifications',
                    'u.id, email, firstname, lastname, emailstop', '', '', '', '', '', false);
        }

        $completionrecord = $DB->get_record('course_modules_completion', array('coursemoduleid' => $cmid, 'userid' => $userid));

        if (!empty($completionrecord)) {
            $updaterecord = new stdClass();
            $updaterecord->id = $completionrecord->id;
            $updaterecord->completionstate = COMPLETION_COMPLETE_PASS;
            $updaterecord->timemodified = $timenow;
            $DB->update_record('course_modules_completion', $updaterecord) . " \n";
        }


        //Delete the 'inprogress' record, and send emails to user and reengagementadmins as needed
        $deletion = $DB->delete_records('reengagement_inprogress', array('id' => $completereengagement->id));
        if (empty($deletion)) {
            //Deletion failed - don't send email.
            continue;
        }

        $user = $DB->get_record('user', array('id' =>  $completereengagement->userid));
        $email = true;
        if(isset($reengagement->supresstarget) && $reengagement->supresstarget != 0) {
            if($comp = $DB->get_record('course_modules_completion', array('userid'=>$user->id, 'coursemoduleid'=>$reengagement->supresstarget))) {
                //check if they have completVed the course, pass or fail notwith standing
                if(in_array($comp->completionstate, array(COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS, COMPLETION_COMPLETE_FAIL))) {
                    mtrace('User:'.$user->id.' has completed target activity:'.$reengagement->supresstarget.' supressing email');
                    $email = false;
                }
            }
        }
        mtrace('email is :'.$email);
        if ($reengagement->emailuser && $email) {
            $emailsubject = $SITE->shortname . ": " . $reengagement->name . " is complete";
            email_to_user($user, $emailsenduser, $emailsubject, $reengagement->usertext);
        }
        if (!empty($reengagementadmins[$cmid]) && $email) {
            $fullname = fullname($user);
            foreach ($reengagementadmins[$cmid] as $admin) {
                $subject = $SITE->shortname . ": $fullname has completed $reengagement->name.";
                email_to_user($admin, $emailsenduser, $subject, $reengagement->admintext);
            }
        }
    }
    return true;
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
 * Execute post-uninstall custom actions for the module
 * This function was added in 1.9
 *
 * @return boolean true if success, false on error
 */
function reengagement_uninstall() {
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

/**
 * @uses FEATURE_GROUPS
 * @uses FEATURE_GROUPINGS
 * @uses FEATURE_GROUPMEMBERSONLY
 * @uses FEATURE_MOD_INTRO
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
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return false;
        case FEATURE_COMPLETION_HAS_RULES:    return true;
        case FEATURE_GRADE_HAS_GRADE:         return false;
        case FEATURE_GRADE_OUTCOMES:          return false;
        case FEATURE_BACKUP_MOODLE2:          return true;
        case FEATURE_SHOW_DESCRIPTION:        return true;

        default: return null;
    }
}

