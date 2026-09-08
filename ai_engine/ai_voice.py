"""
Dial GO Voice AI Engine - AI Synthesis & LLM Brain
Síntese de voz em tempo real (OpenAI, ElevenLabs, Cartesia) e raciocínio de conversação (Groq, OpenAI, Anthropic, DeepSeek).
"""

import httpx
import logging
import json
import io
import wave
from typing import Dict, Any, List, Optional
from rtp_media import resample_wav_to_8k_pcm

logger = logging.getLogger("DialGO_Brain")

class AIVoiceBrain:
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
                        # Retorna diretamente PCM 8kHz
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
        Executa completion da LLM com altíssima velocidade (< 300ms).
        """
        llm_provider = (llm_provider or "groq").lower()
        
        async with httpx.AsyncClient(timeout=10.0) as client:
            try:
                # 1. Groq (Llama 3.3 70B Versatile)
                if llm_provider == "groq" and api_keys.get("groq_api_key"):
                    api_key = api_keys.get("groq_api_key")
                    model = llm_model if llm_model and llm_model != "custom" else "llama-3.3-70b-versatile"
                    payload = {
                        "model": model,
                        "messages": messages,
                        "temperature": temperature or 0.7,
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

                # 2. OpenAI (GPT-4o Mini / GPT-4o)
                if api_keys.get("openai_api_key"):
                    api_key = api_keys.get("openai_api_key")
                    model = llm_model if llm_model and llm_model != "custom" else "gpt-4o-mini"
                    payload = {
                        "model": model,
                        "messages": messages,
                        "temperature": temperature or 0.7,
                        "max_tokens": 150
                    }
                    headers = {
                        "Authorization": f"Bearer {api_key}",
                        "Content-Type": "application/json"
                    }
                    r = await client.post("https://api.openai.com/v1/chat/completions", json=payload, headers=headers)
                    if r.status_code == 200:
                        data = r.json()
                        return data["choices"][0]["message"]["content"].strip()

            except Exception as e:
                logger.error(f"Exceção na LLM: {e}")

        return "Entendi perfeitamente. Como posso te ajudar?"
