(() => {
 const titles={fiscal:'Nuevo fiscal',fiscalia:'Nueva fiscalía',comisaria:'Nueva comisaría',modalidad:'Nueva modalidad',consecuencia:'Nueva consecuencia'};
 document.addEventListener('click',event=>{
  const trigger=event.target.closest('[data-acc-catalog]');
  if(!trigger)return;
  const parent=trigger.closest('form'),kind=trigger.dataset.accCatalog;
  if(!parent||!titles[kind])return;
  const fiscalia=parent.elements.fiscalia_id?.value;
  if(kind==='fiscal'&&!fiscalia){alert('Selecciona primero la fiscalía.');return;}
  const dialog=document.createElement('dialog');
  dialog.style.cssText='border:1px solid #cbd8ec;border-radius:16px;padding:24px;max-width:480px;width:90%;color:#173d36';
  const heading=document.createElement('h3');heading.textContent=titles[kind];dialog.append(heading);
  const form=document.createElement('form');form.style.cssText='display:grid;gap:12px';
  const fields=kind==='fiscal'?[['nombres','Nombres',true],['apellido_paterno','Apellido paterno'],['apellido_materno','Apellido materno'],['telefono','Teléfono'],['cargo','Cargo']]:[['nombre','Nombre',true]];
  fields.forEach(([name,text,required])=>{
   const label=document.createElement('label');label.textContent=text;
   const input=document.createElement('input');input.name=name;input.required=!!required;input.maxLength=200;input.style.cssText='display:block;width:100%;padding:8px';label.append(input);form.append(label);
  });
  const status=document.createElement('p');status.setAttribute('role','status');
  const save=document.createElement('button');save.type='submit';save.textContent='Guardar';save.className='btn-shell';
  const cancel=document.createElement('button');cancel.type='button';cancel.textContent='Cancelar';cancel.className='btn-shell';
  cancel.onclick=()=>dialog.close();
  form.append(status,save,cancel);dialog.append(form);document.body.append(dialog);
  dialog.addEventListener('close',()=>{dialog.remove();trigger.focus();});
  dialog.showModal();
  let busy=false;
  dialog.addEventListener('cancel',e=>{if(busy)e.preventDefault();});
  form.addEventListener('submit',async e=>{
   e.preventDefault();e.stopPropagation();if(busy)return;
   const body=new FormData(form);
   body.set('_csrf',document.querySelector('meta[name="csrf-token"]')?.content||parent.querySelector('[name="_csrf"]')?.value||'');
   if(kind==='fiscal')body.set('fiscalia_id',fiscalia);
   if(kind==='comisaria')['cod_dep','cod_prov','cod_dist'].forEach(name=>body.set(name,parent.elements[name]?.value||''));
   busy=true;save.disabled=cancel.disabled=true;status.textContent='Guardando…';
   try{
    const response=await fetch('accidente_nuevo.php?ajax=create&type='+encodeURIComponent(kind),{method:'POST',body});
    const result=await response.json();
    if(!response.ok||!result.ok)throw new Error(result.msg||'No se pudo guardar.');
    if(kind==='modalidad'||kind==='consecuencia'){
     const name=kind+'_ids[]';
     const grid=trigger.parentElement.querySelector('.general-checkbox-grid');
     let input=[...grid.querySelectorAll('input')].find(i=>i.value===String(result.id));
     if(!input){const label=document.createElement('label');label.className='general-checkbox';input=document.createElement('input');input.type='checkbox';input.name=name;input.value=result.id;const span=document.createElement('span');span.textContent=result.label;label.append(input,span);grid.append(label);}
     input.checked=true;input.dispatchEvent(new Event('change',{bubbles:true}));
    }else{
     const select=parent.elements[kind==='comisaria'?'comisaria_id':kind+'_id'];
     let option=[...select.options].find(o=>o.value===String(result.id));
     if(!option){option=new Option(result.label,result.id);select.add(option);}
     select.disabled=false;select.value=String(result.id);select.dataset.current=String(result.id);
     select.dispatchEvent(new Event('change',{bubbles:true}));
    }
    dialog.close();
   }catch(error){status.textContent=error.message||'No se pudo guardar. Intenta nuevamente.';}
   finally{busy=false;save.disabled=cancel.disabled=false;}
  });
 });
})();
