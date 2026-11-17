<?php
// Requests, administrator decisions and cash execution are separate, durable steps.
class Anulacion
{
 private $db;
 public function __construct(PDO $db){$this->db=$db;}
 private function actor($permission='ver_cobros'){$a=new Auth($this->db);$a->permiso('ver_cobros');return $a->permiso($permission);}
 private function locks(){$this->db->query('SELECT id FROM roles ORDER BY id FOR SHARE')->fetchAll();$this->db->query('SELECT id FROM usuarios ORDER BY id FOR UPDATE')->fetchAll();}
 private function motivo(array $d,$key='motivo'){$s=isset($d[$key])&&is_string($d[$key])?trim($d[$key]):'';if($s===''||strlen($s)>500||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$s))throw new HttpError('Escribe un motivo de hasta 500 bytes.');return $s;}
 private function id($v){if(!is_scalar($v)||!ctype_digit((string)$v)||(int)$v<1)throw new HttpError('Selecciona un registro válido.');return (string)(int)$v;}
 private function solicitud($id){$q=$this->db->prepare('SELECT * FROM solicitudes WHERE id=? FOR UPDATE');$q->execute(array($id));$s=$q->fetch();if(!$s)throw new HttpError('Solicitud no encontrada.',404);return $s;}
 private function accion($id){$q=$this->db->prepare('SELECT * FROM solicitud_acciones WHERE id=? FOR UPDATE');$q->execute(array($id));$a=$q->fetch();if(!$a)throw new HttpError('Petición no encontrada.',404);return $a;}
 private function version(array $s,array $d){if(!isset($d['version'])||(string)$s['version']!==(string)$d['version'])throw new HttpError('La solicitud cambió. Actualiza antes de continuar.',409);}
 public static function filas(PDO $db,$id){$q=$db->prepare('SELECT a.*,s.version solicitud_version,s.estado solicitud_estado FROM solicitud_acciones a JOIN solicitudes s ON s.id=a.solicitud_id WHERE a.solicitud_id=? ORDER BY a.id DESC');$q->execute(array($id));$rows=$q->fetchAll();foreach($rows as &$r){unset($r['huella'],$r['clave'],$r['datos']);}unset($r);return $rows;}
 public function listar(array $f){
  $this->actor();$page=isset($f['pagina'])?$f['pagina']:'1';if(!is_scalar($page)||!ctype_digit((string)$page)||(int)$page<1||(int)$page>100000)throw new HttpError('Página no válida.');
  $state=isset($f['estado'])?$f['estado']:'pendiente';if(!in_array($state,array('pendiente','aprobada','realizada','rechazada','todas'),true))throw new HttpError('Filtro no válido.');
  $sql='SELECT a.*,s.version solicitud_version,s.estado solicitud_estado FROM solicitud_acciones a JOIN solicitudes s ON s.id=a.solicitud_id';$params=array();if($state!=='todas'){$sql.=' WHERE a.estado=?';$params[]=$state;}$sql.=' ORDER BY a.id DESC LIMIT 26 OFFSET '.(((int)$page-1)*25);$q=$this->db->prepare($sql);$q->execute($params);$rows=$q->fetchAll();$more=count($rows)>25;if($more)array_pop($rows);foreach($rows as &$r){unset($r['huella'],$r['clave'],$r['datos']);}unset($r);
  return array('registros'=>$rows,'pagina'=>(int)$page,'hay_mas'=>$more);
 }
 public function solicitar($id,array $d){
  $id=$this->id($id);$reason=$this->motivo($d);$type=isset($d['tipo'])?$d['tipo']:null;if(!in_array($type,array('cancelacion','devolucion'),true))throw new HttpError('Selecciona cancelación o devolución.');
  $key=isset($d['clave'])?$d['clave']:null;if(!is_string($key)||!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/iD',$key))throw new HttpError('Falta una clave válida de la petición.');$key=strtolower($key);
  $payment=$type==='devolucion'?$this->id(isset($d['pago_id'])?$d['pago_id']:null):null;$amount=$type==='devolucion'?Dinero::centavos(isset($d['importe'])?$d['importe']:''):null;if($amount!==null&&$amount<=0)throw new HttpError('La devolución debe ser mayor que cero.');
  $this->db->beginTransaction();try{
   $this->locks();$u=$this->actor('solicitar_anulaciones');$hash=hash('sha256',json_encode(array($id,$type,$payment,$amount,$reason,(string)$u['id']),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
   $q=$this->db->prepare('SELECT id,huella FROM solicitud_acciones WHERE clave=? FOR UPDATE');$q->execute(array($key));$prior=$q->fetch();if($prior){if(!hash_equals($prior['huella'],$hash))throw new HttpError('La clave ya corresponde a otra petición.',409);$this->db->commit();return array('id'=>$prior['id'],'repetido'=>true);}
   $s=$this->solicitud($id);$this->version($s,$d);$method=null;
   if($type==='cancelacion'){
    if($s['estado']==='cancelada')throw new HttpError('La solicitud ya está cancelada.',409);
    $q=$this->db->prepare("SELECT id FROM solicitud_acciones WHERE solicitud_id=? AND tipo='cancelacion' AND estado='pendiente'");$q->execute(array($id));if($q->fetchColumn())throw new HttpError('Ya hay una cancelación pendiente de autorización.',409);
   }else{
    if($s['estado']==='cotizacion')throw new HttpError('Una cotización no tiene pagos para devolver.',409);
    $q=$this->db->prepare('SELECT * FROM solicitud_pagos WHERE id=? AND solicitud_id=? FOR UPDATE');$q->execute(array($payment,$id));$p=$q->fetch();if(!$p)throw new HttpError('El pago no pertenece a esta solicitud.',409);
    $q=$this->db->prepare("SELECT COALESCE(SUM(importe),0) FROM solicitud_acciones WHERE pago_id=? AND estado<>'rechazada'");$q->execute(array($payment));$reserved=Dinero::centavos($q->fetchColumn());if($amount>Dinero::centavos($p['importe'])-$reserved)throw new HttpError('El importe supera lo disponible del pago, considerando devoluciones pendientes y realizadas.',409);$method=$p['metodo'];
   }
   $q=$this->db->prepare('INSERT INTO solicitud_acciones(solicitud_id,pago_id,tipo,importe,metodo,motivo,solicitante_id,solicitante_nombre,clave,huella) VALUES(?,?,?,?,?,?,?,?,?,?)');$q->execute(array($id,$payment,$type,$amount!==null?Dinero::texto($amount):null,$method,$reason,$u['id'],$u['nombre'],$key,$hash));$action=$this->db->lastInsertId();
   Auditoria::registrar($this->db,$u['id'],'anulacion_solicitada','Petición '.$action,$reason,null,array('solicitud_id'=>$id,'tipo'=>$type,'pago_id'=>$payment,'importe'=>$amount!==null?Dinero::texto($amount):null));$this->db->commit();return array('id'=>$action,'repetido'=>false);
  }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
 }
 public function decidir($id,array $d){
  $id=$this->id($id);$reason=$this->motivo($d);$decision=isset($d['decision'])?$d['decision']:null;if(!in_array($decision,array('aprobar','rechazar'),true))throw new HttpError('Selecciona una decisión.');
  $this->db->beginTransaction();try{
   $this->locks();$u=$this->actor('autorizar_anulaciones');(new Auth($this->db))->administrador();$a=$this->accion($id);$s=$this->solicitud($a['solicitud_id']);
   if($a['estado']!=='pendiente')throw new HttpError('La petición ya fue resuelta. Actualiza la lista.',409);$this->version($s,$d);
   $state='rechazada';if($decision==='aprobar'){
    if($a['tipo']==='cancelacion'){
     if($s['estado']==='cancelada')throw new HttpError('La solicitud ya está cancelada.',409);
     $q=$this->db->prepare("UPDATE solicitudes SET estado='cancelada',version=version+1 WHERE id=?");$q->execute(array($s['id']));$state='realizada';
    }else $state='aprobada';
   }
   $q=$this->db->prepare('UPDATE solicitud_acciones SET estado=?,autorizador_id=?,autorizador_nombre=?,motivo_decision=?,decidido_en=CURRENT_TIMESTAMP,realizado_en=IF(?=\'realizada\',CURRENT_TIMESTAMP,NULL) WHERE id=?');$q->execute(array($state,$u['id'],$u['nombre'],$reason,$state,$id));
   Auditoria::registrar($this->db,$u['id'],'anulacion_'.$decision,'Petición '.$id,$reason,null,array('solicitud_id'=>$s['id'],'tipo'=>$a['tipo'],'estado'=>$state));$this->db->commit();return array('id'=>$id,'estado'=>$state);
  }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
 }
 public function ejecutar($id,array $d){
  $id=$this->id($id);$turn=$this->id(isset($d['turno_id'])?$d['turno_id']:null);
  $this->db->beginTransaction();try{
   $this->locks();$u=$this->actor('devolver_pagos');$auth=new Auth($this->db);$auth->permiso('operar_caja');$auth->permiso('registrar_egresos');
   $q=$this->db->prepare('SELECT * FROM caja_turnos WHERE id=? FOR UPDATE');$q->execute(array($turn));$t=$q->fetch();$a=$this->accion($id);$s=$this->solicitud($a['solicitud_id']);
   if($a['tipo']!=='devolucion')throw new HttpError('Esta petición no es una devolución.',409);
   if($a['estado']==='realizada'){if((string)$a['turno_id']!==$turn||(string)$a['ejecutor_id']!==(string)$u['id'])throw new HttpError('La devolución ya fue registrada en otro turno.',409);$this->db->commit();return array('id'=>$id,'repetido'=>true);}
   if($a['estado']!=='aprobada')throw new HttpError('La devolución requiere autorización previa del administrador.',409);
   if(!$t||$t['estado']!=='abierto'||(string)$t['usuario_id']!==(string)$u['id'])throw new HttpError('Abre tu propio turno de caja y actualiza antes de devolver.',409);
   $q=$this->db->prepare('SELECT importe FROM solicitud_pagos WHERE id=? FOR UPDATE');$q->execute(array($a['pago_id']));$original=Dinero::centavos($q->fetchColumn());
   $q=$this->db->prepare("SELECT COALESCE(SUM(importe),0) FROM solicitud_acciones WHERE pago_id=? AND estado='realizada'");$q->execute(array($a['pago_id']));$returned=Dinero::centavos($q->fetchColumn());$n=Dinero::centavos($a['importe']);if($returned+$n>$original)throw new HttpError('El importe supera el pago disponible.',409);
   $q=$this->db->prepare('SELECT tipo,metodo,importe FROM caja_movimientos WHERE turno_id=? ORDER BY id FOR UPDATE');$q->execute(array($turn));$cash=Dinero::centavos($t['fondo']);$otherOut=0;foreach($q->fetchAll() as $m){$v=Dinero::centavos($m['importe']);if($m['metodo']==='efectivo')$cash+=($m['tipo']==='ingreso'?$v:-$v);elseif($m['tipo']==='egreso')$otherOut+=$v;}
   if($a['metodo']==='efectivo'&&$n>$cash)throw new HttpError('No hay suficiente efectivo en este turno para devolver.',409);if($a['metodo']!=='efectivo'&&$otherOut+$n>999999999999)throw new HttpError('El acumulado de egresos excede el máximo del turno.');
   $folio='SOL-'.str_pad($s['id'],6,'0',STR_PAD_LEFT);$q=$this->db->prepare('INSERT INTO caja_movimientos(turno_id,usuario_id,tipo,metodo,importe,concepto,referencia,clave) VALUES(?,?,?,?,?,?,?,?)');$q->execute(array($turn,$u['id'],'egreso',$a['metodo'],$a['importe'],'Devolución de '.$folio.' / PAG-'.str_pad($a['pago_id'],6,'0',STR_PAD_LEFT),'DEV-'.$id,'devolucion:'.$id));$movement=$this->db->lastInsertId();
   $snapshot=json_decode($s['datos'],true);$data=array('laboratorio'=>(new Empresa($this->db))->documento(),'solicitud_folio'=>$folio,'paciente_nombre'=>$snapshot['paciente_nombre'],'expediente'=>$snapshot['expediente'],'responsable'=>$u['nombre']);
   $q=$this->db->prepare("UPDATE solicitud_acciones SET estado='realizada',ejecutor_id=?,turno_id=?,movimiento_id=?,datos=?,realizado_en=CURRENT_TIMESTAMP WHERE id=?");$q->execute(array($u['id'],$turn,$movement,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$id));
   $this->db->prepare('UPDATE caja_turnos SET version=version+1 WHERE id=?')->execute(array($turn));$this->db->prepare('UPDATE solicitudes SET version=version+1 WHERE id=?')->execute(array($s['id']));
   Auditoria::registrar($this->db,$u['id'],'pago_devuelto','Petición '.$id,$a['motivo'],null,array('solicitud_id'=>$s['id'],'pago_id'=>$a['pago_id'],'importe'=>$a['importe'],'metodo'=>$a['metodo'],'autorizador_id'=>$a['autorizador_id'],'turno_id'=>$turn,'movimiento_id'=>$movement));$this->db->commit();return array('id'=>$id,'repetido'=>false);
  }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();if($e instanceof PDOException&&isset($e->errorInfo[1])&&(int)$e->errorInfo[1]===1062)throw new HttpError('La clave ya está registrada en caja. Actualiza y revisa el movimiento.',409);throw $e;}
 }
 public function recibo($id){$this->actor();$id=$this->id($id);$q=$this->db->prepare("SELECT * FROM solicitud_acciones WHERE id=? AND tipo='devolucion' AND estado='realizada'");$q->execute(array($id));$a=$q->fetch();if(!$a)throw new HttpError('Devolución realizada no encontrada.',404);$a['datos']=json_decode($a['datos'],true);unset($a['clave'],$a['huella']);return $a;}
}
