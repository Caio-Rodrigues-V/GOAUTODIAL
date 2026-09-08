# Dial GO Voice AI Engine - Servidor de Mídia em Tempo Real

Microserviço de Voice AI de latência ultrabaixa (< 400ms) que conecta as chamadas telefônicas do Asterisk / GOautodial / Oktor Telecom diretamente ao pipeline de IA:

1. **STT**: Deepgram Nova-2 (Streaming de voz para texto em ~100ms)
2. **LLM**: Groq Llama 3.3 70B ou OpenAI GPT-4o-mini (Geração de resposta em ~150ms)
3. **TTS**: Cartesia Sonic Multilingual ou ElevenLabs (Síntese de voz ultrarrealista em ~90ms)

---

## 🚀 Como Executar

### 1. Instalar dependências
```bash
cd ai_engine
pip install -r requirements.txt
```

### 2. Iniciar o servidor
```bash
python server.py
```
O servidor iniciará em `http://0.0.0.0:8765`.

---

## 🔌 Integração com o Painel Dial GO
- Todas as chaves de API e configurações de agentes são gerenciadas graficamente pelo painel web nas telas **`ai_agents.php`** e **`ai_settings.php`**.
