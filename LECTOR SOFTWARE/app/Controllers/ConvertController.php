<?php

namespace App\Controllers;

use App\Services\PDF\BatchPdfToImageService;
use Exception;

/**
 * Controlador para procesar en lote la conversión de PDFs a imágenes.
 */
class ConvertController {
    private BatchPdfToImageService $batchService;

    public function __construct() {
        $this->batchService = new BatchPdfToImageService();
    }

    /**
     * Procesa todos los PDFs de la carpeta de subidas y responde en JSON.
     */
    public function handleConvertAll(): void {
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
            $results = $this->batchService->processAll();
            
            http_response_code(200);
            // Responder en formato JSON indicando: archivo, cantidad de páginas, imágenes generadas, estado
            echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'error' => $e->getMessage()
            ]);
        }
    }
}
