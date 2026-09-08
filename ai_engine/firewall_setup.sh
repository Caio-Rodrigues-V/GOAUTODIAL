#!/bin/bash
# Libera IPs e Portas da OKTOR Telecom no Firewall (iptables / firewalld)

echo "=== Liberando IPs e Portas da OKTOR Telecom no Firewall ==="

# Lista de IPs da Oktor
OKTOR_IPS=(
    "200.196.232.250"  # Sinalização Primária
    "200.196.232.251"  # Mídia Primária
    "200.196.232.252"  # Mídia Primária
    "177.154.149.210"  # Sinalização Secundária
    "177.154.149.212"  # Mídia Secundária
    "177.154.149.213"  # Mídia Secundária
    "177.154.149.222"  # Monitoramento Oktor
    "200.196.233.149"  # Monitoramento Oktor
)

# 1. Se firewalld estiver ativo
if systemctl is-active --quiet firewalld; then
    echo "[INFO] Configurando firewalld..."
    for ip in "${OKTOR_IPS[@]}"; do
        firewall-cmd --permanent --add-rich-rule="rule family=\"ipv4\" source address=\"$ip\" accept" 2>/dev/null
    done
    firewall-cmd --permanent --add-port=5060/udp 2>/dev/null
    firewall-cmd --permanent --add-port=5050-65535/udp 2>/dev/null
    firewall-cmd --reload 2>/dev/null
    echo "[OK] Regras aplicadas no firewalld com sucesso!"
fi

# 2. Se iptables estiver disponível
if command -v iptables &> /dev/null; then
    echo "[INFO] Configurando iptables..."
    for ip in "${OKTOR_IPS[@]}"; do
        iptables -I INPUT -s "$ip" -j ACCEPT 2>/dev/null
    done
    iptables -I INPUT -p udp --dport 5060 -j ACCEPT 2>/dev/null
    iptables -I INPUT -p udp --dport 5050:65535 -j ACCEPT 2>/dev/null
    echo "[OK] Regras aplicadas no iptables com sucesso!"
fi

echo "=== Firewall OKTOR Configurado com Sucesso! ==="
