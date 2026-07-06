"""
Regenera las firmas con nombres limpios para los documentos en la BD.
"""
import sys
import os
import subprocess

base_dir = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.join(base_dir, "app", "Services", "PDF"))

from pdf_converter import (crop_signature, clean_original_name, get_total_pages,
                           convert_with_fitz, convert_with_pdfium, select_pages,
                           image_output_path)
try:
    import fitz
except ImportError:
    fitz = None
try:
    import pypdfium2 as pdfium
except ImportError:
    pdfium = None

uploads_dir = os.path.join(base_dir, "storage", "temp", "uploads")
signatures_dir = os.path.join(base_dir, "public", "signatures")
images_dir = os.path.join(base_dir, "storage", "temp", "images")

os.makedirs(signatures_dir, exist_ok=True)
os.makedirs(images_dir, exist_ok=True)

# Limpiar TODAS las firmas antiguas
for f in os.listdir(signatures_dir):
    if f.endswith(".jpg") or f.endswith(".png"):
        os.remove(os.path.join(signatures_dir, f))

# Documentos en la BD (nombres limpios sin prefijo)
bd_docs = ["RS-05285-06-2026.pdf", "RS-05286-06-2026.pdf"]

# Buscar el PDF mas reciente para cada documento limpio
best_pdfs = {}
for filename in os.listdir(uploads_dir):
    if not filename.lower().endswith(".pdf"):
        continue
    # Extraer nombre limpio
    base = os.path.splitext(filename)[0]
    parts = base.split("_")
    if len(parts) >= 3 and parts[0].isdigit() and len(parts[0]) >= 10:
        clean = "_".join(parts[2:]) + ".pdf"
        ts = int(parts[0])
    else:
        clean = filename
        ts = 0

    if clean in bd_docs:
        if clean not in best_pdfs or ts > best_pdfs[clean][0]:
            best_pdfs[clean] = (ts, filename)

print("PDFs mas recientes encontrados:")
for doc, (ts, fname) in best_pdfs.items():
    print(f"  {doc} -> {fname}")

# Generar firma para cada uno
for doc, (ts, fname) in best_pdfs.items():
    pdf_path = os.path.join(uploads_dir, fname)
    clean_name = os.path.splitext(doc)[0]  # sin extension

    total_pages = get_total_pages(pdf_path)
    page_indices = select_pages(total_pages, "first_last")

    if fitz is not None:
        imgs = convert_with_fitz(pdf_path, images_dir, clean_name, page_indices)
    elif pdfium is not None:
        imgs = convert_with_pdfium(pdf_path, images_dir, clean_name, page_indices)
    else:
        print("ERROR: No hay libreria PDF disponible")
        break

    if imgs:
        last_img = imgs[-1]
        last_page_index = page_indices[-1]
        result = crop_signature(
            last_img, 
            signatures_dir, 
            clean_name,
            pdf_path=pdf_path,
            last_page_index=last_page_index
        )
        print(f"  Firma generada: {result}")
    else:
        print(f"  ERROR: No se generaron imagenes para {doc}")

print("\nArchivos en signatures/:")
for f in os.listdir(signatures_dir):
    print(f"  {f}")
print("Listo.")
