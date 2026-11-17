<?php
class Empresa
{
    private $db;
    public function __construct(PDO $db) { $this->db=$db; }
    public static function campos() {
        return array('nombre_comercial'=>150,'razon_social'=>200,'rfc'=>13,'telefono'=>30,'correo'=>150,'sitio_web'=>200,'direccion'=>300,'codigo_postal'=>5,'encabezado_ticket'=>300,'pie_ticket'=>300);
    }
    public function obtener() {
        $r=$this->db->query('SELECT * FROM empresa WHERE id=1')->fetch();
        if(!$r)throw new HttpError('Falta preparar la identidad del laboratorio.',503);
        $r['logo_url']=$r['logo_ruta']?'/Laboratorio/public/index.php?api=empresa/logo&v='.rawurlencode(basename($r['logo_ruta'])):null;
        unset($r['logo_ruta']);return $r;
    }
    public function documento() {
        return array_intersect_key($this->obtener(),array_flip(array_merge(array_keys(self::campos()),array('logo_url'))));
    }
    public function guardar(array $datos,$archivo=null) {
        $actor=(new Auth($this->db))->administrador();$values=array();
        foreach(self::campos() as $key=>$max) {
            $v=isset($datos[$key])&&is_string($datos[$key])?trim($datos[$key]):'';
            if(($key==='nombre_comercial'&&$v==='')||preg_match('/^.{0,'.$max.'}$/usD',$v)!==1||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/u',$v))throw new HttpError('Revisa el campo '.$key.'.');
            $values[$key]=$v;
        }
        if($values['correo']!==''&&!filter_var($values['correo'],FILTER_VALIDATE_EMAIL))throw new HttpError('El correo no es válido.');
        if($values['sitio_web']!==''&&(!filter_var($values['sitio_web'],FILTER_VALIDATE_URL)||!preg_match('#^https?://#i',$values['sitio_web'])))throw new HttpError('El sitio web debe comenzar por https:// o http://.');
        if($values['codigo_postal']!==''&&!preg_match('/^[0-9]{5}$/D',$values['codigo_postal']))throw new HttpError('El código postal debe tener 5 dígitos.');
        foreach(array('color_primario','color_secundario','color_acento') as $key) {
            if(!isset($datos[$key])||!is_string($datos[$key])||!preg_match('/^#[0-9a-f]{6}$/iD',$datos[$key]))throw new HttpError('Selecciona colores hexadecimales válidos.');
            $values[$key]=strtoupper($datos[$key]);
        }
        $nuevo=null;$storage=dirname(__DIR__,2).'/storage/';
        if($archivo && isset($archivo['error']) && $archivo['error']!==UPLOAD_ERR_NO_FILE) {
            if(!is_int($archivo['error'])||$archivo['error']!==UPLOAD_ERR_OK||!isset($archivo['tmp_name'],$archivo['size'])||!is_string($archivo['tmp_name'])||!is_uploaded_file($archivo['tmp_name'])||$archivo['size']<1||$archivo['size']>2097152)throw new HttpError('Elige un logo de hasta 2 MB.');
            $formats=array('image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp');
            $size=@getimagesize($archivo['tmp_name']);$mime=$size&&isset($size['mime'])?$size['mime']:'';
            if(class_exists('finfo'))$mime=(new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']);
            if(!$size||!isset($formats[$mime])||$size['mime']!==$mime||$size[0]>4096||$size[1]>4096)throw new HttpError('Usa un logo PNG, JPG o WebP de hasta 4096 × 4096 píxeles.');
            $dir=$storage.'uploads/logos';if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))throw new RuntimeException('No se pudo preparar la carpeta de logos.');
            $nuevo='uploads/logos/'.bin2hex(random_bytes(16)).'.'.$formats[$mime];
            if(!move_uploaded_file($archivo['tmp_name'],$storage.$nuevo))throw new RuntimeException('No se pudo guardar el logo.');
        }
        try {
            $this->db->beginTransaction();
            $this->db->query('SELECT id FROM roles ORDER BY id FOR SHARE')->fetchAll();$this->db->query('SELECT id FROM usuarios ORDER BY id FOR SHARE')->fetchAll();
            $actor=(new Auth($this->db))->administrador();
            $old=$this->db->query('SELECT * FROM empresa WHERE id=1 FOR UPDATE')->fetch();
            if(!isset($datos['version'])||(string)$datos['version']!==(string)$old['version'])throw new HttpError('La identidad cambió. Recarga antes de guardar.',409);
            if($nuevo!==null||isset($datos['quitar_logo'])&&$datos['quitar_logo']==='1')$values['logo_ruta']=$nuevo;
            $sets=array_map(function($k){return $k.'=?';},array_keys($values));
            $q=$this->db->prepare('UPDATE empresa SET '.implode(',',$sets).',version=version+1 WHERE id=1');$q->execute(array_values($values));
            Auditoria::registrar($this->db,$actor['id'],'identidad_editada','Laboratorio','Identidad y datos del laboratorio',array_diff_key($old,array('logo_ruta'=>true)),$values);
            $this->db->commit();return $this->obtener();
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();if($nuevo!==null&&is_file($storage.$nuevo))unlink($storage.$nuevo);throw $e;}
    }
    public function logo($filename=null) {
        if($filename===null){$ruta=$this->db->query('SELECT logo_ruta FROM empresa WHERE id=1')->fetchColumn();$filename=$ruta?basename($ruta):'';}
        if(!is_string($filename)||!preg_match('/^[a-f0-9]{32}\.(png|jpg|webp)$/D',$filename,$m))throw new HttpError('Logo no encontrado.',404);
        $path=dirname(__DIR__,2).'/storage/uploads/logos/'.$filename;
        if(!is_file($path))throw new HttpError('Logo no encontrado.',404);
        $types=array('png'=>'image/png','jpg'=>'image/jpeg','webp'=>'image/webp');header('Content-Type: '.$types[$m[1]]);header('Content-Length: '.filesize($path));readfile($path);exit;
    }
    public static function textoContraste($color)
    {
        $rgb = array_map('hexdec', str_split(substr($color, 1), 2));
        $linear = array_map(function ($n) { $v = $n / 255; return $v <= 0.04045 ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4); }, $rgb);
        $luz = .2126 * $linear[0] + .7152 * $linear[1] + .0722 * $linear[2];
        return (1.05 / ($luz + .05)) >= (($luz + .05) / .05) ? '#FFFFFF' : '#000000';
    }


    public function estilos() {
        $e=$this->obtener();foreach(array('color_primario','color_secundario','color_acento') as $key)if(!preg_match('/^#[0-9a-f]{6}$/iD',$e[$key]))throw new RuntimeException('Color no válido.');
        $p=$e['color_primario'];$s=$e['color_secundario'];$a=$e['color_acento'];
        header('Content-Type: text/css; charset=utf-8');
        echo ':root{--brand-primary:'.$p.';--brand-secondary:'.$s.';--brand-accent:'.$a.';--on-primary:'.self::textoContraste($p).';--on-secondary:'.self::textoContraste($s).';--on-accent:'.self::textoContraste($a).';}';exit;
    }
}
