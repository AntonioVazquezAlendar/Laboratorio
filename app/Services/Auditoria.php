<?php
class Auditoria
{
    private static function limpiar($value)
    {
        if (!is_array($value)) { return $value; }
        $out = array();
        foreach ($value as $key => $item) {
            if ($key !== 'contrasena_modificada' && preg_match('/password|contrasen|hash|csrf|token|secret/i', (string)$key)) { continue; }
            $out[$key] = self::limpiar($item);
        }
        return $out;
    }
    public static function usuario(array $u)
    {
        return array_intersect_key($u, array_flip(array('id','nombre','nombre_usuario','rol_id','rol_nombre','activo','cambio_obligatorio')));
    }
    public static function registrar(PDO $db, $actor, $tipo, $referencia, $resumen, $antes, $despues, array $extra = array())
    {
        if (!$db->inTransaction()) { throw new LogicException('La bitácora debe acompañar la transacción.'); }
        $nombre = 'Sin sesión';
        if ($actor !== null) {
            $q = $db->prepare('SELECT nombre FROM usuarios WHERE id=?'); $q->execute(array($actor));
            $nombre = $q->fetchColumn() ?: 'Usuario no disponible';
        }
        $detalle = json_encode(self::limpiar(array('antes'=>$antes,'despues'=>$despues,'contexto'=>$extra)), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $q = $db->prepare('INSERT INTO bitacora_actividad(usuario_id,usuario_nombre,tipo,referencia,resumen,detalle) VALUES(?,?,?,?,?,?)');
        $q->execute(array($actor,$nombre,$tipo,$referencia,$resumen,$detalle));
    }
}
