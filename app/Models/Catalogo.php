<?php
class Catalogo
{
    private $db;
    public function __construct(PDO $db) { $this->db=$db; }
    private static function f($label,$type='text',$required=false,$max=150,$extra=array()) {
        return array_merge(array('label'=>$label,'type'=>$type,'required'=>$required,'max'=>$max),$extra);
    }
    public static function definiciones() {
        $code=self::f('Código','text',true,30); $name=self::f('Nombre','text',true);
        $notes=self::f('Notas','textarea',false,3000);
        $contact=array('telefono'=>self::f('Teléfono','tel',false,30),'correo'=>self::f('Correo','email',false,150));
        $address=self::f('Dirección','textarea',false,500);
        $method=self::f('Método','relation',false,0,array('source'=>'metodos'));
        return array(
            'estudios'=>array('title'=>'Estudios','singular'=>'estudio','view'=>'ver_catalogos','edit'=>'editar_catalogos','fields'=>array(
                'codigo'=>$code,'nombre'=>$name,'area'=>self::f('Área','text',false,100),
                'tipo_muestra_id'=>self::f('Tipo de muestra','relation',false,0,array('source'=>'tipos_muestra')),
                'metodo_id'=>$method,'tiempo_entrega'=>self::f('Tiempo de entrega','text',false,100),
                'indicaciones'=>self::f('Indicaciones de preparación','textarea',false,3000)),
                'columns'=>array('codigo'=>'Código','nombre'=>'Estudio','area'=>'Área','muestra_nombre'=>'Muestra','metodo_nombre'=>'Método')),
            'analitos'=>array('title'=>'Analitos','singular'=>'analito','view'=>'ver_catalogos','edit'=>'editar_catalogos','fields'=>array(
                'codigo'=>$code,'nombre'=>$name,'unidad'=>self::f('Unidad','text',false,40),
                'tipo_resultado'=>self::f('Tipo de resultado','select',true,0,array('options'=>array('numerico'=>'Numérico','cualitativo'=>'Cualitativo','texto'=>'Texto'))),
                'decimales'=>self::f('Decimales','integer',true,0,array('min'=>0,'maxValue'=>6,'default'=>2)),
                'metodo_id'=>$method,'referencia_texto'=>self::f('Valores de referencia (definidos por el laboratorio)','textarea',false,1000),'notas'=>$notes),'columns'=>array('codigo'=>'Código','nombre'=>'Analito','unidad'=>'Unidad','tipo_resultado'=>'Tipo')),
            'pacientes'=>array('title'=>'Pacientes','singular'=>'paciente','view'=>'ver_pacientes','edit'=>'editar_pacientes','fields'=>array_merge(array(
                'codigo'=>self::f('Expediente','text',false,30),'nombre'=>self::f('Nombre(s)','text',true),
                'apellidos'=>self::f('Apellidos','text',true),'fecha_nacimiento'=>self::f('Fecha de nacimiento','date',true),
                'sexo'=>self::f('Sexo registrado','select',true,0,array('options'=>array('no_especificado'=>'No especificado','femenino'=>'Femenino','masculino'=>'Masculino','otro'=>'Otro')))),
                $contact,array('direccion'=>$address,'medico_id'=>self::f('Médico referente','relation',false,0,array('source'=>'medicos')),
                'compania_id'=>self::f('Compañía','relation',false,0,array('source'=>'companias')),'notas'=>$notes)),
                'columns'=>array('codigo'=>'Expediente','nombre_completo'=>'Paciente','fecha_nacimiento'=>'Nacimiento','telefono'=>'Teléfono','compania_nombre'=>'Compañía')),
            'medicos'=>array('title'=>'Médicos','singular'=>'médico','view'=>'ver_medicos','edit'=>'editar_medicos','fields'=>array_merge(array(
                'codigo'=>$code,'nombre'=>self::f('Nombre(s)','text',true),'apellidos'=>self::f('Apellidos'),
                'cedula'=>self::f('Cédula profesional','text',false,30),'especialidad'=>self::f('Especialidad')), $contact,array('direccion'=>$address,'notas'=>$notes)),
                'columns'=>array('codigo'=>'Código','nombre_completo'=>'Médico','especialidad'=>'Especialidad','cedula'=>'Cédula','telefono'=>'Teléfono')),
            'companias'=>array('title'=>'Compañías','singular'=>'compañía','view'=>'ver_companias','edit'=>'editar_companias','fields'=>array_merge(array(
                'codigo'=>$code,'nombre'=>$name,'razon_social'=>self::f('Razón social','text',false,200),
                'rfc'=>self::f('RFC','text',false,13),'contacto'=>self::f('Persona de contacto')),$contact,array('direccion'=>$address,'notas'=>$notes)),
                'columns'=>array('codigo'=>'Código','nombre'=>'Compañía','contacto'=>'Contacto','telefono'=>'Teléfono')),
            'precios'=>array('title'=>'Precios','singular'=>'precio','view'=>'ver_precios','edit'=>'editar_precios','fields'=>array(
                'estudio_id'=>self::f('Estudio','relation',true,0,array('source'=>'estudios')),
                'compania_id'=>self::f('Compañía','relation',false,0,array('source'=>'companias','empty'=>'Público general')),
                'importe'=>self::f('Precio (MXN)','money',true)),
                'columns'=>array('estudio_nombre'=>'Estudio','compania_nombre'=>'Tarifa / compañía','importe'=>'Precio MXN')),
            'tipos_muestra'=>array('title'=>'Tipos de muestra','singular'=>'tipo de muestra','view'=>'ver_catalogos','edit'=>'editar_catalogos','fields'=>array(
                'codigo'=>$code,'nombre'=>$name,'recipiente'=>self::f('Recipiente'),
                'indicaciones'=>self::f('Indicaciones de recolección','textarea',false,3000)),
                'columns'=>array('codigo'=>'Código','nombre'=>'Muestra','recipiente'=>'Recipiente')),
            'metodos'=>array('title'=>'Métodos','singular'=>'método','view'=>'ver_catalogos','edit'=>'editar_catalogos','fields'=>array(
                'codigo'=>$code,'nombre'=>$name,'descripcion'=>self::f('Descripción','textarea',false,3000)),
                'columns'=>array('codigo'=>'Código','nombre'=>'Método'))
        );
    }
    private function definition($tipo) {
        $all=self::definiciones(); if(!isset($all[$tipo]))throw new HttpError('Catálogo no encontrado.',404); return $all[$tipo];
    }
    public function disponibles() {
        $u=(new Auth($this->db))->usuario(); $out=array();
        foreach(self::definiciones() as $k=>$d)if(!empty($u['permisos'][$d['view']])) { $d['editable']=!empty($u['permisos'][$d['edit']]);$out[$k]=$d; }
        return $out;
    }
    private function select($tipo) {
        $sql='SELECT c.*';$join='';
        if(in_array($tipo,array('pacientes','medicos'),true))$sql.=",TRIM(CONCAT(c.nombre,' ',c.apellidos)) nombre_completo";
        if($tipo==='estudios') { $sql.=',m.nombre muestra_nombre,t.nombre metodo_nombre';$join=' LEFT JOIN tipos_muestra m ON m.id=c.tipo_muestra_id LEFT JOIN metodos t ON t.id=c.metodo_id'; }
        if($tipo==='pacientes') { $sql.=',m.nombre compania_nombre';$join=' LEFT JOIN companias m ON m.id=c.compania_id'; }
        if($tipo==='precios') { $sql.=",e.nombre estudio_nombre,COALESCE(m.nombre,'Público general') compania_nombre";$join=' JOIN estudios e ON e.id=c.estudio_id LEFT JOIN companias m ON m.id=c.compania_id'; }
        return $sql.' FROM '.$tipo.' c'.$join;
    }
    private function audit($actor,$tipo,$id,$accion,$antes=null,$despues=null) {
        // Patient snapshots contain sensitive details: keep audit metadata only.
        if($tipo==='pacientes') { $antes=null;$despues=null; }
        Auditoria::registrar($this->db,$actor['id'],'catalogo_'.$accion,$tipo.($id?' '.$id:''),'Catálogo: '.$tipo,$antes,$despues,array('catalogo'=>$tipo,'id'=>$id));
    }
    public function listar($tipo,array $f,$lookup=false) {
        $d=$this->definition($tipo);$auth=new Auth($this->db);
        // Relational lookups expose only ID/name, not full patient or company records.
        $actor=$auth->usuario();
        if($lookup) {
            $allowed=!empty($actor['permisos'][$d['view']]);
            foreach(self::definiciones() as $parent)if(!empty($actor['permisos'][$parent['edit']]))foreach($parent['fields'] as $field)if(isset($field['source'])&&$field['source']===$tipo)$allowed=true;
            if(!empty($actor['permisos']['crear_ordenes'])&&in_array($tipo,array('pacientes','medicos','companias','estudios'),true))$allowed=true;
            if(!$allowed || $tipo==='precios')throw new HttpError('No tienes permiso para consultar este catálogo.',403);
        } else $actor=$auth->permiso($d['view']);
        $q=isset($f['q'])&&is_string($f['q'])?trim($f['q']):'';if(strlen($q)>100)throw new HttpError('Busca con un máximo de 100 bytes.');
        $page=isset($f['pagina'])?$f['pagina']:'1';if(!is_scalar($page)||!ctype_digit((string)$page)||(int)$page<1||(int)$page>100000)throw new HttpError('Página no válida.');
        $where=' WHERE 1=1';$params=array();
        $state=$lookup?'1':(isset($f['estado'])?$f['estado']:'todos');
        if(!in_array($state,array('todos','0','1'),true))throw new HttpError('Estado no válido.');
        if($state!=='todos') { $where.=' AND c.activo=?';$params[]=$state; }
        if($q!=='') {
            $like='%'.str_replace(array('!','%','_'),array('!!','!%','!_'),$q).'%';
            if($tipo==='precios')$fields=array('e.nombre','e.codigo','m.nombre');
            else {$fields=array('c.codigo','c.nombre');if(in_array($tipo,array('pacientes','medicos'),true)){$fields[]='c.apellidos';$fields[]="TRIM(CONCAT(c.nombre,' ',c.apellidos))";}if($tipo==='pacientes')$fields[]='c.telefono';}
            $where.=' AND ('.implode(' OR ',array_map(function($v){return $v." LIKE ? ESCAPE '!'";},$fields)).')';
            foreach($fields as $v)$params[]=$like;
        }
        $size=25;$offset=((int)$page-1)*$size;$base=$this->select($tipo);
        $this->db->beginTransaction();
        try {
            $stmt=$this->db->prepare($base.$where.' ORDER BY c.id DESC LIMIT '.($size+1).' OFFSET '.$offset);$stmt->execute($params);$rows=$stmt->fetchAll();
            $more=count($rows)>$size;if($more)array_pop($rows);
            if($lookup) $rows=array_map(function($r) use($tipo,$actor,$d){
                $out=array('id'=>$r['id'],'nombre'=>isset($r['nombre_completo'])?$r['nombre_completo']:$r['nombre'],'codigo'=>$r['codigo'],'detalles'=>array());
                if(!empty($actor['permisos'][$d['view']])){
                    $columns=array('pacientes'=>array('fecha_nacimiento'=>'Nacimiento','telefono'=>'Teléfono'),'medicos'=>array('especialidad'=>'Especialidad','cedula'=>'Cédula'),
                        'companias'=>array('contacto'=>'Contacto','telefono'=>'Teléfono'),'estudios'=>array('area'=>'Área','muestra_nombre'=>'Muestra'),
                        'analitos'=>array('unidad'=>'Unidad','tipo_resultado'=>'Tipo'),'tipos_muestra'=>array('recipiente'=>'Recipiente'));
                    if(isset($columns[$tipo]))foreach($columns[$tipo] as $key=>$label)if(isset($r[$key])&&$r[$key]!=='')$out['detalles'][$label]=$r[$key];
                }$out['detalles']=(object)$out['detalles'];return $out;
            },$rows);
            $this->audit($actor,$tipo,null,'consultado');$this->db->commit();
            return array('registros'=>$rows,'pagina'=>(int)$page,'hay_mas'=>$more);
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    public function obtener($tipo,$id) {
        $d=$this->definition($tipo);$actor=(new Auth($this->db))->permiso($d['view']);
        $this->db->beginTransaction();
        try {
            $q=$this->db->prepare($this->select($tipo).' WHERE c.id=?');$q->execute(array($id));$r=$q->fetch();if(!$r)throw new HttpError('Registro no encontrado.',404);
            foreach($d['fields'] as $k=>$field)if($field['type']==='relation'&&$r[$k]) {
                $table=$field['source'];$q=$this->db->prepare('SELECT id,codigo,nombre'.($table==='medicos'?',apellidos':'').' FROM '.$table.' WHERE id=?');$q->execute(array($r[$k]));$v=$q->fetch();$r[$k.'_label']=$v['codigo'].' · '.$v['nombre'].(isset($v['apellidos'])?' '.$v['apellidos']:'');
            }
            if($tipo==='estudios') { $q=$this->db->prepare('SELECT a.id,a.codigo,a.nombre,a.activo,ea.orden FROM estudio_analitos ea JOIN analitos a ON a.id=ea.analito_id WHERE ea.estudio_id=? ORDER BY ea.orden');$q->execute(array($id));$r['analitos']=$q->fetchAll(); }
            $this->audit($actor,$tipo,$id,'consultado');$this->db->commit();return $r;
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    private function normalizar($field,$value,$label) {
        if($value===null && !$field['required'] && $field['type']!=='relation')$value='';
        if($field['type']==='relation') {
            if($value===null||$value==='') { if($field['required'])throw new HttpError('Selecciona '.$label.'.'); return null; }
            if(!is_scalar($value)||!ctype_digit((string)$value)||(int)$value<1)throw new HttpError('Selecciona '.$label.'.');return (string)$value;
        }
        if(!is_string($value)&&!is_int($value))throw new HttpError('Revisa '.$label.'.');$v=trim((string)$value);
        if($field['required']&&$v==='')throw new HttpError('Completa '.$label.'.');
        if($field['type']==='integer') { if(!ctype_digit($v)||(int)$v<$field['min']||(int)$v>$field['maxValue'])throw new HttpError($label.' fuera de rango.');return (int)$v; }
        if($field['type']==='money') { if(!preg_match('/^(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?$/D',$v))throw new HttpError('Escribe un precio no negativo, con hasta 2 decimales.');return $v; }
        if($field['type']==='select') { if(!isset($field['options'][$v]))throw new HttpError('Selecciona '.$label.'.');return $v; }
        if($field['type']==='date') {
            if($v==='')return null;$date=DateTime::createFromFormat('!Y-m-d',$v);
            if(!$date||$date->format('Y-m-d')!==$v||$v>date('Y-m-d')||$v<'1800-01-01')throw new HttpError('La fecha de nacimiento no es válida.');return $v;
        }
        if(preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/u',$v)||preg_match('/^.{0,'.$field['max'].'}$/usD',$v)!==1)throw new HttpError($label.' supera el tamaño permitido o contiene caracteres no válidos.');
        if($field['type']==='email'&&$v!==''&&!filter_var($v,FILTER_VALIDATE_EMAIL))throw new HttpError('El correo no es válido.');return $v;
    }
    public function guardar($tipo,array $datos,$id=null) {
        $d=$this->definition($tipo);$auth=new Auth($this->db);$auth->permiso($d['edit']);$auth->permiso($d['view']);
        $this->db->beginTransaction();
        try {
            $this->db->query('SELECT id FROM roles ORDER BY id FOR SHARE')->fetchAll();$this->db->query('SELECT id FROM usuarios ORDER BY id FOR SHARE')->fetchAll();
            $actor=$auth->permiso($d['edit']);$auth->permiso($d['view']);
            // Shared catalog lock order prevents FK/association races across concurrent edits.
            foreach(array('tipos_muestra','metodos','companias','medicos','pacientes','analitos','estudios','precios') as $table)$this->db->query('SELECT id FROM '.$table.' ORDER BY id FOR UPDATE')->fetchAll();
            $old=null;
            if($id!==null) { $q=$this->db->prepare('SELECT * FROM '.$tipo.' WHERE id=?');$q->execute(array($id));$old=$q->fetch();if(!$old)throw new HttpError('Registro no encontrado.',404);if(!isset($datos['version'])||(string)$datos['version']!==(string)$old['version'])throw new HttpError('El registro cambió. Actualiza antes de guardar.',409); }
            $values=array();
            foreach($d['fields'] as $k=>$field) {
                $value=array_key_exists($k,$datos)?$datos[$k]:($old&&array_key_exists($k,$old)?$old[$k]:(isset($field['default'])?$field['default']:($field['type']==='relation'?null:'')));
                if($tipo==='pacientes'&&$k==='codigo'&&$value==='')$value='PAC-'.strtoupper(bin2hex(random_bytes(5)));
                $values[$k]=$this->normalizar($field,$value,$field['label']);
                if($field['type']==='relation'&&$values[$k]!==null) {
                    $q=$this->db->prepare('SELECT activo FROM '.$field['source'].' WHERE id=?');$q->execute(array($values[$k]));$active=$q->fetchColumn();
                    if($active===false||(!$active&&(!$old||(string)$old[$k]!==$values[$k])))throw new HttpError('Selecciona un registro activo para '.$field['label'].'.');
                }
            }
            $active=isset($datos['activo'])?$datos['activo']:($old?$old['activo']:1);
            if(!in_array($active,array(0,1,'0','1',true,false),true))throw new HttpError('Estado no válido.');$values['activo']=(int)$active;
            if(!$values['activo']&&$old) {
                $inUse=false;
                if($tipo==='analitos') { $q=$this->db->prepare('SELECT 1 FROM estudio_analitos ea JOIN estudios e ON e.id=ea.estudio_id WHERE ea.analito_id=? AND e.activo=1 LIMIT 1');$q->execute(array($id));$inUse=(bool)$q->fetchColumn(); }
                if($tipo==='tipos_muestra'||$tipo==='metodos') { $column=$tipo==='metodos'?'metodo_id':'tipo_muestra_id';$q=$this->db->prepare('SELECT 1 FROM estudios WHERE '.$column.'=? AND activo=1 LIMIT 1');$q->execute(array($id));$inUse=(bool)$q->fetchColumn();if($tipo==='metodos'){$q=$this->db->prepare('SELECT 1 FROM analitos WHERE metodo_id=? AND activo=1 LIMIT 1');$q->execute(array($id));$inUse=$inUse||(bool)$q->fetchColumn();} }
                if($inUse)throw new HttpError('Este registro está asociado a estudios o analitos activos. Retira la asociación antes de desactivarlo.',409);
            }
            if($id===null) { $columns=array_keys($values);$q=$this->db->prepare('INSERT INTO '.$tipo.' ('.implode(',',$columns).') VALUES('.implode(',',array_fill(0,count($values),'?')).')');$q->execute(array_values($values));$id=$this->db->lastInsertId(); }
            else { $sets=array_map(function($k){return $k.'=?';},array_keys($values));$q=$this->db->prepare('UPDATE '.$tipo.' SET '.implode(',',$sets).',version=version+1 WHERE id=?');$q->execute(array_merge(array_values($values),array($id))); }
            if($tipo==='estudios') {
                $q=$this->db->prepare('SELECT analito_id FROM estudio_analitos WHERE estudio_id=?');$q->execute(array($id));$previous=array_map('strval',$q->fetchAll(PDO::FETCH_COLUMN));
                $ids=array_key_exists('analitos',$datos)?$datos['analitos']:$previous;
                if(!is_array($ids)||count($ids)>200||count($ids)!==count(array_unique($ids,SORT_REGULAR)))throw new HttpError('Selecciona hasta 200 analitos, sin repetir.');
                foreach($ids as $a) { if(!is_scalar($a)||!ctype_digit((string)$a))throw new HttpError('Analito no válido.');$q=$this->db->prepare('SELECT activo FROM analitos WHERE id=?');$q->execute(array($a));$on=$q->fetchColumn();if($on===false||(!$on&&!in_array((string)$a,$previous,true)))throw new HttpError('Selecciona analitos activos.'); }
                $this->db->prepare('DELETE FROM estudio_analitos WHERE estudio_id=?')->execute(array($id));$q=$this->db->prepare('INSERT INTO estudio_analitos(estudio_id,analito_id,orden) VALUES(?,?,?)');foreach(array_values($ids) as $pos=>$a)$q->execute(array($id,$a,$pos+1));$values['analitos']=$ids;
            }
            $this->audit($actor,$tipo,$id,$old?'editado':'creado',$old,$values);$this->db->commit();return array('id'=>$id,'version'=>$old?$old['version']+1:1);
        } catch(Throwable $e) {
            if($this->db->inTransaction())$this->db->rollBack();
            if($e instanceof PDOException&&isset($e->errorInfo[1])&&(int)$e->errorInfo[1]===1062)throw new HttpError($tipo==='precios'?'Ya existe un precio para este estudio y compañía. Edita el registro existente.':'El código ya está registrado.',409);
            throw $e;
        }
    }
}
