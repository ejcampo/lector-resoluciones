<?php

/**
 * Configuración central de la aplicación.
 *
 * Para activar el modo DEBUG, cambiar DEBUG_MODE a true.
 * En modo DEBUG:
 *   - No se eliminan los archivos temporales (uploads, images, ocr)
 *   - Se guardan copias de las imágenes y textos OCR en storage/temp/debug/
 *   - Se genera un archivo debug.json con información detallada de cada documento
 *   - Los errores se registran con stack trace completo en debug.log
 */

return [
    'DEBUG_MODE' => true,
];
