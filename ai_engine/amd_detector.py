"""
Dial GO Voice AI Engine - Advanced Answering Machine Detection (AMD)
Algoritmo de detecao ultrarrapida de Caixa Postal e Secretaria Eletronica (< 2 segundos).
Combina 3 camadas de analise:
1. Detecao de Tons e Beeps de Operadora (Pureza Espectral / Goertzel em 400Hz - 1100Hz)
2. Analise Temporal de Fala Continua (Mensagens longas pre-gravadas sem pausas)
3. Classificador Semantico Rapido para Operadoras Brasileiras (Vivo, Claro, TIM, Oi, Embratel)
"""

import math
import re
import struct
import logging
from typing import Tuple, List, Optional

logger = logging.getLogger("DialGO_AMD")

# Padroes semanticos de caixas postais e mensagens gravadas de operadoras brasileiras
VOICEMAIL_PATTERNS: List[str] = [
    r"deixe (o |seu )?recado",
    r"ap[oó]s o (sinal|bipe?|tom)",
    r"caixa (postal|de mensagens?|de correio)",
    r"correio de voz",
    r"gravar? (o |seu )?recado",
    r"n[aã]o pode (atender|receber)",
    r"n[aã]o est[aá] dispon[ií]vel",
    r"chamada encaminhada",
    r"liga[cç][aã]o est[aá] sendo encaminhada",
    r"temporariamente (fora de servi[cç]o|indispon[ií]vel|impossibilitado)",
    r"fora da [aá]rea de cobertura",
    r"desligado ou fora de [aá]rea",
    r"n[uú]mero (chamado|discado|inexistente|indispon[ií]vel)",
    r"sua chamada est[aá] sendo transferida",
    r"atendimento eletr[oô]nico",
    r"operadora informa",
    r"por favor tente mais tarde",
    r"recado com data e hora",
    r"programado para n[aã]o receber",
    r"n[aã]o foi poss[ií]vel completar",
    r"sua liga[cç][aã]o ser[aá] cobrada",
    r"voc[eê] ligou para",
    r"este telefone n[aã]o",
    r"hor[aá]rio de atendimento"
]

COMPILED_PATTERNS = [re.compile(p, re.IGNORECASE) for p in VOICEMAIL_PATTERNS]

def calculate_spectral_purity_and_freq(pcm_bytes: bytes, sample_rate: int = 8000) -> Tuple[bool, float, float]:
    """
    Analisa os samples PCM de 8000Hz para detectar se o som e um Tom Puro / Beep de operadora.
    Retorna: (is_pure_tone, dominant_freq, purity_ratio)
    """
    if not pcm_bytes or len(pcm_bytes) < 320:  # Minimo de 20ms de audio (160 samples)
        return False, 0.0, 0.0

    num_samples = len(pcm_bytes) // 2
    samples = []
    for i in range(num_samples):
        val = int.from_bytes(pcm_bytes[i*2:(i+1)*2], byteorder='little', signed=True)
        samples.append(val)

    # 1. Zero Crossing Rate (ZCR)
    zero_crossings = 0
    for i in range(1, num_samples):
        if (samples[i] >= 0 and samples[i-1] < 0) or (samples[i] < 0 and samples[i-1] >= 0):
            zero_crossings += 1

    # Frequencia fundamental aproximada via ZCR
    approx_freq = (zero_crossings * sample_rate) / (2.0 * num_samples)

    # 2. Autocorrelacao para calcular pureza da onda senoidal
    energy = sum(s * s for s in samples)
    if energy < 1e6:  # Silencio ou energia muito baixa
        return False, approx_freq, 0.0

    if approx_freq < 300 or approx_freq > 1400:
        return False, approx_freq, 0.0

    period_samples = int(round(sample_rate / approx_freq))
    if period_samples <= 0 or period_samples >= num_samples // 2:
        return False, approx_freq, 0.0

    autocorr = 0.0
    norm_energy = 0.0
    for i in range(num_samples - period_samples):
        autocorr += samples[i] * samples[i + period_samples]
        norm_energy += samples[i] * samples[i]

    if norm_energy == 0:
        return False, approx_freq, 0.0

    purity = autocorr / norm_energy

    # Beeps de operadora tem pureza espectral >= 0.82 na faixa de 380Hz a 1200Hz
    is_beep = (purity >= 0.82) and (380.0 <= approx_freq <= 1200.0)

    return is_beep, approx_freq, purity


class AnsweringMachineDetector:
    """
    Rastreador de Estado AMD por Chamada
    """
    def __init__(self, call_id: str):
        self.call_id = call_id
        self.consecutive_beep_ms: float = 0.0
        self.continuous_speech_ms: float = 0.0
        self.call_start_time: float = 0.0
        self.is_detected_voicemail: bool = False
        self.detection_reason: str = ""

    def start(self, start_timestamp: float):
        self.call_start_time = start_timestamp

    def feed_audio_chunk(self, pcm_chunk: bytes, duration_ms: float, is_speech: bool) -> Tuple[bool, str]:
        """
        Alimenta o detector com cada frame RTP recebido (20ms).
        Retorna: (is_voicemail, reason)
        """
        if self.is_detected_voicemail:
            return True, self.detection_reason

        # Analisa Beeps de Caixa Postal
        is_beep, freq, purity = calculate_spectral_purity_and_freq(pcm_chunk, 8000)
        if is_beep:
            self.consecutive_beep_ms += duration_ms
            if self.consecutive_beep_ms >= 180.0:  # Beep continuo por mais de 180ms
                self.is_detected_voicemail = True
                self.detection_reason = f"BEEP_DETECTED ({int(freq)}Hz, {int(self.consecutive_beep_ms)}ms)"
                logger.info(f"[AMD Spectrogram]: Beep de Caixa Postal detectado ({self.detection_reason}) na chamada {self.call_id}")
                return True, self.detection_reason
        else:
            self.consecutive_beep_ms = max(0.0, self.consecutive_beep_ms - duration_ms * 0.5)

        # Analisa Padrao de Fala Continua de Operadora (Sem pausas humanas)
        if is_speech:
            self.continuous_speech_ms += duration_ms
            # Humano fala 'Alo?' (500ms a 1000ms) e para. Operadora fala > 3500ms sem parar.
            if self.continuous_speech_ms >= 3800.0:
                self.is_detected_voicemail = True
                self.detection_reason = f"CONTINUOUS_OPERATOR_RECORDING ({int(self.continuous_speech_ms)}ms sem pausa)"
                logger.info(f"[AMD Cadence]: Fala continua de operadora detectada ({self.detection_reason}) na chamada {self.call_id}")
                return True, self.detection_reason
        else:
            # Silencio reduz a contagem de fala continua
            self.continuous_speech_ms = 0.0

        return False, ""

    def check_transcript(self, text: str) -> Tuple[bool, str]:
        """
        Verificacao semantica instantanea do texto transcrito pelo STT
        """
        if not text or self.is_detected_voicemail:
            return self.is_detected_voicemail, self.detection_reason

        lower = text.strip().lower()
        for pattern in COMPILED_PATTERNS:
            if pattern.search(lower):
                self.is_detected_voicemail = True
                self.detection_reason = f"SEMANTIC_MATCH ('{pattern.pattern}')"
                logger.info(f"[AMD Semantic]: Frase de Caixa Postal identificada ('{text}') -> {self.detection_reason}")
                return True, self.detection_reason

        return False, ""
