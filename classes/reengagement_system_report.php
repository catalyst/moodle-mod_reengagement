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
 * System report for testing the reengagement entity
 * Integrating reportbuilder api system_report to be able to use system_report_factory
 * @package    mod_reengagement
 * @copyright  2021 Catalyst IT
 * @author     Sumaiya Javed <sumaiya.javed@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


declare(strict_types=1);

namespace mod_reengagement;

use core_reportbuilder\local\filters\text;
use core_reportbuilder\local\filters\date;
use core_reportbuilder\local\helpers\database;
use core_reportbuilder\system_report;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use lang_string;
use html_writer;
use context_system;
use mod_reengagement\reengagement_report;


class reengagement_system_report extends system_report {

    /**
     * Initialise the report
     */
    protected function initialise(): void {

        $entity = new reengagement_report();

        $this->set_main_table('reengagement', $entity->get_table_alias('reengagement'));
        $this->add_entity($entity);

        $columns = [];
        foreach ($entity->get_columns() as $column) {
            $columns[] = $column->get_unique_identifier();
        }
        $this->add_columns_from_entities($columns);

        $filters = [];
        foreach ($entity->get_filters() as $filter) {
            $filters[] = $filter->get_unique_identifier();
        }
        $this->add_filters_from_entities($filters);

    }

    /**
     * Ensure we can view the report
     *
     * @return bool
     */
    protected function can_view(): bool {
        return true;
    }

    /**
     * Explicitly set availability of report
     *
     * @return bool
     */
    public static function is_available(): bool {
        return true;
    }
}
