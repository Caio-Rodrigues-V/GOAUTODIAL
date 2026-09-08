<?php
/**
 * @file        ai_call_logs.php
 * @brief       AI Call Logs, Transcripts and Analytics
 * @copyright   (c) Dial GO Voice AI Engine
 */

require_once('php/UIHandler.php');
require_once('php/APIHandler.php');
require_once('php/CRMDefaults.php');
require_once('php/LanguageHandler.php');
require_once('php/Session.php');
require_once('php/AIAgentHandler.php');

$ui = \creamy\UIHandler::getInstance();
$api = \creamy\APIHandler::getInstance();
$lh = \creamy\LanguageHandler::getInstance();
$user = \creamy\CreamyUser::currentUser();
$aiHandler = \creamy\AIAgentHandler::getInstance();

if ($user && $user->getUserRole() == CRM_DEFAULTS_USER_ROLE_AGENT) {
    header("location: agent.php");
    exit();
}

$agentFilter = isset($_GET['agent_id']) ? (int)$_GET['agent_id'] : null;
$phoneFilter = isset($_GET['phone']) ? trim($_GET['phone']) : null;
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : null;

$agents = $aiHandler->getAllAgents();
$stats = $aiHandler->getCallStats();
$callLogs = $aiHandler->getCallLogs(100, 0, $agentFilter, $phoneFilter, $statusFilter);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Dial GO - Histórico & Transcrições de Voz IA</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>

    <?php 
        print $ui->standardizedThemeCSS(); 
        print $ui->creamyThemeCSS();
        print $ui->dataTablesTheme();
    ?>
    <link href="css/style.css" rel="stylesheet" type="text/css" />
    <style>
        .stat-card {
            border-radius: 8px;
            padding: 20px;
            color: #fff;
            margin-bottom: 20px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
            position: relative;
            overflow: hidden;
        }
        .stat-card .icon {
            position: absolute;
            right: 15px;
            bottom: 10px;
            font-size: 50px;
            opacity: 0.2;
        }
        .stat-card h3 { font-size: 32px; font-weight: bold; margin: 0 0 5px 0; }
        .stat-card p { font-size: 14px; margin: 0; text-transform: uppercase; font-weight: 600; }
        
        .chat-container {
            max-height: 480px;
            overflow-y: auto;
            padding: 15px;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        .chat-bubble {
            margin-bottom: 15px;
            display: flex;
            flex-direction: column;
        }
        .chat-bubble.user {
            align-items: flex-start;
        }
        .chat-bubble.assistant {
            align-items: flex-end;
        }
        .chat-bubble.system {
            align-items: center;
        }
        .bubble-content {
            max-width: 80%;
            padding: 10px 16px;
            border-radius: 16px;
            font-size: 13.5px;
            line-height: 1.45;
        }
        .chat-bubble.user .bubble-content {
            background: #ffffff;
            color: #1e293b;
            border: 1px solid #cbd5e1;
            border-bottom-left-radius: 4px;
        }
        .chat-bubble.assistant .bubble-content {
            background: #0084ff;
            color: #ffffff;
            border-bottom-right-radius: 4px;
        }
        .chat-bubble.system .bubble-content {
            background: #e2e8f0;
            color: #64748b;
            font-size: 11px;
            border-radius: 8px;
        }
        .bubble-author {
            font-size: 11px;
            margin-bottom: 3px;
            color: #64748b;
            font-weight: 600;
        }
        .badge-status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-completed { background: #dcfce7; color: #15803d; }
        .badge-busy { background: #fee2e2; color: #b91c1c; }
        .badge-ringing { background: #fef9c3; color: #a16207; }
        .tech-tag {
            display: inline-block;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 11px;
            color: #475569;
            margin-right: 3px;
        }
    </style>
</head>

<?php print $ui->creamyBody(); ?>
<div class="wrapper">
    <?php print $ui->creamyHeader($user); ?>
    <?php print $ui->getSidebar($user->getUserId(), $user->getUserName(), $user->getUserRole(), $user->getUserAvatar()); ?>

    <aside class="right-side">
        <section class="content-header">
            <h1 style="display:flex; align-items:center; justify-content:space-between;">
                <span><i class="fa fa-phone-square"></i> Histórico & Transcrições de Voz IA</span>
                <a href="api_ai_calls.php?action=list" target="_blank" class="btn btn-default btn-sm" style="font-size:12px;">
                    <i class="fa fa-code"></i> Explorar API REST
                </a>
            </h1>
            <ol class="breadcrumb">
                <li><a href="./index.php"><i class="fa fa-dashboard"></i> Início</a></li>
                <li><a href="./ai_agents.php">Agentes de IA</a></li>
                <li class="active">Histórico & Transcrições</li>
            </ol>
        </section>

        <section class="content">
            <!-- Métricas e Estatísticas -->
            <div class="row">
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8);">
                        <div class="icon"><i class="fa fa-phone"></i></div>
                        <h3><?php echo number_format($stats['total']); ?></h3>
                        <p>Total de Ligações</p>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card" style="background: linear-gradient(135deg, #10b981, #047857);">
                        <div class="icon"><i class="fa fa-check-circle"></i></div>
                        <h3>
                            <?php 
                                $rate = $stats['total'] > 0 ? round(($stats['answered'] / $stats['total']) * 100) : 100;
                                echo "{$rate}%";
                            ?>
                        </h3>
                        <p>Taxa de Atendimento</p>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card" style="background: linear-gradient(135deg, #8b5cf6, #6d28d9);">
                        <div class="icon"><i class="fa fa-clock-o"></i></div>
                        <h3>
                            <?php 
                                $min = floor($stats['duration'] / 60);
                                $sec = $stats['duration'] % 60;
                                echo sprintf("%02dm %02ds", $min, $sec);
                            ?>
                        </h3>
                        <p>Duração Total de Conversa</p>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                        <div class="icon"><i class="fa fa-star"></i></div>
                        <h3><?php echo number_format($stats['leads']); ?></h3>
                        <p>Leads Qualificados (Interesse)</p>
                    </div>
                </div>
            </div>

            <!-- Filtros e Tabela de Registros -->
            <div class="box box-primary">
                <div class="box-header with-border" style="padding: 15px;">
                    <form method="GET" class="form-inline" style="display:flex; gap:10px; flex-wrap:wrap;">
                        <div class="form-group">
                            <label style="margin-right:5px;">Agente:</label>
                            <select name="agent_id" class="form-control input-sm">
                                <option value="">Todos os Agentes</option>
                                <?php foreach ($agents as $a): ?>
                                    <option value="<?php echo $a['agent_id']; ?>" <?php echo ($agentFilter == $a['agent_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($a['agent_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label style="margin-right:5px;">Telefone:</label>
                            <input type="text" name="phone" class="form-control input-sm" placeholder="Ex: 2198435..." value="<?php echo htmlspecialchars($phoneFilter ?: ''); ?>">
                        </div>

                        <div class="form-group">
                            <label style="margin-right:5px;">Status:</label>
                            <select name="status" class="form-control input-sm">
                                <option value="">Todos os Status</option>
                                <option value="completed" <?php echo ($statusFilter == 'completed') ? 'selected' : ''; ?>>Completada</option>
                                <option value="busy" <?php echo ($statusFilter == 'busy') ? 'selected' : ''; ?>>Ocupada / Rejeitada</option>
                                <option value="ringing" <?php echo ($statusFilter == 'ringing') ? 'selected' : ''; ?>>Chamando</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-filter"></i> Filtrar</button>
                        <a href="ai_call_logs.php" class="btn btn-default btn-sm"><i class="fa fa-eraser"></i> Limpar</a>
                    </form>
                </div>

                <div class="box-body table-responsive">
                    <table class="table table-bordered table-striped" id="callLogsTable">
                        <thead>
                            <tr style="background:#f8fafc;">
                                <th>ID</th>
                                <th>Data & Hora</th>
                                <th>Telefone Destino</th>
                                <th>Agente de IA</th>
                                <th>Duração</th>
                                <th>Tecnologias</th>
                                <th>Qualificação</th>
                                <th>Status</th>
                                <th style="text-align:center;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($callLogs)): ?>
                                <tr>
                                    <td colspan="9" style="text-align:center; padding:30px; color:#94a3b8;">
                                        <i class="fa fa-phone" style="font-size:32px; display:block; margin-bottom:10px;"></i>
                                        Nenhuma chamada registrada até o momento. Faça uma chamada de teste no menu <a href="ai_agents.php">Meus Agentes</a>.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($callLogs as $c): ?>
                                    <tr>
                                        <td>#<?php echo $c['id']; ?></td>
                                        <td><?php echo date('d/m/Y H:i:s', strtotime($c['created_at'])); ?></td>
                                        <td><strong><?php echo htmlspecialchars($c['phone_number']); ?></strong></td>
                                        <td>
                                            <span style="font-weight:600; color:#1e293b;">
                                                <?php echo htmlspecialchars($c['agent_name'] ?: "Agente #{$c['agent_id']}"); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                                $dur = (int)$c['duration_seconds'];
                                                echo sprintf("%02d:%02d", floor($dur / 60), $dur % 60);
                                            ?>
                                        </td>
                                        <td>
                                            <span class="tech-tag"><i class="fa fa-bolt"></i> <?php echo htmlspecialchars($c['llm_provider'] ?: 'Groq'); ?></span>
                                            <span class="tech-tag"><i class="fa fa-volume-up"></i> <?php echo htmlspecialchars($c['voice_provider'] ?: 'OpenAI'); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge" style="background:#e0e7ff; color:#3730a3; font-weight:600;">
                                                <?php echo htmlspecialchars($c['qualification'] ?: 'Atendida'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($c['status'] == 'completed'): ?>
                                                <span class="badge-status badge-completed"><i class="fa fa-check"></i> Atendida</span>
                                            <?php elseif ($c['status'] == 'busy'): ?>
                                                <span class="badge-status badge-busy"><i class="fa fa-times"></i> Ocupada</span>
                                            <?php else: ?>
                                                <span class="badge-status badge-ringing"><i class="fa fa-phone"></i> <?php echo htmlspecialchars($c['status']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align:center;">
                                            <button type="button" class="btn btn-info btn-xs btn-view-transcript" data-id="<?php echo $c['id']; ?>">
                                                <i class="fa fa-comments"></i> Ver Diálogo
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Box Informativo de Integração de API -->
            <div class="box box-solid box-info">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-code"></i> Integração REST API para Desenvolvedores</h3>
                </div>
                <div class="box-body">
                    <p>Você pode acessar os registros e transcrições de qualquer chamada externamente através de requisições HTTP REST JSON:</p>
                    <pre style="background:#1e293b; color:#38bdf8; padding:12px; border-radius:6px; font-size:12px;">
# 1. Listar Chamadas:
GET https://<?php echo $_SERVER['HTTP_HOST']; ?>/api_ai_calls.php?action=list&limit=50

# 2. Obter Transcrição Completa de uma Chamada:
GET https://<?php echo $_SERVER['HTTP_HOST']; ?>/api_ai_calls.php?action=get&id=1

# 3. Disparar Chamada Externa via API:
POST https://<?php echo $_SERVER['HTTP_HOST']; ?>/api_ai_calls.php?action=dial
Payload: {"agent_id": 1, "phone_number": "21984354821"}</pre>
                </div>
            </div>
        </section>
    </aside>
</div>

<!-- Modal de Transcrição Completa da Conversa -->
<div class="modal fade" id="transcriptModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius:10px;">
            <div class="modal-header" style="background:#0f172a; color:#fff; border-top-left-radius:10px; border-top-right-radius:10px;">
                <button type="button" class="close" data-dismiss="modal" style="color:#fff; opacity:0.8;">&times;</button>
                <h4 class="modal-title" id="transcriptModalTitle"><i class="fa fa-comments"></i> Transcrição da Chamada</h4>
            </div>
            <div class="modal-body">
                <div id="callDetailsHeader" style="display:flex; justify-content:space-between; margin-bottom:15px; padding-bottom:10px; border-bottom:1px solid #e2e8f0; font-size:13px; color:#475569;">
                    <!-- Preenchido via JS -->
                </div>

                <div class="chat-container" id="chatDialogueContainer">
                    <div style="text-align:center; padding:20px; color:#94a3b8;">
                        <i class="fa fa-spinner fa-spin fa-2x"></i><br>Carregando diálogo...
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="background:#f8fafc;">
                <button type="button" class="btn btn-default" data-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<?php print $ui->standardizedThemeJS(); ?>
<script type="text/javascript">
$(document).ready(function() {
    $('.btn-view-transcript').on('click', function() {
        var callId = $(this).data('id');
        $('#transcriptModal').modal('show');
        $('#chatDialogueContainer').html('<div style="text-align:center; padding:30px; color:#94a3b8;"><i class="fa fa-spinner fa-spin fa-2x"></i><br>Carregando diálogo...</div>');
        $('#callDetailsHeader').html('');

        $.ajax({
            url: 'php/GetAICallTranscript.php',
            type: 'GET',
            data: { id: callId },
            dataType: 'json',
            success: function(res) {
                if (res.status === 1) {
                    var c = res.call;
                    var dur = parseInt(c.duration_seconds || 0);
                    var durFmt = Math.floor(dur / 60) + 'm ' + (dur % 60) + 's';

                    $('#callDetailsHeader').html(
                        '<div><strong>Telefone:</strong> ' + c.phone_number + ' | <strong>Agente:</strong> ' + (c.agent_name || ('#' + c.agent_id)) + '</div>' +
                        '<div><strong>Duração:</strong> ' + durFmt + ' | <strong>Data:</strong> ' + c.created_at + '</div>'
                    );

                    var html = '';
                    var list = res.transcripts || [];

                    if (list.length === 0) {
                        html = '<div style="text-align:center; padding:30px; color:#94a3b8;"><i class="fa fa-info-circle fa-2x"></i><br>Nenhum diálogo gravado para esta chamada.</div>';
                    } else {
                        list.forEach(function(msg) {
                            if (msg.role === 'system') return; // Oculta system prompt no chat

                            var roleClass = (msg.role === 'user') ? 'user' : 'assistant';
                            var authorName = (msg.role === 'user') ? '👤 Cliente' : '🤖 ' + (c.agent_name || 'Agente IA');

                            html += '<div class="chat-bubble ' + roleClass + '">';
                            html += '  <div class="bubble-author">' + authorName + '</div>';
                            html += '  <div class="bubble-content">' + $('<div>').text(msg.content).html() + '</div>';
                            html += '</div>';
                        });
                    }

                    $('#chatDialogueContainer').html(html);
                    $('#chatDialogueContainer').scrollTop($('#chatDialogueContainer')[0].scrollHeight);
                } else {
                    $('#chatDialogueContainer').html('<div class="alert alert-danger">' + res.message + '</div>');
                }
            },
            error: function() {
                $('#chatDialogueContainer').html('<div class="alert alert-danger">Erro ao carregar transcrição da chamada.</div>');
            }
        });
    });
});
</script>
</body>
</html>
