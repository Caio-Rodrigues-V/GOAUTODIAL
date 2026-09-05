<?php
/**
 * @file        AddCarrier.php
 * @brief       Handles Add Carrier Request
 */
	require_once('APIHandler.php');
	$api = \creamy\APIHandler::getInstance();
	
	$carrier_id = isset($_POST['carrier_id']) ? trim($_POST['carrier_id']) : '';
	$carrier_name = isset($_POST['carrier_name']) ? trim($_POST['carrier_name']) : '';

	if (empty($carrier_id)) {
		echo json_encode("Carrier ID is required");
		exit;
	}

	$postfields = array(
			'goAction' 				=> 'goAddCarrier',
			'carrier_type'			=> isset($_POST['carrier_type']) ? $_POST['carrier_type'] : 'manual',
			'carrier_id' 			=> $carrier_id,
			'carrier_name'			=> $carrier_name,
			'active' 				=> isset($_POST['active']) ? $_POST['active'] : 'Y',
			'protocol'				=> isset($_POST['protocol']) ? $_POST['protocol'] : 'CUSTOM',
			'carrier_description' 	=> isset($_POST['carrier_description']) ? $_POST['carrier_description'] : '',
			'user_group' 			=> isset($_POST['user_group']) ? $_POST['user_group'] : '---ALL---',
			'authentication' 		=> isset($_POST['authentication']) ? $_POST['authentication'] : 'auth_ip',
			'username'				=> isset($_POST['username']) ? $_POST['username'] : '',
			'password'				=> isset($_POST['password']) ? $_POST['password'] : '',
			'reg_host'				=> isset($_POST['reg_host']) ? $_POST['reg_host'] : '',
			'reg_port'				=> isset($_POST['reg_port']) ? $_POST['reg_port'] : '5060',
			'sip_server_ip' 		=> isset($_POST['sip_server_ip']) ? $_POST['sip_server_ip'] : '',
			'codecs' 				=> isset($_POST['codecs']) ? $_POST['codecs'] : array(),
			'dtmf'					=> isset($_POST['dtmf']) ? $_POST['dtmf'] : 'rfc2833',
			'custom_dtmf' 			=> isset($_POST['custom_dtmf']) ? $_POST['custom_dtmf'] : '',
			'dialprefix' 			=> isset($_POST['dialprefix']) ? $_POST['dialprefix'] : '9',
			'cust_protocol' 		=> isset($_POST['cust_protocol']) ? $_POST['cust_protocol'] : 'SIP',
			'registration_string' 	=> isset($_POST['registration_string']) ? $_POST['registration_string'] : '',
			'account_entry' 		=> isset($_POST['account_entry']) ? $_POST['account_entry'] : '',
			'globals_string' 		=> isset($_POST['globals_string']) ? $_POST['globals_string'] : '',
			'dialplan_entry' 		=> isset($_POST['dialplan_entry']) ? $_POST['dialplan_entry'] : '',
			'manual_server_ip'		=> isset($_POST['server_ip']) ? $_POST['server_ip'] : '127.0.0.1',
			'server_ip'				=> isset($_POST['server_ip']) ? $_POST['server_ip'] : '127.0.0.1',
			'copy_server_ip' 		=> isset($_POST['copy_server_ip']) ? $_POST['copy_server_ip'] : '',
			'source_carrier' 		=> isset($_POST['source_carrier']) ? $_POST['source_carrier'] : ''
		);

	$output = $api->API_addCarrier($postfields);

	if (!empty($output) && isset($output->result) && $output->result == "success") { 
		$status = 1; 
	} elseif (!empty($output) && isset($output->result)) { 
		$status = $output->result; 
	} else {
		$status = 1;
	}

	echo json_encode($status);
?>
