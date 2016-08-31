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
 * Strings for reengagement.
 *
 * @package    mod_reengagement
 * @author     Peter Bulmer <peter.bulmer@catlayst.net.nz>
 * @copyright  2016 Catalyst IT {@link http://www.catalyst.net.nz}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Reengagement';
$string['reengagement'] = 'reengagement';
$string['pluginadministration'] = '';
$string['modulename'] = 'Reengagement';
$string['modulenameplural'] = 'Reengagements';

// Alphabetized.
$string['activitycompleted'] = 'This activity has been marked as complete';
$string['afterdelay'] = 'After Delay';
$string['completion'] = 'Completion';
$string['completionwillturnon'] = 'Note that adding this activity to the course will enable activity completion.';
$string['completeattimex'] = 'This activity will complete at {$a}';
$string['completiontime'] = 'Completion Time';
$string['crontask'] = 'Reengagement cron task';
$string['days'] = 'Days';
$string['duration'] = 'Duration';
$string['duration_help'] = '<p>The reengagement duration is the period of time between a user starting a reengagement, and being marked as finished.
The reengagement duration is specified as a period length (eg Weeks) and number of period (eg 7).</p>

<p>This example would mean that a user starting a reengagement period now would be marked as compete in 7 weeks time.</p>
';
$string['emailcontent'] = 'Email Content (User)';
$string['emailcontent_help'] = 'When the module sends a user an email, it takes the email content from this field.';
$string['emailcontentmanager'] = 'Email Content (Manager)';
$string['emailcontentmanager_help'] = 'When the module sends a user\'s manager an email, it takes the email content from this field.';
$string['emailcontentdefaultvalue'] = 'This is a reminder email from course %coursename%.';
$string['emailcontentmanagerdefaultvalue'] = 'This is a reminder email from course %coursename%, regarding user %userfirstname% %userlastname%.';
$string['emaildelay'] = 'Email Delay';
$string['emaildelay_help'] = 'When module is set to email users "after delay", this setting controls how long the delay is.';
$string['emailrecipient'] = 'Email Recipient(s)';
$string['emailrecipient_help'] = 'When an email needs to be sent out to prompt a user\'s re-engagement with the course, this setting controls if an email is sent to the user, their manager, or both.';
$string['emailsubject'] = 'Email Subject (User)';
$string['emailsubject_help'] = 'When the module sends a user an email, it takes the email subject from this field.';
$string['emailsubjectmanager'] = 'Email Subject (Manager)';
$string['emailsubjectmanager_help'] = 'When the module sends a user\'s manager an email, it takes the email subject from this field.';
$string['emailtime'] = 'Email Time';
$string['emailuser'] = 'Email User';
$string['emailuser_help'] = 'When the activity should email users: <ul>
<li>Never: Don\'t email users.</li>
<li>On Completion: Email the user as the module completes.</li>
<li>After Delay: Email the user a set time after they have started the module.</li>
</ul>';
$string['frequencytoohigh'] = 'The maximum reminder count with the delay period you have set is {$a}.';
$string['periodtoolow'] = 'The delay is too low - it must be at least 5 minutes.';
$string['hours'] = 'Hours';
$string['introdefaultvalue'] = 'This is a reengagement activity.  Its purpose is to enforce a time lapse between the activities which preceed it, and the activities which follow it.';
$string['minutes'] = 'Minutes';
$string['never'] = 'Never';
$string['noemailattimex'] = 'Message scheduled for {$a} will not be sent because you have completed the target activity';
$string['nosuppresstarget'] = 'No target activity selected';
$string['oncompletion'] = 'On Completion';
$string['receiveemailattimex'] = 'Message will be sent on {$a}.';
$string['receiveemailattimexunless'] = 'Message will be sent on {$a} unless you complete target activity.';
$string['reengagement:addinstance'] = 'reengagement:addinstance';
$string['reengagement:startreengagement'] = 'Start Reengagement';
$string['reengagement:getnotifications'] = 'Receive notification of reengagement completions';
$string['reengagement:editreengagementduration'] = 'Edit Reengagement Duration';
$string['reengagementduration'] = 'Reengagement Duration';
$string['reengagementfieldset'] = 'Reengagement details';
$string['reengagementintro'] = 'Reengagement Intro';
$string['reengagementname'] = 'Reengagement Name';
$string['reengagementsinprogress'] = 'Reengagements in progress';
$string['remindercount'] = 'Reminder count';
$string['remindercount_help'] = 'This is the number of times an e-mail is sent after each delay period. There are some limits to the values you can use<ul>
<li>less than 24 hrs - limit of 2 reminders.</li>
<li>less than 5 days - limit of 10 reminders.</li>
<li>less than 15 days - limit of 26 reminders.</li>
<li>over 15 days - maximum limit of 40 reminders.</li></ul>';
$string['search:activity'] = 'Reengagement - activity information';
$string['suppressemail'] = 'Suppress email if target activity complete';
$string['suppressemail_help'] = 'This option instructs the activity to suppress emails to users where a named activity is complete.';
$string['suppresstarget'] = 'Target activity.';
$string['suppresstarget_help'] = 'Use this dropdown to choose which activity should be checked for completion before sending the reminder email.';
$string['userandmanager'] = 'User and Manager';
$string['weeks'] = 'Weeks';

