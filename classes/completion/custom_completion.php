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
 * Prints information about the reengagement to the user.
 *
 * @package    mod_reengagement
 * @author     Sumaiya Javed <sumaiya.javed@catlayst.net.nz>
 * @copyright  2016 Catalyst IT {@link http://www.catalyst.net.nz}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace mod_reengagement\completion;

use core_completion\activity_custom_completion;

class custom_completion extends activity_custom_completion {

    /**
     * Fetches the completion state for a given completion rule.
     *
     * @param string $rule The completion rule.
     * @return int The completion state.
     */
    public function get_state(string $rule): int {
        global $DB;

        $this->validate_rule($rule);

        // Survey only supports duration as a custom rule.
        $status = $DB->record_exists('course_modules_completion', ['coursemoduleid' => $this->cm->id, 'userid' => $this->userid]);
        return $status ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;      //  return $status ? COMPLETION_COMPLETE_PASS : COMPLETION_INCOMPLETE;
    }

    /**
     * Fetch the list of custom completion rules that this module defines.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return ['duration'];
    }

    /**
    * Returns an associative array of the descriptions of custom completion rules.
    *
    * @return array
    */
   public function get_custom_rule_descriptions(): array {
       return [
           'duration' => get_string('duration', 'reengagement')
       ];
   }


    /**
     * Returns an array of all completion rules, in the order they should be displayed to users.
     *
     * @return array
     */
    public function get_sort_order(): array {
        return [
            'duration',
        ];
    }
}






