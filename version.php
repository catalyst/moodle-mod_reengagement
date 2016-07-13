<?php

/**
 * Code fragment to define the version of reengagement
 * This fragment is called by moodle_needs_upgrading() and /admin/index.php
 *
 * @author  Peter Bulmer <peter.bulmer@catlayst.net.nz>
 * @package mod/reengagement
 */

$plugin->version  = 2016042200;   // The current module version (Date: YYYYMMDDXX)
$plugin->requires  = 2011112900;
$plugin->cron     = 150;         // Don't run more often than every 150 seconds - realistically expect ~every 300s.
$plugin->component = 'mod_reengagement';
