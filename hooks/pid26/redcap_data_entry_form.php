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
/**
 * do stuff
 */