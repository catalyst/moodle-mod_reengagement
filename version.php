<?php

/**
 * Code fragment to define the version of reengagement
 * This fragment is called by moodle_needs_upgrading() and /admin/index.php
 *
 * @author  Peter Bulmer <peter.bulmer@catlayst.net.nz>
 * @package mod/reengagement
 */

$module->version  = 2016042200;   // The current module version (Date: YYYYMMDDXX)
$module->requires  = 2011112900;
$module->cron     = 150;         // Don't run more often than every 150 seconds - realistically expect ~every 300s.
