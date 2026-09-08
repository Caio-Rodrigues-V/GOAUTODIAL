#!/usr/bin/env python3
"""
Dial GO Voice AI Engine - Asterisk AGI Media Bridge
Conecta a chamada telefônica do Asterisk diretamente com a IA de Voz em tempo real.
"""

import sys
import os
import json
import time
import urllib.request
import urllib.parse
import subprocess

def agi_send(command):
    sys.stdout.write(f"{command}\n")
    sys.stdout.flush()
    return sys.stdin.readline().strip()

def agi_read_env():
    env = {}
    while True:
        line = sys.stdin.readline().strip()
        if not line:
            break
        key, data = line.split(":", 1)
        env[key.strip()] = data.strip()
    return env

def main():
    env = agi_read_env()
    agent_id = env.get("agi_arg_1") or os.environ.get("AGENT_ID", "1")
    
    # 1. Answer call if not answered
    agi_send("ANSWER")
    time.sleep(0.5)

    # 2. Get Agent Info from Web Backend
    try:
        url = f"http://127.0.0.1/php/TestAIAgent.php"
        data = urllib.parse.urlencode({
            'agent_id': agent_id,
            'message': '',
            'history': '[]'
        }).encode('utf-8')
        
        req = urllib.request.Request(url, data=data)
        with urllib.request.urlopen(req, timeout=10) as resp:
            res = json.loads(resp.read().decode('utf-8'))
            
            greeting_text = res.get('response_text', 'Olá, tudo bem? Falo com o titular da linha?')
            audio_url = res.get('audio_url', '')

            # If audio was generated, download to temporary WAV and play via Asterisk
            if audio_url and 'base64,' in audio_url:
                import base64
                b64_data = audio_url.split('base64,')[1]
                audio_bytes = base64.b64decode(b64_data)
                
                temp_mp3 = f"/tmp/dialgo_ai_{agent_id}_{int(time.time())}.mp3"
                temp_wav = f"/tmp/dialgo_ai_{agent_id}_{int(time.time())}.wav"
                
                with open(temp_mp3, 'wb') as f:
                    f.write(audio_bytes)
                
                # Convert to Asterisk format (WAV 8kHz mono 16bit)
                subprocess.run(["ffmpeg", "-y", "-i", temp_mp3, "-ar", "8000", "-ac", "1", "-c:a", "pcm_s16le", temp_wav], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
                
                # Play audio in Asterisk (without extension)
                wav_no_ext = temp_wav.replace(".wav", "")
                agi_send(f'STREAM FILE "{wav_no_ext}" ""')
                
                # Cleanup temp
                try:
                    os.remove(temp_mp3)
                    os.remove(temp_wav)
                except:
                    pass
            else:
                # Fallback to Cepstral / Festival / Google TTS if available or beep
                agi_send('SAY ALPHA "Dial GO AI"')
                
    except Exception as e:
        agi_send(f'VERBOSE "Erro no AGI Dial GO AI: {e}" 1')

    # Wait or bridge to continuous socket
    time.sleep(1)

if __name__ == "__main__":
    main()
