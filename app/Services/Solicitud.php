<?php
class Solicitud {
 private $db;
 public function __construct(PDO $db){$this->db=$db;}
 private function row($table,$id,$required=false){
  if($id===null||$id===''){if($required)throw new HttpError('Selecciona '.$table.'.');return null;}
  if(!is_scalar($id)||!ctype_digit((string)$id))throw new HttpError('Referencia no válida.');
  $q=$this->db->prepare('SELECT * FROM '.$table.' WHERE id=?');$q->execute(array($id));$r=$q->fetch();if(!$r||!$r['activo'])throw new HttpError('Selecciona un registro activo de '.$table.'.');return $r;
 }
 public function listar(array $f){
  $actor=(new Auth($this->db))->permiso('ver_ordenes');$where='';$p=array();
  if(isset($f['paciente_id'])){if(!is_scalar($f['paciente_id'])||!ctype_digit((string)$f['paciente_id']))throw new HttpError('Paciente no válido.');(new Auth($this->db))->permiso('ver_pacientes');$where=' WHERE paciente_id=?';$p[]=$f['paciente_id'];}
  $q=$this->db->prepare('SELECT id,paciente_id,estado,total,version,datos,creado_en FROM solicitudes'.$where.' ORDER BY id DESC LIMIT 100');$q->execute($p);$rows=$q->fetchAll();
  foreach($rows as &$r){$d=json_decode($r['datos'],true);$r['paciente_nombre']=$d['paciente_nombre'];unset($r['datos']);}unset($r);
  $this->db->beginTransaction();Auditoria::registrar($this->db,$actor['id'],'solicitud_consultada','Solicitudes','Consulta de solicitudes',null,null,array('paciente_id'=>isset($f['paciente_id'])?$f['paciente_id']:null));$this->db->commit();return $rows;
 }
 public function obtener($id,$impresion=false){
  $auth=new Auth($this->db);$actor=$auth->permiso('ver_ordenes');if($impresion)$auth->permiso('imprimir_solicitudes');
  $q=$this->db->prepare('SELECT * FROM solicitudes WHERE id=?');$q->execute(array($id));$r=$q->fetch();if(!$r)throw new HttpError('Solicitud no encontrada.',404);$r['datos']=json_decode($r['datos'],true);
  $r['resultados']=array();if(!empty($actor['permisos']['ver_resultados'])){$q=$this->db->prepare('SELECT id,estudio_id,version,estado FROM resultados WHERE solicitud_id=?');$q->execute(array($id));$r['resultados']=$q->fetchAll();}
  $this->db->beginTransaction();Auditoria::registrar($this->db,$actor['id'],$impresion?'solicitud_impresa':'solicitud_consultada','Solicitud '.$id,$impresion?'Preparación de comprobante u orden de trabajo':'Consulta de solicitud',null,null);$this->db->commit();return $r;
 }
 public function guardar(array $d,$id=null){
  $estado=isset($d['estado'])?$d['estado']:'registrada';if(!in_array($estado,array('cotizacion','registrada'),true))throw new HttpError('Estado no válido.');
  $auth=new Auth($this->db);$auth->permiso('ver_ordenes');$auth->permiso($id?'editar_solicitudes':($estado==='cotizacion'?'cotizar':'crear_ordenes'));
  if(!isset($d['estudios'])||!is_array($d['estudios'])||count($d['estudios'])<1||count($d['estudios'])>100||count($d['estudios'])!==count(array_unique($d['estudios'],SORT_REGULAR)))throw new HttpError('Selecciona entre 1 y 100 estudios, sin repetir.');
  $notas=isset($d['notas'])&&is_string($d['notas'])?trim($d['notas']):'';if(strlen($notas)>3000)throw new HttpError('Las notas son demasiado largas.');
  $this->db->beginTransaction();
  try{
   $this->db->query('SELECT id FROM roles ORDER BY id FOR SHARE')->fetchAll();$this->db->query('SELECT id FROM usuarios ORDER BY id FOR SHARE')->fetchAll();
   $actor=$auth->permiso($id?'editar_solicitudes':($estado==='cotizacion'?'cotizar':'crear_ordenes'));
   // Catalog writes lock these tables in this order too.
   foreach(array('tipos_muestra','metodos','companias','medicos','pacientes','analitos','estudios','precios') as $table)$this->db->query('SELECT id FROM '.$table.' ORDER BY id FOR SHARE')->fetchAll();
   $old=null;$previous=array();
   if($id){$q=$this->db->prepare('SELECT * FROM solicitudes WHERE id=? FOR UPDATE');$q->execute(array($id));$old=$q->fetch();if(!$old)throw new HttpError('Solicitud no encontrada.',404);if(!isset($d['version'])||(string)$d['version']!==(string)$old['version'])throw new HttpError('La solicitud cambió. Actualiza antes de guardar.',409);$previous=json_decode($old['datos'],true);if($old['estado']==='registrada'&&$estado==='cotizacion')throw new HttpError('Una solicitud registrada no vuelve a cotización.',409);if($old['estado']==='cotizacion'&&$estado==='registrada')$auth->permiso('crear_ordenes');}
   $patient=$this->row('pacientes',isset($d['paciente_id'])?$d['paciente_id']:null,$estado==='registrada');
   $doctor=$this->row('medicos',isset($d['medico_id'])?$d['medico_id']:null);
   $company=$this->row('companias',isset($d['compania_id'])?$d['compania_id']:null);
   $companyId=$company?$company['id']:null;
   $q=$this->db->prepare('SELECT estudio_id FROM resultados WHERE solicitud_id=? FOR UPDATE');$q->execute(array($id));$used=array_map('strval',$q->fetchAll(PDO::FETCH_COLUMN));
   if($used){if((string)($patient?$patient['id']:'')!==(string)$old['paciente_id'])throw new HttpError('No se cambia el paciente de una solicitud con resultados.',409);foreach($used as $usedId)if(!in_array($usedId,array_map('strval',$d['estudios']),true))throw new HttpError('No se retiran estudios que ya tienen resultados.',409);}
   $items=array();$total=0;
   foreach($d['estudios'] as $studyId){
    // Preserve historical snapshots of unchanged studies and tariff.
    $existing=null;if($old&&(string)$old['compania_id']===(string)$companyId)foreach($previous['estudios'] as $item)if((string)$item['id']===(string)$studyId)$existing=$item;
    if($existing){$items[]=$existing;$total+=Dinero::centavos($existing['precio']);continue;}
    $study=$this->row('estudios',$studyId,true);
    $q=$this->db->prepare('SELECT importe,compania_id FROM precios WHERE estudio_id=? AND activo=1 AND (compania_id=? OR compania_id IS NULL) ORDER BY compania_id IS NULL ASC LIMIT 1');$q->execute(array($studyId,$companyId));$price=$q->fetch();if(!$price)throw new HttpError('Falta precio activo para el estudio '.$study['nombre'].'.');
    $sample=$study['tipo_muestra_id']?$this->row('tipos_muestra',$study['tipo_muestra_id']):null;$method=$study['metodo_id']?$this->row('metodos',$study['metodo_id']):null;
    $q=$this->db->prepare('SELECT a.id,a.codigo,a.nombre,a.unidad,a.tipo_resultado,a.decimales,a.referencia_texto,a.activo,m.nombre metodo_nombre FROM estudio_analitos ea JOIN analitos a ON a.id=ea.analito_id LEFT JOIN metodos m ON m.id=a.metodo_id WHERE ea.estudio_id=? ORDER BY ea.orden');$q->execute(array($studyId));$analytes=$q->fetchAll();foreach($analytes as $a)if(!$a['activo'])throw new HttpError('El estudio incluye un analito inactivo. Actualiza el catálogo.');
    $items[]=array('id'=>$study['id'],'codigo'=>$study['codigo'],'nombre'=>$study['nombre'],'precio'=>$price['importe'],'tarifa_compania_id'=>$price['compania_id'],'muestra'=>$sample?$sample['nombre']:'Sin especificar','recipiente'=>$sample?$sample['recipiente']:'','metodo'=>$method?$method['nombre']:'Sin especificar','indicaciones'=>$study['indicaciones'],'tiempo_entrega'=>$study['tiempo_entrega'],'analitos'=>$analytes);$total+=Dinero::centavos($price['importe']);
   }
   if($total>999999999999)throw new HttpError('El total excede el máximo permitido.');
   $data=array('laboratorio'=>$old&&isset($previous['laboratorio'])?$previous['laboratorio']:(new Empresa($this->db))->documento(),'paciente_nombre'=>$patient?trim($patient['nombre'].' '.$patient['apellidos']):'Cotización sin paciente','expediente'=>$patient?$patient['codigo']:'','fecha_nacimiento'=>$patient?$patient['fecha_nacimiento']:null,'sexo'=>$patient?$patient['sexo']:null,'medico_nombre'=>$doctor?trim($doctor['nombre'].' '.$doctor['apellidos']):'Sin asignar','compania_nombre'=>$company?$company['nombre']:'Público general','estudios'=>$items,'notas'=>$notas);
   $values=array($patient?$patient['id']:null,$doctor?$doctor['id']:null,$companyId,$estado,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),Dinero::texto($total));
   if($id){$q=$this->db->prepare('UPDATE solicitudes SET paciente_id=?,medico_id=?,compania_id=?,estado=?,datos=?,total=?,version=version+1 WHERE id=?');$q->execute(array_merge($values,array($id)));}
   else{$q=$this->db->prepare('INSERT INTO solicitudes(paciente_id,medico_id,compania_id,estado,datos,total,creado_por) VALUES(?,?,?,?,?,?,?)');$q->execute(array_merge($values,array($actor['id'])));$id=$this->db->lastInsertId();}
   Auditoria::registrar($this->db,$actor['id'],$old?'solicitud_editada':'solicitud_creada','Solicitud '.$id,'Estado: '.$estado,null,array('id'=>$id,'total'=>Dinero::texto($total),'estado'=>$estado));$this->db->commit();return array('id'=>$id);
  }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
 }
}
