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
 * This file contains the forms to create and edit an instance of this module
 *
 * @package    mod_reengagement
 * @author     Peter Bulmer <peter.bulmer@catlayst.net.nz>
 * @copyright  2016 Catalyst IT {@link http://www.catalyst.net.nz}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot.'/course/moodleform_mod.php');

/**
 * Moodleform class.
 *
 * @package    mod_reengagement
 * @author     Peter Bulmer <peter.bulmer@catlayst.net.nz>
 * @copyright  2016 Catalyst IT {@link http://www.catalyst.net.nz}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_reengagement_mod_form extends moodleform_mod {

    /**
     * Called to define this moodle form
     *
     * @return void
     */
    public function definition() {

        global $COURSE, $CFG;
        $mform =& $this->_form;

        // Adding the "general" fieldset, where all the common settings are shown.
        $mform->addElement('header', 'general', get_string('general', 'form'));
        if (!$COURSE->enablecompletion) {
            $coursecontext = context_course::instance($COURSE->id);
            if (has_capability('moodle/course:update', $coursecontext)) {
                $mform->addElement('static', 'completionwillturnon', get_string('completion', 'reengagement'),
                                   get_string('completionwillturnon', 'reengagement'));
            }
        }

        $istotara = false;
        if (file_exists($CFG->wwwroot.'/totara/hierarchy')) {
            $istotara = true;
        }

        // Adding the standard "name" field.
        $mform->addElement('text', 'name', get_string('reengagementname', 'reengagement'), array('size' => '64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // Adding the rest of reengagement settings, spreeading all them into this fieldset
        // or adding more fieldsets ('header' elements) if needed for better logic.
        $mform->addElement('header', 'reengagementfieldset', get_string('reengagementfieldset', 'reengagement'));
        $mform->setExpanded('reengagementfieldset', true);

        // Adding email detail fields:
        $emailuseroptions = array(); // The sorts of emailing this module might do.
        $emailuseroptions[REENGAGEMENT_EMAILUSER_NEVER] = get_string('never', 'reengagement');
        $emailuseroptions[REENGAGEMENT_EMAILUSER_COMPLETION] = get_string('oncompletion', 'reengagement');
        $emailuseroptions[REENGAGEMENT_EMAILUSER_TIME] = get_string('afterdelay', 'reengagement');

        $mform->addElement('select', 'emailuser', get_string('emailuser', 'reengagement'), $emailuseroptions);
        $mform->addHelpButton('emailuser', 'emailuser', 'reengagement');

        if ($istotara) {
            // Add options to control who any notifications should go to.
            $emailrecipientoptions = array(); // The message recipient options.
            $emailrecipientoptions[REENGAGEMENT_RECIPIENT_USER] = get_string('user');
            $emailrecipientoptions[REENGAGEMENT_RECIPIENT_MANAGER] = get_string('manager', 'role');
            $emailrecipientoptions[REENGAGEMENT_RECIPIENT_BOTH] = get_string('userandmanager', 'reengagement');

            $mform->addElement('select', 'emailrecipient', get_string('emailrecipient', 'reengagement'), $emailrecipientoptions);
            $mform->addHelpButton('emailrecipient', 'emailrecipient', 'reengagement');
        } else {
            $mform->addElement('hidden', 'emailrecipient', REENGAGEMENT_RECIPIENT_USER);
            $mform->setType('emailrecipient', PARAM_INT);
        }

        // Add a group of controls to specify after how long an email should be sent.
        $emaildelay = array();
        $periods = array();
        $periods[60] = get_string('minutes', 'reengagement');
        $periods[3600] = get_string('hours', 'reengagement');
        $periods[86400] = get_string('days', 'reengagement');
        $periods[604800] = get_string('weeks', 'reengagement');
        $emaildelay[] = $mform->createElement('text', 'emailperiodcount', '', array('class="emailperiodcount"'));
        $emaildelay[] = $mform->createElement('select', 'emailperiod', '', $periods);
        $mform->addGroup($emaildelay, 'emaildelay', get_string('emaildelay', 'reengagement'), array(' '), false);
        $mform->addHelpButton('emaildelay', 'emaildelay', 'reengagement');
        $mform->setType('emailperiodcount', PARAM_INT);
        $mform->setDefault('emailperiodcount', '1');
        $mform->setDefault('emailperiod', '604800');
        $mform->disabledif('emaildelay', 'emailuser', 'neq', REENGAGEMENT_EMAILUSER_TIME);

        // Add frequency of e-mails.
        $mform->addElement('text', 'remindercount', get_string('remindercount', 'reengagement'), array('maxlength' => '2'));
        $mform->setType('remindercount', PARAM_INT);
        $mform->setDefault('remindercount', '1');
        $mform->addRule('remindercount', get_string('err_numeric', 'form'), 'numeric', '', 'client');
        $mform->addHelpButton('remindercount', 'remindercount', 'reengagement');
        $mform->disabledif('remindercount', 'emailuser', 'neq', REENGAGEMENT_EMAILUSER_TIME);

        $mform->addElement('text', 'emailsubject', get_string('emailsubject', 'reengagement'), array('size' => '64'));
        $mform->setType('emailsubject', PARAM_TEXT);
        $mform->addRule('emailsubject', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->disabledif('emailsubject', 'emailuser', 'eq', REENGAGEMENT_EMAILUSER_NEVER);
        $mform->addHelpButton('emailsubject', 'emailsubject', 'reengagement');
        $mform->addElement('editor', 'emailcontent', get_string('emailcontent', 'reengagement'), null, null);
        $mform->setDefault('emailcontent', get_string('emailcontentdefaultvalue', 'reengagement'));
        $mform->setType('emailcontent', PARAM_RAW);
        $mform->disabledif('emailcontent', 'emailuser', 'eq', REENGAGEMENT_EMAILUSER_NEVER);

        if ($istotara) {
            $mform->addElement('text', 'emailsubjectmanager', get_string('emailsubjectmanager', 'reengagement'),
                               array('size' => '64'));
            $mform->setType('emailsubjectmanager', PARAM_TEXT);
            $mform->addRule('emailsubjectmanager', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
            $mform->disabledif('emailsubjectmanager', 'emailuser', 'eq', REENGAGEMENT_EMAILUSER_NEVER);
            $mform->addHelpButton('emailsubjectmanager', 'emailsubjectmanager', 'reengagement');
            $mform->addElement('editor', 'emailcontentmanager', get_string('emailcontentmanager', 'reengagement'), null, null);
            $mform->setDefault('emailcontentmanager', get_string('emailcontentmanagerdefaultvalue', 'reengagement'));
            $mform->setType('emailcontentmanager', PARAM_RAW);
            $mform->addHelpButton('emailcontentmanager', 'emailcontentmanager', 'reengagement');
        } else {
            $mform->addElement('hidden', 'emailsubjectmanager');
            $mform->setType('emailsubjectmanager', PARAM_ALPHA);
            $mform->addElement('hidden', 'emailcontentmanager');
            $mform->setType('emailcontentmanager', PARAM_ALPHA);
        }

        $mform->addElement('advcheckbox', 'suppressemail', get_string('suppressemail', 'reengagement'));
        $mform->disabledif('suppressemail', 'emailuser', 'eq', REENGAGEMENT_EMAILUSER_NEVER);
        $mform->addHelpbutton('suppressemail', 'suppressemail', 'reengagement');
        $truemods = get_fast_modinfo($COURSE->id);
        $mods = array();
        $mods[0] = get_string('nosuppresstarget', 'reengagement');
        foreach ($truemods->cms as $mod) {
            $mods[$mod->id] = $mod->name;
        }
        $mform->addElement('select', 'suppresstarget', get_string('suppresstarget', 'reengagement'), $mods);
        $mform->disabledif('suppresstarget', 'emailuser', 'eq', REENGAGEMENT_EMAILUSER_NEVER);
        $mform->addHelpbutton('suppresstarget', 'suppresstarget', 'reengagement');

        // Add standard elements, common to all modules.
        $this->standard_coursemodule_elements();
        if ($mform->elementExists('completion')) {
            $mform->setDefault('completion', COMPLETION_TRACKING_AUTOMATIC);
            $mform->freeze('completion');
        }
        if ($mform->elementExists('visible')) {
            $mform->removeElement('visible');
        }

        // Add standard buttons, common to all modules.
        $this->add_action_buttons();

    }

    /**
     * Load in existing data as form defaults
     *
     * @param stdClass|array $toform object or array of default values
     */
    public function set_data($toform) {
        global $CFG;
        $istotara = false;
        if (file_exists($CFG->wwwroot.'/totara/hierarchy')) {
            $istotara = true;
        }
        // Form expects durations as a number of periods eg 5 minutes.
        // Process dbtime (seconds) into form-appropraite times.
        if (!empty($toform->duration)) {
            list ($periodcount, $period) = reengagement_get_readable_duration($toform->duration);
            $toform->period = $period;
            $toform->periodcount = $periodcount;
            unset($toform->duration);
        }
        if (!empty($toform->emaildelay)) {
            list ($periodcount, $period) = reengagement_get_readable_duration($toform->emaildelay);
            $toform->emailperiod = $period;
            $toform->emailperiodcount = $periodcount;
            unset($toform->emaildelay);
        }
        if (empty($toform->emailcontent)) {
            $toform->emailcontent = '';
        }
        if (empty($toform->emailcontentformat)) {
            $toform->emailcontentformat = 1;
        }
        $toform->emailcontent = array('text' => $toform->emailcontent, 'format' => $toform->emailcontentformat);
        if ($istotara) {
            if (empty($toform->emailcontentmanager)) {
                $toform->emailcontentmanager = '';
            }
            if (empty($toform->emailcontentmanagerformat)) {
                $toform->emailcontentmanagerformat = 1;
            }
            $toform->emailcontentmanager = array('text' => $toform->emailcontentmanager,
                'format' => $toform->emailcontentmanagerformat);
        }

        if (empty($toform->suppresstarget)) {
            // There is no target activity specified.
            // Configure the box to have this dropdown disabled by default.
            $toform->suppressemail = 0;
        } else {
            // There is a target activity specified, enable the target selector so that the user can change it if desired.
            $toform->suppressemail = 1;
        }
        // Force completion tracking to automatic.
        $toform->completion = COMPLETION_TRACKING_AUTOMATIC;
        // Force activity to hidden.
        $toform->visible = 0;

        $result = parent::set_data($toform);
        return $result;
    }

    /**
     * Return the data that will be used upon saving.
     *
     * @return null|array
     */
    public function get_data() {
        global $CFG;
        $istotara = false;
        if (file_exists($CFG->wwwroot.'/totara/hierarchy')) {
            $istotara = true;
        }
        $fromform = parent::get_data();
        if (!empty($fromform)) {
            // Force completion tracking to automatic.
            $fromform->completion = COMPLETION_TRACKING_AUTOMATIC;
            // Force activity to hidden.
            $fromform->visible = 0;
            // Format, regulate module duration.
            if (isset($fromform->period) && isset($fromform->periodcount)) {
                $fromform->duration = $fromform->period * $fromform->periodcount;
            }
            if (empty($fromform->duration) || $fromform->duration < 300) {
                $fromform->duration = 300;
            }
            unset($fromform->period);
            unset($fromform->periodcount);
            // Format, regulate email notification delay.
            if (isset($fromform->emailperiod) && isset($fromform->emailperiodcount)) {
                $fromform->emaildelay = $fromform->emailperiod * $fromform->emailperiodcount;
            }
            if (empty($fromform->emaildelay) || $fromform->emaildelay < 300) {
                $fromform->emaildelay = 300;
            }
            unset($fromform->emailperiod);
            unset($fromform->emailperiodcount);
            // Some special handling for the wysiwyg editor field.
            $fromform->emailcontentformat = $fromform->emailcontent['format'];
            $fromform->emailcontent = $fromform->emailcontent['text'];
            if ($istotara) {
                $fromform->emailcontentmanagerformat = $fromform->emailcontentmanager['format'];
                $fromform->emailcontentmanager = $fromform->emailcontentmanager['text'];
            }
        }
        return $fromform;
    }
    /**
     *  Add custom completion rules for reengagement.
     *
     * @return array Array of string IDs of added items, empty array if none
     */
    public function add_completion_rules() {
        $mform =& $this->_form;
        $periods = array();
        $periods[60] = get_string('minutes', 'reengagement');
        $periods[3600] = get_string('hours', 'reengagement');
        $periods[86400] = get_string('days', 'reengagement');
        $periods[604800] = get_string('weeks', 'reengagement');
        $duration[] = &$mform->createElement('text', 'periodcount', '', array('class="periodcount"'));
        $mform->setType('periodcount', PARAM_INT);
        $duration[] = &$mform->createElement('select', 'period', '', $periods);
        $mform->addGroup($duration, 'duration', get_string('reengagementduration', 'reengagement'), array(' '), false);
        $mform->addHelpButton('duration', 'duration', 'reengagement');
        $mform->setDefault('periodcount', '1');
        $mform->setDefault('period', '604800');
        return array('duration');
    }

    /**
     * A custom completion rule is enabled by reengagement.
     *
     * @param array $data Input data (not yet validated)
     * @return bool True if one or more rules is enabled, false if none are;
     *   default returns false
     */
    public function completion_rule_enabled($data) {
        return true;
    }

    /**
     * Perform validation on the settings form
     * @param array $data
     * @param array $files
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (!empty($data['emailperiod'])) {
            $duration = $data['emailperiod'] * $data['emailperiodcount'];

            if ($duration < (60 * 60 * 24) && $data['remindercount'] > 2) {
                // If less than 24hrs, make sure only 2 e-mails can be sent.
                $errors['remindercount'] = get_string('frequencytoohigh', 'reengagement', 2);
            } else if ($duration < (60 * 60 * 24 * 5) && $data['remindercount'] > 10) {
                // If less than 5 days, make sure only 10 e-mails can be sent.
                $errors['remindercount'] = get_string('frequencytoohigh', 'reengagement', 10);
            } else if ($duration < (60 * 60 * 24 * 15) && $data['remindercount'] > 26) {
                // If less than 15 days, make sure only 26 e-mails can be sent.
                $errors['remindercount'] = get_string('frequencytoohigh', 'reengagement', 26);
            } else if ($data['remindercount'] > 40) {
                // Maximum number of reminders is set to 40 - we don't want to be emailing users for several years.
                $errors['remindercount'] = get_string('frequencytoohigh', 'reengagement', 40);
            }
        }

        if ($data['emailperiod'] == 60 && $data['emailperiodcount'] < 5) {
            $errors['emaildelay'] = get_string('periodtoolow', 'reengagement');
        }

        return $errors;
    }
}
