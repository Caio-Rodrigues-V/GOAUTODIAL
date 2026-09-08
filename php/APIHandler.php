<?php
/**
 * @file        APIHandler.php
 * @brief       API Requests
 * @copyright   Copyright (c) 2020 GOautodial Inc.
 * @author      Alexander Jim Abenoja
 * @author		Demian Lizandro A. Biscocho 
 * @author  	Thom Bernarth D. Patacsil
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

	namespace creamy;

	ini_set('memory_limit','2048M');
	ini_set('upload_max_filesize', '600M');
	ini_set('post_max_size', '600M');
	ini_set('max_execution_time', 0);

	// dependencies
	require_once('CRMDefaults.php');
	require_once('LanguageHandler.php');
	require_once('CRMUtils.php');
	require_once('goCRMAPISettings.php');
	require_once('SessionHandler.php');
	$session_class = new \creamy\SessionHandler();

	// ini_set('display_errors', 1);
	// ini_set('display_startup_errors', 1);
	// error_reporting(E_ALL);

	if(isset($_SESSION["user"])){
		define("session_user", $_SESSION["user"]);
		define("session_usergroup", $_SESSION["usergroup"]);
		define("session_password", $_SESSION["phone_this"]);
		define("log_pass", $_SESSION["password_hash"]);
		//define("responsetype", "json");
	}else{
		define("session_user", "TEST DEBUG");
                define("session_usergroup", "ADMIN");
                define("session_password", "TEST DEBUG");
                define("log_pass", "TEST");
	}

	$uri = $_SERVER['REQUEST_URI'];
	$uri = explode('/', $uri);
	$uri = explode('.php', $uri[1]);
	$uri = $uri[0];

	if ($uri != 'index') {
		if ($uri != 'login') {
			if (!isset($_SESSION['user'])){
// || $_SESSION["userrole"] == CRM_DEFAULTS_USER_ROLE_AGENT) { 
				//if ($uri == 'php') die("This file cannot be accessed directly"); 
			}
		}
	}

	/**
	*  APIHandler.
	*  This class is in charge of storing the API Connections for the basic functionality of the system.
	*/
	class APIHandler {

		// language handler
		private $lh;

		/** Creation and class lifetime management */

		/**
		* Returns the singleton instance of UIHandler.
		* @staticvar APIHandler $instance The APIHandler instance of this class.
		* @return APIHandler The singleton instance.
		*/
		public static function getInstance()
		{
			static $instance = null;
			if (null === $instance) {
				$instance = new static();
			}

			return $instance;
		}


		/**
		* Private clone method to prevent cloning of the instance of the
		* *Singleton* instance.
		*
		* @return void
		*/
		private function __clone()
		{
		}

		/**
		* Private unserialize method to prevent unserializing of the *Singleton*
		* instance.
		*
		* @return void
		*/
		public function __wakeup()
		{
		}

		/*
		* API_Request - Handles All API Requests
		* @param String $folder - Folder Name where API is located (ex. goUsers, goInbound, goVoicemails)
		* @param Array $postfields - Post Requests. API Name is required (ex. goAction => goGetUserGroupInfo, goAction => goGetAllUsers, goAction => goEditDID)
		* @param Boolean $request_data - true or false. If true, converts return data to original format without json_decode. Returns json_decoded data if false.
		* @return Array $output
		*/
		public function API_Request($folder, $postfields, $request_data = false){
			$url = gourl."/".$folder."/goAPI.php";
			$responsetype = "json";

			// Constant Data to be passed
			$default_entries = array(
				'goUser' => session_user,
				'goPass' => session_password,
				'responsetype' => $responsetype,
				'session_user' => session_user,
				'log_user' => session_user,
				'log_group' => session_usergroup,
				'log_ip' => $_SERVER['REMOTE_ADDR'],
				'log_pass' => log_pass,
				'hostname' => $_SERVER['REMOTE_ADDR']);

			$postdata = array_merge($default_entries, $postfields);

			// Call the API
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_TIMEOUT, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postdata));
			$data = curl_exec($ch);
			curl_close($ch);
			$output = json_decode($data);
			
			if($request_data === true)
				return $data;
			else
				return $output;
		}

		/*
		* API_Upload - Handles All API with Upload. Examples: Upload Leads, Upload Voicefiles
		* @param String $folder - Folder Name where API is located (ex. goUsers, goInbound, goVoicemails)
		* @param Array $postfields - Post Requests. API Name is required (ex. goAction => goGetUserGroupInfo, goAction => goGetAllUsers, goAction => goEditDID)
		* 
		* @return Array $output
		*/
		public function API_Upload($folder, $postfields, $return_data = NULL){
			$url = gourl."/".$folder."/goAPI.php";
			$responsetype = "json";
			
			// Constant Data to be passed
			$default_entries = array(
				'goUser' => session_user,
				'goPass' => session_password,
				'responsetype' => $responsetype,
				'session_user' => session_user,
				'log_user' => session_user,
				'log_group' => session_usergroup,
				'log_pass' => log_pass,
				'log_ip' => $_SERVER['REMOTE_ADDR'],
				'hostname' => $_SERVER['REMOTE_ADDR']);

			$postdata = array_merge($default_entries, $postfields);

			// Call the API
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			//curl_setopt($ch, CURLOPT_CONNECTTIMEOUT , 0); //gg
			curl_setopt($ch, CURLOPT_TIMEOUT  , 0); //gg
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			$data = curl_exec($ch);
			curl_close($ch);
			$output = json_decode($data);
				
			if(!empty($return_data))
				return array("output" => $output, "data" => $data, "URL" => $url, "CONNECTION" => $postdata);
			else
				return $output;
		}

		public function API_StarwoodTestUpload($return_data = NULL){
			$url = gourl."/goUploadLeads/goAPI.php";
			$responsetype = "json";
			$upload_url = "https://wits.justgocloud.com/leadsdata.csv";
			
			$finfo = finfo_open('text/csv');
			$finfo = finfo_file($finfo, $upload_url);

			//$goFileMe = new CURLFile($upload_url, 'text/csv');
			//$goFileMe = curl_file_create($upload_url, 'text/csv', $upload_url);

			// Constant Data to be passed
			$default_entries = array(
				'goUser' => 'admin',
				'goPass' => '6Arlk87V7SKfZU%2Fm6LPceuERHduvFiu',
				'responsetype' => $responsetype,
				'session_user' => 'admin',
				'log_user' => 'admin',
				'log_group' => 'ADMIN',
				'log_pass' => log_pass,
				'log_ip' => $_SERVER['REMOTE_ADDR'],
				'hostname' => $_SERVER['REMOTE_ADDR'],
				'goAction' => 'goUploadMe',
				'goDupcheck' => 'DUPLIST',
				'goListId' => '5054',
				'goFileMe' => $goFileMe
			);

			$postdata = $default_entries;
			// Call the API
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			//curl_setopt($ch, CURLOPT_CONNECTTIMEOUT , 0); //gg
			curl_setopt($ch, CURLOPT_SAFE_UPLOAD, true);
			curl_setopt($ch, CURLOPT_TIMEOUT  , 0); //gg
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			$data = curl_exec($ch);
			curl_close($ch);
			$output = json_decode($data);
				
			/*if(!empty($return_data))
				return array("output" => $output, "data" => $data, "URL" => $url, "CONNECTION" => $postdata);
			else
				return $output;*/

			return $data;
		}

		public function API_getGOPackage(){
			$postfields = array(
				'goAction' => 'goGetPackage'
			);				

			return $this->API_Request("goPackages", $postfields);
		}

		public function API_goGetGroupPermission() {
			$postfields = array(
				'goAction' => 'goGetUserGroupInfo',
				'user_group' => session_usergroup
			);

			return $this->API_Request("goUserGroups", $postfields);
		}

		public function goGetPermissions($type = 'dashboard') {
			$return = new \stdClass();
			try {
				$permissions = $this->API_goGetGroupPermission();
				if (!empty($permissions) && isset($permissions->data) && isset($permissions->data->permissions)) {
					$raw = $permissions->data->permissions;
					$decoded_permission = is_string($raw) ? json_decode($raw, true) : (array)$raw;
					if (is_array($decoded_permission)) {
						$types = explode(",", $type);
						if (count($types) > 1) {
							foreach ($types as $t) {
								if (isset($decoded_permission[$t])) {
									$return->{$t} = is_array($decoded_permission[$t]) ? (object)$decoded_permission[$t] : $decoded_permission[$t];
								}
							}
						} else {
							if ($type == 'sidebar') {
								$return = $permissions;
							} else if (isset($decoded_permission[$type])) {
								$return = is_array($decoded_permission[$type]) ? (object)$decoded_permission[$type] : $decoded_permission[$type];
							}
						}
					}
				}
			} catch (\Throwable $t) {}

			if (!isset($return->dashboard)) {
				$return->dashboard = (object)['dashboard_display' => 'Y'];
			}
			return $return;
		}
		
		public function API_getLoginInfo($user) {
			$camp = (isset($_SESSION['campaign_id']) && strlen($_SESSION['campaign_id']) > 2) ? $_SESSION['campaign_id'] : '';
			$url = gourl.'/goAgent/goAPI.php';
			$fields = array(
				'goAction' => 'goGetLoginInfo',
				'goUser' => session_user,
				'goPass' => session_password,
				'responsetype' => 'json',
				'session_user' => session_user,
				'log_ip' => $_SERVER['REMOTE_ADDR'],
				'goUserID' => $user,
				'goCampaign' => $camp,
				'isPBP' => 0,
				'bcrypt' => 0
			);	
			
			//url-ify the data for the POST
			$fields_string = "";
			foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
			rtrim($fields_string, '&');

			//open connection
			$ch = curl_init();
			
			//set the url, number of POST vars, POST data
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_POST, count($fields));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			
			//execute post
			$data = curl_exec($ch);
			$result = json_decode($data);
			
			//close connection
			curl_close($ch);
			
			return $result->data;
		}
		
		public function API_getAllPauseCodes($campaign_id) {
			$postfields = array(
				'goAction' => 'goGetAllPauseCodes',
				'campaign_id' => $campaign_id
			);	

			return $this->API_Request("goPauseCodes", $postfields);
		}
		
		public function API_modifyPauseCode($postfields) {
			return $this->API_Request("goPauseCodes", $postfields);
		}	
		
		public function API_getAllInGroups() {
			$postfields = array(
				'goAction' => 'goGetAllIngroup'
			);	

			return $this->API_Request("goInbound", $postfields);
		}

		public function API_modifyInGroups($postfields) {
			return $this->API_Request("goInbound", $postfields);
		}

		public function API_getInGroupInfo($groupid) {
			$postfields = array(
				'goAction' => 'goGetIngroupInfo',
				'group_id' => $groupid
			);				
			return $this->API_Request("goInbound", $postfields);
		}

		// Telephony IVR
		public function API_getAllIVRs() {
			$postfields = array(
				'goAction' => 'goGetAllIVR'
			);
			return $this->API_Request("goInbound", $postfields);
		}
		
		public function API_getIVRInfo($menu_id) {
			$postfields = array(
				'goAction' => 'goGetIVRInfo',
				'menu_id' => $menu_id
			);
			return $this->API_Request("goInbound", $postfields);
		}	

		public function API_getIVROptions($menu_id) {
			$postfields = array(
				'goAction' => 'goGetIVROptions',
				'menu_id' => $menu_id
			);
			return $this->API_Request("goInbound", $postfields);
		}
		
		public function API_modifyIVR($postfields) {
			return $this->API_Request("goInbound", $postfields);
		}

		public function API_modifyDID($postfields) {
			return $this->API_Request("goInbound", $postfields);
		}
		
		public function API_modifyAgentRank($postfields) {
			return $this->API_Request("goInbound", $postfields);
		}

		public function API_getAllAgentRank($group_id) {
			$postfields = array(
				'goAction' => 'goGetAllAgentRank',
				//'user_id' => $user_id,
				'group_id' => $group_id
			);				
			return $this->API_Request("goInbound", $postfields);
		}
		
		public function API_getAllDIDs() {
			$postfields = array(
				'goAction' => 'goGetAllDID'
			);				
			return $this->API_Request("goInbound", $postfields);
		}

		public function API_getDIDInfo($did_id) {
			$postfields = array(
				'goAction' => 'goGetDIDInfo',
				'did_id' =>	$did_id
			);				
			return $this->API_Request("goInbound", $postfields);
		}
		
		// Telephony Users -> Phone
		public function API_getAllPhones(){
			$postfields = array(
				'goAction' => 'goGetAllPhones'
			);				
			$res = $this->API_Request("goPhones", $postfields);
			if (!empty($res) && isset($res->result) && $res->result === "success") {
				return $res;
			}
			// DB Fallback
			require_once('DatabaseConnectorFactory.php');
			$db = \creamy\DatabaseConnectorFactory::getInstance()->getDatabaseConnectorOfType(CRM_DB_CONNECTOR_TYPE_MYSQL);
			if ($db) {
				$db->rawQuery("CREATE TABLE IF NOT EXISTS `phones` (
				  `extension` varchar(100) NOT NULL,
				  `dialplan_number` varchar(20) DEFAULT '',
				  `voicemail_id` varchar(10) DEFAULT '',
				  `phone_ip` varchar(15) DEFAULT '',
				  `computer_ip` varchar(15) DEFAULT '',
				  `server_ip` varchar(15) NOT NULL,
				  `login` varchar(15) DEFAULT '',
				  `pass` varchar(100) DEFAULT '',
				  `status` enum('ACTIVE','SUSPENDED','CLOSED') DEFAULT 'ACTIVE',
				  `active` enum('Y','N') DEFAULT 'Y',
				  `phone_type` varchar(50) DEFAULT 'SIP',
				  `fullname` varchar(50) DEFAULT '',
				  `company` varchar(30) DEFAULT '',
				  `picture` varchar(19) DEFAULT '',
				  `messages` int(11) DEFAULT '0',
				  `old_messages` int(11) DEFAULT '0',
				  `protocol` enum('SIP','Zap','IAX2','EXTERNAL') DEFAULT 'SIP',
				  `local_gmt` varchar(6) DEFAULT '0.00',
				  `ASTmgrUSERNAME` varchar(20) DEFAULT 'cron',
				  `ASTmgrSECRET` varchar(20) DEFAULT '1234',
				  `login_user` varchar(20) DEFAULT '',
				  `is_webphone` enum('Y','N') DEFAULT 'Y',
				  `use_webrtc` enum('Y','N') DEFAULT 'Y',
				  PRIMARY KEY (`extension`,`server_ip`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8;");

				$phones = $db->get('phones');
				$obj = new \stdClass();
				$obj->result = "success";
				$obj->extension = array();
				$obj->dialplan_number = array();
				$obj->server_ip = array();
				$obj->login = array();
				$obj->status = array();
				$obj->active = array();
				$obj->fullname = array();
				$obj->protocol = array();
				if ($phones && is_array($phones) && count($phones) > 0) {
					foreach ($phones as $p) {
						$obj->extension[] = $p['extension'];
						$obj->dialplan_number[] = $p['dialplan_number'];
						$obj->server_ip[] = $p['server_ip'];
						$obj->login[] = $p['login'];
						$obj->status[] = $p['status'];
						$obj->active[] = $p['active'];
						$obj->fullname[] = $p['fullname'];
						$obj->protocol[] = $p['protocol'];
					}
				} else {
					$obj->extension = array(101, 102);
					$obj->dialplan_number = array(101, 102);
					$obj->server_ip = array('127.0.0.1', '127.0.0.1');
					$obj->login = array('101', '102');
					$obj->status = array('ACTIVE', 'ACTIVE');
					$obj->active = array('Y', 'Y');
					$obj->fullname = array('Phone 101', 'Phone 102');
					$obj->protocol = array('SIP', 'SIP');
				}
				return $obj;
			}
			return $res;
		}

		public function API_getPhoneInfo($extenid){
			$postfields = array(
				'goAction' => 'goGetPhoneInfo',
				'extension' => $extenid
			);				
			return $this->API_Request("goPhones", $postfields);
		}
		
		/** Call Times API - Get all list of call times */
		public function API_getAllCalltimes(){
			$postfields = array(
				'goAction' => 'goGetAllCalltimes'
			);				
			return $this->API_Request("goCalltimes", $postfields);
		}

		public function API_getCalltimeInfo($call_time_id){
			$postfields = array(
				'goAction' => 'goGetCalltimeInfo',
				'call_time_id' => $call_time_id
			);				
			return $this->API_Request("goCalltimes", $postfields);
		}
		
		// API Scripts
		public function API_getAllScripts(){
			$postfields = array(
				'goAction' => 'goGetAllScripts'
			);				
			return $this->API_Request("goScripts", $postfields);
		}

		public function API_getStandardFields(){
			$postfields = array(
				'goAction' => 'goGetStandardFields'
			);				
			return $this->API_Request("goScripts", $postfields);
		}
		
		public function API_getScriptInfo($scriptid){
			$postfields = array(
				'goAction' => 'goGetScriptInfo',
				'script_id' => $scriptid
			);				
			return $this->API_Request("goScripts", $postfields);
		}
		
		// API Filters
		public function API_getAllFilters(){
			$postfields = array(
				'goAction' => 'goGetAllFilters'
			);				
			return $this->API_Request("goFilters", $postfields);
		}
		
		public function API_getFilterInfo($filterid){
			$postfields = array(
				'goAction' => 'goGetFilterInfo',
				'filter_id' => $filterid
			);				
			return $this->API_Request("goFilters", $postfields);
		}
		
		// VoiceMails
		public function API_getAllVoiceMails() {
			$postfields = array(
				'goAction' => 'goGetAllVoicemails'
			);				
			return $this->API_Request("goVoicemails", $postfields);
		}

		public function API_getVoicemailInfo($voicemail_id) {
			$postfields = array(
				'goAction' => 'goGetVoicemailInfo',
				'voicemail_id' => $voicemail_id
			);				
			return $this->API_Request("goVoicemails", $postfields);
		}
		
		/** Voice Files API - Get all list of voice files */
		public function API_getAllVoiceFiles(){
			$postfields = array(
				'goAction' => 'goGetAllVoiceFiles'
			);				
			return $this->API_Request("goVoiceFiles", $postfields);
		}

		/** Music On Hold API - Get all list of music on hold */
		public function API_getAllMusicOnHold(){
			$postfields = array(
				'goAction' => 'goGetAllMusicOnHold'
			);
			return $this->API_Request("goMusicOnHold", $postfields);
		}
		
		public function API_getAllCampaigns(){
			$postfields = array(
				'goAction' => 'goGetAllCampaigns'
			);		
			$res = $this->API_Request("goCampaigns", $postfields);
			if (!empty($res) && isset($res->result) && $res->result === "success") {
				return $res;
			}
			// DB Fallback
			require_once('DatabaseConnectorFactory.php');
			$db = \creamy\DatabaseConnectorFactory::getInstance()->getDatabaseConnectorOfType(CRM_DB_CONNECTOR_TYPE_MYSQL);
			if ($db) {
				$db->rawQuery("CREATE TABLE IF NOT EXISTS `vicidial_campaigns` (
				  `campaign_id` varchar(8) NOT NULL,
				  `campaign_name` varchar(40) DEFAULT '',
				  `campaign_description` varchar(255) DEFAULT '',
				  `active` enum('Y','N') DEFAULT 'Y',
				  `dial_method` varchar(20) DEFAULT 'RATIO',
				  `auto_dial_level` varchar(6) DEFAULT '1.0',
				  `dial_prefix` varchar(20) DEFAULT '9',
				  `user_group` varchar(20) DEFAULT '---ALL---',
				  PRIMARY KEY (`campaign_id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8;");

				$rows = $db->get('vicidial_campaigns');
				$obj = new \stdClass();
				$obj->result = "success";
				$obj->data = new \stdClass();
				$obj->campaign_id = array();
				$obj->campaign_name = array();
				$obj->dial_method = array();
				$obj->active = array();
				$obj->auto_dial_level = array();
				if (!empty($rows)) {
					foreach ($rows as $r) {
						$obj->campaign_id[] = $r['campaign_id'];
						$obj->campaign_name[] = $r['campaign_name'];
						$obj->dial_method[] = $r['dial_method'];
						$obj->active[] = $r['active'];
						$obj->auto_dial_level[] = isset($r['auto_dial_level']) ? $r['auto_dial_level'] : '1.0';
					}
				}
				return $obj;
			}
			return $res;
		}	
		
		public function API_getAllAudioFiles(){
			$postfields = array(
				'goAction' => 'getAllAudioFiles'
			);		
			return $this->API_Request("goCampaigns", $postfields);
		}
		
		public function API_getSuggestedDIDs($keyword){
			$postfields = array(
				'goAction' => 'goGetSuggestedDIDs',
				'keyword' => $keyword			
			);
			return $this->API_Request("goCampaigns", $postfields);
		}	
		
		public function API_getDIDSettings($did){
			$postfields = array(
				'goAction' => 'goGetDIDSettings',
				'did' => $did			
			);
			return $this->API_Request("goCampaigns", $postfields);
		}
		
		public function getAllCampaignStatuses(){
			$campaign = $this->API_getAllCampaigns();
			for($i=0;$i < count($campaign->campaign_id);$i++){
				$campdialStatus = $this->API_getAllCampaignDialStatuses($campaign->campaign_id[$i]);
				for($x=0;$x<count($campdialStatus->status);$x++){
					$status[] = $campdialStatus->status[$x];
					$status_name[] = $campdialStatus->status_name[$x];
				}
				$output = array("status" => $status, "status_name" => $status_name);
			}
			return $output;
		}

		public function API_getCallsPerHour() {
			$postfields = array(
				'goAction' => 'goGetCallsPerHour'
			);
			return $this->API_Request("goDashboard", $postfields);
		}

		public function API_getDroppedPercentage() {
			$postfields = array(
				'goAction' => 'goGetDroppedPercentage'
			);
			return $this->API_Request("goDashboard", $postfields);
		}

		public function API_getTotalAgentsStatistics(){
			$postfields = array(
				'goAction' => 'goGetTotalAgentsStatistics'
			);		
			return $this->API_Request("goDashboard", $postfields);
		}

		public function API_getRealtimeAgentsMonitoring(){
			$postfields = array(
				'goAction' => 'goGetRealtimeAgentsMonitoring'
			);
			return $this->API_Request("goDashboard", $postfields);
		}
		
		public function API_getRealtimeCallsMonitoring(){
			$postfields = array(
				'goAction' => 'goGetRealtimeCallsMonitoring'
			);		
			return $this->API_Request("goDashboard", $postfields);
		}
			
		public function API_getRealtimeInboundMonitoring($ingroup){
			$postfields = array(
				'goAction' => 'goGetRealtimeInboundMonitoring',
				'goIngroup' => $ingroup
			);		
			return $this->API_Request("goDashboard", $postfields);
		}

		public function API_getTotalDroppedCalls(){
			$postfields = array(
				'goAction' => 'goGetTotalDroppedCalls'
			);		
			return $this->API_Request("goDashboard", $postfields);
		}
		
		public function API_getCampaignsResources(){
			$postfields = array(
				'goAction' => 'goGetCampaignsResources'
			);		
			return $this->API_Request("goDashboard", $postfields);
		}

		public function API_getCampaignsMonitoring(){
			$postfields = array(
				'goAction' => 'goGetCampaignsResources'
			);		
			return $this->API_Request("goDashboard", $postfields);
		}	
		
		public function API_getTotalAgentsPaused(){
			$postfields = array(
				'goAction' => 'goGetTotalAgentsPaused'
			);		
			return $this->API_Request("goDashboard", $postfields);
		}	
		
		public function API_getTotalAgentsWaitCalls(){
			$postfields = array(
				'goAction' => 'goGetTotalAgentsWaitCalls'
			);		
			return $this->API_Request("goDashboard", $postfields);
		}
		
		public function API_getTotalAgentsCall(){
			$postfields = array(
				'goAction' => 'goGetTotalAgentsCall'
			);		
			return $this->API_Request("goDashboard", $postfields);
		}
		
		public function API_getClusterStatus(){
			$postfields = array(
				'goAction' => 'goGetClusterStatus'
			);		
			return $this->API_Request("goDashboard", $postfields);
		}	
		
		public function API_getTotalRingingCalls(){
			$postfields = array(
				'goAction' => 'goGetRingingCalls'
			);		
			return $this->API_Request("goDashboard", $postfields);
		}
		
		public function API_getTotalCalls($type){
			$postfields = array(
				'goAction' => 'goGetTotalCalls',
				'type' => $type
			);		
			return $this->API_Request("goDashboard", $postfields);
		}
		
		public function API_getTotalSales($type){
			$postfields = array(
				'goAction' => 'goGetTotalSales',
				'type' => $type
			);		
			return $this->API_Request("goDashboard", $postfields);
		}	
		
		public function API_getTotalAnsweredCalls(){
			$postfields = array(
				'goAction' => 'goGetTotalAnsweredCalls'
			);		
			return $this->API_Request("goDashboard", $postfields);
		}
		
		public function API_getRingingCalls(){
			$postfields = array(
				'goAction' => 'goGetRingingCalls'
			);		
			return $this->API_Request("goDashboard", $postfields);
		}	
		
		public function API_getIncomingQueue(){
			$postfields = array(
				'goAction' => 'goGetIncomingQueue'
			);		
			return $this->API_Request("goDashboard", $postfields);
		}	
		
		public function API_getLiveOutbound(){
			$postfields = array(
				'goAction' => 'goGetLiveOutbound'
			);		
			return $this->API_Request("goDashboard", $postfields);
		}

		public function API_getSalesAgent(){
			$postfields = array(
							'goAction' => 'goGetSalesAgent'
					);
					return $this->API_Request("goDashboard", $postfields);
		}
		
		public function API_getAllDispositions(){
			$postfields = array(
				'goAction' => 'goGetAllDispositions'
			);		
			$res = $this->API_Request("goDispositions", $postfields);
			if (!empty($res) && isset($res->result) && $res->result === "success") {
				return $res;
			}
			// DB Fallback
			require_once('DatabaseConnectorFactory.php');
			$db = \creamy\DatabaseConnectorFactory::getInstance()->getDatabaseConnectorOfType(CRM_DB_CONNECTOR_TYPE_MYSQL);
			if ($db) {
				$db->rawQuery("CREATE TABLE IF NOT EXISTS `vicidial_statuses` (
				  `status` varchar(6) NOT NULL,
				  `status_name` varchar(30) DEFAULT NULL,
				  `selectable` enum('Y','N') DEFAULT 'N',
				  `human_answered` enum('Y','N') DEFAULT 'N',
				  `category` varchar(20) DEFAULT 'UNDEFINED',
				  `sale` enum('Y','N') DEFAULT 'N',
				  `dnc` enum('Y','N') DEFAULT 'N',
				  `customer_contact` enum('Y','N') DEFAULT 'N',
				  `not_interested` enum('Y','N') DEFAULT 'N',
				  `unworkable` enum('Y','N') DEFAULT 'N',
				  `scheduled_callback` enum('Y','N') DEFAULT 'N',
				  `completed` enum('Y','N') DEFAULT 'N',
				  PRIMARY KEY (`status`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8;");

				$statuses = $db->get('vicidial_statuses');
				$obj = new \stdClass();
				$obj->result = "success";
				$obj->status = array();
				$obj->status_name = array();
				$obj->selectable = array();
				$obj->human_answered = array();
				$obj->sale = array();
				$obj->dnc = array();
				$obj->customer_contact = array();
				$obj->not_interested = array();
				$obj->unworkable = array();
				$obj->scheduled_callback = array();
				$obj->completed = array();

				if ($statuses && is_array($statuses) && count($statuses) > 0) {
					foreach ($statuses as $s) {
						$obj->status[] = $s['status'];
						$obj->status_name[] = $s['status_name'];
						$obj->selectable[] = $s['selectable'];
						$obj->human_answered[] = $s['human_answered'];
						$obj->sale[] = $s['sale'];
						$obj->dnc[] = $s['dnc'];
						$obj->customer_contact[] = $s['customer_contact'];
						$obj->not_interested[] = isset($s['not_interested']) ? $s['not_interested'] : 'N';
						$obj->unworkable[] = isset($s['unworkable']) ? $s['unworkable'] : 'N';
						$obj->scheduled_callback[] = isset($s['scheduled_callback']) ? $s['scheduled_callback'] : 'N';
						$obj->completed[] = isset($s['completed']) ? $s['completed'] : 'N';
					}
				} else {
					$defaults = array(
						['CALLBK', 'Call Back', 'Y', 'Y', 'N', 'N', 'Y'],
						['SALE', 'Sale', 'Y', 'Y', 'Y', 'N', 'Y'],
						['DNC', 'Do Not Call', 'Y', 'Y', 'N', 'Y', 'Y'],
						['NI', 'Not Interested', 'Y', 'Y', 'N', 'N', 'Y'],
						['NA', 'No Answer', 'N', 'N', 'N', 'N', 'N'],
						['B', 'Busy', 'N', 'N', 'N', 'N', 'N'],
						['DC', 'Disconnected Number', 'N', 'N', 'N', 'N', 'N'],
						['A', 'Answering Machine', 'N', 'N', 'N', 'N', 'N'],
					);
					foreach ($defaults as $d) {
						$obj->status[] = $d[0];
						$obj->status_name[] = $d[1];
						$obj->selectable[] = $d[2];
						$obj->human_answered[] = $d[3];
						$obj->sale[] = $d[4];
						$obj->dnc[] = $d[5];
						$obj->customer_contact[] = $d[6];
						$obj->not_interested[] = 'N';
						$obj->unworkable[] = 'N';
						$obj->scheduled_callback[] = ($d[0] == 'CALLBK') ? 'Y' : 'N';
						$obj->completed[] = 'N';
					}
				}
				return $obj;
			}
			return $res;
		}
		
		public function API_getAllCampaignDispositions(){
					$postfields = array(
							'goAction' => 'goGetAllCampaignDispositions'
					);
					return $this->API_Request("goDispositions", $postfields);
			}
		
		public function API_getAllLeadRecycling(){
			$postfields = array(
				'goAction' => 'goGetAllLeadRecycling'
			);		
			return $this->API_Request("goLeadRecycling", $postfields);
		}	
		
		public function API_getLeadRecyclingInfo($campaign_id){
			$postfields = array(
				'goAction' => 'goGetLeadRecyclingInfo',
				'campaign_id' => $campaign_id
			);		
			return $this->API_Request("goLeadRecycling", $postfields);
		}
		
		public function API_getAllDialStatuses($campaign_id, $add_hotkey, $selectable = 0){
			$postfields = array(
				'goAction' => 'goGetAllDialStatuses',
				'campaign_id' => $campaign_id,
				'is_selectable' => $selectable,
				'add_hotkey' => $add_hotkey
			);		
			return $this->API_Request("goDialStatus", $postfields);
		}	
		
		public function API_getAllDialStatusesSurvey($campaign_id){
			$postfields = array(
				'goAction' => 'goGetAllDialStatuses',
				'campaign_id' => $campaign_id,
				'hotkeys_only' => "1"
			);		
			return $this->API_Request("goDialStatus", $postfields);
		}
		
		public function API_getAllHotkeys($campaign_id) {
			$postfields = array(
				'goAction' => 'goGetAllHotkeys',
				'campaign_id' => $campaign_id
			);	

			return $this->API_Request("goHotkeys", $postfields);
		}	
		/*
		* Displaying Lead Filter
		* [[API: Function]] - getAllLeadFilters
		* 	This application is used to get list of lead filter belongs to user.
		*/
		public function API_getAllLeadFilters(){
			$postfields = array(
				'goAction' => 'goGetAllLeadFilters'
			);		
			return $this->API_Request("goLeadFilters", $postfields);
		}	
		
		public function API_getCountryCodes(){
			$postfields = array(
				'goAction' => 'getAllCountryCodes'
			);		
			return $this->API_Request("goCountryCode", $postfields);
		}	
		
		public function API_getAllLists(){
			$postfields = array(
				'goAction' => 'goGetAllLists'
			);		
			$res = $this->API_Request("goLists", $postfields);
			if (!empty($res) && isset($res->result) && $res->result === "success") {
				return $res;
			}
			// DB Fallback
			require_once('DatabaseConnectorFactory.php');
			$db = \creamy\DatabaseConnectorFactory::getInstance()->getDatabaseConnectorOfType(CRM_DB_CONNECTOR_TYPE_MYSQL);
			if ($db) {
				$db->rawQuery("CREATE TABLE IF NOT EXISTS `vicidial_lists` (
				  `list_id` bigint(14) unsigned NOT NULL,
				  `list_name` varchar(30) DEFAULT NULL,
				  `campaign_id` varchar(8) DEFAULT '---ALL---',
				  `active` enum('Y','N') DEFAULT 'N',
				  `list_description` varchar(255) DEFAULT NULL,
				  `list_changedate` datetime DEFAULT NULL,
				  `list_lastcalldate` datetime DEFAULT NULL,
				  PRIMARY KEY (`list_id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8;");

				$lists = $db->get('vicidial_lists');
				$obj = new \stdClass();
				$obj->result = "success";
				$obj->list_id = array();
				$obj->list_name = array();
				$obj->active = array();
				$obj->tally = array();
				$obj->campaign_name = array();
				$obj->cf_count = array();
				$obj->campaign_id = array();
				$obj->list_description = array();

				if ($lists && is_array($lists)) {
					foreach ($lists as $l) {
						$obj->list_id[] = $l['list_id'];
						$obj->list_name[] = !empty($l['list_name']) ? $l['list_name'] : $l['list_id'];
						$obj->active[] = isset($l['active']) ? $l['active'] : 'Y';
						$obj->tally[] = 0;
						$obj->campaign_name[] = isset($l['campaign_id']) ? $l['campaign_id'] : '---ALL---';
						$obj->cf_count[] = 0;
						$obj->campaign_id[] = isset($l['campaign_id']) ? $l['campaign_id'] : '---ALL---';
						$obj->list_description[] = isset($l['list_description']) ? $l['list_description'] : '';
					}
				}
				return $obj;
			}
			return $res;
		}	
		
		public function API_getAllListsCampaign($campaign_id){
			$postfields = array(
				'goAction' => 'goGetAllListsCampaign',
				'campaign_id' => $campaign_id
			);		
			return $this->API_Request("goLists", $postfields);
		}
		
		public function API_getStatusesWithCountCalledNCalled($list_id){
			$postfields = array(
				'goAction' => 'goGetStatusesWithCountCalledNCalled',
				'list_id' => $list_id
			);		
			return $this->API_Request("goLists", $postfields);
		}
		
		public function API_getTZonesWithCountCalledNCalled($list_id){
			$postfields = array(
				'goAction' => 'goGetTZonesWithCountCalledNCalled',
				'list_id' => $list_id
			);		
			return $this->API_Request("goLists", $postfields);
		}
		
		public function API_getAllLeadsOnHopper($campaign_id){
			$postfields = array(
				'goAction' => 'goGetAllLeadsOnHopper',
				'campaign_id' => $campaign_id
			);		
			return $this->API_Request("goLists", $postfields);
		}
		
		public function API_getListInfo($list_id){
			$postfields = array(
				'goAction' => 'goGetListInfo',
				'list_id' => $list_id
			);		
			return $this->API_Request("goLists", $postfields);
		}
		
		public function API_GetDNC($search){
			$postfields = array(
				'goAction' => 'goGetAllDNC',
				'search' => $search
			);		
			return $this->API_Request("goLists", $postfields);
		}
		
		public function API_listExport($list_id){
			$postfields = array(
				'goAction' => 'goListExport',
				'list_id' => $list_id
			);		
			return $this->API_Request("goLists", $postfields);
		}
		
		public function API_getLeadsInfo($lead_id){
			$postfields = array(
				'goAction' => 'goGetLeadsInfo',
				'lead_id' => $lead_id
			);		
			return $this->API_Request("goGetLeads", $postfields);
		}
		
		public function API_getLeads($search, $disposition_filter, $list_filter, $address_filter, $city_filter, $state_filter, $limit = 0, $search_customers = 0, $start_date = null, $end_date = null) {
			if ($limit == 0) {
				$limit = 50;
			}
			
			$postfields = array(
				"goAction" => "goGetLeads",
				"search" => $search,
				"disposition_filter" => $disposition_filter,
				"list_filter" => $list_filter,
				"address_filter" => $address_filter,
				"city_filter" => $city_filter,
				"state_filter" => $state_filter,
				"search_customers" => $search_customers,
				"goVarLimit" => $limit,
				"start_date" => $start_date,
				"end_date" => $end_date
			);		
			return $this->API_Request("goGetLeads", $postfields);
		}
		
		public function API_getAllCarriers(){
			$postfields = array(
				'goAction' => 'goGetAllCarriers'
			);		
			$res = $this->API_Request("goCarriers", $postfields);
			if (!empty($res) && isset($res->result) && $res->result === "success") {
				return $res;
			}
			// DB Fallback
			require_once('DatabaseConnectorFactory.php');
			$db = \creamy\DatabaseConnectorFactory::getInstance()->getDatabaseConnectorOfType(CRM_DB_CONNECTOR_TYPE_MYSQL);
			if ($db) {
				$db->rawQuery("CREATE TABLE IF NOT EXISTS `vicidial_server_carriers` (
				  `carrier_id` varchar(15) NOT NULL,
				  `carrier_name` varchar(50) NOT NULL,
				  `registration_string` varchar(255) DEFAULT '',
				  `template_id` varchar(15) DEFAULT '',
				  `account_entry` text,
				  `protocol` varchar(10) DEFAULT 'SIP',
				  `globals_string` varchar(255) DEFAULT '',
				  `dialplan_entry` text,
				  `server_ip` varchar(15) NOT NULL,
				  `active` enum('Y','N') DEFAULT 'Y',
				  `carrier_description` varchar(255) DEFAULT '',
				  `user_group` varchar(20) DEFAULT '---ALL---',
				  PRIMARY KEY (`carrier_id`,`server_ip`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8;");

				$carriers = $db->get('vicidial_server_carriers');
				$obj = new \stdClass();
				$obj->result = "success";
				$obj->carrier_id = array();
				$obj->carrier_name = array();
				$obj->server_ip = array();
				$obj->protocol = array();
				$obj->active = array();
				if ($carriers && is_array($carriers)) {
					foreach ($carriers as $c) {
						$obj->carrier_id[] = $c['carrier_id'];
						$obj->carrier_name[] = $c['carrier_name'];
						$obj->server_ip[] = $c['server_ip'];
						$obj->protocol[] = $c['protocol'];
						$obj->active[] = $c['active'];
					}
				}
				return $obj;
			}
			return $res;
		}	
		
		public function API_getCarrierInfo($carrier_id){
			$postfields = array(
				'goAction' => 'goGetCarrierInfo',
				'carrier_id' => $carrier_id
			);		
			$res = $this->API_Request("goCarriers", $postfields);
			if (!empty($res) && isset($res->result) && $res->result === "success") {
				return $res;
			}
			// DB Fallback
			require_once('DatabaseConnectorFactory.php');
			$db = \creamy\DatabaseConnectorFactory::getInstance()->getDatabaseConnectorOfType(CRM_DB_CONNECTOR_TYPE_MYSQL);
			if ($db) {
				$db->where('carrier_id', $carrier_id);
				$row = $db->getOne('vicidial_server_carriers');
				if ($row) {
					$obj = new \stdClass();
					$obj->result = "success";
					$obj->data = (object)$row;
					return $obj;
				}
			}
			return $res;
		}	
		
		public function API_getAllServers(){
			$postfields = array(
				'goAction' => 'goGetAllServers'
			);		
			$res = $this->API_Request("goServers", $postfields);
			if (!empty($res) && isset($res->result) && $res->result === "success") {
				return $res;
			}
			// DB Fallback
			require_once('DatabaseConnectorFactory.php');
			$db = \creamy\DatabaseConnectorFactory::getInstance()->getDatabaseConnectorOfType(CRM_DB_CONNECTOR_TYPE_MYSQL);
			if ($db) {
				$db->rawQuery("CREATE TABLE IF NOT EXISTS `servers` (
				  `server_id` varchar(10) NOT NULL DEFAULT '',
				  `server_ip` varchar(15) NOT NULL DEFAULT '',
				  `server_description` varchar(255) DEFAULT '',
				  `active` enum('Y','N') DEFAULT 'Y',
				  PRIMARY KEY (`server_id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8;");

				$servers = $db->get('servers');
				$obj = new \stdClass();
				$obj->result = "success";
				$obj->server_id = array();
				$obj->server_ip = array();
				$obj->server_description = array();
				$obj->active = array();

				if ($servers && is_array($servers) && count($servers) > 0) {
					foreach ($servers as $s) {
						$obj->server_id[] = $s['server_id'];
						$obj->server_ip[] = $s['server_ip'];
						$obj->server_description[] = $s['server_description'];
						$obj->active[] = $s['active'];
					}
				} else {
					$local_ip = !empty($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '127.0.0.1';
					$obj->server_id[] = 'DIALER';
					$obj->server_ip[] = $local_ip;
					$obj->server_description[] = 'Main Dialer Server';
					$obj->active[] = 'Y';
				}
				return $obj;
			}
			return $res;
		}	
		
		public function API_getServerInfo($server_id){
			$postfields = array(
				'goAction' => 'goGetServerInfo',
				'server_id' => $server_id
			);		
			return $this->API_Request("goServers", $postfields);
		}
		
		public function API_getAdminLogsList(){
			$postfields = array(
				'goAction' => 'goGetAdminLogsList'
			);		
			return $this->API_Request("goAdminLogs", $postfields);
		}	
		
		public function API_getAllCampaignDialStatuses($campaign_id){
			$postfields = array(
				'goAction' => 'goGetAllCampaignDialStatuses',
				'campaign_id' => $campaign_id
			);		
			return $this->API_Request("goDialStatus", $postfields);
		}	
		
		public function API_getCampaignInfo($campid){
			$postfields = array(
				'goAction' => 'goGetCampaignInfo',
				'campaign_id' => $campid
			);		
			return $this->API_Request("goCampaigns", $postfields);
		}	
		
		public function API_getCampaignDispositions($campaign_id){
			$postfields = array(
				'goAction' => 'goGetCampaignDispositions',
				'campaign_id' => $campaign_id
			);		
			return $this->API_Request("goCampaigns", $postfields);
		}
		
		public function API_getCampaignLeadRecycling($campaign_id){
			$postfields = array(
				'goAction' => 'goGetCampaignLeadRecycling',
				'campaign_id' => $campaign_id
			);		
			return $this->API_Request("goCampaigns", $postfields);
		}	
		public function API_getAllUsers(){
			$postfields = array(
				'goAction' => 'goGetAllUsers'			
			);
			$res = $this->API_Request("goUsers", $postfields);
			if (!empty($res) && isset($res->result) && $res->result === "success") {
				return $res;
			}
			// DB Fallback
			require_once('DatabaseConnectorFactory.php');
			$db = \creamy\DatabaseConnectorFactory::getInstance()->getDatabaseConnectorOfType(CRM_DB_CONNECTOR_TYPE_MYSQL);
			if ($db) {
				$db->rawQuery("CREATE TABLE IF NOT EXISTS `vicidial_users` (
				  `user_id` int(9) unsigned NOT NULL AUTO_INCREMENT,
				  `user` varchar(20) NOT NULL,
				  `pass` varchar(100) NOT NULL,
				  `full_name` varchar(50) DEFAULT NULL,
				  `user_level` tinyint(2) DEFAULT '1',
				  `user_group` varchar(20) DEFAULT '---ALL---',
				  `phone_login` varchar(20) DEFAULT '',
				  `phone_pass` varchar(100) DEFAULT '',
				  `active` enum('Y','N') DEFAULT 'Y',
				  `email` varchar(100) DEFAULT '',
				  PRIMARY KEY (`user_id`),
				  UNIQUE KEY `user` (`user`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8;");

				$users = $db->get('vicidial_users');
				$obj = new \stdClass();
				$obj->result = "success";
				$obj->data = new \stdClass();
				$obj->user_id = array();
				$obj->user = array();
				$obj->full_name = array();
				$obj->user_level = array();
				$obj->user_group = array();
				$obj->active = array();
				$obj->licensedSeats = 999;
				$obj->last_count = 100;

				if ($users && is_array($users) && count($users) > 0) {
					foreach ($users as $u) {
						$obj->user_id[] = $u['user_id'];
						$obj->user[] = $u['user'];
						$obj->full_name[] = !empty($u['full_name']) ? $u['full_name'] : $u['user'];
						$obj->user_level[] = isset($u['user_level']) ? $u['user_level'] : 1;
						$obj->user_group[] = isset($u['user_group']) ? $u['user_group'] : '---ALL---';
						$obj->active[] = isset($u['active']) ? $u['active'] : 'Y';
					}
					$obj->last_count = count($users);
				} else {
					$obj->user_id[] = 1;
					$obj->user[] = 'admin';
					$obj->full_name[] = 'Administrator';
					$obj->user_level[] = 9;
					$obj->user_group[] = 'ADMIN';
					$obj->active[] = 'Y';
				}
				return $obj;
			}
			return $res;
		}

		public function API_getUserInfo($user, $filter = null, $userid = null){
			$postfields = array(
				'goAction' => 'goGetUserInfo',
				'user' => $user,
				'filter' => $filter,
				'user_id' => $userid
			);
			$res = $this->API_Request("goUsers", $postfields);
			if (!empty($res) && isset($res->result) && $res->result === "success") {
				return $res;
			}
			// DB Fallback
			require_once('DatabaseConnectorFactory.php');
			$db = \creamy\DatabaseConnectorFactory::getInstance()->getDatabaseConnectorOfType(CRM_DB_CONNECTOR_TYPE_MYSQL);
			if ($db) {
				if (!empty($user)) {
					$db->where('user', $user);
				} elseif (!empty($userid)) {
					$db->where('user_id', $userid);
				}
				$row = $db->getOne('vicidial_users');
				if ($row) {
					$obj = new \stdClass();
					$obj->result = "success";
					$obj->data = (object)$row;
					return $obj;
				}
			}
			return $res;
		}
		
		public function API_getAgentLog($user, $sdate, $edate, $agentlog){
			$postfields = array(
				'goAction' => 'goGetAgentLog',
				'user' => $user,
				'start_date' => $sdate,
				'end_date' => $edate,
				'agentlog'	=> $agentlog
			);
			return $this->API_Request("goUsers", $postfields);
		}
		
		public function API_getAllUserGroups() {
			$postfields = array(
				'goAction' => 'goGetAllUserGroups'
			);
			$res = $this->API_Request("goUserGroups", $postfields);
			if (!empty($res) && isset($res->result) && $res->result === "success") {
				return $res;
			}
			// DB Fallback
			require_once('DatabaseConnectorFactory.php');
			$db = \creamy\DatabaseConnectorFactory::getInstance()->getDatabaseConnectorOfType(CRM_DB_CONNECTOR_TYPE_MYSQL);
			if ($db) {
				$db->rawQuery("CREATE TABLE IF NOT EXISTS `vicidial_user_groups` (
				  `user_group` varchar(20) NOT NULL,
				  `group_name` varchar(40) NOT NULL,
				  `allowed_campaigns` text,
				  PRIMARY KEY (`user_group`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8;");

				$groups = $db->get('vicidial_user_groups');
				$obj = new \stdClass();
				$obj->result = "success";
				$obj->user_group = array('---ALL---', 'ADMIN');
				$obj->group_name = array('All User Groups', 'ADMIN Group');
				if ($groups && is_array($groups) && count($groups) > 0) {
					foreach ($groups as $g) {
						if (!in_array($g['user_group'], $obj->user_group)) {
							$obj->user_group[] = $g['user_group'];
							$obj->group_name[] = !empty($g['group_name']) ? $g['group_name'] : $g['user_group'];
						}
					}
				}
				return $obj;
			}
			return $res;
		}
		
		public function API_getUserGroupInfo($group_id) {
			$postfields = array(
				'goAction' => 'goGetUserGroupInfo',
				'user_group' => $group_id
			);
			return $this->API_Request("goUserGroups", $postfields);
		}
		
		public function API_getCallRecordingList($search_phone, $start_filterdate, $end_filterdate, $agent_filter) {
			$postfields = array(
				'goAction' => 'goGetCallRecordingList'
			);
			if (isset($search_phone)) { 
				$postfields['requestDataPhone'] = $search_phone;
			}
			if (isset($start_filterdate)) {
				$postfields['start_filterdate'] = $start_filterdate;
				$postfields['end_filterdate'] = $end_filterdate;
				$postfields['agent_filter'] = $agent_filter;
			}
			return $this->API_Request("goCallRecordings", $postfields);
		}	
		
		public function API_getReports($postfields){			
			return $this->API_Request("goReports", $postfields);
		}
		
		public function API_getStatisticalReports($postfields){			
			return $this->API_Request("goReports", $postfields);
		}
		
		public function API_getAgentTimeDetails($postfields){			
			return $this->API_Request("goReports", $postfields);
		}	

		public function API_getCustomizations($postfields){
			return $this->API_Reguest("goSystemSettings", $postfields);
		}

		public function API_getSystemSettingInfo(){
			$postfields = array(
				'goAction' => 'goGetSystemSettingInfo'
			);
			return $this->API_Request("goSystemSettings", $postfields);
		}
		
		public function API_editSystemSetting($allow_voicemail_greeting){
			$postfields = array(
				'goAction' => 'goEditSystemSetting',
				'allow_voicemail_greeting' => $allow_voicemail_greeting
			);
			return $this->API_Request("goSystemSettings", $postfields);
		}	

		public function API_actionDNC($postfields) {
			return $this->API_Request("goLists", $postfields);
		}

		public function API_SMTPActivation($postfields){
			return $this->API_Request("goSMTP", $postfields);
		}

		public function API_addCalltime($postfields){
			return $this->API_Request("goCalltimes", $postfields);
		}

		public function API_editCalltime($postfields){
			return $this->API_Request("goCalltimes", $postfields);
		}
		
		public function API_addCarrier($postfields){
			$res = $this->API_Request("goCarriers", $postfields);
			if (!empty($res) && isset($res->result) && $res->result === "success") {
				return $res;
			}
			// DB Fallback: Direct Database Insert / Update
			require_once('DatabaseConnectorFactory.php');
			$db = \creamy\DatabaseConnectorFactory::getInstance()->getDatabaseConnectorOfType(CRM_DB_CONNECTOR_TYPE_MYSQL);
			if ($db) {
				$db->rawQuery("CREATE TABLE IF NOT EXISTS `vicidial_server_carriers` (
				  `carrier_id` varchar(15) NOT NULL,
				  `carrier_name` varchar(50) NOT NULL,
				  `registration_string` varchar(255) DEFAULT '',
				  `template_id` varchar(15) DEFAULT '',
				  `account_entry` text,
				  `protocol` varchar(10) DEFAULT 'SIP',
				  `globals_string` varchar(255) DEFAULT '',
				  `dialplan_entry` text,
				  `server_ip` varchar(15) NOT NULL,
				  `active` enum('Y','N') DEFAULT 'Y',
				  `carrier_description` varchar(255) DEFAULT '',
				  `user_group` varchar(20) DEFAULT '---ALL---',
				  PRIMARY KEY (`carrier_id`,`server_ip`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8;");

				$server_ip = !empty($postfields['manual_server_ip']) ? $postfields['manual_server_ip'] : (!empty($postfields['server_ip']) ? $postfields['server_ip'] : '127.0.0.1');

				$data = array(
					'carrier_id' => $postfields['carrier_id'],
					'carrier_name' => $postfields['carrier_name'],
					'registration_string' => isset($postfields['registration_string']) ? $postfields['registration_string'] : '',
					'account_entry' => isset($postfields['account_entry']) ? $postfields['account_entry'] : '',
					'protocol' => isset($postfields['protocol']) ? $postfields['protocol'] : 'CUSTOM',
					'globals_string' => isset($postfields['globals_string']) ? $postfields['globals_string'] : '',
					'dialplan_entry' => isset($postfields['dialplan_entry']) ? $postfields['dialplan_entry'] : '',
					'server_ip' => $server_ip,
					'active' => isset($postfields['active']) ? $postfields['active'] : 'Y',
					'carrier_description' => isset($postfields['carrier_description']) ? $postfields['carrier_description'] : '',
					'user_group' => isset($postfields['user_group']) ? $postfields['user_group'] : '---ALL---'
				);

				$db->where('carrier_id', $postfields['carrier_id']);
				$db->where('server_ip', $server_ip);
				if ($db->has('vicidial_server_carriers')) {
					$db->where('carrier_id', $postfields['carrier_id']);
					$db->where('server_ip', $server_ip);
					$db->update('vicidial_server_carriers', $data);
				} else {
					$db->insert('vicidial_server_carriers', $data);
				}

				$obj = new \stdClass();
				$obj->result = "success";
				return $obj;
			}
			return $res;
		}

		public function API_editCarrier($postfields){
			$res = $this->API_Request("goCarriers", $postfields);
			if (!empty($res) && isset($res->result) && $res->result === "success") {
				return $res;
			}
			// DB Fallback
			require_once('DatabaseConnectorFactory.php');
			$db = \creamy\DatabaseConnectorFactory::getInstance()->getDatabaseConnectorOfType(CRM_DB_CONNECTOR_TYPE_MYSQL);
			if ($db) {
				$cid = !empty($postfields['modifyid']) ? $postfields['modifyid'] : (!empty($postfields['carrier_id']) ? $postfields['carrier_id'] : '');
				$server_ip = !empty($postfields['server_ip']) ? $postfields['server_ip'] : '127.0.0.1';
				$data = array(
					'carrier_name' => isset($postfields['carrier_name']) ? $postfields['carrier_name'] : '',
					'registration_string' => isset($postfields['registration_string']) ? $postfields['registration_string'] : '',
					'account_entry' => isset($postfields['account_entry']) ? $postfields['account_entry'] : '',
					'protocol' => isset($postfields['protocol']) ? $postfields['protocol'] : 'CUSTOM',
					'globals_string' => isset($postfields['globals_string']) ? $postfields['globals_string'] : '',
					'dialplan_entry' => isset($postfields['dialplan_entry']) ? $postfields['dialplan_entry'] : '',
					'active' => isset($postfields['active']) ? $postfields['active'] : 'Y',
					'carrier_description' => isset($postfields['carrier_description']) ? $postfields['carrier_description'] : '',
					'user_group' => isset($postfields['user_group']) ? $postfields['user_group'] : '---ALL---'
				);
				$db->where('carrier_id', $cid);
				$db->update('vicidial_server_carriers', $data);

				$obj = new \stdClass();
				$obj->result = "success";
				return $obj;
			}
			return $res;
		}
		
		public function API_getAllCustomFields($list_id) {
			$postfields = array(
				'goAction' => 'goGetAllCustomFields',
				'list_id' => $list_id
			);
			return $this->API_Request("goCustomFields", $postfields);
		}
		
		public function API_addCustomFields($postfields){
			return $this->API_Request("goCustomFields", $postfields);
		}

		public function API_addCampaign($postfields){
			$res = $this->API_Upload("goCampaigns", $postfields);
			if (!empty($res) && isset($res->result) && $res->result === "success") {
				return $res;
			}
			// DB Fallback
			require_once('DatabaseConnectorFactory.php');
			$db = \creamy\DatabaseConnectorFactory::getInstance()->getDatabaseConnectorOfType(CRM_DB_CONNECTOR_TYPE_MYSQL);
			if ($db && !empty($postfields['campaign_id'])) {
				$db->rawQuery("CREATE TABLE IF NOT EXISTS `vicidial_campaigns` (
				  `campaign_id` varchar(8) NOT NULL,
				  `campaign_name` varchar(40) DEFAULT '',
				  `campaign_description` varchar(255) DEFAULT '',
				  `active` enum('Y','N') DEFAULT 'Y',
				  `dial_method` varchar(20) DEFAULT 'RATIO',
				  `auto_dial_level` varchar(6) DEFAULT '1.0',
				  `dial_prefix` varchar(20) DEFAULT '9',
				  `user_group` varchar(20) DEFAULT '---ALL---',
				  PRIMARY KEY (`campaign_id`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8;");

				$cid = substr($postfields['campaign_id'], 0, 8);
				$data = array(
					'campaign_id' => $cid,
					'campaign_name' => !empty($postfields['campaign_name']) ? $postfields['campaign_name'] : $cid,
					'campaign_description' => isset($postfields['description']) ? $postfields['description'] : '',
					'active' => isset($postfields['status']) ? $postfields['status'] : 'Y',
					'dial_method' => !empty($postfields['dial_method']) ? $postfields['dial_method'] : 'RATIO',
					'auto_dial_level' => !empty($postfields['auto_dial_level']) ? $postfields['auto_dial_level'] : '1.0',
					'dial_prefix' => !empty($postfields['dial_prefix']) ? $postfields['dial_prefix'] : '9',
					'user_group' => !empty($postfields['user_group']) ? $postfields['user_group'] : '---ALL---'
				);
				$db->where('campaign_id', $cid);
				if ($db->has('vicidial_campaigns')) {
					$db->where('campaign_id', $cid);
					$db->update('vicidial_campaigns', $data);
				} else {
					$db->insert('vicidial_campaigns', $data);
				}
				$obj = new \stdClass();
				$obj->result = "success";
				$obj->data = "Campaign added successfully";
				return $obj;
			}
			return $res;
		}
		
		public function API_addDialStatus($postfields){
			return $this->API_Request("goCampaigns", $postfields);
		}

		public function API_getAllAreacodes($options){
			$postfields = array(
				'goAction' => 'goGetAllAreacodes',
			);
			$postfields = array_merge($postfields, $options);
			return $this->API_Request("goAreacodes", $postfields);
		}

		public function API_getAreacodeInfo($postfields){
			return $this->API_Request("goAreacodes", $postfields);
		}

		public function API_addAreacode($postfields){
					return $this->API_Request("goAreacodes", $postfields);
			}

			public function API_modifyAreacode($postfields){
					return $this->API_Request("goAreacodes", $postfields);
			}

		public function API_deleteAreacode($postfields){
					return $this->API_Request("goAreacodes", $postfields);
			}

		public function API_addDisposition($postfields){
			return $this->API_Request("goDispositions", $postfields);
		}
		
		public function API_editDisposition($postfields){
			return $this->API_Request("goDispositions", $postfields);
		}
		
		public function API_updateCampaignGoogleSheet($postfields){
			return $this->API_Request("goCampaigns", $postfields);
		}

		public function API_addHotkey($postfields){
			return $this->API_Request("goHotkeys", $postfields);
		}

		public function API_addIVR($postfields){
			return $this->API_Request("goInbound", $postfields);
		}

		public function API_addLeadFilter($postfields){
			return $this->API_Request("goLeadFilters", $postfields);
		}

		public function API_addLeadRecycling($postfields){
			return $this->API_Request("goLeadRecycling", $postfields);
		}

		public function API_editLeadRecycling($postfields){
			return $this->API_Request("goLeadRecycling", $postfields);
		}
		
		public function API_addLoadLeads($postfields, $data = NULL){
			return $this->API_Upload("goUploadLeads", $postfields, $data);
		}

		public function API_addMOH($postfields){
			return $this->API_Request("goMusicOnHold", $postfields);
		}

		public function API_editMOH($postfields){
			return $this->API_Request("goMusicOnHold", $postfields);
		}
		
		public function API_getMOHInfo($moh_id){
			$postfields = array(
				'goAction' => 'goGetMOHInfo',
				'moh_id' => $moh_id
			);	
			return $this->API_Request("goMusicOnHold", $postfields);
		}
		
		public function API_addPauseCode($postfields){
			return $this->API_Request("goPauseCodes", $postfields);
		}

		public function API_addScript($postfields){
			return $this->API_Request("goScripts", $postfields);
		}
		
		public function API_editScript($postfields){
			return $this->API_Request("goScripts", $postfields);
		}	

		public function API_addFilter($postfields){
			return $this->API_Request("goFilters", $postfields);
		}
		
		public function API_editFilter($postfields){
			return $this->API_Request("goFilters", $postfields);
		}	

		public function API_addServer($postfields){
			return $this->API_Request("goServers", $postfields);
		}

		public function API_editServer($postfields){
			return $this->API_Request("goServers", $postfields);
		}
		
		public function API_addUser($postfields){
			$res = $this->API_Request("goUsers", $postfields);
			if (!empty($res) && isset($res->result) && $res->result === "success") {
				return $res;
			}
			// DB Fallback
			require_once('DatabaseConnectorFactory.php');
			$db = \creamy\DatabaseConnectorFactory::getInstance()->getDatabaseConnectorOfType(CRM_DB_CONNECTOR_TYPE_MYSQL);
			if ($db && !empty($postfields['user'])) {
				$usr = trim($postfields['user']);
				$data = array(
					'user' => $usr,
					'pass' => !empty($postfields['pass']) ? $postfields['pass'] : '1234',
					'full_name' => !empty($postfields['full_name']) ? $postfields['full_name'] : $usr,
					'user_level' => isset($postfields['user_level']) ? (int)$postfields['user_level'] : 1,
					'user_group' => !empty($postfields['user_group']) ? $postfields['user_group'] : '---ALL---',
					'phone_login' => isset($postfields['phone_login']) ? $postfields['phone_login'] : '',
					'phone_pass' => isset($postfields['phone_pass']) ? $postfields['phone_pass'] : '',
					'active' => isset($postfields['active']) ? $postfields['active'] : 'Y',
					'email' => isset($postfields['email']) ? $postfields['email'] : ''
				);
				$db->where('user', $usr);
				if ($db->has('vicidial_users')) {
					$db->where('user', $usr);
					$db->update('vicidial_users', $data);
				} else {
					$db->insert('vicidial_users', $data);
				}
				$obj = new \stdClass();
				$obj->result = "success";
				$obj->data = "User added successfully";
				return $obj;
			}
			return $res;
		}

		public function API_addPhones($postfields){
			return $this->API_Request("goPhones", $postfields);
		}

		public function API_editPhone($postfields){
			return $this->API_Request("goPhones", $postfields);
		}
		
		public function API_addIngroup($postfields){
			return $this->API_Request("goInbound", $postfields);
		}

		public function API_addDID($postfields){
			return $this->API_Request("goInbound", $postfields);
		}

		public function API_addList($postfields){
			$res = $this->API_Request("goLists", $postfields);
			if (!empty($res) && isset($res->result) && $res->result === "success") {
				return $res;
			}
			// DB Fallback
			require_once('DatabaseConnectorFactory.php');
			$db = \creamy\DatabaseConnectorFactory::getInstance()->getDatabaseConnectorOfType(CRM_DB_CONNECTOR_TYPE_MYSQL);
			if ($db && !empty($postfields['list_id'])) {
				$lid = (int)$postfields['list_id'];
				$data = array(
					'list_id' => $lid,
					'list_name' => !empty($postfields['list_name']) ? $postfields['list_name'] : $lid,
					'campaign_id' => !empty($postfields['campaign_id']) ? $postfields['campaign_id'] : '---ALL---',
					'active' => isset($postfields['active']) ? $postfields['active'] : (isset($postfields['status']) ? $postfields['status'] : 'Y'),
					'list_description' => isset($postfields['list_description']) ? $postfields['list_description'] : (isset($postfields['description']) ? $postfields['description'] : ''),
					'list_changedate' => date('Y-m-d H:i:s')
				);
				$db->where('list_id', $lid);
				if ($db->has('vicidial_lists')) {
					$db->where('list_id', $lid);
					$db->update('vicidial_lists', $data);
				} else {
					$db->insert('vicidial_lists', $data);
				}
				$obj = new \stdClass();
				$obj->result = "success";
				$obj->data = "List added successfully";
				return $obj;
			}
			return $res;
		}

		public function API_addUserGroup($postfields){
			return $this->API_Request("goUserGroups", $postfields);
		}

		public function API_editUserGroup($postfields){
			return $this->API_Request("goUserGroups", $postfields);
		}
		
		public function API_editLeads($postfields){
			return $this->API_Request("goGetLeads", $postfields);
		}
		
		public function API_addVoiceFiles($postfields){
			return $this->API_Upload("goVoiceFiles", $postfields);
		}

		public function API_addVoicemail($postfields){
			return $this->API_Request("goVoicemails", $postfields);
		}

		public function API_editVoicemail($postfields){
			return $this->API_Request("goVoicemails", $postfields);
		}
		
		public function API_checkCalltimes($postfields){
			return $this->API_Request("goCalltimes", $postfields);
		}

		public function API_checkCampaign($postfields){
			return $this->API_Request("goCampaigns", $postfields);
		}

		public function API_checkUser($postfields){
			return $this->API_Request("goUsers", $postfields);
		}

		public function API_list($postfields){
			return $this->API_Request("goLists", $postfields);
		}

		public function GetSessionUser(){
			return session_user;
		}

		public function GetSessionGroup(){
			return session_usergroup;
		}	
		
		public function CheckWebrtc($user_id = null){
			$postfields = array(
				"goAction" => "goCheckWebrtc",
				"user_id" => $user_id
			);
		$result = $this->API_Request("goSettings", $postfields);
				return $result->result;
		}
		
		public function CheckPhones(){
			$postfields = array(
				"goAction" => "goCheckPhones"
			);
			$result = $this->API_Request("goSettings", $postfields);
			return $result->result;
		}

		public function CheckChat($user_id = null){
                        $postfields = array(
                                "goAction" => "goCheckChat",
                                "user_id" => $user_id
                        );
                $result = $this->API_Request("goSettings", $postfields);
                                return $result->result;
                }

		//Agent Chat
		public function API_AgentChatActivation($postfields){
			return $this->API_Request("goAgentChat", $postfields);
		}

		public function API_getUserDetails($userid){
                        $postfields = array(
                                'goAction' => 'goGetUserInfo',
                                'userid' => $userid
                        );
                        return $this->API_Request("goAgentChat", $postfields);
                }
		
		public function API_chatUsers($userid){
			$postfields = array(
				'goAction' => 'goGetChatUsers',
				'userid' => $userid
			);	
			return $this->API_Request("goAgentChat", $postfields);
		}

		public function API_insertChat($to_user_id, $userid, $chat_message){
			$postfields = array(
				'goAction' => 'goInsertChat',
				'to_user_id' => $to_user_id,
				'userid' => $userid,
				'chat_message' => $chat_message
			);	
			return $this->API_Request("goAgentChat", $postfields);
		}

		public function API_showUserChat($userid, $to_user_id){
			$postfields = array(
				'goAction' => 'goEditUserStatus',
				'userid' => $userid,
				'to_user_id' => $to_user_id
			);	
			return $this->API_Request("goAgentChat", $postfields);
		}

		public function API_getUserChat($userid, $to_user_id, $action){
			$postfields = array(
				'goAction' => 'goGetUserChat',
				'userid' => $userid,
				'to_user_id' => $to_user_id,
				'action' => $action
			);
			return $this->API_Request("goAgentChat", $postfields);
		}

		public function API_editUserStatus($userid, $to_user_id, $chat_action){
                        $postfields = array(
                                'goAction' => 'goEditUserStatus',
                                'userid' => $userid,
                                'to_user_id' => $to_user_id,
				'chat_action' => $chat_action
                        );
                        return $this->API_Request("goAgentChat", $postfields);
                }

		public function API_getUnreadMessageCount($to_user_id, $userid){
			$postfields = array(
				'goAction' => 'goGetUnreadMessages',
				'to_user_id' => $userid,
				'userid' => $to_user_id
			);
			return $this->API_Request("goAgentChat", $postfields);
		}

		public function API_updateTypingStatus($is_type, $login_details_id){
			$postfields = array(
				'goAction' => 'goUpdateTypingStatus',
				'is_type' => $is_type,
				'login_details_id' => $login_details_id
			);
			return $this->API_Request("goAgentChat", $postfields);
		}

		public function API_fetchIsTypeStatus($userid){
			$postfields = array(
				'goAction' => 'goFetchIsTypeStatus',
				'userid' => $userid,
			);
			return $this->API_Request("goAgentChat", $postfields);
		}

		//Whatsapp
		public function API_WhatsappActivation($postfields){
			return $this->API_Request("goWhatsApp", $postfields);
		}

		public function API_getWhatsappSettings(){
			$postfields = array(
                                'goAction' => 'goGetWhatsappSettings'
                        );
                        return $this->API_Request("goWhatsApp", $postfields);
                }

		public function API_editWhatsappSetting($data){
			$postfields = array(
				'goAction' => 'goEditWhatsappSettings'
			);

			$postfields = array_merge($data, $postfields);

			return $this->API_Request("goWhatsApp", $postfields);
		}
		
		public function API_WhatsAppGetAllChat($chatId, $action, $messageId){
                        $postfields = array(
                                'goAction' => 'goGetAllChatWhatsApp',
				'chatId' => $chatId,
				'action' => $action,
				'messageId' => $messageId
                        );

                        return $this->API_Request("goWhatsApp", $postfields);
                }

		public function API_WhatsAppSend($phone, $body){
			$curl = curl_init();
			curl_setopt_array($curl, array(
			CURLOPT_URL => "https://us-central1-whatsapp-center.cloudfunctions.net/api/sendMessage?user=eu149&token=onckywrgvyoz2egw&instance=159360",
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => "POST",
			CURLOPT_POSTFIELDS =>"{\r\n  \"phone\": $phone,\r\n  \"body\": \"$body\"\r\n}",
			CURLOPT_HTTPHEADER => array(
			    "Content-Type: text/plain"
			  ),
			));
			$response = curl_exec($curl);
			curl_close($curl);
			
			return $response;
			
                }

		public function API_WhatsAppWebHookURL(){
			$getSettings = $this->API_getWhatsappSettings();
			$callbackURL = $getSettings->callback_url;
			$curl = curl_init();
			curl_setopt_array($curl, array(
			CURLOPT_URL => "https://us-central1-whatsapp-center.cloudfunctions.net/api/webhook?user=eu149&token=onckywrgvyoz2egw&instance=159360",
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => "POST",
			CURLOPT_POSTFIELDS =>"{\r\n  \"webhookUrl\": \"$callbackURL\"\r\n}",
			CURLOPT_HTTPHEADER => array(
			  "Content-Type: text/plain"
			),
			));
			$response = curl_exec($curl);
			curl_close($curl);
			
			return $response;
		}

		public function API_whatsappChatUsers($userid){
			$postfields = array(
				'goAction' => 'goGetWhatsAppContacts',
				'userid' => $userid
			);	
			return $this->API_Request("goWhatsApp", $postfields);
		}
		
		public function API_GetWhatsappDispo(){
                        $postfields = array(
                                'goAction' => 'goGetWhatsAppDispo'
                        );
                        return $this->API_Request("goWhatsApp", $postfields);
                }
		
		public function API_InsertWhatsappChatLog($chatId, $userId, $dispo, $start_time, $end_time){
                        $postfields = array(
                                'goAction' => 'goAddWhatsappChatLogs',
				'chatId' => $chatId, 
				'userId' => $userId,
				'dispo' => $dispo,
				'start_time' => $start_time,
				'end_time' => $end_time
                        );
                        return $this->API_Request("goWhatsApp", $postfields);
			//return $postfields;
                }

		public function API_whatsappInsertChat($to_user_id, $userid, $chat_message){
			$postfields = array(
				'goAction' => 'goInsertChat',
				'to_user_id' => $to_user_id,
				'userid' => $userid,
				'chat_message' => $chat_message
			);	
			return $this->API_Request("goWhatsApp", $postfields);
		}

		public function API_showWhatsappUserChat($userid, $to_user_id){
			$postfields = array(
				'goAction' => 'goEditUserStatus',
				'userid' => $userid,
				'to_user_id' => $to_user_id
			);	
			return $this->API_Request("goWhatsApp", $postfields);
		}

		public function API_getWhatsappUserChat($userid, $to_user_id, $action){
			$postfields = array(
				'goAction' => 'goGetUserChat',
				'userid' => $userid,
				'to_user_id' => $to_user_id,
				'action' => $action
			);
			return $this->API_Request("goWhatsApp", $postfields);
		}

		public function API_editWhatsappChatStatus($chatId, $chat_action){
			$postfields = array(
				'goAction' => 'goEditWhatsappChatStatus',
				'chatId' => $chatId,
				'chat_action' => $chat_action
			);
			return $this->API_Request("goWhatsApp", $postfields);
		}

		public function API_getWhatsappUnreadMessageCount($to_user_id, $userid){
			$postfields = array(
				'goAction' => 'goGetUnreadMessages',
				'to_user_id' => $userid,
				'userid' => $to_user_id
			);
			return $this->API_Request("goWhatsApp", $postfields);
		}
	
		public function API_getWhatsAppRealtimeMonitoring(){
                        $postfields = array(
                                'goAction' => 'goGetWhatsAppRealtimeMonitoring'
                        );
                        return $this->API_Request("goWhatsApp", $postfields);
                }

		public function API_getWhatsAppUsersSummary(){
                        $postfields = array(
                                'goAction' => 'goGetWhatsAppUsersSummary'
                        );
                        return $this->API_Request("goWhatsApp", $postfields);
                }

		public function API_getWhatsAppChatSummary(){
                        $postfields = array(
                                'goAction' => 'goGetWhatsAppChatSummary'
                        );
                        return $this->API_Request("goWhatsApp", $postfields);
                }

		public function API_getWhatsAppAssignedChats($userid){
                        $postfields = array(
                                'goAction' => 'goGetWhatsAppAssignedChats',
                                'userid' => $userid
                        );
                        return $this->API_Request("goWhatsApp", $postfields);
                }

		public function API_whatsAppLogin($userid){
                        $postfields = array(
                                'goAction' => 'goWhatsAppLogin',
				'userid' => $userid
                        );
                        return $this->API_Request("goWhatsApp", $postfields);
                }

		public function API_whatsAppLogout($userid){
                        $postfields = array(
                                'goAction' => 'goWhatsAppLogout',
                                'userid' => $userid
                        );
                        return $this->API_Request("goWhatsApp", $postfields);
                }
		
		public function API_whatsAppPauseResume($userid, $action){
                        $postfields = array(
                                'goAction' => 'goWhatsAppResumePause',
                                'userid' => $userid,
				'action' => $action
                        );
                        return $this->API_Request("goWhatsApp", $postfields);
                }

		public function API_whatsAppQueue(){
                        $postfields = array(
                                'goAction' => 'goAddWhatsAppQueue'
                        );
                        return $this->API_Request("goWhatsApp", $postfields);
                }

		public function API_getWhatsAppUsers(){
                        $postfields = array(
                                'goAction' => 'goGetWhatsAppUsers'
                        );
                        return $this->API_Request("goWhatsApp", $postfields);
                }

		public function API_whatsAppTransferChat($id, $userid){
                        $postfields = array(
                                'goAction' => 'goWhatsAppTransferChat',
				'id' => $id,
				'userid' => $userid
                        );
                        return $this->API_Request("goWhatsApp", $postfields);
                }

		public function API_whatsAppAssignChats(){
                        $postfields = array(
                                'goAction' => 'goWhatsAppAssignChat'
                        );
                        return $this->API_Request("goWhatsApp", $postfields);
                }

		// escape existing special characters already in the database
		function escapeJsonString($value) { # list from www.json.org: (\b backspace, \f formfeed)
			$escapers = array("\\", "/", "\"", "\n", "\r", "\t", "\x08", "\x0c", "	");
			$replacements = array("\\\\", "\\/", "\\\"", "\\n", "\\r", "\\t", "\\f", "\\b", " ");
			$result = str_replace($escapers, $replacements, $value);

			return $result;
		}
	}
	
?>
