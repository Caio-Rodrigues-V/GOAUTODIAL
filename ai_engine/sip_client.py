"""
Dial GO Voice AI Engine - High-Performance Pure Python SIP Engine
Conecta diretamente com a OKTOR Telecom (200.196.232.250) sem necessidade de Asterisk.
Suporta até centenas de chamadas simultâneas via asyncio / UDP RTP.
"""

import socket
import asyncio
import uuid
import time
import logging
import re
from typing import Dict, Any, Optional

logger = logging.getLogger("DialGO_SIP")

OKTOR_PRIMARY_IP = "200.196.232.250"
OKTOR_SECONDARY_IP = "177.154.149.210"
OKTOR_PORT = 5060
TECH_PREFIX = "59083"

class OktorSIPCall:
    def __init__(self, agent_id: int, phone_number: str, local_ip: str = "0.0.0.0", local_port: int = 5060):
        self.agent_id = agent_id
        self.phone_number = phone_number
        self.call_id = f"{uuid.uuid4()}@dialgo-ai"
        self.from_tag = uuid.uuid4().hex[:8]
        self.to_tag = None
        self.cseq = 1
        self.status = "initiating"  # initiating, ringing, answered, completed, failed
        self.local_ip = local_ip
        self.local_port = local_port
        self.rtp_port = 10000 + (int(time.time()) % 20000)
        
        # Format destination: 59083 + 55 + DDD + NUM
        digits = re.sub(r'\D', '', phone_number)
        if digits.startswith('0'):
            digits = digits[1:]
        if not digits.startswith('55'):
            digits = '55' + digits
        self.destination_e164 = digits
        self.dial_string = f"{TECH_PREFIX}{digits}"

    def build_invite(self, server_ip: str) -> str:
        sdp = (
            "v=0\r\n"
            f"o=dialgo_ai {int(time.time())} {int(time.time())} IN IP4 {server_ip}\r\n"
            "s=Dial GO Voice AI\r\n"
            f"c=IN IP4 {server_ip}\r\n"
            "t=0 0\r\n"
            f"m=audio {self.rtp_port} RTP/AVP 8 0 101\r\n"
            "a=rtpmap:8 PCMA/8000\r\n"
            "a=rtpmap:0 PCMU/8000\r\n"
            "a=rtpmap:101 telephone-event/8000\r\n"
            "a=fmtp:101 0-16\r\n"
            "a=sendrecv\r\n"
        )

        content_length = len(sdp.encode('utf-8'))
        
        invite = (
            f"INVITE sip:{self.dial_string}@{OKTOR_PRIMARY_IP}:{OKTOR_PORT} SIP/2.0\r\n"
            f"Via: SIP/2.0/UDP {server_ip}:{self.local_port};branch=z9hG4bK-{uuid.uuid4().hex[:12]};rport\r\n"
            f"Max-Forwards: 70\r\n"
            f"From: \"Dial GO AI\" <sip:{self.dial_string}@{server_ip}>;tag={self.from_tag}\r\n"
            f"To: <sip:{self.dial_string}@{OKTOR_PRIMARY_IP}:{OKTOR_PORT}>\r\n"
            f"Call-ID: {self.call_id}\r\n"
            f"CSeq: {self.cseq} INVITE\r\n"
            f"Contact: <sip:{self.dial_string}@{server_ip}:{self.local_port}>\r\n"
            "Allow: INVITE, ACK, CANCEL, OPTIONS, BYE, REFER, NOTIFY, MESSAGE, SUBSCRIBE, INFO\r\n"
            "User-Agent: DialGO-VoiceAI/1.0\r\n"
            "Content-Type: application/sdp\r\n"
            f"Content-Length: {content_length}\r\n"
            "\r\n"
            f"{sdp}"
        )
        return invite

    def build_ack(self, server_ip: str) -> str:
        ack = (
            f"ACK sip:{self.dial_string}@{OKTOR_PRIMARY_IP}:{OKTOR_PORT} SIP/2.0\r\n"
            f"Via: SIP/2.0/UDP {server_ip}:{self.local_port};branch=z9hG4bK-{uuid.uuid4().hex[:12]};rport\r\n"
            f"Max-Forwards: 70\r\n"
            f"From: \"Dial GO AI\" <sip:{self.dial_string}@{server_ip}>;tag={self.from_tag}\r\n"
            f"To: <sip:{self.dial_string}@{OKTOR_PRIMARY_IP}:{OKTOR_PORT}>" + (f";tag={self.to_tag}" if self.to_tag else "") + "\r\n"
            f"Call-ID: {self.call_id}\r\n"
            f"CSeq: {self.cseq} ACK\r\n"
            "User-Agent: DialGO-VoiceAI/1.0\r\n"
            "Content-Length: 0\r\n"
            "\r\n"
        )
        return ack

    def build_bye(self, server_ip: str) -> str:
        self.cseq += 1
        bye = (
            f"BYE sip:{self.dial_string}@{OKTOR_PRIMARY_IP}:{OKTOR_PORT} SIP/2.0\r\n"
            f"Via: SIP/2.0/UDP {server_ip}:{self.local_port};branch=z9hG4bK-{uuid.uuid4().hex[:12]};rport\r\n"
            f"Max-Forwards: 70\r\n"
            f"From: \"Dial GO AI\" <sip:{self.dial_string}@{server_ip}>;tag={self.from_tag}\r\n"
            f"To: <sip:{self.dial_string}@{OKTOR_PRIMARY_IP}:{OKTOR_PORT}>" + (f";tag={self.to_tag}" if self.to_tag else "") + "\r\n"
            f"Call-ID: {self.call_id}\r\n"
            f"CSeq: {self.cseq} BYE\r\n"
            "User-Agent: DialGO-VoiceAI/1.0\r\n"
            "Content-Length: 0\r\n"
            "\r\n"
        )
        return bye

class SIPProtocol(asyncio.DatagramProtocol):
    def __init__(self, engine):
        self.engine = engine
        self.transport = None

    def connection_made(self, transport):
        self.transport = transport
        logger.info(f"Socket SIP UDP aberto e pronto para receber/enviar sinalização")

    def datagram_received(self, data, addr):
        message = data.decode('utf-8', errors='ignore')
        asyncio.create_task(self.engine.handle_sip_message(message, addr))

class DirectSIPEngine:
    def __init__(self, port: int = 5060):
        self.port = port
        self.public_ip = "127.0.0.1"
        self.active_calls: Dict[str, OktorSIPCall] = {}
        self.transport = None

    async def detect_public_ip(self):
        try:
            import httpx
            async with httpx.AsyncClient(timeout=4.0) as client:
                r = await client.get("https://api.ipify.org")
                if r.status_code == 200:
                    self.public_ip = r.text.strip()
                    logger.info(f"============================================================")
                    logger.info(f"📡 IP Público do Servidor Detectado: {self.public_ip}")
                    logger.info(f"👉 Confirme se o IP '{self.public_ip}' está autorizado na sua conta da OKTOR!")
                    logger.info(f"============================================================")
                    return
        except Exception as e:
            logger.warning(f"Não foi possível obter IP público via API: {e}")

        # Fallback local socket
        try:
            s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
            s.connect((OKTOR_PRIMARY_IP, 80))
            self.public_ip = s.getsockname()[0]
            s.close()
        except:
            self.public_ip = "127.0.0.1"

    async def start(self):
        await self.detect_public_ip()
        loop = asyncio.get_running_loop()
        try:
            # Bind to 0.0.0.0:5060 or ephemeral port if 5060 is taken
            self.transport, _ = await loop.create_datagram_endpoint(
                lambda: SIPProtocol(self),
                local_addr=("0.0.0.0", self.port)
            )
            logger.info(f"SIP Engine ativo na porta {self.port}")
        except Exception as e:
            # Fallback to random high port (50600) if 5060 is restricted in cPanel
            self.port = 50600
            self.transport, _ = await loop.create_datagram_endpoint(
                lambda: SIPProtocol(self),
                local_addr=("0.0.0.0", self.port)
            )
            logger.info(f"SIP Engine ativo na porta alternativa {self.port}")

    async def dial(self, agent_id: int, phone_number: str) -> Dict[str, Any]:
        call = OktorSIPCall(agent_id, phone_number, local_ip=self.public_ip, local_port=self.port)
        self.active_calls[call.call_id] = call
        
        invite_msg = call.build_invite(self.public_ip)
        logger.info(f"Disparando SIP INVITE para Oktor ({OKTOR_PRIMARY_IP}:{OKTOR_PORT}) - Destino: {call.dial_string}")
        
        if self.transport:
            # Send to Primary SBC
            self.transport.sendto(invite_msg.encode('utf-8'), (OKTOR_PRIMARY_IP, OKTOR_PORT))
            # Also send to Secondary SBC
            self.transport.sendto(invite_msg.encode('utf-8'), (OKTOR_SECONDARY_IP, OKTOR_PORT))
        
        call.status = "ringing"
        return {
            "status": "success",
            "call_id": call.call_id,
            "destination": call.dial_string,
            "server_ip": self.public_ip,
            "agent_id": agent_id,
            "message": f"Chamada SIP INVITE enviada para a Oktor ({call.dial_string})"
        }
        
        call.status = "ringing"
        return {
            "status": "success",
            "call_id": call.call_id,
            "destination": call.dial_string,
            "agent_id": agent_id,
            "message": f"Chamada SIP INVITE enviada para a Oktor ({call.dial_string})"
        }

    async def handle_sip_message(self, msg: str, addr):
        first_line = msg.split("\r\n")[0]
        logger.info(f"[SIP <- Oktor {addr[0]}]: {first_line}")

        # Extract Call-ID
        call_id_match = re.search(r'Call-ID:\s*([^\r\n]+)', msg, re.IGNORECASE)
        if not call_id_match:
            return
        
        call_id = call_id_match.group(1).strip()
        call = self.active_calls.get(call_id)
        if not call:
            return

        # Extract To Tag if available
        to_match = re.search(r'To:[^\r\n]+;tag=([^\r\n;]+)', msg, re.IGNORECASE)
        if to_match:
            call.to_tag = to_match.group(1).strip()

        if " 180 Ringing" in first_line or " 183 Session Progress" in first_line:
            call.status = "ringing"
            logger.info(f"Chamada {call.dial_string} está TOCANDO no celular do cliente!")

        elif " 200 OK" in first_line:
            call.status = "answered"
            logger.info(f"Chamada {call.dial_string} foi ATENDIDA! Conectando áudio com a IA...")
            
            # Send ACK
            ack_msg = call.build_ack(call.local_ip)
            if self.transport:
                self.transport.sendto(ack_msg.encode('utf-8'), (OKTOR_PRIMARY_IP, OKTOR_PORT))

        elif " 486 Busy" in first_line or " 603 Decline" in first_line or " 487 Request Terminated" in first_line:
            call.status = "busy"
            logger.info(f"Chamada {call.dial_string} foi rejeitada ou ocupada.")
            self.active_calls.pop(call_id, None)

        elif "BYE " in first_line:
            call.status = "completed"
            logger.info(f"Chamada {call.dial_string} finalizada.")
            self.active_calls.pop(call_id, None)
