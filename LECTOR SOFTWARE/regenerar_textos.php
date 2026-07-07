<?php

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require $file;
});

use App\Database\DatabaseConnection;
use App\Services\OCR\OCRService;
use App\Services\Extraction\ResolutionExtractor;

try {
    $pdo = DatabaseConnection::get();
    
    // Obtener todos los archivos en la DB
    $stmt = $pdo->query("SELECT archivo FROM resoluciones_extraidas");
    $archivos = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $ocrService = new OCRService();
    $extractor = new ResolutionExtractor();

    foreach ($archivos as $archivo) {
        echo "Reprocesando: $archivo\n";
        
        // Asumiendo que el PDF está en temp/uploads
        // Tenemos que buscar el archivo real (con el prefijo de timestamp)
        $uploadsDir = __DIR__ . '/storage/temp/uploads/';
        $pdfReal = '';
        foreach (scandir($uploadsDir) as $file) {
            if (str_ends_with($file, $archivo)) {
                $pdfReal = $file;
                break;
            }
        }

        if (empty($pdfReal)) {
            echo "  No se encontró el PDF en temp/uploads\n";
            continue;
        }

        // Buscar imágenes
        $imagesDir = __DIR__ . '/storage/temp/images/';
        $prefijoImagen = pathinfo($pdfReal, PATHINFO_FILENAME);
        $imagenesGeneradas = [];
        foreach (scandir($imagesDir) as $img) {
            if (strpos($img, $prefijoImagen) === 0 && str_ends_with($img, '.jpg')) {
                $imagenesGeneradas[] = $imagesDir . $img;
            }
        }
        
        sort($imagenesGeneradas); // Asegurar orden de páginas

        if (empty($imagenesGeneradas)) {
             echo "  No se encontraron imágenes.\n";
             continue;
        }

        $conversionResult = [
            'archivo' => $archivo,
            'cantidad_paginas' => count($imagenesGeneradas),
            'imagenes_generadas' => $imagenesGeneradas,
            'estado' => 'Completado'
        ];

        // 1. Extraer texto OCR
        $ocrResult = $ocrService->processDocument($conversionResult);
        
        // 2. Extraer datos (Firmante)
        $datosExtraidos = $extractor->extract($ocrResult);

        if ($datosExtraidos['estado'] === 'OK') {
            $firmanteNuevo = $datosExtraidos['firmante'];
            
            // Actualizar DB
            $stmtUpdate = $pdo->prepare("UPDATE resoluciones_extraidas SET firmante = :firmante WHERE archivo = :archivo");
            $stmtUpdate->execute([
                ':firmante' => $firmanteNuevo,
                ':archivo' => $archivo
            ]);
            
            if (empty($firmanteNuevo)) {
                echo "  Firmante extraido: VACÍO (Mostrará rayita)\n";
            } else {
                echo "  Firmante extraido: $firmanteNuevo\n";
            }
        }
    }
    echo "¡Proceso terminado!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
