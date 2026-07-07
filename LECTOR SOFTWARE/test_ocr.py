import os
from PIL import Image
import pytesseract
import cv2

base_dir = os.path.dirname(os.path.abspath(__file__))
pdf_dir = os.path.join(base_dir, "storage", "temp", "images")
img_name = "1783001695_6a46725feefee_RS-05286-06-2026_pagina_7.jpg"
img_path = os.path.join(pdf_dir, img_name)

# Wait, pytesseract was not installed locally!
# Ah, earlier we saw pytesseract throw an error because `tesseract` wasn't in PATH.
# How does the PHP app do OCR?
# Let's check `pdf_to_text.py`!
