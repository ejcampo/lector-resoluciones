<?php

namespace App\Controllers;

use App\Services\PDF\BatchPdfToImageService;
use App\Services\OCR\OCRService;
use Exception;

/**
 * Controlador para ejecutar el flujo completo:
 * 1. Convertir PDFs a imágenes (BatchPdfToImageService)
 * 2. Ejecutar OCR solo en primera y última página (OCRService)
 */
class OCRController {
    private BatchPdfToImageService $batchService;
    private OCRService $ocrService;

    public function __construct() {
        $this->batchService = new BatchPdfToImageService();
        $this->ocrService = new OCRService();
    }

    /**
     * Procesa todos los PDFs: los convierte a imágenes y ejecuta OCR.
     * Responde en JSON con el texto de la primera y última página.
     */
    public function handleOCR(): void {
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
            // Paso 1: Convertir todos los PDFs a imágenes
            $conversionResults = $this->batchService->processAll();

            if (empty($conversionResults)) {
                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'No se encontraron PDFs para procesar en storage/temp/uploads/.',
                    'results' => []
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                return;
            }

            // Paso 2: Ejecutar OCR sobre primera y última página de cada PDF
            $ocrResults = $this->ocrService->processBatch($conversionResults);

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'results' => $ocrResults
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }
}
