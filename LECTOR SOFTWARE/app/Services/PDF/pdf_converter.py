import fitz  # PyMuPDF
import sys
import os
import json

def convert_pdf_to_images(pdf_path, output_dir):
    try:
        # Verificar que el archivo PDF exista
        if not os.path.exists(pdf_path):
            return {"success": False, "error": f"El archivo PDF no existe en la ruta: {pdf_path}"}
        
        # Crear la carpeta de imágenes si no existe
        os.makedirs(output_dir, exist_ok=True)
        
        # Abrir el documento
        doc = fitz.open(pdf_path)
        base_name = os.path.splitext(os.path.basename(pdf_path))[0]
        
        # Si tiene el prefijo único generado por el cargador (timestamp_uniqid_originalName)
        # lo removemos para que el nombre de la imagen sea limpio.
        parts = base_name.split('_')
        if len(parts) >= 3:
            original_base_name = '_'.join(parts[2:])
        else:
            original_base_name = base_name

        # Quitar la extensión .pdf en caso de que esté duplicada
        if original_base_name.lower().endswith('.pdf'):
            original_base_name = original_base_name[:-4]

        generated_images = []
        
        # Recorrer cada página
        for page_num in range(len(doc)):
            page = doc[page_num]
            
            # Establecer resolución a 300 DPI (72 DPI es el valor estándar)
            # Factor de escala = 300 / 72 = 4.16666667
            zoom = 300 / 72
            mat = fitz.Matrix(zoom, zoom)
            pix = page.get_pixmap(matrix=mat, alpha=False)
            
            # Formato de nombre: nombreArchivo_pagina_X.png
            img_name = f"{original_base_name}_pagina_{page_num + 1}.png"
            img_path = os.path.abspath(os.path.join(output_dir, img_name))
            
            # Guardar la imagen en alta calidad
            pix.save(img_path)
            generated_images.append(img_path)
            
        doc.close()
        
        return {
            "success": True,
            "archivo": os.path.basename(pdf_path),
            "original_name": original_base_name,
            "paginas": len(generated_images),
            "imagenes": generated_images,
            "estado": "Completado"
        }
    except Exception as e:
        return {"success": False, "error": str(e)}

if __name__ == "__main__":
    if len(sys.argv) < 3:
        print(json.dumps({"success": False, "error": "Argumentos insuficientes. Uso: python pdf_converter.py <pdf_path> <output_dir>"}))
        sys.exit(1)
        
    pdf_path = sys.argv[1]
    output_dir = sys.argv[2]
    
    result = convert_pdf_to_images(pdf_path, output_dir)
    print(json.dumps(result))
