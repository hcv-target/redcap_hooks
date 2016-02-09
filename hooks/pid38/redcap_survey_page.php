<?php
/**
 * Created by NC TraCS.
 * User: kenbergquist
 * Date: 7/14/15
 * Time: 4:17 PM
 */
/**
 * use globals
 */
global $Proj;
/**
 * DEBUG
 */
$debug = false;
if ($debug) {
	$timer = array();
	$timer['start'] = microtime(true);
	error_log("DEBUG: " . $project_id . ' ' . $record . ' ' . $instrument . ' ' . $event_id . ' ' . $group_id);
}
/**
 * includes
 */
$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . "/hooks/global/clean_variables.php";
require_once $base_path . '/plugins/includes/functions.php';
require_once $base_path . '/plugins/includes/prioritize_functions.php';
require_once APP_PATH_DOCROOT . '/Config/init_project.php';
Kint::enabled($debug);
/**
 * set up get_hook_terms, like those needed for autocomplete
 */
if (!isset($hook_terms)) {
	$file = $base_path . '/hooks/resources/get_hook_terms.php';
	if (file_exists($file)) {
		include_once $file;
	} else {
		error_log("ERROR: hook_terms array in " . __FILE__ . " not loaded.");
	}
}
/**
 * set up autocomplete
 */
$file = $base_path . '/hooks/resources/autocomplete.php';
if (file_exists($file)) {
	require_once $file;
} else {
	error_log("ERROR: $file in " . __FILE__ . " not loaded.");
}
/**
 * init vars
 */
//$redcap_event_name = $Proj->getUniqueEventNames($event_id);