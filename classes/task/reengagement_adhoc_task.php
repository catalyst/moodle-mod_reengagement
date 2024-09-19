<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * This page lists all the instances of reengagement in a particular course
 *
 * @package    mod_reengagement
 * @author     Rajan Dangi
 * @copyright  2024 Catalyst IT {@link http://www.catalyst-au.net}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_reengagement\task;

use stdClass;
use core\task\adhoc_task;
use context_module;
use cache;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . '/mod/reengagement/lib.php');

/**
 * Adhoc task for reengagement
 */
class reengagement_adhoc_task extends adhoc_task {
    /**
     * Execute the task.
     */
    public function execute() {
        global $DB;

        $data = $this->get_custom_data();
        if (empty($data)) {
            return;
        }

        // Get a list of users who are eligible to start this module.
        $startusers = reengagement_get_startusers($data);

        // Prepare the objects for db iteration.
        $timenow = time();
        $reengagementinprogress = new stdClass();
        $reengagementinprogress->reengagement = $data->rid;
        $reengagementinprogress->completiontime = $timenow + $data->duration;
        $reengagementinprogress->emailtime = $timenow + $data->emaildelay;
        $activitycompletion = new stdClass();
        $activitycompletion->coursemoduleid = $data->cmid;
        $activitycompletion->completionstate = COMPLETION_INCOMPLETE;
        $activitycompletion->timemodified = $timenow;

        // Start reengagement for each user.
        $userlist = array_keys($startusers);
        $newripcount = count($userlist); // Count of new reengagements in progress.

        if (debugging('', DEBUG_DEVELOPER) || ($newripcount && debugging('', DEBUG_ALL))) {
            mtrace("Found $newripcount users to start reengagementid " . $data->rid);
        }

        foreach ($userlist as $userid) {
            $reengagementinprogress->userid = $userid;
            $DB->insert_record('reengagement_inprogress', $reengagementinprogress);
            $activitycompletion->userid = $userid;
            $DB->insert_record('course_modules_completion', $activitycompletion);
        }

        // Process completed reengagements.
        $this->process_completed_reengagements($timenow, $data->rid);
    }

    /**
     * Processes completed reengagements and sends emails to users.
     *
     * This function:
     * - retrieves completed reengagements from the database
     * - checks if the user is still enrolled in the course
     * - updates the completion record,
     * - and sends an email to the user.
     * - also handles in-progress records where the user has reached their email time.
     *
     * @param int $timenow The current time.
     * @param int $reengagementid The ID of the reengagement to process.
     *
     * @return void
     */
    private function process_completed_reengagements($timenow, $reengagementid) {
        global $DB;

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
                         WHERE m.name = 'reengagement' AND r.id = :reengagementid
                      ORDER BY r.id ASC";
        $params = ['reengagementid' => $reengagementid];
        $reengagements = $DB->get_records_sql($reengagementssql, $params);

        $inprogresssql = 'SELECT ri.*
                        FROM {reengagement_inprogress} ri
                        JOIN {reengagement} r ON r.id = ri.reengagement
                        JOIN {user} u ON u.id = ri.userid
                       WHERE u.deleted = 0 AND
                       completiontime < ? AND completed = 0 AND ri.reengagement = ?';
        $inprogresses = $DB->get_recordset_sql($inprogresssql, array($timenow, $reengagementid));
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
                mtrace("Could not find completion record to update complete state, userid: $userid, cmid: $cmid - recreating record.");
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
                ($reengagement->emailuser == REENGAGEMENT_EMAILUSER_TIME && !empty($inprogress->emailsent))
            ) {
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
                             AND ip.reengagement = :reengagementid
                    ORDER BY r.id ASC';
        $params = array('emailtime' => $timenow, 'reengagementid' => $reengagementid);

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
    }
}
