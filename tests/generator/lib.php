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
 * mod_reengagement data generator.
 *
 * @package mod_reengagement
 * @category test
 * @copyright 2019 Matt Clarkson <mattc@catalyst.net.nz>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * mod_reengagement data generator class.
 *
 * @package mod_reengagement
 * @category test
 * @copyright 2019 Matt Clarkson <mattc@catalyst.net.nz>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_reengagement_generator extends testing_module_generator {

    public function create_instance($record = null, array $options = null) {
        $record = (object)(array)$record;

        if (!isset($record->name)) {
            $record->name = 'Test Reengagement';
        }
        if (!isset($record->timecreated)) {
            $record->timecreated = time();
        }
        if (!isset($record->timemodified)) {
            $record->timemodified = time();
        }
        if (!isset($record->emailuser)) {
            $record->emailuser = 1;
        }
        if (!isset($record->emailcontent)) {
            $record->emailcontent = 'Email Content';
        }
        if (!isset($record->emailcontentformat)) {
            $record->emailcontentformat = 0;
        }
        if (!isset($record->emailsubject)) {
            $record->emailsubject = 'Email Subject';
        }
        if (!isset($record->emailcontentmanager)) {
            $record->emailcontentmanager = 'Email Content Manager';
        }
        if (!isset($record->emailcontentmanagerformat)) {
            $record->emailcontentmanagerformat = 0;
        }
        if (!isset($record->emailsubjectmanager)) {
            $record->emailsubjectmanager = 'Email Subject Manager';
        }
        if (!isset($record->emailrecipient)) {
            $record->emailrecipient = 0;
        }
        if (!isset($record->duration)) {
            $record->duration = 604800;
        }
        if (!isset($record->remindercount)) {
            $record->remindercount = 1;
        }
        if (!isset($record->suppresstarget)) {
            $record->suppresstarget = 0;
        }
        if (!isset($record->emaildelay)) {
            $record->emaildelay = 604800;
        }
        if (!isset($record->suppressemail)) {
            $record->suppressemail = 0;
        }

        return parent::create_instance($record, $options);
    }
}
