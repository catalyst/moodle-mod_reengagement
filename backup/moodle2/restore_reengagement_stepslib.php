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
 * Define all the restore steps that will be used by the restore_reengagement_activity_task
 *
 * @package    mod_reengagement
 * @author     Peter Bulmer <peter.bulmer@catlayst.net.nz>
 * @copyright  2016 Catalyst IT {@link http://www.catalyst.net.nz}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Structure step to restore one reengagement activity
 *
 * @package    mod_reengagement
 * @author     Peter Bulmer <peter.bulmer@catlayst.net.nz>
 * @copyright  2016 Catalyst IT {@link http://www.catalyst.net.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_reengagement_activity_structure_step extends restore_activity_structure_step {
    /**
     * Define the structure for the reengagement activity
     * @return void
     */
    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('reengagement', '/activity/reengagement');
        if ($userinfo) {
            $paths[] = new restore_path_element('reengagement_inprogress', '/activity/reengagement/inprogresses/inprogress');
        }

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Process a reengagement restore.
     *
     * @param object $data The data in object form
     * @return void
     */
    protected function process_reengagement($data) {
        global $DB;

        $data = (object)$data;
        $data->course = $this->get_courseid();

        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        // Insert the reengagement record.
        $newitemid = $DB->insert_record('reengagement', $data);
        // Immediately after inserting "activity" record, call this.
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Process reengagement inprogress records.
     *
     * @param object $data The data in object form
     * @return void
     */
    protected function process_reengagement_inprogress($data) {
        global $DB;

        $data = (object)$data;

        $data->reengagement = $this->get_new_parentid('reengagement');
        $data->userid = $this->get_mappingid('user', $data->userid);

        $DB->insert_record('reengagement_inprogress', $data);
        // No need to save this mapping as far as nothing depend on it
        // (child paths, file areas nor links decoder).
    }

    /**
     * Once the database tables have been fully restored, restore any files
     * @return void
     */
    protected function after_execute() {
        // Add reengagement related files, no need to match by itemname (just internally handled context).
    }
}
