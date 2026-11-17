<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../app/Helpers/HttpError.php';require __DIR__.'/../app/Services/InformeResultado.php';
$ctx=array('laboratorio'=>array('nombre_comercial'=>'Laboratorio de demostración para revisión de informes con nombres extensos y acentos',
 'razon_social'=>'Configuración de prueba: no es un informe de un paciente real','direccion'=>'Dirección ficticia de demostración, sin ubicación de personas reales',
 'telefono'=>'','correo'=>'laboratorio@example.test','rfc'=>'','logo_url'=>null,'encabezado_ticket'=>'Documento de prueba de paginación, contenido ficticio.','pie_ticket'=>'Informe de prueba sin uso clínico.'),
 'paciente'=>array('nombre'=>'Paciente Ficticio para revisión de textos extensos y paginación','expediente'=>'DEMO-PDF-QA','fecha_nacimiento'=>'2000-01-01','sexo'=>'no_especificado'),
 'estudio'=>array('nombre'=>'Panel de demostración de campos numéricos, cualitativos y de texto','muestra'=>'Muestra de demostración','metodo'=>'Método de demostración'),
 'fecha_solicitud'=>'2026-11-17 12:00:00','medico'=>'Médico Ficticio de Demostración','capturado_por'=>'Capturista QA');
$rows=array();for($i=1;$i<=45;$i++){$rows[]=array('nombre'=>'Analito de demostración '.$i.' con descripción para comprobar ajuste de renglones','valor'=>$i===2?str_repeat('Descripción ficticia de ejemplo con acentos: áéíóú, μ y µ. ',25):'Dato de prueba '.$i,
 'unidad'=>$i===2?'µg/L':'Unidad QA','referencia'=>str_repeat('Referencia textual configurada para esta prueba. ',3),'comentario'=>$i===4?'Comentario de demostración para validar que no se recorte el resultado.':'','metodo'=>'Método QA');}
$v=array('contexto'=>$ctx,'referencia'=>'SOL-DEMO / MULTIPÁGINA','version'=>1,'analitos'=>$rows,'contenido'=>str_repeat('Observación general de prueba. ',20),'usuario_id'=>0,'creado_en'=>'2026-11-17 12:00:00','validado_nombre'=>'Responsable QA','validado_en'=>'2026-11-17 12:10:00');
file_put_contents(__DIR__.'/../storage/pruebas/informe_multipagina.pdf',InformeResultado::generar($v));echo "Informe de paginación generado.\n";
