<?php


function xmldb_reengagement_upgrade($oldversion=0) {
    global $DB;
    $dbman = $DB->get_manager();
    $upgradeversion = 2014071701;
    if ($oldversion < $upgradeversion) {
        // Define new fields to support emailing managers.
        // Define field emailrecipient to be added to reengagement to record who should receive emails.
        $table = new xmldb_table('reengagement');
        $field = new xmldb_field('emailrecipient', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('reengagement');
        // Define field to hold the email subject which should be used in emails to user's managers.
        $field = new xmldb_field('emailsubjectmanager', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('reengagement');
        // Define field to hold the email content which should be used in emails to user's managers.
        $field = new xmldb_field('emailcontentmanager', XMLDB_TYPE_TEXT, null, null,null, null,null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('reengagement');
        $field = new xmldb_field('emailcontentmanagerformat', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, $upgradeversion, 'reengagement');
    }

    return true;
}

?>
