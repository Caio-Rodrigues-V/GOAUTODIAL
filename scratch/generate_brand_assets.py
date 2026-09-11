import os
import math
from PIL import Image, ImageDraw, ImageFont

# Destination directories
OUTPUT_DIR = os.path.join(os.path.dirname(__file__), "..", "img", "brand")
os.makedirs(OUTPUT_DIR, exist_ok=True)

ROOT_DIR = os.path.join(os.path.dirname(__file__), "..")

# Colors
ORANGE_PRIMARY = "#FF6B00"
ORANGE_DARK = "#E85D00"
CHARCOAL = "#111827"
GRAY_TEXT = "#4B5563"
GRAY_MUTED = "#9CA3AF"
WHITE = "#FFFFFF"

# --------------------------------------------------------------------------
# 1. GENERATE SVGS (Pure Vector, Infinitely Scalable)
# --------------------------------------------------------------------------

def get_symbol_svg_path(fg_grad="grad_orange", mono_color=None):
    fill_ref = mono_color if mono_color else f"url(#{fg_grad})"
    return f'''
    <g transform="translate(0, 0)">
      <!-- Outer Dialogue / D Contour -->
      <path d="M180 120 C 180 80, 210 50, 250 50 L 520 50 C 720 50, 870 200, 870 400 C 870 600, 720 750, 520 750 L 320 750 L 200 870 L 200 750 C 180 750, 180 720, 180 680 Z" fill="{fill_ref}" opacity="0.12"/>
      <path d="M220 160 C 220 130, 240 110, 270 110 L 510 110 C 670 110, 790 230, 790 390 C 790 550, 670 670, 510 670 L 340 670 C 330 670, 320 675, 312 683 L 260 735 L 260 660 C 260 645, 250 635, 235 635 C 225 635, 220 625, 220 615 Z" fill="none" stroke="{fill_ref}" stroke-width="56" stroke-linecap="round" stroke-linejoin="round"/>
      
      <!-- Voice Wave Bars in the Center of D -->
      <rect x="360" y="320" width="46" height="180" rx="23" fill="{fill_ref}"/>
      <rect x="445" y="240" width="46" height="340" rx="23" fill="{fill_ref}"/>
      <rect x="530" y="290" width="46" height="240" rx="23" fill="{fill_ref}"/>
      <rect x="615" y="350" width="46" height="120" rx="23" fill="{fill_ref}"/>
      
      <!-- Subtle Pulse Node / AI Connector -->
      <circle cx="530" cy="180" r="18" fill="{fill_ref}"/>
    </g>
    '''

def generate_svgs():
    # Defs with linear gradients
    defs = '''
    <defs>
      <linearGradient id="grad_orange" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%" stop-color="#FF6B00"/>
        <stop offset="100%" stop-color="#E85D00"/>
      </linearGradient>
      <linearGradient id="grad_orange_vibrant" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%" stop-color="#FF7A1A"/>
        <stop offset="100%" stop-color="#FF5500"/>
      </linearGradient>
    </defs>
    '''

    # A) 1024x1024 Symbols
    # 1. Color on Transparent
    with open(os.path.join(OUTPUT_DIR, "logo_symbol_color.svg"), "w", encoding="utf-8") as f:
        f.write(f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024" width="1024" height="1024">
        {defs}
        {get_symbol_svg_path("grad_orange")}
        </svg>''')

    # 2. Dark (on Dark Background)
    with open(os.path.join(OUTPUT_DIR, "logo_symbol_dark.svg"), "w", encoding="utf-8") as f:
        f.write(f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024" width="1024" height="1024">
        {defs}
        <rect width="1024" height="1024" rx="220" fill="#111827"/>
        {get_symbol_svg_path("grad_orange_vibrant")}
        </svg>''')

    # 3. Mono Black
    with open(os.path.join(OUTPUT_DIR, "logo_symbol_mono_black.svg"), "w", encoding="utf-8") as f:
        f.write(f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024" width="1024" height="1024">
        {defs}
        {get_symbol_svg_path(mono_color="#111827")}
        </svg>''')

    # 4. Mono White
    with open(os.path.join(OUTPUT_DIR, "logo_symbol_mono_white.svg"), "w", encoding="utf-8") as f:
        f.write(f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024" width="1024" height="1024">
        {defs}
        {get_symbol_svg_path(mono_color="#FFFFFF")}
        </svg>''')

    # B) 1600x400 Horizontal Full Logo
    # 1. Color on Light
    with open(os.path.join(OUTPUT_DIR, "logo_horizontal_color.svg"), "w", encoding="utf-8") as f:
        f.write(f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1600 400" width="1600" height="400">
        {defs}
        <g transform="translate(60, 40) scale(0.33)">
            {get_symbol_svg_path("grad_orange")}
        </g>
        <!-- Typography -->
        <text x="410" y="240" font-family="'Inter', 'Satoshi', 'Segoe UI', system-ui, sans-serif" font-size="140" font-weight="800" fill="#111827" letter-spacing="-3">DIAL<tspan fill="#FF6B00">og</tspan></text>
        <!-- DDM Badge / Tag -->
        <rect x="990" y="138" width="160" height="74" rx="14" fill="#FFF4EB" stroke="#FFE4CC" stroke-width="3"/>
        <text x="1070" y="190" font-family="'Inter', 'Satoshi', 'Segoe UI', system-ui, sans-serif" font-size="44" font-weight="800" fill="#FF6B00" text-anchor="middle" letter-spacing="1">DDM</text>
        <text x="415" y="295" font-family="'Inter', 'Segoe UI', system-ui, sans-serif" font-size="28" font-weight="600" fill="#667085" letter-spacing="4">VOICE AI &amp; TELEPHONY PLATFORM</text>
        </svg>''')

    # 2. Color on Dark
    with open(os.path.join(OUTPUT_DIR, "logo_horizontal_dark.svg"), "w", encoding="utf-8") as f:
        f.write(f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1600 400" width="1600" height="400">
        {defs}
        <g transform="translate(60, 40) scale(0.33)">
            {get_symbol_svg_path("grad_orange_vibrant")}
        </g>
        <!-- Typography -->
        <text x="410" y="240" font-family="'Inter', 'Satoshi', 'Segoe UI', system-ui, sans-serif" font-size="140" font-weight="800" fill="#FFFFFF" letter-spacing="-3">DIAL<tspan fill="#FF6B00">og</tspan></text>
        <!-- DDM Badge -->
        <rect x="990" y="138" width="160" height="74" rx="14" fill="rgba(255, 107, 0, 0.15)" stroke="rgba(255, 107, 0, 0.4)" stroke-width="3"/>
        <text x="1070" y="190" font-family="'Inter', 'Satoshi', 'Segoe UI', system-ui, sans-serif" font-size="44" font-weight="800" fill="#FF7A1A" text-anchor="middle" letter-spacing="1">DDM</text>
        <text x="415" y="295" font-family="'Inter', 'Segoe UI', system-ui, sans-serif" font-size="28" font-weight="600" fill="#94A3B8" letter-spacing="4">VOICE AI &amp; TELEPHONY PLATFORM</text>
        </svg>''')

    # 3. Mono Black
    with open(os.path.join(OUTPUT_DIR, "logo_horizontal_mono_black.svg"), "w", encoding="utf-8") as f:
        f.write(f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1600 400" width="1600" height="400">
        {defs}
        <g transform="translate(60, 40) scale(0.33)">
            {get_symbol_svg_path(mono_color="#111827")}
        </g>
        <text x="410" y="240" font-family="'Inter', 'Satoshi', 'Segoe UI', system-ui, sans-serif" font-size="140" font-weight="800" fill="#111827" letter-spacing="-3">DIALog</text>
        <rect x="940" y="138" width="160" height="74" rx="14" fill="#F3F4F6" stroke="#D1D5DB" stroke-width="3"/>
        <text x="1020" y="190" font-family="'Inter', 'Satoshi', 'Segoe UI', system-ui, sans-serif" font-size="44" font-weight="800" fill="#111827" text-anchor="middle" letter-spacing="1">DDM</text>
        <text x="415" y="295" font-family="'Inter', 'Segoe UI', system-ui, sans-serif" font-size="28" font-weight="600" fill="#4B5563" letter-spacing="4">VOICE AI &amp; TELEPHONY PLATFORM</text>
        </svg>''')

    # 4. Mono White
    with open(os.path.join(OUTPUT_DIR, "logo_horizontal_mono_white.svg"), "w", encoding="utf-8") as f:
        f.write(f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1600 400" width="1600" height="400">
        {defs}
        <g transform="translate(60, 40) scale(0.33)">
            {get_symbol_svg_path(mono_color="#FFFFFF")}
        </g>
        <text x="410" y="240" font-family="'Inter', 'Satoshi', 'Segoe UI', system-ui, sans-serif" font-size="140" font-weight="800" fill="#FFFFFF" letter-spacing="-3">DIALog</text>
        <rect x="940" y="138" width="160" height="74" rx="14" fill="rgba(255, 255, 255, 0.15)" stroke="rgba(255, 255, 255, 0.3)" stroke-width="3"/>
        <text x="1020" y="190" font-family="'Inter', 'Satoshi', 'Segoe UI', system-ui, sans-serif" font-size="44" font-weight="800" fill="#FFFFFF" text-anchor="middle" letter-spacing="1">DDM</text>
        <text x="415" y="295" font-family="'Inter', 'Segoe UI', system-ui, sans-serif" font-size="28" font-weight="600" fill="#E5E7EB" letter-spacing="4">VOICE AI &amp; TELEPHONY PLATFORM</text>
        </svg>''')

    # C) 800x200 Compact Horizontal Logo
    # 1. Color
    with open(os.path.join(OUTPUT_DIR, "logo_compact_color.svg"), "w", encoding="utf-8") as f:
        f.write(f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 200" width="800" height="200">
        {defs}
        <g transform="translate(30, 20) scale(0.17)">
            {get_symbol_svg_path("grad_orange")}
        </g>
        <text x="210" y="128" font-family="'Inter', 'Satoshi', 'Segoe UI', system-ui, sans-serif" font-size="82" font-weight="800" fill="#111827" letter-spacing="-2">DIAL<tspan fill="#FF6B00">og</tspan></text>
        <rect x="540" y="70" width="105" height="46" rx="10" fill="#FFF4EB" stroke="#FFE4CC" stroke-width="2"/>
        <text x="592" y="103" font-family="'Inter', 'Satoshi', 'Segoe UI', system-ui, sans-serif" font-size="27" font-weight="800" fill="#FF6B00" text-anchor="middle" letter-spacing="1">DDM</text>
        </svg>''')

    # 2. Dark
    with open(os.path.join(OUTPUT_DIR, "logo_compact_dark.svg"), "w", encoding="utf-8") as f:
        f.write(f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 200" width="800" height="200">
        {defs}
        <g transform="translate(30, 20) scale(0.17)">
            {get_symbol_svg_path("grad_orange_vibrant")}
        </g>
        <text x="210" y="128" font-family="'Inter', 'Satoshi', 'Segoe UI', system-ui, sans-serif" font-size="82" font-weight="800" fill="#FFFFFF" letter-spacing="-2">DIAL<tspan fill="#FF6B00">og</tspan></text>
        <rect x="540" y="70" width="105" height="46" rx="10" fill="rgba(255, 107, 0, 0.15)" stroke="rgba(255, 107, 0, 0.4)" stroke-width="2"/>
        <text x="592" y="103" font-family="'Inter', 'Satoshi', 'Segoe UI', system-ui, sans-serif" font-size="27" font-weight="800" fill="#FF7A1A" text-anchor="middle" letter-spacing="1">DDM</text>
        </svg>''')


# --------------------------------------------------------------------------
# 2. RENDER CRISP RASTER PNGS (Using Pillow ImageDraw with Supersampling)
# --------------------------------------------------------------------------

def draw_symbol_raster(draw, offset_x, offset_y, scale, color_tuple, bg_fill_color=None):
    # Scale factor
    def sx(x): return offset_x + x * scale
    def sy(y): return offset_y + y * scale
    def s(v): return v * scale

    # Outer D Outline: Pill/Arc contours
    stroke_w = int(max(2, 56 * scale))

    # Center voice bars
    bars = [
        (360, 320, 46, 180, 23),
        (445, 240, 46, 340, 23),
        (530, 290, 46, 240, 23),
        (615, 350, 46, 120, 23)
    ]

    # Draw Outer D shape using curved segments
    # Outer arc
    bbox = [sx(180), sy(50), sx(870), sy(750)]
    draw.arc(bbox, start=-90, end=90, fill=color_tuple, width=stroke_w)

    # Top line
    draw.line([sx(250), sy(50), sx(525), sy(50)], fill=color_tuple, width=stroke_w)
    # Bottom line
    draw.line([sx(320), sy(750), sx(525), sy(750)], fill=color_tuple, width=stroke_w)
    # Left vertical stem / dialogue connection
    draw.line([sx(220), sy(100), sx(220), sy(620)], fill=color_tuple, width=stroke_w)
    # Speech tail
    draw.line([sx(320), sy(750), sx(200), sy(870)], fill=color_tuple, width=stroke_w)
    draw.line([sx(200), sy(870), sx(220), sy(620)], fill=color_tuple, width=stroke_w)

    # Rounded voice bars
    for (bx, by, bw, bh, br) in bars:
        x0, y0, x1, y1 = sx(bx), sy(by), sx(bx + bw), sy(by + bh)
        draw.rounded_rectangle([x0, y0, x1, y1], radius=s(br), fill=color_tuple)

    # Pulse node
    nx, ny, nr = sx(530), sy(180), s(18)
    draw.ellipse([nx - nr, ny - nr, nx + nr, ny + nr], fill=color_tuple)

def create_raster_symbol(size, fg_color, bg_color=None, container_radius=None):
    # 2x supersampling for ultra-crisp antialiased edges
    scale_factor = 2
    actual_size = size * scale_factor
    img = Image.new("RGBA", (actual_size, actual_size), (0, 0, 0, 0))
    draw = ImageDraw.Draw(img)

    if bg_color:
        if container_radius:
            draw.rounded_rectangle([0, 0, actual_size - 1, actual_size - 1], radius=container_radius * scale_factor, fill=bg_color)
        else:
            draw.rectangle([0, 0, actual_size - 1, actual_size - 1], fill=bg_color)

    # Calculate symbol placement
    sym_size = 1000
    scale = (actual_size * 0.78) / sym_size
    offset_x = (actual_size - (sym_size * scale)) / 2
    offset_y = (actual_size - (sym_size * scale)) / 2

    draw_symbol_raster(draw, offset_x, offset_y, scale, fg_color)

    # Downsample with high-quality Lanczos filter
    final_img = img.resize((size, size), Image.Resampling.LANCZOS)
    return final_img

def render_all_pngs():
    # 1. Symbol 1024x1024
    img_sym_color = create_raster_symbol(1024, (255, 107, 0, 255))
    img_sym_color.save(os.path.join(OUTPUT_DIR, "logo_symbol_color.png"), "PNG")

    img_sym_dark = create_raster_symbol(1024, (255, 107, 0, 255), bg_color=(17, 24, 39, 255), container_radius=220)
    img_sym_dark.save(os.path.join(OUTPUT_DIR, "logo_symbol_dark.png"), "PNG")

    img_sym_black = create_raster_symbol(1024, (17, 24, 39, 255))
    img_sym_black.save(os.path.join(OUTPUT_DIR, "logo_symbol_mono_black.png"), "PNG")

    img_sym_white = create_raster_symbol(1024, (255, 255, 255, 255))
    img_sym_white.save(os.path.join(OUTPUT_DIR, "logo_symbol_mono_white.png"), "PNG")

    # 2. Favicons in all required sizes
    fav_sizes = [512, 180, 64, 32]
    fav_images = []
    for s in fav_sizes:
        fav_img = create_raster_symbol(s, (255, 107, 0, 255), bg_color=(17, 24, 39, 255), container_radius=int(s * 0.22))
        fav_img.save(os.path.join(OUTPUT_DIR, f"favicon-{s}.png"), "PNG")
        fav_images.append(fav_img)

    # Also save standard favicon.ico (multi-resolution 16, 32, 48, 64, 128, 256)
    ico_img_256 = create_raster_symbol(256, (255, 107, 0, 255), bg_color=(17, 24, 39, 255), container_radius=56)
    ico_img_256.save(os.path.join(OUTPUT_DIR, "favicon.ico"), format="ICO", sizes=[(16,16), (32,32), (48,48), (64,64), (128,128), (256,256)])
    ico_img_256.save(os.path.join(ROOT_DIR, "favicon.ico"), format="ICO", sizes=[(16,16), (32,32), (48,48), (64,64), (128,128), (256,256)])

    # 3. Horizontal Logos (1600x400 and 800x200)
    # We load standard TrueType fonts available in Windows or fallback
    def load_best_font(size, bold=True):
        candidates = [
            "C:/Windows/Fonts/segoeuib.ttf" if bold else "C:/Windows/Fonts/segoeui.ttf",
            "C:/Windows/Fonts/arialbd.ttf" if bold else "C:/Windows/Fonts/arial.ttf",
            "C:/Windows/Fonts/calibrib.ttf" if bold else "C:/Windows/Fonts/calibri.ttf"
        ]
        for c in candidates:
            if os.path.exists(c):
                try:
                    return ImageFont.truetype(c, size)
                except:
                    pass
        return ImageFont.load_default()

    # Full Horizontal 1600x400
    def render_horizontal_full(w, h, text_color, tag_bg, tag_border, tag_text, subtext_color, is_dark_bg=False):
        supersample = 2
        sw, sh = w * supersample, h * supersample
        img = Image.new("RGBA", (sw, sh), (0, 0, 0, 0))
        draw = ImageDraw.Draw(img)

        # Draw Symbol
        sym_scale = (sh * 0.72) / 1000
        offset_x = int(60 * supersample)
        offset_y = int((sh - (1000 * sym_scale)) / 2)
        draw_symbol_raster(draw, offset_x, offset_y, sym_scale, (255, 107, 0, 255))

        # Typography
        f_main = load_best_font(int(135 * supersample), bold=True)
        f_tag = load_best_font(int(42 * supersample), bold=True)
        f_sub = load_best_font(int(26 * supersample), bold=True)

        tx = int(410 * supersample)
        ty = int(120 * supersample)

        # "DIAL"
        draw.text((tx, ty), "DIAL", font=f_main, fill=text_color)
        dial_bbox = draw.textbbox((tx, ty), "DIAL", font=f_main)
        og_x = dial_bbox[2] - 4 * supersample

        # "og"
        draw.text((og_x, ty), "og", font=f_main, fill=(255, 107, 0, 255))
        og_bbox = draw.textbbox((og_x, ty), "og", font=f_main)

        # Badge DDM
        bx = og_bbox[2] + int(24 * supersample)
        by = ty + int(18 * supersample)
        bw = int(150 * supersample)
        bh = int(70 * supersample)
        draw.rounded_rectangle([bx, by, bx + bw, by + bh], radius=int(14 * supersample), fill=tag_bg, outline=tag_border, width=int(3 * supersample))
        
        # Center "DDM" in badge
        ddm_bbox = draw.textbbox((0, 0), "DDM", font=f_tag)
        ddm_w = ddm_bbox[2] - ddm_bbox[0]
        ddm_h = ddm_bbox[3] - ddm_bbox[1]
        dtx = bx + (bw - ddm_w) / 2
        dty = by + (bh - ddm_h) / 2 - int(4 * supersample)
        draw.text((dtx, dty), "DDM", font=f_tag, fill=tag_text)

        # Subtitle
        sub_y = ty + int(145 * supersample)
        draw.text((tx + int(4 * supersample), sub_y), "VOICE AI & TELEPHONY PLATFORM", font=f_sub, fill=subtext_color)

        return img.resize((w, h), Image.Resampling.LANCZOS)

    # 1. Horizontal Color
    h_color = render_horizontal_full(1600, 400, (17, 24, 39, 255), (255, 244, 235, 255), (255, 228, 204, 255), (255, 107, 0, 255), (102, 112, 133, 255))
    h_color.save(os.path.join(OUTPUT_DIR, "logo_horizontal_color.png"), "PNG")

    # 2. Horizontal Dark
    h_dark = render_horizontal_full(1600, 400, (255, 255, 255, 255), (255, 107, 0, 40), (255, 107, 0, 100), (255, 122, 26, 255), (148, 163, 184, 255), is_dark_bg=True)
    h_dark.save(os.path.join(OUTPUT_DIR, "logo_horizontal_dark.png"), "PNG")

    # 3. Horizontal Mono Black
    h_black = render_horizontal_full(1600, 400, (17, 24, 39, 255), (243, 244, 246, 255), (209, 213, 219, 255), (17, 24, 39, 255), (75, 85, 99, 255))
    h_black.save(os.path.join(OUTPUT_DIR, "logo_horizontal_mono_black.png"), "PNG")

    # 4. Horizontal Mono White
    h_white = render_horizontal_full(1600, 400, (255, 255, 255, 255), (255, 255, 255, 40), (255, 255, 255, 80), (255, 255, 255, 255), (229, 231, 235, 255), is_dark_bg=True)
    h_white.save(os.path.join(OUTPUT_DIR, "logo_horizontal_mono_white.png"), "PNG")

    # Compact 800x200 versions
    c_color = h_color.resize((800, 200), Image.Resampling.LANCZOS)
    c_color.save(os.path.join(OUTPUT_DIR, "logo_compact_color.png"), "PNG")

    c_dark = h_dark.resize((800, 200), Image.Resampling.LANCZOS)
    c_dark.save(os.path.join(OUTPUT_DIR, "logo_compact_dark.png"), "PNG")

    # Also update standard app logos in img/ directory for backward compatibility
    c_color.save(os.path.join(ROOT_DIR, "img", "dialog_ddm_logo.png"), "PNG")
    h_color.save(os.path.join(ROOT_DIR, "img", "logo.png"), "PNG")
    h_white.save(os.path.join(ROOT_DIR, "img", "logoWhite.png"), "PNG")
    img_sym_color.save(os.path.join(ROOT_DIR, "img", "logo-single.png"), "PNG")

if __name__ == "__main__":
    generate_svgs()
    render_all_pngs()
    print("Brand assets generated successfully in img/brand/!")
