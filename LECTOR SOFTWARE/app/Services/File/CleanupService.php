<?php

namespace App\Services\File;

/**
 * Servicio de limpieza automática de archivos temporales.
 *
 * Elimina los archivos generados durante el procesamiento (uploads, imágenes, OCR)
 * preservando la estructura de carpetas. Si ocurre un error durante la limpieza,
 * registra el mensaje sin detener la aplicación.
 */
class CleanupService {
    private string $baseDir;

    /** @var string[] Directorios temporales a limpiar tras el procesamiento */
    private array $processingDirs = [
        'images',
        'ocr',
    ];

    /** @var string Directorio de archivos Excel generados */
    private string $excelDirName = 'excel';

    /** @var string Ruta al archivo de log de limpieza */
    private string $logFile;

    public function __construct() {
        $this->baseDir = dirname(dirname(dirname(__DIR__))) . '/storage/temp/';
        $this->logFile = dirname(dirname(dirname(__DIR__))) . '/storage/logs/cleanup.log';

        // Asegurar que el directorio de logs exista
        $logDir = dirname($this->logFile);
        if (!file_exists($logDir)) {
            mkdir($logDir, 0777, true);
        }
    }

    /**
     * Limpia todos los directorios de procesamiento (uploads, images, ocr).
     *
     * Preserva la estructura de carpetas y los archivos .gitkeep.
     * No elimina el directorio de Excel.
     *
     * @return array Resumen de la limpieza con contadores y errores
     */
    public function limpiarProcesamientos(): array {
        $totalEliminados = 0;
        $totalErrores = 0;
        $errores = [];

        foreach ($this->processingDirs as $dirName) {
            $dirPath = $this->baseDir . $dirName . '/';
            $resultado = $this->limpiarDirectorio($dirPath);

            $totalEliminados += $resultado['eliminados'];
            $totalErrores += $resultado['errores'];

            if (!empty($resultado['mensajes_error'])) {
                $errores = array_merge($errores, $resultado['mensajes_error']);
            }
        }

        // Registrar resumen en log
        if ($totalEliminados > 0 || $totalErrores > 0) {
            $this->registrarLog(
                "Limpieza de procesamiento: {$totalEliminados} archivo(s) eliminado(s), {$totalErrores} error(es)."
            );
        }

        return [
            'eliminados' => $totalEliminados,
            'errores' => $totalErrores,
            'mensajes_error' => $errores,
        ];
    }

    /**
     * Limpia el directorio de archivos Excel generados.
     *
     * Se llama después de que el usuario descarga el archivo.
     *
     * @return array Resumen de la limpieza
     */
    public function limpiarExcel(): array {
        $dirPath = $this->baseDir . $this->excelDirName . '/';
        $resultado = $this->limpiarDirectorio($dirPath);

        if ($resultado['eliminados'] > 0 || $resultado['errores'] > 0) {
            $this->registrarLog(
                "Limpieza de Excel: {$resultado['eliminados']} archivo(s) eliminado(s), {$resultado['errores']} error(es)."
            );
        }

        return $resultado;
    }

    /**
     * Ejecuta la limpieza completa: procesamiento + Excel.
     *
     * @return array Resumen total
     */
    public function limpiarTodo(): array {
        $proc = $this->limpiarProcesamientos();
        $excel = $this->limpiarExcel();

        return [
            'eliminados' => $proc['eliminados'] + $excel['eliminados'],
            'errores' => $proc['errores'] + $excel['errores'],
            'mensajes_error' => array_merge($proc['mensajes_error'], $excel['mensajes_error']),
        ];
    }

    /**
     * Elimina todos los archivos dentro de un directorio, preservando
     * la carpeta en sí y los archivos .gitkeep.
     *
     * Recorre subdirectorios recursivamente y los elimina tras vaciarlos.
     *
     * @param string $dirPath Ruta absoluta del directorio a limpiar
     * @return array ['eliminados' => int, 'errores' => int, 'mensajes_error' => string[]]
     */
    private function limpiarDirectorio(string $dirPath): array {
        $eliminados = 0;
        $errores = 0;
        $mensajesError = [];

        if (!is_dir($dirPath)) {
            return [
                'eliminados' => 0,
                'errores' => 0,
                'mensajes_error' => [],
            ];
        }

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dirPath, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST // Procesar hijos antes que padres (para poder eliminar subcarpetas)
            );

            foreach ($iterator as $item) {
                $itemPath = $item->getPathname();

                // Preservar archivos .gitkeep
                if ($item->getFilename() === '.gitkeep') {
                    continue;
                }

                try {
                    if ($item->isDir()) {
                        // Eliminar subdirectorio vacío
                        if ($this->directorioEstaVacio($itemPath)) {
                            rmdir($itemPath);
                        }
                    } else {
                        // Eliminar archivo
                        if (unlink($itemPath)) {
                            $eliminados++;
                        } else {
                            $errores++;
                            $msg = "No se pudo eliminar: {$itemPath}";
                            $mensajesError[] = $msg;
                            $this->registrarLog($msg, 'ERROR');
                        }
                    }
                } catch (\Exception $e) {
                    $errores++;
                    $msg = "Error al procesar '{$itemPath}': " . $e->getMessage();
                    $mensajesError[] = $msg;
                    $this->registrarLog($msg, 'ERROR');
                }
            }
        } catch (\Exception $e) {
            $errores++;
            $msg = "Error al recorrer directorio '{$dirPath}': " . $e->getMessage();
            $mensajesError[] = $msg;
            $this->registrarLog($msg, 'ERROR');
        }

        return [
            'eliminados' => $eliminados,
            'errores' => $errores,
            'mensajes_error' => $mensajesError,
        ];
    }

    /**
     * Verifica si un directorio está vacío (sin contar . y ..).
     *
     * @param string $dirPath Ruta del directorio
     * @return bool
     */
    private function directorioEstaVacio(string $dirPath): bool {
        $items = scandir($dirPath);
        if ($items === false) return false;

        // Filtrar . y ..
        $items = array_diff($items, ['.', '..']);
        return count($items) === 0;
    }

    /**
     * Registra un mensaje en el archivo de log de limpieza.
     *
     * @param string $mensaje Mensaje a registrar
     * @param string $nivel Nivel del log (INFO, ERROR, WARNING)
     */
    private function registrarLog(string $mensaje, string $nivel = 'INFO'): void {
        try {
            $timestamp = date('Y-m-d H:i:s');
            $linea = "[{$timestamp}] [{$nivel}] {$mensaje}" . PHP_EOL;
            file_put_contents($this->logFile, $linea, FILE_APPEND | LOCK_EX);
        } catch (\Exception $e) {
            // Silenciar errores de log para no interrumpir la aplicación
        }
    }
}
