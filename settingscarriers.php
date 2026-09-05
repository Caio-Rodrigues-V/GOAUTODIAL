<?php
/**
 * @file        settingscarriers.php
 * @brief       Manage Carriers
 * @copyright   Copyright (c) 2020 GOautodial Inc.
 * @author      Alexander Jim Abenoja
 * @author		Demian Lizandro A. Biscocho
 * @author		Noel Umandap
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

	require_once('php/UIHandler.php');
	require_once('php/APIHandler.php');
	require_once('./php/CRMDefaults.php');
    require_once('./php/LanguageHandler.php');
    include('./php/Session.php');

	$ui = \creamy\UIHandler::getInstance();
	$api = \creamy\APIHandler::getInstance();
	$lh = \creamy\LanguageHandler::getInstance();
	$user = \creamy\CreamyUser::currentUser();
	
	//proper user redirects
	if($user->getUserRole() != CRM_DEFAULTS_USER_ROLE_ADMIN){
		if($user->getUserRole() == CRM_DEFAULTS_USER_ROLE_AGENT){
			header("location: agent.php");
		}
	}	
	
	//$gopackage = $api->API_getGOPackage();
	$perm = $api->goGetPermissions('carriers', $_SESSION['usergroup']);
	
	/*if( ($gopackage->show_carrier_settings === "N" || $gopackage->show_carrier_settings === NULL) && ($_SESSION['usergroup'] !== "ADMIN" && $_SESSION !== "goAPI") ){
		header("location:index.php");
	}*/
	
?>
<html>
    <head>
        <meta charset="UTF-8">
        <title><?php $lh->translateText('portal_title'); ?> - <?php $lh->translateText("carriers"); ?></title>
        <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>

        <?php 
			print $ui->standardizedThemeCSS(); 
			print $ui->creamyThemeCSS();
			print $ui->dataTablesTheme();
		?>
	
    	<!-- Wizard Form style -->
		<link href="css/style.css" rel="stylesheet" type="text/css" />
		<link href="css/app.css" rel="stylesheet" type="text/css" />
		<style>
		.wizard > .actions {
		    display: block !important;
		    text-align: right;
		    padding: 15px 0;
		}
		.wizard > .actions ul {
		    list-style: none;
		    display: inline-block;
		    padding: 0;
		    margin: 0;
		}
		.wizard > .actions ul > li {
		    display: inline-block;
		    margin: 0 5px;
		}
		.wizard > .actions a {
		    background: #3c8dbc;
		    color: #fff !important;
		    padding: 8px 18px;
		    border-radius: 4px;
		    text-decoration: none;
		    display: inline-block;
		    font-weight: bold;
		}
		.wizard > .actions a:hover {
		    background: #286090;
		}
		.wizard > .actions .disabled a {
		    background: #eee !important;
		    color: #aaa !important;
		}
		.wizard > .steps {
		    display: block;
		    margin-bottom: 20px;
		}
		.wizard > .steps ul {
		    display: table;
		    width: 100%;
		    table-layout: fixed;
		    margin: 0;
		    padding: 0;
		    list-style: none;
		}
		.wizard > .steps li {
		    display: table-cell;
		    text-align: center;
		}
		.wizard > .steps a {
		    display: block;
		    padding: 10px;
		    background: #eee;
		    color: #555;
		    border-radius: 4px;
		    margin: 0 5px;
		    text-decoration: none;
		}
		.wizard > .steps .current a {
		    background: #3c8dbc !important;
		    color: #fff !important;
		    font-weight: bold;
		}
		.wizard > .steps .done a {
		    background: #00a65a !important;
		    color: #fff !important;
		}
		</style>
    </head>

    <?php print $ui->creamyBody(); ?>

        <div class="wrapper">
        <!-- header logo: style can be found in header.less -->
		<?php print $ui->creamyHeader($user); ?>
            <!-- Left side column. contains the logo and sidebar -->
			<?php print $ui->getSidebar($user->getUserId(), $user->getUserName(), $user->getUserRole(), $user->getUserAvatar()); ?>

            <!-- Right side column. Contains the navbar and content of the page -->
            <aside class="right-side">
                <!-- Content Header (Page header) -->
                <section class="content-header">
                    <h1>
                        <?php $lh->translateText("settings"); ?>
                        <small><?php $lh->translateText("carriers_management"); ?></small>
                    </h1>
                    <ol class="breadcrumb">
                        <li><a href="./index.php"><i class="fa fa-phone"></i> <?php $lh->translateText("home"); ?></a></li>
                       <li><?php $lh->translateText("settings"); ?></li>
						<li class="active"><?php $lh->translateText("carriers"); ?>
                    </ol>
                </section>

                <!-- Main content -->
                <section class="content">
                <?php if ($perm->carriers_read !== 'N') { ?>
                    <div class="panel panel-default">
                        <div class="panel-body table" id="recording_table">
                            <legend>
                                <?php $lh->translateText("carriers"); ?>
                                <button type="button" class="btn btn-primary btn-sm pull-right open-add-carrier-modal" onclick="$('#wizard-modal').modal('show');" data-toggle="modal" data-target="#wizard-modal">
                                    <i class="fa fa-plus"></i> <?php $lh->translateText('add_new_carrier'); ?>
                                </button>
                            </legend>
							<?php print $ui->getListAllCarriers($perm); ?>
                        </div>
                    </div>
				<!-- /fila con acciones, formularios y demás -->
				<?php
					} else {
						print $ui->calloutErrorMessage($lh->translationFor("you_dont_have_permission"));
					}
				?>
                </section><!-- /.content -->
            </aside><!-- /.right-side -->
			<?php print $ui->getRightSidebar($user->getUserId(), $user->getUserName(), $user->getUserAvatar()); ?>
        </div><!-- ./wrapper -->

		<div class="action-button-circle open-add-carrier-modal <?php if ($perm->carriers_create == 'N') { echo "hidden"; } ?>" onclick="$('#wizard-modal').modal('show');" data-toggle="modal" data-target="#wizard-modal">
			<?php print $ui->getCircleButton("carriers", "plus"); ?>
		</div>
<?php
	/*
	* MODAL
	*/
	$user_groups = $api->API_getAllUserGroups();
	$carriers = $api->API_getAllCarriers();
	$servers = $api->API_getAllServers();
?>
	<!-- ADD CARRIER MODAL -->
	<div class="modal fade" id="wizard-modal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
				<form id="create_form" class="form-horizontal">
					<input type="hidden" name="log_user" value="<?=$_SESSION['user']?>" />
					<input type="hidden" name="log_group" value="<?=$_SESSION['usergroup']?>" />
					<input type="hidden" name="carrier_type" value="manual" />

					<div class="modal-header">
						<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
						<h4 class="modal-title">
							<b><i class="fa fa-phone"></i> <?php $lh->translateText('add_new_carrier'); ?></b>
						</h4>
					</div>
					<div class="modal-body" style="max-height: 72vh; overflow-y: auto;">
						<div class="form-group">
							<label for="carrier_id" class="col-sm-3 control-label"><?php $lh->translateText('carrier_id'); ?> *</label>
							<div class="col-sm-8">
								<input type="text" class="form-control" name="carrier_id" id="carrier_id" placeholder="Ex: OKTOR_IA" maxlength="15" required />
							</div>
						</div>
						<div class="form-group">
							<label for="carrier_name" class="col-sm-3 control-label"><?php $lh->translateText('carrier_name'); ?> *</label>
							<div class="col-sm-8">
								<input type="text" class="form-control" name="carrier_name" id="carrier_name" placeholder="Ex: Oktor Automacao IA" required />
							</div>
						</div>
						<div class="form-group">
							<label for="carrier_description" class="col-sm-3 control-label"><?php $lh->translateText('carrier_description'); ?></label>
							<div class="col-sm-8">
								<input type="text" class="form-control" name="carrier_description" id="carrier_description" placeholder="Ex: Tronco Oktor Tech Prefix 59083" />
							</div>
						</div>
						<div class="form-group">
							<label for="user_group" class="col-sm-3 control-label"><?php $lh->translateText('user_groups'); ?></label>
							<div class="col-sm-8">
								<select id="user_group" class="form-control" name="user_group">
									<option value="---ALL---"><?php $lh->translateText('all_usergroups'); ?></option>
									<?php
										if (isset($user_groups->user_group) && is_array($user_groups->user_group)) {
											for($i=0;$i<count($user_groups->user_group);$i++){
												echo "<option value='".$user_groups->user_group[$i]."'>".$user_groups->user_group[$i].' - '.$user_groups->group_name[$i]."</option>";
											}
										}
									?>
								</select>
							</div>
						</div>
						<div class="form-group">
							<label for="protocol" class="col-sm-3 control-label"><?php $lh->translateText('protocol'); ?></label>
							<div class="col-sm-8">
								<select class="form-control" name="protocol" id="protocol">
									<option value="CUSTOM" selected>CUSTOM</option>
									<option value="SIP">SIP</option>
									<option value="IAX2">IAX2</option>
								</select>
							</div>
						</div>
						<div class="form-group">
							<label for="server_ip" class="col-sm-3 control-label"><?php $lh->translateText('server_ip'); ?></label>
							<div class="col-sm-8">
								<select name="server_ip" id="server_ip" class="form-control">
									<?php
										$has_server = false;
										if (isset($servers->server_ip)) {
											if (is_array($servers->server_ip) && count($servers->server_ip) > 0) {
												for($i=0;$i<count($servers->server_ip);$i++){
													$desc = isset($servers->server_description[$i]) ? $servers->server_description[$i] : 'Dialer Server';
													echo "<option value='".$servers->server_ip[$i]."'>".$servers->server_ip[$i]." - ".$desc."</option>";
													$has_server = true;
												}
											} elseif (is_string($servers->server_ip) && !empty($servers->server_ip)) {
												$desc = isset($servers->server_description) ? $servers->server_description : 'Dialer Server';
												echo "<option value='".$servers->server_ip."'>".$servers->server_ip." - ".$desc."</option>";
												$has_server = true;
											}
										}
										if (!$has_server) {
											$srv_addr = !empty($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '127.0.0.1';
											echo "<option value='".$srv_addr."' selected>".$srv_addr." - Default Asterisk Server</option>";
											if ($srv_addr !== '127.0.0.1') {
												echo "<option value='127.0.0.1'>127.0.0.1 - Localhost</option>";
											}
										}
									?>
								</select>
							</div>
						</div>
						<div class="form-group">
							<label for="active" class="col-sm-3 control-label"><?php $lh->translateText('status'); ?></label>
							<div class="col-sm-8">
								<select name="active" id="active" class="form-control">
									<option value="Y" selected>Active (Y)</option>
									<option value="N">Inactive (N)</option>
								</select>
							</div>
						</div>
						<div class="form-group">
							<label for="registration_string" class="col-sm-3 control-label"><?php $lh->translateText('registration_string'); ?></label>
							<div class="col-sm-8">
								<input type="text" class="form-control" name="registration_string" id="registration_string" maxlength="255" placeholder="Opcional para troncos IP-to-IP" />
							</div>
						</div>
						<div class="form-group">
							<label for="account_entry" class="col-sm-3 control-label"><?php $lh->translateText('account_entry'); ?></label>
							<div class="col-sm-8">
								<textarea name="account_entry" class="form-control" rows="8" id="account_entry" style="font-family: monospace; font-size: 13px;" placeholder="[oktor_ia_pri]&#10;type=peer&#10;host=200.196.232.250&#10;port=5060&#10;..."></textarea>
							</div>
						</div>
						<div class="form-group">
							<label for="globals_string" class="col-sm-3 control-label"><?php $lh->translateText('global_string'); ?></label>
							<div class="col-sm-8">
								<input type="text" name="globals_string" class="form-control" value="" id="globals_string" maxlength="255" placeholder="Ex: OKTOR_IA = SIP/oktor_ia_pri" />
							</div>
						</div>
						<div class="form-group">
							<label for="dialplan_entry" class="col-sm-3 control-label"><?php $lh->translateText('dialplan_entry'); ?></label>
							<div class="col-sm-8">
								<textarea name="dialplan_entry" class="form-control" rows="8" id="dialplan_entry" style="font-family: monospace; font-size: 13px;" placeholder="exten => _9.,1,AGI(agi://127.0.0.1:4577/call_log)&#10;exten => _9.,2,Set(TIMEOUT(absolute)=14400)&#10;exten => _9.,3,Dial(SIP/oktor_ia_pri/5908355${EXTEN:1},55,tTo)&#10;..."></textarea>
							</div>
						</div>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-default" data-dismiss="modal"><?php $lh->translateText("close"); ?></button>
						<button type="submit" id="btn_save_carrier" class="btn btn-primary"><i class="fa fa-save"></i> <?php $lh->translateText("submit"); ?></button>
					</div>
				</form>
			</div>
		</div>
	</div><!-- end of modal -->

	<!-- Modal -->
	<div id="view-calltime-modal" class="modal fade" role="dialog">
	  <div class="modal-dialog">

	    <!-- Modal content-->
	    <div class="modal-content">
	      <div class="modal-header">
	        <button type="button" class="close" data-dismiss="modal">&times;</button>
	        <h4 class="modal-title"><b><?php $lh->translateText('call_time_details'); ?></b></h4>
	      </div>
	      <div class="modal-body">
		<div class="form-horizontal">
			<div class="message_box"></div>
			<div class="form-group">
				<label class="control-label col-lg-4"><?php $lh->translateText('moh_name'); ?></label>
				<div class="col-lg-8">
					<input type="text" class="form-control moh_name">
				</div>
			</div>
		</div>
	      </div>
	      <div class="modal-footer">
		<button type="button" class="btn btn-primary btn-update-calltime" data-id=""><?php $lh->translateText("modify"); ?></button>
	        <button type="button" class="btn btn-default" data-dismiss="modal"><?php $lh->translateText("close"); ?></button>
	      </div>
	    </div>
	    <!-- End of modal content -->
	  </div>
	</div>
	<!-- End of modal -->

		<!-- Forms and actions -->
		<?php print $ui->standardizedThemeJS(); ?>
		<script src="js/plugins/input-mask/jquery.inputmask.js" type="text/javascript"></script>
		<script src="js/plugins/input-mask/jquery.inputmask.extensions.js" type="text/javascript"></script>
		<script type="text/javascript">

			$(document).ready(function() {
				if ($('table#carriers').length > 0) {
					$('#carriers').dataTable();
				}
				
				/* Open Add Carrier Modal */
				$(document).on('click', '.open-add-carrier-modal, [data-target="#wizard-modal"]', function(e) {
					e.preventDefault();
					$('#wizard-modal').modal('show');
				});
				
				/* Submit Carrier Form */
				$("#create_form").on("submit", function(e) {
					e.preventDefault();
					var btn = $("#btn_save_carrier");
					btn.text("<?php $lh->translateText("loading"); ?>").prop("disabled", true);

					$.ajax({
						url: "./php/AddCarrier.php",
						type: 'POST',
						data: $(this).serialize(),
						dataType: 'json',
						success: function(data) {
							btn.html('<i class="fa fa-save"></i> <?php $lh->translateText("submit"); ?>').prop("disabled", false);
							if (data == 1 || data == "1" || (data && data.result == "success")) {
								swal({
									title: "<?php $lh->translateText("success"); ?>",
									text: "<?php $lh->translateText("add_carriers_success"); ?>",
									type: "success"
								}, function(){
									window.location.href = 'settingscarriers.php';
								});
							} else {
								sweetAlert("<?php $lh->translateText("oops"); ?>", "<?php $lh->translateText("something_went_wrong"); ?>: " + (typeof data === 'object' ? JSON.stringify(data) : data), "error");
							}
						},
						error: function(xhr, status, error) {
							btn.html('<i class="fa fa-save"></i> <?php $lh->translateText("submit"); ?>').prop("disabled", false);
							sweetAlert("<?php $lh->translateText("oops"); ?>", "Erro na requisição: " + error, "error");
						}
					});
				});
				
				/**
				  * Edit user details
				 */
				$(document).on('click','.edit-carrier',function() {
					var url = 'editsettingscarrier.php';
					var cid = $(this).attr('data-id');
					var role = $(this).attr('data-role');
					//alert(userid);
					var form = $('<form action="' + url + '" method="post"><input type="hidden" name="cid" value="'+cid+'" /><input type="hidden" name="role" value="'+role+'"></form>');
					$('body').append(form);  // This line is not necessary
					$(form).submit();
				 });
				
				$(document).on('click','.delete-carrier',function() {
					var id = $(this).attr('data-id');
					var log_user = '<?=$_SESSION['user']?>';
					var log_group = '<?=$_SESSION['usergroup']?>';
                    swal({
                        title: "<?php $lh->translateText("are_you_sure"); ?>",
                        text: "<?php $lh->translateText("action_cannot_be_undone"); ?>",
                        type: "warning",
                        showCancelButton: true,
                        confirmButtonColor: "#DD6B55",
                        confirmButtonText: "<?php $lh->translateText("confirm_delete_carrier"); ?>",
                        cancelButtonText: "<?php $lh->translateText("cancel_please"); ?>",
                        closeOnConfirm: false,
                        closeOnCancel: false
                        },
                        function(isConfirm){
                            if (isConfirm) {
                            	$.ajax({
									url: "./php/DeleteCarrier.php",
									type: 'POST',
									data: {
									      carrier_id : id,
										  log_user: log_user,
										  log_group: log_group
									},
									dataType: 'json',
									success: function(data) {
										if(data == 1){
											swal("<?php $lh->translateText("deleted"); ?>", "<?php $lh->translateText("carrier_delete_success"); ?>", "success");
                                            window.setTimeout(function(){location.reload();},1000);
										}else{
											sweetAlert("<?php $lh->translateText("oups"); ?>", "<?php $lh->translateText("something_went_wrong"); ?>"+data, "error");
										}
									}
								});

							 } else {
                                swal("<?php $lh->translateText("cancelled"); ?>", "<?php $lh->translateText("cancel_msg"); ?>", "error");
                            }
                        }
                    );

				});
				
				// disable special characters on Carrier ID
				$('#carrier_id').bind('keypress', function (event) {
					var regex = new RegExp("^[a-zA-Z0-9]+$");
					var key = String.fromCharCode(!event.charCode ? event.which : event.charCode);
					if (!regex.test(key)) {
					   event.preventDefault();
					   return false;
					}
				});
				
				//input mask
					$("[data-mask]").inputmask();
			});

		</script>

		<?php print $ui->creamyFooter();?>
    </body>
</html>
