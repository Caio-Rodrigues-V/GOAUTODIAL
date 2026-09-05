<?php
require_once('php/UIHandler.php');
require_once('php/APIHandler.php');
require_once('./php/CRMDefaults.php');
require_once('./php/LanguageHandler.php');
include('./php/Session.php');

$ui = \creamy\UIHandler::getInstance();
$api = \creamy\APIHandler::getInstance();
$lh = \creamy\LanguageHandler::getInstance();
$user = \creamy\CreamyUser::currentUser();

if($user->getUserRole() != CRM_DEFAULTS_USER_ROLE_ADMIN){
    if($user->getUserRole() == CRM_DEFAULTS_USER_ROLE_AGENT){
        header('location: agent.php');
        exit();
    }
}

$user_groups = $api->API_getAllUserGroups();
$servers = $api->API_getAllServers();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php $lh->translateText('portal_title'); ?> - Add New Carrier</title>
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
    <?php 
        print $ui->standardizedThemeCSS(); 
        print $ui->creamyThemeCSS();
    ?>
</head>
<?php print $ui->creamyBody(); ?>
<div class="wrapper">
    <?php print $ui->creamyHeader($user); ?>
    <?php print $ui->getSidebar($user->getUserId(), $user->getUserName(), $user->getUserRole(), $user->getUserAvatar()); ?>

    <aside class="right-side">
        <section class="content-header">
            <h1>
                <?php $lh->translateText("settings"); ?>
                <small>Add New Carrier</small>
            </h1>
            <ol class="breadcrumb">
                <li><a href="./index.php"><i class="fa fa-phone"></i> Home</a></li>
                <li><a href="./settingscarriers.php">Carriers</a></li>
                <li class="active">Add Carrier</li>
            </ol>
        </section>

        <section class="content">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-plus"></i> Cadastrar Novo Carrier (Tronco VoIP)</h3>
                </div>
                <div class="panel-body">
                    <form id="create_form" class="form-horizontal">
                        <input type="hidden" name="log_user" value="<?=$_SESSION['user']?>" />
                        <input type="hidden" name="log_group" value="<?=$_SESSION['usergroup']?>" />
                        <input type="hidden" name="carrier_type" value="manual" />

                        <div class="form-group">
                            <label for="carrier_id" class="col-sm-3 control-label">Carrier ID (max 15 carac.) *</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" name="carrier_id" id="carrier_id" placeholder="Ex: OKTOR_IA" maxlength="15" value="OKTOR_IA" required />
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="carrier_name" class="col-sm-3 control-label">Carrier Name *</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" name="carrier_name" id="carrier_name" placeholder="Ex: Oktor Automacao IA" value="Oktor Automacao IA" required />
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="carrier_description" class="col-sm-3 control-label">Description</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" name="carrier_description" id="carrier_description" value="Tronco Oktor Tech Prefix 59083" />
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="user_group" class="col-sm-3 control-label">User Group</label>
                            <div class="col-sm-8">
                                <select id="user_group" class="form-control" name="user_group">
                                    <option value="---ALL---">--- ALL USERGROUPS ---</option>
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
                            <label for="protocol" class="col-sm-3 control-label">Protocol</label>
                            <div class="col-sm-8">
                                <select class="form-control" name="protocol" id="protocol">
                                    <option value="CUSTOM" selected>CUSTOM</option>
                                    <option value="SIP">SIP</option>
                                    <option value="IAX2">IAX2</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="server_ip" class="col-sm-3 control-label">Server IP</label>
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
                            <label for="active" class="col-sm-3 control-label">Status</label>
                            <div class="col-sm-8">
                                <select name="active" id="active" class="form-control">
                                    <option value="Y" selected>Active (Y)</option>
                                    <option value="N">Inactive (N)</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="registration_string" class="col-sm-3 control-label">Registration String</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" name="registration_string" id="registration_string" maxlength="255" placeholder="Opcional para IP-to-IP" />
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="account_entry" class="col-sm-3 control-label">Account Entry</label>
                            <div class="col-sm-8">
                                <textarea class="form-control" name="account_entry" id="account_entry" rows="8" style="font-family: monospace;">[oktor_ia_pri]
type=peer
host=200.196.232.250
port=5060
qualify=yes
disallow=all
allow=alaw
allow=g729
insecure=port,invite
canreinvite=no
context=trunkinbound

[oktor_ia_sec]
type=peer
host=177.154.149.210
port=5060
qualify=yes
disallow=all
allow=alaw
allow=g729
insecure=port,invite
canreinvite=no
context=trunkinbound</textarea>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="globals_string" class="col-sm-3 control-label">Globals String</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" name="globals_string" id="globals_string" value="OKTORIA = SIP/oktor_ia_pri" />
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="dialplan_entry" class="col-sm-3 control-label">Dialplan Entry</label>
                            <div class="col-sm-8">
                                <textarea class="form-control" name="dialplan_entry" id="dialplan_entry" rows="7" style="font-family: monospace;">exten => _9.,1,AGI(agi://127.0.0.1:4577/call_log)
exten => _9.,2,Set(TIMEOUT(absolute)=14400)
exten => _9.,3,Dial(SIP/oktor_ia_pri/5908355${EXTEN:1},55,tTo)
exten => _9.,4,GotoIf($["${DIALSTATUS}" = "CHANUNAVAIL" | "${DIALSTATUS}" = "CONGESTION"]?failover:hangup)
exten => _9,n(failover),Dial(SIP/oktor_ia_sec/5908355${EXTEN:1},55,tTo)
exten => _9,n(hangup),Hangup()</textarea>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="col-sm-offset-3 col-sm-8">
                                <button type="submit" id="btn_save_carrier" class="btn btn-primary btn-lg"><i class="fa fa-save"></i> Salvar Carrier</button>
                                <a href="settingscarriers.php" class="btn btn-default btn-lg">Cancelar</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </aside>
    <?php print $ui->getRightSidebar($user->getUserId(), $user->getUserName(), $user->getUserAvatar()); ?>
</div>

<?php print $ui->standardizedThemeJS(); ?>
<script type="text/javascript">
    $(document).ready(function() {
        $('#carrier_id').bind('keypress', function (event) {
            var regex = new RegExp('^[a-zA-Z0-9_]+$');
            var key = String.fromCharCode(!event.charCode ? event.which : event.charCode);
            if (!regex.test(key)) {
               event.preventDefault();
               return false;
            }
        });

        $('#create_form').on('submit', function(e) {
            e.preventDefault();
            var btn = $('#btn_save_carrier');
            btn.text('Salvando...').prop('disabled', true);

            $.ajax({
                url: './php/AddCarrier.php',
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(data) {
                    btn.html('<i class="fa fa-save"></i> Salvar Carrier').prop('disabled', false);
                    if (data == 1 || data == '1' || (data && data.result == 'success')) {
                        swal({
                            title: 'Sucesso!',
                            text: 'Carrier cadastrado com sucesso!',
                            type: 'success'
                        }, function(){
                            window.location.href = 'settingscarriers.php';
                        });
                    } else {
                        sweetAlert('Oops!', 'Erro ao salvar: ' + (typeof data === 'object' ? JSON.stringify(data) : data), 'error');
                    }
                },
                error: function(xhr, status, error) {
                    btn.html('<i class="fa fa-save"></i> Salvar Carrier').prop('disabled', false);
                    sweetAlert('Oops!', 'Erro na requisição: ' + error, 'error');
                }
            });
        });
    });
</script>
<?php print $ui->creamyFooter();?>
</body>
</html>
