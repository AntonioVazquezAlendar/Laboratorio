"""Installer verification on a disposable copy, without changing this app's connection."""
import base64, json, os, shutil, subprocess, uuid
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
PHP=os.environ.get('LAB_TEST_PHP',r'C:\AppServ\php7\php.exe')
CMD=[PHP,'-d',r'extension_dir=C:\AppServ\php7\ext','-d','extension=php_pdo_mysql.dll','-d','extension=php_curl.dll']
token=uuid.uuid4().hex[:12]
DB='laboratorio_test_inst_'+token
GUARD='laboratorio_test_guard_'+token
COPY=ROOT/'storage/pruebas'/('instalador-'+token)
checks=0

def check(ok,label):
 global checks
 if not ok:raise AssertionError(label)
 checks+=1;print('OK',label,flush=True)

def php(code,env,cwd=ROOT):
 return subprocess.check_output(CMD+['-r',code],cwd=cwd,env=env,text=True,encoding='utf-8')

# Credentials remain in process memory, never in commands, logs, fixtures or test output.
connection=json.loads(php("$c=require 'app/Config/database.local.php';echo json_encode($c);",dict(os.environ)))
env=dict(os.environ,LAB_DB_HOST=connection['host'],LAB_DB_USER=connection['username'],LAB_DB_PASSWORD=connection['password'],LAB_DB_NAME=DB)
original=(ROOT/'app/Config/database.local.php').read_bytes()
def install(name=DB,extra=(),secret=None):
 e=dict(env,LAB_DB_NAME=name)
 args=CMD+[str(COPY/'instalar_pruebas.php')]+list(extra)
 return subprocess.run(args,cwd=ROOT,env=e,input=secret,capture_output=True,text=True,encoding='utf-8')

try:
 COPY.mkdir(parents=True)
 shutil.copytree(ROOT/'app',COPY/'app',ignore=shutil.ignore_patterns('database.local.php','ThirdParty'))
 (COPY/'scripts').mkdir()
 for f in ['instalar.php','cargar_demo.php','datos_demo.json']:shutil.copy2(ROOT/'scripts'/f,COPY/'scripts'/f)
 shutil.copy2(ROOT/'instalar_pruebas.php',COPY/'instalar_pruebas.php')
 shutil.copy2(ROOT/'INSTALAR_PRUEBAS.ps1',COPY/'INSTALAR_PRUEBAS.ps1')
 check(not (COPY/'app/Config/database.local.php').exists(),'copia comienza sin configuracion local')
 encoded=base64.b64encode(connection['password'].encode()).decode()+'\n'
 run=install(extra=('--password-stdin',),secret=encoded)
 check(run.returncode==0,'instalacion completa en una copia nueva: '+run.stderr)
 check((ROOT/'app/Config/database.local.php').read_bytes()==original,'configuracion del proyecto actual permanece intacta')
 code="""require 'app/Config/Database.php';$db=(new Database())->connect();$out=array('base'=>$db->query('SELECT DATABASE()')->fetchColumn());
 $rows=$db->query('SELECT u.nombre_usuario,u.contrasena_hash,u.cambio_obligatorio,u.activo,r.nombre,r.permisos FROM usuarios u JOIN roles r ON r.id=u.rol_id ORDER BY u.id')->fetchAll();
 foreach($rows as &$r){$r['clave_correcta']=password_verify('Admin1.1',$r['contrasena_hash']);unset($r['contrasena_hash']);$r['permisos']=json_decode($r['permisos'],true);}unset($r);$out['usuarios']=$rows;
 foreach(array('estudios','analitos','pacientes','medicos','companias','tipos_muestra','metodos','precios','solicitudes','solicitud_pagos','caja_movimientos') as $t)$out[$t]=(int)$db->query('SELECT COUNT(*) FROM '.$t)->fetchColumn();echo json_encode($out);"""
 # No database/connection environment: Apache will use the generated local configuration.
 local_env={k:v for k,v in os.environ.items() if k not in ['LAB_DB_HOST','LAB_DB_USER','LAB_DB_PASSWORD','LAB_DB_NAME']}
 report=json.loads(php(code,local_env,COPY))
 check(report['base']==DB,'conexion guardada selecciona la nueva base sin variables de entorno')
 check({u['nombre_usuario'] for u in report['usuarios']}=={'admin','recepcion','caja','laboratorista','responsable'},'cinco cuentas con los nombres previstos')
 check(all(u['clave_correcta'] and u['cambio_obligatorio'] and u['activo'] for u in report['usuarios']),'todos tienen Admin1.1, estan activos y exigen cambio inicial')
 users={u['nombre_usuario']:u for u in report['usuarios']}
 check(users['caja']['permisos']['cobrar'] and users['caja']['permisos']['devolver_pagos'] and not users['caja']['permisos']['autorizar_anulaciones'],'caja cobra y devuelve sin autorizar')
 check(users['admin']['permisos']['autorizar_anulaciones'] and not users['admin']['permisos']['validar_resultados'],'administrador autoriza operaciones financieras sin validar clinicamente')
 check(users['responsable']['permisos']['validar_resultados'] and not users['recepcion']['permisos']['validar_resultados'],'responsable valida y recepcion conserva separacion de funciones')
 fixture=json.loads((ROOT/'scripts/datos_demo.json').read_text(encoding='utf-8'))
 check(all(report[k]==len(v) for k,v in fixture['catalogos'].items()),'catalogos coinciden con los ejemplos ficticios del proyecto')
 check(report['solicitudes']==3 and report['solicitud_pagos']==0 and report['caja_movimientos']==0,'tres solicitudes/cotizacion sin movimientos financieros inventados')
 php("require 'app/Config/Database.php';$d=(new Database())->connect();$d->prepare('UPDATE usuarios SET contrasena_hash=?,cambio_obligatorio=0 WHERE nombre_usuario=?')->execute(array(password_hash('PersonalQA-456',PASSWORD_BCRYPT),'caja'));",env,COPY)
 saved=(COPY/'app/Config/database.local.php').read_bytes()
 rerun=install(extra=('--sin-configurar',))
 check(rerun.returncode==0,'repetir el instalador finaliza correctamente')
 again=json.loads(php(code,local_env,COPY))
 check(not next(u for u in again['usuarios'] if u['nombre_usuario']=='caja')['clave_correcta'],'repetir no restablece una clave que ya se cambio')
 check(again['solicitudes']==3 and again['estudios']==30,'repetir no duplica solicitudes ni estudios')
 check((COPY/'app/Config/database.local.php').read_bytes()==saved,'modo sin configurar conserva la conexion')
 # Run the actual Windows assistant, replacing only interactive credential input.
 if os.name=='nt':
  quoted_script=str(COPY/'INSTALAR_PRUEBAS.ps1').replace("'","''")
  quoted_php=PHP.replace("'","''")
  ps="function Read-Host { param([string]$Prompt,[switch]$AsSecureString) if($AsSecureString){$secret=[Security.SecureString]::new();foreach($c in $env:LAB_DB_PASSWORD.ToCharArray()){$secret.AppendChar($c)};return $secret};if($Prompt.StartsWith('Servidor')){return $env:LAB_DB_HOST};return $env:LAB_DB_USER }; & '"+quoted_script+"' -BaseDeDatos '"+DB+"' -Php '"+quoted_php+"'"
  assistant=subprocess.run(['powershell.exe','-NoProfile','-Command',ps],env=env,capture_output=True,text=True)
  detail=assistant.stdout+assistant.stderr
  if connection['password']:detail=detail.replace(connection['password'],'[oculta]')
  check(assistant.returncode==0,'asistente Windows encuentra PHP, carga extensiones y recibe la clave oculta: '+detail)
  check((COPY/'app/Config/database.local.php').read_bytes()==saved,'asistente Windows guarda la misma conexion sin tocar claves de usuarios')
 if connection['password']:
  empty=install(extra=('--sin-configurar','--password-stdin'),secret='\n')
  check(empty.returncode==1,'clave MySQL vacia explicita no reutiliza la clave local o heredada')
 guard_env=dict(env,LAB_DB_NAME=GUARD)
 php("require 'app/Config/Database.php';$s=(new Database())->connect(true);$s->exec('CREATE DATABASE `"+GUARD+"`');$s->exec('CREATE TABLE `"+GUARD+"`.conservar(id INT PRIMARY KEY)');$s->exec('INSERT INTO `"+GUARD+"`.conservar VALUES(42)');",guard_env)
 refused=install(GUARD)
 check(refused.returncode==1 and 'no pertenece' in refused.stderr,'rechaza base existente ajena antes de modificarla')
 intact=php("require 'app/Config/Database.php';$d=(new Database())->connect();echo count($d->query('SHOW TABLES')->fetchAll()).':'.$d->query('SELECT id FROM conservar')->fetchColumn();",guard_env)
 check(intact=='1:42','base ajena conserva sus tablas y registros')
 check((COPY/'app/Config/database.local.php').read_bytes()==saved,'rechazo tampoco cambia la conexion de la copia')
 print(f'PASS: {checks} verificaciones del instalador; copia y bases aisladas.',flush=True)
finally:
 for dbname in [DB,GUARD]:
  if dbname.startswith('laboratorio_test_'):
   php("require 'app/Config/Database.php';(new Database())->connect(true)->exec('DROP DATABASE IF EXISTS `"+dbname+"`');",env)
 # Verify the resolved recursive deletion target stays in this test workspace.
 if COPY.exists():
  assert COPY.resolve().parent==(ROOT/'storage/pruebas').resolve() and COPY.name=='instalador-'+token
  shutil.rmtree(COPY)
 assert (ROOT/'app/Config/database.local.php').read_bytes()==original
