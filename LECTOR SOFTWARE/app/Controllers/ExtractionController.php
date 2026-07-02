<?php

namespace App\Controllers;

use App\Services\PDF\BatchPdfToImageService;
use App\Services\OCR\OCRService;
use App\Services\Extraction\ResolutionExtractor;
use App\Services\File\CleanupService;
use App\Services\Debug\DebugService;
use App\Repositories\ResolutionRepository;
use App\Helpers\AppConfig;
use Exception;

/**
 * Controlador para ejecutar el flujo completo de procesamiento:
 * 1. Convertir PDFs a imágenes (BatchPdfToImageService)
 * 2. Ejecutar OCR sobre primera y última página (OCRService)
 * 3. Extraer datos estructurados del texto (ResolutionExtractor)
 * 4. Limpiar archivos temporales de procesamiento (CleanupService)
 *
 * En modo DEBUG: registra información de diagnóstico en storage/temp/debug/
 * y NO elimina archivos temporales para permitir inspección manual.
 */
class ExtractionController {
    private BatchPdfToImageService $batchService;
    private OCRService $ocrService;
    private ResolutionExtractor $extractor;
    private CleanupService $cleanupService;
    private DebugService $debugService;
    private ResolutionRepository $resolutionRepository;

    public function __construct() {
        $this->batchService = new BatchPdfToImageService();
        $this->ocrService = new OCRService();
        $this->extractor = new ResolutionExtractor();
        $this->cleanupService = new CleanupService();
        $this->debugService = new DebugService();
        $this->resolutionRepository = new ResolutionRepository();
    }

    /**
     * Procesa todos los PDFs: convierte, OCR y extrae datos.
     * Responde en JSON con los datos extraídos de cada documento.
     */
    public function handleExtract(): void {
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'error' => 'Método no permitido.'
            ]);
            return;
        }

        try {
            $payload = json_decode(file_get_contents('php://input') ?: '{}', true);
            $requestedFiles = [];

            if (is_array($payload) && isset($payload['files']) && is_array($payload['files'])) {
                foreach ($payload['files'] as $file) {
                    $name = is_array($file) ? ($file['archivo'] ?? $file['name'] ?? '') : (string) $file;

                    if ($name !== '') {
                        $requestedFiles[] = basename($name);
                    }
                }
            }

            // Paso 1: Convertir solo el lote solicitado. Si no llega lote, conservar compatibilidad.
            $conversionResults = !empty($requestedFiles)
                ? $this->batchService->processFiles($requestedFiles)
                : $this->batchService->processAll();

            if (empty($conversionResults)) {
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'No se encontraron PDFs para procesar en storage/temp/uploads/.',
                    'results' => []
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                return;
            }

            // DEBUG: Registrar resultados de conversión (copiar imágenes primera/última página)
            if ($this->debugService->isEnabled()) {
                foreach ($conversionResults as $convResult) {
                    $this->debugService->registrarConversion($convResult);
                }
            }

            // Paso 2: Ejecutar OCR sobre primera y última página de cada PDF
            // En modo DEBUG: medir tiempo de OCR por documento
            $ocrResults = [];
            foreach ($conversionResults as $convResult) {
                $ocrStart = microtime(true);
                $ocrResult = $this->ocrService->processDocument($convResult);
                $ocrTimeMs = (microtime(true) - $ocrStart) * 1000;

                $ocrResults[] = $ocrResult;

                // DEBUG: Registrar texto crudo del OCR y tiempo
                if ($this->debugService->isEnabled()) {
                    $this->debugService->registrarOCR($ocrResult, $ocrTimeMs);
                }
            }

            // Paso 3: Extraer datos estructurados del texto OCR
            $extractionResults = [];
            foreach ($ocrResults as $ocrResult) {
                $extractionResult = $this->extractor->extract($ocrResult);
                $extractionResults[] = $extractionResult;

                // DEBUG: Registrar resultados de extracción
                if ($this->debugService->isEnabled()) {
                    $this->debugService->registrarExtraccion($extractionResult);
                }
            }

            // DEBUG: Generar reporte debug.json
            if ($this->debugService->isEnabled()) {
                $this->debugService->generarReporte();
            }

            $databaseResult = $this->resolutionRepository->saveMany($extractionResults);

            // Paso 4: Limpiar archivos temporales (SOLO si DEBUG está desactivado)
            $cleanupResult = ['eliminados' => 0, 'errores' => 0];
            if (!AppConfig::isDebug()) {
                $cleanupResult = $this->cleanupService->limpiarProcesamientos();
            }

            http_response_code(200);
            $response = [
                'success' => true,
                'results' => $extractionResults,
                'cleanup' => [
                    'archivos_eliminados' => $cleanupResult['eliminados'],
                    'errores' => $cleanupResult['errores']
                ],
                'database' => $databaseResult
            ];

            // Indicar al frontend que el modo debug está activo
            if (AppConfig::isDebug()) {
                $response['debug_mode'] = true;
                $response['cleanup']['omitida'] = 'Limpieza omitida por modo DEBUG activo.';
            }

            echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        } catch (Exception $e) {
            // DEBUG: Registrar excepción con stack trace completo
            if ($this->debugService->isEnabled()) {
                $this->debugService->registrarExcepcion($e, 'ExtractionController::handleExtract');
                $this->debugService->generarReporte();
            }

            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }
}
