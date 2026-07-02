<?php

namespace App\Services\Excel;

use Exception;

/**
 * Servicio de exportación a Excel (.xlsx) sin dependencias externas.
 *
 * Genera archivos XLSX válidos utilizando el estándar Open XML (ZIP + XML).
 * Incluye encabezado con estilo (negrita, fondo de color) y ajuste automático
 * del ancho de columnas basado en el contenido.
 *
 * No utiliza base de datos: recibe los datos directamente como un array
 * de resultados del procesamiento actual.
 */
class ExcelExportService {
    private string $excelDir;

    public function __construct() {
        $baseDir = dirname(dirname(dirname(__DIR__)));
        $this->excelDir = $baseDir . '/storage/temp/excel/';

        if (!file_exists($this->excelDir)) {
            mkdir($this->excelDir, 0777, true);
        }
    }

    /**
     * Genera un archivo Excel (.xlsx) a partir de los resultados de extracción.
     *
     * @param array $results Array de resultados del procesamiento (de ExtractionController)
     * @return array Con claves: success, filename, filepath, message
     */
    public function export(array $results): array {
        // Generar nombre del archivo con fecha y hora actual
        $timestamp = date('Y-m-d_H-i');
        $filename = "Resoluciones_{$timestamp}.xlsx";
        $filepath = $this->excelDir . $filename;

        try {
            // Limpiar archivos Excel anteriores para evitar acumulación
            $this->limpiarExcelAnteriores();

            // Preparar los datos para la hoja de cálculo
            $headers = ['Archivo', 'Número de resolución', 'Primer párrafo', 'Firmante', 'Estado'];
            $rows = $this->prepararFilas($results);

            // Calcular anchos de columna óptimos
            $anchos = $this->calcularAnchosColumnas($headers, $rows);

            // Generar el archivo XLSX
            $this->generarXLSX($filepath, $headers, $rows, $anchos);

            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'message' => "Archivo Excel generado exitosamente: {$filename}"
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'filename' => '',
                'filepath' => '',
                'message' => 'Error al generar el archivo Excel: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Prepara las filas de datos a partir de los resultados de extracción.
     *
     * @param array $results Resultados del procesamiento
     * @return array Filas con los valores de cada columna
     */
    private function prepararFilas(array $results): array {
        $rows = [];

        foreach ($results as $result) {
            $archivo = $result['archivo'] ?? 'Desconocido';
            // Limpiar el prefijo timestamp_uniqid_ del nombre del archivo
            $archivo = $this->limpiarNombreArchivo($archivo);

            $rows[] = [
                $archivo,
                $result['numero_resolucion'] ?? '',
                $result['primer_parrafo'] ?? '',
                $result['firmante'] ?? '',
                $result['estado'] ?? 'ERROR'
            ];
        }

        return $rows;
    }

    /**
     * Limpia el nombre del archivo eliminando el prefijo timestamp_uniqid_
     * agregado por FileService durante la carga.
     *
     * @param string $filename Nombre con prefijo (ej: 1751401234_668dc1a2e4b21_documento.pdf)
     * @return string Nombre limpio (ej: documento.pdf)
     */
    private function limpiarNombreArchivo(string $filename): string {
        $parts = explode('_', $filename);

        if (count($parts) >= 3) {
            $primerSegmento = $parts[0];
            $segundoSegmento = $parts[1];

            // Verificar si el primer segmento es un timestamp (10+ dígitos)
            $esTimestamp = (strlen($primerSegmento) >= 10) && ctype_digit($primerSegmento);

            // Verificar si el segundo segmento es un uniqid (13+ caracteres hexadecimales)
            $esUniqid = (strlen($segundoSegmento) >= 13) && ctype_xdigit($segundoSegmento);

            if ($esTimestamp && $esUniqid) {
                return implode('_', array_slice($parts, 2));
            }
        }

        return $filename;
    }

    /**
     * Calcula el ancho óptimo de cada columna basado en el contenido.
     *
     * @param array $headers Encabezados de columna
     * @param array $rows Filas de datos
     * @return array Anchos de columna en caracteres
     */
    private function calcularAnchosColumnas(array $headers, array $rows): array {
        $anchos = [];

        // Inicializar con el ancho de los encabezados
        foreach ($headers as $i => $header) {
            $anchos[$i] = mb_strlen($header) + 4; // Margen adicional
        }

        // Comparar con el contenido de cada fila
        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $cellLen = mb_strlen((string)$cell);
                // Limitar el ancho máximo para evitar columnas excesivamente anchas
                $cellLen = min($cellLen + 2, 80);
                if ($cellLen > $anchos[$i]) {
                    $anchos[$i] = $cellLen;
                }
            }
        }

        // Asegurar anchos mínimos razonables
        $anchosMinimos = [20, 22, 50, 25, 12];
        foreach ($anchosMinimos as $i => $min) {
            if (isset($anchos[$i]) && $anchos[$i] < $min) {
                $anchos[$i] = $min;
            }
        }

        return $anchos;
    }

    /**
     * Genera el archivo XLSX completo (formato Open XML).
     *
     * Un archivo .xlsx es un ZIP que contiene archivos XML con la estructura:
     *   [Content_Types].xml
     *   _rels/.rels
     *   xl/workbook.xml
     *   xl/_rels/workbook.xml.rels
     *   xl/styles.xml
     *   xl/worksheets/sheet1.xml
     *   xl/sharedStrings.xml
     *
     * @param string $filepath Ruta completa del archivo a generar
     * @param array $headers Encabezados de columna
     * @param array $rows Filas de datos
     * @param array $anchos Anchos de columna
     * @throws Exception Si no se puede crear el archivo ZIP
     */
    private function generarXLSX(string $filepath, array $headers, array $rows, array $anchos): void {
        $zip = new \ZipArchive();
        $result = $zip->open($filepath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        if ($result !== true) {
            throw new Exception("No se pudo crear el archivo ZIP para el Excel. Código: {$result}");
        }

        // Recopilar todas las cadenas únicas para el SharedStrings
        $allStrings = [];
        foreach ($headers as $h) {
            $allStrings[] = $h;
        }
        foreach ($rows as $row) {
            foreach ($row as $cell) {
                $allStrings[] = (string)$cell;
            }
        }

        // Generar cada componente XML del XLSX
        $zip->addFromString('[Content_Types].xml', $this->generarContentTypes());
        $zip->addFromString('_rels/.rels', $this->generarRels());
        $zip->addFromString('xl/workbook.xml', $this->generarWorkbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->generarWorkbookRels());
        $zip->addFromString('xl/styles.xml', $this->generarStyles());
        $zip->addFromString('xl/sharedStrings.xml', $this->generarSharedStrings($allStrings));
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->generarSheet($headers, $rows, $anchos, $allStrings));

        $zip->close();

        if (!file_exists($filepath)) {
            throw new Exception("El archivo Excel no se generó correctamente.");
        }
    }

    /**
     * Genera el XML de [Content_Types].xml
     */
    private function generarContentTypes(): string {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' .
            '<Default Extension="xml" ContentType="application/xml"/>' .
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
            '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
            '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' .
            '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>' .
            '</Types>';
    }

    /**
     * Genera el XML de _rels/.rels
     */
    private function generarRels(): string {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>' .
            '</Relationships>';
    }

    /**
     * Genera el XML de xl/workbook.xml
     */
    private function generarWorkbook(): string {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
            '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
            '<sheets>' .
            '<sheet name="Resoluciones" sheetId="1" r:id="rId1"/>' .
            '</sheets>' .
            '</workbook>';
    }

    /**
     * Genera el XML de xl/_rels/workbook.xml.rels
     */
    private function generarWorkbookRels(): string {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
            '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>' .
            '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>' .
            '</Relationships>';
    }

    /**
     * Genera el XML de xl/styles.xml con dos estilos:
     *   - Estilo 0: Celdas normales (sin formato especial)
     *   - Estilo 1: Encabezado (negrita, fondo indigo #4F46E5, texto blanco)
     */
    private function generarStyles(): string {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
            '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' .

            // Fuentes
            '<fonts count="2">' .
            '<font><sz val="11"/><name val="Calibri"/></font>' .               // Font 0: Normal
            '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>' . // Font 1: Negrita, blanco
            '</fonts>' .

            // Rellenos
            '<fills count="3">' .
            '<fill><patternFill patternType="none"/></fill>' .                  // Fill 0: Ninguno (requerido)
            '<fill><patternFill patternType="gray125"/></fill>' .               // Fill 1: Gris (requerido)
            '<fill><patternFill patternType="solid"><fgColor rgb="FF4F46E5"/></patternFill></fill>' . // Fill 2: Indigo
            '</fills>' .

            // Bordes
            '<borders count="2">' .
            '<border><left/><right/><top/><bottom/><diagonal/></border>' .      // Border 0: Sin bordes
            '<border>' .                                                         // Border 1: Borde fino gris
            '<left style="thin"><color rgb="FFD1D5DB"/></left>' .
            '<right style="thin"><color rgb="FFD1D5DB"/></right>' .
            '<top style="thin"><color rgb="FFD1D5DB"/></top>' .
            '<bottom style="thin"><color rgb="FFD1D5DB"/></bottom>' .
            '<diagonal/>' .
            '</border>' .
            '</borders>' .

            // Formatos de celda
            '<cellStyleXfs count="1">' .
            '<xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>' .
            '</cellStyleXfs>' .

            '<cellXfs count="3">' .
            '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>' .                                    // xf 0: Normal sin borde
            '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>' . // xf 1: Encabezado
            '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>' . // xf 2: Celda normal con borde
            '</cellXfs>' .

            '</styleSheet>';
    }

    /**
     * Genera el XML de xl/sharedStrings.xml con todas las cadenas del documento.
     *
     * @param array $strings Lista de todas las cadenas (headers + celdas)
     * @return string XML de SharedStrings
     */
    private function generarSharedStrings(array $strings): string {
        $count = count($strings);
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
            '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . $count . '" uniqueCount="' . $count . '">';

        foreach ($strings as $str) {
            $xml .= '<si><t>' . $this->escaparXML((string)$str) . '</t></si>';
        }

        $xml .= '</sst>';
        return $xml;
    }

    /**
     * Genera el XML de la hoja de cálculo (xl/worksheets/sheet1.xml).
     *
     * @param array $headers Encabezados de columna
     * @param array $rows Filas de datos
     * @param array $anchos Anchos de columna calculados
     * @param array $allStrings Cadenas compartidas para referenciar por índice
     * @return string XML de la hoja
     */
    private function generarSheet(array $headers, array $rows, array $anchos, array $allStrings): string {
        // Crear un mapa de cadena → índice para referencias rápidas
        $stringIndex = [];
        foreach ($allStrings as $i => $str) {
            $stringIndex[] = $str; // Usaremos búsqueda secuencial por posición
        }

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" .
            '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';

        // Congelar la fila de encabezado (sheetViews DEBE ir antes de sheetData según Open XML)
        $xml .= '<sheetViews><sheetView tabSelected="1" workbookViewId="0">';
        $xml .= '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>';
        $xml .= '</sheetView></sheetViews>';

        // Definir anchos de columna
        $xml .= '<cols>';
        foreach ($anchos as $i => $ancho) {
            $colNum = $i + 1;
            $xml .= '<col min="' . $colNum . '" max="' . $colNum . '" width="' . $ancho . '" customWidth="1"/>';
        }
        $xml .= '</cols>';

        // Datos de la hoja
        $xml .= '<sheetData>';

        // Fila de encabezado (fila 1, estilo 1 = encabezado)
        $xml .= '<row r="1" ht="28" customHeight="1">';
        $stringPos = 0;
        foreach ($headers as $colIndex => $header) {
            $colLetter = $this->indiceALetraColumna($colIndex);
            $xml .= '<c r="' . $colLetter . '1" t="s" s="1"><v>' . $stringPos . '</v></c>';
            $stringPos++;
        }
        $xml .= '</row>';

        // Filas de datos (comenzando en fila 2, estilo 2 = celda normal con borde)
        foreach ($rows as $rowIndex => $row) {
            $rowNum = $rowIndex + 2;
            $xml .= '<row r="' . $rowNum . '">';
            foreach ($row as $colIndex => $cell) {
                $colLetter = $this->indiceALetraColumna($colIndex);
                $xml .= '<c r="' . $colLetter . $rowNum . '" t="s" s="2"><v>' . $stringPos . '</v></c>';
                $stringPos++;
            }
            $xml .= '</row>';
        }

        $xml .= '</sheetData>';

        $xml .= '</worksheet>';
        return $xml;
    }

    /**
     * Convierte un índice de columna (0-based) a letra(s) de Excel.
     * 0 → A, 1 → B, ..., 25 → Z, 26 → AA
     *
     * @param int $index Índice de columna (0-based)
     * @return string Letra(s) de columna Excel
     */
    private function indiceALetraColumna(int $index): string {
        $letter = '';
        $index++;
        while ($index > 0) {
            $remainder = ($index - 1) % 26;
            $letter = chr(65 + $remainder) . $letter;
            $index = intval(($index - 1) / 26);
        }
        return $letter;
    }

    /**
     * Escapa caracteres especiales para XML válido.
     *
     * @param string $text Texto a escapar
     * @return string Texto escapado seguro para XML
     */
    private function escaparXML(string $text): string {
        // Eliminar caracteres de control que no son válidos en XML
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);

        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Elimina archivos Excel generados anteriormente para evitar acumulación.
     */
    private function limpiarExcelAnteriores(): void {
        $files = scandir($this->excelDir);
        if ($files === false) return;

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if ($ext === 'xlsx') {
                $fullPath = $this->excelDir . $file;
                if (is_file($fullPath)) {
                    unlink($fullPath);
                }
            }
        }
    }

    /**
     * Envía el archivo Excel al navegador como descarga directa.
     *
     * @param string $filepath Ruta completa del archivo .xlsx
     * @param string $filename Nombre del archivo para la descarga
     * @throws Exception Si el archivo no existe
     */
    public function descargar(string $filepath, string $filename): void {
        if (!file_exists($filepath)) {
            throw new Exception("El archivo Excel no existe: {$filename}");
        }

        // Limpiar cualquier salida previa
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Registrar eliminación del archivo Excel al finalizar la petición.
        // Se usa register_shutdown_function para garantizar la limpieza
        // incluso después de exit(), asegurando que el archivo ya fue
        // transmitido completamente al navegador.
        register_shutdown_function(function () use ($filepath) {
            if (file_exists($filepath)) {
                @unlink($filepath);
            }
        });

        // Headers para descarga de archivo Excel
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        readfile($filepath);
        exit;
    }
}
