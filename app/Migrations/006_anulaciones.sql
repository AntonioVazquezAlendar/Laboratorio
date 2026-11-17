CREATE TABLE IF NOT EXISTS solicitud_acciones (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 solicitud_id BIGINT UNSIGNED NOT NULL, pago_id BIGINT UNSIGNED NULL,
 tipo ENUM('cancelacion','devolucion') NOT NULL,
 estado ENUM('pendiente','aprobada','realizada','rechazada') NOT NULL DEFAULT 'pendiente',
 importe DECIMAL(12,2) NULL, metodo ENUM('efectivo','tarjeta','transferencia') NULL,
 motivo VARCHAR(500) NOT NULL, solicitante_id BIGINT UNSIGNED NOT NULL, solicitante_nombre VARCHAR(150) NOT NULL,
 autorizador_id BIGINT UNSIGNED NULL, autorizador_nombre VARCHAR(150) NULL, motivo_decision VARCHAR(500) NULL,
 ejecutor_id BIGINT UNSIGNED NULL, turno_id BIGINT UNSIGNED NULL, movimiento_id BIGINT UNSIGNED NULL,
 clave CHAR(36) NOT NULL, huella CHAR(64) NOT NULL, datos JSON NULL,
 creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, decidido_en TIMESTAMP NULL, realizado_en TIMESTAMP NULL,
 UNIQUE KEY uq_accion_clave(clave), UNIQUE KEY uq_accion_movimiento(movimiento_id),
 KEY ix_accion_solicitud(solicitud_id,id), KEY ix_accion_pago(pago_id,estado), KEY ix_accion_estado(estado,id),
 FOREIGN KEY(solicitud_id) REFERENCES solicitudes(id), FOREIGN KEY(pago_id) REFERENCES solicitud_pagos(id),
 FOREIGN KEY(solicitante_id) REFERENCES usuarios(id), FOREIGN KEY(autorizador_id) REFERENCES usuarios(id),
 FOREIGN KEY(ejecutor_id) REFERENCES usuarios(id), FOREIGN KEY(turno_id) REFERENCES caja_turnos(id),
 FOREIGN KEY(movimiento_id) REFERENCES caja_movimientos(id)
) ENGINE=InnoDB;
