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
 * mod_reengagement Data provider.
 *
 * @package    mod_reengagement
 * @copyright  2018 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_reengagement\privacy;
defined('MOODLE_INTERNAL') || die();

use context;
use context_module;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\{writer, transform, helper, contextlist, approved_contextlist};
use stdClass;

/**
 * Data provider for mod_reengagement.
 *
 * @copyright 2018 Catalyst IT
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider implements
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\metadata\provider
{

    /**
     * Returns meta data about this system.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection) : collection {
        $collection->add_database_table(
            'reengagement_inprogress',
            [
                'reengagement' => 'privacy:metadata:reengagement',
                'userid' => 'privacy:metadata:userid',
                'completiontime' => 'privacy:metadata:completiontime',
                'emailtime' => 'privacy:metadata:emailtime',
                'emailsent' => 'privacy:metadata:emailsent'
            ],
            'privacy:metadata:reengagement_inprogress'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist $contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid) : contextlist {
        return (new contextlist)->add_from_sql(
            "SELECT ctx.id
                 FROM {course_modules} cm
                 JOIN {modules} m ON cm.module = m.id AND m.name = :modulename
                 JOIN {reengagement} r ON cm.instance = r.id
                 JOIN {reengagement_inprogress} rip ON rip.reengagement = r.id
                 JOIN {context} ctx ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                 WHERE rip.userid = :userid",
            [
                'modulename' => 'reengagement',
                'contextlevel' => CONTEXT_MODULE,
                'userid' => $userid
            ]
        );
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(context $context) {
        global $DB;

        if (!$context instanceof context_module) {
            return;
        }

        if (!$cm = get_coursemodule_from_id('reengagement', $context->instanceid)) {
            return;
        }

        // Delete all information recorded against sessions associated with this module.
        $DB->delete_records_select(
            'reengagement_inprogress',
            "reengagement = :reengagementid",
            [
                'reengagementid' => $cm->instance
            ]
        );
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $userid = (int)$contextlist->get_user()->id;

        foreach ($contextlist as $context) {
            if (!$context instanceof context_module) {
                continue;
            }

            if (!$cm = get_coursemodule_from_id('reengagement', $context->instanceid)) {
                continue;
            }

            $DB->delete_records_select(
                'reengagement_inprogress',
                "reengagement = :reengagementid AND userid = :userid",
                [
                    'reengagementid' => $cm->instance,
                    'userid' => $userid
                ]
            );
        }
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $params = [
            'modulename' => 'reengagement',
            'contextlevel' => CONTEXT_MODULE,
            'userid' => $contextlist->get_user()->id
        ];

        list($contextsql, $contextparams) = $DB->get_in_or_equal($contextlist->get_contextids(), SQL_PARAMS_NAMED);

        $sql = "SELECT
                    rip.*,
                    ctx.id as contextid,
                    r.name as reengagementname
                    FROM {course_modules} cm
                    JOIN {reengagement} r ON cm.instance = r.id
                    JOIN {reengagement_inprogress} rip ON rip.reengagement = r.id
                    JOIN {context} ctx ON cm.id = ctx.instanceid
                    WHERE rip.userid = :userid AND (ctx.id {$contextsql})";

        $reengagements = $DB->get_records_sql($sql, $params + $contextparams);

        $bycontext = self::group_by_property($reengagements, 'contextid');

        foreach ($bycontext as $contextid => $sessions) {
            $context = context::instance_by_id($contextid);
            $sessionsbyid = self::group_by_property($sessions, 'reengagement');

            foreach ($sessionsbyid as $sessionid => $sessions) {
                writer::with_context($context)->export_data(
                    [get_string('reengagement', 'reengagement') . ' ' . $sessionid],
                    (object)[array_map([self::class, 'transform_db_row_to_data'], $sessions)]
                );
            };
        }
    }

    /**
     * Helper function to group an array of stdClasses by a common property.
     *
     * @param array $classes An array of classes to group.
     * @param string $property A common property to group the classes by.
     */
    private static function group_by_property(array $classes, string $property) : array {
        return array_reduce(
            $classes,
            function (array $classes, stdClass $class) use ($property) : array {
                $classes[$class->{$property}][] = $class;
                return $classes;
            },
            []
        );
    }

    /**
     * Helper function to transform a row from the database in to session data to export.
     *
     * The properties of the "dbrow" are very specific to the result of the SQL from
     * the export_user_data function.
     *
     * @param stdClass $dbrow A row from the database containing session information.
     * @return stdClass The transformed row.
     */
    private static function transform_db_row_to_data(stdClass $dbrow) : stdClass {
        return (object) [
            'name' => $dbrow->reengagementname,
            'userid' => $dbrow->userid,
            'completiontime' => transform::datetime($dbrow->completiontime),
            'emailtime' => transform::datetime($dbrow->emailtime),
            'emailsent' => $dbrow->emailsent,
            'completed' => $dbrow->completed
        ];
    }
}