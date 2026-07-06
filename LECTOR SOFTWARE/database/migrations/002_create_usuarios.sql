CREATE TABLE IF NOT EXISTS usuarios (
    id BIGSERIAL PRIMARY KEY,
    correo VARCHAR(255) NOT NULL UNIQUE,
    contrasena VARCHAR(255) NOT NULL,
    nombre_completo VARCHAR(255),
    rol VARCHAR(50) DEFAULT 'admin',
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Insertar usuario administrador por defecto (correo: admin@admin.com, contraseña: admin123)
INSERT INTO usuarios (correo, contrasena, nombre_completo, rol)
VALUES ('admin@admin.com', '$2y$12$qtnW5tUqJeUGx./oBLCbDecZC3iyltYBL38gG5B2/MU91jYlGxe.e', 'Administrador del Sistema', 'admin')
ON CONFLICT (correo) DO NOTHING;
