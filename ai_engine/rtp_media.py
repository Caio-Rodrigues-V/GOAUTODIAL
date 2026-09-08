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
import httpx
from typing import Optional, Callable

logger = logging.getLogger("DialGO_RTP")

def linear_to_alaw(pcm_bytes: bytes) -> bytes:
    """Converte PCM 16-bit Little Endian (8000Hz) para G.711 A-law (8-bit)"""
    try:
        import audioop
        return audioop.lin2alaw(pcm_bytes, 2)
    except:
        out = bytearray()
        for i in range(0, len(pcm_bytes) - 1, 2):
            sample = int.from_bytes(pcm_bytes[i:i+2], byteorder='little', signed=True)
            mask = 0x55
            sign = 0x80 if sample >= 0 else 0x00
            if sample < 0:
                sample = -sample
            if sample > 32767:
                sample = 32767
            if sample >= 256:
                exponent = 7
                for exp in range(1, 8):
                    if sample < (256 << exp):
                        exponent = exp
                        break
                mantissa = (sample >> (exponent + 3)) & 0x0F
                byte_val = sign | (exponent << 4) | mantissa
            else:
                byte_val = sign | (sample >> 4)
            out.append(byte_val ^ mask)
        return bytes(out)

def linear_to_ulaw(pcm_bytes: bytes) -> bytes:
    """Converte PCM 16-bit Little Endian (8000Hz) para G.711 Mu-law (8-bit)"""
    try:
        import audioop
        return audioop.lin2ulaw(pcm_bytes, 2)
    except:
        return linear_to_alaw(pcm_bytes)

def resample_wav_to_8k_pcm(wav_bytes: bytes) -> bytes:
    """Decodifica WAV de qualquer taxa de amostragem para PCM 16-bit 8000Hz Mono"""
    try:
        with wave.open(io.BytesIO(wav_bytes), 'rb') as wav:
            nchannels = wav.getnchannels()
            sampwidth = wav.getsampwidth()
            framerate = wav.getframerate()
            frames = wav.readframes(wav.getnframes())

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
                return frames
    except Exception as e:
        logger.error(f"Erro ao processar áudio WAV: {e}")
        return b''

class RTPPacket:
    @staticmethod
    def build(payload: bytes, seq: int, timestamp: int, ssrc: int, payload_type: int = 8) -> bytes:
        """
        Cria cabeçalho RTP RFC 3550 de 12 bytes + payload de áudio.
        Payload Type 8 = PCMA (G.711 A-law, padrão Brasil/Europa)
        Payload Type 0 = PCMU (G.711 Mu-law)
        """
        version = 2
        padding = 0
        extension = 0
        csrc_count = 0
        marker = 0
        
        byte0 = (version << 6) | (padding << 5) | (extension << 4) | csrc_count
        byte1 = (marker << 7) | (payload_type & 0x7F)
        
        header = struct.pack('!BBHII', byte0, byte1, seq & 0xFFFF, timestamp & 0xFFFFFFFF, ssrc & 0xFFFFFFFF)
        return header + payload

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

    def start_socket(self):
        try:
            self.sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
            self.sock.bind(("0.0.0.0", self.local_port))
            self.sock.setblocking(False)
            self.is_running = True
            logger.info(f"Socket RTP local aberto em 0.0.0.0:{self.local_port} -> Oktor Mídia {self.remote_ip}:{self.remote_port}")
        except Exception as e:
            logger.error(f"Erro ao abrir socket RTP na porta {self.local_port}: {e}")

    async def stream_pcm_audio(self, pcm_bytes: bytes):
        """
        Envia áudio PCM para a Oktor em blocos de 20 milissegundos (160 samples = 160 bytes em G.711).
        """
        if not self.sock or not self.is_running:
            self.start_socket()

        if self.payload_type == 8:
            g711_audio = linear_to_alaw(pcm_bytes)
        else:
            g711_audio = linear_to_ulaw(pcm_bytes)

        frame_size = 160  # 160 bytes = 20ms de áudio a 8kHz
        total_frames = len(g711_audio) // frame_size
        logger.info(f"Transmitindo áudio da IA via RTP ({len(g711_audio)} bytes, ~{total_frames * 20}ms de fala)...")

        for i in range(0, len(g711_audio), frame_size):
            if not self.is_running:
                break
            
            chunk = g711_audio[i:i + frame_size]
            if len(chunk) < frame_size:
                chunk = chunk + (b'\xd5' * (frame_size - len(chunk)))

            packet = RTPPacket.build(chunk, self.seq, self.timestamp, self.ssrc, self.payload_type)
            
            try:
                self.sock.sendto(packet, (self.remote_ip, self.remote_port))
            except Exception as e:
                logger.error(f"Erro ao enviar pacote RTP: {e}")
                break

            self.seq = (self.seq + 1) & 0xFFFF
            self.timestamp = (self.timestamp + 160) & 0xFFFFFFFF
            await asyncio.sleep(0.0195)

        logger.info("Transmissão do bloco de áudio da IA concluída!")

    def stop(self):
        self.is_running = False
        if self.sock:
            try:
                self.sock.close()
            except:
                pass
            self.sock = None
        logger.info("Sessão RTP encerrada.")
