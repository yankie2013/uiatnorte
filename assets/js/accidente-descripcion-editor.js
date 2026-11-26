(() => {
  'use strict';
  const clone = value => JSON.parse(JSON.stringify(value));
  const el = (tag, text = '', className = '') => {const node = document.createElement(tag); node.textContent = text; if (className) node.className = className; return node;};
  const button = (text, action) => {const node = el('button', text, 'btn-shell'); node.type = 'button'; node.addEventListener('click', action); return node;};
  const roadFields = [['nombre','Nombre de la vía'],['descripcion','Descripción de la vía'],['configuracion','a. Configuración'],['material','b. Material y estado de la calzada'],['control','c. Dispositivos de control de tránsito'],['ordenamiento','d. Ordenamiento del tránsito'],['maniobrabilidad','e. Área de maniobrabilidad'],['iluminacion','f. Iluminación'],['visibilidad','g. Visibilidad'],['intensidad','h. Intensidad vehicular'],['fluidez','i. Fluidez vehicular'],['medidas','Medidas'],['observaciones','Observaciones'],['fotografia','URL de fotografía (opcional)'],['pie','Pie de fotografía']];
  const seed = (itp = {}, previous = {}) => {
    const result = {inspeccion: {...(previous.inspeccion || {}), fecha: itp.fecha_itp || '', hora: (itp.hora_itp || '').slice(0,5), lugar: previous.inspeccion?.lugar || '', llegada: itp.llegada_lugar || ''}, vias: [], consideraciones: previous.consideraciones || [], aproximaciones: previous.aproximaciones || [], posiciones: []};
    for (const i of [1,2]) {
      const suffix = '_via' + i;
      if (i === 2 && !Object.keys(itp).some(key => key.endsWith(suffix) && itp[key])) continue;
      const road = {nombre: i === 1 ? (itp.forma_via || 'Vía 1') : 'Vía 2', descripcion: itp['descripcion' + suffix] || ''};
      for (const key of ['configuracion','material','ordenamiento','iluminacion','visibilidad','intensidad','fluidez','medidas','observaciones']) road[key] = itp[key + suffix] || '';
      road.control = itp['senializacion' + suffix] || itp['señalizacion' + suffix] || '';
      result.vias.push(road);
    }
    for (const line of (itp.localizacion_unidades || '').split(',').map(line => line.replace(/^\s*[-–•]\s*/, '').trim()).filter(Boolean)) {
      const separator = line.indexOf(':');
      result.posiciones.push({unidad: separator >= 0 ? line.slice(0,separator).trim() : 'Unidad involucrada', ubicacion: separator >= 0 ? line.slice(separator+1).trim() : line});
    }
    return result;
  };
  window.ReportDescriptionEditor = ({context, rows, head, heading, empty, add, save, status}) => {
    const saved = context.saved.descripcion?.data?.contenido;
    let values = saved ? clone(saved) : (context.inspectionSources?.length ? seed(context.inspectionSources[0]) : {inspeccion:{},vias:[],consideraciones:[],aproximaciones:[],posiciones:[]});
    if (!values.inspeccion.lugar) values.inspeccion.lugar = context.accidentPlace || '';
    const section = (title, parent = rows) => {const node = el('section','', 'description-report-section'); node.append(el('h4',title)); parent.append(node); return node;};
    const paragraph = (parent, text) => {if (text) parent.append(el('p',text));};
    const bullets = (parent, text) => {const list = el('ul'); for (const line of (text || '').split(/\r?\n/).map(line=>line.replace(/^\s*[-–•]\s*/,'').trim()).filter(Boolean)) list.append(el('li',line)); if(list.children.length)parent.append(list);};
    const render = () => {
      rows.replaceChildren(); heading.hidden = true; empty.hidden = true; head.style.justifyContent = 'flex-end';
      const inspection = values.inspeccion;
      const intro = section('A. INSPECCIÓN TÉCNICO POLICIAL');
      const date = inspection.fecha ? inspection.fecha.split('-').reverse().join('/') : '';
      const pieces = [date ? 'Realizada el ' + date : '', inspection.hora ? 'a las ' + inspection.hora + ' horas aproximadamente' : '', inspection.lugar ? 'en ' + inspection.lugar : ''].filter(Boolean);
      if(pieces.length)paragraph(intro,pieces.join(', ') + ', constatándose lo siguiente:');
      paragraph(section('1. Llegada al lugar del evento'), inspection.llegada);
      paragraph(section('2. Método utilizado de ingreso a la escena del evento'), inspection.metodo);
      bullets(section('3. Perennización'), inspection.perennizacion);
      const roads = section('4. Referente a la vía');
      for (const road of values.vias) {
        const article = el('article','', 'description-road'); article.append(el('h5',road.nombre || 'Vía')); paragraph(article,road.descripcion);
        const details = el('dl'); for (const [key,label] of roadFields.slice(2,11)) if(road[key])details.append(el('dt',label),el('dd',road[key])); article.append(details);
        if(road.medidas) {article.append(el('h5','Medidas')); bullets(article,road.medidas.replace(/,\s*/g,'\n'));}
        if(road.observaciones) {article.append(el('h5','Observaciones')); paragraph(article,road.observaciones);}
        if(road.fotografia && /^(https?:\/\/|uploads\/)/.test(road.fotografia)) {const figure=el('figure');const img=el('img');img.src=road.fotografia;img.alt=road.pie || road.nombre || 'Vista de la vía';img.loading='lazy';img.referrerPolicy='no-referrer';figure.append(img);if(road.pie)figure.append(el('figcaption',road.pie));article.append(figure);}
        roads.append(article);
      }
      paragraph(section('5. Condiciones climáticas'),inspection.clima);
      const conflict=section('6. Zona de conflicto');
      if(values.consideraciones.length){conflict.append(el('h5','a. Teniendo en consideración lo siguiente:'));const list=el('ul');values.consideraciones.forEach(item=>list.append(el('li',item.texto)));conflict.append(list);}
      if(values.aproximaciones.length){conflict.append(el('h5','b. Se establece por aproximación:'));values.aproximaciones.forEach((item,i)=>{conflict.append(el('h5',`${i+1}) ${item.titulo}`));paragraph(conflict,item.descripcion);});}
      const positions=section('7. Posiciones finales');values.posiciones.forEach((item,i)=>{positions.append(el('h5',`${String.fromCharCode(97+i)}. ${item.unidad}`));paragraph(positions,item.ubicacion);});
    };
    const openModal = () => {
      if(!context.editable)return;
      let draft=clone(values);
      const dialog=el('dialog','', 'report-risk-dialog report-damage-dialog report-integral-dialog description-dialog');
      const title=el('h3','Descripción analítica');title.id='description-dialog-title';dialog.setAttribute('aria-labelledby',title.id);
      const toolbar=el('div','', 'description-import');
      const source=el('select');source.setAttribute('aria-label','ITP de origen');
      for(const itp of context.inspectionSources || [])source.append(new Option('ITP #'+itp.id+' · '+(itp.fecha_itp || 'Sin fecha'),String(itp.id)));
      const message=el('p');message.setAttribute('role','status');
      const editor=el('div','', 'description-editor');
      const tabs=el('div','', 'report-reference-tabs');tabs.setAttribute('role','tablist');
      const panel=el('div','', 'description-edit-panel');panel.id='description-edit-panel';panel.setAttribute('role','tabpanel');
      const field = (object,key,label,type='textarea') => {
        const wrap=el('label',label);const input=el(type==='textarea'?'textarea':'input');if(type!=='textarea')input.type=type;
        input.value=object[key] || '';input.maxLength=20000;input.setAttribute('aria-label',label);if(type==='textarea')input.rows=3;
        input.addEventListener('input',()=>object[key]=input.value);wrap.append(input);return wrap;
      };
      const cards = (key, fields, addLabel) => {
        const list=el('div','', 'description-edit-cards');
        const draw=()=>{list.replaceChildren();draft[key].forEach((item,index)=>{const card=el('section','', 'description-edit-card');const header=el('div','', 'description-card-head');header.append(el('strong',`${index+1}.`),button('Quitar',()=>{draft[key].splice(index,1);draw();}));card.append(header);const grid=el('div','', 'description-fields');fields.forEach(([name,label,type])=>grid.append(field(item,name,label,type || 'textarea')));card.append(grid);list.append(card);});};
        const add=button(addLabel,()=>{if(draft[key].length>=200){message.textContent='Máximo 200 registros.';return;}draft[key].push({});draw();list.lastElementChild?.querySelector('input,textarea')?.focus();});panel.append(add,list);draw();
      };
      const entries=[['Inspección',()=>{
        const grid=el('div','', 'description-fields');for(const [key,label,type] of [['fecha','Fecha de inspección','date'],['hora','Hora','time'],['lugar','Lugar de inspección y distrito','text'],['llegada','1. Llegada al lugar del evento'],['metodo','2. Método utilizado para ingresar a la escena'],['perennizacion','3. Perennización (una técnica por línea)'],['clima','5. Condiciones climáticas']])grid.append(field(draft.inspeccion,key,label,type || 'textarea'));panel.append(grid);
      }],['Vías',()=>cards('vias',roadFields,'Añadir vía')],['Zona de conflicto',()=>{panel.append(el('h4','a. Elementos considerados'));cards('consideraciones',[['texto','Elemento considerado']],'Añadir consideración');panel.append(el('h4','b. Áreas establecidas por aproximación'));cards('aproximaciones',[['titulo','Título del área','text'],['descripcion','Descripción, medidas y ubicación respecto al punto de referencia']],'Añadir área');}],['Posiciones finales',()=>cards('posiciones',[['unidad','Vehículo o persona involucrada','text'],['ubicacion','Posición final, orientación y ubicación']],'Añadir posición')]];
      const activate=index=>{[...tabs.children].forEach((tab,i)=>{tab.setAttribute('aria-selected',String(i===index));tab.tabIndex=i===index?0:-1;});panel.setAttribute('aria-labelledby','description-edit-tab-'+index);panel.replaceChildren();entries[index][1]();};
      entries.forEach(([label],index)=>{const tab=button(label,()=>activate(index));tab.id='description-edit-tab-'+index;tab.setAttribute('role','tab');tab.setAttribute('aria-controls',panel.id);tab.addEventListener('keydown',event=>{let next;if(event.key==='ArrowRight')next=(index+1)%entries.length;if(event.key==='ArrowLeft')next=(index+entries.length-1)%entries.length;if(next!==undefined){event.preventDefault();activate(next);tabs.children[next].focus();}});tabs.append(tab);});
      if(source.options.length)toolbar.append(el('span','Reutilizar datos de ITP:'),source,button('Usar esta ITP',()=>{const itp=context.inspectionSources.find(item=>String(item.id)===source.value);draft=seed(itp,draft);draft.inspeccion.lugar=context.accidentPlace || '';activate(0);message.textContent='Se copiaron llegada, vías y posiciones de la ITP. Los cambios de este análisis no modifican la ITP.';}));
      else toolbar.append(el('p','No hay ITP registrada. Puedes completar los campos manualmente.'));
      editor.append(toolbar,tabs,panel);
      const actions=el('div','', 'report-risk-actions');const cancel=button('Cancelar',()=>dialog.close());const submit=button('Guardar descripción',async()=>{
        const previous=values;values=clone(draft);render();submit.disabled=true;cancel.disabled=true;editor.inert=true;
        const prevent=event=>event.preventDefault();dialog.addEventListener('cancel',prevent);
        try{if(await save())dialog.close();else{values=previous;render();message.textContent=status.textContent;}}finally{submit.disabled=false;cancel.disabled=false;editor.inert=false;dialog.removeEventListener('cancel',prevent);}
      });actions.append(cancel,submit);dialog.append(title,editor,message,actions);dialog.addEventListener('close',()=>dialog.remove());document.body.append(dialog);activate(0);dialog.showModal();
    };
    render();add.addEventListener('click',openModal);return()=>clone(values);
  };
})();
