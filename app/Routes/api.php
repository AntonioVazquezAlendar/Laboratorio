<?php
$prefix='/Laboratorio/public/api/';
if (isset($_GET['api']) && $path==='/Laboratorio/public/index.php' && is_string($_GET['api'])) {
    $route=$_GET['api'];
} elseif (strpos($path,$prefix)===0) { $route=substr($path,strlen($prefix)); }
else { throw new HttpError('Ruta no encontrada.',404); }
$method=$_SERVER['REQUEST_METHOD']; $auth=new Auth($db);
$datos=array();
if (!in_array($method,array('GET','POST','PUT'),true)) { throw new HttpError('Método no permitido.',405); }
if ($method!=='GET') {
    Auth::csrf();
    if ($route==='empresa' && $method==='POST') {
        (new Auth($db))->administrador();
        if(isset($_FILES['logo']) && (!is_array($_FILES['logo']) || is_array($_FILES['logo']['error'])))throw new HttpError('Selecciona un solo logo.');
        Response::json(true,'Identidad del laboratorio guardada',(new Empresa($db))->guardar($_POST,isset($_FILES['logo'])?$_FILES['logo']:null));
    }
    $raw=file_get_contents('php://input',false,null,0,131073);
    if (strlen($raw)>131072) { throw new HttpError('La solicitud es demasiado grande.',413); }
    $datos=json_decode($raw,true);
    if (!is_array($datos) || substr(ltrim($raw),0,1)!=='{') { throw new HttpError('Envía un objeto JSON válido.'); }
}
if($method==='GET' && $route==='empresa') { Response::json(true,'Identidad del laboratorio',(new Empresa($db))->obtener()); }
if($method==='GET' && $route==='empresa/tema.css') { (new Empresa($db))->estilos(); }
if($method==='GET' && $route==='empresa/logo') { (new Empresa($db))->logo(isset($_GET['v'])?$_GET['v']:null); }
if ($route==='sesion' && $method==='GET') {
    Response::json(true,'Estado de sesión',array('usuario'=>$auth->usuario(false),'csrf'=>$_SESSION['csrf'],
        'configuracion_inicial'=>$auth->necesitaConfiguracion(),'configuracion_local'=>Auth::esLocal()));
}
if ($route==='configurar' && $method==='POST') {
    if (!Auth::esLocal()) { throw new HttpError('La primera cuenta se crea desde este equipo.',403); }
    (new Usuario($db))->guardar($datos,null,null,true); Response::json(true,'Administrador creado. Inicia sesión.');
}
if ($route==='login' && $method==='POST') {
    $usuario=$auth->login($datos); Response::json(true,'Sesión iniciada',array('usuario'=>$usuario,'csrf'=>$_SESSION['csrf']));
}
$actor=$auth->usuario();
if ($route==='logout' && $method==='POST') {
    $db->beginTransaction(); Auditoria::registrar($db,$actor['id'],'sesion_cerrada','Acceso','Cierre de sesión',null,null); $db->commit();
    Auth::logout(); Response::json(true,'Sesión cerrada');
}
if ($route==='contrasena' && $method==='POST') {
    Contrasena::cambiar($db,$datos); Response::json(true,'Contraseña actualizada',array('csrf'=>$_SESSION['csrf']));
}
if (!empty($actor['cambio_obligatorio'])) { throw new HttpError('Cambia tu contraseña temporal para continuar.',403); }
if ($route==='solicitudes' && $method==='GET') { Response::json(true,'Solicitudes',(new Solicitud($db))->listar($_GET)); }
if ($route==='solicitudes' && $method==='POST') { Response::json(true,'Solicitud guardada',(new Solicitud($db))->guardar($datos),201); }
if (preg_match('/^solicitudes\/([0-9]+)(?:\/(impresion))?$/D',$route,$sm)) {
    if ($method==='GET') { Response::json(true,'Solicitud',(new Solicitud($db))->obtener($sm[1],isset($sm[2]))); }
    if ($method==='PUT' && !isset($sm[2])) { Response::json(true,'Solicitud actualizada',(new Solicitud($db))->guardar($datos,$sm[1])); }
}
if($route==='anulaciones' && $method==='GET')Response::json(true,'Cancelaciones y devoluciones',(new Anulacion($db))->listar($_GET));
if(preg_match('/^solicitudes\/([0-9]+)\/anulaciones$/D',$route,$an) && $method==='POST')Response::json(true,'Petición enviada al administrador',(new Anulacion($db))->solicitar($an[1],$datos),201);
if(preg_match('/^anulaciones\/([0-9]+)\/(decidir|ejecutar|recibo)$/D',$route,$an)){
 if($method==='POST'&&$an[2]==='decidir')Response::json(true,'Decisión registrada',(new Anulacion($db))->decidir($an[1],$datos));
 if($method==='POST'&&$an[2]==='ejecutar')Response::json(true,'Devolución registrada en caja',(new Anulacion($db))->ejecutar($an[1],$datos));
 if($method==='GET'&&$an[2]==='recibo')Response::json(true,'Recibo de devolución',(new Anulacion($db))->recibo($an[1]));
}
if($route==='cobros/solicitudes' && $method==='GET') { Response::json(true,'Saldos de solicitudes',(new Cobro($db))->listar($_GET)); }
if(preg_match('/^cobros\/solicitudes\/([0-9]+)$/D',$route,$cp)) {
 if($method==='GET')Response::json(true,'Saldo y pagos',(new Cobro($db))->consultar($cp[1]));
 if($method==='POST')Response::json(true,'Pago registrado',(new Cobro($db))->registrar($cp[1],$datos),201);
}
if(preg_match('/^cobros\/pagos\/([0-9]+)(?:\/(recibo))?$/D',$route,$cp) && $method==='GET') { Response::json(true,'Recibo de pago',(new Cobro($db))->recibo($cp[1],isset($cp[2]))); }
if ($route==='caja' && $method==='GET') { Response::json(true,'Caja',(new Caja($db))->contexto()); }
if (preg_match('/^caja\/(abrir|ingreso|egreso|cerrar)$/D',$route,$bm) && $method==='POST') { Response::json(true,'Operación de caja registrada',(new Caja($db))->operar($bm[1],$datos)); }
if ($route==='catalogos' && $method==='GET') { Response::json(true,'Catálogos disponibles',(new Catalogo($db))->disponibles()); }
if (preg_match('/^catalogos\/([a-z_]+)(?:\/(opciones|[0-9]+))?$/D',$route,$cm)) {
    $model=new Catalogo($db); $key=$cm[1]; $target=isset($cm[2])?$cm[2]:null;
    if ($method==='GET') { Response::json(true,'Catálogo',$target&&$target!=='opciones'?$model->obtener($key,$target):$model->listar($key,$_GET,$target==='opciones')); }
    if ($method==='POST' && $target===null) { Response::json(true,'Registro creado',$model->guardar($key,$datos),201); }
    if ($method==='PUT' && $target!==null && ctype_digit($target)) { Response::json(true,'Registro actualizado',$model->guardar($key,$datos,$target)); }
    throw new HttpError('Método no permitido.',405);
}
if ($route==='usuarios' && $method==='GET') {
    $auth->administrador(); Response::json(true,'Usuarios',(new Usuario($db))->listar());
}
if (($route==='usuarios' && $method==='POST') || (preg_match('/^usuarios\/(\d+)$/D',$route,$m) && $method==='PUT')) {
    $auth->administrador(); $id=isset($m[1])?$m[1]:null;
    $u=(new Usuario($db))->guardar($datos,$id,$actor['id']); Response::json(true,'Usuario guardado',$u,$id?200:201);
}
if ($route==='roles' && $method==='GET') {
    $auth->administrador(); $roles=$db->query('SELECT * FROM roles ORDER BY id')->fetchAll();
    foreach($roles as &$rol) { $rol['permisos']=json_decode($rol['permisos'],true); } unset($rol);
    Response::json(true,'Roles',array('roles'=>$roles,'catalogo'=>Rol::catalogo()));
}
if (($route==='roles' && $method==='POST') || (preg_match('/^roles\/(\d+)$/D',$route,$m) && $method==='PUT')) {
    $auth->administrador(); Response::json(true,'Rol guardado',(new Rol($db))->guardar(isset($m[1])?$m[1]:null,$datos,$actor['id']));
}
if ($route==='bitacora' && $method==='GET') {
    $auth->permiso('ver_bitacora'); $before=isset($_GET['antes'])?$_GET['antes']:'0';
    if (!is_string($before) || !ctype_digit($before)) { throw new HttpError('Página no válida.'); }
    $db->beginTransaction();
    Auditoria::registrar($db,$actor['id'],'bitacora_consultada','Bitácora','Consulta de bitácora',null,null);
    $q=$db->prepare('SELECT id,usuario_nombre,tipo,referencia,resumen,detalle,creado_en FROM bitacora_actividad WHERE (?=0 OR id<?) ORDER BY id DESC LIMIT 50');
    $q->execute(array($before,$before)); $rows=$q->fetchAll(); $db->commit(); Response::json(true,'Bitácora',$rows);
}
if($route==='resultados/plantilla' && $method==='GET') { Response::json(true,'Plantilla del estudio',(new Resultado($db))->plantilla(isset($_GET['solicitud_id'])?$_GET['solicitud_id']:null,isset($_GET['estudio_id'])?$_GET['estudio_id']:null)); }
if(preg_match('/^resultados\/([0-9]+)\/informe.pdf$/D',$route,$pm) && $method==='GET') {
    require_once __DIR__.'/../Services/InformeResultado.php';
    $bytes=(new Resultado($db))->informe($pm[1],isset($_GET['version'])?$_GET['version']:null);
    header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="resultado-'.$pm[1].'-v'.(int)$_GET['version'].'.pdf"');header('Content-Length: '.strlen($bytes));echo $bytes;exit;
}
if ($route==='resultados' && $method==='GET') { Response::json(true,'Resultados',(new Resultado($db))->consultar()); }
if (preg_match('/^resultados\/(\d+)$/D',$route,$m) && $method==='GET') { Response::json(true,'Historial',(new Resultado($db))->consultar($m[1])); }
if ($route==='resultados' && $method==='POST') { Response::json(true,'Resultado capturado',(new Resultado($db))->operar('capturar',null,$datos),201); }
if (preg_match('/^resultados\/(\d+)\/(capturar|corregir|validar|entregar)$/D',$route,$m) && $method==='POST') {
    Response::json(true,'Operación registrada',(new Resultado($db))->operar($m[2],$m[1],$datos));
}
throw new HttpError('Ruta no encontrada.',404);
