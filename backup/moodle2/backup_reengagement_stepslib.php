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

/**
 * Define the complete reengagement structure for backup, with file and id annotations
 *
 * @package    mod_reengagement
 * @author     Peter Bulmer <peter.bulmer@catlayst.net.nz>
 * @copyright  2016 Catalyst IT {@link http://www.catalyst.net.nz}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_reengagement_activity_structure_step extends backup_activity_structure_step {

    /**
     * Define the structure for the assign activity
     * @return void
     */
    protected function define_structure() {

        // To know if we are including userinfo.
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated.
        $reengagement = new backup_nested_element('reengagement', array('id'), array(
            'name', 'timecreated', 'timemodified',
            'emailuser', 'emailsubject', 'emailcontent', 'emailcontentformat',
            'duration', 'suppresstarget', 'emaildelay', 'emailrecipient',
            'emailsubjectmanager', 'emailcontentmanager', 'emailcontentmanagerformat'));

        $inprogresses = new backup_nested_element('inprogresses');

        $inprogress = new backup_nested_element('inprogress', array('id'), array(
            'reengagement', 'userid', 'completiontime', 'emailtime', 'emailsent', 'completed'));

        // Build the tree.
        $reengagement->add_child($inprogresses);
        $inprogresses->add_child($inprogress);

        // Define sources.
        $reengagement->set_source_table('reengagement', array('id' => backup::VAR_ACTIVITYID));

        // All the rest of elements only happen if we are including user info.
        if ($userinfo) {
            $inprogress->set_source_table('reengagement_inprogress', array('reengagement' => '../../id'));
        }

        // Define id annotations.
        $inprogress->annotate_ids('user', 'userid');

        // Return the root element (reengagement), wrapped into standard activity structure.
        return $this->prepare_activity_structure($reengagement);
    }
}
