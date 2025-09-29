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
 * @author     Peter Bulmer <peter.bulmer@catlayst.net.nz>
 * @copyright  2016 Catalyst IT {@link http://www.catalyst.net.nz}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_table\local\filter\filter;
use core_table\local\filter\integer_filter;

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/lib.php');

define('DEFAULT_PAGE_SIZE', 20);
define('SHOW_ALL_PAGE_SIZE', 5000);

$id = optional_param('id', 0, PARAM_INT); // Course_module ID.
$a = optional_param('a', 0, PARAM_INT);  // Reengagement instance ID.
$page = optional_param('page', 0, PARAM_INT); // Which page to show.
$perpage = optional_param('perpage', DEFAULT_PAGE_SIZE, PARAM_INT); // How many per page.
$selectall = optional_param('selectall', false, PARAM_BOOL); // When rendering checkboxes against users mark them all checked.

$params = array();

if ($id) {
    $params['id'] = $id;
} else {
    $params['a'] = $a;
}

$PAGE->set_url('/mod/reengagement/view.php', $params);

if ($id) {
    $cm = get_coursemodule_from_id('reengagement', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $reengagement = $DB->get_record('reengagement', array('id' => $cm->instance), '*', MUST_EXIST);

} else if ($a) {
    $reengagement = $DB->get_record('reengagement', array('id' => $a), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $reengagement->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('reengagement', $reengagement->id, $course->id, false, MUST_EXIST);
} else {
    throw new moodle_exception('errornoid', 'mod_reengagement');
}

require_login($course, true, $cm);

// Make sure completion and restriction is enabled.
if (empty($CFG->enablecompletion) || empty($CFG->enableavailability)) {
    throw new moodle_exception('mustenablecompletionavailability', 'mod_reengagement');
}

$context = context_module::instance($cm->id);

$event = \mod_reengagement\event\course_module_viewed::create(array(
    'objectid' => $reengagement->id,
    'context' => $context,
));
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('reengagement', $reengagement);
$event->trigger();

// Print the page header.
$strreengagements = get_string('modulenameplural', 'reengagement');
$strreengagement = get_string('modulename', 'reengagement');

$PAGE->set_title(format_string($reengagement->name));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
// Print the main part of the page.

$PAGE->set_context($context);

$canstart = has_capability('mod/reengagement:startreengagement', $context, null, false);
$canedit = has_capability('mod/reengagement:editreengagementduration', $context);
$bulkoperations = has_capability('mod/reengagement:bulkactions', $context);

if (empty($canstart) && empty($canedit)) {
    throw new moodle_exception('errorreengagementnotvalid', 'mod_reengagement');
}

if ($canstart) {
    // Check reengagement record for this user.
    echo reengagement_checkstart($course, $cm, $reengagement);
}

if ($canedit) {
    $task = \core\task\manager::get_scheduled_task('\mod_reengagement\task\cron_task');
    $lastrun = $task->get_last_run_time();
    if ($lastrun < time() - 28800) { // Check if cron run in last 8hrs.
        echo $OUTPUT->notification(get_string('cronwarning', 'reengagement'));
    }

    $filterset = new \mod_reengagement\table\reengagement_participants_filterset();
    // We pretend the courseid is the cmid, because the core Moodle participants filter doesn't allow adding new filter types.
    $filterset->add_filter(new integer_filter('courseid', filter::JOINTYPE_DEFAULT, [(int) $cm->id]));
    $participanttable = new \mod_reengagement\table\reengagement_participants("reengagement-index-participants-{$cm->id}");

    echo '<div class="userlist">';

    // Should use this variable so that we don't break stuff every time a variable is added or changed.
    $baseurl = new moodle_url('/mod/reengagement/view.php', array(
        'contextid' => $context->id,
        'id' => $cm->id,
        'perpage' => $perpage));

    $participanttable->set_filterset($filterset);

    ob_start();
    $participanttable->out($perpage, true);
    $participanttablehtml = ob_get_contents();
    ob_end_clean();

    echo html_writer::start_tag('form', [
        'action' => 'bulkchange.php',
        'method' => 'post',
        'id' => 'participantsform',
        'data-course-id' => $cm->id,
        'data-table-unique-id' => $participanttable->uniqueid,
        'data-table-default-per-page' => ($perpage < DEFAULT_PAGE_SIZE) ? $perpage : DEFAULT_PAGE_SIZE,
    ]);

    echo '<div>';
    echo '<input type="hidden" name="id" value="' . $cm->id . '" />';
    echo '<input type="hidden" name="sesskey" value="' . sesskey() . '" />';
    echo '<input type="hidden" name="returnto" value="' . s($PAGE->url->out(false)) . '" />';

    echo html_writer::tag(
        'p',
        get_string('countparticipantsfound', 'core_user', $participanttable->totalrows),
        [
            'data-region' => 'participant-count',
        ]
    );

    echo $participanttablehtml;
    $perpagevisible = '';
    $perpagestring = '';
    $perpagesize = '';
    $perpageurl = clone($baseurl);
    $perpageurl->remove_params('perpage');
    if ($perpage == SHOW_ALL_PAGE_SIZE && $participanttable->totalrows > DEFAULT_PAGE_SIZE) {
        $perpageurl->param('perpage', $participanttable->totalrows);
        $perpagesize = SHOW_ALL_PAGE_SIZE;
        $perpagevisible = true;
        $perpagestring = get_string('showperpage', '', DEFAULT_PAGE_SIZE);
    } else if ($participanttable->get_page_size() < $participanttable->totalrows) {
        $perpageurl->param('perpage', SHOW_ALL_PAGE_SIZE);
        $perpagesize = SHOW_ALL_PAGE_SIZE;
        $perpagevisible = true;
        $perpagestring = get_string('showall', '', $participanttable->totalrows);
    }

    $perpageclasses = '';
    if (!$perpagevisible) {
        $perpageclasses = 'hidden';
    }

    echo $OUTPUT->container(html_writer::link(
        $perpageurl,
        $perpagestring,
        [
            'data-action' => 'showcount',
            'data-target-page-size' => $perpagesize,
            'class' => $perpageclasses,
        ]
    ), [], 'showall');

    if ($bulkoperations) {
        echo '<br /><div class="buttons"><div class="form-inline">';

        if ($participanttable->get_page_size() < $participanttable->totalrows) {
            // Select all users, refresh table showing all users and mark them all selected.
            $label = get_string('selectalluserswithcount', 'moodle', $participanttable->totalrows);
            echo html_writer::empty_tag('input', [
                'type' => 'button',
                'id' => 'checkall',
                'class' => 'btn btn-secondary',
                'value' => $label,
                'data-target-page-size' => $participanttable->totalrows,
            ]);
        }
        echo html_writer::end_tag('div');
        $displaylist = array();
        $displaylist['#messageselect'] = get_string('messageselectadd');

        $pluginoptions = [];
        $params = ['operation' => 'resetbyfirstcourseaccess'];
        $url = new moodle_url('bulkchange.php', $params);
        list ($periodcount, $period) = reengagement_get_readable_duration($reengagement->duration, true);
        $duration = $periodcount . " " . $period;
        $pluginoptions['resetbyfirstaccess'] = get_string('resetbyfirstaccess', 'mod_reengagement', $duration);
        $pluginoptions['resetbyenrolment'] = get_string('resetbyenrolment', 'mod_reengagement', $duration);
        $pluginoptions['resetbyspecificdate'] = get_string('resetbyspecificdate', 'mod_reengagement');

        $name = get_string('resetcompletion', 'mod_reengagement');
        $displaylist[] = [$name => $pluginoptions];

        echo $OUTPUT->help_icon('withselectedusers', 'mod_reengagement');
        echo html_writer::tag('label', get_string("withselectedusers"), array('for' => 'formactionid'));
        echo html_writer::select($displaylist, 'formaction', '', array('' => 'choosedots'), array('id' => 'formactionid'));

        echo '<noscript style="display:inline">';
        echo '<div><input type="submit" value="' . get_string('ok') . '" /></div>';
        echo '</noscript>';
        echo '</div></div>';
    }
    echo '</form>';

    $jsoptions = (object)[
        'courseid' => $course->id,
        'context' => $context->id
    ];

    $PAGE->requires->js_call_amd('core_user/participants', 'init', [$jsoptions]);

    echo '</div>';  // Userlist.

}

// Finish the page.
echo $OUTPUT->footer($course);
