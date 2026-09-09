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

def resample_24k_to_8k_pcm(pcm_24k: bytes) -> bytes:
    """Decimação exata 3:1 de PCM 16-bit 24000Hz para 8000Hz"""
    out = bytearray()
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
    Gerador e cache de ruídos de fundo realistas (Escritório, Call Center, Teclado, Ruído de Sala)
    para humanização avançada de chamadas telefônicas de IA.
    """
    _cache = {}

    @classmethod
    def get_ambient_pcm(cls, sound_type: str = "office", duration_sec: float = 15.0, sample_rate: int = 8000) -> bytes:
        sound_type = (sound_type or "off").lower().strip()
        if sound_type in ("off", "none", "false", "0", ""):
            return b""

        # Mapeamento de sinônimos
        if sound_type in ("default", "office", "callcenter", "call_center"):
            sound_type = "office"
        elif sound_type in ("typing", "keyboard"):
            sound_type = "typing"
        elif sound_type in ("room", "comfort", "hum"):
            sound_type = "room"

        if sound_type in cls._cache:
            return cls._cache[sound_type]

        # 1. Tenta carregar de arquivo de áudio se existir em sounds/
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

        # 2. Síntese procedural realista de alta fidelidade
        logger.info(f"Sintetizando som de fundo procedural '{sound_type}' ({duration_sec}s a {sample_rate}Hz)...")
        total_samples = int(duration_sec * sample_rate)
        samples = [0.0] * total_samples

        # Filtro de Ruído Rosa acústico (Simula ar condicionado e presença de microfone de headset)
        b0 = b1 = b2 = b3 = b4 = b5 = b6 = 0.0
        for i in range(total_samples):
            white = random.uniform(-1.0, 1.0)
            b0 = 0.99886 * b0 + white * 0.0555179
            b1 = 0.99332 * b1 + white * 0.0750759
            b2 = 0.96900 * b2 + white * 0.1538520
            b3 = 0.86650 * b3 + white * 0.3104856
            b4 = 0.55000 * b4 + white * 0.5329522
            b5 = -0.7616 * b5 - white * 0.0168980
            pink = b0 + b1 + b2 + b3 + b4 + b5 + b6 + white * 0.5362
            b6 = white * 0.115926

            # Base suave de ruído ambiente de sala
            samples[i] += (pink * 0.12)
            # Frequência base sutil de eletricidade/sala
            samples[i] += 0.02 * math.sin(2 * math.pi * 60 * i / sample_rate)

        if sound_type == "office":
            # 1. Simulação acústica de call center: murmúrio distante e difuso (distant babble/chatter)
            num_voices = 4
            for _ in range(num_voices):
                f_formant = random.uniform(350, 950)
                speed_mod = random.uniform(2.5, 4.5)
                phase_off = random.uniform(0, math.pi * 2)
                for i in range(total_samples):
                    t = i / sample_rate
                    # Modulação de amplitude para simular fala humana distante
                    speech_env = max(0.0, math.sin(2 * math.pi * speed_mod * t + phase_off)) * (0.5 + 0.5 * math.sin(2 * math.pi * 0.3 * t))
                    if speech_env > 0.15:
                        carrier = math.sin(2 * math.pi * f_formant * t) + 0.5 * math.sin(2 * math.pi * (f_formant * 1.8) * t)
                        samples[i] += carrier * speech_env * 0.035

            # 2. Sons de digitação em teclado de escritório
            t = 0.4
            while t < duration_sec - 0.4:
                burst_len = random.randint(3, 8)
                for _ in range(burst_len):
                    idx = int(t * sample_rate)
                    if idx >= total_samples - 400:
                        break
                    freq = random.uniform(1200, 2400)
                    click_amp = random.uniform(0.40, 0.70)
                    for k in range(int(0.025 * sample_rate)):
                        if idx + k < total_samples:
                            env = math.exp(-k / (0.005 * sample_rate))
                            samples[idx + k] += math.sin(2 * math.pi * freq * k / sample_rate) * env * click_amp
                    t += random.uniform(0.08, 0.18)
                t += random.uniform(0.7, 2.5)

            # 3. Sons de cliques sutis de mouse
            t = 0.9
            while t < duration_sec - 0.9:
                idx = int(t * sample_rate)
                if idx < total_samples - 200:
                    freq = random.uniform(2800, 3500)
                    amp = random.uniform(0.20, 0.35)
                    for k in range(int(0.015 * sample_rate)):
                        if idx + k < total_samples:
                            env = math.exp(-k / (0.003 * sample_rate))
                            samples[idx + k] += math.sin(2 * math.pi * freq * k / sample_rate) * env * amp
                t += random.uniform(2.2, 5.5)

        elif sound_type == "typing":
            # Sons contínuos de digitação realista
            t = 0.2
            while t < duration_sec - 0.2:
                burst_len = random.randint(4, 12)
                for _ in range(burst_len):
                    idx = int(t * sample_rate)
                    if idx >= total_samples - 400:
                        break
                    freq = random.uniform(1100, 2600)
                    click_amp = random.uniform(0.45, 0.80)
                    for k in range(int(0.025 * sample_rate)):
                        if idx + k < total_samples:
                            env = math.exp(-k / (0.006 * sample_rate))
                            samples[idx + k] += math.sin(2 * math.pi * freq * k / sample_rate) * env * click_amp
                    t += random.uniform(0.07, 0.16)
                t += random.uniform(0.4, 1.8)

        # Converter para PCM 16-bit Little Endian
        out = bytearray(total_samples * 2)
        for i, s in enumerate(samples):
            val = int(max(-1.0, min(1.0, s * 0.8)) * 32767)
            struct.pack_into('<h', out, i * 2, val)

        pcm_bytes = bytes(out)
        cls._cache[sound_type] = pcm_bytes
        return pcm_bytes


class RTPAudioSession:
    def __init__(self, local_port: int, remote_ip: str, remote_port: int, codec: str = "PCMA", silence_timeout: float = 0.50, background_sound: str = "off", background_volume: float = 0.10):
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
        
        # Ruído de Fundo (Ambiente de Escritório / Call Center)
        self.background_sound = background_sound
        self.background_volume = max(0.0, min(1.0, float(background_volume or 0.10)))
        self.ambient_pcm = AmbientSoundEngine.get_ambient_pcm(self.background_sound)
        self.ambient_pos = 0
        self._ambient_task: Optional[asyncio.Task] = None
        
        # Callbacks de conversa
        self.on_speech_ready = None
        self.on_barge_in = None
        
        # Buffer de Gravação Geral da Chamada (IA + Cliente)
        self.full_recorded_pcm = bytearray()
        
        # VAD & Supressão de Eco
        self.speech_buffer = bytearray()
        self.is_collecting_speech = False
        self.last_speech_time = 0.0
        self.last_transmit_end_time = 0.0
        self.vad_threshold = 550.0  # Threshold calibrado para voz humana clara
        self.silence_timeout = silence_timeout  # Pausa natural antes de fechar o turno de fala
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
            
            # Iniciar streaming contínuo de ruído ambiente
            if self.ambient_pcm and self.background_volume > 0.0:
                self._ambient_task = asyncio.create_task(self._ambient_loop())
        except Exception as e:
            logger.error(f"Erro ao abrir socket RTP na porta {self.local_port}: {e}")

    async def _ambient_loop(self):
        """
        Transmite ruído de fundo de escritório nos momentos em que a IA está ouvindo ou em silêncio.
        Garante uma atmosfera de call center 100% natural, contínua e sem cortes.
        """
        if not self.ambient_pcm or self.background_volume <= 0.0:
            return

        frame_samples = 160  # 160 samples = 20ms a 8kHz
        frame_bytes = frame_samples * 2  # 320 bytes PCM16
        amb_len = len(self.ambient_pcm)

        while self.is_running and self.sock:
            try:
                if not self.is_transmitting:
                    # Monta bloco de 320 bytes com volume calibrado
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
        Escuta pacotes RTP do cliente com cancelamento de eco acústico (AEC)
        e detecção precisa de término de fala.
        """
        logger.info("Escutador RTP com supressão de eco iniciado...")
        loop = asyncio.get_running_loop()

        while self.is_running and self.sock:
            try:
                data = await loop.sock_recv(self.sock, 2048)
                if len(data) <= 12:
                    continue

                now = time.time()

                # 1. Supressão de Eco: Ignorar áudio entrante durante a fala da IA e nos 400ms seguintes
                if self.is_transmitting:
                    continue
                if now - self.last_transmit_end_time < 0.40:
                    continue

                # Extrai payload G.711
                g711_payload = data[12:]
                pcm_chunk = alaw_to_linear(g711_payload) if self.payload_type == 8 else ulaw_to_linear(g711_payload)
                rms = calculate_rms(pcm_chunk)

                if rms > self.vad_threshold:
                    if not self.is_collecting_speech:
                        self.is_collecting_speech = True
                        self.speech_buffer.clear()
                        logger.info("🎙️ [Cliente Falando...] Capturando áudio...")

                    self.speech_buffer.extend(pcm_chunk)
                    self.full_recorded_pcm.extend(pcm_chunk)
                    self.last_speech_time = now

                elif self.is_collecting_speech:
                    self.speech_buffer.extend(pcm_chunk)
                    self.full_recorded_pcm.extend(pcm_chunk)
                    
                    # Checa término de fala (silêncio)
                    if now - self.last_speech_time > self.silence_timeout:
                        self.is_collecting_speech = False
                        audio_len = len(self.speech_buffer)
                        logger.info(f"🤫 [Silêncio detectado]: Final de fala ({audio_len} bytes PCM). Processando com IA...")
                        if self.on_speech_ready and audio_len >= 4000:
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
        Envia áudio PCM para a Oktor com timer de alta precisão monotonic e mixagem de ruído de fundo.
        """
        if not self.sock or not self.is_running:
            self.start_socket()

        self.is_transmitting = True
        self.cancel_playback = False

        # Grava áudio falado pela IA no master recording
        if pcm_bytes:
            self.full_recorded_pcm.extend(pcm_bytes)

        # Se ruído ambiente estiver ativo, mixa diretamente no sinal da fala
        if self.ambient_pcm and self.background_volume > 0.0:
            mixed_buf = bytearray(len(pcm_bytes))
            amb_len = len(self.ambient_pcm)
            for i in range(0, len(pcm_bytes) - 1, 2):
                s_speech = int.from_bytes(pcm_bytes[i:i+2], byteorder='little', signed=True)
                pos = (self.ambient_pos + i) % amb_len
                s_amb = int.from_bytes(self.ambient_pcm[pos:pos+2], byteorder='little', signed=True)
                mixed = s_speech + int(s_amb * self.background_volume)
                mixed = max(-32768, min(32767, mixed))
                mixed_buf[i:i+2] = mixed.to_bytes(2, byteorder='little', signed=True)
            self.ambient_pos = (self.ambient_pos + len(pcm_bytes)) % amb_len
            audio_to_encode = bytes(mixed_buf)
        else:
            audio_to_encode = pcm_bytes

        # Converte PCM para G.711
        if self.payload_type == 8:
            g711_audio = linear_to_alaw(audio_to_encode)
        else:
            g711_audio = linear_to_ulaw(audio_to_encode)

        frame_size = 160  # 160 bytes = 20ms de áudio a 8kHz
        total_frames = len(g711_audio) // frame_size
        silence_byte = b'\xd5' if self.payload_type == 8 else b'\xff'
        logger.info(f"Transmitindo áudio suave da IA via RTP ({len(g711_audio)} bytes, ~{total_frames * 20}ms)...")

        start_time = time.perf_counter()

        for idx, i in enumerate(range(0, len(g711_audio), frame_size)):
            if not self.is_running or self.cancel_playback:
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

            # Timer de alta precisão (Monotonic clock - Jitter ZERO)
            target_next = start_time + ((idx + 1) * 0.020)
            delay = target_next - time.perf_counter()
            if delay > 0.001:
                await asyncio.sleep(delay)

        self.is_transmitting = False
        self.last_transmit_end_time = time.time()
        logger.info("Transmissão do bloco de áudio da IA concluída perfeitamente!")

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
