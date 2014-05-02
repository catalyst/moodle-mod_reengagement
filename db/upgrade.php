<?php  //$Id: upgrade.php,v 1.2 2007/08/08 22:36:54 stronk7 Exp $


function xmldb_reengagement_upgrade($oldversion=0) {

    global $CFG, $THEME, $DB;
    $dbman = $DB->get_manager();
    $result = true;

    if ($oldversion < 2014050200) {

        // Define field supresstarget to be added to reengagement
        $table = new xmldb_table('reengagement');
        $field = new xmldb_field('supresstarget', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', 'duration');

        // Conditionally launch add field supresstarget
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // reengagement savepoint reached
        upgrade_mod_savepoint(true, 2014050200, 'reengagement');
    }

    return $result;
}

?>
