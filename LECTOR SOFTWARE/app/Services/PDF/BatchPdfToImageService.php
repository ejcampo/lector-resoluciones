<?php

namespace App\Services\PDF;

use Exception;

/**
 * Servicio para procesar en lote todos los PDFs almacenados y convertirlos a imágenes.
 */
class BatchPdfToImageService {
    private string $uploadsDir;
    private PdfToImageService $pdfToImageService;

    public function __construct() {
        $baseDir = dirname(dirname(dirname(__DIR__)));
        $this->uploadsDir = $baseDir . '/storage/temp/uploads/';
        $this->pdfToImageService = new PdfToImageService();
    }

    /**
     * Procesa todos los PDFs de la carpeta uploads.
     *
     * @return array
     */
    public function processAll(): array {
        $results = [];

        if (!file_exists($this->uploadsDir)) {
            return $results;
        }

        $files = scandir($this->uploadsDir);
        if ($files === false) {
            return $results;
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if ($ext !== 'pdf') {
                continue;
            }

            try {
                // El PdfToImageService ya implementa:
                // - Crear carpeta temporal storage/temp/images/
                // - Resolución 300 DPI
                // - Formato de nombre de archivo
                // - Devolver las rutas de las imágenes generadas para el OCR
                $conversion = $this->pdfToImageService->convert($file);
                
                $results[] = [
                    'archivo' => $conversion['archivo'],
                    'cantidad_paginas' => $conversion['cantidad_paginas'],
                    'imagenes_generadas' => $conversion['imagenes_generadas'],
                    'estado' => $conversion['estado']
                ];
            } catch (Exception $e) {
                // Si ocurre un error, devolver mensaje indicando el archivo que falló
                $results[] = [
                    'archivo' => $file,
                    'error' => "Falló al convertir: " . $e->getMessage(),
                    'estado' => 'Error'
                ];
            }
        }

        return $results;
    }
}
