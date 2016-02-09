<?php
/**
 * Created by NC TraCS.
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
//require_once $base_path . "/redcap_connect.php";
require_once $base_path . '/plugins/includes/functions.php';
require_once APP_PATH_DOCROOT . '/Config/init_project.php';
/**
 * DEBUG
 */
$debug = true;
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
$allowed_pids = array('29');
REDCap::allowProjects($allowed_pids);
/**
 * per-instrument operations
 * perform different actions depending upon which form ($instrument) was submitted
 */
switch ($instrument) {
	/**
	 * SITE SOURCE UPLOAD FORM
	 * ACTION: when a site uploads new source, record it for later retrieval by send_siteupload_digest.php
	 */
	case 'contract_cda':
	case 'cvs':
	case 'delegation_of_authority_log':
	case 'source_upload_form':
	case 'drug_request_form':
	case 'fda_1572':
	case 'financial_disclosure_form':
	case 'greenlight_document':
	case 'ich_gcp_certificate':
	case 'ip_dispensing_log':
	case 'ip_shipping_documents':
	case 'irb_correspondence':
	case 'irb_roster':
	case 'licenses':
	case 'local_lab_certifications':
	case 'local_lab_cv':
	case 'local_lab_ref_ranges':
	case 'monitoring_visit_documentation':
	case 'protocol_amendment_approval':
	case 'protocol_amendment_submission':
	case 'protocol_signature_page':
	case 'site':
	case 'site_status':
	case 'subject_enrollment_screening_log':
	case 'temperature_logs':
	case 'training_logs':
	case 'user_activations':
		$debug = false;
		$today = date('Y-m-d');
		$sql = "INSERT INTO target_email_actions SET project_id = '$project_id', record = '$record', redcap_event_name = '$redcap_event_name', form_name = '$instrument', action_date = '$today'";
		if (db_query($sql)) {
			error_log("NOTICE: Form $instrument updated for record $record in event $redcap_event_name");
		} else {
			error_log(db_error());
		}
		break;
	/**
	 * all other forms do nothing
	 */
	default:
		break;
}