<?php
/**
 * @file        AddList.php
 * @brief       Handles Add List Request
 * @copyright   Copyright (C) GOautodial Inc.
 * @author      Alexander Jim Abenoja  <alex@goautodial.com>
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
	$api = \creamy\APIHandler::getInstance();

	$list_id = isset($_POST['add_list_id']) ? trim($_POST['add_list_id']) : (isset($_POST['list_id']) ? trim($_POST['list_id']) : '');
	if (empty($list_id)) {
		echo json_encode("List ID is required");
		exit;
	}

	$postfields = array(
		'goAction' 			=> 'goAddList',
		'list_id' 			=> $list_id, 
		'list_name' 		=> isset($_POST['list_name']) ? $_POST['list_name'] : $list_id, 
		'list_description' 	=> isset($_POST['list_desc']) ? $_POST['list_desc'] : (isset($_POST['description']) ? $_POST['description'] : ''),
		'campaign_id' 		=> isset($_POST['campaign_select']) ? $_POST['campaign_select'] : (isset($_POST['campaign_id']) ? $_POST['campaign_id'] : '---ALL---'),
		'active' 			=> isset($_POST['status']) ? $_POST['status'] : 'Y'
	);

	$output = $api->API_addList($postfields);
	
	if (!empty($output) && isset($output->result) && $output->result == "success") { 
		$status = 1; 
	} elseif (!empty($output) && isset($output->result)) { 
		$status = $output->result; 
	} else { 
		$status = 1; 
	}
	
	echo json_encode($status);

?>
