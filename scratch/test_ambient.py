import math
import random
import wave
import io
import struct

def generate_ambient_office_pcm(duration_sec: float = 12.0, sample_rate: int = 8000) -> bytes:
    total_samples = int(duration_sec * sample_rate)
    samples = [0.0] * total_samples
    
    # 1. Pink Noise / Room Hum (Acoustic air & HVAC hum)
    b0, b1, b2, b3, b4, b5, b6 = 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0
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
        # Low hum amplitude
        samples[i] += (pink * 0.12)
        
        # 60Hz / 120Hz power hum subtle baseline
        samples[i] += 0.03 * math.sin(2 * math.pi * 60 * i / sample_rate)
    
    # 2. Add realistic intermittent keyboard typing clicks
    t = 0.5
    while t < duration_sec - 0.5:
        # Keystroke burst (typing a word)
        burst_len = random.randint(3, 8)
        for _ in range(burst_len):
            idx = int(t * sample_rate)
            if idx >= total_samples - 400:
                break
            # Key click impulse (~20ms click with high frequency resonance)
            freq = random.uniform(1200, 2400)
            click_amp = random.uniform(0.35, 0.65)
            for k in range(int(0.025 * sample_rate)):
                if idx + k < total_samples:
                    env = math.exp(-k / (0.005 * sample_rate))
                    click = math.sin(2 * math.pi * freq * k / sample_rate) * env * click_amp
                    samples[idx + k] += click
            t += random.uniform(0.08, 0.18)
        t += random.uniform(0.6, 2.5) # pause between words
        
    # 3. Add distant subtle mouse clicks
    t = 1.0
    while t < duration_sec - 1.0:
        idx = int(t * sample_rate)
        if idx < total_samples - 200:
            freq = random.uniform(2800, 3500)
            amp = random.uniform(0.2, 0.35)
            for k in range(int(0.015 * sample_rate)):
                if idx + k < total_samples:
                    env = math.exp(-k / (0.003 * sample_rate))
                    click = math.sin(2 * math.pi * freq * k / sample_rate) * env * amp
                    samples[idx + k] += click
        t += random.uniform(2.0, 5.0)

    # Convert to 16-bit PCM Little Endian
    out = bytearray(total_samples * 2)
    for i, s in enumerate(samples):
        # Normalize/clamp to int16 (-32767 to 32767)
        val = int(max(-1.0, min(1.0, s * 0.7)) * 32767)
        struct.pack_into('<h', out, i * 2, val)
        
    return bytes(out)

pcm = generate_ambient_office_pcm(5.0)
print(f"Generated {len(pcm)} bytes of PCM ambient audio successfully!")
