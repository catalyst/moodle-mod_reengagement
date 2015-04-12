<?php

/**
 * This page lists all the instances of reengagement in a particular course
 *
 * @author  Your Name <your@email.address>
 * @version $Id: index.php,v 1.7.2.3 2009/08/31 22:00:00 mudrd8mz Exp $
 * @package mod/reengagement
 */

/// Replace reengagement with the name of your module and remove this line

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');

$id = required_param('id', PARAM_INT);   // course

if (! $course = $DB->get_record('course', array('id' => $id))) {
    error('Course ID is incorrect');
}

require_login($course);

/// Get all required stringsreengagement
$strreengagements = get_string('modulenameplural', 'reengagement');
$strreengagement  = get_string('modulename', 'reengagement');

$params = array();

$params['id'] = $id;

$PAGE->set_url('/mod/reengagement/index.php', $params);

/// Print the header


$PAGE->set_title(format_string($strreengagements));
$PAGE->set_heading(format_string($course->fullname));

// Add the page view to the Moodle log
$event = \mod_reengagement\event\course_module_instance_list_viewed::create(array(
    'context' => context_course::instance($course->id)
));
$event->add_record_snapshot('course', $course);
$event->trigger();


echo $OUTPUT->header();
/// Get all the appropriate data

if (! $reengagements = get_all_instances_in_course('reengagement', $course)) {
    notice('There are no instances of reengagement', "../../course/view.php?id=$course->id");
    die;
}

/// Print the list of instances (your module will probably extend this)

$timenow  = time();
$strname  = get_string('name');
$strweek  = get_string('week');
$strtopic = get_string('topic');
$strintro = get_string('moduleintro');
$strsectionname  = get_string('sectionname', 'format_'.$course->format);

$usesections = course_format_uses_sections($course->format);
if ($usesections) {
    $sections = get_all_sections($course->id);
}

$table = new html_table();
$table->attributes['class'] = 'generaltable mod_index';

if ($usesections) {
    $table->head  = array ($strsectionname, $strname, $strintro);
    $table->align = array ('center', 'left', 'left');
} else {
    $table->head  = array ($strlastmodified, $strname, $strintro);
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
                $printsection = get_section_name($course, $sections[$reengagement->section]);
            }
            if ($currentsection !== '') {
                $table->data[] = 'hr';
            }
            $currentsection = $reengagement->section;
        }
    } else {
        $printsection = '<span class="smallinfo">'.userdate($reengagement->timemodified)."</span>";
    }

    $class = $reengagement->visible ? '' : 'class="dimmed"'; // hidden modules are dimmed

    $table->data[] = array (
        $printsection,
        "<a $class href=\"view.php?id=$cm->id\">".format_string($reengagement->name)."</a>",
        format_module_intro('reengagement', $reengagement, $cm->id));
}

echo html_writer::table($table);

/// Finish the page

echo $OUTPUT->footer();

