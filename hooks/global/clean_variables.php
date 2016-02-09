<?php
/**
 * Created by NC TraCS.
 * User: kenbergquist
 * Date: 7/20/15
 * Time: 3:44 PM
 */
/**
 * variables
 */
global $Proj;
if (!isset($project_id)) {
	$project_id = $Proj->project_id;
}
if (!isset($record)) {
	$record = null;
}
if (!isset($instrument)) {
	$instrument = null;
}
if (!isset($event_id)) {
	$event_id = null;
}
if (!isset($group_id)) {
	$group_id = null;
}