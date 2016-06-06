<?php
/**
 * Created by HCV-TARGET.
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
$debug = true;
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
if ($debug) {
	$survey_id = $Proj->forms[$_GET['page']]['survey_id'];
	d($survey_id);
	d($_GET);
	d($_POST);
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
	$tx_duration = substr($tx_duration, strpos($tx_duration, 'P') + 1, strlen($tx_duration) - 2);
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
$_POST['s'] = $_GET['s'];
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
 * alter required fields dialog for surveys
 */
?>
	<script type="text/javascript">
		$(document).ready(function () {
			eachcount = 0;
			setTimeout(function () {
				$("#reqPopup").contents().each(function () {
					if (this.nodeType == 3) {
						var text = this.textContent ? this.textContent : this.innerText;
						text = jQuery.trim(text);
						if (text.length && eachcount == 0) {
							this.textContent = 'Your answers have been saved, but we noticed you left one or more questions unanswered.';
							eachcount++;
						} else {
							if (text.length) {
								this.textContent = 'Please consider providing an answer for:';
							}
						}
					}
				});
				$("#reqPopup").dialog({
					modal: true,
					title: 'Pardon the interruption...',
					buttons: [{
						text: 'Okay',
						click: function () {
							$(this).dialog("close");
						}
					}, {
						text: 'Ignore and continue',
						"class": 'dataEntryLeavePageBtn',
						click: function () {
							// Disable the onbeforeunload so that we don't get an alert before we leave
							window.onbeforeunload = function () {
							};
							// Redirect to next page
							window.location.href = app_path_webroot_full + page + "<?php echo '?s=' . $_GET['s'] ?>";
						}
					}, {
						text: 'Ignore and go to next survey in your queue',
						"class": 'dataEntrySaveLeavePageBtn',
						click: function () {
							//window.onbeforeunload = function () {};
							//window.location.href = "<?php //echo Survey::getAutoContinueSurveyUrl($_GET['id'], $_GET['page'], $_GET['event_id']) ?>";
							$.post(app_path_webroot_full + "plugins/prioritize/complete_survey.php",
								{
									pid: "<?php echo $project_id ?>",
									s: "<?php echo $_GET['s'] ?>",
									page: "<?php echo $_GET['page'] ?>",
									event_id: "<?php echo $_GET['event_id'] ?>",
									debug: true
								}
							);
						}
					}]
				});
			}, (isMobileDevice ? 1500 : 0));
		});
	</script>
<?php