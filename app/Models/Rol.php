<?php
class Rol
{
    private $db;
    public function __construct(PDO $db) { $this->db = $db; }
    public static function catalogo()
    {
        return array(
            'administrar_accesos'=>'Administrar usuarios y roles', 'ver_bitacora'=>'Consultar bitácora',
            'ver_catalogos'=>'Consultar estudios, analitos, muestras y métodos', 'editar_catalogos'=>'Administrar estudios, analitos, muestras y métodos',
            'ver_medicos'=>'Consultar médicos', 'editar_medicos'=>'Administrar médicos',
            'ver_companias'=>'Consultar compañías', 'editar_companias'=>'Administrar compañías',
            'ver_precios'=>'Consultar precios', 'editar_precios'=>'Administrar precios',
            'ver_pacientes'=>'Consultar pacientes', 'editar_pacientes'=>'Registrar y editar pacientes',
            'ver_ordenes'=>'Consultar órdenes', 'crear_ordenes'=>'Registrar órdenes',
            'editar_solicitudes'=>'Editar solicitudes','cotizar'=>'Crear cotizaciones','imprimir_solicitudes'=>'Imprimir comprobantes y órdenes de trabajo',
            'registrar_ingresos'=>'Registrar ingresos de caja','registrar_egresos'=>'Registrar egresos de caja','cerrar_caja'=>'Realizar corte de caja',
            'ver_cobros'=>'Consultar saldos, pagos y recibos de solicitudes', 'cobrar'=>'Registrar anticipos y abonos de solicitudes', 'operar_caja'=>'Operar caja',
            'solicitar_anulaciones'=>'Solicitar cancelaciones y devoluciones', 'autorizar_anulaciones'=>'Autorizar cancelaciones y devoluciones (administrador)', 'devolver_pagos'=>'Registrar devoluciones autorizadas en caja',
            'recibir_muestras'=>'Recibir muestras', 'ver_resultados'=>'Consultar resultados',
            'capturar_resultados'=>'Capturar resultados', 'validar_resultados'=>'Validar resultados',
            'entregar_resultados'=>'Entregar resultados autorizados', 'corregir_resultados'=>'Corregir resultados con motivo'
        );
    }
    public function guardar($id, array $datos, $actorId)
    {
        $nombre = isset($datos['nombre']) && is_string($datos['nombre']) ? trim($datos['nombre']) : '';
        if (!preg_match('/^[^\x00-\x1f\x7f]{1,60}$/uD', $nombre)) { throw new HttpError('Escribe un nombre de rol de hasta 60 caracteres.'); }
        $permisos = isset($datos['permisos']) ? $datos['permisos'] : null;
        $catalogo = self::catalogo();
        if (!is_array($permisos) || count($permisos) !== count($catalogo) || array_diff(array_keys($permisos), array_keys($catalogo))) {
            throw new HttpError('La lista de permisos cambió. Recarga la página.');
        }
        foreach ($catalogo as $clave=>$etiqueta) {
            if (!array_key_exists($clave,$permisos) || !is_bool($permisos[$clave])) { throw new HttpError('Los permisos deben estar activados o desactivados.'); }
        }
        $dependencias = array('solicitar_anulaciones'=>'ver_cobros','autorizar_anulaciones'=>'administrar_accesos','devolver_pagos'=>'ver_cobros','cobrar'=>'ver_cobros','editar_solicitudes'=>'ver_ordenes','cotizar'=>'ver_ordenes','imprimir_solicitudes'=>'ver_ordenes','registrar_ingresos'=>'operar_caja','registrar_egresos'=>'operar_caja','cerrar_caja'=>'operar_caja','editar_catalogos'=>'ver_catalogos','editar_medicos'=>'ver_medicos','editar_companias'=>'ver_companias','editar_precios'=>'ver_precios','editar_pacientes'=>'ver_pacientes','crear_ordenes'=>'ver_ordenes',
            'capturar_resultados'=>'ver_resultados','validar_resultados'=>'ver_resultados',
            'entregar_resultados'=>'ver_resultados','corregir_resultados'=>'ver_resultados');
        foreach ($dependencias as $accion=>$consulta) {
            if ($permisos[$accion] && !$permisos[$consulta]) { throw new HttpError('Activa también: '.$catalogo[$consulta].'.'); }
        }
        if($permisos['cobrar']&&(!$permisos['operar_caja']||!$permisos['registrar_ingresos']))throw new HttpError('Activa Operar caja y Registrar ingresos antes de permitir cobros.');
        if($permisos['autorizar_anulaciones']&&!$permisos['ver_cobros'])throw new HttpError('Activa Consultar saldos y pagos para autorizar.');
        if($permisos['devolver_pagos']&&(!$permisos['operar_caja']||!$permisos['registrar_egresos']))throw new HttpError('Activa Operar caja y Registrar egresos para devolver pagos.');
        $this->db->beginTransaction();
        try {
            $this->db->query('SELECT id FROM roles ORDER BY id FOR UPDATE')->fetchAll();
            $this->db->query('SELECT id FROM usuarios ORDER BY id FOR UPDATE')->fetchAll();
            (new Auth($this->db))->administrador();
            $anterior = null;
            if ($id !== null) {
                $q=$this->db->prepare('SELECT * FROM roles WHERE id=?'); $q->execute(array($id)); $anterior=$q->fetch();
                if (!$anterior) { throw new HttpError('Rol no encontrado.',404); }
                // Protected administrator has no implicit clinical permissions.
                if ($anterior['protegido']) { throw new HttpError('El rol Administrador conserva sus permisos administrativos.',409); }
                if (!$permisos['administrar_accesos']) {
                    $q=$this->db->prepare('SELECT id FROM usuarios WHERE rol_id=? AND activo=1'); $q->execute(array($id));
                    $afectados=$q->fetchAll(PDO::FETCH_COLUMN);
                    if (in_array((string)$actorId,array_map('strval',$afectados),true)) { throw new HttpError('No puedes quitarte tu acceso administrativo.',409); }
                    $q=$this->db->prepare("SELECT COUNT(*) FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE u.activo=1 AND r.id<>? AND JSON_EXTRACT(r.permisos,'$.administrar_accesos')=true");
                    $q->execute(array($id));
                    if (!$q->fetchColumn()) { throw new HttpError('Debe quedar un administrador activo.',409); }
                }
                $q=$this->db->prepare('UPDATE roles SET nombre=?,permisos=? WHERE id=?'); $q->execute(array($nombre,json_encode($permisos),$id));
            } else {
                $q=$this->db->prepare('INSERT INTO roles(nombre,permisos) VALUES(?,?)'); $q->execute(array($nombre,json_encode($permisos))); $id=$this->db->lastInsertId();
            }
            Auditoria::registrar($this->db,$actorId,$anterior?'permisos_editados':'rol_creado','Rol '.$id,$nombre,
                $anterior,array('nombre'=>$nombre,'permisos'=>$permisos));
            $this->db->commit();
            return array('id'=>$id);
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            if ($e instanceof PDOException && isset($e->errorInfo[1]) && $e->errorInfo[1]===1062) { throw new HttpError('Ya existe un rol con ese nombre.',409); }
            throw $e;
        }
    }
}
