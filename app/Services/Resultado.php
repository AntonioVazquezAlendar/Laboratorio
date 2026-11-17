<?php
class Resultado
{
 private $db;
 public function __construct(PDO $db){$this->db=$db;}
 private function texto(array $d,$k,$max,$required=true){$v=isset($d[$k])&&is_string($d[$k])?trim($d[$k]):'';if(($required&&$v==='')||strlen($v)>$max)throw new HttpError('Revisa '.$k.' (máximo '.$max.' bytes).');return $v;}
 private function actor($permission){$auth=new Auth($this->db);$auth->permiso('ver_resultados');return $auth->permiso($permission);}
 private function locks(){ $this->db->query('SELECT id FROM roles ORDER BY id FOR SHARE')->fetchAll();$this->db->query('SELECT id FROM usuarios ORDER BY id FOR SHARE')->fetchAll(); }
 public function plantilla($solicitudId,$estudioId){
  $this->db->beginTransaction();try{$actor=$this->actor('capturar_resultados');$template=$this->template($solicitudId,$estudioId);
   Auditoria::registrar($this->db,$actor['id'],'resultado_plantilla','Solicitud '.$solicitudId,'Consulta de plantilla para captura',null,null,array('estudio_id'=>$estudioId));$this->db->commit();return $template;
  }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
 }
 private function template($solicitudId,$estudioId){
  foreach(array($solicitudId,$estudioId) as $id)if(!is_scalar($id)||!ctype_digit((string)$id)||(int)$id<1)throw new HttpError('Selecciona solicitud y estudio válidos.');
  $q=$this->db->prepare('SELECT * FROM solicitudes WHERE id=?');$q->execute(array($solicitudId));$s=$q->fetch();
  if(!$s||$s['estado']!=='registrada')throw new HttpError('Solo se capturan resultados de solicitudes registradas.',409);
  $snapshot=json_decode($s['datos'],true);$study=null;foreach($snapshot['estudios'] as $item)if((string)$item['id']===(string)$estudioId)$study=$item;
  if(!$study)throw new HttpError('El estudio no pertenece a esta solicitud.');
  if(empty($study['analitos']))throw new HttpError('El estudio no tiene analitos configurados. Configura el estudio y vuelve a registrarlo en la solicitud.',409);
  $q=$this->db->prepare('SELECT codigo,nombre,apellidos,fecha_nacimiento,sexo FROM pacientes WHERE id=?');$q->execute(array($s['paciente_id']));$patient=$q->fetch();
  if(!$patient)throw new HttpError('La solicitud no tiene un paciente válido.',409);
  $context=array('solicitud_id'=>$s['id'],'fecha_solicitud'=>$s['creado_en'],'laboratorio'=>isset($snapshot['laboratorio'])?$snapshot['laboratorio']:(new Empresa($this->db))->documento(),
   'paciente'=>array('id'=>$s['paciente_id'],'nombre'=>$snapshot['paciente_nombre'],'expediente'=>$snapshot['expediente'],'fecha_nacimiento'=>array_key_exists('fecha_nacimiento',$snapshot)?$snapshot['fecha_nacimiento']:$patient['fecha_nacimiento'],'sexo'=>isset($snapshot['sexo'])?$snapshot['sexo']:$patient['sexo']),
   'medico'=>$snapshot['medico_nombre'],'estudio'=>array_intersect_key($study,array_flip(array('id','codigo','nombre','muestra','metodo'))));
  $rows=array();foreach($study['analitos'] as $a)$rows[]=array('analito_id'=>$a['id'],'codigo'=>$a['codigo'],'nombre'=>$a['nombre'],'unidad'=>$a['unidad'],'tipo_resultado'=>$a['tipo_resultado'],'decimales'=>(int)$a['decimales'],
   'metodo'=>!empty($a['metodo_nombre'])?$a['metodo_nombre']:$study['metodo'],'referencia'=>isset($a['referencia_texto'])?(string)$a['referencia_texto']:'','valor'=>'','comentario'=>'');
  return array('referencia'=>'SOL-'.str_pad((string)$s['id'],6,'0',STR_PAD_LEFT).' / '.$study['codigo'],'analitos'=>$rows,'contexto'=>$context);
 }
 private function valores(array $template,array $d){
  if(!isset($d['valores'])||!is_array($d['valores'])||count($d['valores'])!==count($template))throw new HttpError('Envía un valor por cada analito del estudio. Puedes dejar valores pendientes en el borrador.');
  $input=array();foreach($d['valores'] as $row){if(!is_array($row)||!isset($row['analito_id'])||!is_scalar($row['analito_id'])||!ctype_digit((string)$row['analito_id'])||(int)$row['analito_id']<1)throw new HttpError('Analito no válido.');$id=(string)(int)$row['analito_id'];if(isset($input[$id]))throw new HttpError('No repitas analitos.');$input[$id]=$row;}
  $out=array();foreach($template as $a){$id=(string)(int)$a['analito_id'];if(!isset($input[$id]))throw new HttpError('La lista de analitos no corresponde al estudio.');$row=$input[$id];
   if(!isset($row['valor'])||(!is_string($row['valor'])&&!is_int($row['valor'])))throw new HttpError('Escribe el valor de '.$a['nombre'].'.');$value=trim((string)$row['valor']);
   if($value!==''&&$a['tipo_resultado']==='numerico'){
    $value=str_replace(',','.',$value);if(!preg_match('/^-?(?:0|[1-9][0-9]{0,11})(?:\.[0-9]{1,6})?$/D',$value))throw new HttpError('El valor de '.$a['nombre'].' debe ser numérico, sin separadores de miles.');
    $parts=explode('.',$value);if(isset($parts[1])&&strlen($parts[1])>(int)$a['decimales'])throw new HttpError($a['nombre'].' admite hasta '.$a['decimales'].' decimales.');
   }elseif(strlen($value)>($a['tipo_resultado']==='cualitativo'?200:2000))throw new HttpError('El resultado de '.$a['nombre'].' es demasiado largo.');
   $a['valor']=$value;$a['comentario']=$this->texto($row,'comentario',500,false);$out[]=$a;
  }return $out;
 }
 public function consultar($id=null){
  $this->db->beginTransaction();try{
   $actor=(new Auth($this->db))->permiso('ver_resultados');$draft=!empty($actor['permisos']['capturar_resultados'])||!empty($actor['permisos']['validar_resultados'])||!empty($actor['permisos']['corregir_resultados']);
   if($id===null){$rows=$this->db->query('SELECT id,referencia,version,estado,actualizado_en FROM resultados'.($draft?'':" WHERE estado='validado'").' ORDER BY id DESC LIMIT 100')->fetchAll();}
   else{
    $q=$this->db->prepare('SELECT * FROM resultados WHERE id=?');$q->execute(array($id));$rows=$q->fetch();if(!$rows)throw new HttpError('Resultado no encontrado.',404);if(!$draft&&$rows['estado']!=='validado')throw new HttpError('El resultado aún no está autorizado para consulta.',403);
    $q=$this->db->prepare('SELECT v.version,v.contenido,v.datos_analitos,v.contexto,v.motivo,v.usuario_id,v.creado_en,a.usuario_id validado_por,a.creado_en validado_en,COALESCE(a.usuario_nombre,u.nombre) validado_nombre FROM resultado_versiones v LEFT JOIN resultado_validaciones a ON a.resultado_id=v.resultado_id AND a.version=v.version LEFT JOIN usuarios u ON u.id=a.usuario_id WHERE v.resultado_id=? ORDER BY v.version DESC');$q->execute(array($id));$rows['historial']=$q->fetchAll();
    foreach($rows['historial'] as &$v){$v['analitos']=$v['datos_analitos']!==null?json_decode($v['datos_analitos'],true):null;$v['contexto']=$v['contexto']!==null?json_decode($v['contexto'],true):null;unset($v['datos_analitos']);}unset($v);
    if(!$draft)$rows['historial']=array_values(array_filter($rows['historial'],function($v)use($rows){return (string)$v['version']===(string)$rows['version'];}));
    $q=$this->db->prepare('SELECT version,usuario_id,destinatario,creado_en FROM resultado_entregas WHERE resultado_id=? ORDER BY id DESC');$q->execute(array($id));$rows['entregas']=$q->fetchAll();
   }
   Auditoria::registrar($this->db,$actor['id'],'resultado_consultado',$id?'Resultado '.$id:'Resultados','Consulta de resultados',null,null,array('alcance'=>$id?'historial':'lista'));$this->db->commit();return $rows;
  }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
 }
 public function operar($accion,$id,array $d){
  $map=array('capturar'=>'capturar_resultados','corregir'=>'corregir_resultados','validar'=>'validar_resultados','entregar'=>'entregar_resultados');if(!isset($map[$accion]))throw new HttpError('Acción no encontrada.',404);
  $this->db->beginTransaction();try{
   $this->locks();$actor=$this->actor($map[$accion]);$r=null;$context=null;$analytes=null;$previous=null;
   if($id===null){
    if($accion!=='capturar')throw new HttpError('Selecciona un resultado.');
    $sol=isset($d['solicitud_id'])?$d['solicitud_id']:null;$study=isset($d['estudio_id'])?$d['estudio_id']:null;
    $q=$this->db->prepare('SELECT id FROM solicitudes WHERE id=? FOR UPDATE');$q->execute(array($sol));
    $template=$this->template($sol,$study);$analytes=$this->valores($template['analitos'],$d);$context=$template['contexto'];$ref=$template['referencia'];
    $q=$this->db->prepare('INSERT INTO resultados(referencia,creado_por,solicitud_id,estudio_id) VALUES(?,?,?,?)');$q->execute(array($ref,$actor['id'],$sol,$study));$id=$this->db->lastInsertId();$version=1;$reason='Captura inicial';
   }else{
    $q=$this->db->prepare('SELECT * FROM resultados WHERE id=? FOR UPDATE');$q->execute(array($id));$r=$q->fetch();if(!$r)throw new HttpError('Resultado no encontrado.',404);
    if(!isset($d['version'])||(string)$d['version']!==(string)$r['version'])throw new HttpError('El resultado cambió. Actualiza antes de continuar.',409);
    $q=$this->db->prepare('SELECT * FROM resultado_versiones WHERE resultado_id=? AND version=?');$q->execute(array($id,$r['version']));$previous=$q->fetch();
    if($accion==='capturar'||$accion==='corregir'){
     if($accion==='capturar'&&$r['estado']!=='borrador')throw new HttpError('Un resultado validado requiere permiso de corrección y motivo.',409);
     if($previous['datos_analitos']!==null){$analytes=$this->valores(json_decode($previous['datos_analitos'],true),$d);$context=json_decode($previous['contexto'],true);}
     $version=$r['version']+1;$reason=$this->texto($d,'motivo',500);
    }elseif($accion==='validar'){
     if($r['estado']!=='borrador')throw new HttpError('Esta versión ya fue validada.',409);
     if($previous['datos_analitos']!==null){foreach(json_decode($previous['datos_analitos'],true) as $a)if(trim($a['valor'])==='')throw new HttpError('Completa '.$a['nombre'].' antes de validar.',409);}
     $q=$this->db->prepare('INSERT INTO resultado_validaciones(resultado_id,version,usuario_id,usuario_nombre) VALUES(?,?,?,?)');$q->execute(array($id,$r['version'],$actor['id'],$actor['nombre']));
     $q=$this->db->prepare("UPDATE resultados SET estado='validado' WHERE id=?");$q->execute(array($id));
    }else{
     if($r['estado']!=='validado')throw new HttpError('Solo se entregan resultados validados.',409);$recipient=$this->texto($d,'destinatario',150);
     $q=$this->db->prepare('INSERT INTO resultado_entregas(resultado_id,version,usuario_id,destinatario) VALUES(?,?,?,?)');$q->execute(array($id,$r['version'],$actor['id'],$recipient));
    }
   }
   if($accion==='capturar'||$accion==='corregir'){
    $content=$this->texto($d,'contenido',60000,$analytes===null);
    if($context!==null){$context['capturado_por']=$actor['nombre'];$context['capturado_usuario_id']=$actor['id'];}
    $q=$this->db->prepare('INSERT INTO resultado_versiones(resultado_id,version,contenido,datos_analitos,contexto,motivo,usuario_id) VALUES(?,?,?,?,?,?,?)');$q->execute(array($id,$version,$content,$analytes!==null?json_encode($analytes,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR):null,$context!==null?json_encode($context,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR):null,$reason,$actor['id']));
    if($r){$q=$this->db->prepare("UPDATE resultados SET version=?,estado='borrador' WHERE id=?");$q->execute(array($version,$id));}
   }
   Auditoria::registrar($this->db,$actor['id'],'resultado_'.$accion,'Resultado '.$id,'Operación: '.$accion,null,array('resultado_id'=>$id),array('version_anterior'=>$r?$r['version']:null));$this->db->commit();return array('id'=>$id);
  }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();if($e instanceof PDOException&&isset($e->errorInfo[1])&&(int)$e->errorInfo[1]===1062)throw new HttpError('Ya existe un resultado para este estudio o la operación ya fue registrada.',409);throw $e;}
 }
 public function informe($id,$version){
  $this->db->beginTransaction();try{
   $this->locks();$actor=(new Auth($this->db))->permiso('ver_resultados');
   $q=$this->db->prepare('SELECT * FROM resultados WHERE id=? FOR UPDATE');$q->execute(array($id));$r=$q->fetch();if(!$r)throw new HttpError('Resultado no encontrado.',404);
   if($r['estado']!=='validado'||!is_scalar($version)||(string)$version!==(string)$r['version'])throw new HttpError('Solo se emite el informe de la versión vigente validada. Actualiza la consulta.',409);
   $q=$this->db->prepare('SELECT v.*,a.creado_en validado_en,COALESCE(a.usuario_nombre,u.nombre) validado_nombre FROM resultado_versiones v JOIN resultado_validaciones a ON a.resultado_id=v.resultado_id AND a.version=v.version JOIN usuarios u ON u.id=a.usuario_id WHERE v.resultado_id=? AND v.version=?');$q->execute(array($id,$version));$v=$q->fetch();
   if(!$v||$v['datos_analitos']===null||$v['contexto']===null)throw new HttpError('Este resultado histórico no tiene una captura estructurada con paciente para emitir el nuevo informe.',409);
   $v['analitos']=json_decode($v['datos_analitos'],true);$v['contexto']=json_decode($v['contexto'],true);$v['referencia']=$r['referencia'];
   $pdf=InformeResultado::generar($v);
   Auditoria::registrar($this->db,$actor['id'],'informe_pdf_generado','Resultado '.$id,'Informe de resultados PDF',null,array('version'=>$version));$this->db->commit();return $pdf;
  }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
 }
}
