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

import uuid
import time
from typing import Dict, Any, Optional, List

# Instância do SIP Engine Direto para a Oktor
sip_engine = DirectSIPEngine()

class DialRequest(BaseModel):
    agent_id: int
    phone_number: str
    agent_config: Optional[Dict[str, Any]] = None
    api_keys: Optional[Dict[str, str]] = None

class BatchContact(BaseModel):
    phone_number: str
    name: Optional[str] = ""
    custom_data: Optional[Dict[str, Any]] = None

class BatchDialRequest(BaseModel):
    agent_id: int
    contacts: List[Dict[str, Any]]
    concurrency: Optional[int] = 5
    delay_ms: Optional[int] = 250
    agent_config: Optional[Dict[str, Any]] = None
    api_keys: Optional[Dict[str, str]] = None

# Gerenciador em memória de lotes de discagem
batches_db: Dict[str, Dict[str, Any]] = {}
batch_tasks: Dict[str, asyncio.Task] = {}

async def _batch_worker(batch_id: str, req: BatchDialRequest):
    batch = batches_db[batch_id]
    concurrency = max(1, min(50, req.concurrency or 5))
    delay_sec = max(0.05, (req.delay_ms or 250) / 1000.0)
    sem = asyncio.Semaphore(concurrency)

    async def dial_single_contact(idx: int, contact_info: Dict[str, Any]):
        async with sem:
            # Verifica se o lote foi pausado ou cancelado
            while batch["status"] == "paused":
                await asyncio.sleep(0.5)
            if batch["status"] in ("cancelled", "stopped"):
                return

            phone = str(contact_info.get("phone_number") or contact_info.get("phone") or "").strip()
            name = contact_info.get("name") or contact_info.get("customer_name") or ""
            custom_data = contact_info.get("custom_data") or {}

            if not phone:
                batch["failed"] += 1
                batch["contacts"][idx]["status"] = "failed"
                batch["contacts"][idx]["error"] = "Telefone inválido"
                return

            batch["contacts"][idx]["status"] = "dialing"
            batch["in_progress"] += 1
            batch["queued"] = max(0, batch["queued"] - 1)

            # Personalização dinâmica do prompt do agente com dados do contato
            cfg_copy = dict(req.agent_config or {})
            if name or custom_data:
                greeting = cfg_copy.get("greeting_message", "")
                system_p = cfg_copy.get("system_prompt", "")
                if name:
                    greeting = greeting.replace("{nome}", name).replace("{name}", name)
                    system_p = system_p.replace("{nome}", name).replace("{name}", name)
                for k, v in custom_data.items():
                    greeting = greeting.replace(f"{{{k}}}", str(v))
                    system_p = system_p.replace(f"{{{k}}}", str(v))
                cfg_copy["greeting_message"] = greeting
                cfg_copy["system_prompt"] = system_p

            try:
                res = await sip_engine.dial(
                    req.agent_id, 
                    phone, 
                    agent_config=cfg_copy, 
                    api_keys=req.api_keys
                )
                call_id = res.get("call_id")
                batch["contacts"][idx]["call_id"] = call_id

                # Aguarda a conclusão da chamada SIP
                if call_id:
                    while call_id in sip_engine.active_calls:
                        call_obj = sip_engine.active_calls[call_id]
                        batch["contacts"][idx]["call_status"] = call_obj.status
                        await asyncio.sleep(0.4)

                batch["completed"] += 1
                batch["in_progress"] = max(0, batch["in_progress"] - 1)
                batch["contacts"][idx]["status"] = "completed"

            except Exception as e:
                logger.error(f"[Batch {batch_id}] Erro ao discar para {phone}: {e}")
                batch["failed"] += 1
                batch["in_progress"] = max(0, batch["in_progress"] - 1)
                batch["contacts"][idx]["status"] = "failed"
                batch["contacts"][idx]["error"] = str(e)

            batch["updated_at"] = time.time()

    # Dispara tarefas concorrentes com espaçamento
    tasks = []
    for idx, contact in enumerate(req.contacts):
        if batch["status"] in ("cancelled", "stopped"):
            break
        t = asyncio.create_task(dial_single_contact(idx, contact))
        tasks.append(t)
        await asyncio.sleep(delay_sec)

    if tasks:
        await asyncio.gather(*tasks, return_exceptions=True)

    if batch["status"] != "cancelled":
        batch["status"] = "completed"
    batch["updated_at"] = time.time()
    logger.info(f"🚀 [Batch {batch_id}] Disparo em lote finalizado! Total: {batch['total']}, Concluídas: {batch['completed']}, Falhas: {batch['failed']}")

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
        "active_batches": len([b for b in batches_db.values() if b["status"] == "running"]),
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

@app.post("/api/dial/batch")
async def originate_batch_calls(req: BatchDialRequest):
    """
    Inicia uma campanha de disparo em lote (Batch Dialing) com controle de concorrência.
    """
    if not req.contacts or len(req.contacts) == 0:
        return {"status": "error", "message": "Nenhum contato fornecido para disparo em lote."}

    batch_id = f"batch_{int(time.time())}_{uuid.uuid4().hex[:6]}"
    total_contacts = len(req.contacts)

    formatted_contacts = []
    for c in req.contacts:
        p = str(c.get("phone_number") or c.get("phone") or "").strip()
        n = c.get("name") or c.get("customer_name") or ""
        formatted_contacts.append({
            "phone_number": p,
            "name": n,
            "custom_data": c.get("custom_data", {}),
            "status": "queued",
            "call_id": None,
            "call_status": "pending"
        })

    batches_db[batch_id] = {
        "batch_id": batch_id,
        "agent_id": req.agent_id,
        "status": "running",
        "concurrency": req.concurrency or 5,
        "total": total_contacts,
        "queued": total_contacts,
        "in_progress": 0,
        "completed": 0,
        "failed": 0,
        "contacts": formatted_contacts,
        "created_at": time.time(),
        "updated_at": time.time()
    }

    logger.info(f"🚀 Iniciando Campanha em Lote [{batch_id}] para {total_contacts} contatos (Concorrência={req.concurrency or 5})...")
    task = asyncio.create_task(_batch_worker(batch_id, req))
    batch_tasks[batch_id] = task

    return {
        "status": "success",
        "batch_id": batch_id,
        "total": total_contacts,
        "concurrency": req.concurrency or 5,
        "message": f"Disparo em lote iniciado com sucesso para {total_contacts} contatos!"
    }

@app.get("/api/batch/{batch_id}")
async def get_batch_status(batch_id: str):
    """
    Retorna o progresso em tempo real do disparo em lote.
    """
    batch = batches_db.get(batch_id)
    if not batch:
        return {"status": "error", "message": "Lote não encontrado."}
    return {"status": "success", "batch": batch}

@app.post("/api/batch/{batch_id}/pause")
async def pause_batch(batch_id: str):
    batch = batches_db.get(batch_id)
    if not batch:
        return {"status": "error", "message": "Lote não encontrado."}
    batch["status"] = "paused"
    logger.info(f"⏸️ Campanha em Lote [{batch_id}] pausada.")
    return {"status": "success", "message": "Campanha pausada."}

@app.post("/api/batch/{batch_id}/resume")
async def resume_batch(batch_id: str):
    batch = batches_db.get(batch_id)
    if not batch:
        return {"status": "error", "message": "Lote não encontrado."}
    batch["status"] = "running"
    logger.info(f"▶️ Campanha em Lote [{batch_id}] retomada.")
    return {"status": "success", "message": "Campanha retomada."}

@app.post("/api/batch/{batch_id}/cancel")
async def cancel_batch(batch_id: str):
    batch = batches_db.get(batch_id)
    if not batch:
        return {"status": "error", "message": "Lote não encontrado."}
    batch["status"] = "cancelled"
    logger.info(f"⏹️ Campanha em Lote [{batch_id}] cancelada.")
    return {"status": "success", "message": "Campanha cancelada."}

@app.get("/api/batches")
async def list_recent_batches():
    return {
        "count": len(batches_db),
        "batches": list(batches_db.values())[-20:]
    }

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

@app.get("/api/monitor/live")
async def get_live_monitor():
    """
    Retorna telemetria em tempo real dos 500 canais e métricas de CPS da Oktor Telecom.
    """
    now = time.time()
    active_list = []
    for cid, call in list(sip_engine.active_calls.items()):
        duration = int(now - call.answered_time) if call.answered_time > 0 else int(now - call.start_time)
        user_speech = [m.get("content", "") for m in call.conversation_history if m.get("role") == "user"]
        last_turn = user_speech[-1] if user_speech else ""
        
        status_label = "ringing"
        if call.answered_time > 0 or call.status == "answered":
            status_label = "in_call"
        elif call.status == "initiating":
            status_label = "initiating"

        active_list.append({
            "call_id": call.call_id,
            "phone_number": call.phone_number,
            "destination": call.dial_string,
            "agent_id": call.agent_id,
            "agent_name": (call.agent_config or {}).get("agent_name", f"Agente #{call.agent_id}"),
            "status": status_label,
            "duration_seconds": duration,
            "duration_formatted": f"{duration//60:02d}:{duration%60:02d}",
            "started_at": time.strftime("%H:%M:%S", time.localtime(call.start_time)),
            "answered": call.answered_time > 0,
            "last_dialogue": last_turn[:60] if last_turn else "Aguardando primeira fala..."
        })

    # Cálculo dinâmico de CPS
    cps_1s = len([t for t in sip_engine.call_timestamps if now - t <= 1.0])
    cps_60s = len([t for t in sip_engine.call_timestamps if now - t <= 60.0])

    return {
        "status": "online",
        "max_channels": 500,
        "max_cps": 100,
        "active_channels": len(sip_engine.active_calls),
        "channel_utilization_pct": round((len(sip_engine.active_calls) / 500.0) * 100, 1),
        "current_cps": cps_1s,
        "calls_last_minute": cps_60s,
        "total_calls_today": sip_engine.total_calls_today,
        "ringing_count": len([c for c in sip_engine.active_calls.values() if c.status == "ringing" and c.answered_time == 0]),
        "in_call_count": len([c for c in sip_engine.active_calls.values() if c.answered_time > 0 or c.status == "answered"]),
        "active_calls": active_list,
        "timestamp": now
    }

@app.post("/api/monitor/hangup/{call_id:path}")
async def hangup_live_call(call_id: str):
    """
    Derruba uma chamada ativa em tempo real.
    """
    if call_id in sip_engine.active_calls:
        logger.info(f"🛑 [Live Monitor]: Desconexão manual solicitada para {call_id}")
        await sip_engine.send_bye(call_id)
        return {"status": "success", "message": f"Chamada {call_id} encerrada com sucesso."}
    return {"status": "error", "message": "Chamada não encontrada ou já finalizada."}

if __name__ == "__main__":
    port = int(os.environ.get("PORT", 8765))
    logger.info(f"Iniciando Dial GO Voice AI Server na porta {port}...")
    uvicorn.run("server:app", host="0.0.0.0", port=port, log_level="info")
