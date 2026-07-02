<?php

/**
 * Autocargador manual para clases bajo el espacio de nombres App\
 */
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = dirname(__DIR__) . '/app/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Obtener la ruta limpia de la solicitud
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Enrutador básico para APIs
if ($requestUri === '/api/upload') {
    $controller = new \App\Controllers\UploadController();
    $controller->handleUpload();
    exit;
}

if ($requestUri === '/api/convert-all') {
    $controller = new \App\Controllers\ConvertController();
    $controller->handleConvertAll();
    exit;
}

if ($requestUri === '/api/ocr') {
    $controller = new \App\Controllers\OCRController();
    $controller->handleOCR();
    exit;
}

if ($requestUri === '/api/extract') {
    $controller = new \App\Controllers\ExtractionController();
    $controller->handleExtract();
    exit;
}

if ($requestUri === '/api/export') {
    $controller = new \App\Controllers\ExportController();
    $controller->handleExport();
    exit;
}

// Servir la página estática index.html si se solicita la raíz
if ($requestUri === '/' || $requestUri === '/index.html' || $requestUri === '/index.php') {
    include __DIR__ . '/index.html';
    exit;
}

// Fallback para servir activos estáticos si se usa el servidor de desarrollo integrado de PHP
$filePath = __DIR__ . $requestUri;
if (file_exists($filePath) && !is_dir($filePath)) {
    // Resolver Mime Type correcto para recursos estáticos
    $ext = pathinfo($filePath, PATHINFO_EXTENSION);
    switch ($ext) {
        case 'css':
            header('Content-Type: text/css; charset=utf-8');
            break;
        case 'js':
            header('Content-Type: application/javascript; charset=utf-8');
            break;
        case 'png':
            header('Content-Type: image/png');
            break;
        case 'jpg':
        case 'jpeg':
            header('Content-Type: image/jpeg');
            break;
        case 'svg':
            header('Content-Type: image/svg+xml');
            break;
        case 'pdf':
            header('Content-Type: application/pdf');
            break;
        case 'xlsx':
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            break;
    }
    readfile($filePath);
    exit;
}

// 404 por defecto
http_response_code(404);
echo json_encode([
    'success' => false,
    'error' => 'Ruta no encontrada.'
]);
exit;
