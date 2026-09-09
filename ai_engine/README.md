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

### 2. Rodar 24/7 em Segundo Plano (Systemd / Cron Watchdog)
Execute o instalador automático de serviço 24/7:
```bash
chmod +x setup_service.sh
./setup_service.sh
```

Isso irá:
1. Instalar o serviço **Systemd** `dialgo-ai` com auto-restart em caso de falha.
2. Adicionar o watchdog no **Crontab** para checar a cada minuto se o processo está ativo e reiniciá-lo se necessário.

#### Comandos úteis:
```bash
# Ver status do serviço
systemctl status dialgo-ai

# Ver logs em tempo real
journalctl -u dialgo-ai -f
# ou
tail -f server.log

# Reiniciar serviço
systemctl restart dialgo-ai
```

---

## 🔌 Integração com o Painel Dial GO
- Todas as chaves de API e configurações de agentes são gerenciadas graficamente pelo painel web nas telas **`ai_agents.php`** e **`ai_settings.php`**.
