document.addEventListener('DOMContentLoaded', () => {
    // Auth State and Elements
    const loginScreen = document.getElementById('login-screen');
    const loginForm = document.getElementById('login-form');
    const loginEmailInput = document.getElementById('login-email');
    const loginPasswordInput = document.getElementById('login-password');
    const loginSubmitBtn = document.getElementById('btn-login-submit');
    const userProfileHeader = document.getElementById('user-profile-header');
    const headerUserName = document.getElementById('header-user-name');
    const headerUserRole = document.getElementById('header-user-role');
    const logoutBtn = document.getElementById('btn-logout');

    let currentUser = null;

    function checkAuth() {
        const storedUser = localStorage.getItem('usuario_logeado');
        if (storedUser) {
            try {
                currentUser = JSON.parse(storedUser);
                applyAuthenticatedUI();
            } catch (e) {
                localStorage.removeItem('usuario_logeado');
                applyLoggedOutUI();
            }
        } else {
            applyLoggedOutUI();
        }
    }

    function applyAuthenticatedUI() {
        if (loginScreen) loginScreen.classList.add('hidden');
        if (userProfileHeader) userProfileHeader.style.display = 'flex';
        if (headerUserName) headerUserName.textContent = currentUser.nombre_completo || 'Usuario';
        if (headerUserRole) headerUserRole.textContent = currentUser.correo || 'admin';
        
        cargarResolucionesGuardadas();
    }

    function applyLoggedOutUI() {
        if (loginScreen) loginScreen.classList.remove('hidden');
        if (userProfileHeader) userProfileHeader.style.display = 'none';
        currentUser = null;
        extractionResults = [];
        if (typeof renderExtractionResults === 'function') {
            renderExtractionResults([]);
            updateStatsText();
        }
    }

    function cargarResolucionesGuardadas() {
        if (!currentUser || !currentUser.id) return;
        
        fetch(apiUrl(`/api/resoluciones?usuario_id=${currentUser.id}`))
            .then(response => response.json())
            .then(data => {
                if (data.success && data.results) {
                    extractionResults = data.results;
                    renderExtractionResults(extractionResults);
                    updateStatsText({enabled: true, saved: data.results.length});
                    if (data.results.length > 0) {
                        exportBtn.disabled = false;
                    } else {
                        exportBtn.disabled = true;
                    }
                }
            })
            .catch(err => {
                console.error("Error al cargar resoluciones", err);
            });
    }

    if (loginForm) {
        loginForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const correo = loginEmailInput.value.trim();
            const contrasena = loginPasswordInput.value;

            if (!correo || !contrasena) {
                showToast('Por favor, ingresa tu correo y contraseña.', 'error');
                return;
            }

            loginSubmitBtn.disabled = true;
            loginSubmitBtn.innerHTML = '<div class="spinner" style="display:inline-block; vertical-align:middle; margin-right:5px;"></div> Verificando...';

            fetch(apiUrl('/api/login'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ correo, contrasena })
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => {
                        throw new Error(err.error || 'Credenciales incorrectas.');
                    });
                }
                return response.json();
            })
            .then(data => {
                loginSubmitBtn.disabled = false;
                loginSubmitBtn.textContent = 'Iniciar Sesión';
                
                if (data.success && data.user) {
                    localStorage.setItem('usuario_logeado', JSON.stringify(data.user));
                    currentUser = data.user;
                    applyAuthenticatedUI();
                    showToast(`¡Bienvenido de nuevo, ${currentUser.nombre_completo}!`, 'success');
                } else {
                    showToast(data.error || 'Error al iniciar sesión.', 'error');
                }
            })
            .catch(err => {
                loginSubmitBtn.disabled = false;
                loginSubmitBtn.textContent = 'Iniciar Sesión';
                showToast(err.message || 'Error de conexión con el servidor.', 'error');
            });
        });
    }

    if (logoutBtn) {
        logoutBtn.addEventListener('click', () => {
            localStorage.removeItem('usuario_logeado');
            applyLoggedOutUI();
            showToast('Sesión cerrada correctamente.', 'success');
        });
    }

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
    const appContainer = document.querySelector('.app-container');
    const toggleSidebarBtn = document.getElementById('btn-toggle-sidebar');
    
    // Pagination Elements
    const paginationContainer = document.getElementById('pagination-container');
    const btnPrevPage = document.getElementById('btn-prev-page');
    const btnNextPage = document.getElementById('btn-next-page');
    const pageInfo = document.getElementById('page-info');

    // State
    let selectedFiles = [];
    let isUploading = false;
    const API_BASE_URL = window.location.port === '5000'
        ? 'http://127.0.0.1:5001'
        : '';

    function apiUrl(path) {
        return `${API_BASE_URL}${path}`;
    }
    let extractionResults = []; // Almacena los resultados de la última extracción para exportar

    // Ejecutar verificación de autenticación inicial (ahora de forma segura tras declarar las variables)
    checkAuth();

    // Pagination State
    let currentPage = 1;
    const itemsPerPage = 5;

    // Create Toast Container dynamically
    const toastContainer = document.createElement('div');
    toastContainer.className = 'toast-container';
    document.body.appendChild(toastContainer);

    if (toggleSidebarBtn && appContainer) {
        toggleSidebarBtn.addEventListener('click', () => {
            const collapsed = appContainer.classList.toggle('sidebar-collapsed');
            toggleSidebarBtn.setAttribute('aria-expanded', String(!collapsed));
            toggleSidebarBtn.title = collapsed ? 'Mostrar panel de carga' : 'Ocultar panel de carga';
            toggleSidebarBtn.querySelector('.sidebar-toggle-icon').textContent = collapsed ? '>' : '<';
        });
    }

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
            <div class="toast-message">${escapeHtml(message)}</div>
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
            if (selectedFiles.some(f => normalizeComparableFileName(f.name) === normalizeComparableFileName(file.name))) {
                showToast(`El archivo "${file.name}" ya está en la lista.`, 'warning');
                continue;
            }

            if (isFileAlreadyLoaded(file.name)) {
                showToast(`El archivo "${file.name}" ya fue cargado.`, 'warning');
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
     * Elimina un extracto ya cargado de la tabla de resultados.
     */
    window.removeExtractionResult = function(index) {
        if (isUploading || index < 0 || index >= extractionResults.length) return;

        const removed = extractionResults.splice(index, 1)[0];
        const removedName = cleanFileName(removed?.archivo || 'Documento PDF');
        showToast(`Se quitó "${removedName}" de la tabla.`, 'success');

        updateResultsAfterRemoval();
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
        xhr.open('POST', apiUrl('/api/upload'), true);

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
                        if (extractionResults.length === 0) {
                            tableBody.innerHTML = '';
                        }

                        response.files.forEach((file, index) => {
                            const row = document.createElement('tr');
                            const fileName = file.name || file.archivo || 'Documento PDF';
                            const rowNumber = extractionResults.length + index + 1;
                            row.innerHTML = `
                                <td style="color: var(--text-secondary); font-weight: 500;">${rowNumber}</td>
                                <td style="font-weight: 500;">${fileName}</td>
                                <td><div class="cell-loading"><div class="spinner-sm"></div> Procesando...</div></td>
                                <td><div class="cell-loading"><div class="spinner-sm"></div> Procesando...</div></td>
                                <td><div class="cell-loading"><div class="spinner-sm"></div> Procesando...</div></td>
                                <td><div class="cell-loading"><div class="spinner-sm"></div> Procesando...</div></td>
                                <td><div class="cell-loading"><div class="spinner-sm"></div> Procesando...</div></td>
                                <td><span class="badge badge-processing">Procesando</span></td>
                                <td><div class="cell-loading">...</div></td>
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
                        runExtraction(response.files);
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
            showToast('La API PHP no esta iniciada. Abre iniciar-live-server-con-api.bat y vuelve a procesar.', 'error');
            statsText.textContent = 'API PHP apagada.';
        };

        xhr.send(formData);
    }

    /**
     * Ejecuta el pipeline completo de extracción: Conversión → OCR → Extracción.
     * Llama a POST /api/extract y renderiza los resultados en la tabla.
     */
    function runExtraction(filesToExtract = []) {
        procesarBtn.innerHTML = '<div class="spinner"></div> Extrayendo datos...';
        progressContainer.style.display = 'block';
        progressBar.style.width = '100%';
        progressBar.classList.add('progress-indeterminate');

        const payload = { files: filesToExtract };
        if (currentUser && currentUser.id) {
            payload.usuario_id = currentUser.id;
        }

        fetch(apiUrl('/api/extract'), {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        })
        .then(response => response.json())
        .then(data => {
            progressBar.classList.remove('progress-indeterminate');
            progressContainer.style.display = 'none';
            resetProcessButton();

            if (data.success && data.results) {
                const addedResults = appendNewExtractionResults(data.results);
                const targetPage = Math.max(1, Math.ceil(extractionResults.length / itemsPerPage));

                renderExtractionResults(extractionResults, targetPage);
                updateStatsText(data.database);
                
                const okCount = data.results.filter(r => r.estado === 'OK').length;

                if (extractionResults.length > 0) {
                    exportBtn.disabled = false;
                }

                if (addedResults.length > 0 && okCount > 0) {
                    showToast(`Extracción completada: ${addedResults.length} documento(s) agregado(s) a la tabla.`, 'success');
                } else if (addedResults.length === 0) {
                    showToast('Los documentos procesados ya estaban cargados en la tabla.', 'warning');
                } else {
                    showToast('La extracción finalizó pero no se obtuvieron resultados exitosos.', 'error');
                }
            } else {
                showToast(data.error || 'Error durante la extracción de datos.', 'error');
                statsText.textContent = 'Error en la extracción.';
                // Actualizar tabla con error general
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="8" style="text-align: center; color: var(--danger); padding: 2rem;">
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
     * Prepara la vista de resultados e inicializa la paginación.
     */
    function renderExtractionResults(results, page = 1) {
        if (results.length === 0) {
            emptyState.style.display = 'flex';
            tableBody.innerHTML = '';
            paginationContainer.style.display = 'none';
            return;
        }
        
        emptyState.style.display = 'none';
        currentPage = page;
        renderTablePage(currentPage);
    }

    /**
     * Renderiza una página específica de los resultados en la tabla.
     */
    function renderTablePage(page) {
        const totalPages = Math.ceil(extractionResults.length / itemsPerPage);
        
        // Validar límites de página
        if (page < 1) page = 1;
        if (page > totalPages) page = totalPages;
        
        currentPage = page;
        
        // Calcular índices
        const startIndex = (page - 1) * itemsPerPage;
        const endIndex = Math.min(startIndex + itemsPerPage, extractionResults.length);
        
        // Obtener subconjunto de resultados
        const pageResults = extractionResults.slice(startIndex, endIndex);
        
        tableBody.innerHTML = '';

        pageResults.forEach((result, index) => {
            // El índice global (1-based) para la columna #
            const globalIndex = startIndex + index + 1;
            
            const row = document.createElement('tr');
            const isOk = result.estado === 'OK';
            
            // Nombre del archivo (sin timestamp/uniqid del servidor)
            const displayName = cleanFileName(result.archivo || 'Desconocido');

            // Número de resolución
            const numRes = result.numero_resolucion || '—';
            const numResClass = result.numero_resolucion ? '' : 'style="color: var(--text-muted); font-style: italic;"';

            // Primer párrafo (completo en la tabla)
            const parrafo = result.primer_parrafo || '';
            const parrafoDisplay = parrafo || '—';
            const parrafoAttr = parrafo ? `title="${escapeHtml(parrafo)}"` : 'style="color: var(--text-muted); font-style: italic;"';

            // Firmante
            const firmante = result.firmante || '—';
            const firmanteClass = result.firmante ? '' : 'style="color: var(--text-muted); font-style: italic;"';

            // Firma Original
            let firmaOriginalHtml = '<span style="color: var(--text-muted); font-style: italic;">No disp.</span>';
            if (result.imagen_firma) {
                const imgUrl = apiUrl(`/signatures/${result.imagen_firma}`);
                firmaOriginalHtml = `
                    <div class="firma-thumbnail-container" onclick="openFirmaLightbox('${imgUrl}')" title="Ver firma">
                        <img src="${imgUrl}" alt="Firma" class="firma-thumbnail-img" onerror="this.parentElement.innerHTML='<span style=\\'color:var(--text-muted)\\'>No disp.</span>'">
                        <div class="firma-thumbnail-overlay">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                <line x1="11" y1="8" x2="11" y2="14"></line>
                                <line x1="8" y1="11" x2="14" y2="11"></line>
                            </svg>
                        </div>
                    </div>
                `;
            }

            // Badge de estado
            const badgeClass = isOk ? 'badge-success' : 'badge-error';
            const badgeText = isOk ? 'OK' : 'Error';
            
            // Enlace al PDF original
            const pdfUrl = result.archivo ? apiUrl(`/api/view-pdf?file=${encodeURIComponent(result.archivo)}`) : '#';
            const actionBtn = result.archivo 
                ? `<a href="${pdfUrl}" target="_blank" class="btn btn-secondary btn-sm" title="Ver PDF Original" style="text-decoration: none;">Ver PDF</a>`
                : `<span style="color: var(--text-muted); font-size: 0.8rem;">No disp.</span>`;
            const cancelBtn = `
                <button class="btn btn-danger btn-sm" type="button" onclick="removeExtractionResult(${globalIndex - 1})" title="Quitar este extracto de la tabla">
                    Cancelar
                </button>
            `;

            row.innerHTML = `
                <td style="color: var(--text-secondary); font-weight: 500;">${globalIndex}</td>
                <td style="font-weight: 500;" title="${escapeHtml(result.archivo || '')}">${displayName}</td>
                <td ${numResClass}><span class="resolution-number">${escapeHtml(numRes)}</span></td>
                <td class="paragraph-cell" ${parrafoAttr}>${escapeHtml(parrafoDisplay)}</td>
                <td ${firmanteClass}>${escapeHtml(firmante)}</td>
                <td>${firmaOriginalHtml}</td>
                <td><span class="badge ${badgeClass}">${badgeText}</span></td>
                <td><div class="action-buttons">${actionBtn}${cancelBtn}</div></td>
            `;

            // Si hay error, añadir tooltip con el mensaje
            if (!isOk && result.mensaje) {
                row.title = result.mensaje;
                row.style.opacity = '0.7';
            }

            tableBody.appendChild(row);
        });
        
        // Actualizar controles de paginación
        if (totalPages > 1) {
            paginationContainer.style.display = 'flex';
            pageInfo.textContent = `Página ${currentPage} de ${totalPages}`;
            btnPrevPage.disabled = currentPage === 1;
            btnNextPage.disabled = currentPage === totalPages;
        } else {
            paginationContainer.style.display = 'none';
        }
    }

    /**
     * Sincroniza la tabla, la paginación y el botón de exportar después de quitar una fila.
     */
    function updateResultsAfterRemoval() {
        if (extractionResults.length === 0) {
            tableBody.innerHTML = '';
            emptyState.style.display = 'flex';
            paginationContainer.style.display = 'none';
            exportBtn.disabled = true;
            statsText.textContent = 'Sin documentos cargados';
            currentPage = 1;
            return;
        }

        const totalPages = Math.ceil(extractionResults.length / itemsPerPage);
        if (currentPage > totalPages) currentPage = totalPages;

        renderTablePage(currentPage);
        updateStatsText();
        exportBtn.disabled = false;
    }

    /**
     * Actualiza el resumen superior con los resultados que permanecen en la tabla.
     */
    function updateStatsText(database = null) {
        const okCount = extractionResults.filter(r => r.estado === 'OK').length;
        const errCount = extractionResults.length - okCount;

        let statsMsg = `${extractionResults.length} documento(s) cargado(s)`;
        if (okCount > 0) statsMsg += ` · ${okCount} exitoso(s)`;
        if (errCount > 0) statsMsg += ` · ${errCount} con error`;
        if (database) {
            statsMsg += database.enabled
                ? ` · BD guardada (${database.saved})`
                : ' · BD pendiente';
        }
        statsText.textContent = statsMsg;
    }

    /**
     * Comprueba duplicados usando el nombre original visible para el usuario.
     */
    function isFileAlreadyLoaded(fileName) {
        const comparableName = normalizeComparableFileName(fileName);
        return extractionResults.some(result => {
            return normalizeComparableFileName(cleanFileName(result.archivo || '')) === comparableName;
        });
    }

    /**
     * Agrega resultados nuevos sin reemplazar los extractos que ya están en la tabla.
     */
    function appendNewExtractionResults(results) {
        const added = [];

        results.forEach(result => {
            const resultName = cleanFileName(result.archivo || '');
            if (!isFileAlreadyLoaded(resultName)) {
                extractionResults.push(result);
                added.push(result);
            }
        });

        return added;
    }

    /**
     * Replica la normalización de nombres del servidor para comparar duplicados.
     */
    function normalizeComparableFileName(fileName) {
        return String(fileName).replace(/[^a-zA-Z0-9._-]/g, '_').toLowerCase();
    }

    // Eventos de Paginación
    btnPrevPage.addEventListener('click', () => {
        if (currentPage > 1) {
            renderTablePage(currentPage - 1);
        }
    });

    btnNextPage.addEventListener('click', () => {
        const totalPages = Math.ceil(extractionResults.length / itemsPerPage);
        if (currentPage < totalPages) {
            renderTablePage(currentPage + 1);
        }
    });

    /**
     * Devuelve el nombre del archivo (ya no tiene prefijos especiales)
     */
    function cleanFileName(filename) {
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

        fetch(apiUrl('/api/export'), {
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

    // Modal Lightbox logic
    window.openFirmaLightbox = function(imgSrc) {
        const lightbox = document.getElementById('firma-lightbox');
        const img = document.getElementById('firma-lightbox-img');
        if (lightbox && img) {
            img.src = imgSrc;
            lightbox.style.display = 'flex';
        }
    };

    // Cerrar lightbox
    const lightboxCloseBtn = document.getElementById('firma-lightbox-close');
    const lightboxOverlay = document.getElementById('firma-lightbox-overlay');
    const lightbox = document.getElementById('firma-lightbox');

    if (lightboxCloseBtn) {
        lightboxCloseBtn.addEventListener('click', () => {
            if (lightbox) lightbox.style.display = 'none';
        });
    }
    if (lightboxOverlay) {
        lightboxOverlay.addEventListener('click', () => {
            if (lightbox) lightbox.style.display = 'none';
        });
    }

});
