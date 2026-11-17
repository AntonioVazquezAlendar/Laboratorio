CREATE TABLE IF NOT EXISTS solicitudes (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, paciente_id BIGINT UNSIGNED NULL, medico_id BIGINT UNSIGNED NULL,
 compania_id BIGINT UNSIGNED NULL, estado ENUM('cotizacion','registrada') NOT NULL,
 datos JSON NOT NULL, total DECIMAL(12,2) NOT NULL, version INT UNSIGNED NOT NULL DEFAULT 1,
 creado_por BIGINT UNSIGNED NOT NULL, creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(paciente_id) REFERENCES pacientes(id), FOREIGN KEY(medico_id) REFERENCES medicos(id), FOREIGN KEY(compania_id) REFERENCES companias(id),
 KEY ix_solicitud_paciente(paciente_id,id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS caja_turnos (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, usuario_id BIGINT UNSIGNED NOT NULL,
 estado ENUM('abierto','cerrado') NOT NULL DEFAULT 'abierto',
 usuario_abierto BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN estado='abierto' THEN usuario_id ELSE NULL END) STORED,
 fondo DECIMAL(12,2) NOT NULL, version INT UNSIGNED NOT NULL DEFAULT 1, resumen_cierre JSON NULL,
 abierto_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, cerrado_en TIMESTAMP NULL,
 UNIQUE KEY uq_usuario_turno(usuario_abierto), FOREIGN KEY(usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS caja_movimientos (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, turno_id BIGINT UNSIGNED NOT NULL, usuario_id BIGINT UNSIGNED NOT NULL,
 tipo ENUM('ingreso','egreso') NOT NULL, metodo ENUM('efectivo','tarjeta','transferencia') NOT NULL,
 importe DECIMAL(12,2) NOT NULL, concepto VARCHAR(300) NOT NULL, referencia VARCHAR(100) NOT NULL DEFAULT '',
 clave CHAR(36) NOT NULL, creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_movimiento_clave(clave), FOREIGN KEY(turno_id) REFERENCES caja_turnos(id)
) ENGINE=InnoDB;
