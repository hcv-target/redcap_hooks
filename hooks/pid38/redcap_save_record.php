<?php
/**
 * Created by HCV-TARGET.
 * User: kenbergquist
 * Date: 7/14/15
 * Time: 4:26 PM
 */
/**
 * project metadata
 */
global $Proj, $project_id, $user_rights;
/**
 * includes
 */
$base_path = dirname(dirname(dirname(__FILE__)));
//require_once $base_path . "/redcap_connect.php";
require_once $base_path . "/hooks/global/clean_variables.php";
require_once $base_path . '/plugins/includes/functions.php';
require_once $base_path . '/plugins/classes/Prioritize.php';
require_once APP_PATH_DOCROOT . '/Config/init_project.php';
/**
 * DEBUG
 */
$debug = false;
$group_id = Prioritize::getGroupID($record);
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
Kint::enabled($debug);
/**
 * perform no action on locked forms
 */
if (!is_form_locked($record, $instrument, $redcap_event_name)) {
	$arms = Prioritize::get_arms(array_keys($Proj->eventsForms));
	/**
	 * per-instrument operations
	 * perform different actions depending upon which form ($instrument) was submitted
	 */
	switch ($instrument) {
		case 'subject_characteristics':
			Prioritize::set_dag($record, $instrument, $debug);
			Prioritize::set_tx_data($record, $debug);
			Prioritize::setTrtDuration($record, $debug);
			Prioritize::schedule_surveys($record, $event_id, $group_id, $debug);
			break;
		case 'demographics':
			set_bmi($record, $debug);
			break;
		case 'treatment_start':
			Prioritize::set_tx_data($record, $debug);
			Prioritize::schedule_surveys($record, $event_id, $group_id, $debug);
			Prioritize::set_notification($record, $redcap_event_name, $instrument, $debug);
			break;
		case 'randomization':
			Prioritize::set_tx_data($record, $debug);
			Prioritize::setTrtDuration($record, $debug);
			Prioritize::schedule_surveys($record, $event_id, $group_id, $debug);
			break;
		case 'rav_results':
			Prioritize::setTrtDuration($record, $debug);
			Prioritize::schedule_surveys($record, $event_id, $group_id, $debug);
			break;
		/**
		 * SITE SOURCE UPLOAD FORM
		 * ACTION: when a site uploads new source, record it for later retrieval by send_siteupload_digest.php
		 */
		case 'source_upload_form':
		case 'site_source_upload_form':
			Prioritize::set_notification($record, $redcap_event_name, $instrument, $debug);
			break;

		case 'adverse_events':
		case 'key_medical_history':
		case 'early_discontinuation_eot':
		case 'transfusions':
		case 'ae_coding':
		case 'mh_coding':
		case 'ex_coding':
			Prioritize::code_terms($record, $redcap_event_name, $instrument, $debug);
			break;

		case 'ribavirin_administration':
		case 'harvoni_administration':
		case 'ombitasvir_paritaprevir':
		case 'dasabuvir':
		case 'zepatier_administration':
			Prioritize::set_tx_data($record, $debug);
			Prioritize::code_terms($record, $redcap_event_name, $instrument, $debug);
			break;

		case 'conmeds':
		case 'cm_coding':
			Prioritize::code_terms($record, $redcap_event_name, $instrument, $debug);
			set_immunosuppressant($record, $redcap_event_name, $debug);
			set_ppi($record, $redcap_event_name, $debug);
			break;

		case 'cbc':
			set_cbc_flags($record, $debug);
			set_cirrhosis($record, $debug);
			standardize_lab_form($instrument, $project_id, $record, $redcap_event_name, $debug);
			break;

		case 'hcv_rna_results':
			set_svr_dates($record, $debug);
			standardize_lab_form($instrument, $project_id, $record, $redcap_event_name, $debug);
			break;

		case 'chemistry':
			standardize_lab_form($instrument, $project_id, $record, $redcap_event_name, $debug);
			set_crcl($record, $event_id, 'abstracted', $debug);
			set_deltas($record, $debug);
			set_egfr($record, $event_id, 'abstracted', $debug);
			break;

		case 'fibrosis_staging':
		case 'cirrhosis':
			set_cirrhosis($record, $debug);
			break;

		case 'prior_treatment_response':
			Prioritize::set_treatment_exp($record, $debug);
			break;

		case 'derived_values_baseline':
			Prioritize::set_tx_data($record, $debug);
			break;
		/**
		 * all other forms do nothing
		 */
		default:
			break;
	}
	/**
	 * Determine completeness of baseline, week4, eot, eot1year and eot3year surveys and record state.
	 * this has to run on every form save to capture the passage of time. you can't
	 */
	if (in_array($redcap_event_name, array_keys($arms)) && $instrument != 'survey_completion') {
		Prioritize::set_survey_completion($record, $debug);
	}
}
if ($debug) {
	$timer['main_end'] = microtime(true);
	error_log(benchmark_timing($timer));
}