<?php
/**
 * @file        ai_call_logs.php
 * @brief       Dialog DDM - AI Call Logs, Transcripts and Analytics (Design System 2.0)
 * @copyright   (c) Dialog DDM - Grupo DDM
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
$tabulationFilter = isset($_GET['tabulation']) ? trim($_GET['tabulation']) : null;

$agents = $aiHandler->getAllAgents();
$stats = $aiHandler->getCallStats();
$callLogs = $aiHandler->getCallLogs(100, 0, $agentFilter, $phoneFilter, $statusFilter, $tabulationFilter);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Dialog DDM - Histórico & Transcrições de Voz IA</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>

    <?php 
        print $ui->standardizedThemeCSS(); 
        print $ui->creamyThemeCSS();
        print $ui->dataTablesTheme();
    ?>
    <link href="css/style.css" rel="stylesheet" type="text/css" />
    <link href="css/dialog_ddm.css" rel="stylesheet" type="text/css" />
    <style>
        .ddm-stat-card {
            background: var(--ddm-surface);
            border: 1px solid var(--ddm-border);
            border-radius: var(--ddm-radius-lg);
            padding: 20px 24px;
            margin-bottom: 20px;
            box-shadow: var(--ddm-shadow-xs);
            position: relative;
            overflow: hidden;
            transition: all 0.2s;
        }
        .ddm-stat-card:hover {
            box-shadow: var(--ddm-shadow-sm);
            border-color: var(--ddm-border-strong);
        }
        .ddm-stat-card .stat-icon-bg {
            position: absolute;
            right: 18px;
            bottom: 12px;
            font-size: 42px;
            color: var(--ddm-surface-subtle);
            pointer-events: none;
        }
        .ddm-stat-card h3 {
            font-size: 28px;
            font-weight: 800;
            margin: 0 0 4px 0;
            color: var(--ddm-text-primary);
            letter-spacing: -0.5px;
        }
        .ddm-stat-card p {
            font-size: 12px;
            margin: 0;
            text-transform: uppercase;
            font-weight: 700;
            color: var(--ddm-text-secondary);
            letter-spacing: 0.5px;
        }
        
        .chat-container {
            max-height: 440px;
            overflow-y: auto;
            padding: 18px;
            background: var(--ddm-surface-subtle);
            border-radius: var(--ddm-radius-md);
            border: 1px solid var(--ddm-border);
        }
        .chat-bubble {
            margin-bottom: 16px;
            display: flex;
            flex-direction: column;
        }
        .chat-bubble.user { align-items: flex-start; }
        .chat-bubble.assistant { align-items: flex-end; }
        .chat-bubble.system { align-items: center; }
        .bubble-content {
            max-width: 80%;
            padding: 12px 16px;
            border-radius: 14px;
            font-size: 13.5px;
            line-height: 1.5;
            box-shadow: 0 1px 2px rgba(0,0,0,0.04);
        }
        .chat-bubble.user .bubble-content {
            background: #FFFFFF;
            color: var(--ddm-text-primary);
            border: 1px solid var(--ddm-border);
            border-bottom-left-radius: 3px;
        }
        .chat-bubble.assistant .bubble-content {
            background: var(--ddm-primary-light);
            color: #9A3412;
            border: 1px solid var(--ddm-primary-border);
            border-bottom-right-radius: 3px;
        }
        .bubble-author {
            font-size: 11px;
            margin-bottom: 4px;
            color: var(--ddm-text-muted);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
    </style>
</head>

<?php print $ui->creamyBody(); ?>
<div class="wrapper">
    <?php print $ui->creamyHeader($user); ?>
    <?php print $ui->getSidebar($user->getUserId(), $user->getUserName(), $user->getUserRole(), $user->getUserAvatar()); ?>

    <aside class="right-side">
        <div class="ddm-container">
            
            <!-- Breadcrumbs -->
            <div class="ddm-breadcrumbs">
                <a href="index.php"><i class="fa fa-home"></i> Dialog DDM</a>
                <span class="separator">/</span>
                <a href="ai_agents.php">Agentes de IA</a>
                <span class="separator">/</span>
                <span class="current">Histórico & Gravações</span>
            </div>

            <!-- Top Header -->
            <div class="ddm-header">
                <div class="ddm-header-left">
                    <div class="ddm-agent-avatar">
                        <i class="fa fa-history"></i>
                    </div>
                    <div>
                        <div style="font-size: 22px; font-weight: 800; color: var(--ddm-text-primary); letter-spacing: -0.4px;">
                            Histórico & Transcrições de Voz IA
                        </div>
                        <div style="font-size: 13px; color: var(--ddm-text-secondary); margin-top: 2px;">
                            Acompanhe gravações de áudio, transcrições completas e diagnósticos de chamadas em tempo real.
                        </div>
                    </div>
                </div>
                
                <div class="ddm-header-actions">
                    <a href="api_ai_calls.php?action=list" target="_blank" class="ddm-btn ddm-btn-secondary">
                        <i class="fa fa-code"></i> API REST
                    </a>
                    <button type="button" class="ddm-btn ddm-btn-secondary" onclick="location.reload();">
                        <i class="fa fa-refresh"></i> Atualizar
                    </button>
                </div>
            </div>

            <!-- Metric Cards -->
            <div class="row">
                <div class="col-md-2 col-sm-4 col-xs-6">
                    <div class="ddm-stat-card">
                        <i class="fa fa-phone stat-icon-bg"></i>
                        <p>Total Ligações</p>
                        <h3><?php echo number_format($stats['total']); ?></h3>
                    </div>
                </div>
                <div class="col-md-2 col-sm-4 col-xs-6">
                    <div class="ddm-stat-card">
                        <i class="fa fa-check-circle stat-icon-bg"></i>
                        <p>Taxa Atendimento</p>
                        <h3 style="color:#059669;">
                            <?php 
                                $rate = $stats['total'] > 0 ? round(($stats['answered'] / $stats['total']) * 100) : 100;
                                echo "{$rate}%";
                            ?>
                        </h3>
                    </div>
                </div>
                <div class="col-md-3 col-sm-4 col-xs-6">
                    <div class="ddm-stat-card">
                        <i class="fa fa-clock-o stat-icon-bg"></i>
                        <p>Duração Total de Voz</p>
                        <h3>
                            <?php 
                                $min = floor($stats['duration'] / 60);
                                $sec = $stats['duration'] % 60;
                                echo sprintf("%02dm %02ds", $min, $sec);
                            ?>
                        </h3>
                    </div>
                </div>
                <div class="col-md-2 col-sm-6 col-xs-6">
                    <div class="ddm-stat-card">
                        <i class="fa fa-star stat-icon-bg"></i>
                        <p>Leads Qualificados</p>
                        <h3 style="color:var(--ddm-primary);"><?php echo number_format($stats['leads']); ?></h3>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 col-xs-12">
                    <div class="ddm-stat-card" style="border-left: 3px solid var(--ddm-primary);">
                        <i class="fa fa-money stat-icon-bg"></i>
                        <p>Custo Total de IA (Est.)</p>
                        <h3 style="color:var(--ddm-text-primary);">
                            R$ <?php echo number_format((float)($stats['cost'] ?? 0), 2, ',', '.'); ?>
                        </h3>
                    </div>
                </div>
            </div>

            <!-- Filters and Table Box -->
            <div class="ddm-section-box" style="padding: 20px 24px;">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom: 20px;">
                    <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin:0;">
                        <div style="min-width: 170px;">
                            <select name="agent_id" class="ddm-select">
                                <option value="">Todos os Agentes</option>
                                <?php foreach ($agents as $a): ?>
                                    <option value="<?php echo $a['agent_id']; ?>" <?php echo ($agentFilter == $a['agent_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($a['agent_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div style="min-width: 150px;">
                            <input type="text" name="phone" class="ddm-input" placeholder="Buscar Telefone..." value="<?php echo htmlspecialchars($phoneFilter ?: ''); ?>">
                        </div>

                        <div style="min-width: 170px;">
                            <select name="tabulation" class="ddm-select">
                                <option value="">Todas as Tabulações</option>
                                <option value="Venda Concluída" <?php echo ($tabulationFilter == 'Venda Concluída') ? 'selected' : ''; ?>>✅ Venda Concluída</option>
                                <option value="Interessado" <?php echo ($tabulationFilter == 'Interessado') ? 'selected' : ''; ?>>⭐ Interessado</option>
                                <option value="Dúvida Esclarecida" <?php echo ($tabulationFilter == 'Dúvida Esclarecida') ? 'selected' : ''; ?>>ℹ️ Dúvida Esclarecida</option>
                                <option value="Retornar Mais Tarde" <?php echo ($tabulationFilter == 'Retornar Mais Tarde') ? 'selected' : ''; ?>>⏰ Retornar Mais Tarde</option>
                                <option value="Sem Interesse" <?php echo ($tabulationFilter == 'Sem Interesse') ? 'selected' : ''; ?>>❌ Sem Interesse</option>
                                <option value="Caixa Postal" <?php echo ($tabulationFilter == 'Caixa Postal') ? 'selected' : ''; ?>>📵 Caixa Postal</option>
                                <option value="Número Inválido" <?php echo ($tabulationFilter == 'Número Inválido') ? 'selected' : ''; ?>>🚫 Número Inválido</option>
                                <option value="Atendida" <?php echo ($tabulationFilter == 'Atendida') ? 'selected' : ''; ?>>📞 Atendida (Geral)</option>
                            </select>
                        </div>

                        <div style="min-width: 150px;">
                            <select name="status" class="ddm-select">
                                <option value="">Todos os Status</option>
                                <option value="completed" <?php echo ($statusFilter == 'completed') ? 'selected' : ''; ?>>Completada / Atendida</option>
                                <option value="busy" <?php echo ($statusFilter == 'busy') ? 'selected' : ''; ?>>Ocupada / Rejeitada</option>
                                <option value="ringing" <?php echo ($statusFilter == 'ringing') ? 'selected' : ''; ?>>Chamando</option>
                            </select>
                        </div>

                        <button type="submit" class="ddm-btn ddm-btn-primary ddm-btn-sm"><i class="fa fa-filter"></i> Filtrar</button>
                        <a href="ai_call_logs.php" class="ddm-btn ddm-btn-secondary ddm-btn-sm"><i class="fa fa-eraser"></i> Limpar</a>
                    </form>
                </div>

                <div style="overflow-x:auto;">
                    <table class="table table-bordered table-striped ddm-tools-table" id="callLogsTable" width="100%">
                        <thead>
                            <tr>
                                <th style="width: 50px;">ID</th>
                                <th>Data & Hora</th>
                                <th>Telefone Destino</th>
                                <th>Agente de IA</th>
                                <th>Duração</th>
                                <th>Custo</th>
                                <th>Pipeline</th>
                                <th>Tabulação IA</th>
                                <th>Sentimento</th>
                                <th>Status</th>
                                <th style="text-align:center; width: 140px;">Gravação</th>
                                <th style="text-align:right; width: 150px;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($callLogs)): ?>
                                <tr>
                                    <td colspan="12" style="text-align:center; padding:40px; color:var(--ddm-text-muted);">
                                        <i class="fa fa-phone-square" style="font-size:36px; display:block; margin-bottom:12px;"></i>
                                        Nenhuma chamada registrada no período selecionado.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($callLogs as $c): 
                                    $recUrl = !empty($c['recording_url']) ? $c['recording_url'] : '';
                                    if (empty($recUrl) && !empty($c['call_id'])) {
                                        $cleanId = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $c['call_id']);
                                        $possible = 'recordings/ai_call_' . $cleanId . '.wav';
                                        if (file_exists(__DIR__ . '/' . $possible)) {
                                            $recUrl = $possible;
                                        }
                                    }
                                    $costVal = isset($c['cost_estimate']) ? (float)$c['cost_estimate'] : 0.00;
                                    $tabulation = !empty($c['tabulation']) ? $c['tabulation'] : (!empty($c['qualification']) ? $c['qualification'] : 'Atendida');
                                    $sentiment = !empty($c['sentiment']) ? $c['sentiment'] : 'Neutro';
                                ?>
                                    <tr>
                                        <td><span class="ddm-badge ddm-badge-id">#<?php echo $c['id']; ?></span></td>
                                        <td style="font-size:12px; color:var(--ddm-text-secondary);"><?php echo date('d/m/Y H:i:s', strtotime($c['created_at'])); ?></td>
                                        <td><strong><?php echo htmlspecialchars($c['phone_number']); ?></strong></td>
                                        <td>
                                            <span style="font-weight:700; color:var(--ddm-text-primary); font-size:13px;">
                                                <?php echo htmlspecialchars($c['agent_name'] ?: "Agente #{$c['agent_id']}"); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="ddm-badge">
                                                <?php 
                                                    $dur = (int)$c['duration_seconds'];
                                                    echo sprintf("%02d:%02d", floor($dur / 60), $dur % 60);
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="ddm-badge ddm-badge-orange" title="Custo estimado total da chamada">
                                                R$ <?php echo number_format($costVal, 4, ',', '.'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="ddm-badge ddm-badge-id"><i class="fa fa-bolt"></i> <?php echo htmlspecialchars($c['llm_provider'] ?: 'groq'); ?></span>
                                            <span class="ddm-badge ddm-badge-orange"><i class="fa fa-volume-up"></i> <?php echo htmlspecialchars($c['voice_provider'] ?: 'cartesia'); ?></span>
                                        </td>
                                        <td>
                                            <?php if (stripos($tabulation, 'Venda') !== false): ?>
                                                <span class="ddm-badge ddm-badge-active"><i class="fa fa-check-circle text-green"></i> <?php echo htmlspecialchars($tabulation); ?></span>
                                            <?php elseif (stripos($tabulation, 'Interessado') !== false): ?>
                                                <span class="ddm-badge ddm-badge-active"><i class="fa fa-star text-green"></i> <?php echo htmlspecialchars($tabulation); ?></span>
                                            <?php elseif (stripos($tabulation, 'Dúvida') !== false): ?>
                                                <span class="ddm-badge ddm-badge-id"><i class="fa fa-info-circle"></i> <?php echo htmlspecialchars($tabulation); ?></span>
                                            <?php elseif (stripos($tabulation, 'Retornar') !== false || stripos($tabulation, 'Agendamento') !== false): ?>
                                                <span class="ddm-badge ddm-badge-orange"><i class="fa fa-clock-o"></i> <?php echo htmlspecialchars($tabulation); ?></span>
                                            <?php elseif (stripos($tabulation, 'Sem Interesse') !== false || stripos($tabulation, 'Recusa') !== false): ?>
                                                <span class="ddm-badge ddm-badge-inactive"><i class="fa fa-times-circle"></i> <?php echo htmlspecialchars($tabulation); ?></span>
                                            <?php elseif (stripos($tabulation, 'Caixa') !== false || stripos($tabulation, 'Ocupado') !== false): ?>
                                                <span class="ddm-badge ddm-badge-inactive"><i class="fa fa-microphone-slash"></i> <?php echo htmlspecialchars($tabulation); ?></span>
                                            <?php else: ?>
                                                <span class="ddm-badge"><?php echo htmlspecialchars($tabulation); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($sentiment == 'Positivo'): ?>
                                                <span class="ddm-badge ddm-badge-active" title="Sentimento do Cliente: Positivo">😊 Positivo</span>
                                            <?php elseif ($sentiment == 'Negativo'): ?>
                                                <span class="ddm-badge ddm-badge-inactive" title="Sentimento do Cliente: Negativo">😠 Negativo</span>
                                            <?php else: ?>
                                                <span class="ddm-badge" title="Sentimento do Cliente: Neutro">😐 Neutro</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($c['status'] == 'completed'): ?>
                                                <span class="ddm-badge ddm-badge-active"><span class="ddm-dot-indicator ddm-dot-active"></span> Atendida</span>
                                            <?php elseif ($c['status'] == 'busy'): ?>
                                                <span class="ddm-badge ddm-badge-inactive"><span class="ddm-dot-indicator ddm-dot-inactive"></span> Ocupada</span>
                                            <?php else: ?>
                                                <span class="ddm-badge"><i class="fa fa-phone"></i> <?php echo htmlspecialchars($c['status']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align:center; white-space:nowrap;">
                                            <?php if (!empty($recUrl)): ?>
                                                <button type="button" class="ddm-btn ddm-btn-secondary ddm-btn-xs btn-quick-play" data-audio="<?php echo htmlspecialchars($recUrl); ?>" title="Ouvir Gravação">
                                                    <i class="fa fa-play text-orange"></i> Áudio
                                                </button>
                                                <a href="<?php echo htmlspecialchars($recUrl); ?>" download="gravacao_call_#<?php echo $c['id']; ?>_<?php echo $c['phone_number']; ?>.wav" class="ddm-btn ddm-btn-secondary ddm-btn-xs" title="Baixar Áudio (.wav)">
                                                    <i class="fa fa-download text-green"></i>
                                                </a>
                                            <?php else: ?>
                                                <span style="color:var(--ddm-text-muted); font-size:11px;">Sem áudio</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align:right;">
                                            <button type="button" class="ddm-btn ddm-btn-secondary ddm-btn-xs btn-view-transcript" data-id="<?php echo $c['id']; ?>">
                                                <i class="fa fa-comments text-orange"></i> Diálogo & IA
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </aside>
</div>

<!-- Modal de Transcrição Completa da Conversa, Análise de IA e Player de Áudio -->
<div class="modal fade" id="transcriptModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content" style="border-radius: var(--ddm-radius-lg); border: 1px solid var(--ddm-border); overflow:hidden; box-shadow: var(--ddm-shadow-xl);">
            <div class="modal-header" style="background: var(--ddm-surface); border-bottom: 1px solid var(--ddm-border); padding: 18px 24px;">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="transcriptModalTitle" style="font-weight:800; font-size:16px; color:var(--ddm-text-primary);">
                    <i class="fa fa-comments text-orange"></i> Detalhes, Análise de IA & Transcrição da Chamada
                </h4>
            </div>
            <div class="modal-body" style="padding: 24px; background: var(--ddm-surface);">
                <div id="callDetailsHeader" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:18px; padding-bottom:14px; border-bottom:1px solid var(--ddm-border); font-size:13px; color:var(--ddm-text-secondary);">
                    <!-- Preenchido via JS -->
                </div>

                <!-- Análise Inteligente da Chamada (OpenAI / Groq) -->
                <div id="modalAnalysisSection" style="margin-bottom:18px; padding:16px 20px; background:var(--ddm-surface-subtle); border-radius:var(--ddm-radius-md); border:1px solid var(--ddm-border);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; flex-wrap:wrap; gap:8px;">
                        <span style="font-weight:700; font-size:13px; color:var(--ddm-text-primary); display:flex; align-items:center; gap:6px;">
                            <i class="fa fa-magic text-orange"></i> Diagnóstico Inteligente & Tabulação (IA)
                        </span>
                        <div id="modalBadgesContainer" style="display:flex; gap:6px;">
                            <!-- Badges preenchidos via JS -->
                        </div>
                    </div>
                    <div style="font-size:13px; color:var(--ddm-text-primary); margin-bottom:8px; line-height:1.5;" id="modalCallSummary">
                        <!-- Resumo Executivo preenchido via JS -->
                    </div>
                    <div id="modalActionContainer" style="font-size:12px; color:var(--ddm-text-secondary); background:var(--ddm-surface); border:1px dashed var(--ddm-border); border-radius:6px; padding:8px 12px; display:none;">
                        <strong style="color:var(--ddm-primary);"><i class="fa fa-arrow-circle-right"></i> Próxima Ação Sugerida:</strong> <span id="modalActionText"></span>
                    </div>
                </div>

                <!-- Player e Download de Áudio da Gravação -->
                <div id="modalAudioSection" style="margin-bottom:18px; padding:16px 20px; background:var(--ddm-surface-subtle); border-radius:var(--ddm-radius-md); border:1px solid var(--ddm-border); display:none;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                        <span style="font-weight:700; font-size:13px; color:var(--ddm-text-primary); display:flex; align-items:center; gap:6px;">
                            <i class="fa fa-volume-up text-orange"></i> Gravação Completa da Chamada
                        </span>
                        <a id="btnDownloadAudio" href="#" download="gravacao_chamada.wav" class="ddm-btn ddm-btn-primary ddm-btn-xs">
                            <i class="fa fa-download"></i> Baixar Áudio (.wav)
                        </a>
                    </div>
                    <audio id="modalAudioPlayer" controls style="width:100%; height:38px; outline:none; border-radius:6px;"></audio>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                    <span style="font-weight:700; font-size:12px; color:var(--ddm-text-secondary); text-transform:uppercase; letter-spacing:0.5px;">Diálogo da Conversa</span>
                    <button type="button" class="ddm-btn ddm-btn-secondary ddm-btn-xs" id="btnCopyTranscript">
                        <i class="fa fa-copy"></i> Copiar Texto
                    </button>
                </div>

                <div class="chat-container" id="chatDialogueContainer">
                    <div style="text-align:center; padding:25px; color:var(--ddm-text-muted);">
                        <i class="fa fa-spinner fa-spin fa-2x"></i><br>Carregando diálogo...
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="background: var(--ddm-surface-subtle); border-top: 1px solid var(--ddm-border); padding: 14px 24px;">
                <button type="button" class="ddm-btn ddm-btn-secondary" data-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<?php print $ui->standardizedThemeJS(); ?>
<script type="text/javascript">
$(document).ready(function() {
    var rawTranscriptText = '';

    // Quick Play Audio from table
    $('.btn-quick-play').on('click', function() {
        var audioUrl = $(this).data('audio');
        if (audioUrl) {
            var audio = new Audio(audioUrl);
            audio.play();
        }
    });

    // View Transcript Modal
    $('.btn-view-transcript').on('click', function() {
        var callId = $(this).data('id');
        $('#transcriptModal').modal('show');
        $('#chatDialogueContainer').html('<div style="text-align:center; padding:30px; color:var(--ddm-text-muted);"><i class="fa fa-spinner fa-spin fa-2x"></i><br>Carregando diálogo...</div>');
        $('#callDetailsHeader').html('');
        $('#modalBadgesContainer').html('');
        $('#modalCallSummary').html('<em>Carregando análise...</em>');
        $('#modalActionContainer').hide();
        $('#modalAudioSection').hide();
        var player = document.getElementById('modalAudioPlayer');
        if (player) {
            player.pause();
            player.src = '';
        }
        rawTranscriptText = '';

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
                    var cost = parseFloat(c.cost_estimate || 0).toFixed(4);

                    $('#callDetailsHeader').html(
                        '<div><strong>Telefone:</strong> ' + c.phone_number + ' &nbsp;|&nbsp; <strong>Agente:</strong> ' + (c.agent_name || ('#' + c.agent_id)) + '</div>' +
                        '<div><strong>Duração:</strong> ' + durFmt + ' &nbsp;|&nbsp; <strong>Custo Est.:</strong> R$ ' + cost + ' &nbsp;|&nbsp; <strong>Data:</strong> ' + c.created_at + '</div>'
                    );

                    // Análise de IA: Tabulação, Sentimento, Resumo e Ação
                    var tab = c.tabulation || c.qualification || 'Atendida';
                    var sent = c.sentiment || 'Neutro';
                    var summary = c.call_summary || 'Conversa concluída pelo agente de voz IA.';
                    var action = c.action_needed || '';

                    var tabBadgeClass = 'ddm-badge';
                    if (tab.toLowerCase().indexOf('venda') !== -1 || tab.toLowerCase().indexOf('interessado') !== -1) {
                        tabBadgeClass = 'ddm-badge ddm-badge-active';
                    } else if (tab.toLowerCase().indexOf('retornar') !== -1 || tab.toLowerCase().indexOf('agendamento') !== -1) {
                        tabBadgeClass = 'ddm-badge ddm-badge-orange';
                    } else if (tab.toLowerCase().indexOf('sem interesse') !== -1 || tab.toLowerCase().indexOf('recusa') !== -1 || tab.toLowerCase().indexOf('caixa') !== -1) {
                        tabBadgeClass = 'ddm-badge ddm-badge-inactive';
                    }

                    var sentEmoji = '😐';
                    var sentBadgeClass = 'ddm-badge';
                    if (sent === 'Positivo') {
                        sentEmoji = '😊';
                        sentBadgeClass = 'ddm-badge ddm-badge-active';
                    } else if (sent === 'Negativo') {
                        sentEmoji = '😠';
                        sentBadgeClass = 'ddm-badge ddm-badge-inactive';
                    }

                    var badgesHtml = '<span class="' + tabBadgeClass + '">📌 ' + tab + '</span> ' +
                                     '<span class="' + sentBadgeClass + '">' + sentEmoji + ' ' + sent + '</span>';
                    $('#modalBadgesContainer').html(badgesHtml);

                    $('#modalCallSummary').html('<strong>Resumo da Ligação:</strong> ' + $('<div>').text(summary).html());

                    if (action && action.trim() !== '') {
                        $('#modalActionText').text(action);
                        $('#modalActionContainer').show();
                    } else {
                        $('#modalActionContainer').hide();
                    }

                    // Setup Audio Player and Download button
                    var audioUrl = res.audio_url || c.recording_url;
                    if (audioUrl) {
                        $('#modalAudioPlayer').attr('src', audioUrl);
                        $('#btnDownloadAudio').attr('href', audioUrl).attr('download', 'gravacao_call_' + c.id + '_' + c.phone_number + '.wav');
                        $('#modalAudioSection').slideDown();
                    } else {
                        $('#modalAudioSection').hide();
                    }

                    var html = '';
                    var list = res.transcripts || [];
                    var textLines = [];

                    if (list.length === 0) {
                        html = '<div style="text-align:center; padding:30px; color:var(--ddm-text-muted);"><i class="fa fa-info-circle fa-2x"></i><br>Nenhum diálogo textual gravado para esta chamada.</div>';
                    } else {
                        list.forEach(function(msg) {
                            if (msg.role === 'system') return;

                            var isUser = (msg.role === 'user');
                            var roleClass = isUser ? 'user' : 'assistant';
                            var authorName = isUser ? '👤 Cliente' : '🤖 ' + (c.agent_name || 'Agente IA');

                            textLines.push(authorName + ': ' + msg.content);

                            html += '<div class="chat-bubble ' + roleClass + '">';
                            html += '  <div class="bubble-author">' + authorName + '</div>';
                            html += '  <div class="bubble-content">' + $('<div>').text(msg.content).html() + '</div>';
                            html += '</div>';
                        });
                    }

                    rawTranscriptText = textLines.join('\n\n');
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

    // Copy Transcript Button
    $('#btnCopyTranscript').on('click', function() {
        if (!rawTranscriptText) return;
        navigator.clipboard.writeText(rawTranscriptText).then(function() {
            var btn = $('#btnCopyTranscript');
            btn.html('<i class="fa fa-check text-green"></i> Copiado!');
            setTimeout(function() {
                btn.html('<i class="fa fa-copy"></i> Copiar Texto');
            }, 2000);
        });
    });

    // Stop audio when modal closes
    $('#transcriptModal').on('hidden.bs.modal', function () {
        var player = document.getElementById('modalAudioPlayer');
        if (player) {
            player.pause();
        }
    });
});
</script>
</body>
</html>
