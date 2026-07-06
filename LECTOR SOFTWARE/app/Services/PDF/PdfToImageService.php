<?php

namespace App\Services\PDF;

use Exception;

/**
 * Servicio para convertir documentos PDF en imágenes PNG individuales por página.
 */
class PdfToImageService {
    private string $uploadsDir;
    private string $imagesDir;
    private string $pythonScriptPath;

    public function __construct() {
        $baseDir = dirname(dirname(dirname(__DIR__)));
        $this->uploadsDir = $baseDir . '/storage/temp/uploads/';
        $this->imagesDir = $baseDir . '/storage/temp/images/';
        $this->pythonScriptPath = __DIR__ . '/pdf_converter.py';

        // Asegurar la existencia de las carpetas temporales
        if (!file_exists($this->uploadsDir)) {
            mkdir($this->uploadsDir, 0777, true);
        }
        if (!file_exists($this->imagesDir)) {
            mkdir($this->imagesDir, 0777, true);
        }
    }

    /**
     * Convierte un archivo PDF cargado a imágenes.
     *
     * @param string $pdfFileName Nombre del archivo PDF (ubicado en storage/temp/uploads/)
     * @param string $mode Modo de renderizado: 'first_last' (solo primera y última página,
     *                     optimizado para OCR) o 'all' (todas las páginas)
     * @return array Resumen de la conversión
     * @throws Exception
     */
    public function convert(string $pdfFileName, string $mode = 'first_last'): array {
        $pdfPath = $this->uploadsDir . $pdfFileName;

        if (!file_exists($pdfPath)) {
            throw new Exception("El archivo PDF '" . $pdfFileName . "' no se encuentra en el almacenamiento temporal.");
        }

        // Construir comando de ejecución seguro para llamar al script de Python
        $cmd = sprintf(
            'python %s %s %s %s',
            escapeshellarg($this->pythonScriptPath),
            escapeshellarg($pdfPath),
            escapeshellarg($this->imagesDir),
            escapeshellarg($mode)
        );

        $output = [];
        $returnVar = 0;
        
        // Ejecutar el script
        exec($cmd, $output, $returnVar);

        $outputStr = implode("\n", $output);
        $result = json_decode($outputStr, true);

        // Validar resultados de la ejecución
        if ($returnVar !== 0 || !$result || !$result['success']) {
            $errorMsg = $result['error'] ?? 'Error desconocido durante la ejecución del renderizador.';
            throw new Exception("Fallo al convertir el archivo '" . $pdfFileName . "': " . $errorMsg);
        }

        return [
            'archivo' => $result['archivo'],
            'original_name' => $result['original_name'],
            'cantidad_paginas' => $result['paginas'],
            'imagenes_generadas' => $result['imagenes'],
            'estado' => $result['estado']
        ];
    }
}
