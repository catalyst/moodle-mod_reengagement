<?php

/**
 * This file defines the main reengagement configuration form
 * It uses the standard core Moodle (>1.8) formslib. For
 * more info about them, please visit:
 *
 * http://docs.moodle.org/en/Development:lib/formslib.php
 *
 * The form must provide support for, at least these fields:
 *   - name: text element of 64cc max
 *
 * Also, it's usual to use these fields:
 *   - intro: one htmlarea element to describe the activity
 *            (will be showed in the list of activities of
 *             reengagement type (index.php) and in the header
 *             of the reengagement main page (view.php).
 *   - introformat: The format used to write the contents
 *             of the intro field. It automatically defaults
 *             to HTML when the htmleditor is used and can be
 *             manually selected if the htmleditor is not used
 *             (standard formats are: MOODLE, HTML, PLAIN, MARKDOWN)
 *             See lib/weblib.php Constants and the format_text()
 *             function for more info
 */

require_once($CFG->dirroot.'/course/moodleform_mod.php');

class mod_reengagement_mod_form extends moodleform_mod {

    function definition() {

        global $COURSE;
        $mform =& $this->_form;

//-------------------------------------------------------------------------------
    /// Adding the "general" fieldset, where all the common settings are showed
        $mform->addElement('header', 'general', get_string('general', 'form'));

    /// Adding the standard "name" field
        $mform->addElement('text', 'name', get_string('reengagementname', 'reengagement'), array('size'=>'64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

    /// Adding the required "intro" field to hold the description of the instance
        $this->add_intro_editor(true, get_string('reengagementintro', 'reengagement'));


//-------------------------------------------------------------------------------
    /// Adding the rest of reengagement settings, spreeading all them into this fieldset
    /// or adding more fieldsets ('header' elements) if needed for better logic
        $mform->addElement('header', 'reengagementfieldset', get_string('reengagementfieldset', 'reengagement'));

        /// Adding email detail fields:
        $mform->addElement('checkbox', 'emailuser', get_string('emailuser', 'reengagement'));
        $mform->addHelpButton('emailuser', 'emailuser','reengagement');
        $mform->addElement('textarea', 'usertext', get_string('usertext', 'reengagement'),array('rows=5','cols=60'));
        $mform->disabledIf('usertext', 'emailuser');
        $mform->setDefault('usertext', get_string('usertextdefaultvalue','reengagement'));
        $mform->setType('usertext', PARAM_RAW);
        $mform->addHelpButton('usertext', 'usertext', 'reengagement');
        $mform->addElement('textarea', 'admintext', get_string('admintext', 'reengagement'),array('rows=5','cols=60'));
        $mform->setType('admintext', PARAM_RAW);
        $mform->setDefault('admintext', get_string('admintextdefaultvalue','reengagement'));
        $mform->addHelpButton('admintext', 'admintext', 'reengagement');

        $mform->addElement('advcheckbox', 'supressemail', get_string('supressemail', 'reengagement'));
        $mform->addHelpbutton('supressemail', 'supressemail', 'reengagement');
        $truemods = get_fast_modinfo($COURSE->id);
        $mods = array();
        $mods[0] = get_string('nosupresstarget', 'reengagement');
        foreach ($truemods->cms as $mod) {
            $mods[$mod->id] = $mod->name;
        }
        $mform->addElement('select', 'supresstarget', get_string('supresstarget', 'reengagement'), $mods);
        $mform->addHelpbutton('supresstarget', 'supresstarget', 'reengagement');

//-------------------------------------------------------------------------------
        // add standard elements, common to all modules
        $this->standard_coursemodule_elements();
//-------------------------------------------------------------------------------
        // add standard buttons, common to all modules
        $this->add_action_buttons();

    }
    function set_data ($toform) {
        # Take a duration in seconds, and list it as a number of weeks if possible
        # Otherwise list it as days, otherwise hours, otherwise minutes (5 minute minimum)
        if (!empty($toform->duration)) {
            if ($toform->duration < 300) {
                $toform->period = 60;
                $toform->periodcount = 5;
            } else {
                $periods = array(604800, 86400, 3600, 60);
                foreach ($periods as $period) {
                    if ((($toform->duration % $period) == 0) || ($period == 60)) {
                        $toform->period = $period;
                        $toform->periodcount = floor((int)$toform->duration / (int)$period);
                        break;
                    }
                }
                if ($toform->period == 60) {
                    if ($toform->periodcount < 5) {
                        $toform->periodcount = 5;
                    }
                }
            }
            unset($toform->duration);
        }
        // if supress target !== 0, supressemail checkbox is true
        if(!empty($toform->supresstarget)) {
            $toform->supressemail = true;
        } else {
            $toform->supressemail = false;
        }
        return parent::set_data($toform);
    }

    function get_data() {
        $fromform = parent::get_data();
        if (!empty($fromform)) {
            if (isset($fromform->period) && isset($fromform->periodcount)) {
                $fromform->duration = $fromform->period * $fromform->periodcount;
            }
            if (empty($fromform->duration) || $fromform->duration < 300) {
                $fromform->duration = 300;
            }
        }
        return $fromform;
    }
    /**
     * Can be overridden to add custom completion rules if the module wishes
     * them. If overriding this, you should also override completion_rule_enabled.
     * <p>
     * Just add elements to the form as needed and return the list of IDs. The
     * system will call disabledIf and handle other behaviour for each returned
     * ID.
     * @return array Array of string IDs of added items, empty array if none
     */
    function add_completion_rules() {
        $mform =& $this->_form;
        $periods = array();
        $periods[60] = get_string('minutes','reengagement');
        $periods[3600] = get_string('hours','reengagement');
        $periods[86400] = get_string('days','reengagement');
        $periods[604800] = get_string('weeks','reengagement');
        $duration[] = &$mform->createElement('text', 'periodcount', '', array('class="periodcount"'));
        $mform->setType('periodcount', PARAM_INT);
        #$mform->addRule('periodcount', get_string('errperiodcountnumeric', 'reengagement'), 'numeric', '', 'server', false, false);
        #$mform->addRule('periodcount', get_string('errperiodcountnumeric', 'reengagement'), 'numeric', '', 'client', false, false);
        $duration[] = &$mform->createElement('select', 'period', '', $periods);
        $mform->addGroup($duration, 'duration', get_string('reengagementduration','reengagement'), array(' '), false);
        $mform->addHelpButton('duration', 'duration', 'reengagement');
        $mform->setDefault('periodcount','1');
        $mform->setDefault('period','604800');
        return array('duration');
    }

    function completion_rule_enabled($data) {
        return true;
    }
}

?>
