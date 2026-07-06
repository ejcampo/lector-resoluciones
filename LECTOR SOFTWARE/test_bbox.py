import os
from PIL import Image, ImageChops

base_dir = os.path.dirname(os.path.abspath(__file__))
pdf_dir = os.path.join(base_dir, "storage", "temp", "images")
img_name = "RS-05286-06-2026_pagina_7.jpg"
img_path = os.path.join(pdf_dir, img_name)

if not os.path.exists(img_path):
    img_name = "1783001695_6a46725feefee_RS-05286-06-2026_pagina_7.jpg"
    img_path = os.path.join(pdf_dir, img_name)

img = Image.open(img_path)
bg = Image.new(img.mode, img.size, img.getpixel((0,0)))
diff = ImageChops.difference(img, bg)
diff = ImageChops.add(diff, diff, 2.0, -100)
bbox = diff.getbbox()

print(f"Image size: {img.size}")
print(f"Content Bounding Box (left, upper, right, lower): {bbox}")
img.close()
