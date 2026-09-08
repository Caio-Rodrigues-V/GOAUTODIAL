"""
Dial GO Voice AI Engine - High-Performance Pure Python SIP Engine
Conecta diretamente com a OKTOR Telecom (200.196.232.250) sem necessidade de Asterisk.
Suporta chamadas simultâneas via asyncio / UDP RTP com streaming de áudio direto da IA.
"""

import socket
import asyncio
import uuid
import time
import logging
import re
from typing import Dict, Any, Optional
from rtp_media import RTPAudioSession
from ai_voice import AIVoiceBrain

logger = logging.getLogger("DialGO_SIP")

OKTOR_PRIMARY_IP = "200.196.232.250"
OKTOR_SECONDARY_IP = "177.154.149.210"
OKTOR_PORT = 5060
TECH_PREFIX = "59083"

class OktorSIPCall:
    def __init__(self, agent_id: int, phone_number: str, agent_config: Dict[str, Any] = None, api_keys: Dict[str, str] = None, local_ip: str = "0.0.0.0", local_port: int = 5060):
        self.agent_id = agent_id
        self.phone_number = phone_number
        self.agent_config = agent_config or {}
        self.api_keys = api_keys or {}
        self.call_id = f"{uuid.uuid4()}@dialgo-ai"
        self.from_tag = uuid.uuid4().hex[:8]
        self.to_tag = None
        self.cseq = 1
        self.status = "initiating"  # initiating, ringing, answered, completed, failed
        self.local_ip = local_ip
        self.local_port = local_port
        self.rtp_port = 12000 + (int(time.time() * 1000) % 20000)
        self.rtp_session: Optional[RTPAudioSession] = None
        self.remote_rtp_ip = OKTOR_PRIMARY_IP
        self.remote_rtp_port = 0
        
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
        logger.info("Socket SIP UDP aberto e pronto para receber/enviar sinalização")

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
                    logger.info(f"👉 IP '{self.public_ip}' pronto para Oktor Telecom")
                    logger.info(f"============================================================")
                    return
        except Exception as e:
            logger.warning(f"Não foi possível obter IP público via API: {e}")

        self.public_ip = "129.121.42.250"

    async def start(self):
        await self.detect_public_ip()
        loop = asyncio.get_running_loop()
        try:
            self.transport, _ = await loop.create_datagram_endpoint(
                lambda: SIPProtocol(self),
                local_addr=("0.0.0.0", self.port)
            )
            logger.info(f"SIP Engine ativo na porta {self.port}")
        except Exception as e:
            self.port = 50600
            self.transport, _ = await loop.create_datagram_endpoint(
                lambda: SIPProtocol(self),
                local_addr=("0.0.0.0", self.port)
            )
            logger.info(f"SIP Engine ativo na porta alternativa {self.port}")

    async def dial(self, agent_id: int, phone_number: str, agent_config: Dict[str, Any] = None, api_keys: Dict[str, str] = None) -> Dict[str, Any]:
        call = OktorSIPCall(agent_id, phone_number, agent_config=agent_config, api_keys=api_keys, local_ip=self.public_ip, local_port=self.port)
        self.active_calls[call.call_id] = call
        
        invite_msg = call.build_invite(self.public_ip)
        logger.info(f"Disparando SIP INVITE para Oktor Primário ({OKTOR_PRIMARY_IP}:{OKTOR_PORT}) - Destino: {call.dial_string}")
        
        if self.transport:
            # Envia exclusivamente para o SBC Primário da Oktor
            self.transport.sendto(invite_msg.encode('utf-8'), (OKTOR_PRIMARY_IP, OKTOR_PORT))
        
        call.status = "ringing"
        return {
            "status": "success",
            "call_id": call.call_id,
            "destination": call.dial_string,
            "server_ip": self.public_ip,
            "agent_id": agent_id,
            "message": f"Chamada SIP INVITE enviada para a Oktor ({call.dial_string})"
        }

    async def handle_sip_message(self, msg: str, addr):
        lines = msg.split("\r\n")
        first_line = lines[0] if lines else ""
        
        # 1. Tratar OPTIONS (Keep-Alive da Oktor)
        if first_line.startswith("OPTIONS "):
            logger.info(f"[Keep-Alive Oktor <- {addr[0]}]: Respondendo 200 OK")
            via_match = re.search(r'Via:\s*([^\r\n]+)', msg, re.IGNORECASE)
            from_match = re.search(r'From:\s*([^\r\n]+)', msg, re.IGNORECASE)
            to_match = re.search(r'To:\s*([^\r\n]+)', msg, re.IGNORECASE)
            callid_match = re.search(r'Call-ID:\s*([^\r\n]+)', msg, re.IGNORECASE)
            cseq_match = re.search(r'CSeq:\s*([^\r\n]+)', msg, re.IGNORECASE)
            
            resp = (
                "SIP/2.0 200 OK\r\n"
                + (f"Via: {via_match.group(1)}\r\n" if via_match else "")
                + (f"From: {from_match.group(1)}\r\n" if from_match else "")
                + (f"To: {to_match.group(1)};tag=dialgo123\r\n" if to_match else "")
                + (f"Call-ID: {callid_match.group(1)}\r\n" if callid_match else "")
                + (f"CSeq: {cseq_match.group(1)}\r\n" if cseq_match else "")
                + "User-Agent: DialGO-VoiceAI/1.0\r\n"
                + "Allow: INVITE, ACK, CANCEL, OPTIONS, BYE\r\n"
                + "Content-Length: 0\r\n\r\n"
            )
            if self.transport:
                self.transport.sendto(resp.encode('utf-8'), addr)
            return

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
            # Evita processar 200 OK duplicado
            if call.status == "answered":
                # Apenas reenviar ACK para confirmar
                ack_msg = call.build_ack(call.local_ip)
                if self.transport:
                    self.transport.sendto(ack_msg.encode('utf-8'), addr)
                return

            call.status = "answered"
            logger.info(f"Chamada {call.dial_string} foi ATENDIDA! Conectando áudio com a IA...")
            
            # Send ACK
            ack_msg = call.build_ack(call.local_ip)
            if self.transport:
                self.transport.sendto(ack_msg.encode('utf-8'), addr)

            # Extrair IP e Porta RTP do SDP da Oktor
            c_match = re.search(r'c=IN IP4 ([0-9.]+)', msg)
            m_match = re.search(r'm=audio (\d+)', msg)
            
            remote_media_ip = c_match.group(1) if c_match else addr[0]
            remote_media_port = int(m_match.group(1)) if m_match else 10000
            
            # Detectar codec preferido no SDP (PCMA = 8, PCMU = 0)
            codec = "PCMA"
            if "PCMU" in msg and "PCMA" not in msg:
                codec = "PCMU"

            logger.info(f"Conexão RTP estabelecida: Destino Oktor Mídia = {remote_media_ip}:{remote_media_port} (Codec: {codec})")
            
            # Iniciar Streaming RTP da IA
            asyncio.create_task(self.start_ai_conversation(call, remote_media_ip, remote_media_port, codec))

        elif " 486 Busy" in first_line or " 603 Decline" in first_line or " 487 Request Terminated" in first_line:
            call.status = "busy"
            logger.info(f"Chamada {call.dial_string} foi rejeitada ou ocupada.")
            if call.rtp_session:
                call.rtp_session.stop()
            self.active_calls.pop(call_id, None)

        elif "BYE " in first_line:
            call.status = "completed"
            logger.info(f"Chamada {call.dial_string} finalizada.")
            if call.rtp_session:
                call.rtp_session.stop()
            self.active_calls.pop(call_id, None)

    async def start_ai_conversation(self, call: OktorSIPCall, remote_ip: str, remote_port: int, codec: str = "PCMA"):
        """
        Orquestra a fala inicial e a interação contínua da IA na linha telefônica (STT -> LLM -> TTS).
        """
        try:
            call.rtp_session = RTPAudioSession(call.rtp_port, remote_ip, remote_port, codec=codec)
            call.rtp_session.start_socket()

            # Configurações do Agente de IA
            agent = call.agent_config or {}
            greeting = agent.get("greeting_message") or "Olá, tudo bem? Falo com o titular da linha?"
            system_prompt = agent.get("system_prompt") or "Você é um assistente virtual gentil e direto. Responda em no máximo 2 frases curtas."
            voice_provider = agent.get("voice_provider") or "openai"
            voice_id = agent.get("voice_id") or "nova"
            llm_provider = agent.get("llm_provider") or "groq"
            llm_model = agent.get("llm_model") or "llama-3.3-70b-versatile"
            stt_provider = agent.get("stt_provider") or "deepgram"
            temperature = float(agent.get("temperature", 0.7))

            # Histórico da Conversa
            conversation_history = [
                {"role": "system", "content": system_prompt},
                {"role": "assistant", "content": greeting}
            ]

            # Callback quando o cliente termina de falar
            async def handle_user_speech(pcm_audio: bytes):
                try:
                    logger.info(f"Processando fala do cliente ({len(pcm_audio)} bytes PCM)...")
                    
                    # 1. Speech-to-Text (STT)
                    transcript = await AIVoiceBrain.transcribe(pcm_audio, stt_provider, call.api_keys)
                    if not transcript or len(transcript.strip()) < 2:
                        return

                    logger.info(f"🗣️ [Cliente Disse]: \"{transcript}\"")
                    conversation_history.append({"role": "user", "content": transcript})

                    # 2. Cérebro LLM (Groq / OpenAI)
                    ai_reply = await AIVoiceBrain.chat_completion(
                        conversation_history, 
                        llm_provider, 
                        llm_model, 
                        temperature, 
                        call.api_keys
                    )
                    logger.info(f"🤖 [IA Formulou Resposta]: \"{ai_reply}\"")
                    conversation_history.append({"role": "assistant", "content": ai_reply})

                    # 3. Text-to-Speech (TTS)
                    pcm_reply = await AIVoiceBrain.synthesize(ai_reply, voice_provider, voice_id, call.api_keys)
                    if pcm_reply and len(pcm_reply) > 0:
                        # 4. Transmitir áudio da resposta para o telefone
                        await call.rtp_session.stream_pcm_audio(pcm_reply)

                except Exception as ex:
                    logger.error(f"Erro no ciclo de conversa: {ex}")

            # Vincular callbacks na sessão RTP
            call.rtp_session.on_speech_ready = handle_user_speech
            call.rtp_session.on_barge_in = lambda: logger.info("Barge-in acionado: IA parou de falar para escutar o cliente.")

            # 1. Saudação Inicial do Agente
            logger.info(f"[IA Saudação Inicial]: '{greeting}' (Voz: {voice_provider}/{voice_id})")
            pcm_audio = await AIVoiceBrain.synthesize(greeting, voice_provider, voice_id, call.api_keys)
            
            if pcm_audio and len(pcm_audio) > 0:
                await call.rtp_session.stream_pcm_audio(pcm_audio)
            else:
                logger.warning("Nenhum áudio gerado para a saudação inicial.")

        except Exception as e:
            logger.error(f"Erro ao iniciar diálogo de voz da IA: {e}")
