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
        Transcreve o áudio do cliente em texto em < 150ms usando Deepgram, Groq, OpenAI ou Azure.
        """
        if not pcm_bytes or len(pcm_bytes) < 2400:  # Menos de 150ms de áudio é ruído
            return ""

        stt_provider = (stt_provider or "deepgram").lower()
        wav_data = create_wav_from_pcm(pcm_bytes, 8000)
        whisper_pt_prompt = "Transcrição em português do Brasil em chamada telefônica. Entenda com precisão: sim, não, alô, quem fala, tá bom, olá, pode falar, não quero, tenho interesse."

        async with httpx.AsyncClient(timeout=6.0) as client:
            try:
                # 1. Deepgram Nova-2 (Ultra-rápido ~100ms, especialista em telefonia PT-BR - Padrão Vapi)
                if (stt_provider == "deepgram" or not stt_provider) and api_keys.get("deepgram_api_key"):
                    api_key = api_keys.get("deepgram_api_key")
                    url = "https://api.deepgram.com/v1/listen?model=nova-2&language=pt-BR&smart_format=true&punctuate=true&numerals=true&endpointing=300&vad_turnoff=500&encoding=linear16&sample_rate=8000"
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

                # 2. Groq Whisper Large v3 Turbo (Latência ~120ms com Prompt Biasing anti-alucinação)
                elif stt_provider == "groq" and api_keys.get("groq_api_key"):
                    api_key = api_keys.get("groq_api_key")
                    files = {
                        "file": ("audio.wav", wav_data, "audio/wav")
                    }
                    data = {
                        "model": "whisper-large-v3-turbo",
                        "language": "pt",
                        "response_format": "json",
                        "temperature": "0.0",
                        "prompt": whisper_pt_prompt
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

                # 3. OpenAI Whisper (com Prompt Biasing anti-alucinação)
                elif stt_provider == "openai" and api_keys.get("openai_api_key"):
                    api_key = api_keys.get("openai_api_key")
                    files = {
                        "file": ("audio.wav", wav_data, "audio/wav")
                    }
                    data = {
                        "model": "whisper-1",
                        "language": "pt",
                        "temperature": "0.0",
                        "prompt": whisper_pt_prompt
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

                # 4. Microsoft Azure Speech to Text (STT)
                elif stt_provider == "azure" and api_keys.get("azure_speech_key"):
                    api_key = api_keys.get("azure_speech_key")
                    region = api_keys.get("azure_speech_region") or "eastus"
                    url = f"https://{region}.stt.speech.microsoft.com/speech/recognition/conversation/cognitiveservices/v1?language=pt-BR&format=detailed"
                    headers = {
                        "Ocp-Apim-Subscription-Key": api_key,
                        "Content-Type": "audio/wav; codecs=audio/pcm; samplerate=8000"
                    }
                    r = await client.post(url, content=wav_data, headers=headers)
                    if r.status_code == 200:
                        data = r.json()
                        if data.get("RecognitionStatus") == "Success":
                            transcript = data.get("DisplayText", "").strip()
                            if transcript:
                                logger.info(f"[Azure Speech STT]: '{transcript}'")
                                return transcript
                    else:
                        logger.error(f"Erro Azure Speech STT [{r.status_code}]: {r.text}")

                # 5. ElevenLabs Scribe v1 (STT)
                elif stt_provider == "elevenlabs" and api_keys.get("elevenlabs_api_key"):
                    api_key = api_keys.get("elevenlabs_api_key")
                    files = {
                        "file": ("audio.wav", wav_data, "audio/wav")
                    }
                    data = {
                        "model_id": "scribe_v1",
                        "language_code": "por"
                    }
                    headers = {
                        "xi-api-key": api_key
                    }
                    r = await client.post("https://api.elevenlabs.io/v1/speech-to-text", files=files, data=data, headers=headers)
                    if r.status_code == 200:
                        res = r.json()
                        transcript = res.get("text", "").strip()
                        if transcript:
                            logger.info(f"[ElevenLabs Scribe STT]: '{transcript}'")
                            return transcript
                    else:
                        logger.error(f"Erro ElevenLabs STT [{r.status_code}]: {r.text}")

                # Fallbacks automáticos se o provedor principal não tiver chave
                if api_keys.get("deepgram_api_key"):
                    api_key = api_keys.get("deepgram_api_key")
                    url = "https://api.deepgram.com/v1/listen?model=nova-2&language=pt-BR&smart_format=true&punctuate=true&numerals=true&endpointing=300&vad_turnoff=500&encoding=linear16&sample_rate=8000"
                    r = await client.post(url, content=wav_data, headers={"Authorization": f"Token {api_key}", "Content-Type": "audio/wav"})
                    if r.status_code == 200:
                        return r.json()["results"]["channels"][0]["alternatives"][0]["transcript"].strip()

                elif api_keys.get("groq_api_key"):
                    api_key = api_keys.get("groq_api_key")
                    files = {"file": ("audio.wav", wav_data, "audio/wav")}
                    data = {"model": "whisper-large-v3-turbo", "language": "pt", "prompt": whisper_pt_prompt}
                    r = await client.post("https://api.groq.com/openai/v1/audio/transcriptions", files=files, data=data, headers={"Authorization": f"Bearer {api_key}"})
                    if r.status_code == 200:
                        return r.json().get("text", "").strip()

                elif api_keys.get("openai_api_key"):
                    api_key = api_keys.get("openai_api_key")
                    files = {"file": ("audio.wav", wav_data, "audio/wav")}
                    data = {"model": "whisper-1", "language": "pt", "prompt": whisper_pt_prompt}
                    r = await client.post("https://api.openai.com/v1/audio/transcriptions", files=files, data=data, headers={"Authorization": f"Bearer {api_key}"})
                    if r.status_code == 200:
                        return r.json().get("text", "").strip()

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

                # 4. Microsoft Azure Speech TTS
                elif voice_provider == "azure":
                    api_key = api_keys.get("azure_speech_key", "")
                    region = api_keys.get("azure_speech_region", "eastus")
                    if not api_key:
                        logger.error("Chave da Azure Speech não configurada")
                        return b''

                    azure_voice = voice_id if voice_id and voice_id != "custom" else "pt-BR-FranciscaNeural"
                    url = f"https://{region}.tts.speech.microsoft.com/cognitiveservices/v1"
                    ssml = f"<speak version='1.0' xml:lang='pt-BR'><voice xml:lang='pt-BR' name='{azure_voice}'>{text}</voice></speak>"
                    headers = {
                        "Ocp-Apim-Subscription-Key": api_key,
                        "Content-Type": "application/ssml+xml",
                        "X-Microsoft-OutputFormat": "raw-8khz-16bit-mono-pcm",
                        "User-Agent": "DialGO-VoiceEngine"
                    }
                    r = await client.post(url, content=ssml.encode('utf-8'), headers=headers)
                    if r.status_code == 200:
                        return r.content
                    else:
                        logger.error(f"Erro Azure TTS [{r.status_code}]: {r.text}")

            except Exception as e:
                logger.error(f"Exceção durante síntese de áudio: {e}")

        return b''

    @staticmethod
    async def chat_completion(messages: List[Dict[str, str]], llm_provider: str, llm_model: str, temperature: float, api_keys: Dict[str, str]) -> str:
        """
        Executa completion da LLM com altíssima velocidade (< 300ms) de forma 100% dinâmica.
        """
        llm_provider = (llm_provider or "groq").lower()
        
        async with httpx.AsyncClient(timeout=10.0) as client:
            try:
                # 1. Groq (Llama 3.3 70B, Llama 3.1 8B, DeepSeek-R1-Distill, Mixtral, etc.)
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
                    else:
                        logger.error(f"Erro Groq API [{r.status_code}]: {r.text}")

                # 2. DeepSeek (DeepSeek V3, DeepSeek R1)
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
                    else:
                        logger.error(f"Erro DeepSeek API [{r.status_code}]: {r.text}")

                # 3. Anthropic Claude (Claude 3.5 Sonnet, Claude 3.5 Haiku, Claude 3 Opus)
                elif llm_provider == "anthropic" and api_keys.get("anthropic_api_key"):
                    api_key = api_keys.get("anthropic_api_key")
                    model = llm_model if llm_model and llm_model != "custom" else "claude-3-5-sonnet-20241022"
                    
                    system_text = ""
                    anthropic_messages = []
                    for m in messages:
                        if m.get("role") == "system":
                            system_text = m.get("content", "")
                        else:
                            anthropic_messages.append({
                                "role": "user" if m.get("role") == "user" else "assistant",
                                "content": m.get("content", "")
                            })
                    
                    if not anthropic_messages:
                        anthropic_messages = [{"role": "user", "content": "Olá"}]

                    payload = {
                        "model": model,
                        "max_tokens": 150,
                        "temperature": float(temperature) if temperature is not None else 0.7,
                        "messages": anthropic_messages
                    }
                    if system_text:
                        payload["system"] = system_text

                    headers = {
                        "x-api-key": api_key,
                        "anthropic-version": "2023-06-01",
                        "Content-Type": "application/json"
                    }
                    r = await client.post("https://api.anthropic.com/v1/messages", json=payload, headers=headers)
                    if r.status_code == 200:
                        data = r.json()
                        content_list = data.get("content", [])
                        if content_list and len(content_list) > 0:
                            return content_list[0].get("text", "").strip()
                    else:
                        logger.error(f"Erro Anthropic API [{r.status_code}]: {r.text}")

                # 4. Google Gemini (Gemini 2.0 Flash, Gemini 1.5 Pro, etc.)
                elif llm_provider == "gemini" and api_keys.get("gemini_api_key"):
                    api_key = api_keys.get("gemini_api_key")
                    model = llm_model if llm_model and llm_model != "custom" else "gemini-2.0-flash"
                    
                    system_text = ""
                    contents = []
                    for m in messages:
                        if m.get("role") == "system":
                            system_text = m.get("content", "")
                        else:
                            role_str = "user" if m.get("role") == "user" else "model"
                            contents.append({
                                "role": role_str,
                                "parts": [{"text": m.get("content", "")}]
                            })
                    
                    payload = {
                        "contents": contents,
                        "generationConfig": {
                            "temperature": float(temperature) if temperature is not None else 0.7,
                            "maxOutputTokens": 150
                        }
                    }
                    if system_text:
                        payload["systemInstruction"] = {"parts": [{"text": system_text}]}

                    url = f"https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent?key={api_key}"
                    r = await client.post(url, json=payload, headers={"Content-Type": "application/json"})
                    if r.status_code == 200:
                        data = r.json()
                        candidates = data.get("candidates", [])
                        if candidates and "content" in candidates[0]:
                            parts = candidates[0]["content"].get("parts", [])
                            if parts:
                                return parts[0].get("text", "").strip()
                    else:
                        logger.error(f"Erro Gemini API [{r.status_code}]: {r.text}")

                # 5. OpenAI (GPT-4o, GPT-4o Mini, o3-mini, o1, o1-mini, GPT-4 Turbo, GPT-3.5 Turbo) ou Fallback
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
