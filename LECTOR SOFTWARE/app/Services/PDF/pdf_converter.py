import sys
import os
import json

try:
    from PIL import Image
except ImportError:
    Image = None

try:
    import fitz  # PyMuPDF
except ImportError:
    fitz = None

try:
    import pypdfium2 as pdfium
except ImportError:
    pdfium = None


# DPI de renderizado: 200 es suficiente para OCR con Tesseract y mucho más
# rápido que 300. Si se necesita mayor calidad, subir a 250 o 300.
RENDER_DPI = 200
RENDER_ZOOM = RENDER_DPI / 72

# Formato de salida: JPEG es significativamente más rápido de escribir que PNG
# y Tesseract lo acepta sin problemas.
OUTPUT_FORMAT = "jpg"
JPEG_QUALITY = 85


def clean_original_name(pdf_path):
    base_name = os.path.splitext(os.path.basename(pdf_path))[0]
    return base_name


def image_output_path(output_dir, original_base_name, page_num):
    img_name = f"{original_base_name}_pagina_{page_num + 1}.{OUTPUT_FORMAT}"
    return os.path.abspath(os.path.join(output_dir, img_name))


def select_pages(total_pages, mode):
    """Devuelve los índices de página a renderizar según el modo solicitado.

    Modos:
        'all'        – todas las páginas (comportamiento original)
        'first_last' – solo primera y última (por defecto, optimizado para OCR)
    """
    if mode == "all" or total_pages <= 2:
        return list(range(total_pages))
    # first_last
    return [0, total_pages - 1]


def convert_with_fitz(pdf_path, output_dir, original_base_name, page_indices):
    generated_images = []
    doc = fitz.open(pdf_path)
    mat = fitz.Matrix(RENDER_ZOOM, RENDER_ZOOM)

    for page_num in page_indices:
        page = doc[page_num]
        pix = page.get_pixmap(matrix=mat, alpha=False)
        img_path = image_output_path(output_dir, original_base_name, page_num)

        if OUTPUT_FORMAT == "jpg":
            pix.save(img_path, jpg_quality=JPEG_QUALITY)
        else:
            pix.save(img_path)

        generated_images.append(img_path)

    doc.close()
    return generated_images


def convert_with_pdfium(pdf_path, output_dir, original_base_name, page_indices):
    generated_images = []
    doc = pdfium.PdfDocument(pdf_path)

    for page_num in page_indices:
        page = doc[page_num]
        bitmap = page.render(scale=RENDER_ZOOM)
        image = bitmap.to_pil()
        img_path = image_output_path(output_dir, original_base_name, page_num)

        if OUTPUT_FORMAT == "jpg":
            image.save(img_path, quality=JPEG_QUALITY, optimize=False)
        else:
            image.save(img_path)

        generated_images.append(img_path)

        if hasattr(page, "close"):
            page.close()

    if hasattr(doc, "close"):
        doc.close()

    return generated_images


def get_total_pages(pdf_path):
    """Obtiene el número total de páginas de forma ligera."""
    if fitz is not None:
        doc = fitz.open(pdf_path)
        n = len(doc)
        doc.close()
        return n
    elif pdfium is not None:
        doc = pdfium.PdfDocument(pdf_path)
        n = len(doc)
        if hasattr(doc, "close"):
            doc.close()
        return n
    return 0


def crop_signature(image_path, signatures_dir, original_base_name):
    """Recorta el tercio inferior (30%) de la última página del PDF.

    Este fragmento generalmente contiene la firma del funcionario.
    La imagen recortada se guarda en public/signatures/ con un nombre
    derivado del documento original.

    Returns:
        str: Nombre del archivo de firma generado (solo el nombre, no la ruta completa).
    """
    if Image is None:
        return ""

    try:
        img = Image.open(image_path)
        width, height = img.size
        # Recortar el 30% inferior
        crop_top = int(height * 0.70)
        cropped = img.crop((0, crop_top, width, height))

        sig_filename = f"firma_{original_base_name}.jpg"
        sig_path = os.path.join(signatures_dir, sig_filename)
        cropped.save(sig_path, "JPEG", quality=90)
        cropped.close()
        img.close()

        return sig_filename
    except Exception:
        return ""


def convert_pdf_to_images(pdf_path, output_dir, mode="first_last"):
    try:
        if not os.path.exists(pdf_path):
            return {"success": False, "error": f"El archivo PDF no existe en la ruta: {pdf_path}"}

        if fitz is None and pdfium is None:
            return {
                "success": False,
                "error": "Dependencia faltante: instale PyMuPDF o pypdfium2 para convertir PDFs."
            }

        os.makedirs(output_dir, exist_ok=True)
        original_base_name = clean_original_name(pdf_path)

        total_pages = get_total_pages(pdf_path)
        page_indices = select_pages(total_pages, mode)

        if fitz is not None:
            generated_images = convert_with_fitz(pdf_path, output_dir, original_base_name, page_indices)
        else:
            generated_images = convert_with_pdfium(pdf_path, output_dir, original_base_name, page_indices)

        # --- Recortar firma de la última página ---
        signature_path = ""
        if generated_images:
            last_image_path = generated_images[-1]
            # Carpeta de firmas: public/signatures/ relativa al proyecto
            project_root = os.path.dirname(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))
            signatures_dir = os.path.join(project_root, "public", "signatures")
            os.makedirs(signatures_dir, exist_ok=True)
            signature_path = crop_signature(last_image_path, signatures_dir, original_base_name)

        return {
            "success": True,
            "archivo": os.path.basename(pdf_path),
            "original_name": original_base_name,
            "paginas": total_pages,
            "paginas_renderizadas": len(generated_images),
            "imagenes": generated_images,
            "imagen_firma": signature_path,
            "estado": "Completado"
        }
    except Exception as e:
        return {"success": False, "error": str(e)}


if __name__ == "__main__":
    if len(sys.argv) < 3:
        print(json.dumps({
            "success": False,
            "error": "Argumentos insuficientes. Uso: python pdf_converter.py <pdf_path> <output_dir> [mode]"
        }))
        sys.exit(1)

    pdf_path = sys.argv[1]
    output_dir = sys.argv[2]
    mode = sys.argv[3] if len(sys.argv) >= 4 else "first_last"

    result = convert_pdf_to_images(pdf_path, output_dir, mode)
    print(json.dumps(result))
