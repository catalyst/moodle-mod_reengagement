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
 * Contains the class used for the reengagement participants table filterset
 *
 * @package    mod_reengagement
 * @copyright  2020 Catalyst IT
 * @author     Alex Morris <alex.morris@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_reengagement\table;

use core_table\local\filter\integer_filter;
use core_user\table\participants_filterset;

/**
 * Reengagement table filterset, extends the core participants filterset to use its get_optional_filters function.
 *
 * @package    mod_reengagement
 * @copyright  2020 Catalyst IT
 * @author     Alex Morris <alex.morris@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reengagement_filterset extends participants_filterset {

    /**
     * Get the required filters.
     *
     * The required filters are the course module id and the course id.
     *
     * @return array.
     */
    public function get_required_filters(): array {
        return [
            'cmid' => integer_filter::class,
            'courseid' => integer_filter::class,
        ];
    }
}
