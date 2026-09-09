"""
Dial GO Voice AI Engine - AI Synthesis, Transcription & LLM Brain
Síntese de voz em tempo real (OpenAI, ElevenLabs, Cartesia), Transcrição ultrarrápida (Deepgram Nova-2, Groq Whisper, OpenAI Whisper)
e raciocínio conversacional de alta fidelidade (Groq Llama 3.3 70B, OpenAI GPT-4o Mini, Claude, DeepSeek).
"""

import httpx
import logging
import json
import io
import wave
from typing import Dict, Any, List, Optional
from rtp_media import resample_wav_to_8k_pcm

logger = logging.getLogger("DialGO_Brain")

def create_wav_from_pcm(pcm_bytes: bytes, sample_rate: int = 8000) -> bytes:
    """Empacota bytes PCM 16-bit Mono em um container WAV válido em memória"""
    buf = io.BytesIO()
    with wave.open(buf, 'wb') as wav:
        wav.setnchannels(1)
        wav.setsampwidth(2)
        wav.setframerate(sample_rate)
        wav.writeframes(pcm_bytes)
    return buf.getvalue()

class AIVoiceBrain:
    @staticmethod
    async def transcribe(pcm_bytes: bytes, stt_provider: str, api_keys: Dict[str, str]) -> str:
        """
        Transcreve o áudio do cliente em texto em < 150ms usando Deepgram, Groq ou OpenAI.
        """
        if not pcm_bytes or len(pcm_bytes) < 3200:  # Menos de 200ms de áudio é ruído
            return ""

        stt_provider = (stt_provider or "deepgram").lower()
        wav_data = create_wav_from_pcm(pcm_bytes, 8000)

        async with httpx.AsyncClient(timeout=6.0) as client:
            try:
                # 1. Deepgram Nova-2 (Ultra-rápido ~100ms, especialista em telefonia PT-BR)
                if (stt_provider == "deepgram" or not stt_provider) and api_keys.get("deepgram_api_key"):
                    api_key = api_keys.get("deepgram_api_key")
                    url = "https://api.deepgram.com/v1/listen?model=nova-2&language=pt-BR&smart_format=true&encoding=linear16&sample_rate=8000"
                    headers = {
                        "Authorization": f"Token {api_key}",
                        "Content-Type": "audio/wav"
                    }
                    r = await client.post(url, content=wav_data, headers=headers)
                    if r.status_code == 200:
                        data = r.json()
                        transcript = data["results"]["channels"][0]["alternatives"][0]["transcript"].strip()
                        if transcript:
                            logger.info(f"[Deepgram STT]: '{transcript}'")
                            return transcript

                # 2. Groq Whisper Large v3 Turbo (Latência ~120ms)
                if api_keys.get("groq_api_key"):
                    api_key = api_keys.get("groq_api_key")
                    files = {
                        "file": ("audio.wav", wav_data, "audio/wav")
                    }
                    data = {
                        "model": "whisper-large-v3-turbo",
                        "language": "pt",
                        "response_format": "json"
                    }
                    headers = {
                        "Authorization": f"Bearer {api_key}"
                    }
                    r = await client.post("https://api.groq.com/openai/v1/audio/transcriptions", files=files, data=data, headers=headers)
                    if r.status_code == 200:
                        res = r.json()
                        transcript = res.get("text", "").strip()
                        if transcript:
                            logger.info(f"[Groq Whisper STT]: '{transcript}'")
                            return transcript

                # 3. OpenAI Whisper
                if api_keys.get("openai_api_key"):
                    api_key = api_keys.get("openai_api_key")
                    files = {
                        "file": ("audio.wav", wav_data, "audio/wav")
                    }
                    data = {
                        "model": "whisper-1",
                        "language": "pt"
                    }
                    headers = {
                        "Authorization": f"Bearer {api_key}"
                    }
                    r = await client.post("https://api.openai.com/v1/audio/transcriptions", files=files, data=data, headers=headers)
                    if r.status_code == 200:
                        res = r.json()
                        transcript = res.get("text", "").strip()
                        if transcript:
                            logger.info(f"[OpenAI Whisper STT]: '{transcript}'")
                            return transcript

            except Exception as e:
                logger.error(f"Erro no reconhecimento de fala (STT): {e}")

        return ""

    @staticmethod
    async def synthesize(text: str, voice_provider: str, voice_id: str, api_keys: Dict[str, str]) -> bytes:
        """
        Sintetiza texto em áudio PCM 16-bit 8000Hz Mono para streaming de telefonia.
        """
        voice_provider = (voice_provider or "openai").lower()
        logger.info(f"Sintetizando voz: Provedor={voice_provider}, Voz={voice_id}, Texto='{text[:60]}...'")

        async with httpx.AsyncClient(timeout=15.0) as client:
            try:
                # 1. OpenAI TTS
                if voice_provider == "openai" or not voice_provider:
                    api_key = api_keys.get("openai_api_key", "")
                    if not api_key:
                        logger.error("Chave da OpenAI não configurada para TTS")
                        return b''

                    voice = voice_id if voice_id in ["alloy", "echo", "fable", "onyx", "nova", "shimmer"] else "nova"
                    payload = {
                        "model": "tts-1",
                        "voice": voice,
                        "input": text,
                        "response_format": "wav"
                    }
                    headers = {
                        "Authorization": f"Bearer {api_key}",
                        "Content-Type": "application/json"
                    }
                    r = await client.post("https://api.openai.com/v1/audio/speech", json=payload, headers=headers)
                    if r.status_code == 200:
                        return resample_wav_to_8k_pcm(r.content)
                    else:
                        logger.error(f"Erro OpenAI TTS: {r.status_code} - {r.text}")

                # 2. ElevenLabs TTS
                elif voice_provider == "elevenlabs":
                    api_key = api_keys.get("elevenlabs_api_key", "")
                    if not api_key:
                        logger.error("Chave da ElevenLabs não configurada")
                        return b''

                    eleven_voice = voice_id if voice_id and voice_id != "custom" else "21m00Tcm4TlvDq8ikWAM"
                    url = f"https://api.elevenlabs.io/v1/text-to-speech/{eleven_voice}?output_format=pcm_8000"
                    payload = {
                        "text": text,
                        "model_id": "eleven_turbo_v2_5",
                        "voice_settings": {
                            "stability": 0.5,
                            "similarity_boost": 0.8
                        }
                    }
                    headers = {
                        "xi-api-key": api_key,
                        "Content-Type": "application/json"
                    }
                    r = await client.post(url, json=payload, headers=headers)
                    if r.status_code == 200:
                        return r.content
                    else:
                        logger.error(f"Erro ElevenLabs TTS: {r.status_code} - {r.text}")

                # 3. Cartesia Sonic
                elif voice_provider == "cartesia":
                    api_key = api_keys.get("cartesia_api_key", "")
                    if not api_key:
                        logger.error("Chave da Cartesia não configurada")
                        return b''

                    cartesia_voice = voice_id if voice_id and voice_id != "custom" else "a0e99841-438c-4a64-b679-ae501e7d6091"
                    payload = {
                        "model_id": "sonic-multilingual",
                        "transcript": text,
                        "voice": {
                            "mode": "id",
                            "id": cartesia_voice
                        },
                        "output_format": {
                            "container": "raw",
                            "encoding": "pcm_s16le",
                            "sample_rate": 8000
                        },
                        "language": "pt"
                    }
                    headers = {
                        "X-API-Key": api_key,
                        "Cartesia-Version": "2024-06-10",
                        "Content-Type": "application/json"
                    }
                    r = await client.post("https://api.cartesia.ai/tts/bytes", json=payload, headers=headers)
                    if r.status_code == 200:
                        return r.content
                    else:
                        logger.error(f"Erro Cartesia TTS: {r.status_code} - {r.text}")

            except Exception as e:
                logger.error(f"Exceção durante síntese de áudio: {e}")

        return b''

    @staticmethod
    async def chat_completion(messages: List[Dict[str, str]], llm_provider: str, llm_model: str, temperature: float, api_keys: Dict[str, str]) -> str:
        """
        Executa completion da LLM com altíssima velocidade (< 300ms) e suporte a modelos de raciocínio (o1, o3-mini).
        """
        llm_provider = (llm_provider or "groq").lower()
        
        async with httpx.AsyncClient(timeout=10.0) as client:
            try:
                # 1. Groq (Llama 3.3 70B Versatile, Llama 3.1 8B Instant)
                if llm_provider == "groq" and api_keys.get("groq_api_key"):
                    api_key = api_keys.get("groq_api_key")
                    model = llm_model if llm_model and llm_model != "custom" else "llama-3.3-70b-versatile"
                    payload = {
                        "model": model,
                        "messages": messages,
                        "temperature": float(temperature) if temperature is not None else 0.7,
                        "max_tokens": 150
                    }
                    headers = {
                        "Authorization": f"Bearer {api_key}",
                        "Content-Type": "application/json"
                    }
                    r = await client.post("https://api.groq.com/openai/v1/chat/completions", json=payload, headers=headers)
                    if r.status_code == 200:
                        data = r.json()
                        return data["choices"][0]["message"]["content"].strip()

                # 2. DeepSeek (DeepSeek V3 / R1)
                elif llm_provider == "deepseek" and api_keys.get("deepseek_api_key"):
                    api_key = api_keys.get("deepseek_api_key")
                    model = llm_model if llm_model and llm_model != "custom" else "deepseek-chat"
                    payload = {
                        "model": model,
                        "messages": messages,
                        "temperature": float(temperature) if temperature is not None else 0.7,
                        "max_tokens": 150
                    }
                    headers = {
                        "Authorization": f"Bearer {api_key}",
                        "Content-Type": "application/json"
                    }
                    r = await client.post("https://api.deepseek.com/chat/completions", json=payload, headers=headers)
                    if r.status_code == 200:
                        data = r.json()
                        return data["choices"][0]["message"]["content"].strip()

                # 3. OpenAI (GPT-4o, GPT-4o Mini, o3-mini, o1, o1-mini, GPT-4 Turbo, GPT-3.5 Turbo)
                if api_keys.get("openai_api_key"):
                    api_key = api_keys.get("openai_api_key")
                    model = llm_model if llm_model and llm_model != "custom" else "gpt-4o-mini"
                    is_reasoning = any(model.startswith(prefix) for prefix in ["o1", "o3"])

                    payload = {
                        "model": model,
                        "messages": messages
                    }
                    
                    if is_reasoning:
                        # Modelos de raciocínio da OpenAI não aceitam o parâmetro 'temperature'
                        payload["max_completion_tokens"] = 250
                    else:
                        payload["temperature"] = float(temperature) if temperature is not None else 0.7
                        payload["max_tokens"] = 150

                    headers = {
                        "Authorization": f"Bearer {api_key}",
                        "Content-Type": "application/json"
                    }
                    r = await client.post("https://api.openai.com/v1/chat/completions", json=payload, headers=headers)
                    if r.status_code == 200:
                        data = r.json()
                        return data["choices"][0]["message"]["content"].strip()
                    else:
                        logger.error(f"Erro OpenAI API [{r.status_code}]: {r.text}")

            except Exception as e:
                logger.error(f"Exceção na LLM ({llm_provider}): {e}")

        return "Entendi perfeitamente. Como posso te ajudar?"
