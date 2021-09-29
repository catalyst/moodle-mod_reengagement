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
 * Contains the class used for the integrating the plugin with report builder through the report builder api.
 * Defines the data source for 'reengagement' plugin tables 
 * @package    mod_reengagement
 * @copyright  2021 Catalyst IT
 * @author     Sumaiya Javed <sumaiya.javed@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_reengagement;


use core_user\fields;
use core_reportbuilder\local\filters\boolean_select;
use core_reportbuilder\local\filters\date;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\filters\text;
use core_reportbuilder\local\helpers\user_profile_fields;
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use core_reportbuilder\local\entities\base;
use context_system;
use html_writer;
use moodle_url;
use lang_string;
use stdClass;

class reengagement_report extends base {

    /**
     * Database tables that this entity uses and their default aliases
     *
     * @return array
     */
    protected function get_default_table_aliases(): array {
        return [
            'reengagement' => 'r', //plugin table
            'reengagement_inprogress' => 'ri', //plugin table
            'course_modules' => 'cm', //core table
            'modules' => 'mm', //core table
            'user' => 'u', //core table
            'course' => 'c', //core table
        ];
    }

    /**
     * The default title for this entity
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('reengagement', 'mod_reengagement');
    }

    /**
     * Initialise the entity, adding all reengagement and reengagement_inprogress fields
     *
     * @return base
     */
    public function initialise(): base {
        $columns = $this->get_all_columns();
        foreach ($columns as $column) {
            $this->add_column($column);
        }

        $filters = $this->get_all_filters();
        foreach ($filters as $filter) {
            $this->add_filter($filter);
        }

        return $this;
    }

     /**
     * reengagement table fields.
     *
     * @return array
     */
    protected function get_reengagement_fields(): array {
        return [
            'timecreated' => new lang_string('timecreated', 'reengagement'),
            'timemodified' => new lang_string('timemodified', 'reengagement'),
            'emailuser' => new lang_string('emailuser', 'reengagement'),
            'emailcontent' => new lang_string('emailcontent', 'reengagement'),
            'emailsubject' => new lang_string('emailsubject', 'reengagement'),            
            'emailcontentmanager' => new lang_string('emailcontentmanager', 'reengagement'),
            'emailsubjectmanager' => new lang_string('emailsubjectmanager', 'reengagement'),
            'emailrecipient' => new lang_string('emailrecipient', 'reengagement'),
            'thirdpartyemails' => new lang_string('thirdpartyemails', 'reengagement'),
            'emailsubjectthirdparty' => new lang_string('emailsubjectthirdparty', 'reengagement'),
            'emailcontentthirdparty' => new lang_string('emailcontentthirdparty', 'reengagement'),
            'duration' => new lang_string('reengagementduration', 'reengagement'),
            'remindercount' => new lang_string('remindercount', 'reengagement'),
            'suppresstarget' => new lang_string('suppresstarget', 'reengagement'),
            'emaildelay' => new lang_string('emaildelay', 'reengagement'),
        ];
    }

    /**
     * reengagement_inprogress table fields.
     *
     * @return array
     */
    protected function get_reengagement_inprogress_fields(): array {
        return [
            'completiontime' => new lang_string('completiontime', 'reengagement'),
            'emailtime' => new lang_string('emailtime', 'reengagement'),
            'emailsent' => new lang_string('emailsent', 'reengagement'),
            'completed' => new lang_string('completed', 'reengagement'),
        ];
    }
   
    /**
     * Returns list of all available columns
     *
     * These are all the columns available to use in any report that uses this entity.
     *
     * @return column[]
     */
    protected function get_all_columns(): array {
        $columns = [];

        $tablealias = $this->get_table_alias('reengagement');
        $tablealiasinprogress = $this->get_table_alias('reengagement_inprogress');
        $tablealiascoursemodules = $this->get_table_alias('course_modules');
        $tablealiasmodules = $this->get_table_alias('modules');
        $tablealiascourse = $this->get_table_alias('course');
        $tablealiasusers = $this->get_table_alias('user');

        //reengagement name from table reengagement     
        $columns[] = (new column(
        'name',
        new lang_string('reengagementname', 'reengagement'),
        $this->get_entity_name()
        ))
            ->add_join("LEFT JOIN {course_modules} {$tablealiascoursemodules} 
                    ON {$tablealiascoursemodules}.instance = {$tablealias}.id AND {$tablealiascoursemodules}.course = {$tablealias}.course")
            ->add_join("INNER JOIN {modules} {$tablealiasmodules} 
                    ON {$tablealiasmodules}.id = {$tablealiascoursemodules}.module AND {$tablealiasmodules}.name = 'reengagement'")
            ->set_is_sortable(true)
            ->add_field("{$tablealiascoursemodules}.id")
            ->add_field("{$tablealias}.name")
            ->add_callback(static function(?string $value, stdClass $row): string {
                if ($value === null) {
                    return '';
                }
                else {
                    return html_writer::link(new moodle_url('/mod/reengagement/view.php', ['id' => $row->id]),
                    $row->name);
                }
            });

        //course name from table reengagement
        $columns[] = (new column(
        'course',
        new lang_string('course', 'reengagement'),
        $this->get_entity_name()
        ))
            ->add_join("LEFT JOIN {course} {$tablealiascourse} 
                    ON {$tablealiascourse}.id = {$tablealias}.course")
            ->set_is_sortable(true)
            ->add_field("{$tablealiascourse}.id")
            ->add_field("{$tablealiascourse}.shortname")
            ->add_callback(static function(?string $value, stdClass $row): string {
                if ($value === null) {
                    return '';
                }
                else {
                    return html_writer::link(new moodle_url('/course/view.php', ['id' => $row->id]),
                    $row->shortname);
                }
            });
    
        //remaining fields from table reengagement
        $reengagementfields = $this->get_reengagement_fields();
        foreach ($reengagementfields as $reengagementfield => $reengagementfieldlang) {
            $column = (new column(
                $reengagementfield,
                $reengagementfieldlang,
                $this->get_entity_name()
            ))
                ->add_joins($this->get_joins())
                ->set_type($this->get_reengagement_field_type($reengagementfield))
                ->add_field("$tablealias.$reengagementfield") 
                ->add_callback(static function ($value, stdClass $row): string { 
                         if((isset($row->timecreated) && $row->timecreated > 0) ||  (isset($row->timemodified) && $row->timemodified > 0)) { 
                        return userdate($value);
                    }                  
                return strval($value);
                })               
                ->set_is_sortable($this->is_sortable($reengagementfield));
            $columns[] = $column;
        }

        //fields from table reengagement_inprogress
        $reengagementinprogressfields = $this->get_reengagement_inprogress_fields();
        foreach ($reengagementinprogressfields as $reengagementinprogressfield => $reengagementinprogressfieldlang) {
            $column = (new column(
                $reengagementinprogressfield,
                $reengagementinprogressfieldlang,
                $this->get_entity_name()
            ))
                ->add_join("LEFT JOIN {reengagement_inprogress} {$tablealiasinprogress} 
                            ON {$tablealias}.id = {$tablealiasinprogress}.reengagement")
                ->set_type($this->get_reengagement_field_type($reengagementinprogressfield))
                ->add_field("$tablealiasinprogress.$reengagementinprogressfield")   
                ->add_callback(static function ($value, stdClass $row): string { 
                    if((isset($row->emailtime) && $row->emailtime > 0) ||  (isset($row->completiontime) && $row->completiontime > 0)) { 
                        return userdate($value);
                    } 
                return strval($value);
                })              
                ->set_is_sortable(true);
            $columns[] = $column;
        }

        //username from table reengagement_inprogress        
        $columns[] = (new column(
        'username',
        new lang_string('username', 'reengagement'),
        $this->get_entity_name()
        ))
            ->add_join("LEFT JOIN {user} {$tablealiasusers} 
                        ON {$tablealiasusers}.id = {$tablealiasinprogress}.userid")
            ->set_is_sortable(true)
            ->add_field("{$tablealiasusers}.username")
            ->add_field("{$tablealiasusers}.id")
            ->add_callback(static function(?string $value, stdClass $row): string {
                if ($value === null) {
                    return '';
                }
                else {
                    return html_writer::link(new moodle_url('/user/profile.php', ['id' => $row->id]),
                    $row->username);
                }
            });      
        return $columns;
    }
   
    /**
     * Returns list of all available filters
     *
     * @return array
     */
    protected function get_all_filters(): array {
        global $DB;

        $filters = [];
        $tablealias = $this->get_table_alias('reengagement');

        $fields_reengagement = $this->get_reengagement_fields();
        $fields_reengagement_inprogress = $this->get_reengagement_inprogress_fields();
        //extra fields which were added to with extra formatting (like adding links) reengagementname and username 
        $fields_extra = [
            'name' => new lang_string('reengagementname', 'reengagement'),
            'course' => new lang_string('course', 'reengagement'),
            'username' => new lang_string('username', 'reengagement'),
        ];
        $fields = array_merge($fields_extra, $fields_reengagement,$fields_reengagement_inprogress );

        foreach ($fields as $field => $name) {
            // Filtering isn't supported for LONGTEXT fields on Oracle.
            if ($this->get_reengagement_field_type($field) === column::TYPE_LONGTEXT &&
                    $DB->get_dbfamily() === 'oracle') {
                continue;
            }

            $optionscallback = [static::class, 'get_options_for_' . $field];
            if (is_callable($optionscallback)) {
                $filterclass = select::class;
            } else if ($this->get_reengagement_field_type($field) === column::TYPE_BOOLEAN) {
                $filterclass = boolean_select::class;
            } else if ($this->get_reengagement_field_type($field) === column::TYPE_TIMESTAMP) {
                $filterclass = date::class;
            } else {
                $filterclass = text::class;
            }

            $filter = (new filter(
                $filterclass,
                $field,
                $name,
                $this->get_entity_name(),
                "{$tablealias}.$field"
            ))
                ->add_joins($this->get_joins());

            // Populate filter options by callback, if available.
            if (is_callable($optionscallback)) {
                $filter->set_options_callback($optionscallback);
            }

            $filters[] = $filter;
        }

        return $filters;
    }

    /**
     * Return appropriate column type for given fields for both of the plugin tables; reengagement and reengagement_inprogress
     *
     * @param string $reengagementfield
     * @return int
     */
    protected function get_reengagement_field_type(string $reengagementfield): int {
        switch ($reengagementfield) {
            case 'timecreated':
            case 'timemodified':
            case 'completiontime':                
                $fieldtype = column::TYPE_TIMESTAMP;
                break;
            case 'emailcontent':
                $fieldtype = column::TYPE_LONGTEXT;
                break;    
            case 'emailuser':
            case 'emailrecipient':     
            case 'duration':
            case 'remindercount':
            case 'emaildelay': 
            case 'suppresstarget':  
            case 'emaildelay': 
                $fieldtype = column::TYPE_INTEGER;
                break;
            case 'emailsubject':
            default:
                $fieldtype = column::TYPE_TEXT;
                break;
        }

        return $fieldtype;
    }

    /**
     * Check if this field is sortable
     *
     * @param string $fieldname
     * @return bool
     */
    protected function is_sortable(string $fieldname): bool {
        // Some columns can't be sorted, like longtext or images.
        $nonsortable = [
            'emailcontent',
            'emailcontentmanager',
            'thirdpartyemails',
            'emailcontentthirdparty',
        ];
        return !in_array($fieldname, $nonsortable);
    }
   
}
