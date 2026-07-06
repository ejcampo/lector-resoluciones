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


def find_smart_crop_fraction(img_path):
    """
    Analiza la densidad de pixeles verticales de una imagen escaneada 
    para detectar dónde termina realmente el contenido del documento, 
    ignorando bordes negros o ruido de escáner en la parte inferior.
    """
    try:
        img = Image.open(img_path).convert('L')
        orig_width, orig_height = img.size
        
        # Reducir imagen para procesamiento ultra rápido
        scale = 400 / orig_width
        height = int(orig_height * scale)
        img = img.resize((400, height))
        
        threshold = 200
        row_density = []
        for y in range(height):
            # Muestrear pixeles intercalados
            dark_pixels = sum(1 for x in range(0, 400, 2) if img.getpixel((x, y)) < threshold)
            row_density.append(dark_pixels)

        # Suavizar el perfil
        smoothed = []
        window = 3
        for i in range(height):
            start = max(0, i - window)
            end = min(height, i + window + 1)
            smoothed.append(sum(row_density[start:end]) / (end - start))

        img.close()

        in_content = False
        content_spans = []
        start_y = 0
        for y in range(height):
            if smoothed[y] > 3:
                if not in_content:
                    in_content = True
                    start_y = y
            else:
                if in_content:
                    in_content = False
                    content_spans.append((start_y, y - 1))
        if in_content:
            content_spans.append((start_y, height - 1))
            
        if not content_spans:
            return 0.35

        min_span = int(height * 0.02)
        valid_spans = [s for s in content_spans if (s[1] - s[0]) > min_span]
        
        if not valid_spans:
            return 0.35
            
        # Descartar ruido aislado en el fondo (ej. linea negra del escáner)
        while len(valid_spans) > 1:
            last_span = valid_spans[-1]
            prev_span = valid_spans[-2]
            gap = last_span[0] - prev_span[1]
            
            if gap > int(height * 0.15) and last_span[0] > int(height * 0.80):
                valid_spans.pop()
            else:
                break
                
        real_content_bottom = valid_spans[-1][1]
        
        # El bloque de firma suele ser el ultimo 35% del contenido real
        signature_height = int(height * 0.35)
        crop_start_y = max(0, real_content_bottom - signature_height)
        
        return crop_start_y / height
    except Exception:
        return 0.35

def crop_signature(image_path, signatures_dir, original_base_name, pdf_path=None, last_page_index=None):
    """Recorta el area de firma inteligentemente basandose en densidad visual."""
    if Image is None:
        return ""

    try:
        img = Image.open(image_path)
        width, height = img.size

        # Usar algoritmo de densidad visual
        crop_fraction = find_smart_crop_fraction(image_path)

        crop_top = int(height * crop_fraction)
        cropped = img.crop((0, crop_top, width, height))

        sig_filename = f"firma_{original_base_name}.jpg"
        sig_path = os.path.join(signatures_dir, sig_filename)
        cropped.save(sig_path, "JPEG", quality=92)
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

        # --- Recortar firma de la última página con deteccion inteligente ---
        signature_path = ""
        if generated_images:
            last_image_path = generated_images[-1]
            last_page_index = page_indices[-1]
            # Carpeta de firmas: public/signatures/ relativa al proyecto
            project_root = os.path.dirname(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))
            signatures_dir = os.path.join(project_root, "public", "signatures")
            os.makedirs(signatures_dir, exist_ok=True)
            signature_path = crop_signature(
                last_image_path,
                signatures_dir,
                original_base_name,
                pdf_path=pdf_path,
                last_page_index=last_page_index
            )

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
