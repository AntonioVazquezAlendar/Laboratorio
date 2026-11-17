<?php
if(!extension_loaded('curl'))throw new HttpError('El servidor requiere la extensión PHP cURL para generar el PDF.',503);
require_once __DIR__.'/../ThirdParty/tcpdf/tcpdf.php';
class LaboratorioInformePDF extends TCPDF
{
 public $laboratorio=array();public $paciente=array();public $referencia='';public $versionResultado=1;public $logoData=null;public $altoCabecera=50;
 public function Header(){
  $c=$this->laboratorio;$x=16;$w=$this->getPageWidth()-32;$hasLogo=$this->logoData!==null;$textX=$hasLogo?$x+32:$x;$textW=$hasLogo?$w-32:$w;
  if($hasLogo)$this->Image('@'.$this->logoData,$x,12,26,20,'','','',true,150,'',false,false,0,'CM',false,false);
  $this->SetTextColor(24,53,46);$this->SetFont('dejavusans','B',15);$this->SetXY($textX,12);$this->MultiCell($textW,0,$c['nombre_comercial'],0,'L',false,1);
  $this->SetFont('dejavusans','',8);$this->SetX($textX);
  $contact=array_filter(array(isset($c['razon_social'])?$c['razon_social']:'',isset($c['direccion'])?$c['direccion']:'',trim((isset($c['telefono'])?$c['telefono']:'').'  '.(isset($c['correo'])?$c['correo']:'')),isset($c['rfc'])&&$c['rfc']!==''?'RFC: '.$c['rfc']:''));
  $this->MultiCell($textW,0,implode("\n",$contact),0,'L',false,1);
  $y=max($this->GetY()+3,$hasLogo?34:25);$this->SetXY($x,$y);$this->SetFont('dejavusans','B',11);$this->Cell($w,7,'Informe de resultados',0,1);
  $this->SetFont('dejavusans','',8);$this->SetX($x);$this->MultiCell($w,0,$this->referencia.'  |  Versión '.$this->versionResultado."\nPaciente: ".$this->paciente['nombre'].'  |  Expediente: '.$this->paciente['expediente'],0,'L',false,1);
  $this->SetDrawColor(160,180,170);$this->Line($x,$this->altoCabecera-4,$x+$w,$this->altoCabecera-4);
 }
 public function Footer(){
  $this->SetY(-17);$this->SetTextColor(75,89,82);$this->SetFont('dejavusans','',7);
  $message=isset($this->laboratorio['pie_ticket'])?$this->laboratorio['pie_ticket']:'';
  // Keep the repeating footer compact; the full configured message is printed in the document body.
  $this->Cell(0,5,'Informe validado · '.$this->referencia.' · v'.$this->versionResultado,0,1,'L');
  $this->Cell(0,4,'Página '.$this->getAliasNumPage().' de '.$this->getAliasNbPages(),0,0,'R');
 }
}
class InformeResultado
{
 private static function e($v){return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
 private static function logo(array $brand){
  if(empty($brand['logo_url']))return null;$url=parse_url($brand['logo_url']);$args=array();parse_str(isset($url['query'])?$url['query']:'',$args);
  if(!isset($args['v'])||!is_string($args['v'])||!preg_match('/^[a-f0-9]{32}\.(png|jpg|webp)$/D',$args['v'],$m))throw new RuntimeException('La referencia del logo no es válida.');
  $path=dirname(__DIR__,2).'/storage/uploads/logos/'.$args['v'];if(!is_file($path))throw new HttpError('El logo histórico no está disponible. Restaura el archivo para emitir el informe.',409);
  if($m[1]==='webp'){
   if(!function_exists('imagecreatefromwebp'))throw new HttpError('El servidor necesita GD para incluir este logo WebP en PDF. Puedes usar PNG o JPG.',503);
   $image=imagecreatefromwebp($path);if(!$image)throw new RuntimeException('No se pudo leer el logo.');ob_start();imagepng($image);$bytes=ob_get_clean();imagedestroy($image);return $bytes;
  }return file_get_contents($path);
 }
 public static function generar(array $v){
  $ctx=$v['contexto'];$brand=$ctx['laboratorio'];$p=$ctx['paciente'];$study=$ctx['estudio'];
  $pdf=new LaboratorioInformePDF('P','mm','LETTER',true,'UTF-8',false);$pdf->setCreator('Laboratorio');$pdf->SetAuthor($brand['nombre_comercial']);$pdf->SetTitle('Informe '.$v['referencia'].' - versión '.$v['version']);
  $pdf->setFontSubsetting(true);$pdf->SetFont('dejavusans','',9);$pdf->setPrintHeader(true);$pdf->setPrintFooter(true);$pdf->SetAutoPageBreak(true,23);$pdf->SetMargins(16,55,16);$pdf->setImageScale(1.25);
  $pdf->laboratorio=$brand;$pdf->paciente=$p;$pdf->referencia=$v['referencia'];$pdf->versionResultado=$v['version'];$pdf->logoData=self::logo($brand);
  // Calculate the repeated header height with the same font metrics used by Header().
  $width=$pdf->getPageWidth()-32-($pdf->logoData?32:0);$pdf->SetFont('dejavusans','B',15);$nameHeight=$pdf->getStringHeight($width,$brand['nombre_comercial']);
  $contact=array_filter(array(isset($brand['razon_social'])?$brand['razon_social']:'',isset($brand['direccion'])?$brand['direccion']:'',trim((isset($brand['telefono'])?$brand['telefono']:'').'  '.(isset($brand['correo'])?$brand['correo']:'')),!empty($brand['rfc'])?'RFC: '.$brand['rfc']:''));
  $pdf->SetFont('dejavusans','',8);$contactHeight=$pdf->getStringHeight($width,implode("\n",$contact));$base=max(12+$nameHeight+$contactHeight+3,$pdf->logoData?34:25);
  $details=$v['referencia'].'  |  Versión '.$v['version']."\nPaciente: ".$p['nombre'].'  |  Expediente: '.$p['expediente'];$pdf->altoCabecera=$base+7+$pdf->getStringHeight($pdf->getPageWidth()-32,$details)+9;
  $pdf->SetTopMargin($pdf->altoCabecera);$pdf->AddPage();$pdf->SetFont('dejavusans','',9);$pdf->SetTextColor(24,53,46);
  $e=function($x){return self::e($x);};
  $html='<table cellpadding="4"><tr><td width="50%"><b>Fecha de nacimiento:</b> '.$e($p['fecha_nacimiento']?:'No registrada').'</td><td width="50%"><b>Sexo registrado:</b> '.$e(str_replace('_',' ',$p['sexo']?:'No especificado')).'</td></tr><tr><td><b>Solicitud:</b> '.$e($ctx['fecha_solicitud']).'</td><td><b>Médico referente:</b> '.$e($ctx['medico']).'</td></tr></table>';
  if(!empty($brand['encabezado_ticket']))$html.='<p>'.$e($brand['encabezado_ticket']).'</p>';
  $html.='<h2 style="font-size:13pt;">'.$e($study['nombre']).'</h2><p style="font-size:8pt;"><b>Muestra:</b> '.$e($study['muestra']).' &nbsp; <b>Método del estudio:</b> '.$e($study['metodo']).'</p>';
  $html.='<table border="0" cellpadding="6"><thead><tr style="background-color:#e9efeb;"><th width="32%"><b>Analito</b></th><th width="19%"><b>Resultado</b></th><th width="14%"><b>Unidad</b></th><th width="35%"><b>Referencia del laboratorio</b></th></tr></thead><tbody>';
  foreach($v['analitos'] as $i=>$a){$bg=$i%2?'#f5f7f5':'#ffffff';$label=$e($a['nombre']);if(!empty($a['metodo']))$label.='<br><span style="font-size:7pt;color:#52665f;">Método: '.$e($a['metodo']).'</span>';
   $value=nl2br($e($a['valor']));if(!empty($a['comentario']))$value.='<br><span style="font-size:7pt;">'.$e($a['comentario']).'</span>';
   if((isset($a['tipo_resultado'])&&$a['tipo_resultado']==='texto')||strlen($a['valor'])>160){
    preg_match_all('/.{1,600}/us',$a['valor'],$chunks);
    foreach($chunks[0] as $partIndex=>$part){$extra=$partIndex===0?'<p style="font-size:7pt;"><b>Unidad:</b> '.$e($a['unidad']!==''?$a['unidad']:'Sin configurar').'<br><b>Referencia:</b> '.nl2br($e($a['referencia']!==''?$a['referencia']:'No configurada')).(!empty($a['comentario'])?'<br><b>Comentario:</b> '.$e($a['comentario']):'').'</p>':'';
     $html.='<tr nobr="true" style="background-color:'.$bg.';"><td width="32%">'.$label.($partIndex>0?'<br><i>(continuación)</i>':'').'</td><td width="68%" colspan="3">'.nl2br($e($part)).$extra.'</td></tr>';
    }
   }else{$html.='<tr nobr="true" style="background-color:'.$bg.';"><td width="32%">'.$label.'</td><td width="19%"><b>'.$value.'</b></td><td width="14%">'.$e($a['unidad']!==''?$a['unidad']:'Sin configurar').'</td><td width="35%">'.nl2br($e($a['referencia']!==''?$a['referencia']:'No configurada')).'</td></tr>'; }
  }
  $html.='</tbody></table>';
  if($v['contenido']!=='')$html.='<h3 style="font-size:10pt;">Observaciones</h3><p>'.nl2br($e($v['contenido'])).'</p>';
  $html.='<br><table cellpadding="5"><tr><td><b>Capturado por:</b> '.$e(isset($ctx['capturado_por'])?$ctx['capturado_por']:'Usuario '.$v['usuario_id']).'<br><b>Captura:</b> '.$e($v['creado_en']).'</td><td><b>Validado por:</b> '.$e($v['validado_nombre']).'<br><b>Validación:</b> '.$e($v['validado_en']).'</td></tr></table>';
  if(!empty($brand['pie_ticket']))$html.='<p style="font-size:8pt;">'.nl2br($e($brand['pie_ticket'])).'</p>';
  $pdf->writeHTML($html,true,false,true,false,'');return $pdf->Output('','S');
 }
}
