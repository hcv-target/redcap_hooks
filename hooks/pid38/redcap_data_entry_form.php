<?php
/**
 * Created by HCV-TARGET.
 * User: kenbergquist
 * Date: 7/14/15
 * Time: 4:17 PM
 */
/**
 * DEBUG
 */
$debug = false;
global $Proj;
$subjects = ''; // '' = ALL
if ($debug) {
	$timer = array();
	$timer['start'] = microtime(true);
	error_log("DEBUG: " . $project_id . ' ' . $record . ' ' . $instrument . ' ' . $event_id . ' ' . $group_id);
}
/**
 * includes
 */
$base_path = dirname(dirname(dirname(__FILE__)));
//require_once $base_path . "/redcap_connect.php";
require_once $base_path . '/plugins/includes/functions.php';
require_once $base_path . '/plugins/includes/prioritize_functions.php';
require_once APP_PATH_DOCROOT . '/Config/init_project.php';
Kint::enabled($debug);
/**
 * init vars
 */
$redcap_event_name = $Proj->getUniqueEventNames($event_id);
/**
 * restricted use
 */
$allowed_pids = array('38');
REDCap::allowProjects($allowed_pids);
/**
 * do stuff
 */
if ($debug) {
	if (!is_form_locked($record, $instrument, $redcap_event_name)) {
		d(is_t_complete($record, $event_id), $redcap_event_name);
	}
	global $Proj, $project_id;
	$today = date("Y-m-d");
	$fields = array();
	$arms = get_arms(array_keys($Proj->eventsForms));
	d($arms);
	$baseline_event_id = $Proj->firstEventId;
	$tx_duration = get_single_field($record, $project_id, $baseline_event_id, 'trt_exdur', null);
	$tx_duration = substr($tx_duration, strpos($tx_duration, 'P')+1, strlen($tx_duration)-2);
	$tx_first_event = array_search_recursive($tx_duration . ' Weeks', $arms) !== false ? array_search_recursive($tx_duration . ' Weeks', $arms) : null;
	$survey_event_ids = $Proj->getEventsByArmNum($arms[$tx_first_event]['arm_num']);
	d($survey_event_ids);
	foreach ($survey_event_ids AS $survey_event_id) {
		$survey_event_name = $Proj->getUniqueEventNames($survey_event_id);
		$survey_prefix = substr($survey_event_name, 0, strpos($survey_event_name, '_'));
		$fields[] = $survey_prefix . '_completed';
		$fields[] = $survey_prefix . '_date';
		$fields[] = $survey_prefix . '_startdate';
		$fields[] = $survey_prefix . '_deadline';
		$fields[] = $survey_prefix . '_missed';
	}
	d($fields);
	$data = REDCap::getData('array', $record, $fields, $baseline_event_id);
	d($data);
}