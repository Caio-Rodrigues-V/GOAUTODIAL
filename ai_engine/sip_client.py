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
        self.start_time = time.time()
        self.answered_time = 0.0
        self.conversation_history = []
        
        # Métricas de Custo Real da Chamada
        self.stt_seconds = 0.0
        self.tts_characters = 0
        self.llm_input_tokens = 0
        self.llm_output_tokens = 0
        self.cost_breakdown = {}
        
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
            call.answered_time = time.time()
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
            asyncio.create_task(self.save_call_to_crm(call, "busy"))
            self.active_calls.pop(call_id, None)

        elif "BYE " in first_line:
            call.status = "completed"
            logger.info(f"Chamada {call.dial_string} finalizada pelo cliente/operadora.")
            if call.rtp_session:
                call.rtp_session.stop()
            asyncio.create_task(self.save_call_to_crm(call, "completed"))
            self.active_calls.pop(call_id, None)

    async def send_bye(self, call_id: str):
        """Envia sinalização SIP BYE para encerrar a chamada na Oktor e grava o log"""
        call = self.active_calls.get(call_id)
        if not call:
            return
        logger.info(f"Enviando SIP BYE para a Oktor Telecom - Chamada {call.dial_string}")
        try:
            bye_msg = call.build_bye(call.local_ip)
            if self.transport:
                self.transport.sendto(bye_msg.encode('utf-8'), (OKTOR_PRIMARY_IP, OKTOR_PORT))
        except Exception as ex:
            logger.warning(f"Erro ao enviar SIP BYE: {ex}")

        call.status = "completed"
        if call.rtp_session:
            call.rtp_session.stop()
        await self.save_call_to_crm(call, "completed")
        self.active_calls.pop(call_id, None)

    async def save_call_to_crm(self, call: OktorSIPCall, status: str):
        """Salva a transcrição, áudio gravado e métricas da chamada no banco de dados via API PHP"""
        try:
            duration = int(time.time() - call.answered_time) if call.answered_time > 0 else 0
            agent = call.agent_config or {}
            
            # Obter áudio gravado da sessão RTP se disponível
            audio_b64 = ""
            clean_call_id = re.sub(r'[^a-zA-Z0-9_-]', '_', call.call_id)
            recording_filename = f"ai_call_{clean_call_id}.wav"
            recording_rel_path = f"recordings/{recording_filename}"
            
            if call.rtp_session:
                try:
                    wav_bytes = call.rtp_session.get_recorded_wav()
                    if wav_bytes and len(wav_bytes) > 44:
                        import base64
                        audio_b64 = base64.b64encode(wav_bytes).decode('utf-8')
                        
                        # Salvar localmente no servidor
                        import os
                        candidate_dirs = [
                            os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "recordings"),
                            os.path.join(os.path.dirname(os.path.abspath(__file__)), "recordings"),
                            "/var/www/html/recordings"
                        ]
                        for r_dir in candidate_dirs:
                            try:
                                os.makedirs(r_dir, exist_ok=True)
                                dest_file = os.path.join(r_dir, recording_filename)
                                with open(dest_file, "wb") as f:
                                    f.write(wav_bytes)
                                logger.info(f"Gravação de áudio da chamada salva em: {dest_file}")
                            except Exception:
                                pass
                except Exception as ex:
                    logger.warning(f"Erro ao empacotar gravação de áudio: {ex}")

            # Análise Inteligente de Tabulação, Resumo e Sentimento via OpenAI / Groq
            analysis = await AIVoiceBrain.analyze_call(call.conversation_history, call.api_keys)
            qualification = analysis.get("tabulation", "Atendida")
            call_summary = analysis.get("summary", "")
            sentiment = analysis.get("sentiment", "Neutro")
            action_needed = analysis.get("action", "")

            # -------------------------------------------------------------
            # Cálculo Real e Preciso de Custo por Ligação (STT + LLM + TTS + Telefonia)
            # -------------------------------------------------------------
            stt_prov = (agent.get("stt_provider") or "deepgram").lower()
            llm_prov = (agent.get("llm_provider") or "groq").lower()
            llm_mod = (agent.get("llm_model") or "llama-3.3-70b-versatile").lower()
            voice_prov = (agent.get("voice_provider") or "elevenlabs").lower()

            # 1. Custo de Transcrição (STT) por minuto de fala do cliente
            stt_rate = 0.0043 if "deepgram" in stt_prov else (0.0020 if "groq" in stt_prov else 0.0060)
            cost_stt_usd = (call.stt_seconds / 60.0) * stt_rate

            # 2. Custo de Inteligência Artificial (LLM) por 1.000 tokens
            if "groq" in llm_prov:
                in_rate, out_rate = (0.00005, 0.00008) if "8b" in llm_mod else (0.00059, 0.00079)
            elif "deepseek" in llm_prov:
                in_rate, out_rate = 0.00014, 0.00028
            elif "openai" in llm_prov:
                in_rate, out_rate = (0.00015, 0.00060) if ("mini" in llm_mod or "nano" in llm_mod) else (0.00250, 0.01000)
            else:
                in_rate, out_rate = 0.00050, 0.00080

            cost_llm_usd = (call.llm_input_tokens / 1000.0 * in_rate) + (call.llm_output_tokens / 1000.0 * out_rate)

            # 3. Custo de Síntese de Voz (TTS) por 1.000 caracteres
            if "elevenlabs" in voice_prov:
                tts_rate = 0.030
            elif "cartesia" in voice_prov or "openai" in voice_prov:
                tts_rate = 0.015
            else:
                tts_rate = 0.016

            cost_tts_usd = (call.tts_characters / 1000.0) * tts_rate

            # 4. Custo de Telefonia SIP Trunk Oktor (R$ 0,040 / min)
            cost_telephony_brl = (duration / 60.0) * 0.040

            # Câmbio USD -> BRL
            usd_brl = 5.80
            total_ai_usd = cost_stt_usd + cost_llm_usd + cost_tts_usd
            total_ai_brl = total_ai_usd * usd_brl
            cost_estimate = round(max(0.0050, total_ai_brl + cost_telephony_brl), 4)

            logger.info(f"📊 [Custo Real da Chamada]: Total=R$ {cost_estimate:.4f} (STT: R$ {cost_stt_usd*usd_brl:.4f}, LLM: R$ {cost_llm_usd*usd_brl:.4f}, TTS: R$ {cost_tts_usd*usd_brl:.4f}, Tel: R$ {cost_telephony_brl:.4f}) | Tabulação='{qualification}' ({sentiment})")

            payload = {
                "call_id": call.call_id,
                "agent_id": call.agent_id,
                "agent_name": agent.get("agent_name", "Agente IA"),
                "phone_number": call.phone_number,
                "status": status,
                "duration_seconds": duration,
                "llm_provider": agent.get("llm_provider", "groq"),
                "llm_model": agent.get("llm_model", "llama-3.3-70b-versatile"),
                "voice_provider": agent.get("voice_provider", "openai"),
                "voice_id": agent.get("voice_id", "nova"),
                "stt_provider": agent.get("stt_provider", "deepgram"),
                "transcript_json": call.conversation_history,
                "recording_url": recording_rel_path if audio_b64 else "",
                "audio_base64": audio_b64,
                "qualification": qualification,
                "tabulation": qualification,
                "call_summary": call_summary,
                "sentiment": sentiment,
                "action_needed": action_needed,
                "cost_estimate": cost_estimate
            }

            # Candidatos de endpoint PHP do CRM
            candidate_urls = []
            if agent.get("callback_url"):
                candidate_urls.append(agent["callback_url"])
                if agent["callback_url"].startswith("https://"):
                    candidate_urls.append(agent["callback_url"].replace("https://", "http://"))
                elif agent["callback_url"].startswith("http://"):
                    candidate_urls.append(agent["callback_url"].replace("http://", "https://"))
            
            candidate_urls.extend([
                "https://127.0.0.1/php/SaveAICallLog.php",
                "http://127.0.0.1/php/SaveAICallLog.php",
                "https://localhost/php/SaveAICallLog.php",
                "http://localhost/php/SaveAICallLog.php",
                "https://127.0.0.1/goautodial/php/SaveAICallLog.php",
                "http://127.0.0.1/goautodial/php/SaveAICallLog.php"
            ])

            import httpx
            saved = False
            for crm_url in candidate_urls:
                try:
                    async with httpx.AsyncClient(timeout=5.0, verify=False) as client:
                        resp = await client.post(crm_url, json=payload)
                        if resp.status_code == 200:
                            logger.info(f"Log, gravação e transcrição salvos com sucesso no CRM via {crm_url}!")
                            saved = True
                            break
                except Exception:
                    continue

            # Grava backup local em JSON Lines para segurança
            try:
                os.makedirs("recordings", exist_ok=True)
                with open("recordings/ai_call_history.jsonl", "a", encoding="utf-8") as f:
                    f.write(json.dumps(payload, ensure_ascii=False) + "\n")
            except Exception:
                pass

            if not saved:
                logger.info("Log da chamada preservado localmente no histórico de gravações.")
        except Exception as e:
            logger.error(f"Exceção ao gravar log da chamada no CRM: {e}")

    async def start_ai_conversation(self, call: OktorSIPCall, remote_ip: str, remote_port: int, codec: str = "PCMA"):
        """
        Orquestra a fala inicial e a interação contínua da IA na linha telefônica (STT -> LLM -> TTS)
        com suporte a ferramentas Vapi (end_call, voicemail_tool, transfer_call).
        """
        try:
            agent = call.agent_config or {}
            
            # Configurações do Agente de IA
            silence_timeout_ms = int(agent.get("silence_timeout_ms") or 500)
            silence_timeout_sec = max(0.2, min(5.0, silence_timeout_ms / 1000.0))
            background_sound = agent.get("background_sound") or "off"
            background_volume = float(agent.get("background_sound_volume") or 0.05)
            
            call.rtp_session = RTPAudioSession(
                call.rtp_port, remote_ip, remote_port,
                codec=codec,
                silence_timeout=silence_timeout_sec,
                background_sound=background_sound,
                background_volume=background_volume
            )
            call.rtp_session.start_socket()

            first_message_mode = agent.get("first_message_mode") or "assistant_speaks_first"
            greeting = agent.get("greeting_message") or "Olá, tudo bem? Falo com o titular da linha?"
            system_prompt = agent.get("system_prompt") or "Você é um assistente virtual gentil e direto. Responda em no máximo 2 frases curtas."
            voice_provider = agent.get("voice_provider") or "openai"
            voice_id = agent.get("voice_id") or "nova"
            llm_provider = agent.get("llm_provider") or "groq"
            llm_model = agent.get("llm_model") or "llama-3.3-70b-versatile"
            stt_provider = agent.get("stt_provider") or "deepgram"
            temperature = float(agent.get("temperature", 0.7))
            hangup_on_vm = agent.get("hangup_on_voicemail", "Y") == "Y"

            # Histórico da Conversa
            call.conversation_history = [
                {"role": "system", "content": system_prompt}
            ]
            if first_message_mode == "assistant_speaks_first":
                call.conversation_history.append({"role": "assistant", "content": greeting})

            # Trava para garantir processamento de um turno por vez
            speech_lock = asyncio.Lock()

            # Callback quando o cliente termina de falar
            async def handle_user_speech(pcm_audio: bytes):
                if speech_lock.locked():
                    return
                
                async with speech_lock:
                    try:
                        logger.info(f"Processando fala do cliente ({len(pcm_audio)} bytes PCM)...")
                        call.stt_seconds += len(pcm_audio) / 16000.0
                        
                        # 1. Speech-to-Text (STT)
                        transcript = await AIVoiceBrain.transcribe(pcm_audio, stt_provider, call.api_keys)
                        if not transcript or len(transcript.strip()) < 2:
                            return

                        logger.info(f"🗣️ [Cliente Disse]: \"{transcript}\"")
                        call.conversation_history.append({"role": "user", "content": transcript})

                        # Detecção de Caixa Postal / Secretária Eletrônica (Voicemail Tool)
                        lower_text = transcript.lower()
                        vm_keywords = ["deixe seu recado", "após o sinal", "caixa postal", "deixe recado", "não pode atender", "chamada encaminhada"]
                        if hangup_on_vm and any(k in lower_text for k in vm_keywords):
                            logger.info(f"🛑 [Voicemail Tool]: Caixa postal detectada ('{transcript}'). Encerrando chamada...")
                            await self.send_bye(call.call_id)
                            return

                        # 2. Cérebro LLM (Groq / OpenAI)
                        prompt_toks = sum(len(m.get("content", "")) for m in call.conversation_history) // 4
                        call.llm_input_tokens += prompt_toks

                        ai_reply = await AIVoiceBrain.chat_completion(
                            call.conversation_history, 
                            llm_provider, 
                            llm_model, 
                            temperature, 
                            call.api_keys
                        )
                        logger.info(f"🤖 [IA Formulou Resposta]: \"{ai_reply}\"")
                        call.conversation_history.append({"role": "assistant", "content": ai_reply})
                        call.llm_output_tokens += len(ai_reply) // 4

                        # Checa se o encerramento foi solicitado (end_call tool)
                        should_end_call = "[END_CALL]" in ai_reply or "tchau" in ai_reply.lower() and len(call.conversation_history) > 4
                        clean_reply = ai_reply.replace("[END_CALL]", "").strip()

                        # 3. Text-to-Speech (TTS)
                        call.tts_characters += len(clean_reply)
                        pcm_reply = await AIVoiceBrain.synthesize(clean_reply, voice_provider, voice_id, call.api_keys, agent)
                        if pcm_reply and len(pcm_reply) > 0:
                            # 4. Transmitir áudio da resposta para o telefone
                            await call.rtp_session.stream_pcm_audio(pcm_reply)

                        if should_end_call:
                            logger.info("👋 [End Call Tool]: Encerrando chamada após fala de despedida...")
                            await asyncio.sleep(0.8)
                            await self.send_bye(call.call_id)

                    except Exception as ex:
                        logger.error(f"Erro no ciclo de conversa: {ex}")

            # Vincular callbacks na sessão RTP
            call.rtp_session.on_speech_ready = handle_user_speech
            call.rtp_session.on_barge_in = lambda: logger.info("Barge-in acionado: IA parou de falar para escutar o cliente.")

            # 1. Saudação Inicial do Agente (se configurado para falar primeiro)
            if first_message_mode == "assistant_speaks_first":
                logger.info(f"[IA Saudação Inicial]: '{greeting}' (Voz: {voice_provider}/{voice_id})")
                call.tts_characters += len(greeting)
                pcm_audio = await AIVoiceBrain.synthesize(greeting, voice_provider, voice_id, call.api_keys, agent)
                
                if pcm_audio and len(pcm_audio) > 0:
                    await call.rtp_session.stream_pcm_audio(pcm_audio)
                else:
                    logger.warning("Nenhum áudio gerado para a saudação inicial.")
            else:
                logger.info("⏳ [Modo User Speaks First]: Aguardando cliente iniciar a conversa na linha...")

        except Exception as e:
            logger.error(f"Erro ao iniciar diálogo de voz da IA: {e}")
