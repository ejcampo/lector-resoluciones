-- Añadir la columna usuario_id
ALTER TABLE resoluciones_extraidas
ADD COLUMN IF NOT EXISTS usuario_id BIGINT;

-- Añadir la relación de clave foránea si no existe
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.table_constraints
        WHERE constraint_name = 'fk_resoluciones_usuario'
    ) THEN
        ALTER TABLE resoluciones_extraidas
        ADD CONSTRAINT fk_resoluciones_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE;
    END IF;
END $$;
