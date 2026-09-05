<?php
/**
 * @file        AddCampaign.php
 * @brief       Add New Campaign
 */
	error_reporting(E_ERROR | E_PARSE);
	require_once('APIHandler.php');

	$api = \creamy\APIHandler::getInstance();
	
	$campaign_id = isset($_POST['campaign_id']) ? trim($_POST['campaign_id']) : '';
	$campaign_name = isset($_POST['campaign_name']) ? trim($_POST['campaign_name']) : '';

	if (empty($campaign_id)) {
		echo json_encode("Campaign ID is required");
		exit;
	}

	$postfields = array(
		'goAction' 					=> 'goAddCampaign',
		'campaign_id'				=> $campaign_id,
		'campaign_name' 			=> !empty($campaign_name) ? $campaign_name : $campaign_id,
		'campaign_type'				=> isset($_POST['campaign_type']) ? $_POST['campaign_type'] : 'outbound',
		'dial_method' 				=> isset($_POST['dial_method']) ? $_POST['dial_method'] : 'RATIO',
		'auto_dial_level'			=> isset($_POST['auto_dial_level']) ? $_POST['auto_dial_level'] : '1.0',
		'dial_prefix' 				=> isset($_POST['dial_prefix']) ? $_POST['dial_prefix'] : '9',
		'description' 				=> isset($_POST['description']) ? $_POST['description'] : '',
		'status' 					=> isset($_POST['status']) ? $_POST['status'] : 'Y',
		'user_group' 				=> isset($_POST['user_group']) ? $_POST['user_group'] : '---ALL---'
	);

	$output = $api->API_addCampaign($postfields);

	header('Content-Type: application/json');

	if (!empty($output) && isset($output->result) && $output->result == "success") {
		$response = array(
			'status' 	=> 1,
			'result' 	=> 'success',
			'message' 	=> 'Success'
		);
	} elseif (!empty($output) && isset($output->result)) {
		$response = array(
			'status' 	=> 0,
			'result' 	=> $output->result,
			'message' 	=> $output->result
		);
	} else {
		$response = array(
			'status' 	=> 1,
			'result' 	=> 'success',
			'message' 	=> 'Success'
		);
	}

	echo json_encode($response);
?>
