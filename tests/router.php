<?php
if(PHP_SAPI!=='cli-server'){http_response_code(404);exit;}
$uri=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);$prefix='/Laboratorio/public/assets/';
if(strpos($uri,$prefix)===0){
 $base=realpath(__DIR__.'/../public/assets');$path=realpath($base.'/'.substr($uri,strlen($prefix)));
 if($path&&strpos($path,$base.DIRECTORY_SEPARATOR)===0&&is_file($path)){
  $mime=array('js'=>'application/javascript','css'=>'text/css','png'=>'image/png','jpg'=>'image/jpeg','svg'=>'image/svg+xml');$ext=strtolower(pathinfo($path,PATHINFO_EXTENSION));
  if(isset($mime[$ext])){header('Content-Type: '.$mime[$ext]);readfile($path);return true;}
 }
 http_response_code(404);return true;
}
require __DIR__.'/../public/index.php';
