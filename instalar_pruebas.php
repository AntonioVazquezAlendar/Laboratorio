<?php
// Instalador local de demostracion. Nunca se ejecuta por HTTP.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (in_array('--help', $argv, true)) {
 echo "Uso: php instalar_pruebas.php [--sin-configurar]\n";
 echo "Conexion: LAB_DB_HOST, LAB_DB_USER, LAB_DB_PASSWORD, LAB_DB_NAME.\n";
 echo "Por defecto crea laboratorio_pruebas. No reemplaza bases ajenas.\n";
 exit(0);
}
date_default_timezone_set('America/Mexico_City');
$root=__DIR__;
$server=null;$db=null;$locked=false;$configTemp=null;
try {
 if (PHP_VERSION_ID<70300 || PHP_INT_SIZE<8) throw new RuntimeException('Se requiere PHP de 64 bits, version 7.3 o posterior.');
 foreach(array('pdo_mysql','curl') as $extension)if(!extension_loaded($extension))throw new RuntimeException('Activa la extension PHP '.$extension.' antes de instalar.');
 $noConfig=in_array('--sin-configurar',$argv,true);
 foreach(array_slice($argv,1) as $arg)if(!in_array($arg,array('--sin-configurar','--password-stdin'),true))throw new RuntimeException('Opcion desconocida. Consulta --help.');
 $configPath=$root.'/app/Config/database.local.php';
 $local=is_file($configPath)?require $configPath:array();
 $name=getenv('LAB_DB_NAME')?:'laboratorio_pruebas';
 if(!preg_match('/^laboratorio_[a-z0-9_]{1,52}$/D',$name))throw new RuntimeException('La base debe comenzar con laboratorio_ y usar solo minusculas, numeros o guion bajo (hasta 64 caracteres).');
 $connection=array('host'=>getenv('LAB_DB_HOST')?:(isset($local['host'])?$local['host']:'127.0.0.1'),
  'username'=>getenv('LAB_DB_USER')?:(isset($local['username'])?$local['username']:'root'),
  'password'=>getenv('LAB_DB_PASSWORD')!==false?getenv('LAB_DB_PASSWORD'):(isset($local['password'])?$local['password']:''),'database'=>$name);
 if(in_array('--password-stdin',$argv,true)){$decoded=base64_decode(trim(stream_get_contents(STDIN,16384)),true);if($decoded===false)throw new RuntimeException('No se pudo leer la clave MySQL.');$connection['password']=$decoded;unset($decoded);}
 if(!preg_match('/^[a-zA-Z0-9_.:\[\]-]+$/D',$connection['host']))throw new RuntimeException('Escribe un servidor MySQL valido, sin opciones de conexion adicionales.');
 // Windows puede eliminar variables de entorno vacias: conservar la eleccion de clave vacia.
 putenv('LAB_DB_PASSWORD_EMPTY='.($connection['password']===''?'1':'0'));
 putenv('LAB_DB_NAME='.$name);putenv('LAB_DB_HOST='.$connection['host']);putenv('LAB_DB_USER='.$connection['username']);putenv('LAB_DB_PASSWORD='.$connection['password']);
 require_once $root.'/app/Config/Database.php';
 $server=(new Database())->connect(true);
 $version=$server->getAttribute(PDO::ATTR_SERVER_VERSION);
 if(stripos($version,'MariaDB')!==false || version_compare($version,'8.0','<'))throw new RuntimeException('Este proyecto requiere MySQL 8.0 o posterior (usa FOR SHARE y columnas JSON).');
 $lockName='lab-demo-'.substr(hash('sha256',$name),0,40);$q=$server->prepare('SELECT GET_LOCK(?,0)');$q->execute(array($lockName));
 if((string)$q->fetchColumn()!=='1')throw new RuntimeException('Otra instalacion esta preparando esta misma base. Espera a que termine.');$locked=true;
 $q=$server->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?');$q->execute(array($name));$exists=(bool)$q->fetchColumn();
 if($exists){
  $db=(new Database())->connect();$q=$db->query("SHOW TABLES LIKE 'instalacion_demo'");
  if(!$q->fetchColumn())throw new RuntimeException('La base '.$name.' ya existe y no pertenece a este instalador. No se modifico. Elige otro nombre.');
  $marker=$db->query('SELECT firma,completa FROM instalacion_demo WHERE id=1')->fetch();
  if(!$marker||$marker['firma']!=='laboratorio_demo_v1')throw new RuntimeException('La base existente no tiene una marca valida de demostracion. No se modifico.');
 }else{
  $server->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$db=(new Database())->connect();
  $db->exec("CREATE TABLE instalacion_demo (id TINYINT UNSIGNED PRIMARY KEY,firma VARCHAR(50) NOT NULL,completa TINYINT NOT NULL DEFAULT 0,creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
  $db->exec("INSERT INTO instalacion_demo(id,firma) VALUES(1,'laboratorio_demo_v1')");$marker=array('completa'=>0);
 }
 foreach(array('storage/logs','storage/pruebas','storage/backups','storage/uploads/logos') as $dir){$path=$root.'/'.$dir;if(!is_dir($path)&&!mkdir($path,0770,true))throw new RuntimeException('No se pudo crear '.$dir);if(!is_writable($path))throw new RuntimeException('Falta permiso de escritura en '.$dir);}
 if(!$marker['completa']){
  echo 'Preparando base de demostracion: '.$name.PHP_EOL;
  // Los instaladores originales comparten sus validaciones y migraciones.
  ob_start();try{(function()use($root){require $root.'/scripts/instalar.php';})();}finally{ob_end_clean();}
  foreach(array('app/Helpers/HttpError.php','app/Services/Auth.php','app/Helpers/Dinero.php','app/Services/Cobro.php','app/Services/Solicitud.php','app/Models/Empresa.php') as $file)require_once $root.'/'.$file;
  $model=new Usuario($db);$q=$db->prepare('SELECT id FROM usuarios WHERE nombre_usuario=?');$q->execute(array('admin'));$adminId=$q->fetchColumn();
  if(!$adminId){
   if($db->query('SELECT COUNT(*) FROM usuarios')->fetchColumn())throw new RuntimeException('La instalacion incompleta contiene usuarios ajenos. No se restablecieron sus claves.');
   $admin=$model->guardar(array('nombre'=>'Administrador de pruebas','nombre_usuario'=>'admin','contrasena'=>'Admin1.1'),null,null,true);$adminId=$admin['id'];
  }
  $admin=$model->buscar($adminId);$q=$db->prepare('SELECT contrasena_hash FROM usuarios WHERE id=?');$q->execute(array($adminId));
  if(!$admin||!$admin['activo']||$admin['cambio_obligatorio']||empty($admin['permisos']['administrar_accesos'])||!password_verify('Admin1.1',$q->fetchColumn()))throw new RuntimeException('El administrador de la instalacion incompleta cambio. Se conservaron sus datos; no se restablecio su clave.');
  foreach(array('recepcion'=>array('Recepcion de pruebas','Recepción'),'caja'=>array('Caja de pruebas','Caja'),'laboratorista'=>array('Laboratorista de pruebas','Laboratorista'),'responsable'=>array('Responsable de pruebas','Responsable del laboratorio')) as $login=>$account){
   $q=$db->prepare('SELECT id FROM usuarios WHERE nombre_usuario=?');$q->execute(array($login));if($q->fetchColumn())continue;
   $q=$db->prepare('SELECT id FROM roles WHERE nombre=?');$q->execute(array($account[1]));$role=$q->fetchColumn();if(!$role)throw new RuntimeException('Falta el rol '.$account[1]);
   $model->guardar(array('nombre'=>$account[0],'nombre_usuario'=>$login,'rol_id'=>$role,'contrasena'=>'Admin1.1','activo'=>1),null,$adminId);
  }
  ob_start();try{(function()use($root){require $root.'/scripts/cargar_demo.php';})();}finally{ob_end_clean();}
  // El administrador inicial permanece operativo solo durante esta carga local.
  $brand=new Empresa($db);$identity=$brand->obtener();$identity['nombre_comercial']='Laboratorio de pruebas';$brand->guardar($identity);
  $patients=$db->query("SELECT id FROM pacientes WHERE codigo LIKE 'DEMO-%' AND activo=1 ORDER BY id LIMIT 3")->fetchAll(PDO::FETCH_COLUMN);
  $studies=$db->query("SELECT id FROM estudios WHERE codigo LIKE 'DEMO-%' AND activo=1 ORDER BY id LIMIT 3")->fetchAll(PDO::FETCH_COLUMN);
  if(count($patients)<3||count($studies)<3)throw new RuntimeException('No se completaron los catalogos de demostracion.');
  foreach(array('registrada','registrada','cotizacion') as $i=>$state){
   $notes='DEMO-INSTALACION: ejemplo '.($i+1).' - datos ficticios para pruebas';
   $q=$db->prepare("SELECT id FROM solicitudes WHERE JSON_UNQUOTE(JSON_EXTRACT(datos,'$.notas'))=?");$q->execute(array($notes));if($q->fetchColumn())continue;
   (new Solicitud($db))->guardar(array('estado'=>$state,'paciente_id'=>$patients[$i],'estudios'=>array($studies[$i]),'notas'=>$notes));
  }
  $db->beginTransaction();
  $db->prepare('UPDATE usuarios SET cambio_obligatorio=1 WHERE id=?')->execute(array($adminId));
  $db->exec('UPDATE instalacion_demo SET completa=1 WHERE id=1');
  Auditoria::registrar($db,$adminId,'instalacion_demo_completada','Instalacion de pruebas','Catalogos ficticios y cinco cuentas de prueba',null,array('base'=>$name));$db->commit();
  echo "Creados: 5 usuarios, catalogos ficticios, 2 solicitudes y 1 cotizacion.\n";
 }else echo "La demostracion ya estaba instalada. Se conservaron usuarios, claves y registros.\n";
 if(!$noConfig){
  $configTemp=$root.'/app/Config/.database.local.'.bin2hex(random_bytes(8)).'.php';
  if(file_put_contents($configTemp,"<?php\nreturn ".var_export($connection,true).";\n",LOCK_EX)===false)throw new RuntimeException('No se pudo escribir la configuracion de conexion.');
  if(!rename($configTemp,$configPath))throw new RuntimeException('No se pudo activar database.local.php. La base quedo preparada; puedes ejecutar de nuevo el instalador.');$configTemp=null;
  echo "Conexion guardada en app/Config/database.local.php.\n";
 }else echo "Modo --sin-configurar: la conexion del proyecto se conservo.\n";
 echo "Base: ".$name."\nUsuarios iniciales: admin, recepcion, caja, laboratorista, responsable\n";
 echo "Clave temporal inicial de todos: Admin1.1 (cambio obligatorio al entrar).\n";
 echo "En una reinstalacion se conservan las claves que ya se hayan cambiado.\n";
 echo "Abre http://localhost/Laboratorio/public/ (agrega el puerto de Apache si corresponde).\n";
}catch(Throwable $e){
 if($db&&$db->inTransaction())$db->rollBack();
 if($e instanceof PDOException)fwrite(STDERR,"No se pudo preparar MySQL. Revisa el servicio, los datos de conexion y los permisos para crear bases/tablas. Codigo: ".$e->getCode()."\n");
 else fwrite(STDERR,$e->getMessage()."\n");
 if($locked)$server->prepare('SELECT RELEASE_LOCK(?)')->execute(array($lockName));
 if($configTemp&&is_file($configTemp))unlink($configTemp);
 exit(1);
}
if($locked)$server->prepare('SELECT RELEASE_LOCK(?)')->execute(array($lockName));
