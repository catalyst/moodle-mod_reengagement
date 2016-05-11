<?php
function xmldb_reengagement_install() {
    global $DB;
    $settings = array('s__enablecompletion' => 1, 's__enableavailability' => 1);
    admin_write_settings($settings);
}
?>
