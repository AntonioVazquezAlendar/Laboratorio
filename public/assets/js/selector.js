"use strict";
let pickerSequence=0,pickerCount=0;
const pickerNames={pacientes:['Pacientes','paciente'],medicos:['Médicos','médico'],companias:['Compañías','compañía'],estudios:['Estudios','estudio'],analitos:['Analitos','analito'],tipos_muestra:['Tipos de muestra','tipo de muestra'],metodos:['Métodos','método']};
function openCatalogPicker(source,options={}){
 const meta=definitions[source],names=pickerNames[source];if(!names){status('Este catálogo no tiene selector disponible.',true);return;}
 const id='catalog-picker-'+(++pickerSequence),launcher=document.activeElement,dialog=document.createElement('dialog');dialog.className='catalog-picker';dialog.setAttribute('aria-labelledby',id+'-title');dialog.setAttribute('aria-describedby',id+'-help');
 dialog.innerHTML=`<div class="picker-header"><div><h2 id="${id}-title">Seleccionar ${esc(names[1])}</h2><p id="${id}-help">Busca por código o nombre. Haz doble clic en una fila o pulsa Elegir.</p></div><button type="button" data-picker-close aria-label="Cerrar selector de ${esc(names[0].toLowerCase())}">Cerrar</button></div><section data-picker-list-view><form class="picker-search" data-picker-search><label for="${id}-query">Buscar en ${esc(names[0].toLowerCase())}</label><div><input id="${id}-query" name="q" type="search" maxlength="100" placeholder="Código o nombre" autocomplete="off"><button class="primary">Buscar</button>${meta?.editable?`<button type="button" data-picker-new>Crear ${esc(names[1])}</button>`:''}</div></form><p class="picker-notice" data-picker-message role="status" aria-live="polite"></p><div class="picker-list" data-picker-list></div><div class="picker-pagination"><span data-picker-page></span><div><button type="button" data-picker-prev>Anterior</button><button type="button" data-picker-next>Siguiente</button></div></div><div class="picker-footer"><p data-picker-selection>Selecciona una fila.</p><div><button type="button" data-picker-cancel>Cancelar</button><button type="button" class="primary" data-picker-choose disabled>Elegir seleccionado</button></div></div></section><section data-picker-create-view hidden><button type="button" data-picker-back class="picker-back">Volver a la lista</button><div data-picker-create-host></div></section>`;
 document.body.append(dialog);pickerCount++;document.body.classList.add('picker-open');let page=1,query=options.query||'',rows=[],selected=null,more=false,request=0,closed=false,busy=false;
 const find=s=>dialog.querySelector(s),message=find('[data-picker-message]');
 const cleanup=()=>{if(closed)return;closed=true;request++;dialog.close();dialog.remove();pickerCount--;document.body.classList.toggle('picker-open',pickerCount>0);if(launcher?.isConnected)launcher.focus({preventScroll:true});};
 const cancel=()=>{if(!busy)cleanup();};
 const excluded=r=>(options.excluded?.()||[]).map(String).includes(String(r.id));
 const select=r=>{if(closed||excluded(r))return;selected=r;for(const tr of dialog.querySelectorAll('[data-picker-row]')){const on=tr.dataset.pickerRow===String(r.id);tr.setAttribute('aria-selected',String(on));tr.classList.toggle('is-selected',on);tr.tabIndex=on?0:-1;}find('[data-picker-selection]').textContent=r.codigo+' · '+r.nombre;find('[data-picker-choose]').disabled=false;};
 const choose=r=>{if(closed||busy||!r||excluded(r))return;cleanup();try{options.onChoose?.(r);}catch(error){status(error.message,true);}};
 const render=()=>{
  find('[data-picker-list]').innerHTML=rows.length?`<table role="grid" aria-label="Resultados de ${esc(names[0].toLowerCase())}"><thead><tr><th scope="col">Código</th><th scope="col">Nombre</th><th scope="col">Detalles</th><th scope="col">Acción</th></tr></thead><tbody>${rows.map((r,i)=>`<tr data-picker-row="${r.id}" tabindex="${i===0?'0':'-1'}" aria-selected="false" ${excluded(r)?'aria-disabled="true"':''}><td><span class="picker-code">${esc(r.codigo)}</span></td><td><strong>${esc(r.nombre)}</strong>${excluded(r)?'<small>Ya agregado</small>':''}</td><td>${Object.entries(r.detalles||{}).map(([k,v])=>`<span class="picker-detail"><span>${esc(k)}:</span> ${esc(v)}</span>`).join('')||'<span class="picker-muted">Sin datos adicionales</span>'}</td><td><button type="button" data-picker-pick="${r.id}" ${excluded(r)?'disabled':''} aria-label="Elegir ${esc(r.nombre)}">Elegir</button></td></tr>`).join('')}</tbody></table>`:'<div class="picker-empty"><h3>No encontramos coincidencias</h3><p>Prueba otro código o nombre.'+(meta?.editable?' También puedes crear un registro desde aquí.':'')+'</p></div>';
  find('[data-picker-page]').textContent=`Página ${page} · ${rows.length} registros`;find('[data-picker-prev]').disabled=page<=1;find('[data-picker-next]').disabled=!more;
  const trs=[...dialog.querySelectorAll('[data-picker-row]')];
  for(const tr of trs){const r=rows.find(r=>String(r.id)===tr.dataset.pickerRow);tr.onclick=e=>{if(!e.target.closest('button'))select(r);};tr.ondblclick=e=>{if(!e.target.closest('button'))choose(r);};tr.onkeydown=e=>{if(e.target.closest('button'))return;if(e.key==='Enter'){e.preventDefault();choose(r);}else if(e.key===' '){e.preventDefault();select(r);}else if(e.key==='ArrowDown'||e.key==='ArrowUp'){e.preventDefault();const index=trs.indexOf(tr),next=trs[Math.max(0,Math.min(trs.length-1,index+(e.key==='ArrowDown'?1:-1)))];const nr=rows.find(r=>String(r.id)===next.dataset.pickerRow);select(nr);next.focus();}};}
  for(const b of dialog.querySelectorAll('[data-picker-pick]'))b.onclick=()=>choose(rows.find(r=>String(r.id)===b.dataset.pickerPick));
 };
 const load=async()=>{
  const seq=++request;selected=null;rows=[];find('[data-picker-choose]').disabled=true;find('[data-picker-selection]').textContent='Selecciona una fila.';find('[data-picker-prev]').disabled=true;find('[data-picker-next]').disabled=true;find('[data-picker-list]').innerHTML='<p class="picker-loading">Cargando registros…</p>';message.textContent='';
  try{const d=await api(`catalogos/${source}/opciones?q=${encodeURIComponent(query)}&pagina=${page}`);if(closed||seq!==request)return;rows=d.registros;more=d.hay_mas;render();}
  catch(error){if(closed||seq!==request)return;find('[data-picker-list]').replaceChildren();message.textContent=error.message;}
 };
 const showList=()=>{if(busy)return;find('[data-picker-create-view]').hidden=true;find('[data-picker-list-view]').hidden=false;find('[data-picker-create-host]').replaceChildren();find('[data-picker-search] input').focus();load();};
 find('[data-picker-search] input').value=query;
 find('[data-picker-search]').onsubmit=e=>{e.preventDefault();query=new FormData(e.target).get('q').trim();page=1;load();};
 find('[data-picker-prev]').onclick=()=>{if(page>1){page--;load();}};find('[data-picker-next]').onclick=()=>{if(more){page++;load();}};find('[data-picker-choose]').onclick=()=>choose(selected);
 find('[data-picker-close]').onclick=cancel;find('[data-picker-cancel]').onclick=cancel;find('[data-picker-back]').onclick=showList;
 dialog.addEventListener('cancel',e=>{e.preventDefault();cancel();});dialog.addEventListener('click',e=>{if(e.target===dialog){const r=dialog.getBoundingClientRect();if(e.clientX<r.left||e.clientX>r.right||e.clientY<r.top||e.clientY>r.bottom)cancel();}});
 if(find('[data-picker-new]'))find('[data-picker-new]').onclick=async()=>{
  request++;find('[data-picker-list-view]').hidden=true;find('[data-picker-create-view]').hidden=false;
  try{const form=await mountCatalogForm(find('[data-picker-create-host]'),source,null,{mustBeActive:true,saveLabel:'Guardar y seleccionar',cancelLabel:'Volver a la lista',onCancel:showList,
   onBusy:value=>{busy=value;find('[data-picker-close]').disabled=value;find('[data-picker-back]').disabled=value;},
   onSave:async(saved,data)=>{let record={id:saved.id,codigo:data.codigo||'ID '+saved.id,nombre:[data.nombre,data.apellidos].filter(Boolean).join(' '),detalles:{}};
    try{const full=await api(`catalogos/${source}/${saved.id}`);record={id:full.id,codigo:full.codigo,nombre:full.nombre_completo||full.nombre,detalles:{}};}catch{}
    busy=false;choose(record);
   }});form.querySelector('input:not([type=hidden]),select')?.focus();
  }catch(error){busy=false;showList();message.textContent=error.message;}
 };
 dialog.showModal();find('[data-picker-search] input').focus();load();return dialog;
}
