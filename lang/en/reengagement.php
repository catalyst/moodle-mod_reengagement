<?php

$string['pluginname'] = 'Reengagement';
$string['reengagement'] = 'reengagement';
$string['pluginadministration'] = '';
$string['modulename'] = 'Reengagement';
$string['modulenameplural'] = 'Reengagements';

$string['admintext'] = 'Admin Text';
$string['admintext_help'] = 'Reengagement administrators are sent a message each time a user completes their reengagement. &quot;Admin text&quot; is the text which the admin will be sent.';
$string['admintextdefaultvalue'] = 'A user has just completed a reengagement module in which your are configured as admin.';
$string['days'] = 'Days';
$string['duration'] = 'Duration';
$string['duration_help'] = '<p>The reengagement duration is the period of time between a user starting a reengagement, and being marked as finished.
The reengagement duration is specified as a period length (eg Weeks) and number of period (eg 7).</p>

<p>This example would mean that a user starting a reengagement period now would be marked as compete in 7 weeks time.</p>
';
$string['emailuser'] = 'Email User';
$string['emailuser_help'] = 'If set, this instructs the reengagement module to email users when their reengagement period is complete.';
$string['hours'] = 'Hours';
$string['introdefaultvalue'] = 'This is a reengagement activity.  Its purpose is to enforce a time lapse between the activities which preceed it, and the activities which follow it.';
$string['minutes'] = 'Minutes';
$string['reengagementduration'] = 'Renengagement Duration';
$string['reengagementfieldset'] = 'Renengagement details';
$string['reengagementintro'] = 'Renengagement Intro';
$string['reengagementname'] = 'Renengagement Name';
$string['reengagementsinprogress'] = 'Renengagements in progress';
$string['usertext'] = 'User Text';
$string['usertext_help'] = 'If the reengagement has been configured to send an email to the user at the end of their reengagement, &quot;User text&quot; is the text they will be sent';
$string['usertextdefaultvalue'] = 'Your stand down is now complete.  You can now log back into your Moodle course to view any new activities that are available to you.';
$string['weeks'] = 'Weeks';
$string['supressemail'] = 'Supress email if target activity complete';
$string['supressemail_help'] = 'With this option you can configure the reengagement so that it will first check if the target activity is complete before deciding to send an email. If the target activity is complete then it will not send the email. This can be useful for sending reminder emails to a user to complete a survey if they have not already done so';
$string['supresstarget'] = 'Target activity';
$string['nosupresstarget'] = 'No target activity selected';
$string['supresstarget_help'] = 'Use this dropdown to choose which activity should be checked for completion before sending the reminder email';

$string['reengagement:startreengagement'] =  'Start Renengagement';
$string['reengagement:getnotifications'] =  'Receive notification of reengagement completions';
$string['reengagement:editreengagementduration'] =  'Edit Renengagement Duration';
?>
