<?php

namespace App\Services\File;

use App\DTO\FileDTO;
use Exception;

/**
 * Servicio encargado de gestionar las operaciones con archivos físicos,
 * validación de tipos y almacenamiento temporal.
 */
class FileService {
    private string $uploadDir;

    public function __construct() {
        // Carpeta de almacenamiento temporal
        $this->uploadDir = dirname(dirname(dirname(__DIR__))) . '/storage/temp/uploads/';
        if (!file_exists($this->uploadDir)) {
            mkdir($this->uploadDir, 0777, true);
        }
    }

    /**
     * Valida, procesa y almacena un archivo subido.
     *
     * @param array $file Estructura de archivo proveniente de $_FILES
     * @return FileDTO
     * @throws Exception
     */
    public function upload(array $file): FileDTO {
        // 1. Validar errores intrínsecos de carga
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Error del servidor al subir el archivo '" . $file['name'] . "'. Código de error: " . $file['error']);
        }

        // 2. Validar tamaño mínimo (mayor a 0 bytes)
        if ($file['size'] <= 0) {
            throw new Exception("El archivo '" . $file['name'] . "' está vacío.");
        }

        // 3. Validar extensión de archivo
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($extension !== 'pdf') {
            throw new Exception("El archivo '" . $file['name'] . "' no tiene una extensión .pdf permitida.");
        }

        // 4. Validar firma mágica del archivo (Bytes mágicos %PDF)
        if (!$this->isValidPdfHeader($file['tmp_name'])) {
            throw new Exception("El archivo '" . $file['name'] . "' no es un archivo PDF real (encabezado no coincide).");
        }

        // 5. Generar un nombre único para evitar colisiones en almacenamiento temporal
        $safeName = $this->sanitizeFileName($file['name']);
        $uniqueName = time() . '_' . uniqid() . '_' . $safeName;
        $targetPath = $this->uploadDir . $uniqueName;

        // 6. Mover archivo al directorio temporal de destino
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new Exception("Error al mover el archivo '" . $file['name'] . "' al almacenamiento temporal.");
        }

        return new FileDTO($file['name'], $targetPath, $file['size']);
    }

    /**
     * Comprueba los primeros 4 bytes del archivo buscando la cabecera '%PDF'.
     */
    private function isValidPdfHeader(string $filePath): bool {
        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            return false;
        }
        $header = fread($handle, 4);
        fclose($handle);
        return $header === '%PDF';
    }

    /**
     * Limpia el nombre del archivo de caracteres no seguros.
     */
    private function sanitizeFileName(string $filename): string {
        // Reemplazar espacios y caracteres no alfanuméricos por guiones bajos, manteniendo puntos y guiones
        return preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
    }
}
