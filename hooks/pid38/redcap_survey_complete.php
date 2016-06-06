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
	error_log("DEBUG: survey complete: " . $project_id . ' ' . $record . ' ' . $instrument . ' ' . $event_id . ' ' . $group_id . ' ' . $response_id);
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
d($_GET);
d($_POST);
if ($debug) {
	error_log("DEBUG: " . $project_id . ' ' . $record . ' ' . $instrument . ' ' . $event_id . ' ' . $group_id . ' ' . $response_id);
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
							window.onbeforeunload = function () {};
							window.location.href = "<?php echo Survey::getAutoContinueSurveyUrl($_GET['id'], $_GET['page'], $_GET['event_id']) ?>";
						}
					}]
				});
			}, (isMobileDevice ? 1500 : 0));
		});
	</script>
<?php