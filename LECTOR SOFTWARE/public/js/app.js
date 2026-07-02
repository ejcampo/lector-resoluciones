document.addEventListener('DOMContentLoaded', () => {
    // State
    let selectedFiles = [];
    let isUploading = false;
    let extractionResults = []; // Almacena los resultados de la última extracción para exportar

    // DOM Elements
    const dropzone = document.getElementById('dropzone');
    const fileInput = document.getElementById('file-input');
    const selectBtn = document.getElementById('btn-select');
    const fileList = document.getElementById('file-list');
    const procesarBtn = document.getElementById('btn-procesar');
    const exportBtn = document.getElementById('btn-exportar');
    const tableBody = document.getElementById('results-table-body');
    const emptyState = document.getElementById('empty-state');
    const tableCard = document.getElementById('table-card');
    const progressContainer = document.getElementById('progress-container');
    const progressBar = document.getElementById('progress-bar');
    const statsText = document.getElementById('stats-text');
    const selectedCount = document.getElementById('selected-count');

    // Create Toast Container dynamically
    const toastContainer = document.createElement('div');
    toastContainer.className = 'toast-container';
    document.body.appendChild(toastContainer);

    /**
     * Muestra una notificación temporal en pantalla.
     */
    function showToast(message, type = 'error') {
        const toast = document.createElement('div');
        toast.className = `toast ${type === 'success' ? 'toast-success' : ''}`;
        toast.innerHTML = `
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
                ${type === 'success' 
                    ? '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline>' 
                    : '<circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line>'}
            </svg>
            <div class="toast-message">${message}</div>
        `;
        toastContainer.appendChild(toast);

        // Remover automáticamente después de 4 segundos
        setTimeout(() => {
            toast.style.animation = 'toastOut 0.3s ease-out forwards';
            setTimeout(() => {
                toast.remove();
            }, 300);
        }, 4000);
    }

    // Trigger file selection input
    selectBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        fileInput.click();
    });

    dropzone.addEventListener('click', () => {
        fileInput.click();
    });

    // Handle file selection
    fileInput.addEventListener('change', (e) => {
        handleFiles(e.target.files);
        fileInput.value = ''; // Reset input to allow selecting same file again
    });

    // Drag and Drop Events
    ['dragenter', 'dragover'].forEach(eventName => {
        dropzone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.add('dragover');
        }, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
            dropzone.classList.remove('dragover');
        }, false);
    });

    dropzone.addEventListener('drop', (e) => {
        const dt = e.dataTransfer;
        handleFiles(dt.files);
    });

    /**
     * Procesa, valida y agrega archivos a la lista temporal de selección.
     */
    async function handleFiles(files) {
        if (isUploading) return;

        let addedCount = 0;

        for (const file of files) {
            // 1. Validar extensión de archivo (.pdf)
            const isPdfExtension = file.name.toLowerCase().endsWith('.pdf');
            if (!isPdfExtension) {
                showToast(`El archivo "${file.name}" no es un PDF. Solo se admiten archivos con extensión .pdf.`, 'error');
                continue;
            }

            // 2. Validar que realmente sea un PDF leyendo los primeros 4 bytes (firma %PDF)
            const isValidPdf = await validatePdfMagicBytes(file);
            if (!isValidPdf) {
                showToast(`El archivo "${file.name}" está dañado o no es un PDF válido.`, 'error');
                continue;
            }

            // Evitar duplicados por nombre en la lista actual
            if (selectedFiles.some(f => f.name === file.name)) {
                showToast(`El archivo "${file.name}" ya está en la lista.`, 'warning');
                continue;
            }

            selectedFiles.push(file);
            addedCount++;
        }

        if (addedCount > 0) {
            updateUI();
        }
    }

    /**
     * Valida la firma del archivo comprobando los primeros 4 bytes en binario.
     */
    function validatePdfMagicBytes(file) {
        return new Promise((resolve) => {
            const reader = new FileReader();
            reader.onloadend = (e) => {
                if (e.target.readyState !== FileReader.DONE) {
                    resolve(false);
                    return;
                }
                const arr = new Uint8Array(e.target.result);
                // Cabecera hexadecimal para %PDF: 25 50 44 46
                const isPdf = arr[0] === 0x25 && arr[1] === 0x50 && arr[2] === 0x44 && arr[3] === 0x46;
                resolve(isPdf);
            };
            // Leer únicamente los primeros 4 bytes
            const blob = file.slice(0, 4);
            reader.readAsArrayBuffer(blob);
        });
    }

    /**
     * Elimina un archivo de la lista de selección temporal.
     */
    window.removeFile = function(index) {
        if (isUploading) return;
        selectedFiles.splice(index, 1);
        updateUI();
    };

    /**
     * Actualiza la interfaz gráfica con los archivos seleccionados.
     */
    function updateUI() {
        if (selectedFiles.length === 0) {
            fileList.innerHTML = '<p style="font-size: 0.8rem; color: var(--text-muted); text-align: center; padding: 1.5rem 0;">Ningún archivo seleccionado</p>';
            selectedCount.textContent = '';
        } else {
            selectedCount.textContent = `(${selectedFiles.length})`;
            fileList.innerHTML = selectedFiles.map((file, index) => `
                <div class="file-item">
                    <div class="file-info">
                        <div class="file-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                <line x1="16" y1="17" x2="8" y2="17"></line>
                                <polyline points="10 9 9 9 8 9"></polyline>
                            </svg>
                        </div>
                        <div class="file-details">
                            <div class="file-name" title="${file.name}">${file.name}</div>
                            <div class="file-size">${formatBytes(file.size)} <span class="badge badge-pending" style="font-size: 0.6rem; padding: 0.1rem 0.4rem; margin-left: 0.5rem;">Pendiente</span></div>
                        </div>
                    </div>
                    <button class="btn-remove" onclick="removeFile(${index})" title="Quitar archivo">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
            `).join('');
        }

        // Habilitar/Deshabilitar el botón de procesar según la cantidad de archivos
        procesarBtn.disabled = selectedFiles.length === 0 || isUploading;
    }

    /**
     * Formatea el tamaño del archivo en bytes a unidades legibles (KB, MB).
     */
    function formatBytes(bytes, decimals = 2) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }

    // Manejador del botón Procesar
    procesarBtn.addEventListener('click', () => {
        if (selectedFiles.length === 0 || isUploading) return;
        uploadAndExtract();
    });

    // Manejador del botón Exportar a Excel
    exportBtn.addEventListener('click', () => {
        if (extractionResults.length === 0) {
            showToast('No hay resultados para exportar. Procese archivos primero.', 'error');
            return;
        }
        exportToExcel();
    });

    /**
     * Flujo completo: sube archivos al servidor y ejecuta la extracción inteligente.
     * Paso 1: POST /api/upload — sube los PDFs al almacenamiento temporal.
     * Paso 2: POST /api/extract — convierte, OCR y extrae datos estructurados.
     */
    function uploadAndExtract() {
        isUploading = true;
        procesarBtn.disabled = true;
        procesarBtn.innerHTML = '<div class="spinner"></div> Subiendo...';
        progressContainer.style.display = 'block';
        progressBar.style.width = '0%';
        
        // Empaquetar archivos en FormData
        const formData = new FormData();
        selectedFiles.forEach(file => {
            formData.append('files[]', file);
        });

        statsText.textContent = 'Subiendo archivos al servidor...';

        // ──────────────────────────────────────────────────────
        // PASO 1: Subir archivos al servidor
        // ──────────────────────────────────────────────────────
        const xhr = new XMLHttpRequest();
        xhr.open('POST', '/api/upload', true);

        xhr.upload.onprogress = (e) => {
            if (e.lengthComputable) {
                const percentage = Math.round((e.loaded / e.total) * 100);
                progressBar.style.width = `${percentage}%`;
                statsText.textContent = `Subiendo archivos: ${percentage}%...`;
            }
        };

        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        showToast('Archivos cargados exitosamente. Iniciando procesamiento...', 'success');

                        // Mostrar archivos con estado "Procesando" mientras se ejecuta la extracción
                        emptyState.style.display = 'none';
                        tableBody.innerHTML = '';

                        response.files.forEach(file => {
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td style="font-weight: 500;">${file.name}</td>
                                <td><div class="cell-loading"><div class="spinner-sm"></div> Procesando...</div></td>
                                <td><div class="cell-loading"><div class="spinner-sm"></div> Procesando...</div></td>
                                <td><div class="cell-loading"><div class="spinner-sm"></div> Procesando...</div></td>
                                <td><span class="badge badge-processing">Procesando</span></td>
                            `;
                            tableBody.appendChild(row);
                        });

                        statsText.textContent = 'Ejecutando conversión, OCR y extracción...';

                        // Vaciar la lista de selección local
                        selectedFiles = [];
                        updateUI();

                        // ──────────────────────────────────────────────────────
                        // PASO 2: Ejecutar el pipeline de extracción inteligente
                        // ──────────────────────────────────────────────────────
                        runExtraction();
                    } else {
                        resetProcessButton();
                        showToast(response.error || 'Ocurrió un error al cargar los archivos.', 'error');
                        statsText.textContent = 'Error en la carga de archivos.';
                    }
                } catch (e) {
                    resetProcessButton();
                    showToast('Respuesta del servidor inválida.', 'error');
                    statsText.textContent = 'Error de comunicación.';
                }
            } else {
                resetProcessButton();
                try {
                    const response = JSON.parse(xhr.responseText);
                    const errorMessage = response.errors ? response.errors.join('\n') : (response.error || 'Error al procesar la carga.');
                    showToast(errorMessage, 'error');
                } catch (e) {
                    showToast('Error de comunicación con el servidor.', 'error');
                }
                statsText.textContent = 'Error en la carga.';
            }
        };

        xhr.onerror = function() {
            resetProcessButton();
            showToast('Error de red al intentar conectarse al servidor.', 'error');
            statsText.textContent = 'Error de red.';
        };

        xhr.send(formData);
    }

    /**
     * Ejecuta el pipeline completo de extracción: Conversión → OCR → Extracción.
     * Llama a POST /api/extract y renderiza los resultados en la tabla.
     */
    function runExtraction() {
        procesarBtn.innerHTML = '<div class="spinner"></div> Extrayendo datos...';
        progressContainer.style.display = 'block';
        progressBar.style.width = '100%';
        progressBar.classList.add('progress-indeterminate');

        fetch('/api/extract', {
            method: 'POST',
            headers: { 'Accept': 'application/json' }
        })
        .then(response => response.json())
        .then(data => {
            progressBar.classList.remove('progress-indeterminate');
            progressContainer.style.display = 'none';
            resetProcessButton();

            if (data.success && data.results) {
                // Almacenar resultados para exportación
                extractionResults = data.results;

                renderExtractionResults(data.results);
                
                const okCount = data.results.filter(r => r.estado === 'OK').length;
                const errCount = data.results.length - okCount;
                
                let statsMsg = `${data.results.length} documento(s) procesado(s)`;
                if (okCount > 0) statsMsg += ` · ${okCount} exitoso(s)`;
                if (errCount > 0) statsMsg += ` · ${errCount} con error`;
                statsText.textContent = statsMsg;

                if (okCount > 0) {
                    exportBtn.disabled = false;
                    showToast(`Extracción completada: ${okCount} documento(s) procesado(s) correctamente.`, 'success');
                } else {
                    showToast('La extracción finalizó pero no se obtuvieron resultados exitosos.', 'error');
                }
            } else {
                showToast(data.error || 'Error durante la extracción de datos.', 'error');
                statsText.textContent = 'Error en la extracción.';
                // Actualizar tabla con error general
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="5" style="text-align: center; color: var(--danger); padding: 2rem;">
                            Error: ${data.error || 'No se pudieron procesar los documentos.'}
                        </td>
                    </tr>
                `;
            }
        })
        .catch(err => {
            progressBar.classList.remove('progress-indeterminate');
            progressContainer.style.display = 'none';
            resetProcessButton();
            showToast('Error de conexión al ejecutar la extracción.', 'error');
            statsText.textContent = 'Error de red durante la extracción.';
        });
    }

    /**
     * Renderiza los resultados de extracción en la tabla de resultados.
     */
    function renderExtractionResults(results) {
        emptyState.style.display = 'none';
        tableBody.innerHTML = '';

        results.forEach(result => {
            const row = document.createElement('tr');
            const isOk = result.estado === 'OK';
            
            // Nombre del archivo (sin timestamp/uniqid del servidor)
            const displayName = cleanFileName(result.archivo || 'Desconocido');

            // Número de resolución
            const numRes = result.numero_resolucion || '—';
            const numResClass = result.numero_resolucion ? '' : 'style="color: var(--text-muted); font-style: italic;"';

            // Primer párrafo (truncado para la tabla, completo en tooltip)
            const parrafo = result.primer_parrafo || '';
            const parrafoDisplay = parrafo.length > 120 ? parrafo.substring(0, 120) + '…' : (parrafo || '—');
            const parrafoAttr = parrafo ? `title="${escapeHtml(parrafo)}"` : 'style="color: var(--text-muted); font-style: italic;"';

            // Firmante
            const firmante = result.firmante || '—';
            const firmanteClass = result.firmante ? '' : 'style="color: var(--text-muted); font-style: italic;"';

            // Badge de estado
            const badgeClass = isOk ? 'badge-success' : 'badge-error';
            const badgeText = isOk ? 'OK' : 'Error';

            row.innerHTML = `
                <td style="font-weight: 500;" title="${escapeHtml(result.archivo || '')}">${displayName}</td>
                <td ${numResClass}><span class="resolution-number">${escapeHtml(numRes)}</span></td>
                <td class="paragraph-cell" ${parrafoAttr}>${escapeHtml(parrafoDisplay)}</td>
                <td ${firmanteClass}>${escapeHtml(firmante)}</td>
                <td><span class="badge ${badgeClass}">${badgeText}</span></td>
            `;

            // Si hay error, añadir tooltip con el mensaje
            if (!isOk && result.mensaje) {
                row.title = result.mensaje;
                row.style.opacity = '0.7';
            }

            tableBody.appendChild(row);
        });
    }

    /**
     * Limpia el nombre del archivo eliminando el prefijo timestamp_uniqid_ del servidor.
     */
    function cleanFileName(filename) {
        // El servidor añade: time() + '_' + uniqid() + '_' + nombre_original
        // Ejemplo: 1751401234_668dc1a2e4b21_documento.pdf → documento.pdf
        const parts = filename.split('_');
        if (parts.length >= 3) {
            // Verificar si los primeros dos segmentos parecen timestamp y uniqid
            const firstIsNum = /^\d{10,}$/.test(parts[0]);
            const secondIsHex = /^[a-f0-9]{13,}$/.test(parts[1]);
            if (firstIsNum && secondIsHex) {
                return parts.slice(2).join('_');
            }
        }
        return filename;
    }

    /**
     * Escapa caracteres HTML para evitar inyección en la tabla.
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Restaura el botón de procesar a su estado original.
     */
    function resetProcessButton() {
        isUploading = false;
        procesarBtn.innerHTML = `
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polygon points="5 3 19 12 5 21 5 3"></polygon>
            </svg>
            Procesar archivos
        `;
        procesarBtn.disabled = selectedFiles.length === 0;
    }

    /**
     * Exporta los resultados de extracción a un archivo Excel (.xlsx).
     * Envía los datos al servidor vía POST /api/export y descarga el archivo generado.
     */
    function exportToExcel() {
        // Deshabilitar el botón durante la generación
        exportBtn.disabled = true;
        const originalContent = exportBtn.innerHTML;
        exportBtn.innerHTML = '<div class="spinner" style="border-top-color: var(--success-color); border-color: rgba(16,185,129,0.3);"></div> Generando...';

        fetch('/api/export', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            },
            body: JSON.stringify({ results: extractionResults })
        })
        .then(response => {
            if (!response.ok) {
                // Si el servidor respondió con error JSON
                return response.json().then(err => {
                    throw new Error(err.error || 'Error al generar el archivo Excel.');
                });
            }

            // Extraer el nombre del archivo del header Content-Disposition
            const disposition = response.headers.get('Content-Disposition');
            let filename = 'Resoluciones.xlsx';
            if (disposition) {
                const match = disposition.match(/filename="?([^"]+)"?/);
                if (match && match[1]) {
                    filename = match[1];
                }
            }

            return response.blob().then(blob => ({ blob, filename }));
        })
        .then(({ blob, filename }) => {
            // Crear enlace temporal para descargar el archivo
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = filename;
            document.body.appendChild(link);
            link.click();

            // Limpiar
            setTimeout(() => {
                URL.revokeObjectURL(url);
                link.remove();
            }, 100);

            showToast(`Archivo Excel descargado: ${filename}`, 'success');
        })
        .catch(err => {
            showToast(err.message || 'Error al exportar a Excel.', 'error');
        })
        .finally(() => {
            exportBtn.innerHTML = originalContent;
            exportBtn.disabled = false;
        });
    }
});

