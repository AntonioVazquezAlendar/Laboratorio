"""Import the HeidiSQL artifact into a disposable database and verify demo accounts/data."""
import json, os, re, subprocess, uuid
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
CMD=[r'C:\AppServ\php7\php.exe','-d',r'extension_dir=C:\AppServ\php7\ext','-d','extension=php_pdo_mysql.dll']
MYSQL=r'C:\AppServ\MySQL\bin\mysql.exe'
DB='laboratorio_test_sql_'+uuid.uuid4().hex[:12]
original=(ROOT/'app/Config/database.local.php').read_bytes()
checks=0
def check(ok,label):
 global checks
 if not ok:raise AssertionError(label)
 checks+=1;print('OK',label,flush=True)
def php(code,env):
 return subprocess.check_output(CMD+['-r',code],cwd=ROOT,env=env,text=True,encoding='utf-8')
local=json.loads(php("$c=require 'app/Config/database.local.php';echo json_encode($c);",dict(os.environ)))
env=dict(os.environ,LAB_DB_NAME=DB,LAB_DB_HOST=local['host'],LAB_DB_USER=local['username'],LAB_DB_PASSWORD=local['password'],MYSQL_PWD=local['password'])
sql=(ROOT/'LABORATORIO_PRUEBAS_HEIDISQL.sql').read_text(encoding='utf-8')
command=[MYSQL,'--host='+local['host'],'--user='+local['username'],'--default-character-set=utf8mb4','--batch']
def run():return subprocess.run(command,env=env,input=sql.replace('laboratorio_pruebas',DB).encode('utf-8'),capture_output=True)
try:
 check(not re.search(r'^\s*(DROP|DELETE|UPDATE|REPLACE|TRUNCATE)\b',sql,re.M|re.I),'SQL no contiene borrados ni reemplazos de registros existentes')
 check('FOREIGN_KEY_CHECKS' not in sql,'importacion conserva activas las claves foraneas')
 first=run();check(first.returncode==0,'archivo SQL completo se importa sin errores: '+first.stderr.decode('utf-8',errors='replace'))
 report=json.loads(php("""require 'app/Config/Database.php';$d=(new Database())->connect();$out=array();
 foreach(array('roles','usuarios','estudios','analitos','pacientes','medicos','companias','tipos_muestra','metodos','precios','solicitudes','solicitud_pagos','solicitud_acciones','resultados','caja_movimientos') as $t)$out[$t]=(int)$d->query('SELECT COUNT(*) FROM '.$t)->fetchColumn();
 foreach($d->query('SELECT u.*,r.permisos FROM usuarios u JOIN roles r ON r.id=u.rol_id ORDER BY u.id')->fetchAll() as $u){$out['cuentas'][$u['nombre_usuario']]=array('clave'=>password_verify('Admin1.1',$u['contrasena_hash']),'activo'=>$u['activo'],'cambio'=>$u['cambio_obligatorio'],'permisos'=>json_decode($u['permisos'],true));}
 $out['estados']=$d->query('SELECT estado,COUNT(*) cantidad FROM solicitudes GROUP BY estado')->fetchAll();
 $out['cancelada']=strpos($d->query("SHOW COLUMNS FROM solicitudes LIKE 'estado'")->fetch()['Type'],"'cancelada'")!==false;
 $out['referencias']=$d->query("SHOW COLUMNS FROM analitos LIKE 'referencia_texto'")->fetch()!==false;
 $out['captura']=$d->query("SHOW COLUMNS FROM resultado_versiones LIKE 'datos_analitos'")->fetch()!==false;
 $out['tablas']=count($d->query('SHOW TABLES')->fetchAll());echo json_encode($out);""",env))
 check(report['tablas']==25 and report['usuarios']==5 and report['roles']==5,'estructura completa y cinco usuarios/roles importados')
 check(set(report['cuentas'])=={'admin','recepcion','caja','laboratorista','responsable'},'nombres de acceso previstos')
 check(all(u['clave'] and u['activo'] and u['cambio'] for u in report['cuentas'].values()),'Admin1.1 funciona para todos y exige cambio inicial')
 fixture=json.loads((ROOT/'scripts/datos_demo.json').read_text(encoding='utf-8'))
 check(all(report[k]==len(v) for k,v in fixture['catalogos'].items()),'todos los catalogos ficticios y precios se importan completos')
 check(report['solicitudes']==3 and {r['estado']:r['cantidad'] for r in report['estados']}=={'cotizacion':1,'registrada':2},'incluye dos solicitudes y una cotizacion')
 check(all(report[k]==0 for k in ['solicitud_pagos','solicitud_acciones','resultados','caja_movimientos']),'no inventa pagos, devoluciones ni resultados clinicos')
 caja=report['cuentas']['caja']['permisos'];admin=report['cuentas']['admin']['permisos']
 check(caja['cobrar'] and caja['devolver_pagos'] and not caja['autorizar_anulaciones'] and admin['autorizar_anulaciones'],'caja ejecuta devoluciones y administrador autoriza')
 check(report['cuentas']['responsable']['permisos']['validar_resultados'] and not report['cuentas']['recepcion']['permisos']['validar_resultados'],'separacion de captura y validacion clinica')
 check(report['cancelada'] and report['referencias'] and report['captura'],'incluye columnas agregadas por las migraciones actuales')
 second=run();check(second.returncode!=0 and b'1007' in second.stderr,'reimportar se detiene al existir la base, sin sobrescribirla')
 check(php("require 'app/Config/Database.php';echo (new Database())->connect()->query('SELECT COUNT(*) FROM solicitudes')->fetchColumn();",env)=='3','reimportacion rechazada conserva solicitudes existentes')
 print(f'PASS: {checks} verificaciones del SQL de HeidiSQL; base aislada.',flush=True)
finally:
 if DB.startswith('laboratorio_test_sql_'):
  php("require 'app/Config/Database.php';(new Database())->connect(true)->exec('DROP DATABASE IF EXISTS `"+DB+"`');",env)
 assert (ROOT/'app/Config/database.local.php').read_bytes()==original
