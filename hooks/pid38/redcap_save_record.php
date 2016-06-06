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
require_once $base_path . '/plugins/includes/prioritize_functions.php';
require_once APP_PATH_DOCROOT . '/Config/init_project.php';
/**
 * DEBUG
 */
$debug = true;
$group_id = Treatment::getGroupID($record);
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
	$arms = get_arms(array_keys($Proj->eventsForms));
	/**
	 * per-instrument operations
	 * perform different actions depending upon which form ($instrument) was submitted
	 */
	switch ($instrument) {
		case 'subject_characteristics':
			set_dag($record, $instrument, $debug);
			Treatment::setTrtDuration($record, $debug);
			schedule_surveys($record, $event_id, $group_id, $debug);
			break;
		case 'demographics':
			set_bmi($record, $debug);
			break;
		case 'treatment_start':
			schedule_surveys($record, $event_id, $group_id, $debug);
			break;
		case 'randomization':
			Treatment::setTrtDuration($record, $debug);
			schedule_surveys($record, $event_id, $group_id, $debug);
			break;
		case 'il28b_hcv_genotypes':
			Treatment::setTrtDuration($record, $debug);
			schedule_surveys($record, $event_id, $group_id, $debug);
			break;
		/**
		 * SITE SOURCE UPLOAD FORM
		 * ACTION: when a site uploads new source, record it for later retrieval by send_siteupload_digest.php
		 */
		case 'site_source_upload_form':
			$group_name_result = db_query("SELECT group_name FROM redcap_data_access_groups WHERE project_id = '$project_id' AND group_id = '$group_id'");
			if ($group_name_result) {
				$group_name_row = db_fetch_assoc($group_name_result);
				if ($debug) {
					error_log("DEBUG: DAG name = {$group_name_row['group_name']}");
				}
				$today = date('Y-m-d');
				if (db_query("INSERT INTO target_email_actions SET project_id = '$project_id', record = '$record', redcap_event_name = '$redcap_event_name', redcap_data_access_group = '" . prep($group_name_row['group_name']) . "', form_name = '$instrument', action_date = '$today'")) {
					/*error_log("NOTICE: Site $group_id uploaded source to record $record in event $redcap_event_name");*/
				} else {
					error_log(db_error());
				}
			}
			break;
		/**
		 * SOURCE UPLOAD FORM
		 * ACTION: when a foreign abstraction site uploads new source, record it for later retrieval by send_siteupload_digest.php
		 */
		case 'source_upload_form':
			$today = date('Y-m-d');
			$group_name_result = db_query("SELECT group_name FROM redcap_data_access_groups WHERE project_id = '$project_id' AND group_id = '$group_id'");
			if ($group_name_result) {
				$group_name_row = db_fetch_assoc($group_name_result);
				if ($debug) {
					error_log("DEBUG: DAG name = {$group_name_row['group_name']}");
				}
				if (substr($group_name_row['group_name'], 0, 3) >= '300') {
					$today = date('Y-m-d');
					if (db_query("INSERT INTO target_email_actions SET project_id = '$project_id', record = '$record', redcap_event_name = '$redcap_event_name', redcap_data_access_group = '" . prep($group_name_row['group_name']) . "', form_name = '$instrument', action_date = '$today'")) {
						error_log("NOTICE: Site {{$group_name_row['group_name']} uploaded source to record $record in event $redcap_event_name");
					} else {
						error_log(db_error());
					}
				}
			}
			break;
		/**
		 * ADVERSE EVENTS
		 * ACTION: auto-code AE
		 */
		case 'adverse_events':
			/**
			 * AE_AEDECOD
			 */
			$fields = array("ae_aeterm", "ae_oth_aeterm", "ae_aemodify");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					code_llt($project_id, $subject_id, $event_id, fix_case($event['ae_aeterm']), fix_case($event['ae_oth_aeterm']), $event['ae_aemodify'], 'ae_aemodify', $debug, $recode_llt);
					if ($debug) {
						error_log("DEBUG: Coded AE_AEMODIFY {$event['ae_aemodify']}: subject=$subject_id, event=$event_id for AE {$event['ae_aeterm']} - {$event['ae_oth_aeterm']}");
					}
				}
			}
			/**
			 * AE_AEDECOD
			 */
			$fields = array("ae_aemodify", "ae_aedecod");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					code_pt($project_id, $subject_id, $event_id, fix_case($event['ae_aemodify']), $event['ae_aedecod'], 'ae_aedecod', $debug, $recode_pt);
					if ($debug) {
						error_log("DEBUG: Coded AE_AEDECOD {$event['ae_aedecod']}: subject=$subject_id, event=$event_id for AE {$event['ae_aemodify']}");
					}
				}
			}
			/**
			 * AE_AEBODSYS
			 */
			$fields = array("ae_aedecod", "ae_aebodsys");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					code_bodsys($project_id, $subject_id, $event_id, $event['ae_aedecod'], $event['ae_aebodsys'], 'ae_aebodsys', $debug, $recode_soc);
					if ($debug) {
						error_log("DEBUG: Coded SOC: subject=$subject_id, event=$event_id for AE {$event['ae_aedecod']}");
					}
				}
			}
			break;
		/**
		 * MEDICAL HISTORY
		 * ACTION: auto-code MH
		 */
		case 'key_medical_history':
			$recode_llt = false;
			$recode_pt = true;
			$recode_soc = true;
			$mh_prefixes = array('othpsy', 'othca');
			/**
			 * MH_MHMODIFY
			 */
			foreach ($mh_prefixes AS $prefix) {
				$fields = array($prefix . "_oth_mhterm", $prefix . "_mhmodify");
				$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
				foreach ($data AS $subject_id => $subject) {
					foreach ($subject AS $event_id => $event) {
						code_llt($project_id, $subject_id, $event_id, fix_case($event[$prefix . "_oth_mhterm"]), '', $event[$prefix . "_mhmodify"], $prefix . "_mhmodify", $debug, $recode_llt);
						if ($debug) {
							error_log("DEBUG: Coded " . strtoupper($prefix) . "_MHMODIFY {$event[$prefix . "_mhmodify"]}: subject=$subject_id, event=$event_id for MH {$event[$prefix . "_oth_mhterm"]}");
						}
					}
				}
			}
			/**
			 * PREFIX_MHDECOD
			 * uses $mh_prefixes preset array
			 */
			foreach ($mh_prefixes AS $prefix) {
				$fields = array($prefix . "_mhmodify", $prefix . "_mhdecod");
				$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
				foreach ($data AS $subject_id => $subject) {
					foreach ($subject AS $event_id => $event) {
						code_pt($project_id, $subject_id, $event_id, $event[$prefix . "_mhmodify"], $event[$prefix . "_mhdecod"], $prefix . "_mhdecod", $debug, $recode_pt);
						if ($debug) {
							error_log("DEBUG: Coded " . strtoupper($prefix) . "_MHDECOD {$event[$prefix . '_mhdecod']}: subject=$subject_id, event=$event_id for MHMODIFY {$event[$prefix . '_mhmodify']}");
						}
					}
				}
			}
			/**
			 * PREFIX_mhBODSYS
			 * uses $mh_prefixes preset array
			 */
			foreach ($mh_prefixes AS $prefix) {
				$fields = array($prefix . "_mhdecod", $prefix . "_mhbodsys");
				$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
				foreach ($data AS $subject_id => $subject) {
					foreach ($subject AS $event_id => $event) {
						code_bodsys($project_id, $subject_id, $event_id, $event[$prefix . "_mhdecod"], $event[$prefix . "_mhbodsys"], $prefix . "_mhbodsys", $debug, $recode_soc);
						if ($debug) {
							error_log("DEBUG: Coded " . strtoupper($prefix) . "_MHBODSYS {$event[$prefix . "_mhbodsys"]}: subject=$subject_id, event=$event_id for MHDECOD {$event[$prefix . "_mhdecod"]}");
						}
					}
				}
			}
			break;
		/**
		 * EOT
		 */
		case 'early_discontinuation_eot':
			$recode_llt = true;
			$recode_pt = true;
			$recode_soc = true;
			/**
			 * EOT_AEDECOD
			 */
			$fields = array("eot_suppds_ncmpae", "eot_oth_suppds_ncmpae", "eot_aemodify", "eot_dsterm");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					if ($event['eot_dsterm'] == 'ADVERSE_EVENT') {
						code_llt($project_id, $subject_id, $event_id, fix_case($event['eot_suppds_ncmpae']), fix_case($event['eot_oth_suppds_ncmpae']), $event['eot_aemodify'], 'eot_aemodify', $debug, $recode_llt);
						if ($debug) {
							error_log("INFO (TESTING EOT): Coded EOT_AEMODIFY {$event['eot_aemodify']}: subject=$subject_id, event=$event_id for AE {$event['eot_suppds_ncmpae']} - {$event['eot_oth_suppds_ncmpae']}");
						}
						/**
						 * AE_AEDECOD
						 */
						$ptfields = array("eot_aemodify", "eot_aedecod");
						$ptdata = REDCap::getData('array', $record, $ptfields, $redcap_event_name);
						foreach ($ptdata AS $ptsubject_id => $ptsubject) {
							foreach ($ptsubject AS $ptevent_id => $ptevent) {
								code_pt($project_id, $subject_id, $ptevent_id, fix_case($ptevent['eot_aemodify']), $ptevent['eot_aedecod'], 'eot_aedecod', $debug, $recode_pt);
								if ($debug) {
									error_log("DEBUG: Coded EOT_AEDECOD {$ptevent['eot_aedecod']}: subject=$ptsubject_id, event=$ptevent_id for AEMODIFY {$ptevent['eot_aemodify']}");
								}
							}
						}
						/**
						 * EOT_AEBODSYS
						 */
						$soc_fields = array("eot_aedecod", "eot_aebodsys");
						$soc_data = REDCap::getData('array', $record, $soc_fields, $redcap_event_name);
						foreach ($soc_data AS $soc_subject_id => $soc_subject) {
							foreach ($soc_subject AS $soc_event_id => $soc_event) {
								code_bodsys($project_id, $soc_subject_id, $soc_event_id, $soc_event['eot_aedecod'], $soc_event['eot_aebodsys'], 'eot_aebodsys', $debug, $recode_soc);
								if ($debug) {
									error_log("DEBUG: Coded SOC: subject=$soc_subject_id, event=$soc_event_id for AE {$soc_event['eot_aedecod']}");
								}
							}
						}
					}
				}
			}
			break;
		/**
		 * TX stop AEs
		 */
		case 'ribavirin_administration':
		case 'harvoni_administration':
		case 'ombitasvir_paritaprevir':
		case 'dasabuvir':
		case 'zepatier_administration':
			$recode_llt = true;
			$recode_pt = true;
			$recode_soc = true;
			/**
			 * set start and stop variables
			 */
			set_tx_data($record, $debug);
			$tx_prefix = array_search(substr($instrument, 0, strpos($instrument, '_')), $tx_fragment_labels);
			/**
			 * AE_AEMODIFY
			 */
			$fields = array($tx_prefix . '_suppcm_cmncmpae', $tx_prefix . '_oth_suppcm_cmncmpae', $tx_prefix . '_aemodify');
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					code_llt($project_id, $subject_id, $event_id, fix_case($event[$tx_prefix . '_suppcm_cmncmpae']), fix_case($event[$tx_prefix . '_oth_suppcm_cmncmpae']), $event[$tx_prefix . '_aemodify'], $tx_prefix . '_aemodify', $debug, $recode_llt);
					if ($debug) {
						error_log("DEBUG: Coded AE_AEMODIFY {$event[$tx_prefix . '_aemodify']}: subject=$subject_id, event=$event_id for AE {$event[$tx_prefix . '_suppcm_cmncmpae']} - {$event[$tx_prefix . '_oth_suppcm_cmncmpae']}");
					}
				}
			}
			/**
			 * AE_AEDECOD
			 */
			$fields = array($tx_prefix . '_aemodify', $tx_prefix . "_aedecod");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					code_pt($project_id, $subject_id, $event_id, fix_case($event[$tx_prefix . '_aemodify']), $event[$tx_prefix . '_aedecod'], $tx_prefix . '_aedecod', $debug, $recode_pt);
					if ($debug) {
						error_log("DEBUG: Coded AE_AEDECOD {$event[$tx_prefix . '_aedecod']}: subject=$subject_id, event=$event_id for AE {$event[$tx_prefix . '_aemodify']}");
					}
				}
			}
			/**
			 * AE_AEBODSYS
			 */
			$fields = array($tx_prefix . '_aedecod', $tx_prefix . '_aebodsys');
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					code_bodsys($project_id, $subject_id, $event_id, $event[$tx_prefix . '_aedecod'], $event[$tx_prefix . '_aebodsys'], $tx_prefix . '_aebodsys', $debug, $recode_soc);
					if ($debug) {
						error_log("DEBUG: Coded SOC: subject=$subject_id, event=$event_id for AE {$event[$tx_prefix . '_aedecod']}");
					}
				}
			}
			break;
		/**
		 * CONMEDS
		 * ACTION: auto-code CONMEDS
		 */
		case 'conmeds':
			/**
			 * CM_CMDECOD
			 */
			$fields = array("cm_cmtrt", "cm_cmdecod", "cm_cmindc", "cm_oth_cmindc", "cm_suppcm_indcod");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					if (isset($event['cm_cmtrt']) && $event['cm_cmtrt'] != '') {
						$med = array();
						$med_result = db_query("SELECT DISTINCT drug_name FROM _whodrug_mp_us WHERE drug_name = '" . prep($event['cm_cmtrt']) . "'");
						if ($med_result) {
							$med = db_fetch_assoc($med_result);
							if (isset($med['drug_name']) && $med['drug_name'] != '') {
								update_field_compare($subject_id, $project_id, $event_id, $med['drug_name'], $event['cm_cmdecod'], 'cm_cmdecod', $debug);
							}
						}
						if ($debug) {
							error_log("DEBUG: Coded CONMED: subject=$subject_id, event=$event_id for CMTRT {$event['cm_cmtrt']}");
						}
					} else {
						update_field_compare($subject_id, $project_id, $event_id, '', $event['cm_cmdecod'], 'cm_cmdecod', $debug);
					}
				}
			}
			/**
			 * cm_suppcm_mktstat
			 * PRESCRIPTION or OTC
			 */
			$fields = array("cm_cmdecod", "cm_suppcm_mktstat");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data as $subject_id => $subject) {
				foreach ($subject as $event_id => $event) {
					if (isset($event['cm_cmdecod']) && $event['cm_cmdecod'] != '') {
						if ($debug) {
							error_log("DEBUG: $subject_id Marketing Status = " . get_conmed_mktg_status($event['cm_cmdecod']));
						}
						update_field_compare($subject_id, $project_id, $event_id, get_conmed_mktg_status($event['cm_cmdecod']), $event['cm_suppcm_mktstat'], 'cm_suppcm_mktstat', $debug);
					}
				}
			}
			/**
			 * CM_SUPPCM_INDCOD
			 */
			$fields = array("cm_cmindc", "cm_oth_cmindc", "cm_suppcm_indcmodf");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					/**
					 * re-code all nutritional support to nutritional supplement
					 */
					if ($event['cm_oth_cmindc'] == 'Nutritional support') {
						$event['cm_oth_cmindc'] = 'Nutritional supplement';
					}
					code_llt($project_id, $subject_id, $event_id, fix_case($event['cm_cmindc']), fix_case($event['cm_oth_cmindc']), $event['cm_suppcm_indcmodf'], 'cm_suppcm_indcmodf', $debug, $recode_llt);
					if ($debug) {
						error_log("DEBUG: Coded INDC LLT: {} subject=$subject_id, event=$event_id for INDICATION {$event['cm_cmindc']}");
					}
				}
			}
			/**
			 * CM_SUPPCM_INDCOD
			 */
			$fields = array("cm_suppcm_indcmodf", "cm_suppcm_indcod");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					code_pt($project_id, $subject_id, $event_id, $event['cm_suppcm_indcmodf'], $event['cm_suppcm_indcod'], 'cm_suppcm_indcod', $debug, $recode_pt);
					if ($debug) {
						error_log("DEBUG: Coded INDC PT: subject=$subject_id, event=$event_id for INDICATION {$event['cm_suppcm_indcod']}");
					}
				}
			}
			/**
			 * CM_SUPPCM_INDCSYS
			 */
			$fields = array("cm_suppcm_indcod", "cm_suppcm_indcsys");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					code_bodsys($project_id, $subject_id, $event_id, $event['cm_suppcm_indcod'], $event['cm_suppcm_indcsys'], 'cm_suppcm_indcsys', $debug, $recode_soc);
					if ($debug) {
						error_log("DEBUG: Coded INDCSYS: subject=$subject_id, event=$event_id for INDC {$event['cm_suppcm_indcod']}");
					}
				}
			}
			/**
			 * CM_SUPPCM_ATCNAME
			 * CM_SUPPCM_ATC2NAME
			 */
			$fields = array("cm_cmdecod", "cm_suppcm_atcname", "cm_suppcm_atc2name");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					code_atc($project_id, $subject_id, $event_id, $event['cm_cmdecod'], $event['cm_suppcm_atcname'], $event['cm_suppcm_atc2name'], $debug, $recode_atc);
					if ($debug) {
						error_log("DEBUG: Coded ATCs: subject=$subject_id, event=$event_id for CONMED {$event['cm_cmdecod']}");
					}
				}
			}
			set_immunosuppressant($record, $redcap_event_name, $debug);
			set_ppi($record, $redcap_event_name, $debug);
			break;
		case 'transfusions':
			/**
			 * XFSN_CMDECOD
			 */
			$fields = array("xfsn_cmtrt", "xfsn_cmdecod", "xfsn_cmindc", "xfsn_suppcm_indcod");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					if (isset($event['xfsn_cmtrt']) && $event['xfsn_cmtrt'] != '') {
						$med = array();
						$med_result = db_query("SELECT DISTINCT drug_coded FROM _target_xfsn_coding WHERE drug_name = '" . prep($event['xfsn_cmtrt']) . "'");
						if ($med_result) {
							$med = db_fetch_assoc($med_result);
							if (isset($med['drug_coded']) && $med['drug_coded'] != '') {
								update_field_compare($subject_id, $project_id, $event_id, $med['drug_coded'], $event['xfsn_cmdecod'], 'xfsn_cmdecod', $debug);
							}
						}
						if ($debug) {
							error_log("DEBUG: Coded Transfusion: subject=$subject_id, event=$event_id for CMTRT {$event['xfsn_cmtrt']}");
						}
					} else {
						update_field_compare($subject_id, $project_id, $event_id, '', $event['xfsn_cmdecod'], 'xfsn_cmdecod', $debug);
					}
				}
			}
			/**
			 * XFSN_SUPPCM_INDCOD
			 */
			$fields = array("xfsn_cmindc", "xfsn_suppcm_indcod");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					code_llt($project_id, $subject_id, $event_id, fix_case($event['xfsn_cmindc']), fix_case($event['xfsn_oth_cmindc']), $event['xfsn_suppcm_indcod'], 'xfsn_suppcm_indcod', $debug, $recode_llt);
					if ($debug) {
						error_log("DEBUG: Coded XFSN INDC: subject=$subject_id, event=$event_id for CONMED {$event['xfsn_cmdecod']}");
					}
				}
			}
			/**
			 * XFSN_SUPPCM_INDCSYS
			 */
			$fields = array("xfsn_suppcm_indcod", "xfsn_suppcm_indcsys");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					code_bodsys($project_id, $subject_id, $event_id, $event['xfsn_suppcm_indcod'], $event['xfsn_suppcm_indcsys'], 'xfsn_suppcm_indcsys', $debug, $recode_soc);
					if ($debug) {
						error_log("DEBUG: Coded XFSN INDCSYS: subject=$subject_id, event=$event_id for INDC {$event['xfsn_suppcm_indcod']}");
					}
				}
			}
			/**
			 * XFSN_SUPPCM_ATCNAME
			 * XFSN_SUPPCM_ATC2NAME
			 */
			$fields = array("xfsn_cmdecod", "xfsn_suppcm_atcname", "xfsn_suppcm_atc2name");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					code_atc_xfsn($project_id, $subject_id, $event_id, $event['xfsn_cmdecod'], $event['xfsn_suppcm_atcname'], $event['xfsn_suppcm_atc2name'], $debug, $recode_atc);
					if ($debug) {
						error_log("DEBUG: Coded XFSN ATCs: subject=$subject_id, event=$event_id for CONMED {$event['xfsn_cmdecod']}");
					}
				}
			}
			break;

		case 'ae_coding':
			$recode_llt = false;
			$recode_pt = true;
			$recode_soc = true;
			$ae_prefixes = array('ae');
			/**
			 * AE_AEMODIFY
			 */
			$fields = array("ae_aeterm", "ae_oth_aeterm", "ae_aemodify");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					code_llt($project_id, $subject_id, $event_id, fix_case($event['ae_aeterm']), fix_case($event['ae_oth_aeterm']), $event['ae_aemodify'], 'ae_aemodify', $debug, $recode_llt);
					if ($debug) {
						error_log("DEBUG: Coded AE_AEMODIFY {$event['ae_aemodify']}: subject=$subject_id, event=$event_id for AE {$event['ae_aeterm']} - {$event['ae_oth_aeterm']}");
					}
				}
			}
			/**
			 * PREFIX_AEDECOD
			 * uses $tx_prefixes preset array
			 */
			foreach ($ae_prefixes AS $prefix) {
				$fields = array($prefix . "_aemodify", $prefix . "_aedecod");
				$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
				foreach ($data AS $subject_id => $subject) {
					foreach ($subject AS $event_id => $event) {
						code_pt($project_id, $subject_id, $event_id, $event[$prefix . "_aemodify"], $event[$prefix . "_aedecod"], $prefix . "_aedecod", $debug, $recode_pt);
						if ($debug) {
							error_log("DEBUG: Coded " . strtoupper($prefix) . "_AEDECOD {$event[$prefix . '_aedecod']}: subject=$subject_id, event=$event_id for AEMODIFY {$event[$prefix . '_aemodify']}");
						}
					}
				}
			}
			/**
			 * PREFIX_AEBODSYS
			 * uses $tx_prefixes preset array
			 */
			foreach ($ae_prefixes AS $prefix) {
				$fields = array($prefix . "_aedecod", $prefix . "_aebodsys");
				$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
				foreach ($data AS $subject_id => $subject) {
					foreach ($subject AS $event_id => $event) {
						code_bodsys($project_id, $subject_id, $event_id, $event[$prefix . "_aedecod"], $event[$prefix . "_aebodsys"], $prefix . "_aebodsys", $debug, $recode_soc);
						if ($debug) {
							error_log("DEBUG: Coded SOC: subject=$subject_id, event=$event_id for AE {$event[$prefix . "_aedecod"]}");
						}
					}
				}
			}
			break;

		case 'mh_coding':
			$recode_llt = false;
			$recode_pt = true;
			$recode_soc = true;
			$mh_prefixes = array('othpsy', 'othca');
			/**
			 * MH_MHMODIFY
			 */
			foreach ($mh_prefixes AS $prefix) {
				$fields = array($prefix . "_oth_mhterm", $prefix . "_mhmodify");
				$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
				foreach ($data AS $subject_id => $subject) {
					foreach ($subject AS $event_id => $event) {
						code_llt($project_id, $subject_id, $event_id, fix_case($event[$prefix . "_oth_mhterm"]), '', $event[$prefix . "_mhmodify"], $prefix . "_mhmodify", $debug, $recode_llt);
						if ($debug) {
							error_log("DEBUG: Coded " . strtoupper($prefix) . "_MHMODIFY {$event[$prefix . "_mhmodify"]}: subject=$subject_id, event=$event_id for MH {$event[$prefix . "_oth_mhterm"]}");
						}
					}
				}
			}
			/**
			 * PREFIX_MHDECOD
			 * uses $mh_prefixes preset array
			 */
			foreach ($mh_prefixes AS $prefix) {
				$fields = array($prefix . "_mhmodify", $prefix . "_mhdecod");
				$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
				foreach ($data AS $subject_id => $subject) {
					foreach ($subject AS $event_id => $event) {
						code_pt($project_id, $subject_id, $event_id, $event[$prefix . "_mhmodify"], $event[$prefix . "_mhdecod"], $prefix . "_mhdecod", $debug, $recode_pt);
						if ($debug) {
							error_log("DEBUG: Coded " . strtoupper($prefix) . "_MHDECOD {$event[$prefix . '_mhdecod']}: subject=$subject_id, event=$event_id for MHMODIFY {$event[$prefix . '_mhmodify']}");
						}
					}
				}
			}
			/**
			 * PREFIX_mhBODSYS
			 * uses $mh_prefixes preset array
			 */
			foreach ($mh_prefixes AS $prefix) {
				$fields = array($prefix . "_mhdecod", $prefix . "_mhbodsys");
				$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
				foreach ($data AS $subject_id => $subject) {
					foreach ($subject AS $event_id => $event) {
						code_bodsys($project_id, $subject_id, $event_id, $event[$prefix . "_mhdecod"], $event[$prefix . "_mhbodsys"], $prefix . "_mhbodsys", $debug, $recode_soc);
						if ($debug) {
							error_log("DEBUG: Coded  " . strtoupper($prefix) . "_MHBODSYS {$event[$prefix . "_mhbodsys"]}: subject=$subject_id, event=$event_id for MHDECOD {$event[$prefix . "_mhdecod"]}");
						}
					}
				}
			}
			break;

		case 'cm_coding':
			$recode_llt = false;
			$recode_pt = true;
			$recode_soc = true;
			$recode_atc = false;
			$recode_cm = true;
			/**
			 * CM_CMDECOD
			 */
			$fields = array("cm_cmtrt", "cm_cmdecod", "cm_cmindc", "cm_oth_cmindc", "cm_suppcm_indcod");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					code_cm($project_id, $subject_id, $event_id, $event, $debug, $recode_cm);
					if ($debug) {
						error_log("DEBUG: Coded CONMED: subject=$subject_id, event=$event_id for CMTRT {$event['cm_cmtrt']}");
					}
				}
			}
			/**
			 * cm_suppcm_mktstat
			 * PRESCRIPTION or OTC
			 */
			$fields = array("cm_cmdecod", "cm_suppcm_mktstat");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data as $subject_id => $subject) {
				foreach ($subject as $event_id => $event) {
					if (isset($event['cm_cmdecod']) && $event['cm_cmdecod'] != '') {
						update_field_compare($subject_id, $project_id, $event_id, get_conmed_mktg_status($event['cm_cmdecod']), $event['cm_suppcm_mktstat'], 'cm_suppcm_mktstat', $debug);
						if ($debug) {
							error_log("DEBUG: $subject_id Marketing Status = " . get_conmed_mktg_status($event['cm_cmdecod']));
						}
					}
				}
			}
			/**
			 * CM_SUPPCM_INDCOD
			 */
			$fields = array("cm_cmindc", "cm_oth_cmindc", "cm_suppcm_indcmodf");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					/**
					 * re-code all nutritional support to nutritional supplement
					 */
					if ($event['cm_oth_cmindc'] == 'Nutritional support') {
						$event['cm_oth_cmindc'] = 'Nutritional supplement';
					}
					code_llt($project_id, $subject_id, $event_id, fix_case($event['cm_cmindc']), fix_case($event['cm_oth_cmindc']), $event['cm_suppcm_indcmodf'], 'cm_suppcm_indcmodf', $debug, $recode_llt);
					if ($debug) {
						error_log("DEBUG: Coded INDC LLT: {} subject=$subject_id, event=$event_id for INDICATION {$event['cm_cmindc']}");
					}
				}
			}
			/**
			 * CM_SUPPCM_INDCOD
			 */
			$fields = array("cm_suppcm_indcmodf", "cm_suppcm_indcod");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					code_pt($project_id, $subject_id, $event_id, $event['cm_suppcm_indcmodf'], $event['cm_suppcm_indcod'], 'cm_suppcm_indcod', $debug, $recode_pt);
					if ($debug) {
						error_log("DEBUG: Coded INDC PT: subject=$subject_id, event=$event_id for INDICATION {$event['cm_suppcm_indcod']}");
					}
				}
			}
			/**
			 * CM_SUPPCM_INDCSYS
			 */
			$fields = array("cm_suppcm_indcod", "cm_suppcm_indcsys");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					code_bodsys($project_id, $subject_id, $event_id, $event['cm_suppcm_indcod'], $event['cm_suppcm_indcsys'], 'cm_suppcm_indcsys', $debug, $recode_soc);
					if ($debug) {
						error_log("DEBUG: Coded INDCSYS: subject=$subject_id, event=$event_id for INDC {$event['cm_suppcm_indcod']}");
					}
				}
			}
			/**
			 * CM_SUPPCM_ATCNAME
			 * CM_SUPPCM_ATC2NAME
			 */
			$fields = array("cm_cmdecod", "cm_suppcm_atcname", "cm_suppcm_atc2name");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					code_atc_soc($project_id, $subject_id, $event_id, $event['cm_cmdecod'], $event['cm_suppcm_atcname'], $event['cm_suppcm_atc2name'], $debug, $recode_atc);
					if ($debug) {
						error_log("DEBUG: Coded ATCs: subject=$subject_id, event=$event_id for CONMED {$event['cm_cmdecod']}");
					}
				}
			}
			/**
			 * XFSN_CMDECOD
			 */
			$fields = array("xfsn_cmtrt", "xfsn_cmdecod", "xfsn_cmindc", "xfsn_suppcm_indcod");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					if (isset($event['xfsn_cmtrt']) && $event['xfsn_cmtrt'] != '') {
						$med = array();
						$med_result = db_query("SELECT DISTINCT drug_coded FROM _target_xfsn_coding WHERE drug_name = '" . prep($event['xfsn_cmtrt']) . "'");
						if ($med_result) {
							$med = db_fetch_assoc($med_result);
							if (isset($med['drug_coded']) && $med['drug_coded'] != '') {
								update_field_compare($subject_id, $project_id, $event_id, $med['drug_coded'], $event['xfsn_cmdecod'], 'xfsn_cmdecod', $debug);
							}
						}
						if ($debug) {
							error_log("DEBUG: Coded Transfusion: subject=$subject_id, event=$event_id for CMTRT {$event['xfsn_cmtrt']}");
						}
					} else {
						update_field_compare($subject_id, $project_id, $event_id, '', $event['xfsn_cmdecod'], 'xfsn_cmdecod', $debug);
					}
				}
			}
			/**
			 * XFSN_SUPPCM_INDCOD
			 */
			$fields = array("xfsn_cmindc", "xfsn_suppcm_indcod");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					code_llt($project_id, $subject_id, $event_id, fix_case($event['xfsn_cmindc']), fix_case($event['xfsn_oth_cmindc']), $event['xfsn_suppcm_indcod'], 'xfsn_suppcm_indcod', $debug, $recode_llt);
					if ($debug) {
						error_log("DEBUG: Coded XFSN INDC: subject=$subject_id, event=$event_id for CONMED {$event['xfsn_cmdecod']}");
					}
				}
			}
			/**
			 * XFSN_SUPPCM_INDCSYS
			 */
			$fields = array("xfsn_suppcm_indcod", "xfsn_suppcm_indcsys");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					code_bodsys($project_id, $subject_id, $event_id, $event['xfsn_suppcm_indcod'], $event['xfsn_suppcm_indcsys'], 'xfsn_suppcm_indcsys', $debug, $recode_soc);
					if ($debug) {
						error_log("DEBUG: Coded XFSN INDCSYS: subject=$subject_id, event=$event_id for INDC {$event['xfsn_suppcm_indcod']}");
					}
				}
			}
			/**
			 * XFSN_SUPPCM_ATCNAME
			 * XFSN_SUPPCM_ATC2NAME
			 */
			$fields = array("xfsn_cmdecod", "xfsn_suppcm_atcname", "xfsn_suppcm_atc2name");
			$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
			foreach ($data AS $subject_id => $subject) {
				foreach ($subject AS $event_id => $event) {
					code_atc_xfsn($project_id, $subject_id, $event_id, $event['xfsn_cmdecod'], $event['xfsn_suppcm_atcname'], $event['xfsn_suppcm_atc2name'], $debug, $recode_atc);
					if ($debug) {
						error_log("DEBUG: Coded XFSN ATCs: subject=$subject_id, event=$event_id for CONMED {$event['xfsn_cmdecod']}");
					}
				}
			}
			set_immunosuppressant($record, $redcap_event_name, $debug);
			set_ppi($record, $redcap_event_name, $debug);
			break;

		case 'ex_coding':
			$recode_llt = false;
			$recode_pt = true;
			$recode_soc = true;
			$recode_atc = false;
			$recode_cm = true;
			$tx_prefixes[] = 'eot';
			/**
			 * PREFIX_AEDECOD
			 * uses $tx_prefixes preset array
			 */
			foreach ($tx_prefixes AS $prefix) {
				$fields = array($prefix . "_aemodify", $prefix . "_aedecod");
				$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
				foreach ($data AS $subject_id => $subject) {
					foreach ($subject AS $event_id => $event) {
						code_pt($project_id, $subject_id, $event_id, $event[$prefix . "_aemodify"], $event[$prefix . "_aedecod"], $prefix . "_aedecod", $debug, $recode_pt);
						if ($debug) {
							error_log("DEBUG: Coded " . strtoupper($prefix) . "_AEDECOD {$event[$prefix . '_aedecod']}: subject=$subject_id, event=$event_id for AEMODIFY {$event[$prefix . '_aemodify']}");
						}
					}
				}
			}
			/**
			 * PREFIX_AEBODSYS
			 * uses $tx_prefixes preset array
			 */
			foreach ($tx_prefixes AS $prefix) {
				$fields = array($prefix . "_aedecod", $prefix . "_aebodsys");
				$data = REDCap::getData('array', $record, $fields, $redcap_event_name);
				foreach ($data AS $subject_id => $subject) {
					foreach ($subject AS $event_id => $event) {
						code_bodsys($project_id, $subject_id, $event_id, $event[$prefix . "_aedecod"], $event[$prefix . "_aebodsys"], $prefix . "_aebodsys", $debug, $recode_soc);
						if ($debug) {
							error_log("DEBUG: Coded SOC: subject=$subject_id, event=$event_id for AE {$event[$prefix . "_aedecod"]}");
						}
					}
				}
			}
			break;
		case 'cbc':
			set_cbc_flags($record, $debug);
			set_cirrhosis($record, $debug);
			standardize_lab_form($instrument, $project_id, $record, $redcap_event_name, $debug);
			break;
		/*case 'inr':*/
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
			?>
			<script type="text/javascript">
				$(document).ready(function () {
					console.log('blah');
					$("div#reqPopup").html("blah");
				});
			</script>
			<?php
			set_treatment_exp($record, $debug);
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
		set_survey_completion($record, $debug);
	}
}
if ($debug) {
	$timer['main_end'] = microtime(true);
	error_log(benchmark_timing($timer));
}