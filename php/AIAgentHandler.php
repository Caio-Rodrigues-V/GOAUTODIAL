<?php
/**
 * @file        AIAgentHandler.php
 * @brief       AI Voice Agents Handler & Database Manager
 * @copyright   (c) Dial GO Voice AI Engine
 */

namespace creamy;

require_once('CRMDefaults.php');
require_once('DatabaseConnectorFactory.php');

class AIAgentHandler {
    
    private $db;

    public static function getInstance() {
        static $instance = null;
        if (null === $instance) {
            $instance = new static();
        }
        return $instance;
    }

    protected function __construct() {
        $this->db = \creamy\DatabaseConnectorFactory::getInstance()->getDatabaseConnectorOfType(CRM_DB_CONNECTOR_TYPE_MYSQL);
        $this->initTables();
    }

    /**
     * Ensure AI tables exist
     */
    private function initTables() {
        if (!$this->db) return;
        try {
            // Table for AI Agents
            $this->db->rawQuery("CREATE TABLE IF NOT EXISTS `go_ai_agents` (
                `agent_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `agent_name` varchar(100) NOT NULL,
                `description` varchar(255) DEFAULT '',
                `status` enum('Y','N') NOT NULL DEFAULT 'Y',
                `first_message_mode` varchar(50) NOT NULL DEFAULT 'assistant_speaks_first',
                `model_preset` varchar(50) NOT NULL DEFAULT 'balanced',
                `llm_provider` varchar(50) NOT NULL DEFAULT 'groq',
                `llm_model` varchar(100) NOT NULL DEFAULT 'llama-3.3-70b-versatile',
                `temperature` decimal(3,2) NOT NULL DEFAULT '0.70',
                `max_tokens` int(11) NOT NULL DEFAULT '250',
                `prompt_cache_retention` varchar(50) NOT NULL DEFAULT 'in_memory',
                `prompt_cache_key` varchar(100) DEFAULT '',
                `tool_strict_compatibility` enum('Y','N') NOT NULL DEFAULT 'N',
                `voice_provider` varchar(50) NOT NULL DEFAULT 'cartesia',
                `voice_id` varchar(100) NOT NULL DEFAULT 'cartesia-pt-br-sofia',
                `voice_name` varchar(100) DEFAULT 'Sofia (Cartesia Português BR - Natural)',
                `voice_speed` decimal(3,2) NOT NULL DEFAULT '1.00',
                `voice_stability` decimal(3,2) NOT NULL DEFAULT '0.70',
                `voice_clarity` decimal(3,2) NOT NULL DEFAULT '0.60',
                `voice_style_exaggeration` decimal(3,2) NOT NULL DEFAULT '0.20',
                `voice_optimize_latency` int(11) NOT NULL DEFAULT '1',
                `voice_speaker_boost` enum('Y','N') NOT NULL DEFAULT 'N',
                `voice_caching` enum('Y','N') NOT NULL DEFAULT 'Y',
                `background_sound` varchar(50) NOT NULL DEFAULT 'off',
                `stt_provider` varchar(50) NOT NULL DEFAULT 'deepgram',
                `stt_model` varchar(50) NOT NULL DEFAULT 'nova-2',
                `stt_language` varchar(20) NOT NULL DEFAULT 'pt-BR',
                `intelligent_turn_taking` enum('Y','N') NOT NULL DEFAULT 'Y',
                `background_denoising` enum('Y','N') NOT NULL DEFAULT 'Y',
                `silence_timeout_ms` int(11) NOT NULL DEFAULT '500',
                `stt_fallback_provider` varchar(50) NOT NULL DEFAULT 'openai',
                `stt_fallback_model` varchar(50) NOT NULL DEFAULT 'whisper-1',
                `greeting_message` text,
                `system_prompt` longtext,
                `tools_json` longtext,
                `analysis_schema_json` longtext,
                `max_duration_seconds` int(11) NOT NULL DEFAULT '600',
                `interruption_sensitivity` decimal(3,2) NOT NULL DEFAULT '0.80',
                `transfer_phone_or_queue` varchar(100) DEFAULT '',
                `transfer_on_interest` enum('Y','N') NOT NULL DEFAULT 'Y',
                `hangup_on_voicemail` enum('Y','N') NOT NULL DEFAULT 'Y',
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`agent_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

            // Migration for existing databases
            $columnsToEnsure = array(
                'first_message_mode' => "VARCHAR(50) NOT NULL DEFAULT 'assistant_speaks_first'",
                'model_preset' => "VARCHAR(50) NOT NULL DEFAULT 'balanced'",
                'max_tokens' => "INT(11) NOT NULL DEFAULT '250'",
                'prompt_cache_retention' => "VARCHAR(50) NOT NULL DEFAULT 'in_memory'",
                'prompt_cache_key' => "VARCHAR(100) DEFAULT ''",
                'tool_strict_compatibility' => "ENUM('Y','N') NOT NULL DEFAULT 'N'",
                'voice_speed' => "DECIMAL(3,2) NOT NULL DEFAULT '1.00'",
                'voice_stability' => "DECIMAL(3,2) NOT NULL DEFAULT '0.70'",
                'voice_clarity' => "DECIMAL(3,2) NOT NULL DEFAULT '0.60'",
                'voice_style_exaggeration' => "DECIMAL(3,2) NOT NULL DEFAULT '0.20'",
                'voice_optimize_latency' => "INT(11) NOT NULL DEFAULT '1'",
                'voice_speaker_boost' => "ENUM('Y','N') NOT NULL DEFAULT 'N'",
                'voice_caching' => "ENUM('Y','N') NOT NULL DEFAULT 'Y'",
                'background_sound' => "VARCHAR(50) NOT NULL DEFAULT 'off'",
                'background_sound_volume' => "DECIMAL(3,2) NOT NULL DEFAULT '0.10'",
                'intelligent_turn_taking' => "ENUM('Y','N') NOT NULL DEFAULT 'Y'",
                'background_denoising' => "ENUM('Y','N') NOT NULL DEFAULT 'Y'",
                'silence_timeout_ms' => "INT(11) NOT NULL DEFAULT '500'",
                'stt_fallback_provider' => "VARCHAR(50) NOT NULL DEFAULT 'openai'",
                'stt_fallback_model' => "VARCHAR(50) NOT NULL DEFAULT 'whisper-1'",
                'tools_json' => "LONGTEXT",
                'analysis_schema_json' => "LONGTEXT"
            );
            foreach ($columnsToEnsure as $col => $typeDef) {
                try {
                    $this->db->rawQuery("ALTER TABLE `go_ai_agents` ADD COLUMN IF NOT EXISTS `$col` $typeDef;");
                } catch (\Throwable $e) {
                    try {
                        $this->db->rawQuery("ALTER TABLE `go_ai_agents` ADD COLUMN `$col` $typeDef;");
                    } catch (\Throwable $e2) {}
                }
            }

            // Table for AI API Keys & Provider Settings
            $this->db->rawQuery("CREATE TABLE IF NOT EXISTS `go_ai_settings` (
                `setting_key` varchar(100) NOT NULL,
                `setting_value` text,
                `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`setting_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

            // Table for AI Call Logs & Transcripts
            $this->db->rawQuery("CREATE TABLE IF NOT EXISTS `go_ai_call_logs` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `call_id` varchar(100) NOT NULL,
                `agent_id` int(11) unsigned DEFAULT NULL,
                `agent_name` varchar(100) DEFAULT '',
                `phone_number` varchar(30) NOT NULL,
                `status` varchar(50) NOT NULL DEFAULT 'completed',
                `duration_seconds` int(11) NOT NULL DEFAULT 0,
                `llm_provider` varchar(50) DEFAULT '',
                `llm_model` varchar(100) DEFAULT '',
                `voice_provider` varchar(50) DEFAULT '',
                `voice_id` varchar(100) DEFAULT '',
                `stt_provider` varchar(50) DEFAULT '',
                `transcript_json` longtext,
                `recording_url` varchar(255) DEFAULT '',
                `qualification` varchar(100) DEFAULT 'Atendida',
                `cost_estimate` decimal(8,4) DEFAULT '0.0000',
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `ended_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_call_id` (`call_id`),
                KEY `idx_agent_id` (`agent_id`),
                KEY `idx_phone_number` (`phone_number`),
                KEY `idx_created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

            // Auto-migrate missing columns for existing tables
            $checkCols = array(
                'recording_url' => 'varchar(255) DEFAULT ""',
                'qualification' => 'varchar(100) DEFAULT "Atendida"',
                'cost_estimate' => 'decimal(8,4) DEFAULT "0.0000"',
                'llm_provider' => 'varchar(50) DEFAULT ""',
                'llm_model' => 'varchar(100) DEFAULT ""',
                'voice_provider' => 'varchar(50) DEFAULT ""',
                'voice_id' => 'varchar(100) DEFAULT ""',
                'stt_provider' => 'varchar(50) DEFAULT ""',
                'ended_at' => 'datetime DEFAULT NULL'
            );
            foreach ($checkCols as $col => $definition) {
                try {
                    $this->db->rawQuery("ALTER TABLE `go_ai_call_logs` ADD COLUMN `{$col}` {$definition};");
                } catch (\Throwable $t) {}
            }

            // Insert default sample agent if none exists
            $existing = $this->db->get('go_ai_agents');
            if (empty($existing)) {
                $defaultPrompt = "Você é a Sofia, uma especialista de atendimento e vendas cordial, ágil e persuasiva.
Seu objetivo é qualificar o cliente com clareza, empatia e sem enrolação.

Diretrizes da conversa:
1. Seja direta, amigável e use um tom de voz conversacional e humano.
2. Responda em no máximo 2 frases por turno para manter a conversa dinâmica.
3. Se o cliente tiver interesse ou pedir para falar com um humano, transfira a ligação imediatamente confirmando antes.
4. Se o cliente disser que não tem interesse, agradeça educadamente e encerre a ligação.";

                $this->createAgent(array(
                    'agent_name' => 'Sofia - Qualificadora de Vendas',
                    'description' => 'Agente de voz de alta conversão para SDR e pré-vendas',
                    'status' => 'Y',
                    'llm_provider' => 'groq',
                    'llm_model' => 'llama-3.3-70b-versatile',
                    'voice_provider' => 'cartesia',
                    'voice_id' => 'cartesia-pt-br-sofia',
                    'voice_name' => 'Sofia (Cartesia Português BR - Natural)',
                    'stt_provider' => 'deepgram',
                    'stt_model' => 'nova-2',
                    'stt_language' => 'pt-BR',
                    'greeting_message' => 'Olá, tudo bem? Falo com o titular da linha?',
                    'system_prompt' => $defaultPrompt,
                    'temperature' => 0.7,
                    'transfer_phone_or_queue' => '8300',
                    'transfer_on_interest' => 'Y',
                    'hangup_on_voicemail' => 'Y'
                ));
            }
        } catch (\Throwable $t) {}
    }

    public function getAllAgents() {
        if (!$this->db) return array();
        try {
            $this->db->orderBy('agent_id', 'DESC');
            return $this->db->get('go_ai_agents');
        } catch (\Throwable $t) {
            return array();
        }
    }

    public function getAgentById($id) {
        if (!$this->db) return null;
        try {
            $this->db->where('agent_id', (int)$id);
            return $this->db->getOne('go_ai_agents');
        } catch (\Throwable $t) {
            return null;
        }
    }

    public function createAgent($data) {
        if (!$this->db) return false;
        try {
            return $this->db->insert('go_ai_agents', $data);
        } catch (\Throwable $t) {
            return false;
        }
    }

    public function updateAgent($id, $data) {
        if (!$this->db) return false;
        try {
            $this->db->where('agent_id', (int)$id);
            return $this->db->update('go_ai_agents', $data);
        } catch (\Throwable $t) {
            return false;
        }
    }

    public function deleteAgent($id) {
        if (!$this->db) return false;
        try {
            $this->db->where('agent_id', (int)$id);
            return $this->db->delete('go_ai_agents');
        } catch (\Throwable $t) {
            return false;
        }
    }

    public function getSetting($key, $default = '') {
        if (!$this->db) return $default;
        try {
            $this->db->where('setting_key', $key);
            $row = $this->db->getOne('go_ai_settings');
            return $row ? $row['setting_value'] : $default;
        } catch (\Throwable $t) {
            return $default;
        }
    }

    public function saveSetting($key, $value) {
        if (!$this->db) return false;
        try {
            $data = array('setting_key' => $key, 'setting_value' => $value);
            $this->db->where('setting_key', $key);
            if ($this->db->has('go_ai_settings')) {
                $this->db->where('setting_key', $key);
                return $this->db->update('go_ai_settings', $data);
            } else {
                return $this->db->insert('go_ai_settings', $data);
            }
        } catch (\Throwable $t) {
            return false;
        }
    }

    public function getAvailableLLMs() {
        return array(
            'groq' => array(
                'name' => 'Groq (Ultra-Rápido ~120ms)',
                'models' => array(
                    'llama-3.3-70b-versatile' => 'Llama 3.3 70B (Recomendado - Excelente em PT-BR)',
                    'llama-3.1-8b-instant' => 'Llama 3.1 8B Instant (Ultra-rápido ~80ms)',
                    'deepseek-r1-distill-llama-70b' => 'DeepSeek R1 Distill Llama 70B (Raciocínio no Groq)',
                    'mixtral-8x7b-32768' => 'Mixtral 8x7B 32k',
                    'gemma2-9b-it' => 'Gemma 2 9B IT',
                    'custom' => 'Outro Modelo (Digitar ID)'
                )
            ),
            'openai' => array(
                'name' => 'OpenAI',
                'models' => array(
                    'gpt-4o' => 'GPT-4o (Máxima inteligência & raciocínio)',
                    'gpt-4o-mini' => 'GPT-4o Mini (Recomendado - Rápido e econômico)',
                    'gpt-4-turbo' => 'GPT-4 Turbo',
                    'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
                    'o1-mini' => 'o1-mini (Raciocínio avançado)',
                    'o3-mini' => 'o3-mini (Raciocínio ultra-rápido)',
                    'custom' => 'Outro Modelo (Digitar ID)'
                )
            ),
            'anthropic' => array(
                'name' => 'Anthropic Claude',
                'models' => array(
                    'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet (Altíssima qualidade de conversação)',
                    'claude-3-5-haiku-20241022' => 'Claude 3.5 Haiku (Ultra-rápido e natural)',
                    'claude-3-opus-20240229' => 'Claude 3 Opus',
                    'custom' => 'Outro Modelo (Digitar ID)'
                )
            ),
            'deepseek' => array(
                'name' => 'DeepSeek (Direto)',
                'models' => array(
                    'deepseek-chat' => 'DeepSeek V3 (Chat - Excelente e ultra-econômico)',
                    'deepseek-reasoner' => 'DeepSeek R1 (Raciocínio profundo)',
                    'custom' => 'Outro Modelo (Digitar ID)'
                )
            ),
            'gemini' => array(
                'name' => 'Google Gemini',
                'models' => array(
                    'gemini-2.0-flash' => 'Gemini 2.0 Flash (Nova Geração - Ultra Rápido)',
                    'gemini-1.5-flash' => 'Gemini 1.5 Flash (Super ágil e econômico)',
                    'gemini-1.5-pro' => 'Gemini 1.5 Pro (Janela de 2M tokens)',
                    'custom' => 'Outro Modelo (Digitar ID)'
                )
            )
        );
    }

    public function getAvailableVoices() {
        return array(
            'cartesia' => array(
                'name' => 'Cartesia Sonic (Latência < 90ms - Ultrarrealista)',
                'voices' => array(
                    'cartesia-pt-br-sofia' => 'Sofia (Feminina - Natural, Amigável & Comercial - PT-BR)',
                    'cartesia-pt-br-lucas' => 'Lucas (Masculino - Seguro, Profissional & Claro - PT-BR)',
                    'cartesia-pt-br-julia' => 'Júlia (Feminina - Dinâmica & Vendas - PT-BR)',
                    'cartesia-pt-br-mateus' => 'Mateus (Masculino - Firme & Consultivo - PT-BR)',
                    'cartesia-pt-br-beatriz' => 'Beatriz (Feminina - Suave & Atendimento - PT-BR)',
                    'cartesia-pt-br-gabriel' => 'Gabriel (Masculino - Jovem & Entusiasta - PT-BR)',
                    'cartesia-en-sarah' => 'Sarah (English - American Conversational)',
                    'cartesia-en-michael' => 'Michael (English - American Professional)',
                    'custom' => 'Voz Personalizada Cartesia (Digitar Voice ID)'
                )
            ),
            'elevenlabs' => array(
                'name' => 'ElevenLabs Turbo v2.5 (Multilingual & Clones)',
                'voices' => array(
                    '21m00Tcm4TlvDq8ikWAM' => 'Rachel (Feminina - Calma & Conversacional)',
                    'AZnzlk1XvdvUeBnXmlld' => 'Domi (Feminina - Forte & Enérgica)',
                    'EXAVITQu4vr4xnSDxMaL' => 'Bella (Feminina - Suave & Expressiva)',
                    'ErXwobaYiN019PkySvjV' => 'Antoni (Masculino - Bem articulado & Modulado)',
                    'MF3mGyEYCl7XYWbV9V6O' => 'Elli (Feminina - Jovem & Clara)',
                    'TxGEqnHWrfWFTfGW9XjX' => 'Josh (Masculino - Jovem, Americano & Natural)',
                    'VR6AewLTigWG4xSOukaG' => 'Arnold (Masculino - Firme & Narrador)',
                    'pNInz6obpgDQGcFmaJgB' => 'Adam (Masculino - Profissional, Seguro & Conversacional)',
                    'yoZ06aMxZJJ28mfd3POQ' => 'Sam (Masculino - Dinâmico & Rápido)',
                    'ThT5KcBeYPX3keUQqHPh' => 'Dorothy (Feminina - Britânica & Amigável)',
                    'jsCqWAovK2LkecY7zXl4' => 'Freya (Feminina - Expressiva)',
                    'onwK4e9ZLuTAKqWW03F9' => 'Daniel (Masculino - Profundo & Sério)',
                    'ZQe5CZNOzWyzPSCn5a3c' => 'James (Masculino - Confiante & Calmo)',
                    'custom' => 'Voz Clonada / Personalizada ElevenLabs (Digitar Voice ID)'
                )
            ),
            'openai' => array(
                'name' => 'OpenAI TTS (Alloy, Echo, Nova, Onyx, Shimmer, Fable)',
                'voices' => array(
                    'alloy' => 'Alloy (Neutra & Equilibrada)',
                    'echo' => 'Echo (Masculina - Suave & Conversacional)',
                    'fable' => 'Fable (Expressiva & Britânica)',
                    'onyx' => 'Onyx (Masculina - Profunda & Séria)',
                    'nova' => 'Nova (Feminina - Jovem, Viva & Energética)',
                    'shimmer' => 'Shimmer (Feminina - Clara & Otimista)'
                )
            ),
            'deepgram' => array(
                'name' => 'Deepgram Aura (TTS Baixa Latência)',
                'voices' => array(
                    'aura-asteria-en' => 'Asteria (Feminina - Conversacional)',
                    'aura-luna-en' => 'Luna (Feminina - Suave)',
                    'aura-stella-en' => 'Stella (Feminina - Dinâmica)',
                    'aura-arcus-en' => 'Arcus (Masculina - Forte)',
                    'aura-orion-en' => 'Orion (Masculina - Firme)',
                    'aura-zeus-en' => 'Zeus (Masculina - Profunda)'
                )
            )
        );
    }

    public function getAvailableSTTs() {
        return array(
            'deepgram' => array(
                'name' => 'Deepgram Nova-2 (Ultrarrápido ~120ms • Otimizado para Telefonia)',
                'models' => array(
                    'nova-2' => 'Nova-2 General (Recomendado - 98.4% Precisão)',
                    'nova-2-phonecall' => 'Nova-2 Phonecall (Específico para Áudio 8kHz)',
                    'nova-2-conversationalai' => 'Nova-2 Conversational AI'
                )
            ),
            'groq' => array(
                'name' => 'Groq Whisper Turbo (~100ms)',
                'models' => array(
                    'whisper-large-v3-turbo' => 'Whisper Large v3 Turbo',
                    'whisper-large-v3' => 'Whisper Large v3'
                )
            ),
            'openai' => array(
                'name' => 'OpenAI Whisper',
                'models' => array(
                    'whisper-1' => 'Whisper v1'
                )
            ),
            'azure' => array(
                'name' => 'Azure Speech',
                'models' => array(
                    'azure-default' => 'Azure Speech to Text'
                )
            )
        );
    }

    public function getDefaultTools() {
        return array(
            array(
                'id' => 'end_call',
                'name' => 'end_call',
                'type' => 'End call',
                'description' => "Encerra a chamada imediatamente após a fala de despedida ou conclusão do atendimento (ex: 'Tenha um ótimo dia!', 'Tchau', 'Até logo').",
                'enabled' => true,
                'version' => 'Latest'
            ),
            array(
                'id' => 'voicemail_tool',
                'name' => 'voicemail_tool',
                'type' => 'Voicemail',
                'description' => "End the call immediately when the audio indicates that the call reached voicemail, answering machine, mailbox, or an automated unavailable message.",
                'enabled' => true,
                'version' => 'Latest'
            ),
            array(
                'id' => 'response_control',
                'name' => 'response_control',
                'type' => 'Custom tool',
                'description' => "This tool enforces maximum responsiveness and high interruption sensitivity behavior. It ensures the assistant responds immediately after the user finishes speaking.",
                'enabled' => true,
                'version' => 'Latest'
            ),
            array(
                'id' => 'transfer_call',
                'name' => 'transfer_call',
                'type' => 'Transfer call',
                'description' => "Transfere a chamada para um ramal SIP ou fila de atendimento humano quando o cliente demonstrar interesse ou solicitar um atendente.",
                'enabled' => true,
                'version' => 'Latest'
            ),
            array(
                'id' => 'capturar_cpf',
                'name' => 'capturar_cpf',
                'type' => 'Custom tool',
                'description' => "Use this tool when the user provides digits of their CPF or document. Captures and validates format for CRM lookup.",
                'enabled' => false,
                'version' => 'Latest'
            )
        );
    }

    public function saveCallLog($data) {
        if (!$this->db) return false;
        try {
            if (isset($data['transcript_json']) && is_array($data['transcript_json'])) {
                $data['transcript_json'] = json_encode($data['transcript_json']);
            }
            if (!empty($data['call_id'])) {
                $this->db->where('call_id', $data['call_id']);
                if ($this->db->has('go_ai_call_logs')) {
                    $this->db->where('call_id', $data['call_id']);
                    $this->db->update('go_ai_call_logs', $data);
                    return $data['call_id'];
                }
            }
            return $this->db->insert('go_ai_call_logs', $data);
        } catch (\Throwable $t) {
            return false;
        }
    }

    public function getCallLogs($limit = 100, $offset = 0, $agentId = null, $phone = null, $status = null) {
        if (!$this->db) return array();
        try {
            if ($agentId) {
                $this->db->where('agent_id', (int)$agentId);
            }
            if ($phone) {
                $this->db->where('phone_number', "%{$phone}%", 'LIKE');
            }
            if ($status) {
                $this->db->where('status', $status);
            }
            $this->db->orderBy('id', 'DESC');
            return $this->db->get('go_ai_call_logs', array($offset, $limit));
        } catch (\Throwable $t) {
            return array();
        }
    }

    public function getCallLogById($id) {
        if (!$this->db) return null;
        try {
            $this->db->where('id', (int)$id);
            return $this->db->getOne('go_ai_call_logs');
        } catch (\Throwable $t) {
            return null;
        }
    }

    public function getCallStats() {
        if (!$this->db) return array('total' => 0, 'answered' => 0, 'duration' => 0, 'leads' => 0);
        try {
            $total = $this->db->getValue('go_ai_call_logs', 'count(*)');
            $this->db->where('status', 'completed');
            $answered = $this->db->getValue('go_ai_call_logs', 'count(*)');
            $duration = $this->db->getValue('go_ai_call_logs', 'sum(duration_seconds)');
            $this->db->where('qualification', 'Interessado');
            $leads = $this->db->getValue('go_ai_call_logs', 'count(*)');

            return array(
                'total' => (int)$total,
                'answered' => (int)$answered,
                'duration' => (int)$duration,
                'leads' => (int)$leads
            );
        } catch (\Throwable $t) {
            return array('total' => 0, 'answered' => 0, 'duration' => 0, 'leads' => 0);
        }
    }
}
?>
