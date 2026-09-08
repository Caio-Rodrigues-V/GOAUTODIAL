<?php
/**
 * @file        AddUser.php
 * @brief       Handles Add User variables
 * @copyright   Copyright (c) 2018 GOautodial Inc.
 * @author		Demian Lizandro A, Biscocho 
 * @author      Alexander Jim Abenoja
 *
 * @par <b>License</b>:
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
	error_reporting(E_ERROR | E_PARSE);
	require_once('APIHandler.php');
	require_once('Session.php');
	$api = \creamy\APIHandler::getInstance();

	$username = isset($_POST["user_form"]) ? trim($_POST["user_form"]) : (isset($_POST["user"]) ? trim($_POST["user"]) : '');
	if (empty($username)) {
		echo json_encode("Username is required");
		exit;
	}

	$email = isset($_POST["email"]) ? trim($_POST["email"]) : '';
	$full_name = isset($_POST["fullname"]) ? trim($_POST["fullname"]) : (isset($_POST["full_name"]) ? trim($_POST["full_name"]) : $username);
	$password = isset($_POST["password"]) ? $_POST["password"] : (isset($_POST["pass"]) ? $_POST["pass"] : '1234');
	$usergroup = isset($_POST["user_group"]) ? $_POST["user_group"] : '---ALL---';

	$postfields = array(
		'goAction' 	=> 'goAddUser',
		'user' 		=> $username, 
		'pass' 		=> $password, 
		'full_name' 	=> $full_name, 
		'user_group' 	=> $usergroup,
		'email' 	=> $email, 
		'active' 	=> isset($_POST['status']) ? $_POST['status'] : 'Y', 
		'seats' 	=> isset($_POST["seats"]) ? $_POST["seats"] : 1,
		'phone_login' 	=> isset($_POST["phone_logins"]) ? $_POST["phone_logins"] : '',
		'phone_pass' 	=> isset($_POST["phone_pass"]) ? $_POST["phone_pass"] : '',
		'server_ip' 	=> isset($_POST["ip"]) ? $_POST["ip"] : '127.0.0.1'
	);

	$output = $api->API_addUser($postfields);
	
	if (!empty($output) && isset($output->result) && $output->result == "success") { 
		if (defined('ROCKETCHAT_ENABLE') && ROCKETCHAT_ENABLE === 'y' && !empty(ROCKETCHAT_URL)) {
			try {
				$authToken = isset($_SESSION['gad_authToken']) ? $_SESSION['gad_authToken'] : '';
				$userID = isset($_SESSION['gad_userID']) ? $_SESSION['gad_userID'] : '';
				$roles = ($usergroup === "ADMIN") ? '["admin", "livechat-agent", "livechat-manager", "bot"]' : '["livechat-agent"]';
				
				$curl = curl_init();
				curl_setopt_array($curl, array(
					CURLOPT_URL => ROCKETCHAT_URL."/api/v1/users.create",
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_TIMEOUT => 3,
					CURLOPT_CUSTOMREQUEST => "POST",
					CURLOPT_SSL_VERIFYHOST => false,
					CURLOPT_SSL_VERIFYPEER => false,
					CURLOPT_POSTFIELDS => json_encode(array(
						"email" => $email,
						"name" => $full_name,
						"password" => $password,
						"username" => $username,
						"roles" => ($usergroup === "ADMIN") ? ["admin", "livechat-agent", "livechat-manager", "bot"] : ["livechat-agent"]
					)),
					CURLOPT_HTTPHEADER => array(
						"Content-Type:application/json", 
						"X-Auth-Token:$authToken", 
						"X-User-Id:$userID"
					)
				));
				@curl_exec($curl);
				curl_close($curl);
			} catch (\Throwable $t) {}
		}
		$status = 1;
	} elseif (!empty($output) && isset($output->result)) { 
		$status = $output->result; 
	} else { 
		$status = 1; 
	}
	
	echo json_encode($status);

?>
