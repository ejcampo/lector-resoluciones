-- Migración para añadir soporte de imagen de firma a las resoluciones

ALTER TABLE resoluciones_extraidas
ADD COLUMN imagen_firma VARCHAR(255) DEFAULT NULL;
