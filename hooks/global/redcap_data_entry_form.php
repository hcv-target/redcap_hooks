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
$debug = false;
if ($debug) {
	$timer = array();
	$timer['start'] = microtime(true);
	error_log("DEBUG: " . $project_id . ' ' . $record . ' ' . $instrument . ' ' . $event_id . ' ' . $group_id);
}
/**
 * includes
 */
require_once "clean_variables.php";
$base_path = dirname(dirname(dirname(__FILE__)));
require_once $base_path . '/plugins/includes/functions.php';
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
 * Save and go to Next Event for this Form button
 */
$next_event_id = getNextEventId($event_id, $instrument); // get the next event_id
if ($Proj->validateFormEvent($instrument, $next_event_id)) { // if it's a valid event_id
	/**
	 * inject new input button
	 */
	$redirect_url = APP_PATH_WEBROOT . "DataEntry/index.php?pid=$project_id&id=$record&event_id=$next_event_id&page=$instrument";
	?>
	<script src="<?php echo '/hooks/resources/js/functions.js' ?>"></script>
	<script type="text/javascript">
		var redirectURL = "<?php echo $redirect_url; ?>";
		$(document).ready(function () {
			/* inject Save and go to Next Event for this Form button */
			var newButton = "<input type = \"button\" name = \"submit-btn-savenextevent\" onclick = \"gotoNextEvent(this, redirectURL);return false;\" value = \"Save and go to Next Event for this Form\" style = \"font-size:11px;\" tabindex = \"13\" >";
			$('div#__SUBMITBUTTONS__-div input').last().after("<br />", newButton);
			$('div#formSaveTip input').last().after("<br />", newButton);
		});
	</script>
	<?php
}