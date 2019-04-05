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

// Make sure completion and restriction is enabled.
if (empty($CFG->enablecompletion) || empty($CFG->enableavailability)) {
    print_error('mustenablecompletionavailability', 'mod_reengagement');
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

    // Get the currently applied filters.
    $filtersapplied = optional_param_array('unified-filters', [], PARAM_NOTAGS);
    $filterwassubmitted = optional_param('unified-filter-submitted', 0, PARAM_BOOL);

    // If they passed a role make sure they can view that role.
    if ($roleid) {
        $viewableroles = get_profile_roles($context);

        // Check if the user can view this role.
        if (array_key_exists($roleid, $viewableroles)) {
            $filtersapplied[] = USER_FILTER_ROLE . ':' . $roleid;
        } else {
            $roleid = 0;
        }
    }

    // Default group ID.
    $groupid = false;
    $canaccessallgroups = has_capability('moodle/site:accessallgroups', $context);
    if ($course->groupmode != NOGROUPS) {
        if ($canaccessallgroups) {
            // Change the group if the user can access all groups and has specified group in the URL.
            if ($groupparam) {
                $groupid = $groupparam;
            }
        } else {
            // Otherwise, get the user's default group.
            $groupid = groups_get_course_group($course, true);
            if ($course->groupmode == SEPARATEGROUPS && !$groupid) {
                // The user is not in the group so show message and exit.
                echo $OUTPUT->notification(get_string('notingroup'));
                echo $OUTPUT->footer();
                exit;
            }
        }
    }
    $hasgroupfilter = false;
    $lastaccess = 0;
    $searchkeywords = [];
    $enrolid = 0;
    $status = -1;
    foreach ($filtersapplied as $filter) {
        $filtervalue = explode(':', $filter, 2);
        $value = null;
        if (count($filtervalue) == 2) {
            $key = clean_param($filtervalue[0], PARAM_INT);
            $value = clean_param($filtervalue[1], PARAM_INT);
        } else {
            // Search string.
            $key = USER_FILTER_STRING;
            $value = clean_param($filtervalue[0], PARAM_TEXT);
        }

        switch ($key) {
            case USER_FILTER_ENROLMENT:
                $enrolid = $value;
                break;
            case USER_FILTER_GROUP:
                $groupid = $value;
                $hasgroupfilter = true;
                break;
            case USER_FILTER_LAST_ACCESS:
                $lastaccess = $value;
                break;
            case USER_FILTER_ROLE:
                $roleid = $value;
                break;
            case USER_FILTER_STATUS:
                // We only accept active/suspended statuses.
                if ($value == ENROL_USER_ACTIVE || $value == ENROL_USER_SUSPENDED) {
                    $status = $value;
                }
                break;
            default:
                // Search string.
                $searchkeywords[] = $value;
                break;
        }
    }

    // If course supports groups we may need to set a default.
    if ($groupid !== false) {
        if ($canaccessallgroups) {
            // User can access all groups, let them filter by whatever was selected.
            $filtersapplied[] = USER_FILTER_GROUP . ':' . $groupid;
        } else if (!$filterwassubmitted && $course->groupmode == VISIBLEGROUPS) {
            // If we are in a course with visible groups and the user has not submitted anything and does not have
            // access to all groups, then set a default group.
            $filtersapplied[] = USER_FILTER_GROUP . ':' . $groupid;
        } else if (!$hasgroupfilter && $course->groupmode != VISIBLEGROUPS) {
            // The user can't access all groups and has not set a group filter in a course where the groups are not visible
            // then apply a default group filter.
            $filtersapplied[] = USER_FILTER_GROUP . ':' . $groupid;
        } else if (!$hasgroupfilter) { // No need for the group id to be set.
            $groupid = false;
        }
    }

    if ($groupid && ($course->groupmode != SEPARATEGROUPS || $canaccessallgroups)) {
        $grouprenderer = $PAGE->get_renderer('core_group');
        $groupdetailpage = new \core_group\output\group_details($groupid);
        echo $grouprenderer->group_details($groupdetailpage);
    }


    // Render the unified filter.
    $renderer = $PAGE->get_renderer('core_user');
    echo $renderer->unified_filter($course, $context, $filtersapplied);

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
        echo '<form action="bulkchange.php" method="post" id="participantsform">';
        echo '<div>';
        echo '<input type="hidden" name="id" value="' . $cm->id . '" />';
        echo '<input type="hidden" name="sesskey" value="' . sesskey() . '" />';
        echo '<input type="hidden" name="returnto" value="' . s($PAGE->url->out(false)) . '" />';
    }

    echo $participanttablehtml;

    $PAGE->requires->js_call_amd('core_user/name_page_filter', 'init');

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

        echo html_writer::start_tag('div', array('class' => 'btn-group'));
        if ($participanttable->get_page_size() < $participanttable->totalrows) {
            // Select all users, refresh page showing all users and mark them all selected.
            $label = get_string('selectalluserswithcount', 'moodle', $participanttable->totalrows);
            echo html_writer::tag('input', "", array('type' => 'button', 'id' => 'checkall', 'class' => 'btn btn-secondary',
                'value' => $label, 'data-showallink' => $showalllink));
            // Select all users, mark all users on page as selected.
            echo html_writer::tag('input', "", array('type' => 'button', 'id' => 'checkallonpage', 'class' => 'btn btn-secondary',
                'value' => get_string('selectallusersonpage')));
        } else {
            echo html_writer::tag('input', "", array('type' => 'button', 'id' => 'checkallonpage', 'class' => 'btn btn-secondary',
                'value' => get_string('selectall')));
        }

        echo html_writer::tag('input', "", array('type' => 'button', 'id' => 'checknone', 'class' => 'btn btn-secondary',
            'value' => get_string('deselectall')));
        echo html_writer::end_tag('div');
        $displaylist = array();
        $displaylist['#messageselect'] = get_string('messageselectadd');

        $pluginoptions = [];
        $params = ['operation' => 'resetbyfirstcourseaccess'];
        $url = new moodle_url('bulkchange.php', $params);
        list ($periodcount, $period) = reengagement_get_readable_duration($reengagement->duration, true);
        $duration = $periodcount ." " .$period;
        $pluginoptions['resetbyfirstaccess'] = get_string('resetbyfirstaccess', 'mod_reengagement', $duration);
        $pluginoptions['resetbyenrolment'] = get_string('resetbyenrolment', 'mod_reengagement', $duration);
        $pluginoptions['resetbyspecificdate'] = get_string('resetbyspecificdate', 'mod_reengagement');

        $name = get_string('resetcompletion', 'mod_reengagement');
        $displaylist[] = [$name => $pluginoptions];

        echo $OUTPUT->help_icon('withselectedusers', 'mod_reengagement');
        echo html_writer::tag('label', get_string("withselectedusers"), array('for' => 'formactionid'));
        echo html_writer::select($displaylist, 'formaction', '', array('' => 'choosedots'), array('id' => 'formactionid'));

        echo '<noscript style="display:inline">';
        echo '<div><input type="submit" value="'.get_string('ok').'" /></div>';
        echo '</noscript>';
        echo '</div></div>';
        echo '</form>';

        $options = new stdClass();
        $options->courseid = $course->id;
        $options->stateHelpIcon = $OUTPUT->help_icon('publishstate', 'notes');
        $PAGE->requires->js_call_amd('core_user/participants', 'init', [$options]);
    }

    echo '</div>';  // Userlist.

}


// Finish the page.
echo $OUTPUT->footer($course);
