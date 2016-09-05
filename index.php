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
 * This page lists all the instances of reengagement in a particular course
 *
 * @package    mod_reengagement
 * @author     Peter Bulmer
 * @copyright  2016 Catalyst IT {@link http://www.catalyst.net.nz}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');

$id = required_param('id', PARAM_INT);

$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);

require_login($course);

// Get all required stringsreengagement.
$strreengagements = get_string('modulenameplural', 'reengagement');
$strreengagement  = get_string('modulename', 'reengagement');

$params = array();

$params['id'] = $id;

$PAGE->set_url('/mod/reengagement/index.php', $params);

// Print the header.

$PAGE->set_title(format_string($strreengagements));
$PAGE->set_heading(format_string($course->fullname));

// Add the page view to the Moodle log.
$event = \mod_reengagement\event\course_module_instance_list_viewed::create(array(
    'context' => context_course::instance($course->id)
));
$event->add_record_snapshot('course', $course);
$event->trigger();


echo $OUTPUT->header();
// Get all the appropriate data.

if (! $reengagements = get_all_instances_in_course('reengagement', $course)) {
    notice('There are no instances of reengagement', "../../course/view.php?id=$course->id");
    die;
}

// Print the list of instances.

$timenow  = time();
$strname  = get_string('name');
$strweek  = get_string('week');
$strtopic = get_string('topic');
$strsectionname  = get_string('sectionname', 'format_'.$course->format);

$usesections = course_format_uses_sections($course->format);

$table = new html_table();
$table->attributes['class'] = 'generaltable mod_index';

if ($usesections) {
    $table->head  = array ($strsectionname, $strname);
    $table->align = array ('center', 'left', 'left');
} else {
    $table->head  = array ($strlastmodified, $strname);
    $table->align = array ('left', 'left', 'left');
}


$modinfo = get_fast_modinfo($course);
$currentsection = '';
foreach ($reengagements as $reengagement) {
    $cm = $modinfo->cms[$reengagement->coursemodule];
    if ($usesections) {
        $printsection = '';
        if ($reengagement->section !== $currentsection) {
            if ($reengagement->section) {
                $printsection = get_section_name($course, $reengagement->section);
            }
            if ($currentsection !== '') {
                $table->data[] = 'hr';
            }
            $currentsection = $reengagement->section;
        }
    } else {
        $printsection = '<span class="smallinfo">'.userdate($reengagement->timemodified)."</span>";
    }

    $class = $reengagement->visible ? '' : 'class="dimmed"'; // Hidden modules are dimmed.

    $table->data[] = array (
        $printsection,
        "<a $class href=\"view.php?id=$cm->id\">".format_string($reengagement->name)."</a>");
}

echo html_writer::table($table);

echo $OUTPUT->footer();

