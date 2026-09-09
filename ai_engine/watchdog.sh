#!/bin/bash
# Dial GO Voice AI Engine - 24/7 Watchdog & Auto-Restarter
# Executado via cron para garantir 100% de uptime

ENGINE_DIR="$(cd "$(dirname "$0")" && pwd)"
LOG_FILE="$ENGINE_DIR/server.log"
PID_FILE="$ENGINE_DIR/server.pid"

# Verificar se o processo server.py já está rodando
if pgrep -f "$ENGINE_DIR/server.py" > /dev/null 2>&1; then
    exit 0
fi

# Se não estiver rodando, inicia em background com nohup
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Dial GO Voice AI reiniciado automaticamente pelo Watchdog." >> "$LOG_FILE"
cd "$ENGINE_DIR" || exit 1

nohup python3 "$ENGINE_DIR/server.py" >> "$LOG_FILE" 2>&1 &
NEW_PID=$!
echo "$NEW_PID" > "$PID_FILE"
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Servidor ativo com PID $NEW_PID" >> "$LOG_FILE"