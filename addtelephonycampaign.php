<?php
error_reporting(E_ERROR | E_PARSE);

require_once('php/UIHandler.php');
require_once('php/APIHandler.php');
require_once('./php/CRMDefaults.php');
require_once('./php/LanguageHandler.php');
include('./php/Session.php');

$ui = \creamy\UIHandler::getInstance();
$api = \creamy\APIHandler::getInstance();
$lh = \creamy\LanguageHandler::getInstance();
$user = \creamy\CreamyUser::currentUser();

if(!$user || $user->getUserRole() != CRM_DEFAULTS_USER_ROLE_ADMIN){
    if($user && $user->getUserRole() == CRM_DEFAULTS_USER_ROLE_AGENT){
        header('location: agent.php');
        exit();
    }
}

$user_groups = $api->API_getAllUserGroups();
$carriers = $api->API_getAllCarriers();
$scripts = $api->API_getAllScripts();
$sess_user = isset($_SESSION['user']) ? $_SESSION['user'] : '';
$sess_group = isset($_SESSION['usergroup']) ? $_SESSION['usergroup'] : '';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php $lh->translateText('portal_title'); ?> - Criar Nova Campanha</title>
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
                <?php $lh->translateText("telephony"); ?>
                <small>Criar Nova Campanha Discadora</small>
            </h1>
            <ol class="breadcrumb">
                <li><a href="./index.php"><i class="fa fa-phone"></i> Home</a></li>
                <li><a href="./telephonycampaigns.php">Campaigns</a></li>
                <li class="active">Add Campaign</li>
            </ol>
        </section>

        <section class="content">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fa fa-plus"></i> Criar Nova Campanha (Outbound / Discador)</h3>
                </div>
                <div class="panel-body">
                    <form id="create_campaign_form" class="form-horizontal">
                        <input type="hidden" name="log_user" value="<?php echo htmlspecialchars($sess_user); ?>" />
                        <input type="hidden" name="log_group" value="<?php echo htmlspecialchars($sess_group); ?>" />
                        <input type="hidden" name="campaign_type" value="outbound" />

                        <div class="form-group">
                            <label for="campaign_id" class="col-sm-3 control-label">Campaign ID (Max 8 carac.) *</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" name="campaign_id" id="campaign_id" placeholder="Ex: CAMP_IA" maxlength="8" value="CAMP_IA" required />
                                <span class="help-block text-muted">Apenas letras e números (sem espaços, max 8 caracteres).</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="campaign_name" class="col-sm-3 control-label">Campaign Name *</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" name="campaign_name" id="campaign_name" placeholder="Ex: Campanha IA Oktor" value="Campanha IA Oktor" required />
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="description" class="col-sm-3 control-label">Description</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" name="description" id="description" value="Campanha de automação IA Oktor" />
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="dial_prefix" class="col-sm-3 control-label">Dial Prefix (Prefixo da Rota) *</label>
                            <div class="col-sm-8">
                                <input type="text" class="form-control" name="dial_prefix" id="dial_prefix" value="9" required />
                                <span class="help-block text-success"><b>9</b> direciona as chamadas para o tronco OKTOR com Tech Prefix 59083.</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="dial_method" class="col-sm-3 control-label">Dial Method (Método de Discagem)</label>
                            <div class="col-sm-8">
                                <select name="dial_method" id="dial_method" class="form-control">
                                    <option value="RATIO" selected>AUTO DIAL (RATIO - Discagem Automática)</option>
                                    <option value="MANUAL">MANUAL (Discagem Manual)</option>
                                    <option value="ADAPT_TAPERED">PREDICTIVE (Preditivo)</option>
                                    <option value="INBOUND_MAN">INBOUND MAN</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="auto_dial_level" class="col-sm-3 control-label">Auto Dial Level (Linhas por Agente)</label>
                            <div class="col-sm-8">
                                <select name="auto_dial_level" id="auto_dial_level" class="form-control">
                                    <option value="1.0" selected>1.0 (1 linha por agente)</option>
                                    <option value="1.5">1.5</option>
                                    <option value="2.0">2.0 (2 linhas por agente)</option>
                                    <option value="2.5">2.5</option>
                                    <option value="3.0">3.0 (3 linhas por agente)</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="status" class="col-sm-3 control-label">Status</label>
                            <div class="col-sm-8">
                                <select name="status" id="status" class="form-control">
                                    <option value="Y" selected>Active (Y)</option>
                                    <option value="N">Inactive (N)</option>
                                </select>
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
                            <div class="col-sm-offset-3 col-sm-8">
                                <button type="submit" id="btn_save_campaign" class="btn btn-primary btn-lg"><i class="fa fa-save"></i> Criar Campanha</button>
                                <a href="telephonycampaigns.php" class="btn btn-default btn-lg">Cancelar</a>
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
        $('#campaign_id').bind('keypress', function (event) {
            var regex = new RegExp('^[a-zA-Z0-9]+$');
            var key = String.fromCharCode(!event.charCode ? event.which : event.charCode);
            if (!regex.test(key)) {
               event.preventDefault();
               return false;
            }
        });

        $('#create_campaign_form').on('submit', function(e) {
            e.preventDefault();
            var btn = $('#btn_save_campaign');
            btn.text('Criando Campanha...').prop('disabled', true);

            $.ajax({
                url: './php/AddCampaign.php',
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(data) {
                    btn.html('<i class="fa fa-save"></i> Criar Campanha').prop('disabled', false);
                    if (data == 1 || data.status == 1 || data.result == 'success') {
                        swal({
                            title: 'Sucesso!',
                            text: 'Campanha criada com sucesso!',
                            type: 'success'
                        }, function(){
                            window.location.href = 'telephonycampaigns.php';
                        });
                    } else {
                        sweetAlert('Oops!', 'Erro ao criar campanha: ' + (typeof data === 'object' ? JSON.stringify(data) : data), 'error');
                    }
                },
                error: function(xhr, status, error) {
                    btn.html('<i class="fa fa-save"></i> Criar Campanha').prop('disabled', false);
                    sweetAlert('Oops!', 'Erro na requisição: ' + error, 'error');
                }
            });
        });
    });
</script>
<?php print $ui->creamyFooter();?>
</body>
</html>
