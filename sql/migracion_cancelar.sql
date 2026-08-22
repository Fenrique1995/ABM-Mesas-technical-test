USE abm_mesas;

ALTER TABLE reservas
    ADD COLUMN estado ENUM('activa', 'cancelada') NOT NULL DEFAULT 'activa' AFTER ubicacion,
    ADD COLUMN cancelada_en DATETIME NULL AFTER estado;
