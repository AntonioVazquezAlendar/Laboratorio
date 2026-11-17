<?php
require_once __DIR__.'/Anulacion.php';
class Cobro
{
 private $db;
 public function __construct(PDO $db){$this->db=$db;}
 private function actor($write=false){$auth=new Auth($this->db);$u=$auth->permiso('ver_cobros');if($write){$auth->permiso('cobrar');$auth->permiso('operar_caja');$auth->permiso('registrar_ingresos');}return $u;}
 private function id($v){if(!is_scalar($v)||!ctype_digit((string)$v)||(int)$v<1)throw new HttpError('Selecciona una solicitud o turno válido.');return (string)(int)$v;}
 private function texto(array $d,$key,$max){$v=isset($d[$key])&&is_string($d[$key])?trim($d[$key]):'';if(strlen($v)>$max)throw new HttpError('El campo '.$key.' supera el máximo permitido.');return $v;}
 public static function resumen(PDO $db,$id,$total){
  $q=$db->prepare('SELECT COALESCE(SUM(importe),0) pagado,COUNT(*) numero_pagos FROM solicitud_pagos WHERE solicitud_id=?');$q->execute(array($id));$sum=$q->fetch();
  $q=$db->prepare("SELECT COALESCE(SUM(importe),0) FROM solicitud_acciones WHERE solicitud_id=? AND tipo='devolucion' AND estado='realizada'");$q->execute(array($id));$returned=Dinero::centavos($q->fetchColumn());
  $q=$db->prepare('SELECT estado FROM solicitudes WHERE id=?');$q->execute(array($id));$cancelled=$q->fetchColumn()==='cancelada';
  $t=Dinero::centavos($total);$gross=Dinero::centavos($sum['pagado']);$paid=$gross-$returned;$balance=$cancelled?0:$t-$paid;
  return array('cancelada'=>$cancelled,'cobrado'=>Dinero::texto($gross),'devuelto'=>Dinero::texto($returned),'por_devolver'=>$cancelled?Dinero::texto($paid):'0.00','total'=>Dinero::texto($t),'pagado'=>Dinero::texto($paid),'saldo'=>Dinero::texto($balance),'numero_pagos'=>(int)$sum['numero_pagos'],'estado_pago'=>$cancelled?'cancelada':($balance===0?'pagada':($paid>0?'parcial':'pendiente')));
 }
 public function listar(array $f){
  $u=$this->actor();$query=isset($f['q'])&&is_string($f['q'])?trim($f['q']):'';if(strlen($query)>100)throw new HttpError('Usa hasta 100 bytes en la búsqueda.');
  $page=isset($f['pagina'])?$f['pagina']:'1';if(!is_scalar($page)||!ctype_digit((string)$page)||(int)$page<1||(int)$page>100000)throw new HttpError('Página no válida.');
  $state=isset($f['estado'])?$f['estado']:'pendiente';if(!in_array($state,array('todos','pendiente','pagada','cancelada'),true))throw new HttpError('Filtro de saldo no válido.');
  $base=" FROM solicitudes s LEFT JOIN (SELECT solicitud_id,SUM(importe) pagado FROM solicitud_pagos GROUP BY solicitud_id) p ON p.solicitud_id=s.id LEFT JOIN (SELECT solicitud_id,SUM(importe) devuelto FROM solicitud_acciones WHERE tipo='devolucion' AND estado='realizada' GROUP BY solicitud_id) d ON d.solicitud_id=s.id WHERE s.estado IN ('registrada','cancelada')";$params=array();
  if($query!==''){$like='%'.str_replace(array('!','%','_'),array('!!','!%','!_'),$query).'%';$base.=" AND (CONCAT('SOL-',LPAD(s.id,GREATEST(6,LENGTH(s.id)),'0')) LIKE ? ESCAPE '!' OR JSON_UNQUOTE(JSON_EXTRACT(s.datos,'$.paciente_nombre')) LIKE ? ESCAPE '!' OR JSON_UNQUOTE(JSON_EXTRACT(s.datos,'$.expediente')) LIKE ? ESCAPE '!')";$params=array($like,$like,$like);}
  if($state==='cancelada')$base.=" AND s.estado='cancelada'";elseif($state!=='todos')$base.=" AND s.estado='registrada' AND s.total".($state==='pendiente'?'>':'=').'(COALESCE(p.pagado,0)-COALESCE(d.devuelto,0))';
  $q=$this->db->prepare("SELECT s.id,s.estado,s.total,s.version,s.creado_en,JSON_UNQUOTE(JSON_EXTRACT(s.datos,'$.paciente_nombre')) paciente_nombre,JSON_UNQUOTE(JSON_EXTRACT(s.datos,'$.expediente')) expediente,(COALESCE(p.pagado,0)-COALESCE(d.devuelto,0)) pagado,IF(s.estado='cancelada',0,s.total-COALESCE(p.pagado,0)+COALESCE(d.devuelto,0)) saldo".$base.' ORDER BY s.id DESC LIMIT 26 OFFSET '.(((int)$page-1)*25));$q->execute($params);$rows=$q->fetchAll();$more=count($rows)>25;if($more)array_pop($rows);
  $this->db->beginTransaction();Auditoria::registrar($this->db,$u['id'],'cobros_consultados','Solicitudes','Consulta de saldos de solicitudes',null,null,array('pagina'=>(int)$page));$this->db->commit();return array('registros'=>$rows,'pagina'=>(int)$page,'hay_mas'=>$more);
 }
 public function consultar($id){
  $id=$this->id($id);$this->db->beginTransaction();try{
   $u=$this->actor();$q=$this->db->prepare('SELECT id,estado,total,version,datos,creado_en FROM solicitudes WHERE id=?');$q->execute(array($id));$r=$q->fetch();if(!$r||!in_array($r['estado'],array('registrada','cancelada'),true))throw new HttpError('Selecciona una solicitud registrada.',404);$snapshot=json_decode($r['datos'],true);unset($r['datos']);
   $r['paciente_nombre']=$snapshot['paciente_nombre'];$r['expediente']=$snapshot['expediente'];$r['resumen']=self::resumen($this->db,$id,$r['total']);
   $q=$this->db->prepare('SELECT id,turno_id,tipo,importe,metodo,recibido,cambio,referencia,notas,datos,creado_en FROM solicitud_pagos WHERE solicitud_id=? ORDER BY id');$q->execute(array($id));$r['pagos']=$q->fetchAll();foreach($r['pagos'] as &$p){$s=json_decode($p['datos'],true);$p['responsable']=$s['responsable'];unset($p['datos']);}unset($p);
   $r['acciones']=Anulacion::filas($this->db,$id);
   foreach($r['pagos'] as &$p){$reserved=0;foreach($r['acciones'] as $a)if((string)$a['pago_id']===(string)$p['id']&&$a['estado']!=='rechazada')$reserved+=Dinero::centavos($a['importe']);$p['disponible_devolucion']=Dinero::texto(Dinero::centavos($p['importe'])-$reserved);}unset($p);
   $r['turno']=null;if(!empty($u['permisos']['operar_caja'])){$q=$this->db->prepare("SELECT id,abierto_en FROM caja_turnos WHERE usuario_id=? AND estado='abierto'");$q->execute(array($u['id']));$r['turno']=$q->fetch()?:null;}
   $r['puede_cobrar']=$r['estado']==='registrada'&&!empty($u['permisos']['cobrar'])&&!empty($u['permisos']['operar_caja'])&&!empty($u['permisos']['registrar_ingresos']);
   Auditoria::registrar($this->db,$u['id'],'cobros_consultados','Solicitud '.$id,'Consulta de saldo y pagos',null,null);$this->db->commit();return $r;
  }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
 }
 public function recibo($id,$imprimir=false){
  $id=$this->id($id);$this->db->beginTransaction();try{$u=$this->actor();$q=$this->db->prepare('SELECT id,solicitud_id,turno_id,tipo,importe,metodo,recibido,cambio,referencia,notas,datos,creado_en FROM solicitud_pagos WHERE id=?');$q->execute(array($id));$r=$q->fetch();if(!$r)throw new HttpError('Pago no encontrado.',404);$r['datos']=json_decode($r['datos'],true);
   Auditoria::registrar($this->db,$u['id'],$imprimir?'recibo_pago_impreso':'pago_consultado','Pago '.$id,$imprimir?'Impresión de recibo de pago':'Consulta de pago',null,null);$this->db->commit();return $r;
  }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
 }
 public function registrar($id,array $d){
  $id=$this->id($id);$turno=$this->id(isset($d['turno_id'])?$d['turno_id']:null);$n=Dinero::centavos(isset($d['importe'])?$d['importe']:'');if($n<=0)throw new HttpError('El abono debe ser mayor que cero.');
  $method=isset($d['metodo'])?$d['metodo']:null;if(!in_array($method,array('efectivo','tarjeta','transferencia'),true))throw new HttpError('Selecciona un método de pago válido.');
  $received=null;if($method==='efectivo'){$received=isset($d['recibido'])&&$d['recibido']!==''?Dinero::centavos($d['recibido']):$n;if($received<$n)throw new HttpError('El efectivo recibido no puede ser menor que el abono.');}elseif(isset($d['recibido'])&&$d['recibido']!==''&&$d['recibido']!==null)throw new HttpError('El efectivo recibido solo corresponde a pagos en efectivo.');
  $change=$received!==null?$received-$n:0;$ref=$this->texto($d,'referencia',100);$note=$this->texto($d,'notas',300);$key=isset($d['clave'])?$d['clave']:null;
  if(!is_string($key)||!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/iD',$key))throw new HttpError('Falta una clave válida del cobro.');$key=strtolower($key);
  $this->db->beginTransaction();try{
   // Same lock order as Caja: role -> users -> turn. Request edits lock users before the request too.
   $this->db->query('SELECT id FROM roles ORDER BY id FOR SHARE')->fetchAll();$this->db->query('SELECT id FROM usuarios ORDER BY id FOR UPDATE')->fetchAll();$u=$this->actor(true);
   $fingerprint=hash('sha256',json_encode(array($id,$turno,(string)$u['id'],$n,$method,$received,$ref,$note),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
   $q=$this->db->prepare('SELECT id,huella FROM solicitud_pagos WHERE clave=? FOR UPDATE');$q->execute(array($key));$previous=$q->fetch();
   if($previous){if(!hash_equals($previous['huella'],$fingerprint))throw new HttpError('La clave de reintento corresponde a otro cobro.',409);$this->db->commit();return array('id'=>$previous['id'],'solicitud_id'=>$id,'repetido'=>true);}
   $q=$this->db->prepare('SELECT * FROM caja_turnos WHERE id=? FOR UPDATE');$q->execute(array($turno));$t=$q->fetch();
   if(!$t||(string)$t['usuario_id']!==(string)$u['id']||$t['estado']!=='abierto')throw new HttpError('Abre tu propio turno de caja o actualiza el formulario antes de cobrar.',409);
   $q=$this->db->prepare('SELECT * FROM solicitudes WHERE id=? FOR UPDATE');$q->execute(array($id));$request=$q->fetch();if(!$request||$request['estado']!=='registrada')throw new HttpError('Solo se cobran solicitudes registradas; convierte primero la cotización.',409);
   if(!isset($d['version'])||(string)$d['version']!==(string)$request['version'])throw new HttpError('La solicitud o su saldo cambió. Actualiza antes de registrar el abono.',409);
   $summary=self::resumen($this->db,$id,$request['total']);$balance=Dinero::centavos($summary['saldo']);if($n>$balance)throw new HttpError('El abono supera el saldo pendiente de la solicitud.',409);
   $q=$this->db->prepare('SELECT tipo,metodo,importe FROM caja_movimientos WHERE turno_id=? ORDER BY id FOR UPDATE');$q->execute(array($turno));$cash=Dinero::centavos($t['fondo']);$other=0;
   foreach($q->fetchAll() as $m){$amount=Dinero::centavos($m['importe']);if($m['metodo']==='efectivo')$cash+=($m['tipo']==='ingreso'?$amount:-$amount);else $other+=$amount;}
   if(($method==='efectivo'&&$cash+$n>999999999999)||($method!=='efectivo'&&$other+$n>999999999999))throw new HttpError('El acumulado del turno excede el máximo permitido.');
   $type=$n===$balance?'liquidacion':($summary['numero_pagos']===0?'anticipo':'abono');$folio='SOL-'.str_pad($id,6,'0',STR_PAD_LEFT);$concept=ucfirst($type).' de '.$folio;
   $q=$this->db->prepare('INSERT INTO caja_movimientos(turno_id,usuario_id,tipo,metodo,importe,concepto,referencia,clave) VALUES(?,?,?,?,?,?,?,?)');$q->execute(array($turno,$u['id'],'ingreso',$method,Dinero::texto($n),$concept,$folio,$key));$movement=$this->db->lastInsertId();
   $snapshot=json_decode($request['datos'],true);$paid=Dinero::centavos($summary['pagado'])+$n;$remaining=$balance-$n;
   $data=array('laboratorio'=>(new Empresa($this->db))->documento(),'solicitud_folio'=>$folio,'paciente_nombre'=>$snapshot['paciente_nombre'],'expediente'=>$snapshot['expediente'],'responsable'=>$u['nombre'],
    'total_solicitud'=>$request['total'],'pagado_antes'=>$summary['pagado'],'pagado_despues'=>Dinero::texto($paid),'saldo_despues'=>Dinero::texto($remaining));
   $q=$this->db->prepare('INSERT INTO solicitud_pagos(solicitud_id,turno_id,movimiento_id,usuario_id,tipo,importe,metodo,recibido,cambio,referencia,notas,clave,huella,datos) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
   $q->execute(array($id,$turno,$movement,$u['id'],$type,Dinero::texto($n),$method,$received!==null?Dinero::texto($received):null,Dinero::texto($change),$ref,$note,$key,$fingerprint,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)));$payment=$this->db->lastInsertId();
   $this->db->prepare('UPDATE caja_turnos SET version=version+1 WHERE id=?')->execute(array($turno));$this->db->prepare('UPDATE solicitudes SET version=version+1 WHERE id=?')->execute(array($id));
   Auditoria::registrar($this->db,$u['id'],'solicitud_cobrada','Pago '.$payment,'Cobro de '.$folio,null,array('solicitud_id'=>$id,'turno_id'=>$turno,'movimiento_id'=>$movement,'importe'=>Dinero::texto($n),'metodo'=>$method,'saldo'=>Dinero::texto($remaining)));
   $this->db->commit();return array('id'=>$payment,'solicitud_id'=>$id,'repetido'=>false);
  }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();if($e instanceof PDOException&&isset($e->errorInfo[1])&&(int)$e->errorInfo[1]===1062)throw new HttpError('La clave ya se usó en otra operación. Actualiza el formulario.',409);throw $e;}
 }
}
