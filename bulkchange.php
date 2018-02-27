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
 * This page performs bulk changes on reengagment users.
 *
 * @package    mod_reengagement
 * @author     Dan Marsden <dan@danmarsden.com>
 * @copyright  2018 Catalyst IT {@link http://www.catalyst.net.nz}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');

$id = required_param('id', PARAM_INT); // Course_module ID.
$formaction = required_param('formaction', PARAM_LOCALURL);
$userids = optional_param('userids', array(), PARAM_TEXT);
$confirm = optional_param('confirm', 0, PARAM_INT);

$cm = get_coursemodule_from_id('reengagement', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$reengagement = $DB->get_record('reengagement', array('id' => $cm->instance), '*', MUST_EXIST);

$default = new moodle_url('/mod/reengagement/view.php', ['id' => $cm->id]);
$returnurl = new moodle_url(optional_param('returnto', $default, PARAM_URL));

require_sesskey(); // This is an action script.

$PAGE->set_url('/mod/reengagement/view.php', array('id' => $id, 'formaction' => $formaction));

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/reengagement:bulkactions', $context);

$PAGE->set_title(format_string($reengagement->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

if (!empty($userids)) {
    $userids = explode(',', $userids);
}
// First initial post from view.php - users stored in array as "userX" => "on" - get the userids.
if (empty($userids) and $post = data_submitted()) {
    foreach ($post as $k => $v) {
        if (preg_match('/^user(\d+)$/', $k, $m)) {
            $userids[] = $m[1];
        }
    }
}

if (!$confirm) {
    echo $OUTPUT->header();
}

if ($formaction == 'resetbyfirstaccess') {
    // Get information on users and the updated date.
    $usernamefields = get_all_user_name_fields(true, 'u');
    list($usql, $params) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u');
    $sql = "SELECT u.id, $usernamefields, rip.id as ripid,
                   rip.completiontime, rip.emailtime, rip.completiontime, rip.completed,
                   min(l.timecreated) as firstaccess
        FROM {reengagement_inprogress} rip
        JOIN {user} u on u.id = rip.userid
        LEFT JOIN {logstore_standard_log} l ON l.userid = u.id AND l.courseid = :courseid
        WHERE u.id ".$usql ."
        GROUP BY u.id, u.firstname, u.lastname, rip.id,
                 rip.completiontime, rip.emailtime, rip.completiontime, rip.completed";
    $params['courseid'] = $course->id;
    $users = $DB->get_records_sql($sql, $params);
    if (!$confirm) {
        print '<table class="reengagementlist">' . "\n";
        print "<tr><th>" . get_string('user') . "</th>";
        print "<th>" . get_string('completiontime', 'reengagement') . '</th>';
        print "<th>" . get_string('newcompletiontime', 'reengagement') . '</th>';
        print "</tr>";
        foreach ($users as $user) {
            if (!empty($user->firstaccess)) {
                $newdate = $user->firstaccess + $reengagement->duration;
                if ($newdate == $user->completiontime) {
                    // Date is already based on firstaccess.
                    $newdate = get_string('nochange', 'mod_reengagement');
                } else {
                    $newdate = userdate($newdate, get_string('strftimedatetimeshort', 'langconfig'));
                }

            } else {
                $newdate = get_string('nochangenoaccess', 'mod_reengagement');
            }

            print '<tr><td>' . fullname($user) . '</td>';
            print '<td>' . userdate($user->completiontime, get_string('strftimedatetimeshort', 'langconfig'))."</td>";
            print '<td>'. $newdate."</td></tr>";
        }
        print '</table>';

        $yesurl = new moodle_url('/mod/reengagement/bulkchange.php');
        $yesparams = array('id' => $cm->id, 'formaction' => $formaction,
            'userids' => implode(',', $userids), 'confirm' => 1);
        $areyousure = get_string('areyousure', 'mod_reengagement');
        echo $OUTPUT->confirm($areyousure, new moodle_url($yesurl, $yesparams), $returnurl);
        echo $OUTPUT->footer();
        die;
    } else {
        foreach ($users as $user) {
            if (!empty($user->firstaccess)) {
                $newdate = $user->firstaccess + $reengagement->duration;
                if ($newdate !== $user->completiontime) {
                    $rip = new stdClass();
                    $rip->id = $user->ripid;
                    $rip->userid = $user->id;
                    $rip->reengagement = $reengagement->id;
                    $rip->completiontime = $newdate;
                    $DB->update_record('reengagement_inprogress', $rip);
                }
            }
        }
        redirect($returnurl, get_string('completiondatesupdated', 'reengagement'));
    }
}

redirect($returnurl);

