"use strict";
const $=s=>document.querySelector(s), API='/Laboratorio/public/index.php?api=';
let csrf='', user=null, setup=false, roles=[], catalog={}, current='', definitions={};
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const can=k=>!!user?.permisos?.[k];
function status(message,error=false){$('#status').textContent=message;$('#status').classList.toggle('error',error);}
async function api(path,method='GET',data,retried=false){
 const [route,search='']=path.split('?');
 const res=await fetch(API+encodeURIComponent(route)+(search?'&'+search:''),{method,credentials:'same-origin',headers:method==='GET'?{}:{'Content-Type':'application/json','X-CSRF-Token':csrf},body:data?JSON.stringify(data):undefined});
 let body;try{body=await res.json();}catch{throw Error('El servidor no respondió correctamente. Reintenta.');}
 if(!body.success){
  if(!retried&&method!=='GET'&&res.status===403&&body.message==='La sesión del formulario cambió. Recarga la página e inténtalo de nuevo.'){
   const state=await api('sesion');
   if(path==='login'||(path==='configurar'&&state.configuracion_inicial)||(user&&state.usuario&&String(user.id)===String(state.usuario.id))){csrf=state.csrf;return api(path,method,data,true);}
  }
  if(res.status===401){user=null;await refresh();}throw Error(body.message);
 }
 if(body.data?.csrf)csrf=body.data.csrf;return body.data;
}
function view(id){for(const name of ['access','workspace','password'])$('#'+name).hidden=name!==id;}
function table(head,rows){return `<div class="table-wrap"><table><thead><tr>${head.map(h=>`<th scope="col">${esc(h)}</th>`).join('')}</tr></thead><tbody>${rows.join('')}</tbody></table></div>`;}
function title(t,help){document.title=t+' · '+(brandConfig?.nombre_comercial||'Laboratorio clínico');$('#page-title').textContent=t;$('#page-help').textContent=help;}
function toolbar(label){return `<div class="toolbar"><input id="search" type="search" aria-label="Buscar en la lista" placeholder="Buscar en la lista"><button id="new" class="primary">${esc(label)}</button></div>`;}
function filterRows(){const input=$('#search');if(input)input.addEventListener('input',()=>{for(const row of document.querySelectorAll('tbody tr'))row.hidden=!row.textContent.toLocaleLowerCase().includes(input.value.toLocaleLowerCase());});}
async function refresh(){
 const state=await api('sesion');const brand=await api('empresa');applyBrand(brand);csrf=state.csrf;user=state.usuario;setup=state.configuracion_inicial;
 $('#session').hidden=!user;$('#navigation').hidden=!user||!!user.cambio_obligatorio;syncNavigation(!$('#navigation').hidden);
 if(!user){document.title='Acceso · '+brandConfig.nombre_comercial;view('access');$('#login-form').hidden=setup&&!state.configuracion_local;$('#setup-name').hidden=!setup;$('#setup-name input').required=setup;$('#setup-help').hidden=!setup;$('#login-title').textContent=setup?'Crear administrador':'Iniciar sesión';$('#login-submit').textContent=setup?'Crear cuenta':'Entrar';$('#show-login-password').checked=false;$('#login-form input[name=contrasena]').type='password';$('#login-form input[name=contrasena]').autocomplete=setup?'new-password':'current-password';status(setup&&!state.configuracion_local?'Crea la primera cuenta desde el navegador del servidor.':'');return;}
 $('#header-welcome').textContent='Bienvenido, '+user.nombre;
 $('#identity').textContent=user.cambio_obligatorio?user.nombre+' · '+user.rol_nombre:user.rol_nombre;
 if(user.cambio_obligatorio){passwordView(true);return;}
 definitions=await api('catalogos');
 const items=[['inicio','Inicio',true],['usuarios','Usuarios',can('administrar_accesos')],['roles','Roles y permisos',can('administrar_accesos')],['resultados','Resultados',can('ver_resultados')],['bitacora','Bitácora',can('ver_bitacora')],['identidad','Identidad del laboratorio',can('administrar_accesos')]];
 const reception=[['nuevo','Nuevo ingreso','crear_ordenes'],['captura','Captura de resultados','capturar_resultados'],['entrega','Entrega de resultados','entregar_resultados'],['cotizaciones','Cotización','cotizar'],['ordenes','Orden de trabajo','imprimir_solicitudes'],['editar','Editar solicitud','editar_solicitudes'],['comprobantes','Imprimir comprobante','imprimir_solicitudes'],['historial','Historial del paciente','ver_pacientes']];
 const cash=[];
 if(can('ver_cobros'))cash.push(['cobros','Cobros de solicitudes'],['anulaciones','Cancelaciones y devoluciones']);
 if(can('operar_caja'))cash.push(['caja:ingresos','Ingresos'],['caja:egresos','Egresos'],['caja:corte','Corte de caja']);
 renderNavigation([
  {key:'recepcion',label:'Recepción',icon:'clipboard',items:reception.filter(i=>can(i[2])&&can('ver_ordenes')).map(i=>['recepcion:'+i[0],i[1]])},
  {key:'caja',label:'Caja',icon:'wallet',items:cash},
  {key:'sistema',label:'Sistema',icon:'settings',items:items.filter(i=>i[2]).map(i=>[i[0],i[1]])},
  {key:'catalogos',label:'Catálogos',icon:'layers',items:Object.entries(definitions).map(([key,d])=>['catalogo:'+key,d.title])}
 ]);
 await navigate('inicio');status('');
}
async function navigate(page){
 current=page;closeNavigationForPage();view('workspace');for(const b of document.querySelectorAll('[data-nav]')){if(b.dataset.nav===page){b.setAttribute('aria-current','page');const group=b.closest('.nav-group');if(group)group.open=true;}else b.removeAttribute('aria-current');}
 $('#content').replaceChildren();status('Cargando…');
 if(page==='inicio'){
  title('Bienvenido, '+user.nombre,'Tu sesión tiene el rol '+user.rol_nombre+'.');
  const labels={solicitar_anulaciones:'Solicitar cancelaciones y devoluciones',autorizar_anulaciones:'Autorizar cancelaciones y devoluciones',devolver_pagos:'Registrar devoluciones autorizadas',ver_cobros:'Consultar pagos y saldos de solicitudes',cobrar:'Registrar anticipos y abonos',operar_caja:'Operar caja y realizar cortes',administrar_accesos:'Administrar usuarios y roles',ver_bitacora:'Consultar bitácora',ver_resultados:'Consultar resultados',capturar_resultados:'Capturar resultados',validar_resultados:'Validar resultados',entregar_resultados:'Entregar resultados',corregir_resultados:'Corregir resultados'};
  $('#content').innerHTML='<h2>Tus accesos disponibles</h2><ul>'+Object.entries(labels).filter(([k])=>can(k)).map(([,v])=>'<li>'+esc(v)+'</li>').join('')+'</ul><p>Selecciona una sección del menú para trabajar con recepción, caja, catálogos o accesos del equipo.</p>';
 }else if(page.startsWith('recepcion:'))await receptionPage(page.split(':')[1]);else if(page==='cobros')await paymentsPage();else if(page==='anulaciones')await actionsPage();else if(page.startsWith('caja:'))await cashPage(page.split(':')[1]);else if(page.startsWith('catalogo:'))await catalogPage(page.split(':')[1]);else if(page==='usuarios')await usersPage();else if(page==='roles')await rolesPage();else if(page==='bitacora')await auditPage();else if(page==='resultados')await resultsPage();else if(page==='identidad')await identityPage();
 status('');
}
async function loadRoles(){const d=await api('roles');roles=d.roles;catalog=d.catalogo;}
async function usersPage(){
 title('Usuarios','Crea cuentas personales, asigna un rol y desactiva accesos sin borrar su historial.');
 await loadRoles();const rows=await api('usuarios');
 $('#content').innerHTML=toolbar('Crear usuario')+table(['Nombre / usuario','Rol','Estado','Contraseña','Acción'],rows.map(u=>`<tr><td>${esc(u.nombre)}<br><small>${esc(u.nombre_usuario)}</small></td><td>${esc(u.rol_nombre)}</td><td>${Number(u.activo)?'Activo':'Inactivo'}</td><td>${Number(u.cambio_obligatorio)?'Cambio obligatorio':'Personal'}</td><td><button data-edit="${u.id}">Editar</button></td></tr>`))+'<div id="editor"></div>';
 $('#new').onclick=()=>userEditor();for(const b of document.querySelectorAll('[data-edit]'))b.onclick=()=>userEditor(rows.find(u=>String(u.id)===b.dataset.edit));filterRows();
}
function userEditor(u){
 const edit=!!u;
 $('#editor').innerHTML=`<form id="user-form" class="editor"><h2>${edit?'Editar usuario':'Crear usuario'}</h2><label>Nombre completo<input name="nombre" required maxlength="150" value="${esc(u?.nombre)}" autocomplete="name"></label><label>Usuario<input name="nombre_usuario" required pattern="[a-z0-9._-]{3,60}" maxlength="60" value="${esc(u?.nombre_usuario)}" autocomplete="off"></label><label>Rol<select name="rol_id">${roles.map(r=>`<option value="${r.id}" ${String(r.id)===String(u?.rol_id)?'selected':''}>${esc(r.nombre)}</option>`).join('')}</select></label><label>${edit?'Nueva contraseña temporal (opcional)':'Contraseña temporal'}<input name="contrasena" type="password" minlength="8" maxlength="72" ${edit?'':'required'} autocomplete="new-password"></label><p class="hint">${edit?'Deja la contraseña vacía para conservarla. Si la restableces, se cerrarán sus sesiones. ':''}La persona deberá cambiar la contraseña temporal antes de operar.</p><label>Estado<select name="activo"><option value="1">Activo</option><option value="0" ${u&&!Number(u.activo)?'selected':''}>Inactivo</option></select></label><button class="primary">Guardar usuario</button><button type="button" id="cancel-edit">Cancelar</button></form>`;
 $('#cancel-edit').onclick=()=>$('#editor').replaceChildren();$('#user-form').onsubmit=e=>{e.preventDefault();run(async()=>{const d=Object.fromEntries(new FormData(e.target));await api('usuarios'+(u?'/'+u.id:''),u?'PUT':'POST',d);if(u&&String(u.id)===String(user.id)&&d.contrasena){await refresh();return;}await usersPage();status('Usuario guardado.');},e.target);};$('#editor').scrollIntoView({behavior:'smooth',block:'start'});$('#user-form input').focus();
}
const clinical=new Set(['ver_resultados','capturar_resultados','validar_resultados','entregar_resultados','corregir_resultados']);
async function rolesPage(){
 title('Roles y permisos','Cada usuario tiene un rol. Los cambios de permisos se aplican también a sus sesiones abiertas.');await loadRoles();
 $('#content').innerHTML=toolbar('Crear rol')+table(['Rol','Permisos habilitados','Acción'],roles.map(r=>`<tr><td>${esc(r.nombre)} ${Number(r.protegido)?'<span class="badge">Protegido</span>':''}</td><td>${Object.values(r.permisos).filter(Boolean).length}</td><td><button data-role="${r.id}">${Number(r.protegido)?'Consultar':'Editar permisos'}</button></td></tr>`))+'<div id="editor"></div>';
 $('#new').onclick=()=>roleEditor();for(const b of document.querySelectorAll('[data-role]'))b.onclick=()=>roleEditor(roles.find(r=>String(r.id)===b.dataset.role));filterRows();
}
function roleEditor(r){
 const locked=!!Number(r?.protegido);
 $('#editor').innerHTML=`<form id="role-form" class="editor"><h2>${r?'Permisos del rol':'Crear rol'}</h2><fieldset ${locked?'disabled':''}><label>Nombre del rol<input name="nombre" maxlength="60" required value="${esc(r?.nombre)}"></label><p class="hint">Los permisos clínicos son explícitos. Administrar cuentas no autoriza consultar ni validar resultados.</p><div class="checks">${Object.entries(catalog).map(([k,v])=>`<label><input type="checkbox" name="${k}" ${r?.permisos[k]?'checked':''}><span>${esc(v)}${['recibir_muestras'].includes(k)?' <small>(módulo pendiente)</small>':''}</span></label>`).join('')}</div></fieldset>${locked?'':'<button class="primary">Guardar rol</button>'}<button id="cancel-edit" type="button">Cerrar</button></form>`;
 $('#cancel-edit').onclick=()=>$('#editor').replaceChildren();$('#role-form').onsubmit=e=>{e.preventDefault();run(async()=>{const data=new FormData(e.target),permisos={};for(const k of Object.keys(catalog))permisos[k]=data.has(k);await api('roles'+(r?'/'+r.id:''),r?'PUT':'POST',{nombre:data.get('nombre'),permisos});await refresh();await navigate('roles');status('Rol guardado.');},e.target);};$('#editor').scrollIntoView({behavior:'smooth',block:'start'});
}
async function auditPage(before='0'){
 title('Bitácora de actividad','Accesos, cambios de cuentas y permisos, consultas y operaciones sobre resultados.');const rows=await api('bitacora?antes='+encodeURIComponent(before));
 $('#content').innerHTML=table(['Fecha','Persona','Acción','Referencia','Detalle'],rows.map(r=>`<tr><td>${esc(r.creado_en)}</td><td>${esc(r.usuario_nombre)}</td><td>${esc(r.tipo)}</td><td>${esc(r.referencia)}</td><td><details><summary>${esc(r.resumen)}</summary><pre>${esc(JSON.stringify(JSON.parse(r.detalle),null,2))}</pre></details></td></tr>`))+`<div class="toolbar"><button id="audit-first">Más recientes</button>${rows.length===50?'<button id="audit-next">Ver anteriores</button>':''}</div>`;
 $('#audit-first').onclick=()=>run(()=>auditPage());if($('#audit-next'))$('#audit-next').onclick=()=>run(()=>auditPage(String(rows.at(-1).id)));
}
function passwordView(required=false){view('password');$('#password-form').reset();$('#password-help').textContent=required?'Tu contraseña es temporal. Cámbiala antes de acceder al sistema.':'Las demás sesiones de tu cuenta se cerrarán al cambiarla.';$('#password-back').hidden=required;status('');}
async function run(fn,form){if(form)for(const b of form.querySelectorAll('button'))b.disabled=true;try{await fn();}catch(e){status(e.message,true);}finally{if(form)for(const b of form.querySelectorAll('button'))b.disabled=false;}}
$('#login-form').onsubmit=e=>{e.preventDefault();run(async()=>{const d=Object.fromEntries(new FormData(e.target));await api(setup?'configurar':'login','POST',d);e.target.reset();if(setup){await refresh();status('Administrador creado. Inicia sesión.');}else await refresh();},e.target);};
$('#password-form').onsubmit=e=>{e.preventDefault();run(async()=>{const d=Object.fromEntries(new FormData(e.target));if(d.nueva!==d.confirmacion)throw Error('Las contraseñas nuevas no coinciden.');await api('contrasena','POST',d);e.target.reset();await refresh();status('Contraseña actualizada.');},e.target);};
$('#logout').onclick=()=>run(async()=>{await api('logout','POST',{});await refresh();});$('#password-nav').onclick=()=>passwordView(!!user?.cambio_obligatorio);$('#password-back').onclick=()=>run(()=>navigate(current||'inicio'));
window.addEventListener('DOMContentLoaded',()=>run(refresh));

$('#show-login-password').onchange=e=>{$('#login-form input[name=contrasena]').type=e.target.checked?'text':'password';};
