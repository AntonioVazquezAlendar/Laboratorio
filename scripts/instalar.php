<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../app/Config/Database.php';
require_once __DIR__.'/../app/Models/Rol.php';
$localPath=__DIR__.'/../app/Config/database.local.php';
$local=is_file($localPath)?require $localPath:array();
$name=getenv('LAB_DB_NAME') ?: (isset($local['database'])?$local['database']:'laboratorio_clinico');
if(!preg_match('/^laboratorio_[a-z0-9_]+$/D',$name))throw new RuntimeException('Nombre de base del laboratorio no permitido.');
$server=(new Database())->connect(true);
$server->exec('CREATE DATABASE IF NOT EXISTS `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$db=(new Database())->connect();
// No sustituir fechas faltantes por una fecha ficticia al actualizar instalaciones anteriores.
$q=$db->query("SHOW TABLES LIKE 'pacientes'");
if($q->fetchColumn()){
 $missing=(int)$db->query("SELECT COUNT(*) FROM pacientes WHERE fecha_nacimiento IS NULL OR fecha_nacimiento<'1800-01-01'")->fetchColumn();
 if($missing)throw new RuntimeException('Hay '.$missing.' pacientes sin fecha de nacimiento valida (nula o anterior a 1800-01-01). Completa sus fechas reales antes de aplicar la actualizacion; no se asignaron fechas automaticamente.');
}
foreach (glob(__DIR__.'/../app/Migrations/*.sql') as $migration) {
 $sql=file_get_contents($migration);
 foreach (explode(';',$sql) as $statement) { if (trim($statement)!=='') { $db->exec($statement); } }
}
$birthColumn=$db->query("SHOW COLUMNS FROM pacientes LIKE 'fecha_nacimiento'")->fetch();
$birthChanges=array();
if($birthColumn['Null']==='YES')$birthChanges[]='MODIFY fecha_nacimiento DATE NOT NULL';
$birthCheck=$db->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='pacientes' AND CONSTRAINT_NAME='ck_paciente_fecha_nacimiento' AND CONSTRAINT_TYPE='CHECK'")->fetchColumn();
// Impedir tambien la fecha cero que MySQL permisivo puede sustituir por un NULL.
if(!$birthCheck)$birthChanges[]="ADD CONSTRAINT ck_paciente_fecha_nacimiento CHECK (fecha_nacimiento >= '1800-01-01')";
if($birthChanges)$db->exec('ALTER TABLE pacientes '.implode(',',$birthChanges));
$columns=$db->query("SHOW COLUMNS FROM resultados LIKE 'solicitud_id'")->fetchAll();
if (!$columns) { $db->exec('ALTER TABLE resultados ADD solicitud_id BIGINT UNSIGNED NULL, ADD estudio_id BIGINT UNSIGNED NULL, ADD UNIQUE KEY uq_resultado_solicitud_estudio(solicitud_id,estudio_id), ADD FOREIGN KEY(solicitud_id) REFERENCES solicitudes(id), ADD FOREIGN KEY(estudio_id) REFERENCES estudios(id)'); }
foreach (array(
 'analitos'=>array('referencia_texto'=>"TEXT NULL"),
 'resultado_versiones'=>array('datos_analitos'=>'JSON NULL','contexto'=>'JSON NULL'),
 'resultado_validaciones'=>array('usuario_nombre'=>'VARCHAR(150) NULL')
) as $table=>$changes) {
 foreach($changes as $column=>$type) { $q=$db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');$q->execute(array($table,$column));if(!$q->fetchColumn())$db->exec('ALTER TABLE '.$table.' ADD '.$column.' '.$type); }
}
$stateColumn=$db->query("SHOW COLUMNS FROM solicitudes LIKE 'estado'")->fetch();
if(strpos($stateColumn['Type'],"'cancelada'")===false)$db->exec("ALTER TABLE solicitudes MODIFY estado ENUM('cotizacion','registrada','cancelada') NOT NULL");
$roles=array(
 'Administrador'=>array('solicitar_anulaciones','autorizar_anulaciones','devolver_pagos','ver_cobros','cobrar','administrar_accesos','ver_bitacora','ver_catalogos','editar_catalogos','ver_pacientes','editar_pacientes','ver_medicos','editar_medicos','ver_companias','editar_companias','ver_precios','editar_precios','ver_ordenes','crear_ordenes','editar_solicitudes','cotizar','imprimir_solicitudes','operar_caja','registrar_ingresos','registrar_egresos','cerrar_caja'),
 'Recepción'=>array('solicitar_anulaciones','ver_cobros','ver_catalogos','ver_medicos','editar_medicos','ver_companias','ver_precios','ver_pacientes','editar_pacientes','ver_ordenes','crear_ordenes','editar_solicitudes','cotizar','imprimir_solicitudes','ver_resultados','capturar_resultados','entregar_resultados'),
 'Caja'=>array('solicitar_anulaciones','devolver_pagos','ver_cobros','ver_precios','cobrar','operar_caja','registrar_ingresos','registrar_egresos','cerrar_caja'),
 'Laboratorista'=>array('ver_catalogos','ver_ordenes','recibir_muestras','ver_resultados','capturar_resultados'),
 'Responsable del laboratorio'=>array('ver_catalogos','editar_catalogos','ver_ordenes','ver_resultados','validar_resultados','corregir_resultados')
);
$db->exec('CREATE TABLE IF NOT EXISTS schema_migrations (clave VARCHAR(80) PRIMARY KEY, aplicado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
$db->beginTransaction();
try {
 foreach ($roles as $nombre=>$claves) {
  $q=$db->prepare('SELECT id FROM roles WHERE nombre=?'); $q->execute(array($nombre));
  if ($existingId=$q->fetchColumn()) {
   $q=$db->prepare('SELECT permisos FROM roles WHERE id=? FOR UPDATE'); $q->execute(array($existingId)); $existing=json_decode($q->fetchColumn(),true);
   $merged=array_fill_keys(array_keys(Rol::catalogo()),false);
   foreach ($merged as $key=>&$value) { $value=array_key_exists($key,$existing)?$existing[$key]:in_array($key,$claves,true); } unset($value);
   $q=$db->prepare('UPDATE roles SET permisos=? WHERE id=?'); $q->execute(array(json_encode($merged),$existingId)); continue;
  }
  $permisos=array_fill_keys(array_keys(Rol::catalogo()),false);
  foreach ($claves as $clave) { $permisos[$clave]=true; }
  $q=$db->prepare('INSERT INTO roles(nombre,permisos,protegido) VALUES(?,?,?)');
  $q->execute(array($nombre,json_encode($permisos),$nombre==='Administrador'?1:0));
 }
 if (!$db->query("SELECT clave FROM schema_migrations WHERE clave='recepcion_captura_20261117'")->fetchColumn()) {
  $q=$db->prepare('SELECT id,permisos FROM roles WHERE nombre=? FOR UPDATE'); $q->execute(array('Recepción')); $reception=$q->fetch();
  if ($reception) { $rp=json_decode($reception['permisos'],true); $rp['capturar_resultados']=true; $q=$db->prepare('UPDATE roles SET permisos=? WHERE id=?'); $q->execute(array(json_encode($rp),$reception['id'])); }
  $q=$db->prepare('SELECT id,permisos FROM roles WHERE nombre=? AND protegido=1 FOR UPDATE'); $q->execute(array('Administrador')); $admin=$q->fetch();
  if ($admin) { $ap=json_decode($admin['permisos'],true); foreach(array('ver_ordenes','crear_ordenes','operar_caja') as $key)$ap[$key]=true; $q=$db->prepare('UPDATE roles SET permisos=? WHERE id=?'); $q->execute(array(json_encode($ap),$admin['id'])); }
  $db->exec("INSERT INTO schema_migrations(clave) VALUES('recepcion_captura_20261117')");
 }
 if (!$db->query("SELECT clave FROM schema_migrations WHERE clave='catalogos_admin_pacientes_20261117'")->fetchColumn()) {
  $q=$db->prepare('SELECT id,permisos FROM roles WHERE nombre=? AND protegido=1 FOR UPDATE'); $q->execute(array('Administrador')); $admin=$q->fetch();
  if ($admin) { $ap=json_decode($admin['permisos'],true); $ap['ver_pacientes']=true; $ap['editar_pacientes']=true; $q=$db->prepare('UPDATE roles SET permisos=? WHERE id=?'); $q->execute(array(json_encode($ap),$admin['id'])); }
  $db->exec("INSERT INTO schema_migrations(clave) VALUES('catalogos_admin_pacientes_20261117')");
 }
 if (!$db->query("SELECT clave FROM schema_migrations WHERE clave='cobros_solicitudes_20261117'")->fetchColumn()) {
  $q=$db->query("SELECT id,nombre,permisos FROM roles WHERE nombre IN ('Administrador','Recepción','Caja') FOR UPDATE");
  foreach($q->fetchAll() as $row){$perms=json_decode($row['permisos'],true);$perms['ver_cobros']=true;if($row['nombre']==='Administrador')$perms['cobrar']=true;$update=$db->prepare('UPDATE roles SET permisos=? WHERE id=?');$update->execute(array(json_encode($perms),$row['id']));}
  $db->exec("INSERT INTO schema_migrations(clave) VALUES('cobros_solicitudes_20261117')");
 }
 $custom=$db->query('SELECT id,permisos FROM roles')->fetchAll();
 foreach ($custom as $row) { $old=json_decode($row['permisos'],true); $merged=array_merge(array_fill_keys(array_keys(Rol::catalogo()),false),$old); $q=$db->prepare('UPDATE roles SET permisos=? WHERE id=?'); $q->execute(array(json_encode($merged),$row['id'])); }
 $db->commit(); echo "Base del laboratorio preparada. Crea la primera cuenta desde el navegador de este equipo.\n";
} catch(Throwable $e) { $db->rollBack(); throw $e; }
