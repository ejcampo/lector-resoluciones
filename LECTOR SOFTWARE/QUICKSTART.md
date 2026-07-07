# ⚡ QUICKSTART - Iniciar LECTOR en 5 minutos

## 1️⃣ Preparar Base de Datos (1 min)

```bash
cd "LECTOR SOFTWARE"
php run-migrations.php
```

**Esperado:**
```
✓ Conectado a PostgreSQL: 127.0.0.1:5432/lector_resoluciones
✓ Ejecutadas: 4
✓ Migración completada exitosamente.
```

---

## 2️⃣ Iniciar Servidor PHP (30 seg)

```bash
cd "LECTOR SOFTWARE\public"
php -S 127.0.0.1:5001
```

**Esperado:**
```
Development Server (http://127.0.0.1:5001) started
```

---

## 3️⃣ Abrir en Navegador (30 seg)

Accede a:
```
http://127.0.0.1:5001/
```

---

## 4️⃣ Iniciar Sesión (1 min)

```
Email:       admin@admin.com
Contraseña:  (según tu configuración)
```

---

## 5️⃣ Probar Nuevas Funcionalidades

### A. Procesar un Documento
1. Arrastra un PDF o haz clic "Seleccionar archivos"
2. Haz clic "Procesar archivos"
3. Espera a que aparezca en la tabla

### B. Confirmar Documentos
1. Una vez procesado, verás botón: **"✓ CONFIRMAR DOCUMENTOS"**
2. Haz clic en él
3. ¡Automáticamente pasará a vista "Documentos Confirmados"!

### C. Ver Confirmados
1. En el menú superior, haz clic en **"✓ DOCUMENTOS CONFIRMADOS"**
2. Verás tu histórico de documentos confirmados
3. El panel lateral desaparece (solo visualización)

---

## 🎯 Características Clave

| Función | Ubicación | Tecla/Click |
|---------|-----------|------------|
| Procesar | Panel izquierdo | `[Procesar archivos]` |
| Confirmar | Tabla resultados | `[✓ CONFIRMAR DOCUMENTOS]` |
| Ver confirmados | Menú superior | `[✓ DOCUMENTOS CONFIRMADOS]` |
| Exportar Excel | Header | `[Excel↓]` |

---

## 📊 Lo que Sucede Internamente

```
Procesar → Tabla (confirmado=F) → [Confirmar] → Tabla Confirmados (confirmado=T)
```

**Base de Datos:**
- Nuevas columnas: `confirmado` (BOOLEAN) y `usuario_id` (BIGINT)
- Índices: Para búsquedas rápidas

**API:**
- `POST /api/confirmar` - Marca como confirmado
- `GET /api/resoluciones-confirmadas` - Trae confirmados

---

## ✅ Checklist

- [ ] Ejecutaste `php run-migrations.php` sin errores
- [ ] Servidor PHP corriendo en puerto 5001
- [ ] Iniciar sesión funcionando
- [ ] Procesaste al menos 1 PDF
- [ ] Botón "Confirmar" está visible y habilitado
- [ ] Tras confirmar, cambió a vista "Documentos Confirmados"
- [ ] Datos visible en tabla de confirmados

---

## 🔧 Troubleshooting

### Error: "API PHP no iniciada"
```bash
# Solución: Ejecuta en nueva terminal
cd "LECTOR SOFTWARE\public"
php -S 127.0.0.1:5001
```

### Error: "Migración rechazada"
```bash
# Solución: Verifica PostgreSQL está corriendo
psql -U postgres -d lector_resoluciones -c "SELECT 1"
```

### Documentos no aparecen
```bash
# Solución: Verifica que usuario_id coincida
# En consola del navegador (F12):
localStorage.getItem('usuario_logeado')
# Debe mostrar: {"id": 1, ...}
```

---

## 📖 Documentación Completa

Para más detalles, lee:
- `INSTALACIÓN_Y_USO.md` - Guía completa
- `CAMBIOS_REALIZADOS.md` - Detalles técnicos
- `FLUJO_DE_USUARIOS.txt` - Diagramas visuales

---

## 🚀 ¡Listo!

Ya tienes:
- ✅ Navegación de dos vistas
- ✅ Botón de confirmación
- ✅ Histórico de documentos
- ✅ Base de datos mejorada

**Disfruta procesando resoluciones!** 📄✨
