"""
Dial GO Voice AI Engine - RTP Media Streamer and Audio Codec Bridge
Converte áudio de síntese de voz (OpenAI TTS, ElevenLabs, Cartesia) em pacotes RTP (RFC 3550) 
com codec G.711 A-law (PCMA) / Mu-law (PCMU) a 8000Hz para transmissão direta à Oktor Telecom.
"""

import socket
import asyncio
import struct
import time
import io
import wave
import logging
from typing import Optional

logger = logging.getLogger("DialGO_RTP")

# Tabela padrão ITU-T G.711 A-law
def pcm16_to_alaw_byte(sample: int) -> int:
    """Converte um sample PCM 16-bit com sinal (-32768 a 32767) para 1 byte G.711 A-law (ITU-T G.711)"""
    if sample < 0:
        sample = -sample - 1
        sign = 0x00
    else:
        sign = 0x80

    if sample > 32767:
        sample = 32767

    if sample >= 256:
        exponent = 7
        for exp in range(1, 8):
            if sample < (256 << exp):
                exponent = exp
                break
        mantissa = (sample >> (exponent + 3)) & 0x0F
        alaw = sign | (exponent << 4) | mantissa
    else:
        alaw = sign | (sample >> 4)

    return alaw ^ 0xD5  # Inversão padrão de bits pares ITU-T

def linear_to_alaw(pcm_bytes: bytes) -> bytes:
    """Converte PCM 16-bit Little Endian (8000Hz) para G.711 A-law (8-bit)"""
    try:
        import audioop
        return audioop.lin2alaw(pcm_bytes, 2)
    except:
        out = bytearray(len(pcm_bytes) // 2)
        idx = 0
        for i in range(0, len(pcm_bytes) - 1, 2):
            sample = int.from_bytes(pcm_bytes[i:i+2], byteorder='little', signed=True)
            out[idx] = pcm16_to_alaw_byte(sample)
            idx += 1
        return bytes(out)

def linear_to_ulaw(pcm_bytes: bytes) -> bytes:
    """Converte PCM 16-bit Little Endian (8000Hz) para G.711 Mu-law (8-bit)"""
    try:
        import audioop
        return audioop.lin2ulaw(pcm_bytes, 2)
    except:
        return linear_to_alaw(pcm_bytes)

def resample_24k_to_8k_pcm(pcm_24k: bytes) -> bytes:
    """Decimação exata 3:1 de PCM 16-bit 24000Hz para 8000Hz"""
    out = bytearray()
    # Pega 1 amostra de 2 bytes a cada 6 bytes (3 amostras)
    for i in range(0, len(pcm_24k) - 5, 6):
        out.extend(pcm_24k[i:i+2])
    return bytes(out)

def resample_wav_to_8k_pcm(wav_bytes: bytes) -> bytes:
    """Decodifica WAV de qualquer taxa de amostragem para PCM 16-bit 8000Hz Mono"""
    try:
        with wave.open(io.BytesIO(wav_bytes), 'rb') as wav:
            nchannels = wav.getnchannels()
            sampwidth = wav.getsampwidth()
            framerate = wav.getframerate()
            frames = wav.readframes(wav.getnframes())

            if framerate == 24000 and sampwidth == 2:
                # Se estéreo, converte para mono
                if nchannels == 2:
                    mono = bytearray()
                    for i in range(0, len(frames) - 3, 4):
                        mono.extend(frames[i:i+2])
                    frames = bytes(mono)
                return resample_24k_to_8k_pcm(frames)

            try:
                import audioop
                if nchannels == 2:
                    frames = audioop.tomono(frames, sampwidth, 0.5, 0.5)
                if framerate != 8000:
                    frames, _ = audioop.ratecv(frames, sampwidth, 1, framerate, 8000, None)
                if sampwidth != 2:
                    frames = audioop.lin2lin(frames, sampwidth, 2)
                return frames
            except:
                return resample_24k_to_8k_pcm(frames)
    except Exception as e:
        logger.error(f"Erro ao processar áudio WAV: {e}")
        return b''

class RTPPacket:
    @staticmethod
    def build(payload: bytes, seq: int, timestamp: int, ssrc: int, payload_type: int = 8, marker: int = 0) -> bytes:
        """
        Cria cabeçalho RTP RFC 3550 de 12 bytes + payload de áudio.
        Payload Type 8 = PCMA (G.711 A-law, padrão Brasil/Europa)
        Payload Type 0 = PCMU (G.711 Mu-law)
        """
        version = 2
        padding = 0
        extension = 0
        csrc_count = 0
        
        byte0 = (version << 6) | (padding << 5) | (extension << 4) | csrc_count
        byte1 = ((marker & 1) << 7) | (payload_type & 0x7F)
        
        header = struct.pack('!BBHII', byte0, byte1, seq & 0xFFFF, timestamp & 0xFFFFFFFF, ssrc & 0xFFFFFFFF)
        return header + payload

# Tabela ITU-T G.711 A-law para Linear PCM 16-bit
def alaw_to_pcm16_byte(b: int) -> int:
    """Converte 1 byte G.711 A-law para inteiro 16-bit"""
    b = b ^ 0xD5
    sign = b & 0x80
    exponent = (b & 0x70) >> 4
    mantissa = b & 0x0F
    
    if exponent == 0:
        sample = (mantissa << 4) + 8
    else:
        sample = ((mantissa << 4) + 0x108) << (exponent - 1)
        
    return -sample if sign == 0 else sample

def alaw_to_linear(alaw_bytes: bytes) -> bytes:
    """Converte G.711 A-law (8-bit) para PCM 16-bit Little Endian (8000Hz)"""
    try:
        import audioop
        return audioop.alaw2lin(alaw_bytes, 2)
    except:
        out = bytearray(len(alaw_bytes) * 2)
        idx = 0
        for b in alaw_bytes:
            sample = alaw_to_pcm16_byte(b)
            out[idx:idx+2] = sample.to_bytes(2, byteorder='little', signed=True)
            idx += 2
        return bytes(out)

def ulaw_to_linear(ulaw_bytes: bytes) -> bytes:
    """Converte G.711 Mu-law (8-bit) para PCM 16-bit Little Endian (8000Hz)"""
    try:
        import audioop
        return audioop.ulaw2lin(ulaw_bytes, 2)
    except:
        return alaw_to_linear(ulaw_bytes)

def calculate_rms(pcm_bytes: bytes) -> float:
    """Calcula a energia sonora (RMS) do bloco PCM 16-bit para Voice Activity Detection (VAD)"""
    if not pcm_bytes or len(pcm_bytes) < 2:
        return 0.0
    try:
        import audioop
        return float(audioop.rms(pcm_bytes, 2))
    except:
        import math
        total = 0
        count = len(pcm_bytes) // 2
        for i in range(0, len(pcm_bytes) - 1, 2):
            sample = int.from_bytes(pcm_bytes[i:i+2], byteorder='little', signed=True)
            total += sample * sample
        return math.sqrt(total / count) if count > 0 else 0.0

class RTPAudioSession:
    def __init__(self, local_port: int, remote_ip: str, remote_port: int, codec: str = "PCMA"):
        self.local_port = local_port
        self.remote_ip = remote_ip
        self.remote_port = remote_port
        self.codec = codec
        self.payload_type = 8 if codec == "PCMA" else 0
        self.ssrc = int(time.time()) & 0xFFFFFFFF
        self.seq = 1000
        self.timestamp = 0
        self.sock: Optional[socket.socket] = None
        self.is_running = False
        self.is_transmitting = False
        self.cancel_playback = False
        
        # Callbacks de conversa
        self.on_speech_ready: Optional[Callable] = None
        self.on_barge_in: Optional[Callable] = None
        
        # VAD & Buffer de Fala do Cliente
        self.speech_buffer = bytearray()
        self.is_collecting_speech = False
        self.last_speech_time = 0.0
        self.vad_threshold = 400.0  # Nível de energia sonora para detectar voz humana
        self.silence_timeout = 0.65  # 650ms de silêncio finaliza o turno de fala
        self._rx_task: Optional[asyncio.Task] = None

    def start_socket(self):
        try:
            self.sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
            self.sock.bind(("0.0.0.0", self.local_port))
            self.sock.setblocking(False)
            self.is_running = True
            logger.info(f"Socket RTP local aberto em 0.0.0.0:{self.local_port} <-> Oktor Mídia {self.remote_ip}:{self.remote_port}")
            
            # Iniciar loop de recepção de áudio do cliente em background
            self._rx_task = asyncio.create_task(self._receive_loop())
        except Exception as e:
            logger.error(f"Erro ao abrir socket RTP na porta {self.local_port}: {e}")

    async def _receive_loop(self):
        """
        Escuta pacotes RTP do cliente, detecta fala (VAD), trata interrupções (Barge-In)
        e despacha para transcrição quando o cliente termina de falar.
        """
        logger.info(f"Escutador RTP iniciado para captura da voz do cliente...")
        loop = asyncio.get_running_loop()

        while self.is_running and self.sock:
            try:
                data = await loop.sock_recv(self.sock, 2048)
                if len(data) <= 12:
                    continue  # Apenas cabeçalho ou pacote vazio

                # Extrai payload G.711 (após os 12 bytes de header RTP RFC 3550)
                g711_payload = data[12:]
                
                # Decodifica para PCM 16-bit
                pcm_chunk = alaw_to_linear(g711_payload) if self.payload_type == 8 else ulaw_to_linear(g711_payload)
                rms = calculate_rms(pcm_chunk)

                now = time.time()

                if rms > self.vad_threshold:
                    # Cliente está falando!
                    if self.is_transmitting:
                        # BARGE-IN: Cliente interrompeu a IA!
                        logger.info("⚡ [BARGE-IN]: Cliente começou a falar! Interrompendo a voz da IA imediatamente...")
                        self.cancel_playback = True
                        if self.on_barge_in:
                            asyncio.create_task(self.on_barge_in())

                    if not self.is_collecting_speech:
                        self.is_collecting_speech = True
                        self.speech_buffer.clear()
                        logger.info("🎙️ [Cliente Falando...] Capturando áudio...")

                    self.speech_buffer.extend(pcm_chunk)
                    self.last_speech_time = now

                elif self.is_collecting_speech:
                    # Adiciona um pouco de silêncio natural no buffer
                    self.speech_buffer.extend(pcm_chunk)
                    
                    # Checa se o silêncio atingiu o tempo de finalização da fala
                    if now - self.last_speech_time > self.silence_timeout:
                        self.is_collecting_speech = False
                        logger.info(f"🤫 [Silêncio detectado]: Final de fala do cliente ({len(self.speech_buffer)} bytes PCM). Enviando para STT...")
                        if self.on_speech_ready and len(self.speech_buffer) > 3200:
                            collected_audio = bytes(self.speech_buffer)
                            asyncio.create_task(self.on_speech_ready(collected_audio))
                        self.speech_buffer.clear()

            except asyncio.CancelledError:
                break
            except Exception as e:
                if self.is_running:
                    await asyncio.sleep(0.01)

    async def stream_pcm_audio(self, pcm_bytes: bytes):
        """
        Envia áudio PCM para a Oktor em blocos de 20ms com suporte a cancelamento instantâneo (Barge-In).
        """
        if not self.sock or not self.is_running:
            self.start_socket()

        self.is_transmitting = True
        self.cancel_playback = False

        # Envia pacotes de warm-up (silêncio)
        silence_byte = b'\xd5' if self.payload_type == 8 else b'\xff'
        silence_frame = silence_byte * 160
        for _ in range(3):
            pkt = RTPPacket.build(silence_frame, self.seq, self.timestamp, self.ssrc, self.payload_type, marker=0)
            try:
                self.sock.sendto(pkt, (self.remote_ip, self.remote_port))
            except:
                pass
            self.seq = (self.seq + 1) & 0xFFFF
            self.timestamp = (self.timestamp + 160) & 0xFFFFFFFF
            await asyncio.sleep(0.02)

        # Converte PCM para G.711
        if self.payload_type == 8:
            g711_audio = linear_to_alaw(pcm_bytes)
        else:
            g711_audio = linear_to_ulaw(pcm_bytes)

        frame_size = 160  # 160 bytes = 20ms de áudio a 8kHz
        total_frames = len(g711_audio) // frame_size
        logger.info(f"Transmitindo áudio da IA via RTP ({len(g711_audio)} bytes, ~{total_frames * 20}ms de fala)...")

        for idx, i in enumerate(range(0, len(g711_audio), frame_size)):
            if not self.is_running or self.cancel_playback:
                if self.cancel_playback:
                    logger.info("🚫 Transmissão de áudio da IA cancelada por interrupção do cliente (Barge-In)")
                break
            
            chunk = g711_audio[i:i + frame_size]
            if len(chunk) < frame_size:
                chunk = chunk + (silence_byte * (frame_size - len(chunk)))

            marker = 1 if idx == 0 else 0
            packet = RTPPacket.build(chunk, self.seq, self.timestamp, self.ssrc, self.payload_type, marker=marker)
            
            try:
                self.sock.sendto(packet, (self.remote_ip, self.remote_port))
            except Exception as e:
                logger.error(f"Erro ao enviar pacote RTP: {e}")
                break

            self.seq = (self.seq + 1) & 0xFFFF
            self.timestamp = (self.timestamp + 160) & 0xFFFFFFFF
            await asyncio.sleep(0.0195)

        self.is_transmitting = False
        if not self.cancel_playback:
            logger.info("Transmissão do bloco de áudio da IA concluída com sucesso!")

    def stop(self):
        self.is_running = False
        self.is_transmitting = False
        self.cancel_playback = True
        if self._rx_task:
            self._rx_task.cancel()
            self._rx_task = None
        if self.sock:
            try:
                self.sock.close()
            except:
                pass
            self.sock = None
        logger.info("Sessão RTP encerrada.")

