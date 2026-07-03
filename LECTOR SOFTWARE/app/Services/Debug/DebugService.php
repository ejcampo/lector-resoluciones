<?php

namespace App\Services\Debug;

use App\Helpers\AppConfig;

/**
 * Servicio de depuración para el pipeline de procesamiento OCR.
 *
 * Cuando DEBUG_MODE está activado en config/app.php, este servicio:
 *   - Crea storage/temp/debug/ automáticamente
 *   - Guarda copias de las imágenes de primera y última página (.png)
 *   - Guarda el texto crudo que devuelve Tesseract antes de ResolutionExtractor (.txt)
 *   - Genera debug.json con información detallada de cada documento
 *   - Registra excepciones con stack trace completo en debug.log
 *
 * No modifica la lógica existente del pipeline. Solo observa y registra.
 */
class DebugService {
    private string $debugDir;
    private string $debugLogFile;
    private bool $enabled;

    /** @var array Información de depuración acumulada por documento */
    private array $documentos = [];

    /** @var float Tiempo de inicio del pipeline completo */
    private float $pipelineStart;

    public function __construct() {
        $baseDir = dirname(dirname(dirname(__DIR__)));
        $this->debugDir = $baseDir . '/storage/temp/debug/';
        $this->debugLogFile = $baseDir . '/storage/temp/debug/debug.log';
        $this->enabled = AppConfig::isDebug();
        $this->pipelineStart = microtime(true);

        if ($this->enabled) {
            $this->prepararDirectorio();
        }
    }

    /**
     * Indica si el modo DEBUG está activado.
     *
     * @return bool
     */
    public function isEnabled(): bool {
        return $this->enabled;
    }

    /**
     * Registra los resultados de la conversión PDF → Imágenes para un documento.
     *
     * Guarda copias de la primera y última imagen generada en el directorio de debug.
     *
     * @param array $conversionResult Resultado de BatchPdfToImageService
     */
    public function registrarConversion(array $conversionResult): void {
        if (!$this->enabled) return;

        $archivo = $conversionResult['archivo'] ?? 'desconocido';
        $nombreBase = pathinfo($archivo, PATHINFO_FILENAME);

        // Inicializar entrada de documento
        $this->documentos[$archivo] = [
            'archivo' => $archivo,
            'cantidad_paginas' => $conversionResult['cantidad_paginas'] ?? 0,
            'imagenes_generadas' => count($conversionResult['imagenes_generadas'] ?? []),
            'estado_conversion' => $conversionResult['estado'] ?? 'Desconocido',
            'error_conversion' => $conversionResult['error'] ?? null,
            'tiempo_ocr_ms' => null,
            'estado_final' => null,
            'error_final' => null,
        ];

        // Copiar primera y última imagen al directorio de debug
        $imagenes = $conversionResult['imagenes_generadas'] ?? [];
        if (!empty($imagenes)) {
            $primeraImagen = $imagenes[0];
            $ultimaImagen = $imagenes[count($imagenes) - 1];
            $imgExt = pathinfo($primeraImagen, PATHINFO_EXTENSION) ?: 'jpg';

            $this->copiarArchivoSeguro(
                $primeraImagen,
                $this->debugDir . $nombreBase . '_primera_pagina.' . $imgExt
            );

            if (count($imagenes) > 1) {
                $this->copiarArchivoSeguro(
                    $ultimaImagen,
                    $this->debugDir . $nombreBase . '_ultima_pagina.' . $imgExt
                );
            } else {
                // Si solo hay una página, copiarla también como última
                $this->copiarArchivoSeguro(
                    $primeraImagen,
                    $this->debugDir . $nombreBase . '_ultima_pagina.' . $imgExt
                );
            }
        }
    }

    /**
     * Registra los resultados del OCR para un documento.
     *
     * Guarda el texto crudo de Tesseract (antes de ResolutionExtractor) en archivos .txt.
     *
     * @param array $ocrResult Resultado de OCRService::processDocument()
     * @param float $tiempoOcrMs Tiempo de ejecución del OCR en milisegundos
     */
    public function registrarOCR(array $ocrResult, float $tiempoOcrMs = 0): void {
        if (!$this->enabled) return;

        $archivo = $ocrResult['archivo'] ?? 'desconocido';
        $nombreBase = pathinfo($archivo, PATHINFO_FILENAME);

        // Actualizar la entrada del documento
        if (isset($this->documentos[$archivo])) {
            $this->documentos[$archivo]['tiempo_ocr_ms'] = round($tiempoOcrMs, 2);
        }

        // Guardar texto crudo de la primera página
        $primeraPaginaTexto = $ocrResult['primera_pagina_texto'] ?? '';
        $this->escribirArchivoSeguro(
            $this->debugDir . $nombreBase . '_primera_pagina.txt',
            $primeraPaginaTexto
        );

        // Guardar texto crudo de la última página
        $ultimaPaginaTexto = $ocrResult['ultima_pagina_texto'] ?? '';
        $this->escribirArchivoSeguro(
            $this->debugDir . $nombreBase . '_ultima_pagina.txt',
            $ultimaPaginaTexto
        );

        // Si el OCR falló, registrar el error
        if (isset($ocrResult['estado']) && $ocrResult['estado'] === 'ERROR') {
            if (isset($this->documentos[$archivo])) {
                $this->documentos[$archivo]['estado_final'] = 'ERROR';
                $this->documentos[$archivo]['error_final'] = $ocrResult['mensaje'] ?? 'Error OCR desconocido';
            }
        }
    }

    /**
     * Registra los resultados de la extracción para un documento.
     *
     * @param array $extractionResult Resultado de ResolutionExtractor::extract()
     */
    public function registrarExtraccion(array $extractionResult): void {
        if (!$this->enabled) return;

        $archivo = $extractionResult['archivo'] ?? 'desconocido';

        if (isset($this->documentos[$archivo])) {
            $this->documentos[$archivo]['estado_final'] = $extractionResult['estado'] ?? 'Desconocido';

            if (isset($extractionResult['mensaje'])) {
                $this->documentos[$archivo]['error_final'] = $extractionResult['mensaje'];
            }

            // Agregar los datos extraídos para poder compararlos con los textos crudos
            $this->documentos[$archivo]['datos_extraidos'] = [
                'numero_resolucion' => $extractionResult['numero_resolucion'] ?? '',
                'primer_parrafo' => $extractionResult['primer_parrafo'] ?? '',
                'firmante' => $extractionResult['firmante'] ?? '',
            ];
        }
    }

    /**
     * Registra una excepción con stack trace completo en debug.log.
     *
     * @param \Throwable $exception La excepción a registrar
     * @param string $contexto Contexto donde ocurrió la excepción
     */
    public function registrarExcepcion(\Throwable $exception, string $contexto = ''): void {
        if (!$this->enabled) return;

        $timestamp = date('Y-m-d H:i:s');
        $entry = "============================================================\n";
        $entry .= "[{$timestamp}] EXCEPCIÓN en: {$contexto}\n";
        $entry .= "Mensaje: {$exception->getMessage()}\n";
        $entry .= "Archivo: {$exception->getFile()}:{$exception->getLine()}\n";
        $entry .= "Stack Trace:\n{$exception->getTraceAsString()}\n";
        $entry .= "============================================================\n\n";

        $this->escribirArchivoSeguro($this->debugLogFile, $entry, true);
    }

    /**
     * Genera el archivo debug.json con toda la información recopilada.
     *
     * Debe llamarse al finalizar el pipeline de procesamiento.
     */
    public function generarReporte(): void {
        if (!$this->enabled) return;

        $tiempoTotal = round((microtime(true) - $this->pipelineStart) * 1000, 2);

        $reporte = [
            'generado_en' => date('Y-m-d H:i:s'),
            'debug_mode' => true,
            'tiempo_total_pipeline_ms' => $tiempoTotal,
            'total_documentos' => count($this->documentos),
            'documentos' => array_values($this->documentos),
        ];

        $jsonPath = $this->debugDir . 'debug.json';
        $jsonContent = json_encode(
            $reporte,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        $this->escribirArchivoSeguro($jsonPath, $jsonContent);
    }

    /**
     * Crea el directorio de debug y lo limpia de ejecuciones anteriores.
     */
    private function prepararDirectorio(): void {
        try {
            // Crear directorio si no existe
            if (!file_exists($this->debugDir)) {
                mkdir($this->debugDir, 0777, true);
            }

            // Limpiar archivos de ejecuciones anteriores (excepto debug.log que es acumulativo)
            $archivos = scandir($this->debugDir);
            if ($archivos !== false) {
                foreach ($archivos as $archivo) {
                    if ($archivo === '.' || $archivo === '..' || $archivo === 'debug.log') {
                        continue;
                    }
                    $ruta = $this->debugDir . $archivo;
                    if (is_file($ruta)) {
                        @unlink($ruta);
                    }
                }
            }
        } catch (\Exception $e) {
            // No interrumpir la aplicación por errores de debug
        }
    }

    /**
     * Copia un archivo de forma segura, sin interrumpir la aplicación si falla.
     *
     * @param string $origen Ruta del archivo origen
     * @param string $destino Ruta del archivo destino
     */
    private function copiarArchivoSeguro(string $origen, string $destino): void {
        try {
            if (file_exists($origen)) {
                copy($origen, $destino);
            }
        } catch (\Exception $e) {
            $this->registrarExcepcion($e, "Copiando archivo: {$origen} → {$destino}");
        }
    }

    /**
     * Escribe contenido en un archivo de forma segura.
     *
     * @param string $ruta Ruta del archivo
     * @param string $contenido Contenido a escribir
     * @param bool $append Si true, agrega al final del archivo en vez de sobrescribir
     */
    private function escribirArchivoSeguro(string $ruta, string $contenido, bool $append = false): void {
        try {
            $flags = LOCK_EX;
            if ($append) {
                $flags |= FILE_APPEND;
            }
            file_put_contents($ruta, $contenido, $flags);
        } catch (\Exception $e) {
            // No interrumpir la aplicación por errores de debug
        }
    }
}
