<?php

namespace App\Services\Extraction;

/**
 * Servicio de extracción inteligente de datos a partir de texto OCR.
 *
 * Extrae de documentos de resolución:
 *   - Número de resolución
 *   - Primer párrafo jurídico
 *   - Nombre del firmante
 *
 * Cada extracción se implementa en una función independiente para facilitar
 * el mantenimiento y la depuración. No utiliza IA ni expresiones regulares
 * complejas. Todas las funciones son deterministas y basadas en búsqueda
 * de patrones simples con limpieza de artefactos OCR.
 */
class ResolutionExtractor {

    /**
     * Procesa un resultado de OCRService y extrae los datos estructurados.
     *
     * @param array $ocrResult Con claves: archivo, primera_pagina_texto, ultima_pagina_texto
     * @return array Con claves: archivo, numero_resolucion, primer_parrafo, firmante, estado
     */
    public function extract(array $ocrResult): array {
        $archivo = $ocrResult['archivo'] ?? 'desconocido';

        // Si el OCR falló previamente, propagar el error
        if (isset($ocrResult['estado']) && $ocrResult['estado'] === 'ERROR') {
            return [
                'archivo' => $archivo,
                'numero_resolucion' => '',
                'primer_parrafo' => '',
                'firmante' => '',
                'estado' => 'ERROR',
                'mensaje' => $ocrResult['mensaje'] ?? 'El OCR falló previamente.'
            ];
        }

        $primeraPagina = $ocrResult['primera_pagina_texto'] ?? '';
        $ultimaPagina = $ocrResult['ultima_pagina_texto'] ?? '';

        // Limpiar el texto OCR antes de procesar
        $primeraPaginaLimpia = $this->limpiarTextoOCR($primeraPagina);
        $ultimaPaginaLimpia = $this->limpiarTextoOCR($ultimaPagina);

        // Extraer cada campo con funciones independientes
        $numeroResolucion = $this->extraerNumeroResolucion($primeraPaginaLimpia);
        $primerParrafo = $this->extraerPrimerParrafo($primeraPaginaLimpia);
        $firmante = $this->extraerFirmante($ultimaPaginaLimpia);

        return [
            'archivo' => $archivo,
            'numero_resolucion' => $numeroResolucion,
            'primer_parrafo' => $primerParrafo,
            'firmante' => $firmante,
            'estado' => 'OK'
        ];
    }

    /**
     * Procesa un lote de resultados OCR.
     *
     * @param array $ocrResults Array de resultados de OCRService->processBatch()
     * @return array Array de resultados de extracción
     */
    public function extractBatch(array $ocrResults): array {
        $results = [];

        foreach ($ocrResults as $ocrResult) {
            $results[] = $this->extract($ocrResult);
        }

        return $results;
    }

    // =========================================================================
    // FUNCIONES INDEPENDIENTES DE EXTRACCIÓN
    // =========================================================================

    /**
     * Extrae el número de resolución del texto de la primera página.
     *
     * Busca patrones como:
     *   - N° 05285-06-2026
     *   - N 05285-06-2026
     *   - N.° 052 85 - 06 - 2026
     *   - Nro. 05285 -06-2026
     *   - Num. 05285-06-2026
     *
     * Limpia espacios adicionales producidos por el OCR y devuelve
     * el formato normalizado: 05285-06-2026
     *
     * @param string $texto Texto limpio de la primera página
     * @return string Número de resolución normalizado o cadena vacía
     */
    public function extraerNumeroResolucion(string $texto): string {
        // Lista de indicadores ordenados por prioridad y especificidad
        $indicadores = [
            'RESOLUCIÓN NÚMERO', 'RESOLUCION NUMERO',
            'RESOLUCIÓN N°', 'RESOLUCION N°',
            'RESOLUCIÓN N.°', 'RESOLUCION N.°',
            'RESOLUCIÓN Nro', 'RESOLUCION Nro',
            'RESOLUCIÓN Num', 'RESOLUCION Num',
            'RESOLUCIÓN No', 'RESOLUCION No',
            'DECRETO NÚMERO', 'DECRETO NUMERO',
            'DECRETO N°', 'DECRETO No', 'DECRETO Nro', 'DECRETO Num',
            'NÚMERO', 'NUMERO',
            'N.°', 'N°', 'Nro.', 'Nro', 'Num.', 'Num', 'N °', 'No.', 'No ',
            'RESOLUCIÓN', 'RESOLUCION', 'DECRETO'
        ];

        $posIndicador = false;
        $longitudIndicador = 0;

        foreach ($indicadores as $indicador) {
            $pos = stripos($texto, $indicador);
            if ($pos !== false) {
                $posIndicador = $pos;
                $longitudIndicador = strlen($indicador);
                break;
            }
        }

        if ($posIndicador === false) {
            // Intentar buscar simplemente "N " seguido de dígitos
            $posIndicador = $this->buscarIndicadorNumero($texto);
            if ($posIndicador === false) {
                return '';
            }
            $longitudIndicador = 2; // Longitud de "N "
        }

        // Extraer el fragmento después del indicador (máximo 40 caracteres)
        $fragmentoDespues = substr($texto, $posIndicador + $longitudIndicador, 40);
        $fragmentoDespues = ltrim($fragmentoDespues);

        // Extraer solo los caracteres que conforman el número de resolución:
        // dígitos, guiones y espacios (los espacios son artefactos OCR)
        $numeroSucio = '';
        $inicioEncontrado = false;

        for ($i = 0; $i < strlen($fragmentoDespues); $i++) {
            $char = $fragmentoDespues[$i];

            if (ctype_digit($char)) {
                $inicioEncontrado = true;
                $numeroSucio .= $char;
            } elseif ($inicioEncontrado && ($char === '-' || $char === ' ' || $char === ':' || $char === '.' || $char === '/')) {
                $numeroSucio .= $char;
            } elseif ($inicioEncontrado) {
                // Terminamos al encontrar un carácter que no es parte del número
                break;
            }
        }

        if (empty($numeroSucio)) {
            return '';
        }

        // Limpiar: eliminar espacios alrededor de guiones y espacios internos entre dígitos
        $numeroLimpio = $this->limpiarNumeroResolucion($numeroSucio);

        return $numeroLimpio;
    }

    /**
     * Extrae el primer párrafo jurídico del texto de la primera página.
     *
     * Busca el texto comprendido entre "LA SECRETARIA" y "CONSIDERANDO".
     * Mantiene el texto completo en una sola línea.
     *
     * @param string $texto Texto limpio de la primera página
     * @return string Primer párrafo extraído o cadena vacía
     */
    public function extraerPrimerParrafo(string $texto): string {
        // Buscar delimitador de inicio (variantes posibles por OCR)
        $inicioMarcadores = [
            'LA SECRETARIA',
            'LA SECRET ARIA',
            'LA SECRETAR IA',
            'EL SECRETARIO',
            'EL SECRET ARIO',
            'LA DIRECTORA',
            'EL DIRECTOR',
            'POR EL CUAL',
            'POR MEDIO DEL CUAL',
            'EL GOBERNADOR',
            'LA GOBERNADORA',
        ];

        $posInicio = false;
        $marcadorUsado = '';

        foreach ($inicioMarcadores as $marcador) {
            $pos = stripos($texto, $marcador);
            if ($pos !== false) {
                $posInicio = $pos;
                $marcadorUsado = $marcador;
                break;
            }
        }

        if ($posInicio === false) {
            return '';
        }

        // Buscar delimitador de fin
        $finMarcadores = [
            'CONSIDERANDO',
            'CONSIDER ANDO',
            'CONSIDE RANDO',
        ];

        $posFin = false;

        foreach ($finMarcadores as $marcador) {
            $pos = stripos($texto, $marcador, $posInicio);
            if ($pos !== false) {
                $posFin = $pos;
                break;
            }
        }

        if ($posFin === false) {
            return '';
        }

        // Extraer el fragmento entre ambos marcadores (incluyendo el marcador de inicio)
        $parrafo = substr($texto, $posInicio, $posFin - $posInicio);

        // Limpiar el párrafo
        $parrafo = $this->limpiarParrafo($parrafo);

        return $parrafo;
    }

    /**
     * Extrae el nombre del firmante de la última página.
     *
     * Busca la línea inmediatamente anterior al cargo "Secretaria de Educación"
     * (o variantes con errores OCR). El nombre del firmante se encuentra
     * justo encima de esa línea.
     *
     * @param string $texto Texto limpio de la última página
     * @return string Nombre del firmante o cadena vacía
     */
    public function extraerFirmante(string $texto): string {
        // Dividir el texto en líneas
        $lineas = explode("\n", $texto);

        // Limpiar líneas vacías y de solo espacios
        $lineasLimpias = [];
        foreach ($lineas as $linea) {
            $lineaTrimmed = trim($linea);
            if (!empty($lineaTrimmed)) {
                $lineasLimpias[] = $lineaTrimmed;
            }
        }

        // Buscar la línea que contiene el cargo del firmante.
        // Hacemos la búsqueda de ABAJO hacia ARRIBA (reverse) para encontrar 
        // la firma real al final del documento y evitar coincidencias falsas
        // dentro del texto de los artículos (ej. "Secretaria de Educación" en un párrafo).
        $cargoMarcadores = [
            'Secretaria de Educacion',
            'Secretario de Educacion',
            'Secretaria de Educación',
            'Secretario de Educación',
            'SECRETARIA DE EDUCACION',
            'SECRETARIO DE EDUCACION',
            'SECRETARIA DE EDUCACIÓN',
            'SECRETARIO DE EDUCACIÓN',
            'Secret aria de Educacion',
            'Secre taria de Educacion',
            'Secretaria de Ed',
            'Secretario de Ed',
            'SECRETARIA DE ED',
            'SECRETARIO DE ED',
            'Directora General',
            'Director General',
            'DIRECTORA GENERAL',
            'DIRECTOR GENERAL',
            'Gobernador del Departamento',
            'GOBERNADOR DEL DEPARTAMENTO',
            'Gobernador',
            'Gobernadora',
            'GOBERNADOR',
            'GOBERNADORA',
        ];

        $indiceCargo = -1;
        $totalLineas = count($lineasLimpias);

        // Buscar desde el final de la página hacia arriba
        for ($i = $totalLineas - 1; $i >= 0; $i--) {
            // No buscar más arriba del 60% inferior de la página para evitar falsos positivos
            if ($i < $totalLineas - 30) {
                break;
            }

            foreach ($cargoMarcadores as $marcador) {
                if (stripos($lineasLimpias[$i], $marcador) !== false) {
                    $indiceCargo = $i;
                    break 2;
                }
            }
        }

        if ($indiceCargo <= 0) {
            return '';
        }

        // El nombre está en la línea inmediatamente anterior al cargo
        $lineaNombre = $lineasLimpias[$indiceCargo - 1];

        // Limpiar el nombre extraído
        $nombreLimpio = $this->limpiarNombreFirmante($lineaNombre);

        // Validar que parece un nombre (no es una línea de firma manuscrita o basura)
        if (!$this->esNombreValido($nombreLimpio)) {
            // Intentar con la línea anterior si existe
            if ($indiceCargo >= 2) {
                $lineaAlternativa = $lineasLimpias[$indiceCargo - 2];
                $nombreAlternativo = $this->limpiarNombreFirmante($lineaAlternativa);
                if ($this->esNombreValido($nombreAlternativo)) {
                    return $nombreAlternativo;
                }
            }
            return '';
        }

        return $nombreLimpio;
    }

    // =========================================================================
    // FUNCIONES DE LIMPIEZA
    // =========================================================================

    /**
     * Limpia el texto OCR eliminando artefactos comunes.
     *
     * @param string $texto Texto crudo del OCR
     * @return string Texto limpio
     */
    public function limpiarTextoOCR(string $texto): string {
        // Reemplazar tabulaciones por espacios
        $texto = str_replace("\t", ' ', $texto);

        // Normalizar retornos de carro
        $texto = str_replace("\r\n", "\n", $texto);
        $texto = str_replace("\r", "\n", $texto);

        // Eliminar caracteres de control no imprimibles (excepto newline)
        $limpio = '';
        for ($i = 0; $i < strlen($texto); $i++) {
            $ord = ord($texto[$i]);
            // Mantener: printable ASCII (32-126), newline (10), y caracteres UTF-8 extendidos (>127)
            if ($ord >= 32 || $ord === 10 || $ord > 127) {
                $limpio .= $texto[$i];
            }
        }
        $texto = $limpio;

        // Eliminar caracteres extraños comunes de OCR
        $caracteresBasura = ['|', '\\', '~', '`', '{', '}', '[', ']', '<', '>', '©', '®', '™', '¢', '£', '¥'];
        $texto = str_replace($caracteresBasura, '', $texto);

        // Reducir múltiples espacios consecutivos a uno solo
        while (strpos($texto, '  ') !== false) {
            $texto = str_replace('  ', ' ', $texto);
        }

        // Reducir múltiples saltos de línea a máximo dos
        while (strpos($texto, "\n\n\n") !== false) {
            $texto = str_replace("\n\n\n", "\n\n", $texto);
        }

        return trim($texto);
    }

    /**
     * Limpia el número de resolución eliminando espacios producidos por OCR.
     *
     * Convierte "052 85 - 06 - 2026" en "05285-06-2026".
     *
     * @param string $numero Número sucio con posibles espacios
     * @return string Número limpio en formato XXXXX-XX-XXXX
     */
    private function limpiarNumeroResolucion(string $numero): string {
        // Reemplazar dos puntos, puntos o slashes por guiones (para corregir errores de lectura en escaneados)
        $numero = str_replace([':', '.', '/'], '-', $numero);

        // Eliminar espacios alrededor de guiones: "85 - 06" -> "85-06"
        while (strpos($numero, ' -') !== false) {
            $numero = str_replace(' -', '-', $numero);
        }
        while (strpos($numero, '- ') !== false) {
            $numero = str_replace('- ', '-', $numero);
        }

        // Separar por guiones para procesar cada segmento
        $segmentos = explode('-', $numero);
        $segmentosLimpios = [];

        foreach ($segmentos as $segmento) {
            // Eliminar espacios dentro de cada segmento numérico
            // "052 85" -> "05285"
            $segmentoLimpio = str_replace(' ', '', trim($segmento));
            if ($segmentoLimpio !== '') {
                $segmentosLimpios[] = $segmentoLimpio;
            }
        }

        // Reconstruir con guiones
        $resultado = implode('-', $segmentosLimpios);

        // Eliminar guión final si quedó
        $resultado = rtrim($resultado, '-');

        return $resultado;
    }

    /**
     * Limpia el párrafo jurídico extraído.
     *
     * @param string $parrafo Texto crudo del párrafo
     * @return string Párrafo limpio en una sola línea
     */
    private function limpiarParrafo(string $parrafo): string {
        // Reemplazar saltos de línea por espacios
        $parrafo = str_replace("\n", ' ', $parrafo);

        // Corregir separaciones incorrectas de palabras producidas por OCR
        // Ejemplo: "resolu ción" -> detectar y unir palabras cortadas
        $parrafo = $this->corregirSeparacionesPalabras($parrafo);

        // Reducir múltiples espacios a uno solo
        while (strpos($parrafo, '  ') !== false) {
            $parrafo = str_replace('  ', ' ', $parrafo);
        }

        // Eliminar espacios antes de signos de puntuación
        $signos = [',', '.', ';', ':', ')', '!', '?'];
        foreach ($signos as $signo) {
            $parrafo = str_replace(' ' . $signo, $signo, $parrafo);
        }

        // Eliminar espacios después de paréntesis de apertura
        $parrafo = str_replace('( ', '(', $parrafo);

        return trim($parrafo);
    }

    /**
     * Limpia el nombre del firmante eliminando artefactos OCR.
     *
     * @param string $nombre Texto crudo de la línea del nombre
     * @return string Nombre limpio
     */
    private function limpiarNombreFirmante(string $nombre): string {
        // Eliminar caracteres de firma manuscrita (puntos, guiones bajos, asteriscos repetidos)
        $nombre = trim($nombre);

        // Eliminar secuencias de puntos (firmas: ........)
        while (strpos($nombre, '..') !== false) {
            $nombre = str_replace('..', '', $nombre);
        }

        // Eliminar secuencias de guiones bajos (firmas: ______)
        while (strpos($nombre, '__') !== false) {
            $nombre = str_replace('__', '', $nombre);
        }

        // Eliminar secuencias de asteriscos
        while (strpos($nombre, '**') !== false) {
            $nombre = str_replace('**', '', $nombre);
        }

        // Eliminar secuencias de guiones largos (—— o --)
        while (strpos($nombre, '--') !== false) {
            $nombre = str_replace('--', '', $nombre);
        }

        // Eliminar caracteres numéricos sueltos (artefactos OCR de firmas)
        $nombreLimpio = '';
        $palabras = explode(' ', $nombre);
        foreach ($palabras as $palabra) {
            $palabra = trim($palabra);
            // Descartar "palabras" que son solo números o un carácter suelto no alfabético
            if (empty($palabra)) {
                continue;
            }
            if (ctype_digit($palabra)) {
                continue;
            }
            if (strlen($palabra) === 1 && !ctype_alpha($palabra)) {
                continue;
            }
            $nombreLimpio .= $palabra . ' ';
        }

        $nombreLimpio = trim($nombreLimpio);

        // Corregir separaciones de palabras producidas por OCR
        $nombreLimpio = $this->corregirSeparacionesPalabras($nombreLimpio);

        // Reducir múltiples espacios
        while (strpos($nombreLimpio, '  ') !== false) {
            $nombreLimpio = str_replace('  ', ' ', $nombreLimpio);
        }

        return $nombreLimpio;
    }

    /**
     * Valida que una cadena parece un nombre de persona y no es basura OCR.
     *
     * @param string $texto Cadena a validar
     * @return bool True si parece un nombre válido
     */
    private function esNombreValido(string $texto): bool {
        // Debe tener al menos 3 caracteres
        if (strlen($texto) < 3) {
            return false;
        }

        // Debe contener al menos una letra
        $tieneLetras = false;
        for ($i = 0; $i < strlen($texto); $i++) {
            if (ctype_alpha($texto[$i])) {
                $tieneLetras = true;
                break;
            }
        }
        if (!$tieneLetras) {
            return false;
        }

        // No debe ser mayoritariamente números o símbolos
        $letras = 0;
        $total = 0;
        for ($i = 0; $i < strlen($texto); $i++) {
            $char = $texto[$i];
            if ($char !== ' ') {
                $total++;
                if (ctype_alpha($char) || ord($char) > 127) {
                    $letras++;
                }
            }
        }

        // Al menos el 60% debe ser letras
        if ($total > 0 && ($letras / $total) < 0.6) {
            return false;
        }

        // Debe tener al menos dos palabras (nombre y apellido)
        $palabras = array_filter(explode(' ', $texto), function ($p) {
            return strlen(trim($p)) > 0;
        });
        $numPalabras = count($palabras);
        if ($numPalabras < 2) {
            return false;
        }

        // Un nombre normal no tiene más de 6-7 palabras
        if ($numPalabras > 7) {
            return false;
        }

        // Si contiene palabras típicas del cuerpo de la resolución, rechazar
        $palabrasProhibidas = [
            'ARTICULO', 'ARTÍCULO', 'RESOLUCION', 'RESOLUCIÓN', 
            'CONSIDERANDO', 'PARAGRAFO', 'PUBLIQUESE', 'PUBLÍQUESE',
            'COMUNIQUESE', 'COMUNÍQUESE', 'CUMPLASE', 'CÚMPLASE',
            'DADA', 'POPAYAN', 'POPAYÁN', 'ESTABLECIMIENTO', 'EDUCATIVO'
        ];
        $textoMayusculas = mb_strtoupper($texto, 'UTF-8');
        foreach ($palabrasProhibidas as $prohibida) {
            if (mb_strpos($textoMayusculas, $prohibida, 0, 'UTF-8') !== false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Corrige separaciones incorrectas de palabras producidas por OCR.
     *
     * Detecta patrones donde una letra minúscula sola o un fragmento corto
     * está separado incorrectamente de la palabra que le sigue.
     * Ejemplo: "resolu ción" -> "resolución"
     *
     * @param string $texto Texto con posibles separaciones incorrectas
     * @return string Texto con separaciones corregidas
     */
    private function corregirSeparacionesPalabras(string $texto): string {
        // Patrones de corrección simples:
        // Si una "palabra" de 1-2 caracteres minúsculos está entre dos partes,
        // y la siguiente palabra empieza con minúscula, probablemente es una separación OCR.
        $palabras = explode(' ', $texto);
        $resultado = [];
        $i = 0;

        while ($i < count($palabras)) {
            $actual = $palabras[$i];

            // Si la palabra actual termina con una letra y la siguiente empieza con
            // una minúscula, y la siguiente tiene solo 1-3 caracteres que parecen
            // continuación (como "ción", "ión", "ble"), intentar unir
            if ($i + 1 < count($palabras)) {
                $siguiente = $palabras[$i + 1];

                // Verificar si parece una palabra cortada por OCR:
                // La palabra actual termina en letra y la siguiente es un fragmento corto
                // que empieza con minúscula
                $ultimoCharActual = substr($actual, -1);
                $primerCharSiguiente = substr($siguiente, 0, 1);

                if (
                    strlen($siguiente) <= 4
                    && strlen($actual) >= 2
                    && ctype_alpha($ultimoCharActual)
                    && ctype_lower($primerCharSiguiente)
                    && ctype_alpha($primerCharSiguiente)
                ) {
                    // Unir las palabras
                    $resultado[] = $actual . $siguiente;
                    $i += 2;
                    continue;
                }
            }

            $resultado[] = $actual;
            $i++;
        }

        return implode(' ', $resultado);
    }

    /**
     * Busca la posición de un indicador de número de resolución
     * cuando no se encuentra "N°" explícitamente.
     *
     * Busca "N " seguido de un dígito como fallback.
     *
     * @param string $texto Texto donde buscar
     * @return int|false Posición encontrada o false
     */
    private function buscarIndicadorNumero(string $texto): int|false {
        // Buscar "N " seguido de un dígito
        $pos = 0;
        while (($pos = stripos($texto, 'N ', $pos)) !== false) {
            // Verificar que después del espacio viene un dígito
            $posDigito = $pos + 2;
            // Saltar espacios adicionales
            while ($posDigito < strlen($texto) && $texto[$posDigito] === ' ') {
                $posDigito++;
            }
            if ($posDigito < strlen($texto) && ctype_digit($texto[$posDigito])) {
                return $pos;
            }
            $pos++;
        }

        return false;
    }
}
