<?php

namespace App\Controllers;

use App\Services\Excel\ExcelExportService;
use Exception;

/**
 * Controlador para gestionar la exportación de resultados a Excel.
 *
 * Recibe los datos de extracción directamente del frontend (JSON)
 * y genera un archivo .xlsx para descarga inmediata.
 * No utiliza base de datos.
 */
class ExportController {
    private ExcelExportService $excelService;

    public function __construct() {
        $this->excelService = new ExcelExportService();
    }

    /**
     * Genera y descarga un archivo Excel con los resultados de extracción.
     *
     * Espera recibir un JSON con la estructura:
     * {
     *   "results": [
     *     { "archivo": "", "numero_resolucion": "", "primer_parrafo": "", "firmante": "", "estado": "" },
     *     ...
     *   ]
     * }
     */
    public function handleExport(): void {
        // Validar método HTTP
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'error' => 'Método no permitido. Debe utilizar una petición POST.'
            ]);
            return;
        }

        try {
            // Leer el cuerpo JSON de la solicitud
            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);

            if (!$data || !isset($data['results']) || !is_array($data['results'])) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'No se recibieron resultados válidos para exportar.'
                ]);
                return;
            }

            $results = $data['results'];

            if (empty($results)) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'La lista de resultados está vacía. No hay datos para exportar.'
                ]);
                return;
            }

            // Generar el archivo Excel
            $exportResult = $this->excelService->export($results);

            if (!$exportResult['success']) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => $exportResult['message']
                ]);
                return;
            }

            // Enviar el archivo directamente como descarga
            $this->excelService->descargar($exportResult['filepath'], $exportResult['filename']);

        } catch (\Throwable $e) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Error inesperado al exportar: ' . $e->getMessage()
            ]);
        }
    }
}
