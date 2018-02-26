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
 * Compat functions for bulk change screen - functions backported from stable branches.
 *
 * @package    mod_reengagement
 * @author     Dan Marsden
 * @copyright  2018 Catalyst IT {@link http://www.catalyst.net.nz}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();



/**
 * Returns the SQL used by the participants table.
 *
 * @param int $courseid The course id
 * @param int $groupid The groupid, 0 means all groups
 * @param int $accesssince The time since last access, 0 means any time
 * @param int $roleid The role id, 0 means all roles
 * @param int $enrolid The enrolment id, 0 means all enrolment methods will be returned.
 * @param int $statusid The user enrolment status, -1 means all enrolments regardless of the status will be returned, if allowed.
 * @param string|array $search The search that was performed, empty means perform no search
 * @param string $additionalwhere Any additional SQL to add to where
 * @param array $additionalparams The additional params
 * @return array
 */
function user_get_participants_sql($courseid, $groupid = 0, $accesssince = 0, $roleid = 0, $enrolid = 0, $statusid = -1,
                                   $search = '', $additionalwhere = '', $additionalparams = array()) {
    global $DB, $USER;
    // Get the context.
    $context = \context_course::instance($courseid, MUST_EXIST);
    $isfrontpage = ($courseid == SITEID);
    // Default filter settings. We only show active by default, especially if the user has no capability to review enrolments.
    $onlyactive = true;
    $onlysuspended = false;
    if (has_capability('moodle/course:enrolreview', $context)) {
        switch ($statusid) {
            case ENROL_USER_ACTIVE:
                // Nothing to do here.
                break;
            case ENROL_USER_SUSPENDED:
                $onlyactive = false;
                $onlysuspended = true;
                break;
            default:
                // If the user has capability to review user enrolments, but statusid is set to -1, set $onlyactive to false.
                $onlyactive = false;
                break;
        }
    }
    list($esql, $params) = get_enrolled_sql($context, null, $groupid, $onlyactive, $onlysuspended, $enrolid);
    $joins = array('FROM {user} u');
    $wheres = array();
    $userfields = get_extra_user_fields($context, array('username', 'lang', 'timezone', 'maildisplay'));
    $userfieldssql = \user_picture::fields('u', $userfields);
    if ($isfrontpage) {
        $select = "SELECT $userfieldssql, u.lastaccess";
        $joins[] = "JOIN ($esql) e ON e.id = u.id"; // Everybody on the frontpage usually.
        if ($accesssince) {
            $wheres[] = user_get_user_lastaccess_sql($accesssince);
        }
    } else {
        $select = "SELECT $userfieldssql, COALESCE(ul.timeaccess, 0) AS lastaccess";
        $joins[] = "JOIN ($esql) e ON e.id = u.id"; // Course enrolled users only.
        // Not everybody has accessed the course yet.
        $joins[] = 'LEFT JOIN {user_lastaccess} ul ON (ul.userid = u.id AND ul.courseid = :courseid)';
        $params['courseid'] = $courseid;
        if ($accesssince) {
            $wheres[] = user_get_course_lastaccess_sql($accesssince);
        }
    }
    // Performance hacks - we preload user contexts together with accounts.
    $ccselect = ', ' . \context_helper::get_preload_record_columns_sql('ctx');
    $ccjoin = 'LEFT JOIN {context} ctx ON (ctx.instanceid = u.id AND ctx.contextlevel = :contextlevel)';
    $params['contextlevel'] = CONTEXT_USER;
    $select .= $ccselect;
    $joins[] = $ccjoin;
    // Limit list to users with some role only.
    if ($roleid) {
        // We want to query both the current context and parent contexts.
        list($relatedctxsql, $relatedctxparams) = $DB->get_in_or_equal($context->get_parent_context_ids(true),
            SQL_PARAMS_NAMED, 'relatedctx');
        $wheres[] = "u.id IN (SELECT userid FROM {role_assignments} WHERE roleid = :roleid AND contextid $relatedctxsql)";
        $params = array_merge($params, array('roleid' => $roleid), $relatedctxparams);
    }
    if (!empty($search)) {
        if (!is_array($search)) {
            $search = [$search];
        }
        foreach ($search as $index => $keyword) {
            $searchkey1 = 'search' . $index . '1';
            $searchkey2 = 'search' . $index . '2';
            $searchkey3 = 'search' . $index . '3';
            $searchkey4 = 'search' . $index . '4';
            $searchkey5 = 'search' . $index . '5';
            $searchkey6 = 'search' . $index . '6';
            $searchkey7 = 'search' . $index . '7';
            $conditions = array();
            // Search by fullname.
            $fullname = $DB->sql_fullname('u.firstname', 'u.lastname');
            $conditions[] = $DB->sql_like($fullname, ':' . $searchkey1, false, false);
            // Search by email.
            $email = $DB->sql_like('email', ':' . $searchkey2, false, false);
            if (!in_array('email', $userfields)) {
                $maildisplay = 'maildisplay' . $index;
                $userid1 = 'userid' . $index . '1';
                // Prevent users who hide their email address from being found by others
                // who aren't allowed to see hidden email addresses.
                $email = "(". $email ." AND (" .
                    "u.maildisplay <> :$maildisplay " .
                    "OR u.id = :$userid1". // User can always find himself.
                    "))";
                $params[$maildisplay] = \core_user::MAILDISPLAY_HIDE;
                $params[$userid1] = $USER->id;
            }
            $conditions[] = $email;
            // Search by idnumber.
            $idnumber = $DB->sql_like('idnumber', ':' . $searchkey3, false, false);
            if (!in_array('idnumber', $userfields)) {
                $userid2 = 'userid' . $index . '2';
                // Users who aren't allowed to see idnumbers should at most find themselves
                // when searching for an idnumber.
                $idnumber = "(". $idnumber . " AND u.id = :$userid2)";
                $params[$userid2] = $USER->id;
            }
            $conditions[] = $idnumber;
            // Search by middlename.
            $middlename = $DB->sql_like('middlename', ':' . $searchkey4, false, false);
            $conditions[] = $middlename;
            // Search by alternatename.
            $alternatename = $DB->sql_like('alternatename', ':' . $searchkey5, false, false);
            $conditions[] = $alternatename;
            // Search by firstnamephonetic.
            $firstnamephonetic = $DB->sql_like('firstnamephonetic', ':' . $searchkey6, false, false);
            $conditions[] = $firstnamephonetic;
            // Search by lastnamephonetic.
            $lastnamephonetic = $DB->sql_like('lastnamephonetic', ':' . $searchkey7, false, false);
            $conditions[] = $lastnamephonetic;
            $wheres[] = "(". implode(" OR ", $conditions) .") ";
            $params[$searchkey1] = "%$keyword%";
            $params[$searchkey2] = "%$keyword%";
            $params[$searchkey3] = "%$keyword%";
            $params[$searchkey4] = "%$keyword%";
            $params[$searchkey5] = "%$keyword%";
            $params[$searchkey6] = "%$keyword%";
            $params[$searchkey7] = "%$keyword%";
        }
    }
    if (!empty($additionalwhere)) {
        $wheres[] = $additionalwhere;
        $params = array_merge($params, $additionalparams);
    }
    $from = implode("\n", $joins);
    if ($wheres) {
        $where = 'WHERE ' . implode(' AND ', $wheres);
    } else {
        $where = '';
    }
    return array($select, $from, $where, $params);
}
/**
 * Returns the total number of participants for a given course.
 *
 * @param int $courseid The course id
 * @param int $groupid The groupid, 0 means all groups
 * @param int $accesssince The time since last access, 0 means any time
 * @param int $roleid The role id, 0 means all roles
 * @param int $enrolid The applied filter for the user enrolment ID.
 * @param int $status The applied filter for the user's enrolment status.
 * @param string|array $search The search that was performed, empty means perform no search
 * @param string $additionalwhere Any additional SQL to add to where
 * @param array $additionalparams The additional params
 * @return int
 */
function user_get_total_participants($courseid, $groupid = 0, $accesssince = 0, $roleid = 0, $enrolid = 0, $statusid = -1,
                                     $search = '', $additionalwhere = '', $additionalparams = array()) {
    global $DB;
    list($select, $from, $where, $params) = user_get_participants_sql($courseid, $groupid, $accesssince, $roleid, $enrolid,
        $statusid, $search, $additionalwhere, $additionalparams);
    return $DB->count_records_sql("SELECT COUNT(u.id) $from $where", $params);
}

/**
 * Gets all the user roles assigned in this context, or higher contexts for a list of users.
 *
 * @param context $context
 * @param array $userids. An empty list means fetch all role assignments for the context.
 * @param bool $checkparentcontexts defaults to true
 * @param string $order defaults to 'c.contextlevel DESC, r.sortorder ASC'
 * @return array
 */
function get_users_roles(context $context, $userids = [], $checkparentcontexts = true, $order = 'c.contextlevel DESC, r.sortorder ASC') {
    global $USER, $DB;
    if ($checkparentcontexts) {
        $contextids = $context->get_parent_context_ids();
    } else {
        $contextids = array();
    }
    $contextids[] = $context->id;
    list($contextids, $params) = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'con');
    // If userids was passed as an empty array, we fetch all role assignments for the course.
    if (empty($userids)) {
        $useridlist = ' IS NOT NULL ';
        $uparams = [];
    } else {
        list($useridlist, $uparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uids');
    }
    $sql = "SELECT ra.*, r.name, r.shortname, ra.userid
              FROM {role_assignments} ra, {role} r, {context} c
             WHERE ra.userid $useridlist
                   AND ra.roleid = r.id
                   AND ra.contextid = c.id
                   AND ra.contextid $contextids
          ORDER BY $order";
    $all = $DB->get_records_sql($sql , array_merge($params, $uparams));
    // Return results grouped by userid.
    $result = [];
    foreach ($all as $id => $record) {
        if (!isset($result[$record->userid])) {
            $result[$record->userid] = [];
        }
        $result[$record->userid][$record->id] = $record;
    }
    // Make sure all requested users are included in the result, even if they had no role assignments.
    foreach ($userids as $id) {
        if (!isset($result[$id])) {
            $result[$id] = [];
        }
    }
    return $result;
}

/**
 * Gets a list of roles that this user can view in a context
 *
 * @param context $context a context.
 * @param int $userid id of user.
 * @return array an array $roleid => $rolename.
 */
function get_viewable_roles(context $context, $userid = null) {
    global $USER, $DB;
    if ($userid == null) {
        $userid = $USER->id;
    }
    $params = array();
    $extrajoins = '';
    $extrawhere = '';
    if (!is_siteadmin()) {
        // Admins are allowed to view any role.
        // Others are subject to the additional constraint that the view role must be allowed by
        // 'role_allow_view' for some role they have assigned in this context or any parent.
        $contexts = $context->get_parent_context_ids(true);
        list($insql, $inparams) = $DB->get_in_or_equal($contexts, SQL_PARAMS_NAMED);
        $extrajoins = "JOIN {role_allow_view} ras ON ras.allowview = r.id
                       JOIN {role_assignments} ra ON ra.roleid = ras.roleid";
        $extrawhere = "WHERE ra.userid = :userid AND ra.contextid $insql";
        $params += $inparams;
        $params['userid'] = $userid;
    }
    if ($coursecontext = $context->get_course_context(false)) {
        $params['coursecontext'] = $coursecontext->id;
    } else {
        $params['coursecontext'] = 0; // No course aliases.
        $coursecontext = null;
    }
    $query = "
        SELECT r.id, r.name, r.shortname, rn.name AS coursealias, r.sortorder
          FROM {role} r
          $extrajoins
     LEFT JOIN {role_names} rn ON (rn.contextid = :coursecontext AND rn.roleid = r.id)
          $extrawhere
      GROUP BY r.id, r.name, r.shortname, rn.name, r.sortorder
      ORDER BY r.sortorder";
    $roles = $DB->get_records_sql($query, $params);
    return role_fix_names($roles, $context, ROLENAME_ALIAS, true);
}
