<?php

namespace App\Controllers;

use App\Services\File\FileService;
use App\Services\PDF\PdfToImageService;
use Exception;

/**
 * Controlador para gestionar la subida y validación inicial de archivos.
 */
class UploadController {
    private FileService $fileService;
    private PdfToImageService $pdfToImageService;

    public function __construct() {
        $this->fileService = new FileService();
        $this->pdfToImageService = new PdfToImageService();
    }

    /**
     * Procesa la solicitud HTTP de carga de archivos y los convierte a imágenes.
     */
    public function handleUpload(): void {
        // Establecer encabezados JSON
        header('Content-Type: application/json; charset=utf-8');

        // Validar método HTTP
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'error' => 'Método no permitido. Debe utilizar una petición POST.'
            ]);
            return;
        }

        // Validar existencia de archivos en el request
        if (empty($_FILES['files'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'No se han enviado archivos para su procesamiento.'
            ]);
            return;
        }

        $files = $_FILES['files'];
        $processedFiles = [];
        $errors = [];

        // Normalizar estructura de $_FILES si se subieron múltiples archivos
        $normalizedFiles = [];
        if (is_array($files['name'])) {
            for ($i = 0; $i < count($files['name']); $i++) {
                $normalizedFiles[] = [
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i],
                ];
            }
        } else {
            $normalizedFiles[] = $files;
        }

        // Procesar, guardar y convertir cada archivo individualmente
        foreach ($normalizedFiles as $file) {
            try {
                // 1. Guardar el PDF de forma segura
                $dto = $this->fileService->upload($file);
                
                // 2. Obtener el nombre de archivo guardado y ejecutar conversión a PNG
                $savedFileName = basename($dto->tempPath);
                $conversion = $this->pdfToImageService->convert($savedFileName);
                
                $processedFiles[] = [
                    'archivo' => $conversion['archivo'],
                    'cantidad_paginas' => $conversion['cantidad_paginas'],
                    'imagenes_generadas' => $conversion['imagenes_generadas'],
                    'estado' => $conversion['estado']
                ];
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        // Si todos los archivos fallaron la validación o conversión
        if (count($errors) > 0 && count($processedFiles) === 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'errors' => $errors
            ]);
            return;
        }

        // Responder con éxito y el detalle de archivos procesados
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'files' => $processedFiles,
            'warnings' => count($errors) > 0 ? $errors : null
        ]);
    }
}
