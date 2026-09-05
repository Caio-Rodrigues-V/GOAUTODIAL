<?php
/**
 * Setup OKTOR Carrier in MariaDB Database directly
 */
require_once('php/CRMDefaults.php');
require_once('php/Session.php');
require_once('php/DatabaseConnectorFactory.php');

$db = \creamy\DatabaseConnectorFactory::getInstance()->getDatabaseConnectorOfType(\creamy\CRM_DB_CONNECTOR_TYPE_MYSQL);

if (!$db) {
    die("<h3>Erro ao conectar ao banco de dados MySQL/MariaDB.</h3>");
}

// Ensure table vicidial_server_carriers exists
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

$server_ip = !empty($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '127.0.0.1';

$account_entry = "[oktor_ia_pri]
type=peer
host=200.196.232.250
port=5060
dtmfmode=rfc2833
context=trunkinbound
insecure=port,invite
disallow=all
allow=alaw
allow=g729
qualify=yes
nat=no

[oktor_ia_sec]
type=peer
host=177.154.149.210
port=5060
dtmfmode=rfc2833
context=trunkinbound
insecure=port,invite
disallow=all
allow=alaw
allow=g729
qualify=yes
nat=no";

$dialplan_entry = 'exten => _9.,1,AGI(agi://127.0.0.1:4577/call_log)
exten => _9.,2,Set(TIMEOUT(absolute)=14400)
exten => _9.,3,Dial(SIP/oktor_ia_pri/5908355${EXTEN:1},55,tTo)
exten => _9.,4,GotoIf($["${DIALSTATUS}" = "CHANUNAVAIL" | "${DIALSTATUS}" = "CONGESTION"]?backup)
exten => _9.,5,Hangup
exten => _9.(backup),Dial(SIP/oktor_ia_sec/5908355${EXTEN:1},55,tTo)
exten => _9.,n,Hangup';

$carrier_data = array(
    'carrier_id' => 'OKTOR_IA',
    'carrier_name' => 'Oktor Automacao IA',
    'registration_string' => '',
    'account_entry' => $account_entry,
    'protocol' => 'CUSTOM',
    'globals_string' => 'OKTOR_IA = SIP/oktor_ia_pri',
    'dialplan_entry' => $dialplan_entry,
    'server_ip' => $server_ip,
    'active' => 'Y',
    'carrier_description' => 'Tronco Oktor Tech Prefix 59083',
    'user_group' => '---ALL---'
);

$db->where('carrier_id', 'OKTOR_IA');
if ($db->has('vicidial_server_carriers')) {
    $db->where('carrier_id', 'OKTOR_IA');
    $db->update('vicidial_server_carriers', $carrier_data);
    $action = "atualizado";
} else {
    $db->insert('vicidial_server_carriers', $carrier_data);
    $action = "cadastrado";
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Configuração Tronco OKTOR</title>
    <link href="css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <style>
        body { background: #f4f6f9; font-family: sans-serif; padding: 40px 20px; }
        .card { max-width: 700px; margin: 0 auto; background: #fff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); padding: 30px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        table th, table td { padding: 10px; border: 1px solid #ddd; }
        table th { background: #f9f9f9; width: 30%; }
        .badge { background: #00a65a; color: #fff; padding: 4px 8px; border-radius: 4px; }
        .btn { display: inline-block; padding: 10px 20px; background: #3c8dbc; color: #fff; text-decoration: none; border-radius: 4px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="card">
        <h2 style="color: #00a65a;">OKTOR_IA <?=$action?> com sucesso!</h2>
        <p>O tronco da OKTOR foi gravado diretamente no banco de dados MariaDB.</p>
        
        <table>
            <tr><th>Carrier ID</th><td><b>OKTOR_IA</b></td></tr>
            <tr><th>Carrier Name</th><td>Oktor Automacao IA</td></tr>
            <tr><th>Tech Prefix</th><td><b>59083</b></td></tr>
            <tr><th>IP Primário</th><td>200.196.232.250</td></tr>
            <tr><th>IP Secundário</th><td>177.154.149.210</td></tr>
            <tr><th>Dialplan Prefix</th><td><b>9</b> (Ex: disca 9 + 11999999999 -> envia 590835511999999999)</td></tr>
            <tr><th>Status</th><td><span class="badge">ATIVO (Y)</span></td></tr>
        </table>

        <div style="margin-top: 25px;">
            <a href="settingscarriers.php" class="btn">Ir para Lista de Carriers</a>
        </div>
    </div>
</body>
</html>
