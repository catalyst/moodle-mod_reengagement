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
 * Code fragment to define the version of reengagement
 * This fragment is called by moodle_needs_upgrading() and /admin/index.php
 *
 * @author  Peter Bulmer <peter.bulmer@catlayst.net.nz>
 * @package mod/reengagement
 */

$plugin->version  = 2016080302;   // The current module version.
$plugin->requires  = 2011112900;
$plugin->cron     = 150;         // Don't run more often than every 150 seconds - realistically expect ~every 300s.
$plugin->component = 'mod_reengagement';
$plugin->release = '3.1.2';
$plugin->maturity  = MATURITY_STABLE;
