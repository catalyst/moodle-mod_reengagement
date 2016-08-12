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
 * Backup class
 *
 * @package    mod_reengagement
 * @author     Peter Bulmer <peter.bulmer@catlayst.net.nz>
 * @copyright  2016 Catalyst IT {@link http://www.catalyst.net.nz}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot . '/mod/reengagement/backup/moodle2/backup_reengagement_stepslib.php');
require_once($CFG->dirroot . '/mod/reengagement/backup/moodle2/backup_reengagement_settingslib.php');

/**
 * Task that provides all the settings and steps to perform one complete backup of the activity.
 *
 * @package    mod_reengagement
 * @author     Peter Bulmer <peter.bulmer@catlayst.net.nz>
 * @copyright  2016 Catalyst IT {@link http://www.catalyst.net.nz}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_reengagement_activity_task extends backup_activity_task {

    /**
     * Define (add) particular settings this activity can have.
     */
    protected function define_my_settings() {
        // No particular settings for this activity.
    }

    /**
     * Define (add) particular steps this activity can have
     */
    protected function define_my_steps() {
        // The reengagement only has one structure step.
        $this->add_step(new backup_reengagement_activity_structure_step('reengagement_structure', 'reengagement.xml'));
    }

    /**
     * Code the transformations to perform in the activity in
     * order to get transportable (encoded) links
     * @param string $content
     * @return string
     */
    static public function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, "/");

        // Link to the list of reengagements.
        $search = "/(".$base."\/mod\/reengagement\/index.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@reengagementINDEX*$2@$', $content);

        // Link to reengagement view by moduleid.
        $search = "/(".$base."\/mod\/reengagement\/view.php\?id\=)([0-9]+)/";
        $content = preg_replace($search, '$@reengagementVIEWBYID*$2@$', $content);

        return $content;
    }
}
