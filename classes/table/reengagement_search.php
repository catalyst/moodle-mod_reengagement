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
 * Class used to fetch participants based on the reengagement filterset
 *
 * @package    mod_reengagement
 * @copyright  2020 Catalyst IT
 * @author     Alex Morris <alex.morris@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_reengagement\table;

use core_user\table\participants_search;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/user/lib.php');

/**
 * Class used to fetch participants based on the reengagement filterset
 *
 * @package    mod_reengagement
 * @copyright  2020 Catalyst IT
 * @author     Alex Morris <alex.morris@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reengagement_search extends participants_search {

    /**
     * Generate the SQL used to fetch filtered data for the reengagement table.
     *
     * @param string $additionalwhere Any additional SQL to add to where
     * @param array $additionalparams The additional params
     * @return array
     */
    protected function get_participants_sql(string $additionalwhere, array $additionalparams): array {
        $sql = parent::get_participants_sql($additionalwhere, $additionalparams);
        $sql['outerjoins'] .= 'LEFT JOIN {reengagement_inprogress} rip ON rip.userid = u.id';
        $sql['outerselect'] .= ', rip.completiontime AS completiontime, rip.emailtime AS emailtime, '
            . 'rip.emailsent AS emailsent, rip.completed AS completed';
        return $sql;
    }
}
