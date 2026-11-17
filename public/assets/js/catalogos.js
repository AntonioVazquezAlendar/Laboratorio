"use strict";
let catalogState={key:'',page:1,q:'',estado:'todos'};
async function catalogPage(key,page=1,q='',estado='todos'){
 const d=definitions[key];if(!d)throw Error('No tienes acceso a este catálogo.');
 catalogState={key,page,q,estado};title(d.title,'Consulta, registra y actualiza '+d.title.toLocaleLowerCase()+'. Los registros inactivos conservan su historial.');
 const list=await api(`catalogos/${key}?pagina=${page}&q=${encodeURIComponent(q)}&estado=${estado}`);
 const rows=list.registros;
 $('#content').innerHTML=`<form id="catalog-filter" class="toolbar"><div class="filter-fields"><input name="q" type="search" aria-label="Buscar en ${esc(d.title)}" placeholder="Buscar por código o nombre" value="${esc(q)}"><select name="estado" aria-label="Filtrar por estado"><option value="todos">Todos los estados</option><option value="1" ${estado==='1'?'selected':''}>Activos</option><option value="0" ${estado==='0'?'selected':''}>Inactivos</option></select><button>Buscar</button></div>${d.editable?`<button type="button" id="catalog-new" class="primary">Crear ${esc(d.singular)}</button>`:''}</form>`+
 (rows.length?table([...Object.values(d.columns),'Estado','Acción'],rows.map(r=>'<tr>'+Object.keys(d.columns).map(k=>`<td>${esc(k==='importe'?money(r[k]):r[k]||'—')}</td>`).join('')+`<td><span class="badge ${Number(r.activo)?'':'inactive'}">${Number(r.activo)?'Activo':'Inactivo'}</span></td><td><button data-catalog-id="${r.id}">${d.editable?'Ver / editar':'Consultar'}</button>${key==='pacientes'&&can('ver_ordenes')?` <button data-history="${r.id}">Historial</button>`:''}</td></tr>`)):'<p class="empty">No hay registros con estos filtros. '+(d.editable?'Puedes crear el primero.':'')+'</p>')+
 `<div class="toolbar"><span>Página ${page} · ${rows.length} registros</span><div><button id="cat-prev" ${page===1?'disabled':''}>Anterior</button> <button id="cat-next" ${!list.hay_mas?'disabled':''}>Siguiente</button></div></div><div id="catalog-editor"></div>`;
 $('#catalog-filter').onsubmit=e=>{e.preventDefault();const f=new FormData(e.target);run(()=>catalogPage(key,1,f.get('q'),f.get('estado')));};
 if($('#catalog-new'))$('#catalog-new').onclick=()=>run(()=>catalogEditor(key));
 for(const b of document.querySelectorAll('[data-catalog-id]'))b.onclick=()=>run(async()=>catalogEditor(key,await api(`catalogos/${key}/${b.dataset.catalogId}`)));
 for(const b of document.querySelectorAll('[data-history]'))b.onclick=()=>run(()=>patientHistory(b.dataset.history));
 $('#cat-prev').onclick=()=>run(()=>catalogPage(key,page-1,q,estado));$('#cat-next').onclick=()=>run(()=>catalogPage(key,page+1,q,estado));
}
const money=v=>new Intl.NumberFormat('es-MX',{style:'currency',currency:'MXN'}).format(Number(v));
let catalogFormSequence=0;
function fieldMarkup(k,f,r,prefix='field'){
 const value=r?.[k]??f.default??'',required=f.required?'required':'',id=prefix+'-'+k;let control;
 if(f.type==='select')control=`<select id="${id}" name="${k}" ${required}>${Object.entries(f.options).map(([v,t])=>`<option value="${v}" ${String(value)===v?'selected':''}>${esc(t)}</option>`).join('')}</select>`;
 else if(f.type==='relation')control=`<input type="hidden" name="${k}" value="${esc(value)}"><div class="relation-picker"><input id="${id}" type="text" data-lookup-label="${k}" readonly value="${value?esc(r?.[k+'_label']||value):''}" placeholder="${esc(f.empty||'Sin asignar')}" aria-haspopup="dialog"><button type="button" data-lookup="${k}" aria-haspopup="dialog">Seleccionar</button>${!f.required?`<button type="button" data-lookup-clear="${k}" aria-label="Quitar ${esc(f.label)}" ${value?'':'hidden'}>Quitar</button>`:''}</div><small class="hint">${f.required?'Selecciona un registro del catálogo.':esc(f.empty||'Opcional.')}</small>`;
 else if(f.type==='textarea')control=`<textarea id="${id}" name="${k}" maxlength="${f.max}" ${required}>${esc(value)}</textarea>`;
 else control=`<input id="${id}" name="${k}" type="${f.type==='money'||f.type==='integer'?'number':f.type}" value="${esc(value)}" ${required} ${f.max?'maxlength="'+f.max+'"':''} ${f.type==='date'?'max="'+new Date().toLocaleDateString('en-CA')+'"':''} ${f.type==='money'?'min="0" max="9999999999.99" step="0.01"':''} ${f.type==='integer'?'min="'+f.min+'" max="'+f.maxValue+'" step="1"':''}>`;
 return `<div class="field ${f.type==='textarea'?'wide':''}"><label for="${id}">${esc(f.label)}${f.required?' <span aria-hidden="true">*</span>':''}</label>${control}</div>`;
}
async function attachRelations(form,fields){
 for(const [k,f] of Object.entries(fields))if(f.type==='relation'){
  const button=form.querySelector(`[data-lookup="${k}"]`),display=form.querySelector(`[data-lookup-label="${k}"]`),input=form.elements[k],clear=form.querySelector(`[data-lookup-clear="${k}"]`);
  const choose=()=>openCatalogPicker(f.source,{onChoose:record=>{if(!form.isConnected)return;input.value=record.id;display.value=record.codigo+' · '+record.nombre;if(clear)clear.hidden=false;input.dispatchEvent(new Event('change',{bubbles:true}));}});
  button.onclick=choose;display.onclick=choose;display.onkeydown=e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();choose();}};
  if(clear)clear.onclick=()=>{input.value='';display.value='';clear.hidden=true;input.dispatchEvent(new Event('change',{bubbles:true}));};
 }
}
async function mountCatalogForm(host,key,r=null,options={}){
 const d=definitions[key];if(!d)throw Error('No tienes acceso a este catálogo.');const locked=!d.editable,prefix='catalog-'+(++catalogFormSequence);
 host.innerHTML=`<form class="editor catalog-form"><h2>${r?(locked?'Consultar':'Editar')+' '+esc(d.singular):'Crear '+esc(d.singular)}</h2><p class="hint">Los campos con * son obligatorios.${key==='pacientes'?' El expediente se genera si lo dejas vacío.':''}${key==='precios'?' Sin compañía, el precio aplica a Público general.':''}</p><fieldset ${locked?'disabled':''}><div class="form-grid">${Object.entries(d.fields).map(([k,f])=>fieldMarkup(k,f,r,prefix)).join('')}</div>${key==='estudios'?'<section class="wide"><h3>Analitos del estudio</h3><p class="hint">Agrega los analitos en el orden en que aparecerán en el estudio.</p><button type="button" data-analyte-picker>Agregar analito</button><ol data-analyte-selected></ol></section>':''}<label for="${prefix}-active">Estado</label><select id="${prefix}-active" name="activo"><option value="1">Activo</option><option value="0" ${r&&!Number(r.activo)?'selected':''}>Inactivo</option></select></fieldset><div class="form-actions">${locked?'':'<button class="primary">'+(options.saveLabel||'Guardar '+esc(d.singular))+'</button>'}<button data-catalog-cancel type="button">${options.cancelLabel||'Cerrar'}</button></div><p data-form-message role="status" aria-live="polite"></p></form>`;
 const form=host.querySelector('form');let chosen=r?.analitos?[...r.analitos]:[];
 if(!locked)await attachRelations(form,d.fields);
 if(key==='estudios'){
  const render=()=>{form.querySelector('[data-analyte-selected]').innerHTML=chosen.map((a,i)=>`<li><span>${esc(a.codigo+' · '+a.nombre)}${Number(a.activo)===0?' (inactivo)':''}</span>${locked?'':`<button type="button" data-up="${i}" ${i===0?'disabled':''} aria-label="Subir ${esc(a.nombre)}">Subir</button><button type="button" data-remove="${i}" aria-label="Quitar ${esc(a.nombre)}">Quitar</button>`}</li>`).join('')||'<li>Sin analitos asociados.</li>';for(const b of form.querySelectorAll('[data-remove]'))b.onclick=()=>{chosen.splice(Number(b.dataset.remove),1);render();};for(const b of form.querySelectorAll('[data-up]'))b.onclick=()=>{const i=Number(b.dataset.up);[chosen[i-1],chosen[i]]=[chosen[i],chosen[i-1]];render();};};render();
  form.querySelector('[data-analyte-picker]').onclick=()=>openCatalogPicker('analitos',{onChoose:a=>{if(!chosen.some(v=>String(v.id)===String(a.id)))chosen.push(a);render();},excluded:()=>chosen.map(a=>String(a.id))});
 }
 form.querySelector('[data-catalog-cancel]').onclick=()=>options.onCancel?options.onCancel():host.replaceChildren();
 form.onsubmit=async e=>{e.preventDefault();if(locked)return;const message=form.querySelector('[data-form-message]');message.textContent='';options.onBusy?.(true);for(const b of form.querySelectorAll('button'))b.disabled=true;
  try{const data=Object.fromEntries(new FormData(form));if(r)data.version=r.version;if(key==='estudios')data.analitos=chosen.map(a=>a.id);if(options.mustBeActive)data.activo='1';
   const saved=await api('catalogos/'+key+(r?'/'+r.id:''),r?'PUT':'POST',data);await options.onSave?.(saved,data);
  }catch(error){message.textContent=error.message;message.classList.add('message');message.scrollIntoView({block:'nearest'});}
  finally{options.onBusy?.(false);if(form.isConnected){for(const b of form.querySelectorAll('button'))b.disabled=false;for(const b of form.querySelectorAll('[data-up="0"]'))b.disabled=true;}}
 };
 if(options.mustBeActive){form.elements.activo.value='1';form.elements.activo.disabled=true;}
 return form;
}
async function catalogEditor(key,r=null){
 const host=$('#catalog-editor');const form=await mountCatalogForm(host,key,r,{onSave:async()=>{await catalogPage(key,catalogState.page,catalogState.q,catalogState.estado);status(definitions[key].title+': registro guardado.');}});
 host.scrollIntoView({behavior:'smooth',block:'start'});form.querySelector('input:not([type=hidden]),select')?.focus();
}
