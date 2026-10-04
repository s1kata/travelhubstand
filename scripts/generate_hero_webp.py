"""Generate responsive WebP hero images from source PNG."""
from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
HERO_DIR = ROOT / "frontend" / "window" / "img" / "hero"
CANDIDATES = [
    "e978c0767c0fe7bc778596c86b2b54f3 1.png",
    "e978c0767c0fe7bc778596c86b2b54f3%201.png",
]

source = next((HERO_DIR / name for name in CANDIDATES if (HERO_DIR / name).is_file()), None)
if source is None:
    raise SystemExit("Hero PNG not found")

img = Image.open(source).convert("RGB")
print(f"Source: {source.name} {img.size[0]}x{img.size[1]}")

for width in (640, 960, 1280, 1920):
    ratio = min(1.0, width / img.size[0])
    target = (int(img.size[0] * ratio), int(img.size[1] * ratio))
    resized = img if ratio >= 1 else img.resize(target, Image.Resampling.LANCZOS)
    out = HERO_DIR / f"home-hero-{width}.webp"
    resized.save(out, "WEBP", quality=78, method=6)
    print(f"{out.name}: {out.stat().st_size // 1024} KB")
