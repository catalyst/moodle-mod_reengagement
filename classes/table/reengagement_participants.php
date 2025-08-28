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
 * Contains the class used for the displaying the participants table.
 *
 * @package    mod_reengagement
 * @copyright  2018 Catalyst IT
 * @author     Dan Marsden <Dan@danmarsden.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_reengagement\table;

use context;
use context_module;

use core_table\local\filter\filterset;
use core_user\output\status_field;
use DateTime;
use moodle_url;

defined('MOODLE_INTERNAL') || die;

global $CFG;

require_once($CFG->libdir . '/tablelib.php');
require_once($CFG->dirroot . '/user/lib.php');

/**
 * Class for the displaying the participants table.
 *
 * @package    mod_reengagement
 * @copyright  2018 Catalyst IT
 * @author     Dan Marsden <Dan@danmarsden.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reengagement_participants extends \core_user\table\participants {

    /**
     * @var \stdclass $reengagement The reengagement record
     */
    protected $reengagement;

    /**
     * @var int $cmid The course module id
     */
    protected $cmid;

    /**
     * @var int $courseid The course id
     */
    protected $courseid;

    /**
     * @var int|false False if groups not used, int if groups used, 0 for all groups.
     */
    protected $currentgroup;

    /**
     * @var int $roleid The role we are including, 0 means all enrolled users
     */
    protected $roleid;

    /**
     * @var int $enrolid The applied filter for the user enrolment ID.
     */
    protected $enrolid;

    /**
     * @var int $status The applied filter for the user's enrolment status.
     */
    protected $status;

    /**
     * @var string $search The string being searched.
     */
    protected $search;

    /**
     * @var bool $selectall Has the user selected all users on the page?
     */
    protected $selectall;

    /**
     * @var string[] The list of countries.
     */
    protected $countries;

    /**
     * @var \stdClass[] The list of groups with membership info for the course.
     */
    protected $groups;

    /**
     * @var string[] Extra fields to display.
     */
    protected $extrafields;

    /**
     * @var \stdClass $course The course details.
     */
    protected $course;

    /**
     * @var  context $context The course context.
     */
    protected $context;

    /**
     * @var \stdClass[] List of roles indexed by roleid.
     */
    protected $allroles;

    /**
     * @var \stdClass[] List of roles indexed by roleid.
     */
    protected $allroleassignments;

    /**
     * @var \stdClass[] Assignable roles in this course.
     */
    protected $assignableroles;

    /**
     * @var \stdClass[] Profile roles in this course.
     */
    protected $profileroles;

    /** @var \stdClass[] $viewableroles */
    private $viewableroles;

    /**
     * Render the participants table.
     *
     * @param int $pagesize Size of page for paginated displayed table.
     * @param bool $useinitialsbar Whether to use the initials bar which will only be used if there is a fullname column defined.
     * @param string $downloadhelpbutton
     */
    public function out($pagesize, $useinitialsbar, $downloadhelpbutton = '') {
        global $CFG, $OUTPUT, $PAGE;

        // Define the headers and columns.
        $headers = [];
        $columns = [];

        $bulkoperations = has_capability('moodle/course:bulkmessaging', $this->context);
        if ($bulkoperations) {
            $mastercheckbox = new \core\output\checkbox_toggleall('participants-table', true, [
                'id' => 'select-all-reengagement-participants',
                'name' => 'select-all-reengagement-participants',
                'label' => get_string('selectall'),
                'labelclasses' => 'sr-only',
                'classes' => 'm-1',
                'checked' => false,
            ]);
            $headers[] = $OUTPUT->render($mastercheckbox);
            $columns[] = 'select';
        }

        $headers[] = get_string('fullname');
        $columns[] = 'fullname';

        $extrafields = \core_user\fields::for_identity($this->context)->get_required_fields();

        foreach ($extrafields as $field) {
            $headers[] = \core_user\fields::get_display_name($field);
            $columns[] = $field;
        }

        $headers[] = get_string('roles');
        $columns[] = 'roles';

        // Get the list of fields we have to hide.
        $hiddenfields = array();
        if (!has_capability('moodle/course:viewhiddenuserfields', $this->context)) {
            $hiddenfields = array_flip(explode(',', $CFG->hiddenuserfields));
        }

        // Add column for groups if the user can view them.
        $canseegroups = !isset($hiddenfields['groups']);
        if ($canseegroups) {
            $headers[] = get_string('groups');
            $columns[] = 'groups';
        }

        // Do not show the columns if it exists in the hiddenfields array.
        if (!isset($hiddenfields['lastaccess'])) {
            if ($this->courseid == SITEID) {
                $headers[] = get_string('lastsiteaccess');
            } else {
                $headers[] = get_string('lastcourseaccess');
            }
            $columns[] = 'lastaccess';
        }

        // Show notify time and Completion time columns.
        if (!in_array($this->reengagement->emailuser, array(REENGAGEMENT_EMAILUSER_NEVER, REENGAGEMENT_EMAILUSER_COMPLETION))) {
            $headers[] = get_string('emailtime', 'mod_reengagement');
            $columns[] = 'emailtime';
        }
        $headers[] = get_string('completiontime', 'mod_reengagement');
        $columns[] = 'completiontime';

        $canreviewenrol = has_capability('moodle/course:enrolreview', $this->context);
        if ($canreviewenrol && $this->courseid != SITEID) {
            $columns[] = 'status';
            $headers[] = get_string('participationstatus', 'enrol');
            $this->no_sorting('status');
        };

        $this->define_columns($columns);
        $this->define_headers($headers);

        // The name column is a header.
        $this->define_header_column('fullname');

        // Make this table sorted by last name by default.
        $this->sortable(true, 'lastname');

        $this->no_sorting('select');
        $this->no_sorting('roles');
        if ($canseegroups) {
            $this->no_sorting('groups');
        }

        $this->set_attribute('id', "reengagement-index-participants-$this->cmid");

        $this->countries = get_string_manager()->get_list_of_countries(true);
        $this->extrafields = $extrafields;
        if ($canseegroups) {
            $this->groups = groups_get_all_groups($this->courseid, 0, 0, 'g.*', true);
        }
        $this->allroles = role_fix_names(get_all_roles($this->context), $this->context);
        $this->assignableroles = get_assignable_roles($this->context, ROLENAME_ALIAS, false);
        $this->profileroles = get_profile_roles($this->context);
        $this->viewableroles = get_viewable_roles($this->context);

        parent::out($pagesize, $useinitialsbar, $downloadhelpbutton);

        if (has_capability('moodle/course:enrolreview', $this->context)) {
            $params = [
                'contextid' => $this->context->id,
                'uniqueid' => $this->uniqueid,
            ];
            $PAGE->requires->js_call_amd('core_user/status_field', 'init', [$params]);
        }
    }

    /**
     * Generate the notify time column.
     *
     * @param \stdClass $data The data object.
     * @return string
     */
    public function col_emailtime($data) {
        return $data->emailtime ? userdate($data->emailtime, get_string('strftimedatetimeshort', 'langconfig')) : '-';
    }

    /**
     * Generate the completion time column.
     *
     * @param \stdClass $data The data object.
     * @return string
     */
    public function col_completiontime($data) {
        return $data->completiontime ? userdate($data->completiontime, get_string('strftimedatetimeshort', 'langconfig')) : '-';
    }

    /**
     * Query the database for results to display in the table.
     *
     * @param int $pagesize size of page for paginated displayed table.
     * @param bool $useinitialsbar do you want to use the initials bar.
     */
    public function query_db($pagesize, $useinitialsbar = true) {
        list($twhere, $tparams) = $this->get_sql_where();
        $psearch = new \mod_reengagement\table\reengagement_search($this->course, $this->context, $this->filterset);

        $sort = $this->get_sql_sort();

        $rs = $psearch->get_participants(
            $twhere,
            $tparams,
            $sort,
            (int)$this->get_page_start(),
            (int)$this->get_page_size()
        );

        $this->rawdata = [];
        $total = 0;

        foreach ($rs as $user) {
            if ($total === 0) {
                $total = (int)$user->fullcount;
            }
            $this->rawdata[$user->id] = $user;
        }
        $rs->close();

        $this->pagesize($pagesize, $total);

        $this->allroleassignments = $this->rawdata
            ? get_users_roles($this->context, array_keys($this->rawdata), true, 'c.contextlevel DESC, r.sortorder ASC')
            : [];

        if ($useinitialsbar) {
            $this->initialbars(true);
        }
    }

    /**
     * Set filters and build table structure.
     *
     * @param filterset $filterset The filterset object to get the filters from.
     */
    public function set_filterset(filterset $filterset): void {
        global $DB;
        // Get the context.
        $this->cmid = $filterset->get_filter('courseid')->current();

        // Pretend the courseid is the cmid, as the core participants JS doesn't support additional filters.
        $cm = get_coursemodule_from_id('reengagement', $this->cmid, 0, false, MUST_EXIST);
        $this->courseid = $cm->course;
        $this->course = get_course($this->courseid);
        $this->reengagement = $DB->get_record('reengagement', array('id' => $cm->instance), '*', MUST_EXIST);

        $this->context = context_module::instance($this->cmid, MUST_EXIST);

        // Process the filterset.
        \table_sql::set_filterset($filterset);
    }

    /**
     * Guess the base url for the participants table.
     */
    public function guess_base_url(): void {
        $this->baseurl = new moodle_url('/mod/reengagement/view.php', ['id' => $this->cmid]);
    }
}
