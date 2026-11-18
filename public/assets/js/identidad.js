"use strict";
let brandConfig=null, logoPreviewUrl=null;
const mobileMenu=window.matchMedia('(max-width:900px)');
function setNavigation(open,restoreFocus=false){
 const sidebar=$('#app-sidebar'),enabled=!sidebar.hidden;
 open=enabled&&open;document.body.classList.toggle('menu-open',open);
 $('#menu-toggle').title=open?'Cerrar menú':'Abrir menú';$('#menu-toggle').setAttribute('aria-expanded',String(open));$('#menu-toggle').setAttribute('aria-label',open?'Cerrar menú':'Abrir menú');
 sidebar.inert=!open;$('#menu-backdrop').hidden=!open||!mobileMenu.matches;
 const modal=open&&mobileMenu.matches;$('#main-content').inert=modal;$('#session').inert=modal;const brand=$('.brand');brand.inert=modal&&!sidebar.contains(brand);
 if(modal){sidebar.setAttribute('role','dialog');sidebar.setAttribute('aria-modal','true');}else{sidebar.removeAttribute('role');sidebar.removeAttribute('aria-modal');}
 document.body.classList.toggle('menu-modal',modal);
 if(!mobileMenu.matches&&enabled){try{localStorage.setItem('lab_menu_open',String(open));}catch{}}
 if(restoreFocus)$('#menu-toggle').focus();
}
function syncNavigation(enabled){
 const brand=$('.brand'),destination=enabled?$('#sidebar-brand'):$('#header-brand');
 if(brand.parentElement!==destination)destination.append(brand);
 $('#header-welcome').hidden=!enabled;
 document.body.classList.toggle('has-navigation',enabled);$('#menu-toggle').hidden=!enabled;$('#app-sidebar').hidden=!enabled;
 let desktopOpen=true;try{desktopOpen=localStorage.getItem('lab_menu_open')!=='false';}catch{}
 setNavigation(enabled&&!mobileMenu.matches&&desktopOpen);
}
function closeNavigationForPage(){if(mobileMenu.matches)setNavigation(false);}
$('#menu-toggle').onclick=()=>{const opening=!document.body.classList.contains('menu-open');setNavigation(opening);if(opening&&mobileMenu.matches)$('#menu-close').focus();};
$('#menu-close').onclick=()=>setNavigation(false,true);$('#menu-backdrop').onclick=()=>setNavigation(false,true);
mobileMenu.addEventListener('change',()=>syncNavigation(!!user&&!user.cambio_obligatorio));
new ResizeObserver(entries=>{document.documentElement.style.setProperty('--header-height',Math.ceil(entries[0].target.getBoundingClientRect().height)+'px');}).observe($('#app-header'));
document.addEventListener('keydown',e=>{
 if(document.querySelector('dialog[open]'))return;
 if(!document.body.classList.contains('menu-open'))return;
 if(e.key==='Escape'){e.preventDefault();setNavigation(false,true);return;}
 if(e.key==='Tab'&&mobileMenu.matches){const list=[...$('#app-sidebar').querySelectorAll('button,summary,a[href]')].filter(b=>!b.disabled&&b.getClientRects().length&&(b.matches('summary')||b.closest('.nav-group')?.open!==false));const first=list[0],last=list.at(-1);if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus();}else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus();}}
});
function applyBrand(config){
 brandConfig=config;$('#brand-name').textContent=config.nombre_comercial;$('#brand-footer').textContent=config.nombre_comercial+' · Administración del laboratorio';
 $('#brand-logo-button').hidden=!config.logo_url;$('#brand-logo').hidden=!config.logo_url;if(config.logo_url)$('#brand-logo').src=config.logo_url;else $('#brand-logo').removeAttribute('src');
 document.title=($('#page-title').textContent||'Acceso')+' · '+config.nombre_comercial;
}
const logoDialog=$('#brand-logo-dialog');
$('#brand-logo-button').onclick=()=>{
 if(!brandConfig?.logo_url)return;
 $('#brand-logo-dialog-title').textContent=brandConfig.nombre_comercial+' · Logo';
 $('#brand-logo-fullsize').src=brandConfig.logo_url;
 $('#brand-logo-fullsize').alt='Logo de '+brandConfig.nombre_comercial;
 document.body.classList.add('logo-preview-open');logoDialog.showModal();
};
$('#brand-logo-close').onclick=()=>logoDialog.close();
logoDialog.addEventListener('close',()=>document.body.classList.remove('logo-preview-open'));
logoDialog.addEventListener('click',event=>{if(event.target!==logoDialog)return;const rect=logoDialog.getBoundingClientRect();if(event.clientX<rect.left||event.clientX>rect.right||event.clientY<rect.top||event.clientY>rect.bottom)logoDialog.close();});
function printBrand(config){const c=config||brandConfig;return (c.logo_url?`<img class="print-logo" src="${esc(c.logo_url)}" alt="">`:'')+`<h1>${esc(c.nombre_comercial)}</h1>`+
 `<p class="print-contact">${[c.razon_social,c.rfc?'RFC: '+c.rfc:'',c.direccion,c.codigo_postal?'C.P. '+c.codigo_postal:'',c.telefono,c.correo,c.sitio_web].filter(Boolean).map(esc).join('<br>')}</p>`+(c.encabezado_ticket?'<p>'+esc(c.encabezado_ticket)+'</p>':'');}
function printFooter(config){const c=config||brandConfig;return c.pie_ticket?'<p class="print-footer">'+esc(c.pie_ticket)+'</p>':'';}
function textContrast(color){const rgb=color.slice(1).match(/../g).map(v=>parseInt(v,16)/255).map(v=>v<=.04045?v/12.92:((v+.055)/1.055)**2.4),l=.2126*rgb[0]+.7152*rgb[1]+.0722*rgb[2];return 1.05/(l+.05)>=(l+.05)/.05?'#FFFFFF':'#000000';}
async function identityPage(){
 title('Identidad del laboratorio','Personaliza el nombre, logo y colores. Los datos de contacto se usan en los nuevos comprobantes y órdenes de trabajo.');
 const c=await api('empresa');if(logoPreviewUrl){URL.revokeObjectURL(logoPreviewUrl);logoPreviewUrl=null;}
 const fields=[['nombre_comercial','Nombre del laboratorio',150,'text'],['razon_social','Razón social',200,'text'],['rfc','RFC',13,'text'],['telefono','Teléfono',30,'tel'],['correo','Correo electrónico',150,'email'],['sitio_web','Sitio web',200,'url'],['codigo_postal','Código postal',5,'text'],['direccion','Dirección',300,'text'],['encabezado_ticket','Mensaje de encabezado',300,'textarea'],['pie_ticket','Mensaje al final',300,'textarea']];
 const colors=[['color_primario','Color principal','Botones y sección seleccionada'],['color_secundario','Color secundario','Encabezado y barra lateral'],['color_acento','Color de acento','Detalles de identidad']];
 $('#content').innerHTML=`<div class="identity-layout"><form id="identity-form" class="editor identity-form" enctype="multipart/form-data"><h2>Datos e imagen</h2><div class="form-grid">${fields.map(([k,label,max,type])=>`<div class="field ${type==='textarea'?'wide':''}"><label for="brand-${k}">${label}${k==='nombre_comercial'?' *':''}</label>${type==='textarea'?`<textarea id="brand-${k}" name="${k}" maxlength="${max}">${esc(c[k])}</textarea>`:`<input id="brand-${k}" name="${k}" type="${type}" maxlength="${max}" value="${esc(c[k])}" ${k==='nombre_comercial'?'required':''}>`}</div>`).join('')}</div><h3>Logotipo</h3><label for="logo-file">Seleccionar imagen</label><input id="logo-file" name="logo" type="file" accept="image/png,image/jpeg,image/webp"><p class="hint">PNG, JPG o WebP; hasta 2 MB y 4096 × 4096 píxeles. Puedes usar una imagen con fondo transparente.</p><label class="show-password"><input id="remove-logo" name="quitar_logo" type="checkbox" value="1"> Quitar el logo actual</label><h3>Colores</h3>${colors.map(([k,label,help])=>`<div class="color-row"><label for="${k}">${label}<small>${help}</small></label><input type="color" id="${k}" name="${k}" value="${esc(c[k])}"><input data-color-text="${k}" type="text" aria-label="${label} hexadecimal" pattern="#[0-9A-Fa-f]{6}" maxlength="7" value="${esc(c[k])}"></div>`).join('')}<p class="hint">El texto de botones y menú se adapta a los colores para mantener su legibilidad.</p><div class="form-actions"><button class="primary">Guardar identidad</button><button type="button" id="identity-reset">Descartar cambios</button></div><p id="identity-message" role="status" aria-live="polite"></p></form><aside class="brand-preview" aria-label="Vista previa de identidad"><h2>Vista previa</h2><p class="hint">Los cambios se aplican al guardar.</p><div id="identity-preview"><div class="preview-header"><img id="preview-logo" alt="" hidden><strong id="preview-name"></strong></div><div class="preview-body"><span class="preview-selected">Estudios</span><h3>Comprobante de solicitud</h3><p id="preview-contact"></p><p id="preview-heading"></p><button class="preview-action" type="button" disabled>Acción principal</button><p id="preview-footer"></p></div></div></aside></div>`;
 const form=$('#identity-form'),preview=$('#identity-preview');
 const update=()=>{
  $('#preview-name').textContent=form.elements.nombre_comercial.value||'Nombre del laboratorio';$('#preview-contact').textContent=[form.elements.direccion.value,form.elements.telefono.value,form.elements.correo.value].filter(Boolean).join(' · ');$('#preview-heading').textContent=form.elements.encabezado_ticket.value;$('#preview-footer').textContent=form.elements.pie_ticket.value;
  for(const [k] of colors){const value=form.elements[k].value;preview.style.setProperty('--preview-'+k,value);preview.style.setProperty('--preview-on-'+k,textContrast(value));}
  const logo=form.elements.quitar_logo.checked?null:logoPreviewUrl||c.logo_url;$('#preview-logo').hidden=!logo;if(logo)$('#preview-logo').src=logo;
 };
 for(const [k] of colors){form.elements[k].oninput=()=>{form.querySelector(`[data-color-text="${k}"]`).value=form.elements[k].value;update();};form.querySelector(`[data-color-text="${k}"]`).oninput=e=>{if(/^#[0-9a-f]{6}$/i.test(e.target.value)){form.elements[k].value=e.target.value;update();}};}
 form.addEventListener('input',update);
 $('#logo-file').onchange=e=>{if(logoPreviewUrl){URL.revokeObjectURL(logoPreviewUrl);logoPreviewUrl=null;}const file=e.target.files[0];if(file){if(file.size>2097152||!['image/png','image/jpeg','image/webp'].includes(file.type)){e.target.value='';$('#identity-message').textContent='Selecciona un logo PNG, JPG o WebP de hasta 2 MB.';return;}form.elements.quitar_logo.checked=false;logoPreviewUrl=URL.createObjectURL(file);}update();};
 $('#remove-logo').onchange=()=>{if(form.elements.quitar_logo.checked){$('#logo-file').value='';if(logoPreviewUrl){URL.revokeObjectURL(logoPreviewUrl);logoPreviewUrl=null;}}update();};
 $('#identity-reset').onclick=()=>run(identityPage);
 form.onsubmit=e=>{e.preventDefault();run(async()=>{const fd=new FormData(form);fd.set('version',String(c.version));for(const [k] of colors)fd.set(k,form.querySelector(`[data-color-text="${k}"]`).value);const response=await fetch(API+'empresa',{method:'POST',credentials:'same-origin',headers:{'X-CSRF-Token':csrf},body:fd});const body=await response.json();if(!body.success)throw Error(body.message);applyBrand(body.data);$('#brand-theme').href=API+'empresa/tema.css&v='+body.data.version;await identityPage();status('Identidad del laboratorio guardada.');},form);};update();
}

async function printDocument(){await Promise.all([...document.querySelectorAll('#print-area img')].map(img=>img.decode?img.decode().catch(()=>{}):Promise.resolve()));window.print();}
