<?php
class Contrasena
{
    public static function cambiar(PDO $db, array $datos)
    {
        $auth=new Auth($db); $actor=$auth->usuario();
        $actual=isset($datos['actual']) && is_string($datos['actual']) ? $datos['actual'] : '';
        $nueva=isset($datos['nueva']) && is_string($datos['nueva']) ? $datos['nueva'] : '';
        if (!preg_match('/^.{8,}$/us',$nueva) || strlen($nueva)>72) { throw new HttpError('Usa al menos 8 caracteres y un máximo de 72 bytes.'); }
        $db->beginTransaction();
        try {
            $q=$db->prepare('SELECT contrasena_hash FROM usuarios WHERE id=? AND activo=1 FOR UPDATE'); $q->execute(array($actor['id'])); $hash=$q->fetchColumn();
            if (!$hash || !password_verify($actual,$hash)) { throw new HttpError('La contraseña actual es incorrecta.',403); }
            if (password_verify($nueva,$hash)) { throw new HttpError('Elige una contraseña distinta de la actual.'); }
            $q=$db->prepare('UPDATE usuarios SET contrasena_hash=?,cambio_obligatorio=0 WHERE id=?');
            $q->execute(array(password_hash($nueva,PASSWORD_BCRYPT),$actor['id']));
            Auditoria::registrar($db,$actor['id'],'contrasena_cambiada','Usuario '.$actor['id'],'Contraseña actualizada',null,null,array('contrasena_modificada'=>true));
            $db->commit();
            session_regenerate_id(true); $_SESSION['credencial']=$auth->credencial($actor['id']); $_SESSION['csrf']=bin2hex(random_bytes(32));
        } catch(Throwable $e) { if($db->inTransaction()) { $db->rollBack(); } throw $e; }
    }
}
