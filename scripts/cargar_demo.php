<?php
// Carga voluntaria de ejemplos; nunca se ejecuta desde el navegador ni al instalar.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../app/Config/Database.php';
require_once __DIR__.'/../app/Helpers/HttpError.php';
require_once __DIR__.'/../app/Services/Auth.php';
require_once __DIR__.'/../app/Models/Catalogo.php';

$db=(new Database())->connect();
$fixture=json_decode(file_get_contents(__DIR__.'/datos_demo.json'),true,512,JSON_THROW_ON_ERROR);
$order=array('tipos_muestra','metodos','companias','medicos','analitos','estudios','pacientes','precios');
$relations=array('_muestra_codigo'=>array('tipos_muestra','tipo_muestra_id'),
    '_metodo_codigo'=>array('metodos','metodo_id'), '_compania_codigo'=>array('companias','compania_id'),
    '_medico_codigo'=>array('medicos','medico_id'), '_estudio_codigo'=>array('estudios','estudio_id'));
$lock=fopen(__DIR__.'/../storage/demo-catalogos.lock','c');
if (!$lock || !flock($lock,LOCK_EX|LOCK_NB)) { throw new RuntimeException('Ya hay una carga de demostración en curso.'); }

function buscarDemo(PDO $db,$table,$code) {
    if ($code===null) { return null; }
    if (!is_string($code) || strpos($code,'DEMO-')!==0) { throw new RuntimeException('Referencia fuera del catálogo de demostración.'); }
    $q=$db->prepare('SELECT id FROM '.$table.' WHERE codigo=?'); $q->execute(array($code));
    $id=$q->fetchColumn();
    if (!$id) { throw new RuntimeException('No se encuentra la referencia '.$code.' en '.$table.'.'); }
    return $id;
}

try {
    // Usa la autorización del administrador local solicitado, sin cambiar su contraseña o sesiones.
    $q=$db->prepare('SELECT u.id,u.contrasena_hash,u.activo,r.permisos FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE u.nombre_usuario=?');
    $q->execute(array('admin')); $actor=$q->fetch();
    if (!$actor || !$actor['activo'] || empty(json_decode($actor['permisos'],true)['administrar_accesos'])) {
        throw new RuntimeException('Se necesita la cuenta local admin activa para registrar la carga.');
    }
    $_SESSION=array('usuario_id'=>$actor['id'],'actividad'=>time(),'credencial'=>hash('sha256',$actor['contrasena_hash']),'epoch'=>Auth::epoch());
    $model=new Catalogo($db); $summary=array();
    foreach ($order as $table) {
        $created=0; $existing=0;
        foreach ($fixture['catalogos'][$table] as $row) {
            if ($table!=='precios' && (!isset($row['codigo']) || strpos($row['codigo'],'DEMO-')!==0)) {
                throw new RuntimeException('Todos los ejemplos deben identificarse con el prefijo DEMO-.');
            }
            foreach ($relations as $key=>$relation) {
                if (array_key_exists($key,$row)) {
                    $row[$relation[1]]=buscarDemo($db,$relation[0],$row[$key]); unset($row[$key]);
                }
            }
            if (isset($row['_analito_codigos'])) {
                $row['analitos']=array();
                foreach ($row['_analito_codigos'] as $code) { $row['analitos'][]=buscarDemo($db,'analitos',$code); }
                unset($row['_analito_codigos']);
            }
            if ($table==='precios') {
                $q=$db->prepare('SELECT id FROM precios WHERE estudio_id=? AND compania_ambito=?');
                $q->execute(array($row['estudio_id'],$row['compania_id']===null?0:$row['compania_id']));
            } else {
                $q=$db->prepare('SELECT id FROM '.$table.' WHERE codigo=?'); $q->execute(array($row['codigo']));
            }
            if ($q->fetchColumn()) { $existing++; continue; }
            $model->guardar($table,$row); $created++;
        }
        $summary[$table]=array('creados'=>$created,'existentes_conservados'=>$existing);
    }
    echo json_encode($summary,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
    echo "Carga terminada. Registros ficticios, precios ilustrativos y configuración técnica por validar.\n";
} finally {
    flock($lock,LOCK_UN); fclose($lock);
}
