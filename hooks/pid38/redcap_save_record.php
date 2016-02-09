<?php
/**
 * Created by HCV-TARGET
 * User: kenbergquist
 * Date: 7/14/15
 * Time: 4:26 PM
 */
/**
 * project metadata
 */
global $Proj;
/**
 * includes
 */
$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . '/plugins/includes/functions.php';
require_once $base_path . '/plugins/includes/prioritize_functions.php';
require_once APP_PATH_DOCROOT . '/Config/init_project.php';
/**
 * DEBUG
 */
$debug = false;
Kint::enabled($debug);
if ($debug) {
	$timer = array();
	$timer['start'] = microtime(true);
	error_log("DEBUG: " . $project_id . ' ' . $record . ' ' . $instrument . ' ' . $event_id . ' ' . $group_id);
}
/**
 * initialize variables
 */
$redcap_event_name = $Proj->getUniqueEventNames($event_id);
/**
 * restricted use
 */
$allowed_pids = array('38');
REDCap::allowProjects($allowed_pids);
$baseline_event_id = $Proj->firstEventId;
/**
 * This code will be executed at any save of any form ($instrument)
 * First we need to know if this is really a save, or if it's a lock...
 */
if (!is_form_locked($record, $instrument, $redcap_event_name)) {
	$arms = get_arms(array_keys($Proj->eventsForms));
	/**
	 * Determine completeness of this Assessment, T1, T2 - T5 and record state.
	 */
	if (in_array($redcap_event_name, array_keys($arms))) {
		events_completion($record, $debug);
	}
	/**
	 * per-instrument operations
	 * perform different actions depending upon which form ($instrument) was submitted
	 */
	switch ($instrument) {
		case 'informed_consent':
			/**
			 * SET Data Access Group based upon dm_usubjid prefix
			 */
			$debug = false;
			$fields = array('dm_usubjid');
			$data = REDCap::getData('array', $record, $fields);
			foreach ($data AS $subject) {
				foreach ($subject AS $event_id => $event) {
					if ($event['dm_usubjid'] != '') {
						/**
						 * find which DAG this subject belongs to
						 */
						$site_prefix = substr($event['dm_usubjid'], 0, 3) . '%';
						$dag_query = "SELECT group_id, group_name FROM redcap_data_access_groups WHERE project_id = '$project_id' AND group_name LIKE '$site_prefix'";
						$dag_result = db_query($dag_query);
						if ($dag_result) {
							$dag = db_fetch_assoc($dag_result);
							if (isset($dag['group_id'])) {
								/**
								 * For each event in project for this subject, determine if this subject_id has been added to its appropriate DAG. If it hasn't, make it so.
								 * First, we need a list of events for which this subject has data
								 */
								$subject_events_query = "SELECT DISTINCT event_id FROM redcap_data WHERE project_id = '$project_id' AND record = '$record' AND field_name = '" . $instrument . "_complete'";
								$subject_events_result = db_query($subject_events_query);
								if ($subject_events_result) {
									while ($subject_events_row = db_fetch_assoc($subject_events_result)) {
										if (isset($subject_events_row['event_id'])) {
											$_GET['event_id'] = $subject_events_row['event_id']; // for logging
											/**
											 * The subject has data in this event_id
											 * does the subject have corresponding DAG assignment?
											 */
											$has_event_data_query = "SELECT DISTINCT event_id FROM redcap_data WHERE project_id = '$project_id' AND record = '$record' AND event_id = '" . $subject_events_row['event_id'] . "' AND field_name = '__GROUPID__'";
											$has_event_data_result = db_query($has_event_data_query);
											if ($has_event_data_result) {
												$has_event_data = db_fetch_assoc($has_event_data_result);
												if (!isset($has_event_data['event_id'])) {
													/**
													 * Subject does not have a matching DAG assignment for this data
													 * construct proper matching __GROUPID__ record and insert
													 */
													$insert_dag_query = "INSERT INTO redcap_data SET record = '$record', event_id = '" . $subject_events_row['event_id'] . "', value = '" . $dag['group_id'] . "', project_id = '$project_id', field_name = '__GROUPID__'";
													if (!$debug) {
														if (db_query($insert_dag_query)) {
															target_log_event($insert_dag_query, 'redcap_data', 'insert', $record, $dag['group_name'], 'Assign record to Data Access Group (' . $dag['group_name'] . ')');
															d($insert_dag_query);
														} else {
															error_log("SQL INSERT FAILED: " . db_error() . "\n");
															echo db_error() . "\n";
														}
													} else {
														d($insert_dag_query);
														error_log('(TESTING) NOTICE: ' . $insert_dag_query);
													}
												}
												db_free_result($has_event_data_result);
											}
										}
									}
									db_free_result($subject_events_result);
								}
							}
							db_free_result($dag_result);
						}
					}
				}
			}
			break;

		case 'clinical_and_lab_data':
		case 'clinical_lab_followup':
			$debug = false;
			standardize_lab_form($instrument, $project_id, $record, $redcap_event_name, $debug);
			$timer_stop = microtime(true);
			$timer_time = number_format(($timer_stop - $timer_start), 2);
			if ($debug) {
				error_log("DEBUG: This DET action (Standardize $instrument) took $timer_time seconds");
			}
			break;
		case 'enrollment':
			/**
			 * derive inter-event timing variables
			 */
//			$enrollment_event_id = getNextEventId($baseline_event_id);
//			$rfstdtc = get_single_field($record, $project_id, $enrollment_event_id, 'dm_rfstdtc', null);
//			/**
//			 * timing for invitations is taken from treatment start, and we don't need to fire this when we're editing other forms in this event...
//			 */
//			if (isset($rfstdtc) && $rfstdtc != '') {
//				/**
//				 * Subjects exist in two arms: arm_1 (Baseline) and a treatment arm (arm_2, arm_3 or arm_4 (8, 12 or 24 Weeks)). The current event is in arm_1
//				 * When setting send and deadline dates in T2 from enrollment, we must first know in which treatment arm the subject is meant to be scheduled
//				 * This is found by matching the treatment duration on enrollment form with the arm name
//				 */
//				$tx_duration = get_single_field($record, $project_id, $enrollment_event_id, 'dm_suppdm_actarmdur', null);
//				$tx_first_event = array_search_recursive($tx_duration . ' Weeks', $arms) !== false ? array_search_recursive($tx_duration . ' Weeks', $arms) : $redcap_event_name;
//				$tx_arm_event_ids = $Proj->getEventsByArmNum($arms[$tx_first_event]['arm_num']);
//				/**
//				 * iterate the subject's treatment arm in $arms: foreach this arm in $arms, event->send_date = rfstdtc + (day_offset - offset_min), deadline = rfstdtc + (day_offset + offset_max)
//				 */
//				foreach (get_arms($tx_arm_event_ids) AS $tx_arm_event_name => $tx_arm_event) {
//					$prefix = substr($tx_arm_event_name, 0, strpos($tx_arm_event_name, '_'));
//					$send_date_value = get_single_field($record, $project_id, $baseline_event_id, $prefix . '_startdate', null);
//					$deadline_value = get_single_field($record, $project_id, $baseline_event_id, $prefix . '_deadline', null);
//					update_field_compare($record, $project_id, $baseline_event_id, add_date($rfstdtc, ($tx_arm_event['day_offset'] - $tx_arm_event['offset_min'])), $send_date_value, $prefix . '_startdate', $debug);
//					update_field_compare($record, $project_id, $baseline_event_id, add_date($rfstdtc, ($tx_arm_event['day_offset'] + $tx_arm_event['offset_max'])), $deadline_value, $prefix . '_deadline', $debug);
//				}
//			}
			break;
		/**
		 * all other forms do nothing
		 */
		default:
			break;
	}
}