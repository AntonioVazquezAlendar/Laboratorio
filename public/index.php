<?php
date_default_timezone_set('America/Mexico_City');
ini_set('display_errors','0'); ini_set('log_errors','1');
ini_set('error_log',__DIR__.'/../storage/logs/php-error.log');
require __DIR__.'/../app/Config/Database.php';
require __DIR__.'/../app/Helpers/HttpError.php';
require __DIR__.'/../app/Helpers/Response.php';
require __DIR__.'/../app/Services/Auth.php';
require __DIR__.'/../app/Models/Rol.php';
require __DIR__.'/../app/Services/Contrasena.php';
require __DIR__.'/../app/Services/Resultado.php';
require __DIR__.'/../app/Models/Catalogo.php';
require __DIR__.'/../app/Helpers/Dinero.php';
require __DIR__.'/../app/Services/Solicitud.php';
require __DIR__.'/../app/Services/Caja.php';
require __DIR__.'/../app/Services/Cobro.php';
require __DIR__.'/../app/Models/Empresa.php';
Auth::iniciarSesion();
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if (!isset($_GET['api']) && in_array($path,array('/Laboratorio/public/','/Laboratorio/public','/Laboratorio/public/index.php'),true)) {
    require __DIR__.'/../views/inicio/index.php'; exit;
}
try { $db=(new Database())->connect(); require __DIR__.'/../app/Routes/api.php'; }
catch(HttpError $e) { Response::json(false,$e->getMessage(),array(),$e->getCode()); }
catch(Throwable $e) { error_log((string)$e); Response::json(false,'No se pudo completar la operación. Si acabas de instalar, prepara la base con scripts/instalar.php.',array(),500); }
