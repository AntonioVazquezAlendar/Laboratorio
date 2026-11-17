CREATE TABLE IF NOT EXISTS solicitud_pagos (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 solicitud_id BIGINT UNSIGNED NOT NULL, turno_id BIGINT UNSIGNED NOT NULL,
 movimiento_id BIGINT UNSIGNED NOT NULL, usuario_id BIGINT UNSIGNED NOT NULL,
 tipo ENUM('anticipo','abono','liquidacion') NOT NULL, importe DECIMAL(12,2) NOT NULL,
 metodo ENUM('efectivo','tarjeta','transferencia') NOT NULL, recibido DECIMAL(12,2) NULL,
 cambio DECIMAL(12,2) NOT NULL DEFAULT 0, referencia VARCHAR(100) NOT NULL DEFAULT '', notas VARCHAR(300) NOT NULL DEFAULT '',
 clave CHAR(36) NOT NULL, huella CHAR(64) NOT NULL, datos JSON NOT NULL,
 creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_pago_clave(clave), UNIQUE KEY uq_pago_movimiento(movimiento_id),
 KEY ix_pagos_solicitud(solicitud_id,id), KEY ix_pagos_turno(turno_id,id),
 FOREIGN KEY(solicitud_id) REFERENCES solicitudes(id), FOREIGN KEY(turno_id) REFERENCES caja_turnos(id),
 FOREIGN KEY(movimiento_id) REFERENCES caja_movimientos(id), FOREIGN KEY(usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;
