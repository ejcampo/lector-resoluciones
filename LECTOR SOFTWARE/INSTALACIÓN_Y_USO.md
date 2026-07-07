# Lector de Resoluciones - Guía de Instalación y Uso

## 🎯 Descripción del Proyecto

**LECTOR** es una aplicación web avanzada para procesar y extraer información de resoluciones administrativas en formato PDF mediante tecnología OCR (Reconocimiento Óptico de Caracteres).

### Características principales:
- ✅ Procesamiento automatizado de PDFs
- ✅ Extracción inteligente de datos (número de resolución, párrafos, firmante)
- ✅ Sistema de navegación de dos vistas: **Procesar Documentos** y **Documentos Confirmados**
- ✅ Botón de confirmación para guardar documentos procesados
- ✅ Gestión de usuarios con autenticación
- ✅ Exportación a Excel
- ✅ Visualización de firmas extraídas

---

## 🔧 Requisitos Previos

### Software necesario:
1. **PHP 8.0+** (incluido en el proyecto: PHP 8.5.8)
2. **PostgreSQL 12+** (versión 17 instalada)
3. **Python 3.8+** (para scripts OCR/PDF)
4. **Tesseract OCR 4.0+** (para reconocimiento de texto)

### Dependencias Python:
- `Pillow` (PIL) - Procesamiento de imágenes
- `PyMuPDF` o `pypdfium2` - Conversión de PDFs
- `openpyxl` - Generación de Excel

---

## 📋 Pasos de Instalación

### 1. Clonar/Preparar el Proyecto
```bash
cd "C:\Users\darry\Documents\LECTOR\lector-resoluciones"
cd "LECTOR SOFTWARE"
```

### 2. Ejecutar Migraciones de Base de Datos
Las migraciones crean o actualizan la estructura de la base de datos:

```bash
php run-migrations.php
```

**Resultado esperado:**
```
✓ Conectado a PostgreSQL: 127.0.0.1:5432/lector_resoluciones
✓ Ejecutadas: 4
✓ Migración completada exitosamente.
```

### 3. Instalar Dependencias Python
```bash
pip install Pillow PyMuPDF openpyxl
# O alternativamente:
pip install Pillow pypdfium2 openpyxl
```

### 4. Verificar Tesseract OCR
Tesseract debe estar instalado en: `C:\Program Files\Tesseract-OCR\tesseract.exe`

Para verificar:
```bash
"C:\Program Files\Tesseract-OCR\tesseract.exe" --version
```

---

## 🚀 Iniciando la Aplicación

### Opción 1: Servidor PHP Integrado
```bash
cd "LECTOR SOFTWARE\public"
php -S 127.0.0.1:5001
```

### Opción 2: Con Live Server (Frontend en puerto 5000)
```bash
# Terminal 1 - Servidor PHP (puerto 5001)
cd "LECTOR SOFTWARE\public"
php -S 127.0.0.1:5001

# Terminal 2 - Live Server para el frontend (puerto 5000)
# Usar la extensión Live Server de VS Code o:
npx live-server --port=5000 "LECTOR SOFTWARE\public"
```

### Acceder a la Aplicación
- URL: `http://localhost:5000/` (si usas Live Server)
- O: `http://127.0.0.1:5001/` (si usas PHP integrado)

---

## 📖 Guía de Uso

### 1. **Iniciar Sesión**
```
Email: admin@admin.com
Contraseña: (según tu configuración)
```

### 2. **Procesar Documentos** (Primera Pestaña)

1. **Cargar PDFs:**
   - Arrastra archivos PDF al área de carga
   - O haz clic en "Seleccionar archivos"

2. **Procesar:**
   - Haz clic en "Procesar archivos"
   - La aplicación:
     - Convierte PDFs a imágenes
     - Ejecuta OCR
     - Extrae datos estructurados

3. **Ver Resultados:**
   - Se muestra tabla con:
     - Número de resolución
     - Primer párrafo jurídico
     - Nombre del firmante
     - Firma original (vista ampliada)

4. **Confirmar Documentos:**
   - Haz clic en botón **"Confirmar Documentos"**
   - Los documentos procesados se guardan como confirmados
   - La vista cambia automáticamente a "Documentos Confirmados"

### 3. **Documentos Confirmados** (Segunda Pestaña)

- Visualiza todos los documentos que has confirmado
- Acceso rápido al PDF original
- Los datos se guardan en la base de datos

### 4. **Exportar a Excel**
- Haz clic en "Exportar a Excel"
- Se descarga un archivo `.xlsx` con todos los datos procesados

---

## 🏗️ Estructura de Carpetas

```
LECTOR SOFTWARE/
├── app/                          # Código backend
│   ├── Controllers/              # Controladores API
│   ├── Services/                 # Lógica de negocio
│   │   ├── Extraction/           # Extracción de datos
│   │   ├── OCR/                  # Procesamiento OCR
│   │   ├── PDF/                  # Conversión PDF
│   │   └── Debug/                # Herramientas debug
│   └── Repositories/             # Acceso a datos
├── config/                       # Configuración
├── database/
│   └── migrations/               # Scripts SQL
├── public/                       # Frontend
│   ├── css/style.css             # Estilos
│   ├── js/app_v2.js              # Lógica frontend
│   ├── index.html                # Interfaz
│   └── index.php                 # Enrutador API
├── storage/
│   ├── temp/                     # Archivos temporales
│   ├── tessdata/                 # Modelos OCR
│   └── ...
└── run-migrations.php            # Script migraciones
```

---

## 🗄️ Cambios a la Base de Datos

### Migración: `002_add_confirmado_and_usuario_id.sql`

Se agregaron dos nuevos campos a la tabla `resoluciones_extraidas`:

1. **`confirmado` (BOOLEAN):**
   - `DEFAULT FALSE` - Documentos nuevos no están confirmados
   - Marcar como `TRUE` al confirmar en la interfaz

2. **`usuario_id` (BIGINT):**
   - Rastrea qué usuario procesó cada documento
   - Permite filtrar por usuario

### Índices nuevos:
- `idx_resoluciones_confirmado` - Para búsquedas rápidas
- `idx_resoluciones_usuario` - Para filtrar por usuario

---

## 🔌 Nuevos Endpoints API

### `POST /api/confirmar`
Confirma documentos procesados

**Parámetros:**
```json
{
  "archivos": ["archivo1.pdf", "archivo2.pdf"],
  "usuario_id": 123
}
```

**Respuesta:**
```json
{
  "success": true,
  "confirmados": 2,
  "message": "Se confirmaron 2 documento(s)."
}
```

### `GET /api/resoluciones-confirmadas?usuario_id=123`
Obtiene documentos confirmados del usuario

**Respuesta:**
```json
{
  "success": true,
  "results": [
    {
      "id": 1,
      "archivo": "resolucion_001.pdf",
      "numero_resolucion": "05285-06-2026",
      "primer_parrafo": "...",
      "firmante": "Juan Pérez",
      "imagen_firma": "firma_resolucion_001.jpg",
      "estado": "OK"
    }
  ]
}
```

---

## 🛠️ Configuración

### Variables de Entorno (.env)
```env
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=lector_resoluciones
DB_USERNAME=postgres
DB_PASSWORD=
```

### Configuración de Tesseract
En `app/Services/OCR/OCRService.php`:
```php
$this->tesseractPath = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe';
$this->tessdataDir = $baseDir . '/storage/tessdata';
```

---

## 🐛 Troubleshooting

### Error: "API PHP no está iniciada"
**Solución:** Asegúrate de que PHP está corriendo en puerto 5001
```bash
php -S 127.0.0.1:5001
```

### Error: "Conexión a PostgreSQL rechazada"
**Solución:** Verifica credenciales en `config/database.php`
```bash
psql -U postgres -h 127.0.0.1 -d lector_resoluciones
```

### Error: "Tesseract no encontrado"
**Solución:** Instala Tesseract desde: https://github.com/UB-Mannheim/tesseract/wiki

### Documentos no aparecen en "Confirmados"
**Solución:** Asegúrate de hacer clic en "Confirmar Documentos" en la vista de procesamiento

---

## 📊 Modo Debug

Para habilitar el modo debug (conserva archivos temporales):

En `app/Helpers/AppConfig.php`:
```php
define('LECTOR_DEBUG', true);
```

Los archivos debug se guardan en `storage/temp/debug/`

---

## 📝 Licencia y Autoría

Desarrollado: 2026  
Arquitectura: Modular con servicios, repositorios y controladores  
OCR Engine: Tesseract  
Base de Datos: PostgreSQL

---

## 📞 Soporte

Para reportar problemas o sugerencias:
1. Revisa los logs en `storage/temp/debug/`
2. Verifica la consola del navegador (F12)
3. Consulta `app/Services/Debug/DebugService.php`

---

**¡Listo! Tu aplicación LECTOR está lista para procesar resoluciones.** ✨
