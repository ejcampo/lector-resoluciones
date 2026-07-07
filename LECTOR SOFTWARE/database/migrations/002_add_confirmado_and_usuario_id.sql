-- Migración para agregar campos de confirmación y relación con usuario
-- Fecha: 2026-07-07

-- Agregar columna confirmado para distinguir documentos procesados de confirmados
ALTER TABLE resoluciones_extraidas 
ADD COLUMN IF NOT EXISTS confirmado BOOLEAN DEFAULT FALSE;

-- Agregar columna usuario_id para rastrear qué usuario procesó cada resolución
ALTER TABLE resoluciones_extraidas 
ADD COLUMN IF NOT EXISTS usuario_id BIGINT DEFAULT NULL;

-- Crear índice para mejorar consultas por estado de confirmación
CREATE INDEX IF NOT EXISTS idx_resoluciones_confirmado
    ON resoluciones_extraidas (confirmado);

-- Crear índice para consultas por usuario
CREATE INDEX IF NOT EXISTS idx_resoluciones_usuario
    ON resoluciones_extraidas (usuario_id);

-- Comentarios para documentación
COMMENT ON COLUMN resoluciones_extraidas.confirmado IS 'Indica si el documento fue confirmado por el usuario (TRUE) o solo procesado (FALSE)';
COMMENT ON COLUMN resoluciones_extraidas.usuario_id IS 'ID del usuario que procesó la resolución';
