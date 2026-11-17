"""Integration checks against an isolated, disposable laboratory database."""
import os, json, subprocess, base64, re, struct, zlib, urllib.request, urllib.error, http.cookiejar, socket, time, uuid
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
PHP=r'C:\AppServ\php7\php.exe'
CMD=[PHP,'-d',r'extension_dir=C:\AppServ\php7\ext','-d','extension=php_pdo_mysql.dll','-d','extension=php_curl.dll']
PDF_PYTHON=os.environ.get('LAB_PDF_PYTHON','C:/Users/Antonio/.cache/codex-runtimes/codex-primary-runtime/dependencies/python/python.exe')
DB='laboratorio_test_'+uuid.uuid4().hex[:12]
env=dict(os.environ,LAB_DB_NAME=DB)
checks=0

def check(condition,label):
 global checks
 if not condition: raise AssertionError(label)
 checks+=1
 print('OK',label,flush=True)

class Client:
 def __init__(self):
  self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
  self.csrf=''
 def call(self,route,method='GET',data=None,code=200,csrf=True):
  headers={'Content-Type':'application/json'}
  if csrf: headers['X-CSRF-Token']=self.csrf
  req=urllib.request.Request(BASE+route,data=json.dumps(data).encode() if data is not None else None,method=method,headers=headers)
  try:
   response=self.opener.open(req); status=response.status; body=json.load(response)
  except urllib.error.HTTPError as e: status=e.code; body=json.load(e)
  check(status==code,route+' '+str(status)+' expected '+str(code)+' '+str(body.get('message')))
  if isinstance(body.get('data'),dict) and 'csrf' in body['data']: self.csrf=body['data']['csrf']
  return body['data']
 def form(self,route,fields,file=None,code=200,csrf=True):
  boundary='----LabQA'+uuid.uuid4().hex
  parts=[]
  for key,value in fields.items():
   if value is not None:parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode())
  if file:
   filename,mime,data=file
   parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="logo"; filename="{filename}"\r\nContent-Type: {mime}\r\n\r\n'.encode()+data+b'\r\n')
  parts.append(f'--{boundary}--\r\n'.encode())
  headers={'Content-Type':'multipart/form-data; boundary='+boundary}
  if csrf:headers['X-CSRF-Token']=self.csrf
  req=urllib.request.Request(BASE+route,data=b''.join(parts),headers=headers)
  try:
   response=self.opener.open(req);status=response.status;body=json.load(response)
  except urllib.error.HTTPError as e:status=e.code;body=json.load(e)
  check(status==code,route+' multipart '+str(status)+' expected '+str(code)+' '+str(body.get('message')))
  return body['data']
 def pdf(self,route):
  with self.opener.open(BASE+route) as response:
   data=response.read();check(response.status==200 and response.headers.get_content_type()=='application/pdf' and data.startswith(b'%PDF-'),'PDF autenticado válido')
   check('no-store' in response.headers.get('Cache-Control',''),'PDF impide caché de datos clínicos')
   return data
 def session(self):return self.call('sesion')
 def login(self,name,password):
  self.session();return self.call('login','POST',{'nombre_usuario':name,'contrasena':password})

server=None
uploaded_logos=[]
try:
 subprocess.run(CMD+[str(ROOT/'scripts/instalar.php')],env=env,check=True,capture_output=True)
 with socket.socket() as sock:
  sock.bind(('127.0.0.1',0)); port=sock.getsockname()[1]
 BASE=f'http://127.0.0.1:{port}/Laboratorio/public/api/'
 log=open(ROOT/'storage/logs/test-server.log','w')
 server=subprocess.Popen(CMD+['-S',f'127.0.0.1:{port}',str(ROOT/'tests/router.php')],cwd=ROOT,env=env,stdout=log,stderr=log)
 for _ in range(80):
  try:
   with socket.create_connection(('127.0.0.1',port),timeout=.2):break
  except OSError:time.sleep(.1)
 admin=Client();admin.session()
 admin.call('usuarios',code=401)
 admin.call('configurar','POST',{'nombre':'Admin QA','nombre_usuario':'adminqa','contrasena':'TemporalQA-123'})
 admin.call('configurar','POST',{'nombre':'Otro','nombre_usuario':'otroqa','contrasena':'TemporalQA-123'},code=409)
 admin.login('adminqa','TemporalQA-123')
 admin.call('resultados',code=403)
 admin.call('usuarios','POST',{'nombre':'Sin CSRF'},code=403,csrf=False)
 d=admin.call('roles'); roles={r['nombre']:r for r in d['roles']};catalog=d['catalogo']
 check(not roles['Administrador']['permisos']['validar_resultados'],'administrador sin autorización clínica')
 check(roles['Recepción']['permisos']['capturar_resultados'] and not roles['Recepción']['permisos']['validar_resultados'],'recepción captura sin validar resultados')
 # A delivery-only fixture keeps coverage for restricted result consultation;
 # the real Reception role now has capture permission per the product requirements.
 delivery=dict(roles['Recepción']['permisos']);delivery['capturar_resultados']=False
 delivery_id=admin.call('roles','POST',{'nombre':'Entrega QA','permisos':delivery})['id']
 roles['Entrega QA']={'id':delivery_id,'permisos':delivery}
 accounts={}
 for label,rol in [('labqa','Laboratorista'),('respqa','Responsable del laboratorio'),('recepqa','Entrega QA'),('cajaqa','Caja')]:
  u=admin.call('usuarios','POST',{'nombre':label,'nombre_usuario':label,'contrasena':'TemporalQA-123','rol_id':roles[rol]['id'],'activo':1},code=201)
  c=Client();c.login(label,'TemporalQA-123');check(bool(c.session()['usuario']['cambio_obligatorio']),'contraseña temporal '+label)
  c.call('resultados',code=403)
  c.call('contrasena','POST',{'actual':'incorrecta','nueva':'PersonalQA-456'},code=403)
  c.call('contrasena','POST',{'actual':'TemporalQA-123','nueva':'PersonalQA-456'})
  check(not c.session()['usuario']['cambio_obligatorio'],'cambio obligatorio completado '+label)
  accounts[label]=(c,u,rol)
 lab,labu,_=accounts['labqa'];resp,respu,_=accounts['respqa'];recep,recepu,_=accounts['recepqa'];caja,cajau,_=accounts['cajaqa']
 caja.call('usuarios',code=403);caja.call('resultados',code=403)
 lab.call('roles',code=403)
 # Simulate an existing pre-migration text result, without permitting new unlinked captures.
 lab.call('resultados','POST',{'referencia':'Sin solicitud','contenido':'No debe registrarse'},code=422)
 legacy_code="require 'app/Config/Database.php'; $db=(new Database())->connect(); $db->prepare('INSERT INTO resultados(referencia,creado_por) VALUES(?,?)')->execute(array('QA-ORDEN-001',$argv[1])); $id=$db->lastInsertId(); $db->prepare('INSERT INTO resultado_versiones(resultado_id,version,contenido,motivo,usuario_id) VALUES(?,1,?,?,?)')->execute(array($id,'Contenido de prueba sin datos clínicos reales','Captura histórica',$argv[1])); echo $id;"
 rid=subprocess.check_output(CMD+['-r',legacy_code,str(labu['id'])],cwd=ROOT,env=env,text=True).strip()
 lab.call(f'resultados/{rid}/validar','POST',{'version':1},code=403)
 check(recep.call('resultados')==[],'recepción no lista borradores')
 recep.call(f'resultados/{rid}',code=403)
 recep.call(f'resultados/{rid}/entregar','POST',{'version':1,'destinatario':'QA'},code=409)
 resp.call(f'resultados/{rid}/validar','POST',{'version':1})
 resp.call(f'resultados/{rid}/validar','POST',{'version':1},code=409)
 check(len(recep.call('resultados'))==1,'recepción consulta resultados validados')
 recep.call(f'resultados/{rid}/entregar','POST',{'version':1,'destinatario':'Persona QA'})
 resp.call(f'resultados/{rid}/corregir','POST',{'version':1,'contenido':'Segunda versión QA','motivo':''},code=422)
 resp.call(f'resultados/{rid}/corregir','POST',{'version':1,'contenido':'Segunda versión QA','motivo':'Corrección QA'})
 history=resp.call(f'resultados/{rid}')
 check(len(history['historial'])==2 and history['estado']=='borrador','corrección conserva versiones y vuelve a borrador')
 check(history['historial'][1]['contenido']=='Contenido de prueba sin datos clínicos reales','contenido anterior intacto')
 check(len(history['entregas'])==1 and history['entregas'][0]['version']==1,'entrega anterior conserva su versión')
 recep.call(f'resultados/{rid}',code=403)
 resp.call(f'resultados/{rid}/validar','POST',{'version':1},code=409)
 resp.call(f'resultados/{rid}/validar','POST',{'version':2})
 h=recep.call(f'resultados/{rid}');check(len(h['historial'])==1 and h['historial'][0]['version']==2,'recepción solo ve versión vigente autorizada')


 # Public branding; only administrators may change it.
 brand=admin.call('empresa');check(brand['nombre_comercial']=='Laboratorio clínico','identidad predeterminada')
 public_client=Client();public_client.call('empresa')
 admin.form('empresa',dict(brand,nombre_comercial='Sin CSRF'),code=403,csrf=False)
 caja.form('empresa',dict(brand,nombre_comercial='Sin permiso'),code=403)
 admin.form('empresa',dict(brand,color_primario='red;body{}'),code=422)
 admin.form('empresa',dict(brand,correo='correo-invalido'),code=422)
 admin.form('empresa',dict(brand,nombre_comercial=''),code=422)
 def pngchunk(kind,data):return struct.pack('!I',len(data))+kind+data+struct.pack('!I',zlib.crc32(kind+data)&0xffffffff)
 pixels=b''.join(b'\x00'+b''.join(bytes((255,255,255) if 14<x<98 and (15<y<23 or 36<y<44) else (20,80,65)) for x in range(120)) for y in range(60))
 png=b'\x89PNG\r\n\x1a\n'+pngchunk(b'IHDR',struct.pack('!IIBBBBB',120,60,8,2,0,0,0))+pngchunk(b'IDAT',zlib.compress(pixels))+pngchunk(b'IEND',b'')
 admin.form('empresa',brand,('logo.svg','image/svg+xml',b'<svg xmlns="http://www.w3.org/2000/svg"></svg>'),code=422)
 admin.form('empresa',brand,('logo.png','image/png',png+b'0'*2097152),code=422)
 updated=admin.form('empresa',dict(brand,nombre_comercial='Laboratorio QA',color_primario='#102030',color_secundario='#102030',color_acento='#cc7722'),('logo.png','image/png',png))
 logo_name=updated['logo_url'].split('&v=')[-1];check(bool(re.fullmatch(r'[a-f0-9]{32}\.png',logo_name)),'logo usa nombre aleatorio seguro')
 uploaded_logos.append(logo_name)
 logo_response=admin.opener.open(BASE+'empresa/logo?v='+logo_name);check(logo_response.status==200 and logo_response.read()==png,'logo servido desde ruta pública controlada')
 theme=admin.opener.open(BASE+'empresa/tema.css').read().decode();check('--brand-primary:#102030' in theme and '--on-primary:#FFFFFF' in theme,'tema persiste y calcula contraste para fondos oscuros')
 admin.form('empresa',dict(brand,nombre_comercial='Edición desactualizada'),code=409)
 removed=admin.form('empresa',dict(updated,quitar_logo='1',color_primario='#FFFFFF'))
 check(removed['logo_url'] is None,'retirar logo conserva identidad')
 admin.call('empresa/logo',code=404)
 check(admin.opener.open(BASE+'empresa/logo?v='+logo_name).status==200,'logo histórico sigue disponible para documentos anteriores')
 theme=admin.opener.open(BASE+'empresa/tema.css').read().decode();check('--on-primary:#000000' in theme,'tema elige texto oscuro para fondos claros')
 admin.call('empresa/logo?v=../../database.local.php',code=404)

 # Catalog and reception integration, all synthetic and isolated.
 def create(kind,data):return admin.call('catalogos/'+kind,'POST',data,code=201)
 def get(kind,id):return admin.call('catalogos/'+kind+'/'+str(id))
 def update(kind,row,changes):
  data=dict(row,**changes)
  if kind=='estudios':data['analitos']=[a['id'] if isinstance(a,dict) else a for a in data.get('analitos',[])]
  return admin.call('catalogos/'+kind+'/'+str(row['id']),'PUT',data)
 sample=create('tipos_muestra',{'codigo':'M-QA','nombre':'Muestra QA','recipiente':'Recipiente QA','indicaciones':'Indicaciones ficticias'})
 method=create('metodos',{'codigo':'MET-QA','nombre':'Método QA','descripcion':'Prueba técnica'})
 analyte=create('analitos',{'codigo':'A-QA','nombre':'Analito QA','tipo_resultado':'numerico','decimales':'2','metodo_id':method['id']})
 study=create('estudios',{'codigo':'E-QA','nombre':'Estudio QA','tipo_muestra_id':sample['id'],'metodo_id':method['id'],'analitos':[analyte['id']]})
 second=create('estudios',{'codigo':'E-QA2','nombre':'Estudio QA sin precio','analitos':[]})
 company=create('companias',{'codigo':'C-QA','nombre':'Compañía QA','correo':'contacto@example.test'})
 doctor=create('medicos',{'codigo':'D-QA','nombre':'Médico','apellidos':'Ficticio','cedula':'QA123'})
 patient=create('pacientes',{'nombre':'Paciente','apellidos':'Ficticio','fecha_nacimiento':'2000-02-29','sexo':'no_especificado','medico_id':doctor['id'],'compania_id':company['id']})
 price=create('precios',{'estudio_id':study['id'],'importe':'120.50'})
 convened=create('precios',{'estudio_id':study['id'],'compania_id':company['id'],'importe':'99.99'})
 admin.call('catalogos/precios','POST',{'estudio_id':study['id'],'importe':'22.00'},code=409)
 admin.call('catalogos/precios','POST',{'estudio_id':study['id'],'compania_id':company['id'],'importe':'1.001'},code=422)
 admin.call('catalogos/pacientes','POST',{'nombre':'QA','apellidos':'Fecha','fecha_nacimiento':'2099-01-01','sexo':'no_especificado'},code=422)
 admin.call('catalogos/estudios','POST',{'codigo':'DUP','nombre':'QA','analitos':[analyte['id'],analyte['id']]},code=422)
 sample_row=get('tipos_muestra',sample['id'])
 admin.call('catalogos/tipos_muestra/'+str(sample['id']),'PUT',dict(sample_row,activo=0),code=409)
 study_row=get('estudios',study['id']);check(len(study_row['analitos'])==1,'estudio guarda asociación de analitos')
 update('estudios',study_row,{'area':'Área QA'})
 admin.call('catalogos/estudios/'+str(study['id']),'PUT',dict(study_row,area='Edición vieja',analitos=[analyte['id']]),code=409)
 check(admin.call('catalogos/estudios?q=%25')['registros']==[],'búsqueda trata comodines como texto')
 p_row=get('pacientes',patient['id']);check(p_row['codigo'].startswith('PAC-'),'expediente automático')
 caja.call('catalogos/pacientes',code=403);caja.call('catalogos/precios')
 lab.call('catalogos/estudios');lab.call('catalogos/estudios','POST',{'codigo':'NO','nombre':'No permitido'},code=403)
 # Tariff selection and price snapshots.
 quote=admin.call('solicitudes','POST',{'estado':'cotizacion','estudios':[study['id']],'compania_id':company['id']},code=201)
 qr=admin.call('solicitudes/'+str(quote['id']));check(qr['total']=='99.99','cotización usa precio de convenio')
 check(qr['datos']['laboratorio']['nombre_comercial']=='Laboratorio QA','solicitud conserva identidad al crearse')
 newbrand=admin.call('empresa');admin.form('empresa',dict(newbrand,nombre_comercial='Laboratorio QA renombrado'))
 check(admin.call('solicitudes/'+str(quote['id']))['datos']['laboratorio']['nombre_comercial']=='Laboratorio QA','renombrar laboratorio no cambia identidad histórica de solicitud')
 admin.call('solicitudes/'+str(quote['id']),'PUT',{'estado':'registrada','version':qr['version'],'estudios':[study['id']],'compania_id':company['id']},code=422)
 public=admin.call('solicitudes','POST',{'estado':'cotizacion','estudios':[study['id']]},code=201)
 check(admin.call('solicitudes/'+str(public['id']))['total']=='120.50','tarifa pública disponible')
 admin.call('solicitudes','POST',{'estado':'registrada','paciente_id':patient['id'],'estudios':[second['id']]},code=422)
 current_brand=admin.call('empresa');with_logo=admin.form('empresa',current_brand,('demo-logo.png','image/png',png));uploaded_logos.append(with_logo['logo_url'].split('&v=')[-1])
 order=admin.call('solicitudes','POST',{'estado':'registrada','paciente_id':patient['id'],'medico_id':doctor['id'],'compania_id':company['id'],'estudios':[study['id']]},code=201)
 oid=order['id'];orow=admin.call('solicitudes/'+str(oid))
 update('precios',get('precios',convened['id']),{'importe':'110.00'})
 admin.call('solicitudes/'+str(oid),'PUT',{'estado':'registrada','version':orow['version'],'paciente_id':patient['id'],'medico_id':doctor['id'],'compania_id':company['id'],'estudios':[study['id']],'notas':'Cambio de notas'})
 orow=admin.call('solicitudes/'+str(oid));check(orow['total']=='99.99','editar conserva precio histórico de estudio sin cambios')
 template=lab.call('resultados/plantilla?solicitud_id='+str(oid)+'&estudio_id='+str(study['id']))
 check(len(template['analitos'])==1 and template['contexto']['paciente']['expediente']==p_row['codigo'],'plantilla respeta paciente y estudio de solicitud')
 lab.call('resultados','POST',{'solicitud_id':oid,'estudio_id':study['id'],'valores':[]},code=422)
 lab.call('resultados','POST',{'solicitud_id':oid,'estudio_id':study['id'],'valores':[{'analito_id':analyte['id'],'valor':'12.345'}]},code=422)
 linked=lab.call('resultados','POST',{'contenido':'Observaciones ficticias de QA','solicitud_id':oid,'estudio_id':study['id'],'valores':[{'analito_id':analyte['id'],'valor':''}]},code=201)
 lid=linked['id'];resp.call('resultados/'+str(lid)+'/validar','POST',{'version':1},code=409)
 lab.call('resultados/'+str(lid)+'/informe.pdf?version=1',code=409)
 lab.call('resultados/'+str(lid)+'/capturar','POST',{'version':1,'motivo':'Completar captura','valores':[{'analito_id':analyte['id'],'valor':'12,34','unidad':'UNIDAD FALSA'}]})
 details=lab.call('resultados/'+str(lid));check(details['historial'][0]['analitos'][0]['valor']=='12.34' and details['historial'][0]['analitos'][0]['unidad']=='','servidor valida decimal y no admite metadatos alterados')
 resp.call('resultados/'+str(lid)+'/validar','POST',{'version':2})
 admin.call('resultados/'+str(lid)+'/informe.pdf?version=2',code=403)
 resp.call('resultados/'+str(lid)+'/informe.pdf?version=1',code=409)
 pdf_data=recep.pdf('resultados/'+str(lid)+'/informe.pdf?version=2')
 output=ROOT/'output/pdf';output.mkdir(parents=True,exist_ok=True);pdf_path=output/'informe_demo_validado.pdf';pdf_path.write_bytes(pdf_data)
 pdfcheck=json.loads(subprocess.check_output([PDF_PYTHON,'-c',"from pypdf import PdfReader; import json,sys; r=PdfReader(sys.argv[1]); print(json.dumps({'text':'\\n'.join(p.extract_text() for p in r.pages),'images':sum(len(p.images) for p in r.pages)}))",str(pdf_path)],text=True));text=pdfcheck['text']
 check('Paciente Ficticio' in text and '12.34' in text and 'Analito QA' in text and 'Validado por' in text,'PDF incluye paciente, analito, valor y responsable')
 check(pdfcheck['images']>0,'PDF incluye el logo del laboratorio')
 resp.call('resultados/'+str(lid)+'/corregir','POST',{'version':2,'motivo':'Corrección ficticia','valores':[{'analito_id':analyte['id'],'valor':'-1.25'}]})
 recep.call('resultados/'+str(lid)+'/informe.pdf?version=2',code=409)
 resp.call('resultados/'+str(lid)+'/validar','POST',{'version':3})
 check(lab.call('resultados/'+str(lid))['historial'][1]['analitos'][0]['valor']=='12.34','corrección conserva valor histórico por analito')
 lab.call('resultados','POST',{'solicitud_id':oid,'estudio_id':study['id'],'valores':[{'analito_id':analyte['id'],'valor':'1.00'}]},code=409)
 lab.call('resultados','POST',{'referencia':'Cotización no autorizada','contenido':'Ficticio','solicitud_id':quote['id'],'estudio_id':study['id']},code=409)
 lab.call('resultados','POST',{'referencia':'Estudio ajeno','contenido':'Ficticio','solicitud_id':oid,'estudio_id':second['id']},code=422)
 admin.call('solicitudes/'+str(oid),'PUT',{'estado':'registrada','version':orow['version'],'paciente_id':patient['id'],'estudios':[second['id']]},code=409)
 check(len(admin.call('solicitudes?paciente_id='+str(patient['id'])))==1,'historial de paciente enlaza solicitudes')
 receipt=admin.call('solicitudes/'+str(oid)+'/impresion');check(str(receipt['datos']['estudios'][0]['analitos'][0]['id'])==str(analyte['id']),'orden de trabajo conserva analitos históricos')

 # All result types, precision, references and immutable catalog snapshots.
 qn=create('analitos',{'codigo':'TIPO-N','nombre':'Entero de prueba','tipo_resultado':'numerico','decimales':'0','unidad':'µg/L','referencia_texto':'Referencia de prueba, definida por QA'})
 qq=create('analitos',{'codigo':'TIPO-Q','nombre':'Cualitativo de prueba','tipo_resultado':'cualitativo','decimales':'0'})
 qt=create('analitos',{'codigo':'TIPO-T','nombre':'Texto de prueba','tipo_resultado':'texto','decimales':'0'})
 qs=create('estudios',{'codigo':'TIPOS-QA','nombre':'Estudio de tipos de resultado','analitos':[qn['id'],qq['id'],qt['id']]})
 create('precios',{'estudio_id':qs['id'],'importe':'10.00'})
 qo=admin.call('solicitudes','POST',{'estado':'registrada','paciente_id':patient['id'],'estudios':[qs['id']]},code=201)
 payload={'solicitud_id':qo['id'],'estudio_id':qs['id'],'valores':[{'analito_id':qn['id'],'valor':'7'},{'analito_id':qq['id'],'valor':'Texto cualitativo ficticio'},{'analito_id':qt['id'],'valor':'<b>Texto literal</b> con acentos y µ'}]}
 invalid=dict(payload,valores=[dict(payload['valores'][0],valor='7.1')]+payload['valores'][1:]);lab.call('resultados','POST',invalid,code=422)
 invalid=dict(payload,valores=[payload['valores'][0],payload['valores'][0],payload['valores'][2]]);lab.call('resultados','POST',invalid,code=422)
 update('analitos',get('analitos',qn['id']),{'unidad':'UNIDAD NUEVA','referencia_texto':'REFERENCIA NUEVA'})
 tpl=lab.call('resultados/plantilla?solicitud_id='+str(qo['id'])+'&estudio_id='+str(qs['id']))
 check(tpl['analitos'][0]['unidad']=='µg/L' and tpl['analitos'][0]['referencia']=='Referencia de prueba, definida por QA','plantilla conserva unidad y referencia de solicitud, sin adoptar cambios posteriores')
 qresult=lab.call('resultados','POST',payload,code=201);qid=qresult['id']
 details=lab.call('resultados/'+str(qid));check([a['tipo_resultado'] for a in details['historial'][0]['analitos']]==['numerico','cualitativo','texto'],'captura admite los tres tipos y conserva su orden')
 resp.call('resultados/'+str(qid)+'/validar','POST',{'version':1})
 values_pdf=recep.pdf('resultados/'+str(qid)+'/informe.pdf?version=1');(ROOT/'storage/pruebas/tipos_resultado.pdf').write_bytes(values_pdf)
 rawtext=json.loads(subprocess.check_output([PDF_PYTHON,'-c',"from pypdf import PdfReader; import sys,json; print(json.dumps(PdfReader(sys.argv[1]).pages[0].extract_text()))",str(ROOT/'storage/pruebas/tipos_resultado.pdf')],text=True,encoding='utf-8'))
 check('<b>Texto literal</b>' in rawtext and 'µg/L' in rawtext,'PDF conserva Unicode y escapa el texto que parece HTML')
 unauth=Client();unauth.session();unauth.call('resultados/'+str(qid)+'/informe.pdf?version=1',code=401)

 # Cash uses cents and separates noncash receipts from the physical balance.
 caja.call('caja/ingreso','POST',{'importe':'1.00','concepto':'QA','clave':str(uuid.uuid4())},code=409)
 caja.call('caja/abrir','POST',{'fondo':'100.00'})
 caja.call('caja/abrir','POST',{'fondo':'0.00'},code=409)
 key=str(uuid.uuid4());move={'importe':'20.25','concepto':'Ingreso QA','metodo':'efectivo','clave':key}
 caja.call('caja/ingreso','POST',move);caja.call('caja/ingreso','POST',move)
 caja.call('caja/ingreso','POST',dict(move,importe='21.00'),code=409)
 caja.call('caja/ingreso','POST',{'importe':'50.00','concepto':'Tarjeta QA','metodo':'tarjeta','clave':str(uuid.uuid4())})
 caja.call('caja/egreso','POST',{'importe':'120.26','concepto':'Exceso QA','metodo':'efectivo','clave':str(uuid.uuid4())},code=409)
 caja.call('caja/egreso','POST',{'importe':'0','concepto':'Cero QA','clave':str(uuid.uuid4())},code=422)
 caja.call('caja/egreso','POST',{'importe':'10.10','concepto':'Egreso QA','metodo':'efectivo','clave':str(uuid.uuid4())})
 cash=caja.call('caja')['turno'];check(cash['resumen']['esperado']=='110.15' and cash['resumen']['ingresos_otros']=='50.00','caja calcula efectivo sin duplicar reintentos ni sumar tarjeta')
 caja.call('caja/cerrar','POST',{'version':cash['version']-1,'contado':'110.15'},code=409)
 caja.call('caja/cerrar','POST',{'version':cash['version'],'contado':'100.00'},code=422)
 caja.call('caja/cerrar','POST',{'version':cash['version'],'contado':'100.00','notas':'Diferencia ficticia QA'})
 closed=caja.call('caja')['historial'][0];check(closed['resumen']['diferencia']=='-10.15','corte guarda diferencia firmada')
 caja.call('caja/egreso','POST',{'importe':'1.00','concepto':'Turno cerrado QA','clave':str(uuid.uuid4())},code=409)
 lab.call('caja',code=403)
 # Install reruns preserve records and customized permissions.
 subprocess.run(CMD+[str(ROOT/'scripts/instalar.php')],env=env,check=True,capture_output=True)
 check(get('pacientes',patient['id'])['codigo']==p_row['codigo'],'migración repetible conserva pacientes')


 # Popup selector: server pagination, full-name search, minimal disclosure and creation permissions.
 for index in range(27):
  create('pacientes',{'codigo':f'SEL-{index:03}','nombre':f'ListadoSelector {index:02}','apellidos':'Ejemplo','fecha_nacimiento':'2000-01-01','sexo':'no_especificado','telefono':'Teléfono ficticio'})
 sel1=admin.call('catalogos/pacientes/opciones?q=ListadoSelector&pagina=1');sel2=admin.call('catalogos/pacientes/opciones?q=ListadoSelector&pagina=2')
 check(len(sel1['registros'])==25 and sel1['hay_mas'] and len(sel2['registros'])==2 and not sel2['hay_mas'],'selector pagina registros sin descargar todo el catálogo')
 check(not ({r['id'] for r in sel1['registros']} & {r['id'] for r in sel2['registros']}),'selector no repite registros entre páginas')
 check('Nacimiento' in sel1['registros'][0]['detalles'],'selector muestra detalles autorizados para distinguir pacientes')
 found=admin.call('catalogos/pacientes/opciones?q=ListadoSelector%2001%20Ejemplo')
 check(len(found['registros'])==1 and found['registros'][0]['codigo']=='SEL-001','selector busca por nombre y apellido completos')
 admin.call('catalogos/pacientes/opciones?pagina=0',code=422)
 limited={k:False for k in catalog};limited['ver_ordenes']=True;limited['crear_ordenes']=True
 limited_role=admin.call('roles','POST',{'nombre':'Operador de selección QA','permisos':limited})
 admin.call('usuarios','POST',{'nombre':'Selector de prueba','nombre_usuario':'selectorqa','rol_id':limited_role['id'],'contrasena':'TemporalQA-123','activo':1},code=201)
 limited_client=Client();limited_client.login('selectorqa','TemporalQA-123');limited_client.call('contrasena','POST',{'actual':'TemporalQA-123','nueva':'PersonalQA-456'})
 minimal=limited_client.call('catalogos/pacientes/opciones?q=ListadoSelector')
 check(minimal['registros'] and all(r['detalles']=={} for r in minimal['registros']),'selector restringido no expone nacimiento ni contacto')
 limited_client.call('catalogos/pacientes',code=403)
 limited_client.call('catalogos/pacientes','POST',{'nombre':'No debe crearse','apellidos':'QA','sexo':'no_especificado'},code=403)

 # Runtime revocation applies without logging in again.
 perms=dict(roles['Laboratorista']['permisos']);perms['capturar_resultados']=False
 admin.call('roles/'+str(roles['Laboratorista']['id']),'PUT',{'nombre':'Laboratorista','permisos':perms})
 lab.call('resultados','POST',{'referencia':'QA-002','contenido':'No debe guardarse'},code=403)
 # Disabling an account invalidates its existing session.
 admin.call('usuarios/'+str(labu['id']),'PUT',{'nombre':'labqa','nombre_usuario':'labqa','rol_id':roles['Laboratorista']['id'],'activo':0})
 lab.call('resultados',code=401)
 # Resetting password invalidates all sessions and forces a new personal password.
 admin.call('usuarios/'+str(recepu['id']),'PUT',{'nombre':'recepqa','nombre_usuario':'recepqa','rol_id':roles['Entrega QA']['id'],'activo':1,'contrasena':'RestablecidaQA-789'})
 recep.call('resultados',code=401)
 recep.login('recepqa','RestablecidaQA-789');check(bool(recep.session()['usuario']['cambio_obligatorio']),'restablecer contraseña fuerza cambio')
 adminu=admin.session()['usuario']
 admin.call('usuarios/'+str(adminu['id']),'PUT',{'nombre':'Admin QA','nombre_usuario':'adminqa','rol_id':roles['Administrador']['id'],'activo':0},code=409)
 admin.call('roles/'+str(roles['Administrador']['id']),'PUT',{'nombre':'Administrador','permisos':roles['Administrador']['permisos']},code=409)
 invalid=dict(roles['Caja']['permisos']);invalid['todo']=True
 admin.call('roles','POST',{'nombre':'Malicioso','permisos':invalid},code=422)
 logs=admin.call('bitacora');logs+=admin.call('bitacora?antes='+str(logs[-1]['id']));logs+=admin.call('bitacora?antes='+str(logs[-1]['id']));kinds={r['tipo'] for r in logs}
 check({'resultado_corregir','resultado_validar','resultado_entregar','resultado_consultado','usuario_editado','permisos_editados'}<=kinds,'bitácora de operaciones y consultas')
 check('contrasena_hash' not in json.dumps(logs) and 'RestablecidaQA-789' not in json.dumps(logs),'bitácora sin contraseñas ni hashes')
 # Failed logins are persisted and throttled across sessions.
 for i in range(5):
  bad=Client();bad.session();bad.call('login','POST',{'nombre_usuario':'inexistente','contrasena':'IncorrectaQA'},code=401)
 bad=Client();bad.session();bad.call('login','POST',{'nombre_usuario':'inexistente','contrasena':'IncorrectaQA'},code=429)
 print(f'PASS: {checks} verificaciones HTTP; base de prueba aislada.',flush=True)

 if os.environ.get('LAB_UI_PREVIEW')=='1':
  (ROOT/'storage/pruebas/ui_preview.stop').unlink(missing_ok=True)
  # Only this random test database is retained while the preview process runs.
  preview_role=admin.call('roles')['roles'];preview_role=next(r for r in preview_role if r['nombre']=='Recepción')
  admin.call('usuarios','POST',{'nombre':'Recepción de demostración','nombre_usuario':'previewqa','contrasena':'VistaQA-123','rol_id':preview_role['id'],'activo':1},code=201)
  subprocess.run(CMD+['-r',"require 'app/Config/Database.php'; (new Database())->connect()->exec('DELETE FROM intentos_acceso');"],cwd=ROOT,env=env,check=True)
  viewer=Client();viewer.login('previewqa','VistaQA-123');viewer.call('contrasena','POST',{'actual':'VistaQA-123','nueva':'VistaQA-456'})
  aq1=create('analitos',{'codigo':'UI-NUM','nombre':'Parámetro numérico de prueba','tipo_resultado':'numerico','decimales':'2','unidad':'Unidad QA','referencia_texto':'Referencia de demostración'})
  aq2=create('analitos',{'codigo':'UI-CUAL','nombre':'Parámetro cualitativo de prueba','tipo_resultado':'cualitativo','decimales':'0','referencia_texto':'Texto definido por el laboratorio'})
  aq3=create('analitos',{'codigo':'UI-TEXT','nombre':'Observación de prueba','tipo_resultado':'texto','decimales':'0'})
  ui_study=create('estudios',{'codigo':'UI-EST','nombre':'Panel de captura QA','tipo_muestra_id':sample['id'],'metodo_id':method['id'],'analitos':[aq1['id'],aq2['id'],aq3['id']]})
  create('precios',{'estudio_id':ui_study['id'],'importe':'100.00'})
  ui_order=admin.call('solicitudes','POST',{'estado':'registrada','paciente_id':patient['id'],'estudios':[ui_study['id']]},code=201)
  info={'url':f'http://127.0.0.1:{port}/Laboratorio/public/','database':DB,'solicitud':ui_order['id']}
  (ROOT/'storage/pruebas/ui_preview.json').write_text(json.dumps(info),encoding='utf-8')
  print('UI_PREVIEW '+json.dumps(info),flush=True)
  # The caller stops the preview through this file; timeout is a fallback.
  deadline=time.time()+1800
  while time.time()<deadline and not (ROOT/'storage/pruebas/ui_preview.stop').exists():time.sleep(.5)

finally:
 if server: server.terminate();server.wait(timeout=10);log.close()
 for logo_name in uploaded_logos:
  if re.fullmatch(r'[a-f0-9]{32}\.png',logo_name):
   logo_path=ROOT/'storage/uploads/logos'/logo_name
   if logo_path.is_file():logo_path.unlink()
 # The random database created by this test is the only cleanup target.
 if DB.startswith('laboratorio_test_'):
  script="require 'app/Config/Database.php'; (new Database())->connect(true)->exec('DROP DATABASE `"+DB+"`');"
  subprocess.run(CMD+['-r',script],cwd=ROOT,env=env,check=True)
