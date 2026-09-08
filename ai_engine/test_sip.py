#!/usr/bin/env python3
"""
Dial GO - Ferramenta de Teste e Diagnóstico de Conexão SIP com a OKTOR
Testa OPTIONS Ping e INVITE direto mostrando a resposta exata da operadora.
"""

import socket
import sys
import time
import uuid

OKTOR_IPS = [
    ("200.196.232.250", 5060, "Oktor Primário"),
    ("177.154.149.210", 5060, "Oktor Secundário")
]

def get_public_ip():
    try:
        import urllib.request
        with urllib.request.urlopen("https://api.ipify.org", timeout=3) as r:
            return r.read().decode('utf-8').strip()
    except:
        return "129.121.42.250"

def test_options(host, port, name, public_ip, sock):
    branch = f"z9hG4bK-{uuid.uuid4().hex[:10]}"
    call_id = f"{uuid.uuid4().hex[:16]}@{public_ip}"
    tag = uuid.uuid4().hex[:8]
    
    local_port = sock.getsockname()[1]
    
    msg = (
        f"OPTIONS sip:{host}:{port} SIP/2.0\r\n"
        f"Via: SIP/2.0/UDP {public_ip}:{local_port};branch={branch};rport\r\n"
        f"Max-Forwards: 70\r\n"
        f"From: <sip:59083@{host}>;tag={tag}\r\n"
        f"To: <sip:{host}>\r\n"
        f"Call-ID: {call_id}\r\n"
        f"CSeq: 1 OPTIONS\r\n"
        f"Contact: <sip:59083@{public_ip}:{local_port}>\r\n"
        f"Accept: application/sdp\r\n"
        f"User-Agent: Asterisk PBX 18.0.0\r\n"
        f"Content-Length: 0\r\n"
        f"\r\n"
    )

    print(f"\n[1] Enviando SIP OPTIONS (Ping) para {name} ({host}:{port})...")
    sock.sendto(msg.encode('utf-8'), (host, port))
    
    sock.settimeout(3.0)
    try:
        data, addr = sock.recvfrom(4096)
        resp = data.decode('utf-8', errors='ignore')
        first_line = resp.split('\r\n')[0]
        print(f"✅ RESPOSTA DA OKTOR ({addr[0]}): {first_line}")
        return True, resp
    except socket.timeout:
        print(f"❌ Sem resposta da OKTOR ({host}:{port}) após 3 segundos.")
        return False, None

def test_invite(host, port, destination, public_ip, sock):
    branch = f"z9hG4bK-{uuid.uuid4().hex[:10]}"
    call_id = f"{uuid.uuid4().hex[:16]}@{public_ip}"
    tag = uuid.uuid4().hex[:8]
    local_port = sock.getsockname()[1]
    rtp_port = 18000
    
    dial_num = f"59083{destination}"
    if not destination.startswith('55'):
        dial_num = f"5908355{destination}"

    sdp = (
        "v=0\r\n"
        f"o=dialgo 1 1 IN IP4 {public_ip}\r\n"
        "s=DialGO\r\n"
        f"c=IN IP4 {public_ip}\r\n"
        "t=0 0\r\n"
        f"m=audio {rtp_port} RTP/AVP 8 0 101\r\n"
        "a=rtpmap:8 PCMA/8000\r\n"
        "a=rtpmap:0 PCMU/8000\r\n"
        "a=rtpmap:101 telephone-event/8000\r\n"
        "a=sendrecv\r\n"
    )
    content_len = len(sdp.encode('utf-8'))

    msg = (
        f"INVITE sip:{dial_num}@{host}:{port} SIP/2.0\r\n"
        f"Via: SIP/2.0/UDP {public_ip}:{local_port};branch={branch};rport\r\n"
        f"Max-Forwards: 70\r\n"
        f"From: \"Dial GO AI\" <sip:{dial_num}@{host}>;tag={tag}\r\n"
        f"To: <sip:{dial_num}@{host}:{port}>\r\n"
        f"Call-ID: {call_id}\r\n"
        f"CSeq: 101 INVITE\r\n"
        f"Contact: <sip:{dial_num}@{public_ip}:{local_port}>\r\n"
        f"Allow: INVITE, ACK, CANCEL, BYE, OPTIONS\r\n"
        f"User-Agent: Asterisk PBX 18.0.0\r\n"
        f"Content-Type: application/sdp\r\n"
        f"Content-Length: {content_len}\r\n"
        f"\r\n"
        f"{sdp}"
    )

    print(f"\n[2] Enviando SIP INVITE para {host}:{port} -> Destino: {dial_num}...")
    sock.sendto(msg.encode('utf-8'), (host, port))
    
    sock.settimeout(5.0)
    for _ in range(3):
        try:
            data, addr = sock.recvfrom(4096)
            resp = data.decode('utf-8', errors='ignore')
            first_line = resp.split('\r\n')[0]
            print(f"📞 RESPOSTA SIP DA OKTOR ({addr[0]}): {first_line}")
            if "180" in first_line or "183" in first_line:
                print("🎉 SUCESSO! A chamada está TOCANDO no celular de destino!")
            elif "200" in first_line:
                print("🎉 ATENDIDA! Chamada conectada com sucesso!")
        except socket.timeout:
            break

def main():
    target_number = sys.argv[1] if len(sys.argv) > 1 else "21984354821"
    
    print("==========================================================")
    print("  DIAL GO - DIAGNÓSTICO E TESTE DE TRONCO SIP OKTOR")
    print("==========================================================")
    
    public_ip = get_public_ip()
    print(f"📡 IP Público Detectado: {public_ip}")
    
    # Create UDP socket
    sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    try:
        sock.bind(("0.0.0.0", 5060))
        print(f"🔌 Socket SIP local aberto na porta 5060")
    except:
        sock.bind(("0.0.0.0", 0))
        local_p = sock.getsockname()[1]
        print(f"🔌 Socket SIP local aberto na porta alternativa {local_p}")

    # 1. Test OPTIONS on Primary and Secondary
    for host, port, name in OKTOR_IPS:
        test_options(host, port, name, public_ip, sock)

    # 2. Test INVITE Call
    test_invite(OKTOR_IPS[0][0], OKTOR_IPS[0][1], target_number, public_ip, sock)
    
    sock.close()
    print("\n==========================================================")

if __name__ == "__main__":
    main()
