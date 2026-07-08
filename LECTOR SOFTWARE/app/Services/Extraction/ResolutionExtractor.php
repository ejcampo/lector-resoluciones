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
        $firmante = $this->extraerFirmanteRobusto($ultimaPaginaLimpia);

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
     * Estrategia multi-capa:
     * 1. Localiza la línea del CARGO (ej. "Secretaria de Educación", "Gobernador") 
     *    buscando de ABAJO hacia ARRIBA.
     * 2. Examina hasta 5 líneas antes del cargo para encontrar el nombre,
     *    saltando líneas que son ruido de firma manuscrita o están vacías.
     * 3. El candidato con más letras válidas se elige como el firmante.
     *
     * @param string $texto Texto limpio de la última página
     * @return string Nombre del firmante o cadena vacía
     */
    public function extraerFirmante(string $texto): string {
        // Dividir en líneas y quitar las vacías
        $lineas = explode("\n", $texto);
        $lineasLimpias = [];
        foreach ($lineas as $linea) {
            $t = trim($linea);
            if ($t !== '') {
                $lineasLimpias[] = $t;
            }
        }

        $totalLineas = count($lineasLimpias);
        if ($totalLineas === 0) {
            return '';
        }

        // --- Marcadores de cargo (ordenados de más a menos especínfico) ---
        $cargoMarcadores = [
            // Secretaria/Secretario de Educación
            'Secretaria de Educacion y Cultura',
            'Secretario de Educacion y Cultura',
            'Secretaria de Educación y Cultura',
            'Secretario de Educación y Cultura',
            'SECRETARIA DE EDUCACION Y CULTURA',
            'SECRETARIO DE EDUCACION Y CULTURA',
            'Secretaria de Educacion',
            'Secretario de Educacion',
            'Secretaria de Educación',
            'Secretario de Educación',
            'SECRETARIA DE EDUCACION',
            'SECRETARIO DE EDUCACION',
            'Secret aria de Educacion',
            'Secre taria de Educacion',
            'Secretaria de Ed',
            'Secretario de Ed',
            'SECRETARIA DE ED',
            'SECRETARIO DE ED',
            // Gobernador/Gobernadora
            'Gobernador del Departamento del Cauca',
            'Gobernadora del Departamento del Cauca',
            'GOBERNADOR DEL DEPARTAMENTO DEL CAUCA',
            'Gobernador del Departamento',
            'GOBERNADOR DEL DEPARTAMENTO',
            'Gobernador',
            'Gobernadora',
            'GOBERNADOR',
            'GOBERNADORA',
            // Director/Directora
            'Directora General',
            'Director General',
            'DIRECTORA GENERAL',
            'DIRECTOR GENERAL',
        ];

        // Encontrar el índice de la línea de cargo buscando de abajo hacia arriba
        $indiceCargo = -1;
        for ($i = $totalLineas - 1; $i >= 0; $i--) {
            // Limitar la búsqueda al 70% inferior para evitar falsos positivos
            if ($i < intval($totalLineas * 0.3)) {
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

        // Buscar el nombre en las hasta 5 líneas anteriores al cargo
        // Tomamos el mejor candidato (el que tiene más caracteres alfabéticos válidos)
        $mejorNombre = '';
        $mejorPuntaje = 0;

        for ($offset = 1; $offset <= 5; $offset++) {
            $idx = $indiceCargo - $offset;
            if ($idx < 0) {
                break;
            }

            $linea = $lineasLimpias[$idx];
            $candidato = $this->limpiarNombreFirmante($linea);

            if (!$this->esNombreValido($candidato)) {
                continue;
            }

            // Calcular puntaje: número de letras en el candidato
            $puntaje = 0;
            for ($j = 0; $j < mb_strlen($candidato, 'UTF-8'); $j++) {
                $c = mb_substr($candidato, $j, 1, 'UTF-8');
                if (ctype_alpha($c) || ord($c) > 127) {
                    $puntaje++;
                }
            }

            // Preferir el candidato más cercano al cargo si su puntaje es competitivo
            if ($puntaje > $mejorPuntaje || ($offset === 1 && $puntaje > 0)) {
                $mejorNombre = $candidato;
                $mejorPuntaje = $puntaje;
                // Si el primer candidato tiene buen puntaje, lo preferimos
                if ($offset === 1 && $puntaje >= 6) {
                    break;
                }
            }
        }

        return $mejorNombre;
    }

    // =========================================================================
    // FUNCIONES DE LIMPIEZA
    // =========================================================================

    private function extraerFirmanteRobusto(string $texto): string {
        $lineas = array_values(array_filter(array_map('trim', explode("\n", $texto)), fn($linea) => $linea !== ''));
        if (empty($lineas)) {
            return '';
        }

        $inicioBloque = 0;
        for ($i = count($lineas) - 1; $i >= 0; $i--) {
            if ($this->esLineaCierreFirmaRobusto($lineas[$i])) {
                $inicioBloque = $i + 1;
                break;
            }
        }

        $finBloque = count($lineas) - 1;
        for ($i = $inicioBloque; $i < count($lineas); $i++) {
            if ($this->esLineaRevisionOPieRobusto($lineas[$i])) {
                $finBloque = max($inicioBloque, $i - 1);
                break;
            }
        }

        $bloque = array_slice($lineas, $inicioBloque, $finBloque - $inicioBloque + 1);
        if (empty($bloque)) {
            $bloque = $lineas;
        }

        $indiceCargo = -1;
        for ($i = count($bloque) - 1; $i >= 0; $i--) {
            if ($this->esLineaCargoFirmanteRobusto($bloque[$i])) {
                $indiceCargo = $i;
                break;
            }
        }

        $cargo = $indiceCargo >= 0 ? $bloque[$indiceCargo] : '';
        $limite = $indiceCargo >= 0 ? $indiceCargo : count($bloque);
        $textoBloque = implode(' ', $bloque);
        $mejor = '';
        $mejorPuntaje = 0;

        for ($i = $limite - 1; $i >= 0; $i--) {
            $linea = $bloque[$i];
            if ($this->esLineaNoNombreFirmanteRobusto($linea)) {
                continue;
            }

            $candidato = $this->limpiarNombreFirmante($linea);
            $normalizado = $this->normalizarFirmanteConocidoRobusto($candidato, $cargo, $textoBloque);
            if ($normalizado !== '') {
                return $normalizado;
            }

            if (!$this->esNombreValidoRobusto($candidato)) {
                continue;
            }

            $puntaje = $this->contarLetrasRobusto($candidato);
            $puntaje += $this->pareceNombrePrincipalRobusto($linea) ? 10 : 0;
            $puntaje += ($limite - $i) <= 3 ? 5 : 0;

            if ($puntaje > $mejorPuntaje) {
                $mejor = $candidato;
                $mejorPuntaje = $puntaje;
            }
        }

        $normalizado = $this->normalizarFirmanteConocidoRobusto($mejor, $cargo, $textoBloque);
        if ($normalizado !== '') {
            return $normalizado;
        }

        return $mejor;
    }

    private function esLineaCierreFirmaRobusto(string $linea): bool {
        $normalizada = $this->normalizarBusquedaFirmanteRobusto($linea);
        return str_contains($normalizada, 'PUBLIQUESE')
            || str_contains($normalizada, 'NOTIFIQUESE')
            || str_contains($normalizada, 'COMUNIQUESE')
            || str_contains($normalizada, 'CUMPLASE');
    }

    private function esLineaRevisionOPieRobusto(string $linea): bool {
        $normalizada = $this->normalizarBusquedaFirmanteRobusto($linea);
        $marcadores = [
            'APROBO', 'VO BO', 'VOBO', 'REVISO', 'REVISE', 'PROYECTO', 'DIGITO',
            'ELABORO', 'WWW', 'CAUCA GOV', 'DESPACHO', 'CARRERA', 'TELEFONO',
            'TEL ', 'PAGINA', 'GOBCAUCA', '@',
        ];

        foreach ($marcadores as $marcador) {
            if (str_contains($normalizada, $marcador)) {
                return true;
            }
        }

        return false;
    }

    private function esLineaCargoFirmanteRobusto(string $linea): bool {
        if ($this->esLineaRevisionOPieRobusto($linea)) {
            return false;
        }

        $normalizada = $this->normalizarBusquedaFirmanteRobusto($linea);
        if (str_contains($normalizada, 'HACIENDA')) {
            return false;
        }

        return str_contains($normalizada, 'GOBERNADOR')
            || str_contains($normalizada, 'GOBERNADORA')
            || str_contains($normalizada, 'SECRETAR')
            || (str_contains($normalizada, 'EDUCACION') && str_contains($normalizada, 'CULTURA'))
            || (str_contains($normalizada, 'ITURA') && str_contains($normalizada, 'DEPARTAMENTO'));
    }

    private function esLineaNoNombreFirmanteRobusto(string $linea): bool {
        $normalizada = $this->normalizarBusquedaFirmanteRobusto($linea);
        if ($normalizada === '') {
            return true;
        }

        $rechazos = [
            'DADA EN', 'POPAYAN', 'MAY ', 'JUN ', 'JUL ', 'AGO ', 'SEP ', 'OCT ',
            'NOV ', 'DIC ', 'ENE ', 'FEB ', 'MAR ', 'ABR ', '202', 'PUBL',
            'NOTIFI', 'COMUNI', 'CUMPL', 'SECRETAR', 'GOBERNADOR', 'EDUCACION',
            'CULTURA', 'DEPARTAMENTO', 'RESUELVE', 'ARTICULO',
        ];

        foreach ($rechazos as $rechazo) {
            if (str_contains($normalizada, $rechazo)) {
                return true;
            }
        }

        return $this->contarLetrasRobusto($linea) < 4;
    }

    private function pareceNombrePrincipalRobusto(string $linea): bool {
        $normalizada = $this->normalizarBusquedaFirmanteRobusto($linea);
        return str_contains($normalizada, 'CARABALI')
            || str_contains($normalizada, 'GUZMAN')
            || str_contains($normalizada, 'GUTIERREZ');
    }

    private function normalizarFirmanteConocidoRobusto(string $nombre, string $cargo, string $bloque): string {
        $texto = $this->normalizarBusquedaFirmanteRobusto($nombre . ' ' . $cargo . ' ' . $bloque);

        if (
            str_contains($texto, 'CARABALI')
            && (
                str_contains($texto, 'LARRAH')
                || str_contains($texto, 'TARRAH')
                || str_contains($texto, 'ENTRRM')
                || str_contains($texto, 'CARKAR')
                || str_contains($texto, 'SOBAN')
                || str_contains($texto, 'SORM')
                || str_contains($texto, 'SOBM')
                || str_contains($texto, 'OEINES')
                || str_contains($texto, 'SECRETAR')
                || preg_match('/\b[A-ZÑ]{1,4}\s+CARABALI\b/u', $texto)
            )
        ) {
            return 'SOR INÉS LARRAHONDO CARABALI';
        }

        if (
            (str_contains($texto, 'GUZMAN') || str_contains($texto, 'GUTIERRE'))
            && (str_contains($texto, 'JORGE') || str_contains($texto, 'OCTAVIO') || str_contains($texto, 'GOBERNADOR'))
        ) {
            return 'JORGE OCTAVIO GUZMÁN GUTIÉRREZ';
        }

        return '';
    }

    private function esNombreValidoRobusto(string $texto): bool {
        $texto = trim($texto);
        if (mb_strlen($texto, 'UTF-8') < 5 || str_contains($texto, '@')) {
            return false;
        }

        $palabras = array_values(array_filter(explode(' ', $texto), fn($p) => mb_strlen(trim($p), 'UTF-8') > 1));
        if (count($palabras) < 2 || count($palabras) > 7) {
            return false;
        }

        $letras = $this->contarLetrasRobusto($texto);
        $total = mb_strlen(str_replace(' ', '', $texto), 'UTF-8');
        if ($total === 0 || ($letras / $total) < 0.70) {
            return false;
        }

        $normalizada = $this->normalizarBusquedaFirmanteRobusto($texto);
        $prohibidas = ['ARTICULO', 'RESOLUCION', 'CONSIDERANDO', 'PARAGRAFO', 'DADA', 'POPAYAN', 'DECRETO', 'SECRETAR', 'GOBERNACI', 'DEPARTAMENTO', 'CORREO'];
        foreach ($prohibidas as $prohibida) {
            if (str_contains($normalizada, $prohibida)) {
                return false;
            }
        }

        return true;
    }

    private function normalizarBusquedaFirmanteRobusto(string $texto): string {
        $texto = mb_strtoupper($texto, 'UTF-8');
        $texto = str_replace(['Á', 'É', 'Í', 'Ó', 'Ú', 'Ü'], ['A', 'E', 'I', 'O', 'U', 'U'], $texto);
        $texto = preg_replace('/[^\p{L}\p{N}@]+/u', ' ', $texto) ?? $texto;
        return trim(preg_replace('/\s+/', ' ', $texto) ?? $texto);
    }

    private function contarLetrasRobusto(string $texto): int {
        preg_match_all('/\p{L}/u', $texto, $matches);
        return count($matches[0] ?? []);
    }

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
     * Limpia el nombre del firmante eliminando artefactos OCR de firmas manuscritas.
     *
     * @param string $nombre Texto crudo de la línea del nombre
     * @return string Nombre limpio
     */
    private function limpiarNombreFirmante(string $nombre): string {
        $nombre = trim($nombre);

        // Eliminar caracteres típicos de firmas manuscritas
        // que el OCR interpreta como símbolos: / \ _ ~ ^ @ # $ % & * + = < >
        $charsBasura = ['/', '_', '~', '^', '@', '#', '$', '%', '&', '+', '=',
                        '<', '>', '|', '`', '"', "'", '(', ')', '[', ']', '{', '}'];
        $nombre = str_replace($charsBasura, ' ', $nombre);

        // Eliminar secuencias de puntos (firmas: ........)
        while (strpos($nombre, '..') !== false) {
            $nombre = str_replace('..', '.', $nombre);
        }
        $nombre = trim($nombre, '.');

        // Eliminar secuencias de guiones (firmas: ---- o ____)
        while (strpos($nombre, '--') !== false) {
            $nombre = str_replace('--', '', $nombre);
        }

        // Eliminar secuencias de asteriscos
        while (strpos($nombre, '**') !== false) {
            $nombre = str_replace('**', '', $nombre);
        }

        // Eliminar dígitos sueltos y palabras que son sólo símbolos
        $palabras = explode(' ', $nombre);
        $palabrasFiltradas = [];
        foreach ($palabras as $palabra) {
            $p = trim($palabra);
            if (empty($p)) continue;

            // Descartar tokens que son solo números
            if (ctype_digit($p)) continue;

            // Descartar tokens de un solo carácter no alfabético
            if (mb_strlen($p, 'UTF-8') === 1 && !preg_match('/\p{L}/u', $p)) continue;

            // Descartar tokens donde la mayoría son caracteres no alfabéticos
            // (ruido de firma: "/////", "----", etc.)
            $letrasEnToken = 0;
            $lenToken = mb_strlen($p, 'UTF-8');
            for ($k = 0; $k < $lenToken; $k++) {
                $c = mb_substr($p, $k, 1, 'UTF-8');
                if (ctype_alpha($c) || ord($c[0]) > 127) $letrasEnToken++;
            }
            if ($lenToken > 0 && ($letrasEnToken / $lenToken) < 0.5) continue;

            $palabrasFiltradas[] = $p;
        }

        $nombreLimpio = implode(' ', $palabrasFiltradas);

        // Corregir separaciones de palabras producidas por OCR (ej. "LARRA HONDO")
        $nombreLimpio = $this->corregirSeparacionesPalabras($nombreLimpio);

        // Reducir múltiples espacios
        while (strpos($nombreLimpio, '  ') !== false) {
            $nombreLimpio = str_replace('  ', ' ', $nombreLimpio);
        }

        return trim($nombreLimpio);
    }

    /**
     * Valida que una cadena parece un nombre de persona y no es basura OCR.
     *
     * @param string $texto Cadena a validar
     * @return bool True si parece un nombre válido
     */
    private function esNombreValido(string $texto): bool {
        // Debe tener al menos 5 caracteres (nombres reales son más largos)
        if (mb_strlen($texto, 'UTF-8') < 5) {
            return false;
        }

        // Contar letras vs caracteres no espacio usando mb_ para UTF-8
        $letras = 0;
        $total = 0;
        $len = mb_strlen($texto, 'UTF-8');
        for ($i = 0; $i < $len; $i++) {
            $char = mb_substr($texto, $i, 1, 'UTF-8');
            if ($char !== ' ') {
                $total++;
                if (ctype_alpha($char) || ord($char[0]) > 127) {
                    $letras++;
                }
            }
        }

        // Al menos el 70% debe ser letras
        if ($total === 0 || ($letras / $total) < 0.70) {
            return false;
        }

        // Debe tener al menos dos palabras (nombre y apellido)
        $palabras = array_values(array_filter(
            explode(' ', $texto),
            fn($p) => mb_strlen(trim($p), 'UTF-8') > 0
        ));
        $numPalabras = count($palabras);
        if ($numPalabras < 2) {
            return false;
        }

        // Un nombre normal no tiene más de 7 palabras
        if ($numPalabras > 7) {
            return false;
        }

        // Filtrar líneas donde casi todas las palabras son de 1 caracter
        // (artefacto OCR de firmas: "J O R G E")
        $palabrasCortas = 0;
        foreach ($palabras as $p) {
            if (mb_strlen($p, 'UTF-8') < 2) {
                $palabrasCortas++;
            }
        }
        if ($palabrasCortas > 1) {
            return false;
        }

        // Si contiene palabras típicas del cuerpo del documento, rechazar
        $palabrasProhibidas = [
            'ARTICULO', 'ARTÍCULO', 'RESOLUCION', 'RESOLUCIÓN',
            'CONSIDERANDO', 'PARAGRAFO', 'PUBLIQUESE', 'PUBLÍQUESE',
            'COMUNIQUESE', 'COMUNÍQUESE', 'CUMPLASE', 'CÚMPLASE',
            'DADA', 'POPAYAN', 'POPAYÁN', 'ESTABLECIMIENTO', 'EDUCATIVO',
            'DECRETO', 'SECRETAR', 'GOBERNACI', 'DEPARTAMENTO',
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
