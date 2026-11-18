"use strict";
// Native disclosure controls keep the existing rows, events and form values intact.
(() => {
 const editors=new Map();
 function syncEditors(){
  const selector='#catalog-editor form, #user-form, #role-form, #request-form, #result-form, .catalog-picker [data-picker-create-host] form';
  const forms=new Set([...document.querySelectorAll(selector)].filter(form=>!form.querySelector('fieldset[disabled]')));
  // Successful saves replace the editor; cancelling it restores the list too.
  // Failed validation leaves the same form mounted and its grids collapsed.
  for(const [form,sections] of editors){
   if(forms.has(form))continue;
   for(const section of sections)if(section.isConnected)section.open=true;
   editors.delete(form);
  }
  for(const form of forms){
   if(editors.has(form))continue;
   const scope=form.closest('.catalog-picker')||document.querySelector('#content');
   const sections=[...scope.querySelectorAll('details.grid-section')].filter(section=>!section.closest('form')&&!section.contains(form));
   for(const section of sections)section.open=false;
   editors.set(form,sections);
  }
 }
 function updateCount(section,wrap){
  const table=wrap.querySelector('table');
  const rows=table?.tBodies[0]?.rows;
  const count=rows?[...rows].filter(row=>!row.hidden).length:0;
  const label=wrap.querySelector('.picker-loading')?'Cargando…':`${count} ${count===1?'registro':'registros'}`;
  const counter=section.querySelector('[data-grid-count]');
  if(counter.textContent!==label)counter.textContent=label;
 }
 function enhanceGrids(){
  for(const wrap of document.querySelectorAll('#content .table-wrap, .catalog-picker .picker-list')){
   let section=wrap.parentElement;
   if(!section.matches('details.grid-section')){
    if(!wrap.querySelector('table'))continue;
    section=document.createElement('details');section.className='grid-section';section.open=true;
    if(wrap.classList.contains('picker-list'))section.classList.add('grid-picker');
    const summary=document.createElement('summary');summary.className='grid-summary';
    summary.innerHTML='<span class="grid-label">Listado <span data-grid-count class="grid-count"></span></span><span class="grid-action"><span class="grid-when-open">Contraer</span><span class="grid-when-closed">Expandir</span><svg class="grid-chevron" aria-hidden="true" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></span>';
    wrap.before(section);section.append(summary,wrap);
   }
   updateCount(section,wrap);
  }
  syncEditors();
 }
 // Open hidden fields before the browser presents a required/invalid input.
 document.addEventListener('invalid',event=>{
  let section=event.target.closest('.grid-section');
  while(section){section.open=true;section=section.parentElement?.closest('.grid-section');}
 },true);
 enhanceGrids();
 const observer=new MutationObserver(enhanceGrids);
 observer.observe(document.body,{childList:true,subtree:true,attributes:true,attributeFilter:['hidden']});
})();
