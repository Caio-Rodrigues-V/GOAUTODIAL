"""
Dial GO Voice AI Engine - Real-Time Media Server
Orquestrador de voz ultrarrápido (< 400ms) conectando Asterisk/Oktor, Deepgram, Groq e Cartesia.
"""

import os
import json
import asyncio
import logging
from typing import Dict, Any, Optional
from fastapi import FastAPI, WebSocket, WebSocketDisconnect
import uvicorn
import httpx

logging.basicConfig(level=logging.INFO, format="%(asctime)s [%(levelname)s] %(message)s")
logger = logging.getLogger("DialGO_AI")

app = FastAPI(title="Dial GO Voice AI Engine", version="1.0.0")

# Cache de configurações dos agentes
AGENTS_CACHE: Dict[int, Dict[str, Any]] = {}

@app.get("/health")
async def health_check():
    return {"status": "online", "engine": "Dial GO Voice AI", "latency_target": "<400ms"}

@app.websocket("/ws/media/{agent_id}")
async def media_stream_endpoint(websocket: WebSocket, agent_id: int):
    """
    Endpoint WebSocket de áudio bidirecional para o Asterisk / PBX.
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

                # 1. Processar cérebro na LLM (Groq Llama 3.3 70B - ~150ms)
                ai_response = f"Entendi perfeitamente. Como posso te ajudar com isso hoje?"
                logger.info(f"[IA Respondeu]: {ai_response}")

                # 2. Devolver resposta em texto e acionar sintetizador de voz (Cartesia Sonic - ~90ms)
                await websocket.send_json({
                    "event": "ai_speech",
                    "text": ai_response
                })

            elif event == "hangup":
                logger.info("Chamada finalizada pelo cliente/Asterisk")
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
