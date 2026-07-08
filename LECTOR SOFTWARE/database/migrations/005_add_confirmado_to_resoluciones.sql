ALTER TABLE resoluciones_extraidas
ADD COLUMN IF NOT EXISTS confirmado BOOLEAN DEFAULT FALSE;

CREATE INDEX IF NOT EXISTS idx_resoluciones_confirmado
    ON resoluciones_extraidas (confirmado);
