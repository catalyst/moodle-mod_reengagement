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

namespace mod_reengagement;

use advanced_testcase;
use mod_reengagement\task\reengagement_adhoc_task;

/**
 * Reengagement adhoc task tests
 *
 * @package    mod_reengagement
 * @author     Matthew Hilton <matthewhilton@catalyst-au.net>
 * @copyright  2025 Catalyst IT {@link http://www.catalyst-au.net}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_reengagement\task\reengagement_adhoc_task
 */
class reengagement_adhoc_task_test extends advanced_testcase {
    /**
     * Tests adhoc task exits gracefully without any customdata.
     */
    public function test_adhoc_task_no_customdata() {
        $task = new reengagement_adhoc_task();
        $task->execute();
    }

    /**
     * Tests adhoc task exits gracefully with a missing cmid in customdata.
     */
    public function test_adhoc_task_missing_cmid_customdata() {
        // No cmid is customdata, should exit early and not throw exception.
        $task = new reengagement_adhoc_task();
        $task->set_custom_data(['cmid' => null]);
        $task->execute();
    }

    /**
     * Tests adhoc task exits gracefully with an invalid cmid in customdata.
     */
    public function test_adhoc_task_coursemodule_does_not_exist() {
        // This coursemodule does not exist, it should gracefully exit.
        $task = new reengagement_adhoc_task();
        $task->set_custom_data(['cmid' => 12345]);
        $task->execute();
    }
}
