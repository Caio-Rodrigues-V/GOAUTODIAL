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
                `llm_provider` varchar(50) NOT NULL DEFAULT 'groq',
                `llm_model` varchar(100) NOT NULL DEFAULT 'llama-3.3-70b-versatile',
                `voice_provider` varchar(50) NOT NULL DEFAULT 'cartesia',
                `voice_id` varchar(100) NOT NULL DEFAULT 'sonic-multilingual-pt-br',
                `voice_name` varchar(100) DEFAULT 'Cartesia Português Brasil',
                `stt_provider` varchar(50) NOT NULL DEFAULT 'deepgram',
                `stt_model` varchar(50) NOT NULL DEFAULT 'nova-2',
                `stt_language` varchar(20) NOT NULL DEFAULT 'pt-BR',
                `greeting_message` text,
                `system_prompt` longtext,
                `temperature` decimal(3,2) NOT NULL DEFAULT '0.70',
                `max_duration_seconds` int(11) NOT NULL DEFAULT '600',
                `interruption_sensitivity` decimal(3,2) NOT NULL DEFAULT '0.80',
                `transfer_phone_or_queue` varchar(100) DEFAULT '',
                `transfer_on_interest` enum('Y','N') NOT NULL DEFAULT 'Y',
                `hangup_on_voicemail` enum('Y','N') NOT NULL DEFAULT 'Y',
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`agent_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

            // Table for AI API Keys & Provider Settings
            $this->db->rawQuery("CREATE TABLE IF NOT EXISTS `go_ai_settings` (
                `setting_key` varchar(100) NOT NULL,
                `setting_value` text,
                `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`setting_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

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
                    'llama-3.1-8b-instant' => 'Llama 3.1 8B Instant (Ultra-rápido ~80ms)'
                )
            ),
            'openai' => array(
                'name' => 'OpenAI',
                'models' => array(
                    'gpt-4o-mini' => 'GPT-4o Mini (Ótima precisão e raciocínio)',
                    'gpt-4o' => 'GPT-4o (Máxima inteligência)'
                )
            ),
            'gemini' => array(
                'name' => 'Google Gemini',
                'models' => array(
                    'gemini-1.5-flash' => 'Gemini 1.5 Flash (Super ágil e econômico)',
                    'gemini-2.0-flash' => 'Gemini 2.0 Flash'
                )
            )
        );
    }

    public function getAvailableVoices() {
        return array(
            'cartesia' => array(
                'name' => 'Cartesia Sonic (Latência < 90ms - Ultrarrealista)',
                'voices' => array(
                    'cartesia-pt-br-sofia' => 'Sofia (Feminina - Natural & Amigável)',
                    'cartesia-pt-br-lucas' => 'Lucas (Masculino - Seguro & Profissional)',
                    'cartesia-pt-br-julia' => 'Júlia (Feminina - Dinâmica & Comercial)',
                    'cartesia-pt-br-mateus' => 'Mateus (Masculino - Firme & Consultivo)'
                )
            ),
            'elevenlabs' => array(
                'name' => 'ElevenLabs Turbo v2.5',
                'voices' => array(
                    'eleven-pt-br-alice' => 'Alice (Feminina - Expressiva)',
                    'eleven-pt-br-bruno' => 'Bruno (Masculino - Narrador / Vendas)',
                    'eleven-pt-br-carol' => 'Carol (Feminina - Atendimento)'
                )
            ),
            'openai' => array(
                'name' => 'OpenAI TTS',
                'voices' => array(
                    'nova' => 'Nova (Feminina - Jovem)',
                    'shimmer' => 'Shimmer (Feminina - Clara)',
                    'onyx' => 'Onyx (Masculina - Grave)',
                    'echo' => 'Echo (Masculina - Suave)'
                )
            )
        );
    }
}
?>
