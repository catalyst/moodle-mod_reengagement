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

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');

define('DEFAULT_PAGE_SIZE', 20);
define('SHOW_ALL_PAGE_SIZE', 5000);

$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or
$a  = optional_param('a', 0, PARAM_INT);  // reengagement instance ID.
$page         = optional_param('page', 0, PARAM_INT); // Which page to show.
$perpage      = optional_param('perpage', DEFAULT_PAGE_SIZE, PARAM_INT); // How many per page.
$selectall    = optional_param('selectall', false, PARAM_BOOL); // When rendering checkboxes against users mark them all checked.
$roleid       = optional_param('roleid', 0, PARAM_INT);
$groupparam   = optional_param('group', 0, PARAM_INT);

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
    error('You must specify a course_module ID or an instance ID');
}

require_login($course, true, $cm);

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
$strreengagement  = get_string('modulename', 'reengagement');

$PAGE->set_title(format_string($reengagement->name));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
// Print the main part of the page.

$PAGE->set_context($context);

$canstart = has_capability('mod/reengagement:startreengagement', $context, null, false);
$canedit = has_capability('mod/reengagement:editreengagementduration', $context);
$bulkoperations = has_capability('mod/reengagement:bulkactions', $context);

if (empty($canstart) && empty($canedit)) {
    error("This reengagement module is not enabled for your account.
      Please contact your administrator if you feel this is in error");
}

if ($canstart) {
    // Check reengagement record for this user.
    echo reengagement_checkstart($course, $cm, $reengagement);
}

if ($canedit) {
    $task = \core\task\manager::get_scheduled_task('\mod_reengagement\task\cron_task');
    $lastrun = $task->get_last_run_time();
    if ($lastrun < time() - 3600) { // Check if cron run in last 60min.
        echo $OUTPUT->notification(get_string('cronwarning', 'reengagement'));
    }
    $groupid = false;
    $lastaccess = 0;
    $searchkeywords = [];
    $enrolid = 0;
    $status = -1;

    echo '<div class="userlist">';

    // Should use this variable so that we don't break stuff every time a variable is added or changed.
    $baseurl = new moodle_url('/mod/reengagement/view.php', array(
        'contextid' => $context->id,
        'id' => $cm->id,
        'perpage' => $perpage));

    $participanttable = new \mod_reengagement\table\participants($reengagement, $course->id, $groupid,
        $lastaccess, $roleid, $enrolid, $status, $searchkeywords, $bulkoperations, $selectall);
    $participanttable->define_baseurl($baseurl);

    // Do this so we can get the total number of rows.
    ob_start();
    $participanttable->out($perpage, true);
    $participanttablehtml = ob_get_contents();
    ob_end_clean();

    if ($bulkoperations) {
        echo \html_writer::start_div('', array('id' => 'participantscontainer'));
        echo '<form action="bulkchange.php" method="post" id="participantsform">';
        echo '<div>';
        echo '<input type="hidden" name="id" value="' . $cm->id . '" />';
        echo '<input type="hidden" name="sesskey" value="' . sesskey() . '" />';
        echo '<input type="hidden" name="returnto" value="' . s($PAGE->url->out(false)) . '" />';
    }

    echo $participanttablehtml;

    $perpageurl = clone($baseurl);
    $perpageurl->remove_params('perpage');
    if ($perpage == SHOW_ALL_PAGE_SIZE && $participanttable->totalrows > DEFAULT_PAGE_SIZE) {
        $perpageurl->param('perpage', DEFAULT_PAGE_SIZE);
        echo $OUTPUT->container(html_writer::link($perpageurl, get_string('showperpage', '', DEFAULT_PAGE_SIZE)),
            array(), 'showall');

    } else if ($participanttable->get_page_size() < $participanttable->totalrows) {
        $perpageurl->param('perpage', SHOW_ALL_PAGE_SIZE);
        echo $OUTPUT->container(html_writer::link($perpageurl, get_string('showall', '', $participanttable->totalrows)),
            array(), 'showall');
    }

    if ($bulkoperations) {
        echo '<br /><div class="buttons">';

        if ($participanttable->get_page_size() < $participanttable->totalrows) {
            $perpageurl = clone($baseurl);
            $perpageurl->remove_params('perpage');
            $perpageurl->param('perpage', SHOW_ALL_PAGE_SIZE);
            $perpageurl->param('selectall', true);
            $showalllink = $perpageurl;
        } else {
            $showalllink = false;
        }
        echo html_writer::link('javascript:select_all_in(\'DIV\', null, \'participantscontainer\');',
                get_string('selectall', 'scorm')).' / ';
        echo html_writer::link('javascript:deselect_all_in(\'DIV\', null, \'participantscontainer\');',
            get_string('selectnone', 'scorm'));

        $displaylist = array();

        $pluginoptions = [];
        $params = ['operation' => 'resetbyfirstcourseaccess'];
        $url = new moodle_url('bulkchange.php', $params);
        $pluginoptions['resetbyfirstaccess'] = get_string('resetbyfirstaccess', 'mod_reengagement');

        $name = get_string('resetcompletion', 'mod_reengagement');
        $displaylist[] = [$name => $pluginoptions];

        echo html_writer::tag('label', get_string("withselectedusers"), array('for' => 'formactionid'));
        echo html_writer::select($displaylist, 'formaction', '', array('' => 'choosedots'), array('id' => 'formactionid'));

        echo '<div><input type="submit" value="'.get_string('ok').'" /></div>';
        echo '</div></div>';
        echo '</form>';
        echo html_writer::end_div(); // The participantscontainer.
    }

    echo '</div>';  // Userlist.

}


// Finish the page.
echo $OUTPUT->footer($course);
