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
 * Define all the backup steps that will be used by the backup_reengagement_activity_task
 *
 * @package    mod_reengagement
 * @author     Peter Bulmer <peter.bulmer@catlayst.net.nz>
 * @copyright  2016 Catalyst IT {@link http://www.catalyst.net.nz}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/reengagement/backup/moodle2/restore_reengagement_stepslib.php');

/**
 * Task that provides all the settings and steps to perform one complete restore of the activity.
 *
 * @package    mod_reengagement
 * @author     Peter Bulmer <peter.bulmer@catlayst.net.nz>
 * @copyright  2016 Catalyst IT {@link http://www.catalyst.net.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_reengagement_activity_task extends restore_activity_task {

    /**
     * Define (add) particular settings this activity can have
     */
    protected function define_my_settings() {
        // No particular settings for this activity.
    }

    /**
     * Define (add) particular steps this activity can have.
     */
    protected function define_my_steps() {
        // The reengagement only has one structure step.
        $this->add_step(new restore_reengagement_activity_structure_step('reengagement_structure', 'reengagement.xml'));
    }

    /**
     * Define the contents in the activity that must be
     * processed by the link decoder
     */
    static public function define_decode_contents() {
        $contents = array();

        return $contents;
    }

    /**
     * Define the decoding rules for links belonging
     * to the activity to be executed by the link decoder
     */
    static public function define_decode_rules() {
        $rules = array();

        $rules[] = new restore_decode_rule('STANDDOWNVIEWBYID', '/mod/reengagement/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('STANDDOWNINDEX', '/mod/reengagement/index.php?id=$1', 'course');

        return $rules;

    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * reengagement logs. It must return one array
     * of {@link restore_log_rule} objects
     */
    static public function define_restore_log_rules() {
        $rules = array();

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * course logs. It must return one array
     * of {@link restore_log_rule} objects
     *
     * Note this rules are applied when restoring course logs
     * by the restore final task, but are defined here at
     * activity level. All them are rules not linked to any module instance (cmid = 0)
     */
    static public function define_restore_log_rules_for_course() {
        $rules = array();

        // Fix old wrong uses (missing extension).
        $rules[] = new restore_log_rule('reengagement', 'view all', 'index?id={course}', null,
                                        null, null, 'index.php?id={course}');
        $rules[] = new restore_log_rule('reengagement', 'view all', 'index.php?id={course}', null);

        return $rules;
    }

    /**
     * The reengagement module has a suppresstarget which is a cmid, we need to update that accordingly, however,
     * in certain cases, that course may be restored to our target course
     * After the reengagement itself is restored, so we do the cmid mapping fix after the restore has finished.
     */
    public function after_restore() {
        global $DB;
        $id = $this->get_activityid();
        $course = $this->get_courseid();
        $reengagement = $DB->get_record('reengagement', array('id' => $id));
        if (empty($reengagement)) {
            // Unexpected, but nothing needs doing.
            return;
        }
        if (empty($reengagement->suppresstarget)) {
            // Restored activity didn't have a targeted activity. Nothing needs mapping.
            return;
        }
        // Find the mapping between old course_module id and new course_module id.
        $map = restore_dbops::get_backup_ids_record($this->get_restoreid(), 'course_module', $reengagement->suppresstarget);
        if ($map) {
            $newid = $map->newitemid;
            // Update cmid if the mapping exists.
            $reengagement->suppresstarget = $newid;
            $DB->update_record('reengagement', $reengagement);
        } else {
            // If there is no new cm, then the course we are targeting is not included in the backup
            // put out a log warning and set a target of 0. not much else we can do here
            // nb: according to wiki doc these logs go nowhere!
            $this->get_logger()->process("Failed to restore the suppressed email target in reengagement: '$id'. " .
                "Backup and restore of this item will not work correctly unless you include the required activity ".
                "in the restore to course:$course.", backup::LOG_ERROR);
            $reengagement->suppresstarget = 0;
            $DB->update_record('reengagement', $reengagement);
        }
    }
}
