CREATE TABLE IF NOT EXISTS resoluciones_extraidas (
    id BIGSERIAL PRIMARY KEY,
    archivo VARCHAR(255) NOT NULL UNIQUE,
    numero_resolucion VARCHAR(80) DEFAULT '',
    primer_parrafo TEXT DEFAULT '',
    firmante VARCHAR(255) DEFAULT '',
    estado VARCHAR(30) DEFAULT '',
    mensaje TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_resoluciones_numero
    ON resoluciones_extraidas (numero_resolucion);

CREATE INDEX IF NOT EXISTS idx_resoluciones_estado
    ON resoluciones_extraidas (estado);
