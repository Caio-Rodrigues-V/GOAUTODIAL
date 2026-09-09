#!/bin/bash
# Script de instalacao do servico 24/7 (Systemd + Cron Watchdog)

ENGINE_DIR="$(cd "$(dirname "$0")" && pwd)"
SERVICE_FILE="/etc/systemd/system/dialgo-ai.service"

chmod +x "$ENGINE_DIR/watchdog.sh"
chmod +x "$ENGINE_DIR/server.py"

echo "=== Configurando Dial GO Voice AI Server 24/7 ==="

# 1. Configurar Systemd Service
if [ -d "/etc/systemd/system" ]; then
    echo "Criando servico systemd em $SERVICE_FILE..."
    cat <<EOF > $SERVICE_FILE
[Unit]
Description=Dial GO Voice AI Engine Server (FastAPI + Direct SIP + LLM)
After=network.target network-online.target asterisk.service
Wants=network-online.target

[Service]
Type=simple
User=root
WorkingDirectory=$ENGINE_DIR
ExecStart=/usr/bin/python3 $ENGINE_DIR/server.py
Restart=always
RestartSec=3
KillMode=process
StandardOutput=append:$ENGINE_DIR/server.log
StandardError=append:$ENGINE_DIR/server.log

[Install]
WantedBy=multi-user.target
EOF

    systemctl daemon-reload
    systemctl enable dialgo-ai
    systemctl restart dialgo-ai
    echo "Servico systemd 'dialgo-ai' ativado e iniciado com sucesso!"
fi

# 2. Configurar Crontab Watchdog (Checa a cada 1 minuto se esta rodando)
CRON_JOB="* * * * * $ENGINE_DIR/watchdog.sh > /dev/null 2>&1"
CRON_REBOOT="@reboot $ENGINE_DIR/watchdog.sh > /dev/null 2>&1"

(crontab -l 2>/dev/null | grep -v "watchdog.sh"; echo "$CRON_JOB"; echo "$CRON_REBOOT") | crontab -
echo "Cron Watchdog instalado no crontab (checa e sobe a cada 1 min e no reboot)!"

# 3. Iniciar processo imediatamente
bash "$ENGINE_DIR/watchdog.sh"

sleep 1
echo "================================================="
echo "Status do Servidor Dial GO Voice AI:"
pgrep -fl "server.py" || echo "Processo ativo em background (verifique 'tail -f server.log')"
echo "================================================="