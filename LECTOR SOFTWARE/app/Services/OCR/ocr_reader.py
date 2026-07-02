"""
Script de OCR que recibe rutas de imágenes y devuelve el texto extraído.
Utiliza Tesseract OCR con soporte para español.

Uso:
    python ocr_reader.py <tesseract_path> <tessdata_dir> <image_path_1> [image_path_2]

Salida:
    JSON con el texto extraído de cada imagen.
"""

import sys
import os
import json

try:
    import pytesseract
    from PIL import Image
except ImportError as e:
    print(json.dumps({
        "success": False,
        "error": f"Dependencia faltante: {str(e)}. Instale con: pip install pytesseract Pillow"
    }))
    sys.exit(1)


def extract_text(image_path, tesseract_path, tessdata_dir):
    """
    Extrae texto de una imagen usando Tesseract OCR.
    
    Args:
        image_path: Ruta absoluta a la imagen PNG.
        tesseract_path: Ruta al ejecutable de Tesseract.
        tessdata_dir: Ruta al directorio tessdata con los modelos de idioma.
    
    Returns:
        str: Texto extraído de la imagen.
    """
    if not os.path.exists(image_path):
        raise FileNotFoundError(f"La imagen no existe: {image_path}")

    # Configurar la ruta del ejecutable de Tesseract
    pytesseract.pytesseract.tesseract_cmd = tesseract_path

    # Establecer la variable de entorno TESSDATA_PREFIX para que Tesseract
    # encuentre los archivos de idioma correctamente en Windows
    os.environ['TESSDATA_PREFIX'] = os.path.normpath(tessdata_dir)

    # Abrir imagen con Pillow
    img = Image.open(image_path)

    # Configuración de Tesseract para alta precisión
    # --psm 3: Segmentación automática de página completa
    # --oem 3: Motor LSTM + Legacy combinado
    custom_config = '--psm 3 --oem 3'

    # Ejecutar OCR con español + inglés como idiomas
    text = pytesseract.image_to_string(
        img,
        lang='spa+eng',
        config=custom_config
    )

    return text.strip()


def main():
    if len(sys.argv) < 4:
        print(json.dumps({
            "success": False,
            "error": "Argumentos insuficientes. Uso: python ocr_reader.py <tesseract_path> <tessdata_dir> <image_1> [image_2]"
        }))
        sys.exit(1)

    tesseract_path = sys.argv[1]
    tessdata_dir = sys.argv[2]
    image_paths = sys.argv[3:]

    results = []

    for img_path in image_paths:
        try:
            text = extract_text(img_path, tesseract_path, tessdata_dir)
            results.append({
                "image": os.path.basename(img_path),
                "path": img_path,
                "text": text,
                "success": True
            })
        except Exception as e:
            results.append({
                "image": os.path.basename(img_path),
                "path": img_path,
                "text": "",
                "success": False,
                "error": str(e)
            })

    print(json.dumps({
        "success": True,
        "results": results
    }))


if __name__ == "__main__":
    main()
