"""
Rebuild the globe's Earth textures from the NASA Blue Marble originals.

    python scripts/make_earth_textures.py

Downloads the two public-domain source images, processes them, and writes
assets/earth-day.b64 and assets/earth-night.b64 as data: URIs ready for
build_portal.py to inject. See assets/README.md for why data URIs and why the
night image needs the blue basemap stripped out of it.

Only needs re-running if the sources change or the sizes need revisiting; the
committed .b64 files are the build inputs.
"""

import base64
import io
import urllib.request
from pathlib import Path

import numpy as np
from PIL import Image, ImageEnhance

HERE = Path(__file__).resolve().parent
ASSETS = HERE.parent / "assets"

DAY_URL = "https://eoimages.gsfc.nasa.gov/images/imagerecords/57000/57752/land_shallow_topo_2048.jpg"
NIGHT_URL = "https://eoimages.gsfc.nasa.gov/images/imagerecords/55000/55167/earth_lights_lrg.jpg"

DAY_SIZE = (1024, 512)
NIGHT_SIZE = (768, 384)


def fetch(url):
    req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0"})
    with urllib.request.urlopen(req, timeout=120) as r:
        return Image.open(io.BytesIO(r.read())).convert("RGB")


def as_data_uri(im, quality):
    buf = io.BytesIO()
    im.save(buf, "JPEG", quality=quality, optimize=True, subsampling=0)
    return "data:image/jpeg;base64," + base64.b64encode(buf.getvalue()).decode()


def build_day():
    im = fetch(DAY_URL).resize(DAY_SIZE, Image.LANCZOS)
    im = ImageEnhance.Color(im).enhance(1.18)
    im = ImageEnhance.Contrast(im).enhance(1.06)
    return as_data_uri(im, 78)


def build_night():
    im = fetch(NIGHT_URL).resize(NIGHT_SIZE, Image.LANCZOS)
    a = np.asarray(im).astype(np.float32)
    # The source has a dark-blue basemap beneath the lights. It sits almost
    # entirely in the blue channel, so averaging red+green isolates the lit
    # cities; without this the whole night side renders as a purple haze.
    lights = np.clip(((a[:, :, 0] + a[:, :, 1]) / 2.0 - 7.0) * 2.7, 0, 255)
    warm = np.array([1.0, 0.88, 0.66])          # sodium-lamp white
    out = (lights[:, :, None] * warm[None, None, :]).clip(0, 255).astype(np.uint8)
    return as_data_uri(Image.fromarray(out), 62)


if __name__ == "__main__":
    ASSETS.mkdir(parents=True, exist_ok=True)
    for name, fn in (("earth-day.b64", build_day), ("earth-night.b64", build_night)):
        uri = fn()
        (ASSETS / name).write_text(uri, encoding="utf-8")
        print("%-16s %6.1f KB" % (name, len(uri) / 1024))
