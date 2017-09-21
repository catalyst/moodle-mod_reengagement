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
 * Upgrade tasks.
 *
 * @package    mod_reengagement
 * @author     Peter Bulmer
 * @copyright  2016 Catalyst IT {@link http://www.catalyst.net.nz}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Code run to upgrade the reengagment database tables.
 *
 * @param int $oldversion
 * @return bool always true
 */
function xmldb_reengagement_upgrade($oldversion=0) {
    global $DB;
    $dbman = $DB->get_manager();
    if ($oldversion < 2014071701) {
        // Define new fields to support emailing managers.
        // Define field emailrecipient to be added to reengagement to record who should receive emails.
        $table = new xmldb_table('reengagement');
        $field = new xmldb_field('emailrecipient', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('reengagement');
        // Define field to hold the email subject which should be used in emails to user's managers.
        $field = new xmldb_field('emailsubjectmanager', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('reengagement');
        // Define field to hold the email content which should be used in emails to user's managers.
        $field = new xmldb_field('emailcontentmanager', XMLDB_TYPE_TEXT, null, null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('reengagement');
        $field = new xmldb_field('emailcontentmanagerformat', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2014071701, 'reengagement');
    }

    // Add remindercount fields.
    if ($oldversion < 2016080301) {

        $table = new xmldb_table('reengagement');
        $field = new xmldb_field('remindercount', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '1');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2016080301, 'reengagement');
    }

    // Add third-party email fields.
    if ($oldversion < 2016112400) {
        // Define new fields to support emailing thirdparties.
        $table = new xmldb_table('reengagement');

        // Define field thirdpartyemails to be added to reengagement to record who should receive emails.
        $field = new xmldb_field('thirdpartyemails', XMLDB_TYPE_TEXT, null, null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field to hold the email subject which should be used in emails to user's thirdparties.
        $field = new xmldb_field('emailsubjectthirdparty', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field to hold the email content which should be used in emails to user's thirdparties.
        $field = new xmldb_field('emailcontentthirdparty', XMLDB_TYPE_TEXT, null, null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('emailcontentthirdpartyformat', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2016112400, 'reengagement');
    }
    // Set default value
    if ($oldversion < 2017040400) {

        $table = new xmldb_table('reengagement');
        $field = new xmldb_field('thirdpartyemails', XMLDB_TYPE_TEXT, null, null, false, null, null);
        $dbman->change_field_notnull($table, $field);

        $field = new xmldb_field('emailsubjectthirdparty', XMLDB_TYPE_CHAR, '255', null, false, null, null);
        $dbman->change_field_notnull($table, $field);

        $field = new xmldb_field('emailcontentthirdparty', XMLDB_TYPE_TEXT, null, null, false, null, null);
        $dbman->change_field_notnull($table, $field);

        $field = new xmldb_field('emailcontentthirdpartyformat', XMLDB_TYPE_INTEGER, '4', null, false, null, '0');
        $dbman->change_field_notnull($table, $field);

        upgrade_mod_savepoint(true, 2017040400, 'reengagement');
    }

    if ($oldversion < 2017102001) {
        global $CFG;
        require_once($CFG->dirroot.'/mod/reengagement/lib.php');
        // A bug in previous versions prevented some e-mails from being sent.
        // Flag these old broken reengagment e-mails as sent as they may no longer be relevant.
        $timenow = time();
        $sql = "SELECT rin.*, cm.id as cmid, r.emailuser
                  FROM {reengagement_inprogress} rin
                  JOIN {course_modules} cm ON cm.instance = rin.reengagement
                  JOIN {modules} m ON m.id = cm.module
                  JOIN {reengagement} r on r.id = rin.reengagement
             LEFT JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id AND cmc.userid = rin.userid
                 WHERE m.name = 'reengagement' AND cmc.id is null
                       AND rin.completiontime < ? AND rin.completed = 0";
        $missingprogress = $DB->get_recordset_sql($sql, array($timenow));

        foreach ($missingprogress as $missing) {
            // Add course completion record.
            $activitycompletion = new stdClass();
            $activitycompletion->coursemoduleid = $missing->cmid;
            $activitycompletion->completionstate = COMPLETION_COMPLETE_PASS;
            $activitycompletion->timemodified = $timenow;
            $activitycompletion->userid = $missing->userid;
            $DB->insert_record('course_modules_completion', $activitycompletion);

            // flag re-enagement as complete if required - or delete record.
            // logic copied from cron function.
            if (($missing->emailuser == REENGAGEMENT_EMAILUSER_COMPLETION) ||
                ($missing->emailuser == REENGAGEMENT_EMAILUSER_NEVER) ||
                ($missing->emailuser == REENGAGEMENT_EMAILUSER_TIME && !empty($missing->emailsent))) {
                // No need to keep 'inprogress' record for later emailing
                // Delete inprogress record.
                debugging('', DEBUG_DEVELOPER) && mtrace("mode $missing->emailuser reengagementid $missing->id.
                      User marked complete, deleting inprogress record for user $missing->userid");
                $DB->delete_records('reengagement_inprogress', array('id' => $missing->id));
            } else {
                // Update inprogress record to indicate completion done.
                debugging('', DEBUG_DEVELOPER) && mtrace("mode $missing->emailuser reengagementid $missing->id
                      updating inprogress record for user $missing->userid to indicate completion");
                $updaterecord = new stdClass();
                $updaterecord->id = $missing->id;
                $updaterecord->completed = COMPLETION_COMPLETE;
                $DB->update_record('reengagement_inprogress', $updaterecord);
            }

            // Don't send e-mail as this record may be stale.
        }
        $missingprogress->close();

        upgrade_mod_savepoint(true, 2017102001, 'reengagement');
    }

    return true;
}