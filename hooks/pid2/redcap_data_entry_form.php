<?php
/**
 * Created by HCV-TARGET
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
	$arms = get_arms(array_keys($Proj->eventsForms));
	$enrollment_event_id = getNextEventId($Proj->firstEventId);
	d($arms);
	$events_this_arm = $Proj->getEventsByArmNum($arms[$redcap_event_name]['arm_num']);
	d($events_this_arm);
	$next_event_this_arm = in_array(getNextEventId($event_id), $events_this_arm) ? $Proj->getUniqueEventNames(getNextEventId($event_id)) : null;
	d($next_event_this_arm);
	d(in_array($redcap_event_name, array_keys($arms)));
	$prefix = substr($redcap_event_name, 0, strpos($redcap_event_name, '_'));
	d($prefix);
	$rfstdtc = get_single_field($record, $project_id, getNextEventId($Proj->getFirstEventIdArmId($Proj->firstArmId)), 'dm_rfstdtc', null);
	d($rfstdtc);
	$prefix = substr($redcap_event_name, 0, strpos($redcap_event_name, '_'));
	$fields = array($prefix . '_completed', $prefix . '_date', $prefix . '_senddate', $prefix . '_deadline');
	$data = REDCap::getData('array', $record, $fields, $Proj->getFirstEventIdArmId($Proj->firstArmId));
	d($data);
	$tx_duration = get_single_field($record, $project_id, $enrollment_event_id, 'dm_suppdm_actarmdur', null);
	$tx_first_event = array_search_recursive($tx_duration . ' Weeks', $arms) !== false ? array_search_recursive($tx_duration . ' Weeks', $arms) : $redcap_event_name;
	d($tx_first_event);
	$previous_event_id_this_arm = in_array($events_this_arm[array_search($Proj->getEventIdUsingUniqueEventName($redcap_event_name), $events_this_arm) - 1], $events_this_arm) ? $events_this_arm[array_search($Proj->getEventIdUsingUniqueEventName($redcap_event_name), $events_this_arm) - 1] : null;
	d($previous_event_id_this_arm);
	$tx_arm_event_ids = $Proj->getEventsByArmNum($arms[$tx_first_event]['arm_num']);
	$project_event_ids = $Proj->eventsForms;
	d($project_event_ids);
	d(array_keys($project_event_ids));
	d(get_arms($tx_arm_event_ids));
	$all_events_this_subject = array_merge($events_this_arm, $tx_arm_event_ids);
	d($all_events_this_subject);
	$survey_event_ids[] = $baseline_event_id;
	$survey_event_ids = array_merge($survey_event_ids, $tx_arm_event_ids);
	foreach ($survey_event_ids AS $survey_event_id) {
		$survey_event_name = $Proj->getUniqueEventNames($survey_event_id);
		$survey_prefix = substr($survey_event_name, 0, strpos($survey_event_name, '_'));
		$fields[] = $survey_prefix . '_completed';
		$fields[] = $survey_prefix . '_date';
	}
	$data = REDCap::getData('array', $record, $fields, $baseline_event_id);
	d($data);
}