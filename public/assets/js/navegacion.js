"use strict";
// Small, local SVG icons share the text color of each laboratory's navigation.
const navIconPaths={
 home:'<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1Z"/>',
 user:'<circle cx="12" cy="7" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',
 users:'<circle cx="9" cy="7" r="3"/><path d="M2 21v-3a7 7 0 0 1 14 0v3M17 4a3 3 0 0 1 0 6m2 4a6 6 0 0 1 3 5v2"/>',
 'user-plus':'<circle cx="9" cy="7" r="3"/><path d="M2 21v-3a7 7 0 0 1 14 0v3m3-16v6m-3-3h6"/>',
 shield:'<path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6Z"/><path d="m8 12 3 3 5-6"/>',
 clipboard:'<rect x="5" y="5" width="14" height="17" rx="2"/><rect x="9" y="2" width="6" height="5" rx="1"/><path d="M9 12h6m-6 5h6"/>',
 flask:'<path d="M9 3h6m-5 0v7L4 20a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1l-6-10V3M7 16h10"/>',
 send:'<path d="m22 2-7 20-4-9L2 9Zm0 0L11 13"/>',
 calculator:'<rect x="5" y="2" width="14" height="20" rx="2"/><path d="M8 6h8v4H8Zm0 8h1m3 0h1m3 0h0m-8 4h1m3 0h1m3 0h0"/>',
 edit:'<path d="M12 5H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-7M16 3l5 5M9 15l-1 4 4-1L22 8a2 2 0 0 0-5-5Z"/>',
 printer:'<path d="M7 8V3h10v5M7 17H4a2 2 0 0 1-2-2v-4a3 3 0 0 1 3-3h14a3 3 0 0 1 3 3v4a2 2 0 0 1-2 2h-3M7 14h10v7H7Zm11-3h1"/>',
 history:'<path d="M3 10a9 9 0 1 1 2 8M3 4v6h6m3-4v6l4 2"/>',
 wallet:'<path d="M21 8H5a3 3 0 0 1 0-6h14v6M3 5v14a2 2 0 0 0 2 2h16V8m0 5h-5v4h5"/>',
 card:'<rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 9h20M6 15h4"/>',
 refund:'<path d="M3 8h12a6 6 0 0 1 0 12h-3M7 4 3 8l4 4m5-8V2m5 4V4"/>',
 incoming:'<path d="M20 4 6 18m0-7v7h7M3 22h18"/>',
 outgoing:'<path d="M4 18 18 4m-7 0h7v7M3 22h18"/>',
 check:'<rect x="3" y="3" width="18" height="18" rx="2"/><path d="m7 12 3 3 7-7"/>',
 settings:'<path d="M4 6h16M4 12h16M4 18h16"/><circle cx="8" cy="6" r="2"/><circle cx="16" cy="12" r="2"/><circle cx="10" cy="18" r="2"/>',
 activity:'<path d="M2 12h4l3-8 6 16 3-8h4"/>',
 palette:'<path d="M12 3a9 9 0 1 0 0 18h2a2 2 0 0 0 1-4 2 2 0 0 1 1-4h2a3 3 0 0 0 3-3c0-4-4-7-9-7Z"/><circle cx="7" cy="10" r=".8"/><circle cx="10" cy="7" r=".8"/><circle cx="15" cy="7" r=".8"/>',
 layers:'<path d="m12 3 10 5-10 5L2 8Zm-10 9 10 5 10-5M2 16l10 5 10-5"/>',
 tubes:'<path d="M4 3h6M5 3v15a2 2 0 0 0 4 0V3M14 3h6m-5 0v15a2 2 0 0 0 4 0V3M5 11h4m6 2h4"/>',
 doctor:'<path d="M5 3v6a5 5 0 0 0 10 0V3M3 3h4m6 0h4m-7 11v2a5 5 0 0 0 10 0v-3"/><circle cx="20" cy="10" r="3"/>',
 building:'<path d="M4 22V3h12v19m0-12h5v12M2 22h21M8 7h4m-4 4h4m-4 4h4m-3 7v-3h3v3"/>',
 tag:'<path d="M3 3h8l10 10-8 8L3 11Z"/><circle cx="7.5" cy="7.5" r="1"/>',
 droplet:'<path d="M12 2c-2 4-8 9-8 13a8 8 0 0 0 16 0c0-4-6-9-8-13Z"/><path d="M8 15a4 4 0 0 0 4 4"/>'
};
const navPageIcons={
 'recepcion:nuevo':'user-plus','recepcion:captura':'flask','recepcion:entrega':'send','recepcion:cotizaciones':'calculator','recepcion:ordenes':'clipboard','recepcion:editar':'edit','recepcion:comprobantes':'printer','recepcion:historial':'history',
 cobros:'card',anulaciones:'refund','caja:ingresos':'incoming','caja:egresos':'outgoing','caja:corte':'check',
 inicio:'home',usuarios:'users',roles:'shield',resultados:'activity',bitacora:'history',identidad:'palette',
 'catalogo:estudios':'flask','catalogo:analitos':'tubes','catalogo:pacientes':'user','catalogo:medicos':'doctor','catalogo:companias':'building','catalogo:precios':'tag','catalogo:tipos_muestra':'droplet','catalogo:metodos':'settings'
};
function navigationIcon(name){return `<svg class="nav-icon" aria-hidden="true" focusable="false" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">${navIconPaths[name]||navIconPaths.clipboard}</svg>`;}
function renderNavigation(groups){
 const storageKey='lab_nav_groups_v1:'+user.id;let saved={};
 try{const value=JSON.parse(localStorage.getItem(storageKey)||'{}');if(value&&typeof value==='object'&&!Array.isArray(value))saved=value;}catch{}
 const nav=$('#navigation');
 nav.innerHTML=groups.filter(group=>group.items.length).map(group=>`<details class="nav-group" data-nav-group="${esc(group.key)}" ${saved[group.key]===false?'':'open'}><summary class="nav-group-toggle">${navigationIcon(group.icon)}<span>${esc(group.label)}</span><svg class="nav-group-chevron" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 5 7 7-7 7"/></svg></summary><div class="nav-group-items">${group.items.map(([page,label])=>`<button type="button" data-nav="${esc(page)}">${navigationIcon(navPageIcons[page])}<span class="nav-label">${esc(label)}</span></button>`).join('')}</div></details>`).join('');
 for(const group of nav.querySelectorAll('.nav-group'))group.addEventListener('toggle',()=>{
  saved[group.dataset.navGroup]=group.open;try{localStorage.setItem(storageKey,JSON.stringify(saved));}catch{}
 });
 for(const button of nav.querySelectorAll('[data-nav]'))button.onclick=()=>run(()=>navigate(button.dataset.nav));
}
