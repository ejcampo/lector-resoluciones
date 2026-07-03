"""
Script de OCR que recibe rutas de imagenes y devuelve el texto extraido.
Usa directamente el ejecutable de Tesseract OCR con soporte para espanol.

Uso:
    python ocr_reader.py <tesseract_path> <tessdata_dir> <image_path_1> [image_path_2]
"""

import sys
import os
import json
import subprocess


def extract_text(image_path, tesseract_path, tessdata_dir):
    if not os.path.exists(image_path):
        raise FileNotFoundError(f"La imagen no existe: {image_path}")

    if not os.path.exists(tesseract_path):
        raise FileNotFoundError(f"Tesseract no existe en la ruta: {tesseract_path}")

    if not os.path.isdir(tessdata_dir):
        raise FileNotFoundError(f"El directorio tessdata no existe: {tessdata_dir}")

    env = os.environ.copy()
    env["TESSDATA_PREFIX"] = os.path.normpath(tessdata_dir)

    cmd = [
        tesseract_path,
        image_path,
        "stdout",
        "--tessdata-dir",
        os.path.normpath(tessdata_dir),
        "-l",
        "spa+eng",
        "--psm",
        "3",
        "--oem",
        "3",
    ]

    completed = subprocess.run(
        cmd,
        capture_output=True,
        text=True,
        encoding="utf-8",
        errors="replace",
        env=env,
    )

    if completed.returncode != 0:
        details = completed.stderr.strip() or completed.stdout.strip() or "Sin salida de Tesseract"
        raise RuntimeError(details)

    return completed.stdout.strip()


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
