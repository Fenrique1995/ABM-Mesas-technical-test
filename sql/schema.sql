CREATE DATABASE IF NOT EXISTS abm_mesas
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE abm_mesas;

-- Usuarios
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Mesas
CREATE TABLE mesas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ubicacion ENUM('A', 'B', 'C', 'D') NOT NULL,
    numero INT NOT NULL,
    capacidad INT NOT NULL DEFAULT 4,
    UNIQUE KEY uk_mesa (ubicacion, numero)
) ENGINE=InnoDB;

-- Reservas
CREATE TABLE reservas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    fecha DATE NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    cantidad_personas INT NOT NULL,
    cantidad_mesas TINYINT NOT NULL DEFAULT 1,
    mesas_reservadas VARCHAR(100) NOT NULL DEFAULT '',
    ubicacion ENUM('A', 'B', 'C', 'D') NOT NULL,
    estado ENUM('activa', 'cancelada') NOT NULL DEFAULT 'activa',
    cancelada_en DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_reserva_fecha_ubicacion (fecha, ubicacion),
    INDEX idx_reserva_usuario (usuario_id)
) ENGINE=InnoDB;

-- Mesas asignadas a reserva (max 3 por reserva, misma ubicacion)
CREATE TABLE reserva_mesas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reserva_id INT NOT NULL,
    mesa_id INT NOT NULL,
    FOREIGN KEY (reserva_id) REFERENCES reservas(id) ON DELETE CASCADE,
    FOREIGN KEY (mesa_id) REFERENCES mesas(id) ON DELETE CASCADE,
    UNIQUE KEY uk_reserva_mesa (reserva_id, mesa_id)
) ENGINE=InnoDB;

-- Datos de ejemplo
INSERT INTO usuarios (nombre, email, password_hash) VALUES
    ('Admin', 'admin@resto.com', '$2y$10$87PNmHbygz0UG3LxsLlHzegbvYxRJ11SnO/DCZww9YNfCU2bdXh8S');

INSERT INTO mesas (ubicacion, numero, capacidad) VALUES
    ('A', 1, 2),
    ('A', 2, 4),
    ('A', 3, 4),
    ('A', 4, 6),
    ('B', 1, 2),
    ('B', 2, 4),
    ('B', 3, 4),
    ('B', 4, 6),
    ('B', 5, 8),
    ('C', 1, 2),
    ('C', 2, 4),
    ('C', 3, 4),
    ('C', 4, 2),
    ('D', 1, 4),
    ('D', 2, 6),
    ('D', 3, 8);
