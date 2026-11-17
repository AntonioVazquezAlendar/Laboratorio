<?php
class Dinero {
 public static function centavos($value) {
  if(!is_string($value)&&!is_int($value))throw new HttpError('Escribe un importe válido.');
  if(!preg_match('/^(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?$/D',(string)$value))throw new HttpError('El importe debe ser positivo con hasta 2 decimales.');
  $p=explode('.',(string)$value);return (int)$p[0]*100+(int)str_pad(isset($p[1])?$p[1]:'',2,'0');
 }
 public static function texto($n) { return ($n<0?'-':'').intdiv(abs($n),100).'.'.str_pad((string)(abs($n)%100),2,'0',STR_PAD_LEFT); }
}
