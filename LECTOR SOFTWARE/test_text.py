import sys
import os
import fitz

base_dir = os.path.dirname(os.path.abspath(__file__))
pdf_path = os.path.join(base_dir, "storage", "temp", "uploads", "1783351932_6a4bca7c756ab_RS-05286-06-2026.pdf")

doc = fitz.open(pdf_path)
page = doc[-1]
text = page.get_text()
print(f"Extracted text length: {len(text)}")
print(f"Extracted text:\n{text[:500]}")
doc.close()
