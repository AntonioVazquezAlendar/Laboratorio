<?php
class Caja {
 private $db;public function __construct(PDO $db){$this->db=$db;}
 private function actor($permission){$auth=new Auth($this->db);$auth->permiso('operar_caja');return $auth->permiso($permission);}
 private function summary(array $t){
  if($t['estado']==='cerrado')return json_decode($t['resumen_cierre'],true);
  $q=$this->db->prepare('SELECT * FROM caja_movimientos WHERE turno_id=? ORDER BY id');$q->execute(array($t['id']));$rows=$q->fetchAll();$cashIn=0;$cashOut=0;$other=0;
  foreach($rows as $m){$n=Dinero::centavos($m['importe']);if($m['metodo']==='efectivo'){if($m['tipo']==='ingreso')$cashIn+=$n;else $cashOut+=$n;}else $other+=$n;}
  return array('fondo'=>$t['fondo'],'ingresos_efectivo'=>Dinero::texto($cashIn),'egresos_efectivo'=>Dinero::texto($cashOut),'ingresos_otros'=>Dinero::texto($other),'esperado'=>Dinero::texto(Dinero::centavos($t['fondo'])+$cashIn-$cashOut),'movimientos'=>$rows);
 }
 public function contexto(){
  $u=$this->actor('operar_caja');$q=$this->db->prepare("SELECT * FROM caja_turnos WHERE usuario_id=? ORDER BY id DESC LIMIT 30");$q->execute(array($u['id']));$rows=$q->fetchAll();$open=null;foreach($rows as &$t){$t['resumen']=$this->summary($t);if($t['estado']==='abierto')$open=$t;}unset($t);return array('turno'=>$open,'historial'=>$rows);
 }
 public function operar($action,array $d){
  $map=array('abrir'=>'operar_caja','ingreso'=>'registrar_ingresos','egreso'=>'registrar_egresos','cerrar'=>'cerrar_caja');if(!isset($map[$action]))throw new HttpError('Operación no encontrada.',404);
  $this->db->beginTransaction();try{
   $this->db->query('SELECT id FROM roles ORDER BY id FOR SHARE')->fetchAll();$this->db->query('SELECT id FROM usuarios ORDER BY id FOR UPDATE')->fetchAll();$u=$this->actor($map[$action]);
   $q=$this->db->prepare("SELECT * FROM caja_turnos WHERE usuario_id=? AND estado='abierto' FOR UPDATE");$q->execute(array($u['id']));$t=$q->fetch();
   if($action==='abrir'){
    if($t)throw new HttpError('Ya tienes un turno abierto.',409);$amount=Dinero::centavos(isset($d['fondo'])?$d['fondo']:'');
    $q=$this->db->prepare('INSERT INTO caja_turnos(usuario_id,fondo) VALUES(?,?)');$q->execute(array($u['id'],Dinero::texto($amount)));$id=$this->db->lastInsertId();
   }else{
    if(!$t)throw new HttpError('Abre tu turno de caja antes de continuar.',409);$id=$t['id'];
    $s=$this->summary($t);$expected=Dinero::centavos($s['esperado']);
    if($action==='cerrar'){
     if(!isset($d['version'])||(string)$d['version']!==(string)$t['version'])throw new HttpError('El resumen cambió. Actualiza el corte.',409);
     $counted=Dinero::centavos(isset($d['contado'])?$d['contado']:'');$note=isset($d['notas'])&&is_string($d['notas'])?trim($d['notas']):'';
     if(strlen($note)>1000||($counted!==$expected&&$note===''))throw new HttpError('Explica la diferencia entre efectivo esperado y contado.');
     $s['contado']=Dinero::texto($counted);$s['diferencia']=Dinero::texto($counted-$expected);$s['notas']=$note;$s['laboratorio']=(new Empresa($this->db))->documento();$s['responsable']=$u['nombre'];$s['usuario_id']=$u['id'];
     $q=$this->db->prepare("UPDATE caja_turnos SET estado='cerrado',resumen_cierre=?,cerrado_en=CURRENT_TIMESTAMP,version=version+1 WHERE id=?");$q->execute(array(json_encode($s),$id));
    }else{
     $n=Dinero::centavos(isset($d['importe'])?$d['importe']:'');if($n===0)throw new HttpError('El movimiento debe ser mayor que cero.');
     $method=isset($d['metodo'])?$d['metodo']:'efectivo';if(!in_array($method,array('efectivo','tarjeta','transferencia'),true)||($action==='egreso'&&$method!=='efectivo'))throw new HttpError('Los egresos de esta caja se registran en efectivo.');
     $concept=isset($d['concepto'])&&is_string($d['concepto'])?trim($d['concepto']):'';$ref=isset($d['referencia'])&&is_string($d['referencia'])?trim($d['referencia']):'';$key=isset($d['clave'])&&is_string($d['clave'])?$d['clave']:'';
     if($concept===''||strlen($concept)>300||strlen($ref)>100||!preg_match('/^[a-f0-9-]{36}$/D',$key))throw new HttpError('Completa el concepto, referencia y clave del movimiento.');
     $q=$this->db->prepare('SELECT * FROM caja_movimientos WHERE clave=?');$q->execute(array($key));$previous=$q->fetch();
     if($previous){if((string)$previous['turno_id']!== (string)$id||$previous['tipo']!==$action||$previous['metodo']!==$method||Dinero::centavos($previous['importe'])!==$n||$previous['concepto']!==$concept||$previous['referencia']!==$ref)throw new HttpError('Esta clave corresponde a otro movimiento.',409);$this->db->commit();return array('id'=>$previous['id'],'repetido'=>true);}
     if($action==='ingreso' && (($method==='efectivo' && $expected+$n>999999999999) || ($method!=='efectivo' && Dinero::centavos($s['ingresos_otros'])+$n>999999999999)))throw new HttpError('El acumulado del turno excede el máximo permitido.');
     if($action==='egreso'&&$n>$expected)throw new HttpError('No hay suficiente efectivo para este egreso.',409);
     $q=$this->db->prepare('INSERT INTO caja_movimientos(turno_id,usuario_id,tipo,metodo,importe,concepto,referencia,clave) VALUES(?,?,?,?,?,?,?,?)');$q->execute(array($id,$u['id'],$action,$method,Dinero::texto($n),$concept,$ref,$key));
     $q=$this->db->prepare('UPDATE caja_turnos SET version=version+1 WHERE id=?');$q->execute(array($id));
    }
   }
   Auditoria::registrar($this->db,$u['id'],'caja_'.$action,'Turno '.$id,'Caja: '.$action,null,array('turno_id'=>$id));$this->db->commit();return array('id'=>$id);
  }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
 }
}
