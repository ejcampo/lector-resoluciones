import sys
import os
from PIL import Image

base_dir = os.path.dirname(os.path.abspath(__file__))

sig_05285 = os.path.join(base_dir, "public", "signatures", "firma_RS-05285-06-2026.jpg")
sig_05286 = os.path.join(base_dir, "public", "signatures", "firma_RS-05286-06-2026.jpg")

img = Image.open(sig_05285)
print(f"05285 signature size: {img.size}")
img.close()

img = Image.open(sig_05286)
print(f"05286 signature size: {img.size}")
img.close()

pdf_dir = os.path.join(base_dir, "storage", "temp", "images")
for f in os.listdir(pdf_dir):
    if f.endswith(".jpg"):
        img = Image.open(os.path.join(pdf_dir, f))
        print(f"Original image {f} size: {img.size}")
        img.close()
