<?php

namespace App\Services\OCR;

use Exception;

/**
 * Servicio OCR que utiliza Tesseract para extraer texto de imágenes.
 * 
 * Solo procesa la primera y última página de cada documento PDF convertido.
 * Diseñado para recibir las rutas de imágenes generadas por BatchPdfToImageService
 * y devolver el texto OCR en formato estructurado, listo para que el siguiente
 * módulo de extracción analice el contenido.
 */
class OCRService {
    private string $tesseractPath;
    private string $tessdataDir;
    private string $pythonScriptPath;

    public function __construct() {
        $baseDir = dirname(dirname(dirname(__DIR__)));

        // Ruta al ejecutable de Tesseract OCR
        $this->tesseractPath = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe';

        // Directorio local de tessdata con los modelos de idioma (spa, eng)
        $this->tessdataDir = $baseDir . '/storage/tessdata';

        // Script Python que ejecuta el OCR
        $this->pythonScriptPath = __DIR__ . '/ocr_reader.py';
    }

    /**
     * Procesa un resultado de conversión de BatchPdfToImageService.
     * 
     * Selecciona únicamente la primera y última imagen (página) del documento
     * y ejecuta OCR sobre ellas.
     *
     * @param array $conversionResult Resultado de BatchPdfToImageService->processAll()
     *                                con las claves: archivo, cantidad_paginas, imagenes_generadas, estado
     * @return array JSON con: archivo, primera_pagina_texto, ultima_pagina_texto, estado
     */
    public function processDocument(array $conversionResult): array {
        $archivo = $conversionResult['archivo'] ?? 'desconocido';

        // Si la conversión previa falló, propagar el error
        if (isset($conversionResult['estado']) && $conversionResult['estado'] === 'Error') {
            return [
                'archivo' => $archivo,
                'estado' => 'ERROR',
                'mensaje' => $conversionResult['error'] ?? 'La conversión de imágenes falló previamente.'
            ];
        }

        $imagenes = $conversionResult['imagenes_generadas'] ?? [];
        $totalPaginas = count($imagenes);

        if ($totalPaginas === 0) {
            return [
                'archivo' => $archivo,
                'estado' => 'ERROR',
                'mensaje' => 'No se encontraron imágenes generadas para este documento.'
            ];
        }

        // Seleccionar SOLO la primera y la última página
        $firstImagePath = $imagenes[0];
        $lastImagePath = $imagenes[$totalPaginas - 1];

        // Determinar las imágenes a procesar (evitar duplicar si es un PDF de 1 página)
        $imagesToProcess = [$firstImagePath];
        if ($totalPaginas > 1) {
            $imagesToProcess[] = $lastImagePath;
        }

        try {
            // Ejecutar OCR vía script Python
            $ocrResults = $this->runOCR($imagesToProcess);

            $firstPageText = $ocrResults[0]['text'] ?? '';
            $lastPageText = $totalPaginas > 1 
                ? ($ocrResults[1]['text'] ?? '') 
                : $firstPageText;

            return [
                'archivo' => $archivo,
                'primera_pagina_texto' => $firstPageText,
                'ultima_pagina_texto' => $lastPageText,
                'estado' => 'OK'
            ];
        } catch (Exception $e) {
            return [
                'archivo' => $archivo,
                'estado' => 'ERROR',
                'mensaje' => $e->getMessage()
            ];
        }
    }

    /**
     * Ejecuta el script Python de OCR sobre las imágenes proporcionadas.
     *
     * @param array $imagePaths Lista de rutas absolutas a las imágenes PNG
     * @return array Resultados del OCR por cada imagen
     * @throws Exception Si el script falla o la respuesta no es válida
     */
    private function runOCR(array $imagePaths): array {
        // Construir el comando con las rutas de las imágenes
        $cmd = sprintf(
            'python %s %s %s',
            escapeshellarg($this->pythonScriptPath),
            escapeshellarg($this->tesseractPath),
            escapeshellarg($this->tessdataDir)
        );

        // Añadir cada ruta de imagen como argumento adicional
        foreach ($imagePaths as $imgPath) {
            $cmd .= ' ' . escapeshellarg($imgPath);
        }

        $output = [];
        $returnVar = 0;

        exec($cmd, $output, $returnVar);

        $outputStr = implode("\n", $output);
        $result = json_decode($outputStr, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Intentar convertir la codificación si hay un error UTF-8
            if (function_exists('mb_convert_encoding')) {
                $outputStrUtf8 = mb_convert_encoding($outputStr, 'UTF-8', 'auto');
            } else {
                // Fallback para PHP viejo / sin mbstring
                $outputStrUtf8 = @utf8_encode($outputStr);
            }
            $result = json_decode($outputStrUtf8, true);
        }

        if ($returnVar !== 0 || !$result || !isset($result['success']) || !$result['success']) {
            $errorDetail = '';
            if (json_last_error() !== JSON_ERROR_NONE) {
                $errorDetail = ' (Error JSON: ' . json_last_error_msg() . ')';
            }
            $rawOutputSnippet = !empty($outputStr) ? ' - Salida cruda: ' . substr($outputStr, 0, 500) : ' - Sin salida de terminal';
            $errorMsg = $result['error'] ?? 'Error desconocido durante la ejecución del OCR' . $errorDetail . $rawOutputSnippet;
            throw new Exception("Falló el OCR: " . $errorMsg);
        }

        return $result['results'] ?? [];
    }

    /**
     * Procesa un lote de resultados de BatchPdfToImageService.
     *
     * @param array $conversionResults Array de resultados de processAll()
     * @return array Array de resultados OCR por documento
     */
    public function processBatch(array $conversionResults): array {
        $ocrResults = [];

        foreach ($conversionResults as $conversionResult) {
            $ocrResults[] = $this->processDocument($conversionResult);
        }

        return $ocrResults;
    }
}
