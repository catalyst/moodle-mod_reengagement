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
        if (!$COURSE->enablecompletion) {
            $coursecontext = context_course::instance($COURSE->id);
            if (has_capability('moodle/course:update', $coursecontext)) {
                $mform->addElement('static', 'completionwillturnon', get_string('completion', 'reengagement'), get_string('completionwillturnon', 'reengagement'));
            }
        }

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
        $emailuseroptions = array(); // The sorts of emailing this module might do.
        $emailuseroptions[REENGAGEMENT_EMAILUSER_NEVER] = get_string('never', 'reengagement');
        $emailuseroptions[REENGAGEMENT_EMAILUSER_COMPLETION] = get_string('oncompletion', 'reengagement');
        $emailuseroptions[REENGAGEMENT_EMAILUSER_TIME] = get_string('afterdelay', 'reengagement');

        $mform->addElement('select', 'emailuser', get_string('emailuser', 'reengagement'), $emailuseroptions);
        $mform->addHelpButton('emailuser', 'emailuser','reengagement');

        // Add options to control who any notifications should go to.
        $emailrecipientoptions = array(); // The message recipient options.
        $emailrecipientoptions[REENGAGEMENT_RECIPIENT_USER] = get_string('user');
        $emailrecipientoptions[REENGAGEMENT_RECIPIENT_MANAGER] = get_string('manager', 'role');
        $emailrecipientoptions[REENGAGEMENT_RECIPIENT_BOTH] = get_string('userandmanager', 'reengagement');

        $mform->addElement('select', 'emailrecipient', get_string('emailrecipient', 'reengagement'), $emailrecipientoptions);
        $mform->addHelpButton('emailrecipient', 'emailrecipient','reengagement');

        // Add a group of controls to specify after how long an email should be sent.
        $emaildelay;
        $periods = array();
        $periods[60] = get_string('minutes','reengagement');
        $periods[3600] = get_string('hours','reengagement');
        $periods[86400] = get_string('days','reengagement');
        $periods[604800] = get_string('weeks','reengagement');
        $emaildelay[] = $mform->createElement('text', 'emailperiodcount', '', array('class="emailperiodcount"'));
        $emaildelay[] = $mform->createElement('select', 'emailperiod', '', $periods);
        $mform->addGroup($emaildelay, 'emaildelay', get_string('emaildelay','reengagement'), array(' '), false);
        $mform->addHelpButton('emaildelay', 'emaildelay', 'reengagement');
        $mform->setType('emailperiodcount', PARAM_INT);
        $mform->setDefault('emailperiodcount','1');
        $mform->setDefault('emailperiod','604800');

        $mform->addElement('text', 'emailsubject', get_string('emailsubject', 'reengagement'), array('size'=>'64'));
        $mform->setType('emailsubject', PARAM_TEXT);
        $mform->addRule('emailsubject', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('emailsubject', 'emailsubject', 'reengagement');
        $mform->addElement('editor', 'emailcontent', get_string('emailcontent', 'reengagement'), null, null);
        $mform->setDefault('emailcontent', get_string('emailcontentdefaultvalue','reengagement'));
        $mform->setType('emailcontent', PARAM_CLEANHTML);
        $mform->addHelpButton('emailcontent', 'emailcontent', 'reengagement');

        $mform->addElement('text', 'emailsubjectmanager', get_string('emailsubjectmanager', 'reengagement'), array('size'=>'64'));
        $mform->setType('emailsubjectmanager', PARAM_TEXT);
        $mform->addRule('emailsubjectmanager', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('emailsubjectmanager', 'emailsubjectmanager', 'reengagement');
        $mform->addElement('editor', 'emailcontentmanager', get_string('emailcontentmanager', 'reengagement'), null, null);
        $mform->setDefault('emailcontentmanager', get_string('emailcontentmanagerdefaultvalue','reengagement'));
        $mform->setType('emailcontentmanager', PARAM_CLEANHTML);
        $mform->addHelpButton('emailcontentmanager', 'emailcontentmanager', 'reengagement');

        $mform->addElement('advcheckbox', 'suppressemail', get_string('suppressemail', 'reengagement'));
        $mform->addHelpbutton('suppressemail', 'suppressemail', 'reengagement');
        $truemods = get_fast_modinfo($COURSE->id);
        $mods = array();
        $mods[0] = get_string('nosuppresstarget', 'reengagement');
        foreach ($truemods->cms as $mod) {
            $mods[$mod->id] = $mod->name;
        }
        $mform->addElement('select', 'suppresstarget', get_string('suppresstarget', 'reengagement'), $mods);
        $mform->addHelpbutton('suppresstarget', 'suppresstarget', 'reengagement');

//-------------------------------------------------------------------------------
        // add standard elements, common to all modules
        $this->standard_coursemodule_elements();
        if ($mform->elementExists('completion')) {
            $mform->setDefault('completion', COMPLETION_TRACKING_AUTOMATIC);
            $mform->freeze('completion');
        }
        if ($mform->elementExists('visible')) {
            $mform->removeElement('visible');
        }
//-------------------------------------------------------------------------------
        // add standard buttons, common to all modules
        $this->add_action_buttons();

    }
    function set_data ($toform) {
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
        $toform->emailcontent = array('text'=>$toform->emailcontent, 'format'=>$toform->emailcontentformat);

        if (empty($toform->emailcontentmanager)) {
            $toform->emailcontentmanager = '';
        }
        if (empty($toform->emailcontentmanagerformat)) {
            $toform->emailcontentmanagerformat = 1;
        }
        $toform->emailcontentmanager = array('text'=>$toform->emailcontentmanager, 'format'=>$toform->emailcontentmanagerformat);

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

    function get_data() {
        $fromform = parent::get_data();
        if (!empty($fromform)) {
            // Force completion tracking to automatic.
            $fromform->completion = COMPLETION_TRACKING_AUTOMATIC;
            // Force activity to hidden.
            $fromform->visible = 0;
            // Format, regulate module duration:
            if (isset($fromform->period) && isset($fromform->periodcount)) {
                $fromform->duration = $fromform->period * $fromform->periodcount;
            }
            if (empty($fromform->duration) || $fromform->duration < 300) {
                $fromform->duration = 300;
            }
            unset($fromform->period);
            unset($fromform->periodcount);
            // Format, regulate email notification delay:
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
            $fromform->emailcontentmanagerformat = $fromform->emailcontentmanager['format'];
            $fromform->emailcontentmanager = $fromform->emailcontentmanager['text'];
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
