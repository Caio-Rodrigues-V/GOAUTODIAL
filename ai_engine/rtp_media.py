"""
Dial GO Voice AI Engine - RTP Media Streamer and Audio Codec Bridge
Converte áudio de síntese de voz (OpenAI TTS, ElevenLabs, Cartesia) em pacotes RTP (RFC 3550) 
com codec G.711 A-law (PCMA) / Mu-law (PCMU) a 8000Hz para transmissão direta à Oktor Telecom.
Suporta mixagem de ruído ambiente em tempo real (Escritório / Call Center / Digitação / Conforto).
"""

import socket
import asyncio
import struct
import time
import io
import wave
import logging
import math
import random
import os
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
    except Exception:
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
    except Exception:
        return linear_to_alaw(pcm_bytes)

def apply_telephony_filter(pcm_bytes: bytes, sample_rate: int = 8000) -> bytes:
    """
    Filtro de Áudio Telefônico Profissional (ITU-T G.712):
    1. DC Blocker / High-Pass (~260Hz): Elimina ruído elétrico, estalos e rumble DC da linha.
    2. Low-Pass Anti-Aliasing (~3400Hz): Remove frequências agudas que causam chiado e estalos no G.711.
    3. Headroom Gain (-1.7dB / 0.82): Garante margem de pico para prevenir saturação/clipagem no codec A-law/Mu-law.
    """
    if not pcm_bytes or len(pcm_bytes) < 2:
        return pcm_bytes

    num_samples = len(pcm_bytes) // 2
    out = bytearray(len(pcm_bytes))

    prev_x = 0
    prev_y = 0.0
    alpha_hp = 0.80  # High-pass ~260Hz
    alpha_lp = 0.62  # Low-pass ~3400Hz
    lp_val = 0.0

    for i in range(num_samples):
        x = int.from_bytes(pcm_bytes[i*2:(i+1)*2], byteorder='little', signed=True)
        # DC Blocker / High-pass
        y_hp = x - prev_x + alpha_hp * prev_y
        prev_x = x
        prev_y = y_hp

        # Low-pass
        lp_val = alpha_lp * lp_val + (1.0 - alpha_lp) * y_hp

        # Headroom gain 82%
        s_out = int(lp_val * 0.82)
        s_out = max(-32767, min(32767, s_out))
        struct.pack_into('<h', out, i * 2, s_out)

    return bytes(out)

def resample_24k_to_8k_pcm(pcm_24k: bytes) -> bytes:
    """Decimação 3:1 de PCM 16-bit 24000Hz para 8000Hz com filtro telefônico"""
    if not pcm_24k:
        return b''
    out = bytearray()
    for i in range(0, len(pcm_24k) - 5, 6):
        out.extend(pcm_24k[i:i+2])
    return apply_telephony_filter(bytes(out), 8000)

def resample_16k_to_8k_pcm(pcm_16k: bytes) -> bytes:
    """
    Decimação 2:1 profissional (16kHz -> 8kHz) com filtro anti-aliasing e headroom gain de alta fidelidade.
    """
    if not pcm_16k:
        return b''

    try:
        import audioop
        resampled = audioop.ratecv(pcm_16k, 2, 1, 16000, 8000, None)[0]
        return apply_telephony_filter(resampled, 8000)
    except Exception:
        pass

    out = bytearray()
    length = len(pcm_16k)
    for i in range(0, length - 3, 4):
        s1 = int.from_bytes(pcm_16k[i:i+2], byteorder='little', signed=True)
        s2 = int.from_bytes(pcm_16k[i+2:i+4], byteorder='little', signed=True)
        s_filtered = int((s1 + s2) * 0.5)
        s_filtered = max(-32767, min(32767, s_filtered))
        out.extend(s_filtered.to_bytes(2, byteorder='little', signed=True))
    return apply_telephony_filter(bytes(out), 8000)

def resample_wav_to_8k_pcm(wav_bytes: bytes) -> bytes:
    """Decodifica WAV de qualquer taxa de amostragem para PCM 16-bit 8000Hz Mono com filtro ITU-T G.712"""
    try:
        with wave.open(io.BytesIO(wav_bytes), 'rb') as wav:
            nchannels = wav.getnchannels()
            sampwidth = wav.getsampwidth()
            framerate = wav.getframerate()
            frames = wav.readframes(wav.getnframes())

            if framerate == 24000 and sampwidth == 2:
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
                return apply_telephony_filter(frames, 8000)
            except Exception:
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
    except Exception:
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
    except Exception:
        return alaw_to_linear(ulaw_bytes)

def calculate_rms(pcm_bytes: bytes) -> float:
    """Calcula a energia sonora (RMS) do bloco PCM 16-bit para Voice Activity Detection (VAD)"""
    if not pcm_bytes or len(pcm_bytes) < 2:
        return 0.0
    try:
        import audioop
        return float(audioop.rms(pcm_bytes, 2))
    except Exception:
        total = 0.0
        count = len(pcm_bytes) // 2
        for i in range(0, len(pcm_bytes) - 1, 2):
            sample = int.from_bytes(pcm_bytes[i:i+2], byteorder='little', signed=True)
            total += sample * sample
        return math.sqrt(total / max(1, count))

def create_wav_from_pcm(pcm_bytes: bytes, sample_rate: int = 8000) -> bytes:
    """Empacota bytes PCM 16-bit Mono em um container WAV válido em memória"""
    buf = io.BytesIO()
    with wave.open(buf, 'wb') as wav:
        wav.setnchannels(1)
        wav.setsampwidth(2)
        wav.setframerate(sample_rate)
        wav.writeframes(pcm_bytes)
    return buf.getvalue()


class AmbientSoundEngine:
    """
    Gerenciador de ruídos de fundo opcionais (Escritório, Call Center, Teclado).
    Somente ativo quando configurado explicitamente com arquivo de áudio WAV de alta fidelidade.
    """
    _cache = {}

    @classmethod
    def get_ambient_pcm(cls, sound_type: str = "off", duration_sec: float = 15.0, sample_rate: int = 8000) -> bytes:
        sound_type = (sound_type or "off").lower().strip()
        if sound_type in ("off", "none", "desativado", "disabled", "false", "0", ""):
            return b""

        # Mapeamento de sinônimos
        if sound_type in ("office", "callcenter", "call_center"):
            sound_type = "office"
        elif sound_type in ("typing", "keyboard"):
            sound_type = "typing"
        elif sound_type in ("room", "comfort", "hum"):
            sound_type = "room"

        if sound_type in cls._cache:
            return cls._cache[sound_type]

        # Tenta carregar de arquivo de áudio pré-gravado limpo em sounds/
        candidate_paths = [
            os.path.join(os.path.dirname(os.path.abspath(__file__)), "sounds", f"{sound_type}.wav"),
            os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", "sounds", f"{sound_type}.wav"),
            f"/var/www/html/sounds/{sound_type}.wav"
        ]
        for p in candidate_paths:
            if os.path.exists(p):
                try:
                    with open(p, "rb") as f:
                        wav_data = f.read()
                    pcm = resample_wav_to_8k_pcm(wav_data)
                    if pcm and len(pcm) >= 1600:
                        logger.info(f"Carregado som de fundo '{sound_type}' do arquivo: {p}")
                        cls._cache[sound_type] = pcm
                        return pcm
                except Exception as ex:
                    logger.warning(f"Erro ao carregar arquivo de som {p}: {ex}")

        # Se não houver arquivo real, não gera ruído procedural para manter a linha 100% limpa e silenciosa
        return b""


class RTPAudioSession:
    def __init__(self, local_port: int, remote_ip: str, remote_port: int, codec: str = "PCMA", silence_timeout: float = 0.55, background_sound: str = "off", background_volume: float = 0.0):
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
        
        # Ruído de Fundo (Apenas se configurado com arquivo real)
        sound_key = (background_sound or "off").lower().strip()
        if sound_key in ("off", "none", "desativado", "disabled", "false", "0", ""):
            self.background_sound = "off"
            self.background_volume = 0.0
            self.ambient_pcm = b""
        else:
            self.background_sound = sound_key
            self.background_volume = max(0.0, min(1.0, float(background_volume or 0.0)))
            self.ambient_pcm = AmbientSoundEngine.get_ambient_pcm(self.background_sound) if self.background_volume > 0.0 else b""
            
        self.ambient_pos = 0
        self._ambient_task: Optional[asyncio.Task] = None
        
        # Callbacks de conversa
        self.on_speech_ready = None
        self.on_barge_in = None
        
        # Buffer de Gravação Geral da Chamada (IA + Cliente)
        self.full_recorded_pcm = bytearray()
        
        # VAD & Supressão de Eco Avançada
        self.created_at = time.time()
        self.speech_buffer = bytearray()
        self.pre_speech_ring_buffer = bytearray()  # Pre-buffer circular de 200ms para nunca cortar o início da fala ("Alô", "Sim")
        self.is_collecting_speech = False
        self.last_speech_time = 0.0
        self.last_transmit_end_time = 0.0
        self.vad_threshold = 850.0  # Threshold calibrado anti-chiado (850 RMS)
        self.vad_consecutive_hits = 0  # Confirmação de 2 frames para evitar falsos positivos por estalos
        self.min_speech_bytes = 2400   # ~150ms de áudio real (permite palavras rápidas como "Alô", "Oi", "Sim")
        self.silence_timeout = max(0.35, min(3.0, float(silence_timeout or 0.55)))  # Tempo de silêncio para encerramento de turno
        self._rx_task: Optional[asyncio.Task] = None

    def start_socket(self):
        try:
            self.sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
            self.sock.bind(("0.0.0.0", self.local_port))
            self.sock.setblocking(False)
            self.is_running = True
            logger.info(f"Socket RTP local aberto em 0.0.0.0:{self.local_port} <-> Oktor Mídia {self.remote_ip}:{self.remote_port} (Fundo: '{self.background_sound}', Vol: {int(self.background_volume*100)}%)")
            
            # Iniciar loop de recepção de áudio do cliente em background
            self._rx_task = asyncio.create_task(self._receive_loop())
            
            # Iniciar streaming contínuo de ruído ambiente somente se houver áudio real
            if self.ambient_pcm and len(self.ambient_pcm) > 0 and self.background_volume > 0.0:
                self._ambient_task = asyncio.create_task(self._ambient_loop())
        except Exception as e:
            logger.error(f"Erro ao abrir socket RTP na porta {self.local_port}: {e}")

    async def _ambient_loop(self):
        """Transmite ruído ambiente suave caso tenha sido configurado um arquivo WAV válido"""
        if not self.ambient_pcm or self.background_volume <= 0.0:
            return

        frame_samples = 160
        frame_bytes = frame_samples * 2
        amb_len = len(self.ambient_pcm)

        while self.is_running and self.sock:
            try:
                if not self.is_transmitting:
                    chunk_pcm = bytearray(frame_bytes)
                    for i in range(0, frame_bytes, 2):
                        pos = (self.ambient_pos + i) % amb_len
                        s = int.from_bytes(self.ambient_pcm[pos:pos+2], byteorder='little', signed=True)
                        s_vol = int(s * self.background_volume)
                        s_vol = max(-32768, min(32767, s_vol))
                        chunk_pcm[i:i+2] = s_vol.to_bytes(2, byteorder='little', signed=True)

                    self.ambient_pos = (self.ambient_pos + frame_bytes) % amb_len
                    g711_chunk = linear_to_alaw(bytes(chunk_pcm)) if self.payload_type == 8 else linear_to_ulaw(bytes(chunk_pcm))
                    packet = RTPPacket.build(g711_chunk, self.seq, self.timestamp, self.ssrc, self.payload_type, marker=0)
                    try:
                        self.sock.sendto(packet, (self.remote_ip, self.remote_port))
                    except Exception:
                        pass

                    self.seq = (self.seq + 1) & 0xFFFF
                    self.timestamp = (self.timestamp + frame_samples) & 0xFFFFFFFF

                await asyncio.sleep(0.020)
            except asyncio.CancelledError:
                break
            except Exception:
                await asyncio.sleep(0.020)

    async def _receive_loop(self):
        """
        Escuta pacotes RTP do cliente com cancelamento de eco acústico (AEC),
        pre-buffering contínuo e detecção rápida e precisa da voz do usuário.
        """
        logger.info("Escutador RTP com supressão de eco e pre-buffering ativado.")
        loop = asyncio.get_running_loop()
        barge_in_hits = 0

        while self.is_running and self.sock:
            try:
                data = await loop.sock_recv(self.sock, 2048)
                if len(data) <= 12:
                    continue

                now = time.time()

                # Ignora estalos de sinalização nos primeiros 400ms da chamada
                if now - self.created_at < 0.40:
                    continue

                # Extrai payload G.711 e converte para Linear PCM 16-bit
                g711_payload = data[12:]
                pcm_chunk = alaw_to_linear(g711_payload) if self.payload_type == 8 else ulaw_to_linear(g711_payload)
                rms = calculate_rms(pcm_chunk)

                # Mantém sempre o buffer circular dos últimos 200ms atualizado (3200 bytes)
                self.pre_speech_ring_buffer.extend(pcm_chunk)
                if len(self.pre_speech_ring_buffer) > 3200:
                    self.pre_speech_ring_buffer = self.pre_speech_ring_buffer[-3200:]

                # 1. Detecção de Interrupção / Barge-in enquanto a IA fala
                if self.is_transmitting:
                    if rms > 1500.0:  # Usuário falou por cima da IA com voz firme
                        barge_in_hits += 1
                        if barge_in_hits >= 3:  # ~60ms sustentados
                            logger.info("🎙️ [Barge-in detectado]: Usuário começou a falar. Interrompendo fala da IA...")
                            self.cancel_playback = True
                            if self.on_barge_in:
                                self.on_barge_in()
                    else:
                        barge_in_hits = 0
                    continue
                else:
                    barge_in_hits = 0

                # 2. Supressão de Eco Residual pós-transmissão (Janela de 150ms)
                if now - self.last_transmit_end_time < 0.15:
                    continue

                # 3. Classificação VAD de Fala
                if rms > self.vad_threshold:
                    self.vad_consecutive_hits += 1
                    if self.vad_consecutive_hits >= 2:  # Confirmação de 2 frames (>40ms)
                        if not self.is_collecting_speech:
                            self.is_collecting_speech = True
                            # Recupera o início da palavra ("Alô", "Sim") do pre-buffer
                            self.speech_buffer = bytearray(self.pre_speech_ring_buffer)
                            logger.info("🎙️ [Cliente Falando...] Capturando áudio com pre-buffer...")

                        self.speech_buffer.extend(pcm_chunk)
                        self.full_recorded_pcm.extend(pcm_chunk)
                        self.last_speech_time = now
                else:
                    self.vad_consecutive_hits = 0
                    if self.is_collecting_speech:
                        self.speech_buffer.extend(pcm_chunk)
                        self.full_recorded_pcm.extend(pcm_chunk)

                        # Detecção de fim de fala (silêncio atingiu timeout)
                        if now - self.last_speech_time > self.silence_timeout:
                            self.is_collecting_speech = False
                            audio_len = len(self.speech_buffer)
                            logger.info(f"🤫 [Silêncio detectado]: Fim de fala ({audio_len} bytes PCM).")
                            
                            # Dispara callback se o áudio capturado tiver pelo menos 150ms
                            if self.on_speech_ready and audio_len >= self.min_speech_bytes:
                                collected = bytes(self.speech_buffer)
                                asyncio.create_task(self.on_speech_ready(collected))
                            
                            self.speech_buffer.clear()

            except asyncio.CancelledError:
                break
            except Exception:
                if self.is_running:
                    await asyncio.sleep(0.01)

    async def stream_pcm_audio(self, pcm_bytes: bytes):
        """
        Envia áudio PCM para a Oktor com timer de alta precisão monotonic e filtro telefônico ITU-T G.712.
        """
        if not self.sock or not self.is_running:
            self.start_socket()

        self.is_transmitting = True
        self.cancel_playback = False

        if not pcm_bytes:
            self.is_transmitting = False
            return

        # Aplica filtro telefônico passa-faixa anti-chiado e headroom gain
        filtered_audio = apply_telephony_filter(pcm_bytes, 8000)

        # Grava áudio falado pela IA no master recording
        self.full_recorded_pcm.extend(filtered_audio)

        # Converte PCM filtrado para G.711 A-law ou Mu-law
        if self.payload_type == 8:
            g711_audio = linear_to_alaw(filtered_audio)
        else:
            g711_audio = linear_to_ulaw(filtered_audio)

        frame_size = 160  # 160 bytes = 20ms de áudio a 8kHz
        total_frames = len(g711_audio) // frame_size
        silence_byte = b'\xd5' if self.payload_type == 8 else b'\xff'
        logger.info(f"Transmitindo áudio cristalino da IA via RTP ({len(g711_audio)} bytes, ~{total_frames * 20}ms)...")

        start_time = time.perf_counter()

        for idx, i in enumerate(range(0, len(g711_audio), frame_size)):
            if not self.is_running or self.cancel_playback:
                logger.info("Transmissão de áudio interrompida.")
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

            # Timer de alta precisão (Monotonic clock)
            target_next = start_time + ((idx + 1) * 0.020)
            delay = target_next - time.perf_counter()
            if delay > 0.001:
                await asyncio.sleep(delay)

        self.is_transmitting = False
        self.last_transmit_end_time = time.time()
        logger.info("Transmissão do bloco de áudio da IA concluída.")

    def get_recorded_wav(self) -> bytes:
        """Retorna o áudio completo gravado da conversa em formato WAV 8000Hz 16-bit Mono"""
        if not self.full_recorded_pcm or len(self.full_recorded_pcm) < 1600:
            return b''
        return create_wav_from_pcm(bytes(self.full_recorded_pcm), 8000)

    def stop(self):
        self.is_running = False
        self.is_transmitting = False
        self.cancel_playback = True
        if self._ambient_task:
            self._ambient_task.cancel()
            self._ambient_task = None
        if self._rx_task:
            self._rx_task.cancel()
            self._rx_task = None
        if self.sock:
            try:
                self.sock.close()
            except Exception:
                pass
            self.sock = None
        logger.info("Sessão RTP encerrada.")

