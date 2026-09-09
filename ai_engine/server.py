"""
Dial GO Voice AI Engine - Real-Time Media & Direct SIP Server
Orquestrador de voz ultrarrápido (< 400ms) conectando diretamente à Oktor Telecom, Deepgram, Groq, OpenAI e Cartesia.
"""

import os
import json
import asyncio
import logging
from typing import Dict, Any, Optional
from fastapi import FastAPI, WebSocket, WebSocketDisconnect, Request
from pydantic import BaseModel
import uvicorn
import httpx

from sip_client import DirectSIPEngine

logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")
logger = logging.getLogger("DialGO_AI")

app = FastAPI(title="Dial GO Voice AI Engine", version="1.0.0")

# Instância do SIP Engine Direto para a Oktor
sip_engine = DirectSIPEngine()

class DialRequest(BaseModel):
    agent_id: int
    phone_number: str
    agent_config: Optional[Dict[str, Any]] = None
    api_keys: Optional[Dict[str, str]] = None

@app.on_event("startup")
async def startup_event():
    logger.info("Inicializando Direct SIP Engine para Oktor Telecom...")
    await sip_engine.start()

@app.get("/health")
async def health_check():
    return {
        "status": "online", 
        "engine": "Dial GO Voice AI", 
        "sip_engine": "active",
        "active_calls": len(sip_engine.active_calls),
        "latency_target": "<400ms"
    }

@app.post("/api/dial")
async def originate_call(req: DialRequest):
    """
    Origina uma chamada SIP direta para a Oktor Telecom sem precisar de Asterisk.
    """
    logger.info(f"Recebida solicitação de discagem para Agente #{req.agent_id} -> {req.phone_number}")
    result = await sip_engine.dial(req.agent_id, req.phone_number, agent_config=req.agent_config, api_keys=req.api_keys)
    return result

@app.get("/api/calls")
async def list_active_calls():
    calls = []
    for c_id, c in sip_engine.active_calls.items():
        calls.append({
            "call_id": c_id,
            "destination": c.dial_string,
            "agent_id": c.agent_id,
            "status": c.status
        })
    return {"count": len(calls), "calls": calls}

@app.websocket("/ws/media/{agent_id}")
async def media_stream_endpoint(websocket: WebSocket, agent_id: int):
    """
    Endpoint WebSocket de áudio bidirecional.
    Recebe chunks de áudio PCM (8kHz/16kHz) do cliente e devolve o áudio gerado pelo TTS.
    """
    await websocket.accept()
    logger.info(f"Nova chamada conectada para o Agente de IA #{agent_id}")

    try:
        # Saudação inicial
        greeting_text = "Olá, tudo bem? Falo com o titular da linha?"
        
        # Envia evento de início com a saudação
        await websocket.send_json({
            "event": "greeting",
            "text": greeting_text
        })

        while True:
            # Recebe mensagens ou pacotes de áudio
            message = await websocket.receive_text()
            data = json.loads(message)

            event = data.get("event")
            if event == "user_speech":
                user_transcript = data.get("text", "")
                logger.info(f"[Cliente Falou]: {user_transcript}")

                # 1. Processar cérebro na LLM dinâmica
                ai_response = await AIVoiceBrain.chat_completion(
                    [{"role": "user", "content": user_transcript}],
                    "openai",
                    "gpt-4o-mini",
                    0.7,
                    {}
                )
                logger.info(f"[IA Respondeu]: {ai_response}")

                # 2. Devolver resposta em texto e áudio
                await websocket.send_json({
                    "event": "ai_speech",
                    "text": ai_response
                })

            elif event == "hangup":
                logger.info("Chamada finalizada pelo cliente")
                break

    except WebSocketDisconnect:
        logger.info(f"Conexão encerrada para o Agente #{agent_id}")
    except Exception as e:
        logger.error(f"Erro na sessão de voz: {e}")
    finally:
        try:
            await websocket.close()
        except:
            pass

if __name__ == "__main__":
    port = int(os.environ.get("PORT", 8765))
    logger.info(f"Iniciando Dial GO Voice AI Server na porta {port}...")
    uvicorn.run("server:app", host="0.0.0.0", port=port, log_level="info")
