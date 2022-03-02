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
 * Uses the report builder instance imported in classes/reengagement_report.php
 *
 * @package    mod_reengagement
 * @copyright  2021 Catalyst IT
 * @author     Sumaiya Javed <sumaiya.javed@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
declare(strict_types=1);

use core_reportbuilder\system_report_factory;
use mod_reengagement\reengagement_system_report;


require_once('../../config.php');

require_login();
$context = context_system::instance();
   
$PAGE->set_url(new moodle_url('/mod/reengagement/report.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('report', 'mod_reengagement'));
$PAGE->set_heading(get_string('report', 'mod_reengagement'));
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('report_sample', 'mod_reengagement'));
if (has_capability('mod/reengagement:viewsitereport', $context)) {
    // Create report instance.
    $report = system_report_factory::create(reengagement_system_report::class, context_system::instance()); 
    echo $report->output();
} else {
    echo "You do not have access to view this report";        
}

echo $OUTPUT->footer();