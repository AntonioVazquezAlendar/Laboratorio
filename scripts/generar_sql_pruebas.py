"""Build the HeidiSQL artifact exclusively from a fresh disposable demo database."""
import json, os, re, subprocess, uuid
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
PHP=r'C:\AppServ\php7\php.exe'
CMD=[PHP,'-d',r'extension_dir=C:\AppServ\php7\ext','-d','extension=php_pdo_mysql.dll','-d','extension=php_curl.dll']
SOURCE='laboratorio_test_export_'+uuid.uuid4().hex[:12]
TARGET='laboratorio_pruebas'
OUTPUT=ROOT/'LABORATORIO_PRUEBAS_HEIDISQL.sql'
env=dict(os.environ,LAB_DB_NAME=SOURCE)
original=(ROOT/'app/Config/database.local.php').read_bytes()

def php(code):
 return subprocess.check_output(CMD+['-r',code],cwd=ROOT,env=env,text=True,encoding='utf-8')

def literal(value):
 if value is None:return 'NULL'
 # The artifact explicitly enables NO_BACKSLASH_ESCAPES before any text insert.
 return "'"+str(value).replace(SOURCE,TARGET).replace("'","''")+"'"

try:
 run=subprocess.run(CMD+[str(ROOT/'instalar_pruebas.php'),'--sin-configurar'],cwd=ROOT,env=env,capture_output=True,text=True,encoding='utf-8')
 if run.returncode:raise RuntimeError(run.stderr)
 data=json.loads(php("""
 require 'app/Config/Database.php';$db=(new Database())->connect();$out=array();
 foreach($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table){
  $columns=array();foreach($db->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll() as $c)if(!preg_match('/(?:VIRTUAL|STORED) GENERATED/',$c['Extra']))$columns[]=$c['Field'];
  $select=implode(',',array_map(function($c){return '`'.$c.'`';},$columns));
  $q=$db->prepare('SELECT DISTINCT REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL');$q->execute(array($table));
  $out[$table]=array('ddl'=>$db->query('SHOW CREATE TABLE `'.$table.'`')->fetch()['Create Table'],'columns'=>$columns,'rows'=>$db->query('SELECT '.$select.' FROM `'.$table.'`')->fetchAll(PDO::FETCH_NUM),'depends'=>$q->fetchAll(PDO::FETCH_COLUMN));
 }echo json_encode($out,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
 """))
 pending=dict(data);ordered=[]
 while pending:
  ready=sorted(t for t,v in pending.items() if not (set(v['depends'])-set(ordered)-{t}))
  if not ready:raise RuntimeError('Hay referencias circulares; no se desactivaran las claves foraneas.')
  for t in ready:ordered.append(t);del pending[t]
 header="""-- LABORATORIO CLINICO: INSTALACION DE PRUEBAS PARA HEIDISQL
-- Requiere MySQL 8.0 o posterior. Codificacion UTF-8.
-- HeidiSQL: Archivo > Cargar archivo SQL; ejecutar TODO el archivo (F9).
-- Usar una conexion con permisos para crear bases y tablas.
-- Crea una base NUEVA laboratorio_pruebas. Si ya existe, detenerse en el primer error.
-- No usar la opcion de continuar/ignorar errores. No contiene DROP, DELETE ni reemplazos.
-- Para otro nombre, cambiar CREATE DATABASE y USE antes de ejecutar.
-- Datos ficticios: 30 estudios, 57 analitos, 10 pacientes, 5 medicos,
-- 3 companias, 6 tipos de muestra, 7 metodos y 120 precios ilustrativos.
-- Incluye 2 solicitudes y 1 cotizacion; sin pagos ni resultados clinicos.
-- Usuarios: admin, recepcion, caja, laboratorista, responsable.
-- Clave temporal de TODOS: Admin1.1 (bcrypt; cambio obligatorio al entrar).
-- No es una exportacion de la base operativa del equipo de origen.
-- Despues, configurar app/Config/database.local.php con database=laboratorio_pruebas
-- y las credenciales MySQL del equipo destino; este SQL no modifica archivos PHP.

CREATE DATABASE `laboratorio_pruebas` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `laboratorio_pruebas`;
SET NAMES utf8mb4;
SET @lab_sql_mode_previo = @@SESSION.sql_mode;
SET @lab_zona_previa = @@SESSION.time_zone;
SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_AUTO_VALUE_ON_ZERO,NO_BACKSLASH_ESCAPES';
SET SESSION time_zone = '-06:00';

-- Estructura completa, incluidas migraciones de resultados, cobros y devoluciones.
-- Las tablas se ordenan por sus dependencias; las claves foraneas permanecen activas.
"""
 chunks=[header]
 for table in ordered:
  ddl=data[table]['ddl'].replace(SOURCE,TARGET)
  if not ddl.startswith('CREATE TABLE '):raise RuntimeError('DDL inesperado para '+table)
  chunks.append('\n'+ddl+';\n')
 chunks.append('\n-- Carga de datos de demostracion en una transaccion.\nSTART TRANSACTION;\n')
 for table in ordered:
  columns=', '.join('`'+c+'`' for c in data[table]['columns'])
  for row in data[table]['rows']:
   chunks.append('INSERT INTO `'+table+'` ('+columns+') VALUES ('+', '.join(literal(v) for v in row)+');\n')
 chunks.append("""
COMMIT;
SET SESSION sql_mode = @lab_sql_mode_previo;
SET SESSION time_zone = @lab_zona_previa;

-- Comprobacion final: las cinco cuentas deben mostrar activo=1 y cambio_obligatorio=1.
SELECT u.nombre_usuario AS usuario, r.nombre AS rol, u.activo, u.cambio_obligatorio
FROM usuarios u JOIN roles r ON r.id=u.rol_id ORDER BY u.id;
SELECT 'laboratorio_pruebas' AS base_instalada, 'Admin1.1' AS clave_temporal_inicial;
""")
 sql=''.join(chunks)
 assert SOURCE not in sql and 'FOREIGN_KEY_CHECKS' not in sql
 OUTPUT.write_text(sql,encoding='utf-8',newline='\n')
 print(f'Generado: {OUTPUT.name}; {len(ordered)} tablas; {OUTPUT.stat().st_size} bytes. Sin cambios en la base operativa.')
finally:
 if SOURCE.startswith('laboratorio_test_export_'):
  php("require 'app/Config/Database.php';(new Database())->connect(true)->exec('DROP DATABASE IF EXISTS `"+SOURCE+"`');")
 assert (ROOT/'app/Config/database.local.php').read_bytes()==original
