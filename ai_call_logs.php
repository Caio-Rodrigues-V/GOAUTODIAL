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
    <title>DIALog DDM - Histórico & Transcrições de Voz IA</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>

    <?php 
        print $ui->standardizedThemeCSS(); 
        print $ui->creamyThemeCSS();
        print $ui->dataTablesTheme();
    ?>
    <link href="css/dialog_ddm.css?v=2.6.0" rel="stylesheet" type="text/css" />
</head>

<?php print $ui->creamyBody(); ?>
<div class="wrapper">
    <?php print $ui->creamyHeader($user); ?>
    <?php print $ui->getSidebar($user->getUserId(), $user->getUserName(), $user->getUserRole(), $user->getUserAvatar()); ?>

    <aside class="right-side">
        <div class="ddm-container">
            
            <!-- Breadcrumbs -->
            <div class="ddm-breadcrumbs">
                <a href="index.php">Dashboard</a>
                <span class="separator">/</span>
                <span class="current">Histórico & Transcrições</span>
            </div>

            <!-- Page Header -->
            <div class="ddm-page-header">
                <div>
                    <h1 class="ddm-page-title">Histórico & Transcrições de Voz IA</h1>
                    <p class="ddm-page-subtitle">Acompanhe gravações de áudio, transcrições completas e diagnósticos de chamadas em tempo real.</p>
                </div>
                
                <div class="ddm-page-actions">
                    <a href="api_ai_calls.php?action=list" target="_blank" class="ddm-btn ddm-btn-secondary">
                        <i data-lucide="code-2" style="width:16px;height:16px;"></i>
                        <span>API REST</span>
                    </a>
                    <button type="button" class="ddm-btn ddm-btn-secondary" onclick="location.reload();">
                        <i data-lucide="rotate-cw" style="width:16px;height:16px;"></i>
                        <span>Atualizar</span>
                    </button>
                </div>
            </div>

            <!-- Minimalist Metric Cards -->
            <div class="row">
                <div class="col-md-2 col-sm-4 col-xs-6">
                    <div class="ddm-stat-card">
                        <div class="stat-label">Total Ligações</div>
                        <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
                    </div>
                </div>
                <div class="col-md-2 col-sm-4 col-xs-6">
                    <div class="ddm-stat-card">
                        <div class="stat-label">Taxa Atendimento</div>
                        <div class="stat-value" style="color:var(--ddm-success-text);">
                            <?php 
                                $rate = $stats['total'] > 0 ? round(($stats['answered'] / $stats['total']) * 100) : 100;
                                echo "{$rate}%";
                            ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-4 col-xs-6">
                    <div class="ddm-stat-card">
                        <div class="stat-label">Duração Total de Voz</div>
                        <div class="stat-value">
                            <?php 
                                $min = floor($stats['duration'] / 60);
                                $sec = $stats['duration'] % 60;
                                echo sprintf("%02dm %02ds", $min, $sec);
                            ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-sm-6 col-xs-6">
                    <div class="ddm-stat-card">
                        <div class="stat-label">Leads Qualificados</div>
                        <div class="stat-value" style="color:var(--ddm-primary);"><?php echo number_format($stats['leads']); ?></div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 col-xs-12">
                    <div class="ddm-stat-card">
                        <div class="stat-label">Custo Total de IA (Est.)</div>
                        <div class="stat-value">
                            R$ <?php echo number_format((float)($stats['cost'] ?? 0), 2, ',', '.'); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Compact Filter Toolbar -->
            <div class="ddm-toolbar">
                <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; width:100%; margin:0;">
                    
                    <!-- Search Telefone First -->
                    <div class="ddm-search-input-wrapper" style="min-width: 220px; flex: 1;">
                        <i data-lucide="search" class="ddm-search-icon" style="width:16px;height:16px;"></i>
                        <input type="text" name="phone" class="ddm-input ddm-search-input" placeholder="Buscar por telefone..." value="<?php echo htmlspecialchars($phoneFilter ?: ''); ?>">
                    </div>

                    <!-- Agente Filter -->
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

                    <!-- Tabulação Filter -->
                    <div style="min-width: 180px;">
                        <select name="tabulation" class="ddm-select">
                            <option value="">Todas as Tabulações</option>
                            <option value="HUMAN_COMPLETED" <?php echo ($tabulationFilter == 'HUMAN_COMPLETED' || $tabulationFilter == 'Conversa Concluída') ? 'selected' : ''; ?>>Conversa Concluída</option>
                            <option value="BUSY_LATER" <?php echo ($tabulationFilter == 'BUSY_LATER' || $tabulationFilter == 'Ocupado / Pediu Retorno') ? 'selected' : ''; ?>>Ocupado / Pediu Retorno</option>
                            <option value="REFUSED" <?php echo ($tabulationFilter == 'REFUSED' || $tabulationFilter == 'Recusa / Não Quer Falar') ? 'selected' : ''; ?>>Recusa / Não Quer Falar</option>
                            <option value="WRONG_NUMBER" <?php echo ($tabulationFilter == 'WRONG_NUMBER' || $tabulationFilter == 'Número Errado / Engano') ? 'selected' : ''; ?>>Número Errado / Engano</option>
                            <option value="CALL_DROPPED" <?php echo ($tabulationFilter == 'CALL_DROPPED' || $tabulationFilter == 'Desligou no Início (< 5s)') ? 'selected' : ''; ?>>Desligou no Início (< 5s)</option>
                            <option value="MUTE_SILENCE" <?php echo ($tabulationFilter == 'MUTE_SILENCE' || $tabulationFilter == 'Mudo / Sem Áudio') ? 'selected' : ''; ?>>Mudo / Sem Áudio</option>
                            <option value="VOICEMAIL" <?php echo ($tabulationFilter == 'VOICEMAIL' || $tabulationFilter == 'Caixa Postal / Secretária') ? 'selected' : ''; ?>>Caixa Postal / Secretária</option>
                            <option value="NO_ANSWER" <?php echo ($tabulationFilter == 'NO_ANSWER' || $tabulationFilter == 'Não Atende') ? 'selected' : ''; ?>>Não Atende</option>
                            <option value="BUSY" <?php echo ($tabulationFilter == 'BUSY' || $tabulationFilter == 'Ocupado') ? 'selected' : ''; ?>>Linha Ocupada</option>
                            <option value="INVALID_NUMBER" <?php echo ($tabulationFilter == 'INVALID_NUMBER' || $tabulationFilter == 'Número Inválido') ? 'selected' : ''; ?>>Número Inválido</option>
                            <option value="TRANSFERRED" <?php echo ($tabulationFilter == 'TRANSFERRED' || $tabulationFilter == 'Transferida') ? 'selected' : ''; ?>>Transferida</option>
                        </select>
                    </div>

                    <!-- Status Filter -->
                    <div style="min-width: 160px;">
                        <select name="status" class="ddm-select">
                            <option value="">Todos os Status</option>
                            <option value="completed" <?php echo ($statusFilter == 'completed') ? 'selected' : ''; ?>>Atendida</option>
                            <option value="no_answer" <?php echo ($statusFilter == 'no_answer') ? 'selected' : ''; ?>>Não Atendeu</option>
                            <option value="voicemail" <?php echo ($statusFilter == 'voicemail') ? 'selected' : ''; ?>>Caixa Postal</option>
                            <option value="busy" <?php echo ($statusFilter == 'busy') ? 'selected' : ''; ?>>Ocupada</option>
                            <option value="dropped" <?php echo ($statusFilter == 'dropped') ? 'selected' : ''; ?>>Queda</option>
                            <option value="invalid_number" <?php echo ($statusFilter == 'invalid_number') ? 'selected' : ''; ?>>Número Inválido</option>
                            <option value="ringing" <?php echo ($statusFilter == 'ringing') ? 'selected' : ''; ?>>Chamando</option>
                        </select>
                    </div>

                    <!-- Actions -->
                    <button type="submit" class="ddm-btn ddm-btn-secondary" style="height:40px;">
                        <i data-lucide="filter" style="width:15px;height:15px;"></i>
                        <span>Filtrar</span>
                    </button>
                    <a href="ai_call_logs.php" class="ddm-btn ddm-btn-ghost" style="height:40px;">
                        <span>Limpar</span>
                    </a>
                </form>
            </div>

            <!-- Calls Table Container -->
            <div class="ddm-table-container">
                <div style="overflow-x:auto;">
                    <table class="table ddm-table" id="callLogsTable" width="100%">
                        <thead>
                            <tr>
                                <th style="width: 60px; min-width: 60px;">ID</th>
                                <th style="min-width: 130px;">Data & Hora</th>
                                <th style="min-width: 140px;">Telefone Destino</th>
                                <th style="min-width: 140px;">Agente IA</th>
                                <th style="min-width: 80px;">Duração</th>
                                <th style="min-width: 90px;">Custo</th>
                                <th style="min-width: 150px;">Tabulação IA</th>
                                <th style="min-width: 90px;">Sentimento</th>
                                <th style="min-width: 100px;">Status</th>
                                <th style="text-align:center; width: 130px; min-width: 130px; white-space: nowrap;">Gravação</th>
                                <th style="text-align:right; width: 140px; min-width: 140px; white-space: nowrap;">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($callLogs)): ?>
                                <tr>
                                    <td colspan="11" style="text-align:center; padding:48px 20px;">
                                        <div style="width:48px; height:48px; border-radius:12px; background:var(--ddm-surface-subtle); display:flex; align-items:center; justify-content:center; margin:0 auto 14px auto; color:var(--ddm-text-muted);">
                                            <i data-lucide="phone-missed" style="width:24px;height:24px;"></i>
                                        </div>
                                        <div style="color:var(--ddm-text-primary); font-size:15px; font-weight:600;">Nenhuma chamada registrada no período</div>
                                        <div style="color:var(--ddm-text-secondary); font-size:13px; margin-top:4px;">Ajuste os filtros de busca ou inicie um novo disparo em lote.</div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($callLogs as $c): 
                                    $tabCode = !empty($c['tabulation_code']) ? $c['tabulation_code'] : '';
                                    $tabulation = !empty($c['tabulation']) ? $c['tabulation'] : (!empty($c['qualification']) ? $c['qualification'] : 'Conversa Concluída');
                                    $sentiment = !empty($c['sentiment']) ? $c['sentiment'] : 'Neutro';
                                    $st = !empty($c['status']) ? strtolower($c['status']) : 'completed';
                                    
                                    $isUnanswered = ($st == 'no_answer' || $st == 'busy' || $st == 'invalid_number' || $tabCode == 'MUTE_SILENCE' || $tabCode == 'NO_ANSWER' || $tabCode == 'BUSY' || $tabCode == 'INVALID_NUMBER');

                                    $recUrl = '';
                                    if (!$isUnanswered) {
                                        $recUrl = !empty($c['recording_url']) ? $c['recording_url'] : '';
                                        if (empty($recUrl) && !empty($c['call_id'])) {
                                            $cleanId = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $c['call_id']);
                                            $possible = 'recordings/ai_call_' . $cleanId . '.wav';
                                            if (file_exists(__DIR__ . '/' . $possible)) {
                                                $recUrl = $possible;
                                            }
                                        }
                                    }
                                    $costVal = isset($c['cost_estimate']) ? (float)$c['cost_estimate'] : 0.00;
                                ?>
                                    <tr>
                                        <td><span class="ddm-badge ddm-badge-neutral" style="font-family:monospace;">#<?php echo $c['id']; ?></span></td>
                                        <td style="font-size:12.5px; color:var(--ddm-text-secondary);"><?php echo date('d/m/Y H:i', strtotime($c['created_at'])); ?></td>
                                        <td><strong style="font-family:monospace; font-size:13.5px;"><?php echo htmlspecialchars($c['phone_number']); ?></strong></td>
                                        <td>
                                            <span style="font-weight:600; color:var(--ddm-text-primary);">
                                                <?php echo htmlspecialchars($c['agent_name'] ?: "Agente #{$c['agent_id']}"); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span style="font-family:monospace; color:var(--ddm-text-secondary); font-weight:600;">
                                                <?php 
                                                    $dur = (int)$c['duration_seconds'];
                                                    echo sprintf("%02d:%02d", floor($dur / 60), $dur % 60);
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span style="font-size:12.5px; color:var(--ddm-text-secondary);">
                                                R$ <?php echo number_format($costVal, 4, ',', '.'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($tabCode == 'HUMAN_COMPLETED' || stripos($tabulation, 'Concluída') !== false || stripos($tabulation, 'Venda') !== false || stripos($tabulation, 'Interessado') !== false): ?>
                                                <span class="ddm-badge ddm-badge-success">
                                                    <span class="ddm-dot-indicator ddm-dot-success"></span> <?php echo htmlspecialchars($tabulation); ?>
                                                </span>
                                            <?php elseif ($tabCode == 'BUSY_LATER' || stripos($tabulation, 'Retornar') !== false || stripos($tabulation, 'Ocupado') !== false): ?>
                                                <span class="ddm-badge ddm-badge-warning">
                                                    <span class="ddm-dot-indicator ddm-dot-warning"></span> <?php echo htmlspecialchars($tabulation); ?>
                                                </span>
                                            <?php elseif ($tabCode == 'REFUSED' || stripos($tabulation, 'Recusa') !== false || stripos($tabulation, 'Sem Interesse') !== false): ?>
                                                <span class="ddm-badge ddm-badge-error">
                                                    <span class="ddm-dot-indicator ddm-dot-error"></span> <?php echo htmlspecialchars($tabulation); ?>
                                                </span>
                                            <?php elseif ($tabCode == 'VOICEMAIL' || stripos($tabulation, 'Caixa') !== false): ?>
                                                <span class="ddm-badge ddm-badge-warning">
                                                    <span class="ddm-dot-indicator ddm-dot-warning"></span> Caixa Postal
                                                </span>
                                            <?php else: ?>
                                                <span class="ddm-badge ddm-badge-neutral">
                                                    <?php echo htmlspecialchars($tabulation); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($sentiment == 'Positivo'): ?>
                                                <span class="ddm-badge ddm-badge-success">Positivo</span>
                                            <?php elseif ($sentiment == 'Negativo'): ?>
                                                <span class="ddm-badge ddm-badge-error">Negativo</span>
                                            <?php else: ?>
                                                <span class="ddm-badge ddm-badge-neutral">Neutro</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($st == 'completed'): ?>
                                                <span class="ddm-badge ddm-badge-success">Atendida</span>
                                            <?php elseif ($st == 'no_answer'): ?>
                                                <span class="ddm-badge ddm-badge-warning">Não Atendeu</span>
                                            <?php elseif ($st == 'voicemail'): ?>
                                                <span class="ddm-badge ddm-badge-warning">Caixa Postal</span>
                                            <?php elseif ($st == 'busy'): ?>
                                                <span class="ddm-badge ddm-badge-neutral">Ocupada</span>
                                            <?php elseif ($st == 'dropped'): ?>
                                                <span class="ddm-badge ddm-badge-warning">Queda</span>
                                            <?php elseif ($st == 'invalid_number'): ?>
                                                <span class="ddm-badge ddm-badge-error">Inválido</span>
                                            <?php else: ?>
                                                <span class="ddm-badge ddm-badge-neutral"><?php echo htmlspecialchars($st); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align:center; white-space:nowrap;">
                                            <?php if (!empty($recUrl)): ?>
                                                <button type="button" class="ddm-btn ddm-btn-secondary ddm-btn-xs btn-quick-play" data-audio="<?php echo htmlspecialchars($recUrl); ?>" title="Ouvir Gravação">
                                                    <i data-lucide="play" style="width:12px;height:12px;color:var(--ddm-primary);"></i>
                                                    <span>Áudio</span>
                                                </button>
                                                <a href="<?php echo htmlspecialchars($recUrl); ?>" download="gravacao_call_#<?php echo $c['id']; ?>_<?php echo $c['phone_number']; ?>.wav" class="ddm-btn ddm-btn-secondary ddm-btn-xs" title="Baixar Áudio (.wav)">
                                                    <i data-lucide="download" style="width:12px;height:12px;"></i>
                                                </a>
                                            <?php else: ?>
                                                <span style="color:var(--ddm-text-muted); font-size:12px;">Sem áudio</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align:right; white-space:nowrap;">
                                            <button type="button" class="ddm-btn ddm-btn-secondary ddm-btn-xs btn-view-transcript" data-id="<?php echo $c['id']; ?>">
                                                <i data-lucide="message-square" style="width:12px;height:12px;color:var(--ddm-primary);"></i>
                                                <span>Diálogo & IA</span>
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
        <div class="modal-content">
            <div class="modal-header">
                <div style="display:flex; align-items:center; gap:10px;">
                    <div style="width:32px; height:32px; border-radius:8px; background:var(--ddm-primary-soft); display:flex; align-items:center; justify-content:center; color:var(--ddm-primary);">
                        <i data-lucide="message-square" style="width:16px;height:16px;"></i>
                    </div>
                    <h4 class="modal-title" id="transcriptModalTitle">
                        Detalhes, Diagnóstico de IA & Transcrição da Chamada
                    </h4>
                </div>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div id="callDetailsHeader" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:18px; padding-bottom:14px; border-bottom:1px solid var(--ddm-border); font-size:13px; color:var(--ddm-text-secondary);">
                    <!-- Preenchido via JS -->
                </div>

                <!-- Análise Inteligente da Chamada (OpenAI / Groq) -->
                <div id="modalAnalysisSection" style="margin-bottom:18px; padding:16px 20px; background:var(--ddm-surface-subtle); border-radius:var(--ddm-radius-card); border:1px solid var(--ddm-border);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; flex-wrap:wrap; gap:8px;">
                        <span style="font-weight:600; font-size:13px; color:var(--ddm-text-primary); display:flex; align-items:center; gap:6px;">
                            <i data-lucide="sparkles" style="width:15px;height:15px;color:var(--ddm-primary);"></i>
                            Diagnóstico & Tabulação (IA)
                        </span>
                        <div id="modalBadgesContainer" style="display:flex; gap:6px;">
                            <!-- Badges preenchidos via JS -->
                        </div>
                    </div>
                    <div style="font-size:13px; color:var(--ddm-text-primary); margin-bottom:8px; line-height:1.5;" id="modalCallSummary">
                        <!-- Resumo Executivo preenchido via JS -->
                    </div>
                    <div id="modalActionContainer" style="font-size:12px; color:var(--ddm-text-secondary); background:var(--ddm-surface); border:1px dashed var(--ddm-border); border-radius:6px; padding:8px 12px; display:none;">
                        <strong style="color:var(--ddm-primary);"><i data-lucide="arrow-right" style="width:12px;height:12px;"></i> Próxima Ação Sugerida:</strong> <span id="modalActionText"></span>
                    </div>
                </div>

                <!-- Player e Download de Áudio da Gravação -->
                <div id="modalAudioSection" style="margin-bottom:18px; padding:16px 20px; background:var(--ddm-surface-subtle); border-radius:var(--ddm-radius-card); border:1px solid var(--ddm-border); display:none;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                        <span style="font-weight:600; font-size:13px; color:var(--ddm-text-primary); display:flex; align-items:center; gap:6px;">
                            <i data-lucide="volume-2" style="width:15px;height:15px;color:var(--ddm-primary);"></i>
                            Gravação da Chamada
                        </span>
                        <a id="btnDownloadAudio" href="#" download="gravacao_chamada.wav" class="ddm-btn ddm-btn-secondary ddm-btn-xs">
                            <i data-lucide="download" style="width:13px;height:13px;"></i>
                            <span>Baixar (.wav)</span>
                        </a>
                    </div>
                    <audio id="modalAudioPlayer" controls style="width:100%; height:36px; outline:none;"></audio>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                    <span style="font-weight:600; font-size:12px; color:var(--ddm-text-secondary); text-transform:uppercase; letter-spacing:0.5px;">Diálogo da Conversa</span>
                    <button type="button" class="ddm-btn ddm-btn-secondary ddm-btn-xs" id="btnCopyTranscript">
                        <i data-lucide="copy" style="width:13px;height:13px;"></i>
                        <span>Copiar Diálogo</span>
                    </button>
                </div>

                <div class="ddm-chat-container" id="chatDialogueContainer">
                    <div style="text-align:center; padding:25px; color:var(--ddm-text-muted);">
                        Carregando diálogo...
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="ddm-btn ddm-btn-secondary" data-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<?php print $ui->standardizedThemeJS(); ?>
<script type="text/javascript">
$(document).ready(function() {
    var rawTranscriptText = '';

    if (typeof lucide !== 'undefined') { lucide.createIcons(); }

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
        $('#chatDialogueContainer').html('<div style="text-align:center; padding:30px; color:var(--ddm-text-muted);">Carregando diálogo...</div>');
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

                    var tabBadgeClass = 'ddm-badge ddm-badge-neutral';
                    if (tab.toLowerCase().indexOf('venda') !== -1 || tab.toLowerCase().indexOf('interessado') !== -1 || tab.toLowerCase().indexOf('concluída') !== -1) {
                        tabBadgeClass = 'ddm-badge ddm-badge-success';
                    } else if (tab.toLowerCase().indexOf('retornar') !== -1 || tab.toLowerCase().indexOf('agendamento') !== -1 || tab.toLowerCase().indexOf('ocupado') !== -1) {
                        tabBadgeClass = 'ddm-badge ddm-badge-warning';
                    } else if (tab.toLowerCase().indexOf('sem interesse') !== -1 || tab.toLowerCase().indexOf('recusa') !== -1 || tab.toLowerCase().indexOf('caixa') !== -1) {
                        tabBadgeClass = 'ddm-badge ddm-badge-error';
                    }

                    var sentBadgeClass = 'ddm-badge ddm-badge-neutral';
                    if (sent === 'Positivo') {
                        sentBadgeClass = 'ddm-badge ddm-badge-success';
                    } else if (sent === 'Negativo') {
                        sentBadgeClass = 'ddm-badge ddm-badge-error';
                    }

                    var badgesHtml = '<span class="' + tabBadgeClass + '">' + tab + '</span> ' +
                                     '<span class="' + sentBadgeClass + '">' + sent + '</span>';
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
                        html = '<div style="text-align:center; padding:30px; color:var(--ddm-text-muted);">Nenhum diálogo textual gravado para esta chamada.</div>';
                    } else {
                        list.forEach(function(msg) {
                            if (msg.role === 'system') return;

                            var isUser = (msg.role === 'user');
                            var roleClass = isUser ? 'user' : 'assistant';
                            var authorName = isUser ? 'Cliente' : (c.agent_name || 'Agente IA');

                            textLines.push(authorName + ': ' + msg.content);

                            html += '<div class="ddm-chat-bubble ' + roleClass + '">';
                            html += '  <div class="bubble-author">' + authorName + '</div>';
                            html += '  <div class="bubble-content">' + $('<div>').text(msg.content).html() + '</div>';
                            html += '</div>';
                        });
                    }

                    rawTranscriptText = textLines.join('\n\n');
                    $('#chatDialogueContainer').html(html);
                    $('#chatDialogueContainer').scrollTop($('#chatDialogueContainer')[0].scrollHeight);
                    if (typeof lucide !== 'undefined') { lucide.createIcons(); }
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
            btn.html('<i data-lucide="check" style="width:13px;height:13px;color:var(--ddm-success-text);"></i> <span>Copiado!</span>');
            if (typeof lucide !== 'undefined') { lucide.createIcons(); }
            setTimeout(function() {
                btn.html('<i data-lucide="copy" style="width:13px;height:13px;"></i> <span>Copiar Diálogo</span>');
                if (typeof lucide !== 'undefined') { lucide.createIcons(); }
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
