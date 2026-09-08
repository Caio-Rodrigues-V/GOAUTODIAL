#!/bin/bash
# Configura o tronco SIP da OKTOR Telecom diretamente no Asterisk

SIP_FILE="/etc/asterisk/sip.conf"
SIP_CUSTOM="/etc/asterisk/sip-custom.conf"
SIP_VICI="/etc/asterisk/sip-vicidial.conf"

echo "=== Configurando Tronco SIP OKTOR no Asterisk ==="

TARGET_FILE="$SIP_FILE"
if [ -f "$SIP_CUSTOM" ]; then
    TARGET_FILE="$SIP_CUSTOM"
elif [ -f "$SIP_VICI" ]; then
    TARGET_FILE="$SIP_VICI"
fi

echo "[INFO] Inserindo configuração em $TARGET_FILE..."

# Verifica se o peer oktor_ia_pri já está configurado
if ! grep -q "\[oktor_ia_pri\]" "$TARGET_FILE" 2>/dev/null && ! grep -q "\[oktor_ia_pri\]" "$SIP_FILE" 2>/dev/null; then
    cat << 'EOF' >> "$TARGET_FILE"

; --- OKTOR TELECOM IA TRUNK ---
[oktor_ia_pri]
type=peer
host=200.196.232.250
port=5060
dtmfmode=rfc2833
context=trunkinbound
insecure=port,invite
disallow=all
allow=alaw
allow=ulaw
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
allow=ulaw
allow=g729
qualify=yes
nat=no

EOF
    echo "[OK] Peers oktor_ia_pri e oktor_ia_sec adicionados com sucesso!"
else
    echo "[INFO] Peers da Oktor já existem no arquivo SIP."
fi

# Reload Asterisk SIP
echo "[INFO] Recarregando módulo SIP do Asterisk..."
asterisk -rx "sip reload" 2>/dev/null || true
sleep 1

echo "=== Status dos Peers OKTOR no Asterisk ==="
asterisk -rx "sip show peers like oktor" 2>/dev/null || true
echo "=========================================="
