#!/bin/bash
# Configura o contexto [dialgo-ai] no Asterisk para atendimento com IA

EXTENSIONS_FILE="/etc/asterisk/extensions.conf"
AGI_DIR="/var/lib/asterisk/agi-bin"

echo "=== Configurando Dial GO Voice AI no Asterisk ==="

# 1. Copiar AGI para a pasta padrão do Asterisk
if [ -d "$AGI_DIR" ]; then
    cp asterisk_agi.py "$AGI_DIR/dialgo_ai.py"
    chmod +x "$AGI_DIR/dialgo_ai.py"
    echo "[OK] AGI copiado para $AGI_DIR/dialgo_ai.py"
fi

# 2. Adicionar contexto no extensions.conf se não existir
if [ -f "$EXTENSIONS_FILE" ]; then
    if ! grep -q "\[dialgo-ai\]" "$EXTENSIONS_FILE"; then
        echo -e "\n\n; --- DIAL GO VOICE AI CONTEXT ---" >> "$EXTENSIONS_FILE"
        echo -e "[dialgo-ai]" >> "$EXTENSIONS_FILE"
        echo -e "exten => 8300,1,Answer()" >> "$EXTENSIONS_FILE"
        echo -e "exten => 8300,n,Wait(1)" >> "$EXTENSIONS_FILE"
        echo -e "exten => 8300,n,AGI(dialgo_ai.py,\${AGENT_ID})" >> "$EXTENSIONS_FILE"
        echo -e "exten => 8300,n,Hangup()\n" >> "$EXTENSIONS_FILE"
        echo "[OK] Contexto [dialgo-ai] adicionado em $EXTENSIONS_FILE"
    else
        echo "[INFO] Contexto [dialgo-ai] já existe no Asterisk."
    fi
    
    # Reload Asterisk dialplan
    asterisk -rx "dialplan reload" 2>/dev/null || true
    echo "[OK] Dialplan do Asterisk recarregado com sucesso!"
fi

echo "=== Configuração Concluída com Sucesso! ==="
