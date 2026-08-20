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
    seccion VARCHAR(50) NOT NULL DEFAULT 'General',
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
    ubicacion ENUM('A', 'B', 'C', 'D') NOT NULL,
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

INSERT INTO mesas (ubicacion, numero, capacidad, seccion) VALUES
    ('A', 1, 2, 'Patio'),
    ('A', 2, 4, 'Patio'),
    ('A', 3, 4, 'Patio'),
    ('A', 4, 6, 'Patio'),
    ('B', 1, 2, 'Interior'),
    ('B', 2, 4, 'Interior'),
    ('B', 3, 4, 'Interior'),
    ('B', 4, 6, 'Interior'),
    ('B', 5, 8, 'Interior'),
    ('C', 1, 2, 'Terraza'),
    ('C', 2, 4, 'Terraza'),
    ('C', 3, 4, 'Terraza'),
    ('C', 4, 2, 'Terraza'),
    ('D', 1, 4, 'VIP'),
    ('D', 2, 6, 'VIP'),
    ('D', 3, 8, 'VIP');
