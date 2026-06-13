# Guia de marcadores para plantillas Word

Generada: 13/06/2026 02:34

## Como usar los marcadores

- Inserta cada marcador en Word con el formato `${nombre_marcador}`.
- Conserva el marcador completo en una sola linea y con la misma escritura.
- **Presente:** fue encontrado dentro de la plantilla DOCX.
- **Disponible:** un generador PHP relacionado sabe completar el marcador, aunque no necesariamente este insertado en el DOCX.
- Los marcadores dinamicos construidos por concatenacion pueden no aparecer como disponibles en este inventario automatico.

## Resumen

- Plantillas revisadas: 37
- Marcadores unicos presentes: 1286
- Marcadores unicos detectados en generadores relacionados: 831

## oficio_necropsia.docx

**Generadores relacionados:** No detectado automaticamente

**Marcadores presentes (12):**

- `entidad_*`: `${entidad_nombre}`
- `fallecido_*`: `${fallecido_apellidos}`, `${fallecido_edad}`, `${fallecido_nombres}`
- `fiscalia_*`: `${fiscalia_nombre}`
- `nombre_*`: `${nombre_oficial_ano}`
- `numero_*`: `${numero_pericial}`
- `oficio_*`: `${oficio_anio}`, `${oficio_fecha}`, `${oficio_grado_cargo}`, `${oficio_numero}`, `${oficio_persona_destino}`

**Disponibles en codigo pero no presentes (0):**

- Ninguno detectado.

## plantillas/acta_entrega_vehiculo.docx

**Generadores relacionados:** `accidente_vista_tabs.php`, `acta_entrega_vehiculo_descargar.php`

**Marcadores presentes (9):**

- `acta_*`: `${acta_presentacion_propietario}`
- `hora_*`: `${hora_culminacion}`
- `propietario_*`: `${propietario_nombre}`
- `vehiculo_*`: `${vehiculo_anio_compuesto}`, `${vehiculo_clase_compuesto}`, `${vehiculo_color_compuesto}`, `${vehiculo_marca_compuesto}`, `${vehiculo_modelo_compuesto}`, `${vehiculo_placa_compuesto}`

**Disponibles en codigo pero no presentes (62):**

- `accidente_*`: `${accidente_lugar}`, `${accidente_sidpol}`
- `acta_*`: `${acta_distrito}`, `${acta_estado}`, `${acta_id}`, `${acta_intro_apertura}`, `${acta_intro_cierre}`, `${acta_intro_despues_persona}`, `${acta_intro_empresa}`, `${acta_intro_persona}`, `${acta_tipo}`
- `conductor_*`: `${conductor_celular}`, `${conductor_domicilio}`, `${conductor_email}`, `${conductor_nombre}`, `${conductor_num_doc}`, `${conductor_tipo_doc}`
- `fecha_*`: `${fecha_entrega}`, `${fecha_entrega_abrev}`, `${fecha_entrega_corta}`
- `hora_*`: `${hora_inicio}`
- `observaciones_*`: `${observaciones}`
- `placa_*`: `${placa_rodaje}`
- `propietario_*`: `${propietario_celular}`, `${propietario_domicilio}`, `${propietario_email}`, `${propietario_num_doc}`, `${propietario_origen}`, `${propietario_presentacion}`, `${propietario_razon_social}`, `${propietario_rol_legal}`, `${propietario_ruc}`, `${propietario_tipo}`, `${propietario_tipo_doc}`
- `representante_*`: `${representante_celular}`, `${representante_domicilio}`, `${representante_email}`, `${representante_nombre}`, `${representante_num_doc}`, `${representante_rol_legal}`, `${representante_tipo_doc}`
- `vehiculo_*`: `${vehiculo_anio}`, `${vehiculo_carroceria}`, `${vehiculo_carroceria_compuesto}`, `${vehiculo_categoria}`, `${vehiculo_categoria_compuesto}`, `${vehiculo_clase}`, `${vehiculo_color}`, `${vehiculo_dimensiones}`, `${vehiculo_dimensiones_compuesto}`, `${vehiculo_marca}`, `${vehiculo_modelo}`, `${vehiculo_motor}`, `${vehiculo_motor_compuesto}`, `${vehiculo_participacion_compuesto}`, `${vehiculo_placa}`, `${vehiculo_tipo}`, `${vehiculo_ut}`, `${vehiculo_ut_compuesto}`, `${vehiculo_vin}`, `${vehiculo_vin_compuesto}`
- `vehiculos_*`: `${vehiculos_involucrados}`

## plantillas/acta_itp_interseccion.docx

**Generadores relacionados:** `itp_plantilla_descargar.php`

**Marcadores presentes (0):**

- Ninguno detectado.

**Disponibles en codigo pero no presentes (0):**

- Ninguno detectado.

## plantillas/acta_itp_simple.docx

**Generadores relacionados:** `itp_plantilla_descargar.php`

**Marcadores presentes (0):**

- Ninguno detectado.

**Disponibles en codigo pero no presentes (0):**

- Ninguno detectado.

## plantillas/acta_visualizacion_video.docx

**Generadores relacionados:** `accidente_vista_tabs.php`, `acta_visualizacion_descargar.php`, `docs/scripts/agregar_bloque_descripciones_acta_visualizacion.php`

**Marcadores presentes (11):**

- `/DESCRIPCIONES_*`: `${/DESCRIPCIONES_VIDEO}`
- `acta_*`: `${acta_presentacion}`
- `archivo_*`: `${archivo_encabezado}`
- `descripcion_*`: `${descripcion_captura}`, `${descripcion_detalle}`, `${descripcion_tiempo}`
- `DESCRIPCIONES_*`: `${DESCRIPCIONES_VIDEO}`
- `diligencia_*`: `${diligencia_discos_parrafo}`, `${diligencia_oficios_parrafo}`
- `disco_*`: `${disco_encabezado}`
- `ministerio_*`: `${ministerio_publico_parrafo}`

**Disponibles en codigo pero no presentes (43):**

- `abogados_*`: `${abogados_detalle}`
- `accidente_*`: `${accidente_distrito}`, `${accidente_fecha}`, `${accidente_fecha_abrev}`, `${accidente_fecha_corta}`, `${accidente_hora}`, `${accidente_id}`, `${accidente_lugar}`, `${accidente_referencia}`, `${accidente_sidpol}`
- `acta_*`: `${acta_visualizacion_estado}`, `${acta_visualizacion_fecha}`, `${acta_visualizacion_fecha_abrev}`, `${acta_visualizacion_fecha_corta}`, `${acta_visualizacion_hora_inicio}`, `${acta_visualizacion_id}`, `${acta_visualizacion_observaciones}`
- `archivos_*`: `${archivos_detalle}`
- `cantidad_*`: `${cantidad_archivos}`, `${cantidad_descripciones_video}`, `${cantidad_discos}`
- `desarrollo_*`: `${desarrollo_diligencia}`
- `descripciones_*`: `${descripciones_video_detalle}`
- `diligencia_*`: `${diligencia_archivos_detalle}`
- `discos_*`: `${discos_detalle}`
- `documentos_*`: `${documentos_camaras_detalle}`
- `familiares_*`: `${familiares_detalle}`
- `fiscal_*`: `${fiscal_cargo}`, `${fiscal_nombre}`, `${fiscal_telefono}`
- `fiscalia_*`: `${fiscalia_nombre}`
- `instructor_*`: `${instructor_cip}`, `${instructor_grado}`, `${instructor_nombre}`
- `lugar_*`: `${lugar_diligencia}`
- `oficios_*`: `${oficios_camaras_detalle}`
- `parte_*`: `${parte_agraviada}`, `${parte_investigada}`
- `participantes_*`: `${participantes_detalle}`, `${participantes_nombres}`
- `propietarios_*`: `${propietarios_detalle}`
- `respuestas_*`: `${respuestas_camaras_detalle}`
- `unidad_*`: `${unidad_nombre}`

## plantillas/analisis_del_informe.docx

**Generadores relacionados:** `word_informe_selector_vehiculo.php`

**Marcadores presentes (0):**

- Ninguno detectado.

**Disponibles en codigo pero no presentes (0):**

- Ninguno detectado.

## plantillas/citacion_diligencia.docx

**Generadores relacionados:** `citacion_diligencia.php`

**Marcadores presentes (16):**

- `accidente_*`: `${accidente_fecha}`, `${accidente_hora}`, `${accidente_lugar}`, `${accidente_modalidad}`
- `cit_*`: `${cit_en_calidad}`, `${cit_fecha}`, `${cit_hora}`, `${cit_lugar}`, `${cit_motivo}`
- `fiscalia_*`: `${fiscalia_nombre}`
- `persona_*`: `${persona_apellidos}`, `${persona_celular}`, `${persona_domicilio}`, `${persona_edad}`, `${persona_email}`, `${persona_nombres}`

**Disponibles en codigo pero no presentes (0):**

- Ninguno detectado.

## plantillas/informe_atropello.docx

**Generadores relacionados:** `Dato_General_accidente.php`, `word_informe_atropello.php`, `word_informe_atropello_probe.php`, `word_informe_atropello_tplcheck.php`

**Marcadores presentes (116):**

- `acc_*`: `${acc_fecha}`, `${acc_hora}`, `${acc_lugar}`, `${acc_referencia}`, `${acc_registro_sidpol}`
- `com_*`: `${com_fecha}`, `${com_hora}`
- `comisaria_*`: `${comisaria}`
- `cond_*`: `${cond_abog_cel}`, `${cond_abog_coleg}`, `${cond_abog_domproc}`, `${cond_abog_email}`, `${cond_abog_nombre}`, `${cond_abog_reg}`, `${cond_apem}`, `${cond_apep}`, `${cond_celular}`, `${cond_dep_nac}`, `${cond_doc_num}`, `${cond_domicilio}`, `${cond_edad}`, `${cond_email}`, `${cond_estado_civil}`, `${cond_grado_instr}`, `${cond_nacim}`, `${cond_nombres}`
- `consecuencia_*`: `${consecuencia}`
- `distrito_*`: `${distrito}`
- `doc_*`: `${doc_aseguradora_soat}`, `${doc_certificado_revision}`, `${doc_danos_peritaje}`, `${doc_fecha_peritaje}`, `${doc_num_peritaje}`, `${doc_num_propiedad}`, `${doc_num_revision}`, `${doc_num_soat}`, `${doc_partida_propiedad}`, `${doc_titulo_propiedad}`, `${doc_vencimiento_revision}`, `${doc_vencimiento_soat}`, `${doc_vigente_revision}`, `${doc_vigente_soat}`
- `dosaje_*`: `${dosaje_cond_fecha}`, `${dosaje_cond_numero}`, `${dosaje_cond_registro}`, `${dosaje_cond_resultado_cuant}`, `${dosaje_peat_fecha}`, `${dosaje_peat_numero}`, `${dosaje_peat_registro}`, `${dosaje_peat_resultado_cual}`, `${dosaje_peat_resultado_cuant}`, `${dosaje_resultado_cual}`
- `fam_*`: `${fam_abog_nombre}`, `${fam_apem}`, `${fam_apep}`, `${fam_celular}`, `${fam_dep_nac}`, `${fam_domicilio}`, `${fam_email}`, `${fam_grado_instr}`, `${fam_nombres}`, `${fam_parentesco}`
- `fiscal_*`: `${fiscal_nombre}`
- `fiscalia_*`: `${fiscalia}`
- `int_*`: `${int_fecha}`, `${int_hora}`
- `itp_*`: `${itp_configuracion_via1}`, `${itp_fluidez_via1}`, `${itp_iluminacion_via1}`, `${itp_intensidad_via1}`, `${itp_material_via1}`, `${itp_medidas_via1}`, `${itp_observaciones_via1}`, `${itp_ocurrencia_policial}`, `${itp_ordenamiento_via1}`, `${itp_punto_referencia}`, `${itp_señalizacion_via1}`, `${itp_visibilidad_via1}`
- `lc_*`: `${lc_categoria}`, `${lc_clase}`, `${lc_numero}`, `${lc_vigente_desde}`, `${lc_vigente_hasta}`
- `modalidad_*`: `${modalidad}`
- `nro_*`: `${nro_informe_policial}`
- `occiso_*`: `${occiso_cmp_legista}`, `${occiso_fecha_lev}`, `${occiso_fecha_pericial}`, `${occiso_hora_lev}`, `${occiso_legista}`, `${occiso_lesiones_prot}`, `${occiso_lugar_lev}`, `${occiso_num_pericial}`, `${occiso_presuntivo_prot}`
- `peaton_*`: `${peaton_apem}`, `${peaton_apep}`, `${peaton_doc_num}`, `${peaton_domicilio}`, `${peaton_edad}`, `${peaton_estado_civil}`, `${peaton_nacim}`, `${peaton_nombres}`
- `prop_*`: `${prop_nombre}`
- `rml_*`: `${rml_cond_atencion}`, `${rml_cond_incapacidad}`, `${rml_cond_numero}`
- `veh_*`: `${veh_ancho}`, `${veh_anio}`, `${veh_carroceria}`, `${veh_categoria_cod}`, `${veh_color}`, `${veh_largo}`, `${veh_marca}`, `${veh_modelo}`, `${veh_placa}`, `${veh_tipo}`

**Disponibles en codigo pero no presentes (127):**

- `acc_*`: `${acc_estado}`, `${acc_id}`, `${acc_secuencia}`, `${acc_sentido}`, `${acc_sidpol}`
- `cond_*`: `${cond_abog_casilla}`, `${cond_abog_cond}`, `${cond_doc_tipo}`, `${cond_lesion}`, `${cond_nacionalidad}`, `${cond_observ}`, `${cond_sexo}`
- `diligencias_*`: `${diligencias}`
- `doc_*`: `${doc_perito_peritaje}`, `${doc_sede_propiedad}`
- `dosaje_*`: `${dosaje_cond_observ}`, `${dosaje_cond_resultado_cual}`, `${dosaje_fecha}`, `${dosaje_numero}`, `${dosaje_observ}`, `${dosaje_ocu_fecha}`, `${dosaje_ocu_numero}`, `${dosaje_ocu_observ}`, `${dosaje_ocu_registro}`, `${dosaje_ocu_resultado_cual}`, `${dosaje_ocu_resultado_cuant}`, `${dosaje_peat_observ}`, `${dosaje_registro}`, `${dosaje_resultado_cuant}`
- `fam_*`: `${fam_abog_casilla}`, `${fam_abog_cel}`, `${fam_abog_coleg}`, `${fam_abog_cond}`, `${fam_abog_domproc}`, `${fam_abog_email}`, `${fam_abog_reg}`, `${fam_doc_num}`, `${fam_doc_tipo}`, `${fam_observ}`
- `itp_*`: `${itp_configuracion_via2}`, `${itp_descripcion_via1}`, `${itp_descripcion_via2}`, `${itp_evidencia_biologica}`, `${itp_evidencia_fisica}`, `${itp_evidencia_material}`, `${itp_fecha_itp}`, `${itp_fluidez_via2}`, `${itp_forma_via}`, `${itp_hora_itp}`, `${itp_iluminacion_via2}`, `${itp_intensidad_via2}`, `${itp_llegada_lugar}`, `${itp_localizacion_unidades}`, `${itp_material_via2}`, `${itp_medidas_via2}`, `${itp_observaciones_via2}`, `${itp_ordenamiento_via2}`, `${itp_ubicacion_gps}`, `${itp_visibilidad_via2}`
- `lc_*`: `${lc_expedido_por}`, `${lc_restricciones}`
- `occiso_*`: `${occiso_dosaje_prot}`, `${occiso_fecha_alta}`, `${occiso_fecha_protocolo}`, `${occiso_hora_alta}`, `${occiso_hora_pericial}`, `${occiso_hora_protocolo}`, `${occiso_lesiones_lev}`, `${occiso_nosoc_epicrisis}`, `${occiso_num_hist_epic}`, `${occiso_num_protocolo}`, `${occiso_obs_lev}`, `${occiso_obs_pericial}`, `${occiso_posicion_cuerpo}`, `${occiso_presuntivo_lev}`, `${occiso_tam_epic}`, `${occiso_toxico_prot}`
- `ocu_*`: `${ocu_edad}`, `${ocu_nacim}`
- `pas_*`: `${pas_edad}`, `${pas_nacim}`
- `peaton_*`: `${peaton_abog_casilla}`, `${peaton_abog_cel}`, `${peaton_abog_coleg}`, `${peaton_abog_cond}`, `${peaton_abog_domproc}`, `${peaton_abog_email}`, `${peaton_abog_nombre}`, `${peaton_abog_reg}`, `${peaton_dep_nac}`, `${peaton_doc_tipo}`, `${peaton_grado_instr}`, `${peaton_nacionalidad}`, `${peaton_observ}`, `${peaton_sexo}`
- `prop_*`: `${prop_abog_casilla}`, `${prop_abog_cel}`, `${prop_abog_coleg}`, `${prop_abog_cond}`, `${prop_abog_domproc}`, `${prop_abog_email}`, `${prop_abog_nombre}`, `${prop_abog_reg}`, `${prop_doc_num}`, `${prop_doc_tipo}`, `${prop_domicilio}`, `${prop_tipo}`
- `rml_*`: `${rml_atencion}`, `${rml_cond_fecha}`, `${rml_cond_observ}`, `${rml_fecha}`, `${rml_incapacidad}`, `${rml_numero}`, `${rml_observ}`, `${rml_ocu_atencion}`, `${rml_ocu_fecha}`, `${rml_ocu_incapacidad}`, `${rml_ocu_numero}`, `${rml_ocu_observ}`, `${rml_peat_atencion}`, `${rml_peat_fecha}`, `${rml_peat_incapacidad}`, `${rml_peat_numero}`, `${rml_peat_observ}`
- `TEST_*`: `${TEST}`
- `veh_*`: `${veh_alto}`, `${veh_categoria_desc}`

## plantillas/informe_choque_dos_vehiculos.docx

**Generadores relacionados:** `word_informe_choque_dos_vehiculos.php`

**Marcadores presentes (81):**

- `acc_*`: `${acc_fecha}`, `${acc_hora}`, `${acc_lugar}`, `${acc_referencia}`, `${acc_sidpol}`
- `com_*`: `${com_fecha}`, `${com_hora}`
- `comisaria_*`: `${comisaria}`
- `consecuencia_*`: `${consecuencia}`
- `dep_*`: `${dep_nombre}`
- `distrito_*`: `${distrito}`
- `efec1_*`: `${efec1_apem}`, `${efec1_apep}`, `${efec1_celular}`, `${efec1_cip}`, `${efec1_dependencia_policial}`, `${efec1_domicilio}`, `${efec1_edad}`, `${efec1_email}`, `${efec1_estado_civil}`, `${efec1_fecha_nacimiento}`, `${efec1_grado_instruccion}`, `${efec1_grado_policial}`, `${efec1_nacionalidad}`, `${efec1_nombres}`, `${efec1_num_doc}`, `${efec1_observaciones}`, `${efec1_ocupacion}`, `${efec1_rol_funcion}`, `${efec1_sexo}`, `${efec1_tipo_doc}`
- `efec2_*`: `${efec2_apem}`, `${efec2_apep}`, `${efec2_celular}`, `${efec2_cip}`, `${efec2_dependencia_policial}`, `${efec2_domicilio}`, `${efec2_edad}`, `${efec2_email}`, `${efec2_estado_civil}`, `${efec2_fecha_nacimiento}`, `${efec2_grado_instruccion}`, `${efec2_grado_policial}`, `${efec2_nacionalidad}`, `${efec2_nombres}`, `${efec2_num_doc}`, `${efec2_observaciones}`, `${efec2_ocupacion}`, `${efec2_rol_funcion}`, `${efec2_sexo}`, `${efec2_tipo_doc}`
- `fiscal_*`: `${fiscal_nombre}`
- `fiscalia_*`: `${fiscalia}`
- `int_*`: `${int_fecha}`, `${int_hora}`
- `modalidad_*`: `${modalidad}`
- `nro_*`: `${nro_informe_policial}`
- `prov_*`: `${prov_nombre}`
- `ut1_*`: `${ut1_cond_apem}`, `${ut1_cond_apep}`, `${ut1_cond_celular}`, `${ut1_cond_doc_num}`, `${ut1_cond_doc_tipo}`, `${ut1_cond_domicilio}`, `${ut1_cond_edad}`, `${ut1_cond_email}`, `${ut1_cond_grado_instr}`, `${ut1_cond_nombres}`, `${ut1_cond_sexo}`, `${ut1_prop_doc_num}`, `${ut1_prop_doc_tipo}`, `${ut1_prop_nombre}`, `${ut1_veh_anio}`, `${ut1_veh_carroceria}`, `${ut1_veh_categoria_cod}`, `${ut1_veh_categoria_desc}`, `${ut1_veh_color}`, `${ut1_veh_marca}`, `${ut1_veh_modelo}`, `${ut1_veh_placa}`, `${ut1_veh_tipo}`

**Disponibles en codigo pero no presentes (219):**

- `acc_*`: `${acc_registro_sidpol}`
- `diligencias_*`: `${diligencias}`
- `ut1_*`: `${ut1_cond_abog_casilla}`, `${ut1_cond_abog_cel}`, `${ut1_cond_abog_coleg}`, `${ut1_cond_abog_cond}`, `${ut1_cond_abog_domproc}`, `${ut1_cond_abog_email}`, `${ut1_cond_abog_nombre}`, `${ut1_cond_abog_reg}`, `${ut1_cond_dep_nac}`, `${ut1_cond_estado_civil}`, `${ut1_cond_lesion}`, `${ut1_cond_nacim}`, `${ut1_cond_nacionalidad}`, `${ut1_cond_observ}`, `${ut1_doc_aseguradora_soat}`, `${ut1_doc_certificado_revision}`, `${ut1_doc_danos_peritaje}`, `${ut1_doc_fecha_peritaje}`, `${ut1_doc_num_peritaje}`, `${ut1_doc_num_propiedad}`, `${ut1_doc_num_revision}`, `${ut1_doc_num_soat}`, `${ut1_doc_partida_propiedad}`, `${ut1_doc_perito_peritaje}`, `${ut1_doc_sede_propiedad}`, `${ut1_doc_titulo_propiedad}`, `${ut1_doc_vencimiento_revision}`, `${ut1_doc_vencimiento_soat}`, `${ut1_doc_vigente_revision}`, `${ut1_doc_vigente_soat}`, `${ut1_dosaje_fecha}`, `${ut1_dosaje_numero}`, `${ut1_dosaje_observ}`, `${ut1_dosaje_registro}`, `${ut1_dosaje_resultado_cual}`, `${ut1_dosaje_resultado_cuant}`, `${ut1_fam_celular}`, `${ut1_fam_doc}`, `${ut1_fam_domicilio}`, `${ut1_fam_edad}`, `${ut1_fam_email}`, `${ut1_fam_estado_civil}`, `${ut1_fam_fecnac}`, `${ut1_fam_grado_instr}`, `${ut1_fam_nacionalidad}`, `${ut1_fam_nombres}`, `${ut1_fam_ocupacion}`, `${ut1_fam_parentesco}`, `${ut1_fam_sexo}`, `${ut1_lc_categoria}`, `${ut1_lc_clase}`, `${ut1_lc_expedido_por}`, `${ut1_lc_numero}`, `${ut1_lc_restricciones}`, `${ut1_lc_vigente_desde}`, `${ut1_lc_vigente_hasta}`, `${ut1_occ_cmp_legista}`, `${ut1_occ_doc}`, `${ut1_occ_dosaje_prot}`, `${ut1_occ_fecha_lev}`, `${ut1_occ_fecha_pericial}`, `${ut1_occ_fecha_protocolo}`, `${ut1_occ_hora_alta}`, `${ut1_occ_hora_lev}`, `${ut1_occ_hora_pericial}`, `${ut1_occ_hora_protocolo}`, `${ut1_occ_legista}`, `${ut1_occ_lesiones_lev}`, `${ut1_occ_lesiones_prot}`, `${ut1_occ_lugar_lev}`, `${ut1_occ_nombres}`, `${ut1_occ_nosoc_epicrisis}`, `${ut1_occ_num_hist_epic}`, `${ut1_occ_num_pericial}`, `${ut1_occ_num_protocolo}`, `${ut1_occ_obs_lev}`, `${ut1_occ_obs_pericial}`, `${ut1_occ_posicion_cuerpo}`, `${ut1_occ_presuntivo_lev}`, `${ut1_occ_presuntivo_prot}`, `${ut1_occ_toxico_prot}`, `${ut1_occ_trat_epic}`, `${ut1_prop_abog_casilla}`, `${ut1_prop_abog_cel}`, `${ut1_prop_abog_coleg}`, `${ut1_prop_abog_cond}`, `${ut1_prop_abog_domproc}`, `${ut1_prop_abog_email}`, `${ut1_prop_abog_nombre}`, `${ut1_prop_abog_reg}`, `${ut1_prop_domicilio}`, `${ut1_prop_tipo}`, `${ut1_rml_atencion}`, `${ut1_rml_fecha}`, `${ut1_rml_incapacidad}`, `${ut1_rml_numero}`, `${ut1_rml_observ}`
- `ut2_*`: `${ut2_cond_abog_casilla}`, `${ut2_cond_abog_cel}`, `${ut2_cond_abog_coleg}`, `${ut2_cond_abog_cond}`, `${ut2_cond_abog_domproc}`, `${ut2_cond_abog_email}`, `${ut2_cond_abog_nombre}`, `${ut2_cond_abog_reg}`, `${ut2_cond_apem}`, `${ut2_cond_apep}`, `${ut2_cond_celular}`, `${ut2_cond_dep_nac}`, `${ut2_cond_doc_num}`, `${ut2_cond_doc_tipo}`, `${ut2_cond_domicilio}`, `${ut2_cond_edad}`, `${ut2_cond_email}`, `${ut2_cond_estado_civil}`, `${ut2_cond_grado_instr}`, `${ut2_cond_lesion}`, `${ut2_cond_nacim}`, `${ut2_cond_nacionalidad}`, `${ut2_cond_nombres}`, `${ut2_cond_observ}`, `${ut2_cond_sexo}`, `${ut2_doc_aseguradora_soat}`, `${ut2_doc_certificado_revision}`, `${ut2_doc_danos_peritaje}`, `${ut2_doc_fecha_peritaje}`, `${ut2_doc_num_peritaje}`, `${ut2_doc_num_propiedad}`, `${ut2_doc_num_revision}`, `${ut2_doc_num_soat}`, `${ut2_doc_partida_propiedad}`, `${ut2_doc_perito_peritaje}`, `${ut2_doc_sede_propiedad}`, `${ut2_doc_titulo_propiedad}`, `${ut2_doc_vencimiento_revision}`, `${ut2_doc_vencimiento_soat}`, `${ut2_doc_vigente_revision}`, `${ut2_doc_vigente_soat}`, `${ut2_dosaje_fecha}`, `${ut2_dosaje_numero}`, `${ut2_dosaje_observ}`, `${ut2_dosaje_registro}`, `${ut2_dosaje_resultado_cual}`, `${ut2_dosaje_resultado_cuant}`, `${ut2_fam_celular}`, `${ut2_fam_doc}`, `${ut2_fam_domicilio}`, `${ut2_fam_edad}`, `${ut2_fam_email}`, `${ut2_fam_estado_civil}`, `${ut2_fam_fecnac}`, `${ut2_fam_grado_instr}`, `${ut2_fam_nacionalidad}`, `${ut2_fam_nombres}`, `${ut2_fam_ocupacion}`, `${ut2_fam_parentesco}`, `${ut2_fam_sexo}`, `${ut2_lc_categoria}`, `${ut2_lc_clase}`, `${ut2_lc_expedido_por}`, `${ut2_lc_numero}`, `${ut2_lc_restricciones}`, `${ut2_lc_vigente_desde}`, `${ut2_lc_vigente_hasta}`, `${ut2_occ_cmp_legista}`, `${ut2_occ_doc}`, `${ut2_occ_dosaje_prot}`, `${ut2_occ_fecha_lev}`, `${ut2_occ_fecha_pericial}`, `${ut2_occ_fecha_protocolo}`, `${ut2_occ_hora_alta}`, `${ut2_occ_hora_lev}`, `${ut2_occ_hora_pericial}`, `${ut2_occ_hora_protocolo}`, `${ut2_occ_legista}`, `${ut2_occ_lesiones_lev}`, `${ut2_occ_lesiones_prot}`, `${ut2_occ_lugar_lev}`, `${ut2_occ_nombres}`, `${ut2_occ_nosoc_epicrisis}`, `${ut2_occ_num_hist_epic}`, `${ut2_occ_num_pericial}`, `${ut2_occ_num_protocolo}`, `${ut2_occ_obs_lev}`, `${ut2_occ_obs_pericial}`, `${ut2_occ_posicion_cuerpo}`, `${ut2_occ_presuntivo_lev}`, `${ut2_occ_presuntivo_prot}`, `${ut2_occ_toxico_prot}`, `${ut2_occ_trat_epic}`, `${ut2_prop_abog_casilla}`, `${ut2_prop_abog_cel}`, `${ut2_prop_abog_coleg}`, `${ut2_prop_abog_cond}`, `${ut2_prop_abog_domproc}`, `${ut2_prop_abog_email}`, `${ut2_prop_abog_nombre}`, `${ut2_prop_abog_reg}`, `${ut2_prop_doc_num}`, `${ut2_prop_doc_tipo}`, `${ut2_prop_domicilio}`, `${ut2_prop_nombre}`, `${ut2_prop_tipo}`, `${ut2_rml_atencion}`, `${ut2_rml_fecha}`, `${ut2_rml_incapacidad}`, `${ut2_rml_numero}`, `${ut2_rml_observ}`, `${ut2_veh_anio}`, `${ut2_veh_carroceria}`, `${ut2_veh_categoria_cod}`, `${ut2_veh_categoria_desc}`, `${ut2_veh_color}`, `${ut2_veh_marca}`, `${ut2_veh_modelo}`, `${ut2_veh_placa}`, `${ut2_veh_tipo}`

## plantillas/informe_policial.docx

**Generadores relacionados:** No detectado automaticamente

**Marcadores presentes (138):**

- `/FALLECIDO_*`: `${/FALLECIDO}`
- `acc_*`: `${acc_dep}`, `${acc_dist}`, `${acc_fecha_larga}`, `${acc_hora}`, `${acc_lugar}`, `${acc_prov}`, `${acc_referencia}`, `${acc_registro}`, `${acc_secuencia}`, `${acc_sentido}`, `${acc_sidpol}`
- `alertas_*`: `${alertas}`
- `cit_*`: `${cit_doc}`, `${cit_en_cal}`, `${cit_fecha}`, `${cit_hora}`, `${cit_lugar}`, `${cit_motivo}`, `${cit_n}`, `${cit_orden}`, `${cit_persona}`, `${cit_tipo_dil}`
- `cnt_*`: `${cnt_conductores}`, `${cnt_fallecidos}`, `${cnt_ocupantes}`, `${cnt_pasajeros}`, `${cnt_peatones}`, `${cnt_testigos}`
- `comisaria_*`: `${comisaria_nombre}`
- `cond_*`: `${cond_doc}`, `${cond_lesion}`, `${cond_n}`, `${cond_nombre}`, `${cond_placa}`, `${cond_ut}`
- `fal_*`: `${fal_doc}`, `${fal_n}`, `${fal_nombre}`, `${fal_obs}`, `${fal_peri}`, `${fal_proto}`, `${fal_rol}`
- `FALLECIDO_*`: `${FALLECIDO}`
- `fam_*`: `${fam_doc}`, `${fam_n}`, `${fam_nombre}`, `${fam_obs}`, `${fam_parent}`
- `fiscal_*`: `${fiscal_nombre}`
- `fiscalia_*`: `${fiscalia_nombre}`
- `nro_*`: `${nro_informe}`
- `ocup_*`: `${ocup_doc}`, `${ocup_dom}`, `${ocup_etq}`, `${ocup_lc}`, `${ocup_lesion}`, `${ocup_letra}`, `${ocup_n}`, `${ocup_nombre}`, `${ocup_placa}`, `${ocup_rol}`, `${ocup_ut}`
- `of_*`: `${of_asunto}`, `${of_dest}`, `${of_estado}`, `${of_fecha}`, `${of_n}`, `${of_num}`
- `pasa_*`: `${pasa_doc}`, `${pasa_dom}`, `${pasa_etq}`, `${pasa_lc}`, `${pasa_lesion}`, `${pasa_letra}`, `${pasa_n}`, `${pasa_nombre}`, `${pasa_placa}`, `${pasa_rol}`, `${pasa_ut}`
- `peat_*`: `${peat_doc}`, `${peat_dom}`, `${peat_etq}`, `${peat_lc}`, `${peat_lesion}`, `${peat_letra}`, `${peat_n}`, `${peat_nombre}`, `${peat_rol}`
- `pol_*`: `${pol_cip}`, `${pol_dep}`, `${pol_funcion}`, `${pol_grado}`, `${pol_n}`, `${pol_nombre}`, `${pol_obs}`
- `test_*`: `${test_doc}`, `${test_dom}`, `${test_lc}`, `${test_lesion}`, `${test_n}`, `${test_nombre}`, `${test_rol}`
- `v_*`: `${v_anio}`, `${v_carroceria}`, `${v_categoria}`, `${v_clase}`, `${v_color}`, `${v_marca}`, `${v_medidas}`, `${v_modelo}`, `${v_n}`, `${v_per_danos}`, `${v_per_fecha}`, `${v_per_num}`, `${v_per_perito}`, `${v_placa}`, `${v_prop_doc}`, `${v_prop_dom}`, `${v_prop_num}`, `${v_prop_observ}`, `${v_prop_part}`, `${v_prop_persona}`, `${v_prop_sede}`, `${v_prop_tipo}`, `${v_prop_tit}`, `${v_rep_doc}`, `${v_rep_nombre}`, `${v_rev_cert}`, `${v_rev_num}`, `${v_rev_ven}`, `${v_rev_vig}`, `${v_soat_aseg}`, `${v_soat_num}`, `${v_soat_ven}`, `${v_soat_vig}`, `${v_tipo}`, `${v_ut}`

**Disponibles en codigo pero no presentes (0):**

- Ninguno detectado.

## plantillas/informe_policial2.docx

**Generadores relacionados:** `informe_policial2.php`

**Marcadores presentes (252):**

- `..._*`: `${...}`
- `acc_*`: `${acc_direccion}`, `${acc_fecha}`, `${acc_fecha_abrev}`, `${acc_fecha_larga}`, `${acc_hora}`, `${acc_id}`, `${acc_km}`, `${acc_lugar}`, `${acc_referencia}`, `${acc_sidpol}`
- `comisaria_*`: `${comisaria_direccion}`, `${comisaria_distrito}`, `${comisaria_nombre}`
- `consecuencia_*`: `${consecuencia_codigo}`, `${consecuencia_nombre}`
- `dep_*`: `${dep_nombre}`
- `dist_*`: `${dist_nombre}`
- `dosaje_*`: `${dosaje_fecha}`, `${dosaje_numero}`, `${dosaje_resultado}`
- `fam_*`: `${fam_cel}`, `${fam_doc}`, `${fam_nombre}`, `${fam_parentesco}`
- `fiscal_*`: `${fiscal_cargo}`, `${fiscal_nombre}`
- `fiscalia_*`: `${fiscalia_nombre}`
- `lc_*`: `${lc_clase_cat}`, `${lc_fecha_emision}`, `${lc_fecha_venc}`, `${lc_numero}`
- `lista_*`: `${lista_ocu}`, `${lista_pas}`, `${lista_pea}`, `${lista_tes}`
- `modalidad_*`: `${modalidad_codigo}`, `${modalidad_nombre}`
- `occiso_*`: `${occiso_acta_numero}`, `${occiso_doc}`, `${occiso_doc_numero}`, `${occiso_edad}`, `${occiso_lesion}`, `${occiso_nombre}`
- `OCU_*`: `${OCU_A_doc}`, `${OCU_A_edad}`, `${OCU_A_lesion}`, `${OCU_A_nombre}`, `${OCU_B_doc}`, `${OCU_B_edad}`, `${OCU_B_lesion}`, `${OCU_B_nombre}`, `${OCU_C_doc}`, `${OCU_C_edad}`, `${OCU_C_lesion}`, `${OCU_C_nombre}`, `${OCU_D_doc}`, `${OCU_D_edad}`, `${OCU_D_lesion}`, `${OCU_D_nombre}`
- `PAS_*`: `${PAS_A_doc}`, `${PAS_A_edad}`, `${PAS_A_lesion}`, `${PAS_A_nombre}`, `${PAS_B_doc}`, `${PAS_B_edad}`, `${PAS_B_lesion}`, `${PAS_B_nombre}`, `${PAS_C_doc}`, `${PAS_C_edad}`, `${PAS_C_lesion}`, `${PAS_C_nombre}`, `${PAS_D_doc}`, `${PAS_D_edad}`, `${PAS_D_lesion}`, `${PAS_D_nombre}`
- `PEA_*`: `${PEA_A_doc}`, `${PEA_A_edad}`, `${PEA_A_lesion}`, `${PEA_A_nombre}`, `${PEA_B_doc}`, `${PEA_B_edad}`, `${PEA_B_lesion}`, `${PEA_B_nombre}`, `${PEA_C_doc}`, `${PEA_C_edad}`, `${PEA_C_lesion}`, `${PEA_C_nombre}`, `${PEA_D_doc}`, `${PEA_D_edad}`, `${PEA_D_lesion}`, `${PEA_D_nombre}`
- `pol_*`: `${pol_1_cargo}`, `${pol_1_cip}`, `${pol_1_grado}`, `${pol_1_nombre}`, `${pol_2_cargo}`, `${pol_2_cip}`, `${pol_2_grado}`, `${pol_2_nombre}`, `${pol_3_cargo}`, `${pol_3_cip}`, `${pol_3_grado}`, `${pol_3_nombre}`, `${pol_4_cargo}`, `${pol_4_cip}`, `${pol_4_grado}`, `${pol_4_nombre}`
- `prov_*`: `${prov_nombre}`
- `rml_*`: `${rml_fecha}`, `${rml_numero}`, `${rml_resultado}`
- `TES_*`: `${TES_A_doc}`, `${TES_A_edad}`, `${TES_A_lesion}`, `${TES_A_nombre}`, `${TES_B_doc}`, `${TES_B_edad}`, `${TES_B_lesion}`, `${TES_B_nombre}`, `${TES_C_doc}`, `${TES_C_edad}`, `${TES_C_lesion}`, `${TES_C_nombre}`, `${TES_D_doc}`, `${TES_D_edad}`, `${TES_D_lesion}`, `${TES_D_nombre}`
- `UT1_*`: `${UT1_anio}`, `${UT1_carroceria}`, `${UT1_categoria}`, `${UT1_color}`, `${UT1_conductor}`, `${UT1_cond_clase_cat}`, `${UT1_cond_doc}`, `${UT1_cond_edad}`, `${UT1_cond_licencia}`, `${UT1_marca}`, `${UT1_modelo}`, `${UT1_motor}`, `${UT1_peritaje_danos}`, `${UT1_peritaje_fecha}`, `${UT1_peritaje_numero}`, `${UT1_peritaje_perito}`, `${UT1_placa}`, `${UT1_propietario}`, `${UT1_prop_cel}`, `${UT1_prop_dir}`, `${UT1_prop_doc}`, `${UT1_rev_certificadora}`, `${UT1_rev_numero}`, `${UT1_rev_vencimiento}`, `${UT1_rev_vigencia}`, `${UT1_soat_aseguradora}`, `${UT1_soat_numero}`, `${UT1_soat_vencimiento}`, `${UT1_soat_vigencia}`, `${UT1_tipo}`, `${UT1_vin}`
- `UT2_*`: `${UT2_anio}`, `${UT2_carroceria}`, `${UT2_categoria}`, `${UT2_color}`, `${UT2_conductor}`, `${UT2_cond_clase_cat}`, `${UT2_cond_doc}`, `${UT2_cond_edad}`, `${UT2_cond_licencia}`, `${UT2_marca}`, `${UT2_modelo}`, `${UT2_motor}`, `${UT2_peritaje_danos}`, `${UT2_peritaje_fecha}`, `${UT2_peritaje_numero}`, `${UT2_peritaje_perito}`, `${UT2_placa}`, `${UT2_propietario}`, `${UT2_prop_cel}`, `${UT2_prop_dir}`, `${UT2_prop_doc}`, `${UT2_rev_certificadora}`, `${UT2_rev_numero}`, `${UT2_rev_vencimiento}`, `${UT2_rev_vigencia}`, `${UT2_soat_aseguradora}`, `${UT2_soat_numero}`, `${UT2_soat_vencimiento}`, `${UT2_soat_vigencia}`, `${UT2_tipo}`, `${UT2_vin}`
- `UT3_*`: `${UT3_anio}`, `${UT3_carroceria}`, `${UT3_categoria}`, `${UT3_color}`, `${UT3_conductor}`, `${UT3_cond_clase_cat}`, `${UT3_cond_doc}`, `${UT3_cond_edad}`, `${UT3_cond_licencia}`, `${UT3_marca}`, `${UT3_modelo}`, `${UT3_motor}`, `${UT3_peritaje_danos}`, `${UT3_peritaje_fecha}`, `${UT3_peritaje_numero}`, `${UT3_peritaje_perito}`, `${UT3_placa}`, `${UT3_propietario}`, `${UT3_prop_cel}`, `${UT3_prop_dir}`, `${UT3_prop_doc}`, `${UT3_rev_certificadora}`, `${UT3_rev_numero}`, `${UT3_rev_vencimiento}`, `${UT3_rev_vigencia}`, `${UT3_soat_aseguradora}`, `${UT3_soat_numero}`, `${UT3_soat_vencimiento}`, `${UT3_soat_vigencia}`, `${UT3_tipo}`, `${UT3_vin}`
- `UT4_*`: `${UT4_anio}`, `${UT4_carroceria}`, `${UT4_categoria}`, `${UT4_color}`, `${UT4_conductor}`, `${UT4_cond_clase_cat}`, `${UT4_cond_doc}`, `${UT4_cond_edad}`, `${UT4_cond_licencia}`, `${UT4_marca}`, `${UT4_modelo}`, `${UT4_motor}`, `${UT4_peritaje_danos}`, `${UT4_peritaje_fecha}`, `${UT4_peritaje_numero}`, `${UT4_peritaje_perito}`, `${UT4_placa}`, `${UT4_propietario}`, `${UT4_prop_cel}`, `${UT4_prop_dir}`, `${UT4_prop_doc}`, `${UT4_rev_certificadora}`, `${UT4_rev_numero}`, `${UT4_rev_vencimiento}`, `${UT4_rev_vigencia}`, `${UT4_soat_aseguradora}`, `${UT4_soat_numero}`, `${UT4_soat_vencimiento}`, `${UT4_soat_vigencia}`, `${UT4_tipo}`, `${UT4_vin}`

**Disponibles en codigo pero no presentes (0):**

- Ninguno detectado.

## plantillas/manifestacion_efectivopolicial.docx

**Generadores relacionados:** `marcador_manifestacion_policia.php`

**Marcadores presentes (27):**

- `accidente_*`: `${accidente_fecha}`, `${accidente_fecha_abrev}`, `${accidente_fiscalia_id}`, `${accidente_fiscal_id}`, `${accidente_hora}`, `${accidente_lugar}`
- `manifestacion_*`: `${manifestacion_fecha_abrev}`, `${manifestacion_hora_inicio}`
- `policia_*`: `${policia_apellidos}`, `${policia_cip}`, `${policia_dependencia_policial}`, `${policia_edad_al_accidente}`, `${policia_fecha_nacimiento_abrev}`, `${policia_grado_policial}`, `${policia_nombres}`, `${policia_persona_celular}`, `${policia_persona_departamento_nac}`, `${policia_persona_distrito_nac}`, `${policia_persona_domicilio}`, `${policia_persona_email}`, `${policia_persona_estado_civil}`, `${policia_persona_grado_instruccion}`, `${policia_persona_nombres}`, `${policia_persona_nombre_madre}`, `${policia_persona_nombre_padre}`, `${policia_persona_num_doc}`, `${policia_persona_provincia_nac}`

**Disponibles en codigo pero no presentes (0):**

- Ninguno detectado.

## plantillas/manifestacion_familiar.docx

**Generadores relacionados:** `marcador_manifestacion_familiar.php`

**Marcadores presentes (29):**

- `acc_*`: `${acc_fecha_abrev}`, `${acc_fiscalia_nombre}`, `${acc_fiscal_cargo}`, `${acc_fiscal_nombre_completo}`, `${acc_hora}`, `${acc_lugar}`
- `fal_*`: `${fal_apellido_materno}`, `${fal_apellido_paterno}`, `${fal_departamento_nac}`, `${fal_distrito_nac}`, `${fal_fecha_nacimiento}`, `${fal_nombres}`, `${fal_num_doc}`, `${fal_provincia_nac}`
- `fam_*`: `${fam_apellido_materno}`, `${fam_apellido_paterno}`, `${fam_celular}`, `${fam_domicilio}`, `${fam_dom_distrito}`, `${fam_edad}`, `${fam_email}`, `${fam_estado_civil}`, `${fam_grado_instruccion}`, `${fam_nombres}`, `${fam_nombre_madre}`, `${fam_nombre_padre}`, `${fam_ocupacion}`, `${fam_parentesco}`, `${fam_sexo}`

**Disponibles en codigo pero no presentes (58):**

- `acc_*`: `${acc_cod_dep}`, `${acc_cod_dist}`, `${acc_cod_prov}`, `${acc_comisaria_id}`, `${acc_estado}`, `${acc_fecha}`, `${acc_fecha_larga}`, `${acc_fiscalia_correo}`, `${acc_fiscalia_direccion}`, `${acc_fiscalia_id}`, `${acc_fiscalia_telefono}`, `${acc_fiscal_correo}`, `${acc_fiscal_dni}`, `${acc_fiscal_id}`, `${acc_fiscal_telefono}`, `${acc_nro_informe_policial}`, `${acc_referencia}`, `${acc_registro_sidpol}`, `${acc_secuencia}`, `${acc_sentido}`, `${acc_sidpol}`
- `accidente_*`: `${accidente_id}`
- `fal_*`: `${fal_celular}`, `${fal_doc}`, `${fal_domicilio}`, `${fal_dom_departamento}`, `${fal_dom_distrito}`, `${fal_dom_provincia}`, `${fal_edad}`, `${fal_email}`, `${fal_estado_civil}`, `${fal_grado_instruccion}`, `${fal_nacionalidad}`, `${fal_nombre_completo}`, `${fal_observaciones}`, `${fal_ocupacion}`, `${fal_orden_persona}`, `${fal_sexo}`, `${fal_tipo_doc}`
- `fallecido_*`: `${fallecido_inv_id}`
- `fam_*`: `${fam_doc}`, `${fam_dom_departamento}`, `${fam_dom_provincia}`, `${fam_fecha_nacimiento}`, `${fam_id}`, `${fam_nacionalidad}`, `${fam_nombre_completo}`, `${fam_num_doc}`, `${fam_observaciones}`, `${fam_tipo_doc}`
- `familiar_*`: `${familiar_persona_id}`
- `manif_*`: `${manif_cargo_funcionario}`, `${manif_fecha}`, `${manif_fecha_larga}`, `${manif_funcionario}`, `${manif_hora}`, `${manif_lugar_toma}`, `${manif_observaciones_finales}`

## plantillas/manifestacion_investigado.docx

**Generadores relacionados:** `marcador_manifestacion_investigado.php`, `marcador_manifestacion_propietario.php`

**Marcadores presentes (34):**

- `ABOGADO_*`: `${ABOGADO_CASILLA_ELECTRONICA}`, `${ABOGADO_CELULAR}`, `${ABOGADO_COLEGIATURA}`, `${ABOGADO_DOMICILIO_PROCESAL}`, `${ABOGADO_EMAIL}`, `${ABOGADO_NOMBRE_COMPLETO}`, `${ABOGADO_REGISTRO}`
- `ACCIDENTE_*`: `${ACCIDENTE_FECHA_ABREV}`, `${ACCIDENTE_HORA}`, `${ACCIDENTE_LUGAR}`
- `CONDUCTOR_*`: `${CONDUCTOR_EDAD_AL_ACCIDENTE}`, `${CONDUCTOR_FECHA_NAC_ABREV}`, `${CONDUCTOR_NOMBRE_COMPLETO}`, `${CONDUCTOR_VEH_PLACA}`
- `FISCAL_*`: `${FISCAL_CARGO}`, `${FISCAL_NOMBRE_COMPLETO}`
- `FISCALIA_*`: `${FISCALIA_NOMBRE}`
- `HOY_*`: `${HOY_FECHA}`
- `PERSONA_*`: `${PERSONA_APELLIDO_MATERNO}`, `${PERSONA_APELLIDO_PATERNO}`, `${PERSONA_CELULAR}`, `${PERSONA_DEPARTAMENTO_NAC}`, `${PERSONA_DISTRITO_NAC}`, `${PERSONA_DOMICILIO}`, `${PERSONA_EMAIL}`, `${PERSONA_ESTADO_CIVIL}`, `${PERSONA_GRADO_INSTRUCCION}`, `${PERSONA_NOMBRES}`, `${PERSONA_NOMBRE_MADRE}`, `${PERSONA_NOMBRE_PADRE}`, `${PERSONA_NUM_DOC}`, `${PERSONA_OCUPACION}`, `${PERSONA_PROVINCIA_NAC}`
- `VEH_*`: `${VEH_PLACA}`

**Disponibles en codigo pero no presentes (0):**

- Ninguno detectado.

## plantillas/manifestacion_propietario.docx

**Generadores relacionados:** `marcador_manifestacion_propietario.php`

**Marcadores presentes (31):**

- `ABOGADO_*`: `${ABOGADO_CASILLA_ELECTRONICA}`, `${ABOGADO_CELULAR}`, `${ABOGADO_COLEGIATURA}`, `${ABOGADO_DOMICILIO_PROCESAL}`, `${ABOGADO_EMAIL}`, `${ABOGADO_NOMBRE_COMPLETO}`, `${ABOGADO_REGISTRO}`
- `ACCIDENTE_*`: `${ACCIDENTE_FECHA_ABREV}`, `${ACCIDENTE_HORA}`
- `CONDUCTOR_*`: `${CONDUCTOR_EDAD_AL_ACCIDENTE}`, `${CONDUCTOR_FECHA_NAC_ABREV}`, `${CONDUCTOR_NOMBRE_COMPLETO}`
- `FISCAL_*`: `${FISCAL_CARGO}`, `${FISCAL_NOMBRE_COMPLETO}`
- `FISCALIA_*`: `${FISCALIA_NOMBRE}`
- `HOY_*`: `${HOY_FECHA}`
- `PERSONA_*`: `${PERSONA_APELLIDO_MATERNO}`, `${PERSONA_APELLIDO_PATERNO}`, `${PERSONA_CELULAR}`, `${PERSONA_DEPARTAMENTO_NAC}`, `${PERSONA_DISTRITO_NAC}`, `${PERSONA_DOMICILIO}`, `${PERSONA_EMAIL}`, `${PERSONA_ESTADO_CIVIL}`, `${PERSONA_GRADO_INSTRUCCION}`, `${PERSONA_NOMBRES}`, `${PERSONA_NOMBRE_MADRE}`, `${PERSONA_NOMBRE_PADRE}`, `${PERSONA_NUM_DOC}`, `${PERSONA_OCUPACION}`, `${PERSONA_PROVINCIA_NAC}`

**Disponibles en codigo pero no presentes (0):**

- Ninguno detectado.

## plantillas/marcadores_informe_datos_generales.docx

**Generadores relacionados:** `word_informe_datos_generales_marcadores.php`

**Marcadores presentes (50):**

- `acc_*`: `${acc_clase_accidente}`, `${acc_clase_via_zona}`, `${acc_consecuencia}`, `${acc_fecha_hora_accidente}`, `${acc_fecha_hora_comunicacion}`, `${acc_fecha_hora_intervencion}`, `${acc_fiscalia}`, `${acc_fiscal_cargo}`, `${acc_lugar_jurisdiccion_policial}`, `${acc_nro_informe_policial}`, `${acc_registro_sidpol}`, `${acc_secuencia}`, `${acc_sentido}`, `${acc_sidpol}`, `${acc_unidades_participantes}`
- `accidente_*`: `${accidente_id}`
- `itp_*`: `${itp_evidencia_biologica}`, `${itp_evidencia_fisica}`, `${itp_evidencia_material}`, `${itp_fecha}`, `${itp_forma_via}`, `${itp_hora}`, `${itp_id}`, `${itp_llegada_lugar}`, `${itp_localizacion_unidades}`, `${itp_ocurrencia_policial}`, `${itp_punto_referencia}`, `${itp_ubicacion_gps}`, `${itp_via1_configuracion}`, `${itp_via1_descripcion}`, `${itp_via1_fluidez}`, `${itp_via1_iluminacion}`, `${itp_via1_intensidad}`, `${itp_via1_material}`, `${itp_via1_medidas}`, `${itp_via1_observaciones}`, `${itp_via1_ordenamiento}`, `${itp_via1_senializacion}`, `${itp_via1_visibilidad}`, `${itp_via2_configuracion}`, `${itp_via2_descripcion}`, `${itp_via2_fluidez}`, `${itp_via2_iluminacion}`, `${itp_via2_intensidad}`, `${itp_via2_material}`, `${itp_via2_medidas}`, `${itp_via2_observaciones}`, `${itp_via2_ordenamiento}`, `${itp_via2_senializacion}`, `${itp_via2_visibilidad}`

**Disponibles en codigo pero no presentes (0):**

- Ninguno detectado.

## plantillas/notificacion_abogado.backup_pre_fix.docx

**Generadores relacionados:** No detectado automaticamente

**Marcadores presentes (0):**

- Ninguno detectado.

**Disponibles en codigo pero no presentes (0):**

- Ninguno detectado.

## plantillas/notificacion_abogado.backup_pre_normalize.docx

**Generadores relacionados:** No detectado automaticamente

**Marcadores presentes (0):**

- Ninguno detectado.

**Disponibles en codigo pero no presentes (0):**

- Ninguno detectado.

## plantillas/notificacion_abogado.docx

**Generadores relacionados:** `marcador_abogado.php`, `tmp_fix_template_utf8.php`, `tmp_test_multi.php`

**Marcadores presentes (19):**

- `/citaciones_*`: `${/citaciones}`
- `abogado_*`: `${abogado_celular}`, `${abogado_colegiatura}`, `${abogado_de_quien}`, `${abogado_domicilio_procesal}`, `${abogado_email}`, `${abogado_nombre_completo}`, `${abogado_registro}`
- `accidente_*`: `${accidente_fecha}`, `${accidente_hora}`, `${accidente_lugar}`, `${accidente_modalidad}`
- `citacion_*`: `${citacion_detalle_persona}`, `${citacion_fecha}`, `${citacion_hora}`, `${citacion_lugar}`, `${citacion_motivo}`
- `citaciones_*`: `${citaciones}`
- `fiscalia_*`: `${fiscalia_nombre}`

**Disponibles en codigo pero no presentes (1):**

- `fecha_*`: `${fecha_notificacion}`

## plantillas/oficio_camaras.docx

**Generadores relacionados:** `ia_generar_oficio.php`, `word_oficio_camaras.php`

**Marcadores presentes (15):**

- `accidente_*`: `${accidente_consecuencia}`, `${accidente_fecha_abrev}`, `${accidente_lugar}`, `${accidente_modalidad}`, `${accidente_referencia}`
- `entidad_*`: `${entidad_nombre}`
- `fiscalia_*`: `${fiscalia_nombre}`
- `nombre_*`: `${nombre_oficial_ano}`
- `oficio_*`: `${oficio_anio}`, `${oficio_fecha}`, `${oficio_grado_cargo}`, `${oficio_numero}`, `${oficio_persona_destino}`, `${oficio_rango_desde}`, `${oficio_rango_hasta}`

**Disponibles en codigo pero no presentes (29):**

- `accidente_*`: `${accidente_fecha}`, `${accidente_hora}`, `${accidente_sentido}`, `${accidente_sidpol}`
- `ASUNTO_*`: `${ASUNTO}`
- `asunto_*`: `${asunto_detalle}`, `${asunto_nombre}`
- `comisaria_*`: `${comisaria_nombre}`
- `CUERPO_*`: `${CUERPO}`
- `DESTINATARIO_*`: `${DESTINATARIO}`
- `entidad_*`: `${entidad_linea}`
- `ENTIDAD_*`: `${ENTIDAD_NOMBRE}`, `${ENTIDAD_SIGLAS}`
- `FECHA_*`: `${FECHA_ACTUAL}`
- `FIRMA_*`: `${FIRMA}`
- `grado_*`: `${grado_cargo_abrev}`, `${grado_cargo_nombre}`, `${grado_cargo_tipo}`
- `NUM_*`: `${NUM_OFICIO}`
- `oficio_*`: `${oficio_entidad_linea}`, `${oficio_entidad_nombre}`, `${oficio_entidad_siglas}`, `${oficio_fecha_abrev}`, `${oficio_motivo}`, `${oficio_rango_camaras}`, `${oficio_referencia}`, `${oficio_subentidad_nombre}`, `${oficio_subentidad_tipo}`
- `REFERENCIA_*`: `${REFERENCIA}`

## plantillas/oficio_informacion_certificado_uper.docx

**Generadores relacionados:** `word_oficio_informacion_certificado_uper.php`

**Marcadores presentes (13):**

- `accidente_*`: `${accidente_fecha_abrev}`, `${accidente_hora}`, `${accidente_lugar_completo}`, `${accidente_modalidades}`
- `comisaria_*`: `${comisaria_nombre}`
- `nombre_*`: `${nombre_oficial_ano}`
- `oficio_*`: `${oficio_anio}`, `${oficio_entidad_nombre}`, `${oficio_grado_cargo}`, `${oficio_numero}`, `${oficio_persona_destino}`
- `veh_*`: `${veh_placa}`, `${veh_tipo}`

**Disponibles en codigo pero no presentes (71):**

- `accidente_*`: `${accidente_cod_dep}`, `${accidente_cod_dist}`, `${accidente_cod_prov}`, `${accidente_comunicacion_carpeta_nro}`, `${accidente_comunicacion_decreto}`, `${accidente_comunicacion_oficio}`, `${accidente_comunicante_nombre}`, `${accidente_comunicante_telefono}`, `${accidente_consecuencia}`, `${accidente_consecuencias}`, `${accidente_coordenadas}`, `${accidente_departamento}`, `${accidente_distrito}`, `${accidente_estado}`, `${accidente_fecha}`, `${accidente_fecha_comunicacion}`, `${accidente_fecha_comunicacion_abrev}`, `${accidente_fecha_intervencion}`, `${accidente_fecha_intervencion_abrev}`, `${accidente_folder}`, `${accidente_hora_comunicacion}`, `${accidente_hora_intervencion}`, `${accidente_id}`, `${accidente_latitud}`, `${accidente_longitud}`, `${accidente_lugar}`, `${accidente_modalidad}`, `${accidente_nro_informe_policial}`, `${accidente_prioridad}`, `${accidente_provincia}`, `${accidente_referencia}`, `${accidente_resumen}`, `${accidente_secuencia}`, `${accidente_sentido}`, `${accidente_sidpol}`, `${accidente_tipo_registro}`, `${accidente_ubicacion}`
- `asunto_*`: `${asunto_detalle}`, `${asunto_nombre}`
- `fiscalia_*`: `${fiscalia_nombre}`
- `grado_*`: `${grado_cargo_abrev}`, `${grado_cargo_nombre}`, `${grado_cargo_tipo}`
- `oficio_*`: `${oficio_entidad_linea}`, `${oficio_entidad_siglas}`, `${oficio_fecha}`, `${oficio_fecha_abrev}`, `${oficio_motivo}`, `${oficio_referencia}`, `${oficio_subentidad_nombre}`, `${oficio_subentidad_tipo}`
- `veh_*`: `${veh_alto_mm}`, `${veh_ancho_mm}`, `${veh_anio}`, `${veh_carroceria}`, `${veh_carroceria_descripcion}`, `${veh_categoria}`, `${veh_categoria_descripcion}`, `${veh_color}`, `${veh_largo_mm}`, `${veh_marca}`, `${veh_medidas}`, `${veh_modelo}`, `${veh_notas}`, `${veh_nro_motor}`, `${veh_observaciones}`, `${veh_orden}`, `${veh_serie_vin}`, `${veh_tipo_codigo}`, `${veh_tipo_descripcion}`, `${veh_tipo_participacion}`

## plantillas/oficio_peritaje.docx

**Generadores relacionados:** `oficio_peritaje.php`, `oficio_peritaje_diag.php`

**Marcadores presentes (21):**

- `accidente_*`: `${accidente_distrito}`, `${accidente_fecha}`, `${accidente_hora}`, `${accidente_lugar}`, `${accidente_modalidades}`
- `conductor_*`: `${conductor_nombre}`
- `destino_*`: `${destino_entidad}`, `${destino_grado_cargo}`, `${destino_persona}`, `${destino_subentidad}`
- `nombre_*`: `${nombre_ano}`
- `oficio_*`: `${oficio_fecha}`, `${oficio_titulo}`
- `propietario_*`: `${propietario_nombre}`
- `veh_*`: `${veh_anio}`, `${veh_carroceria}`, `${veh_categoria}`, `${veh_color}`, `${veh_marca}`, `${veh_modelo}`, `${veh_placa}`

**Disponibles en codigo pero no presentes (27):**

- `accidente_*`: `${accidente_referencia}`, `${accidente_resumen}`, `${accidente_sentido}`, `${accidente_sidpol}`
- `carta_*`: `${carta_lugar}`
- `cierre_*`: `${cierre_cordial}`
- `conductor_*`: `${conductor_doc}`
- `despedida_*`: `${despedida}`
- `firma_*`: `${firma_cargo}`, `${firma_grado}`, `${firma_nombre}`, `${firma_sigla}`
- `oficio_*`: `${oficio_anio}`, `${oficio_asunto}`, `${oficio_motivo}`, `${oficio_numero}`, `${oficio_referencia}`
- `pie_*`: `${pie_direccion}`, `${pie_email}`, `${pie_telefono}`, `${pie_unidad}`
- `propietario_*`: `${propietario_doc}`
- `siglas_*`: `${siglas_tramite}`
- `solicitud_*`: `${solicitud_texto}`
- `veh_*`: `${veh_clase}`, `${veh_orden}`, `${veh_tipo}`

## plantillas/oficio_protocolo.docx

**Generadores relacionados:** `oficio_protocolo.php`

**Marcadores presentes (12):**

- `entidad_*`: `${entidad_nombre}`
- `fallecido_*`: `${fallecido_apellidos}`, `${fallecido_edad}`, `${fallecido_nombres}`
- `fiscalia_*`: `${fiscalia_nombre}`
- `nombre_*`: `${nombre_oficial_ano}`
- `numero_*`: `${numero_pericial}`
- `oficio_*`: `${oficio_anio}`, `${oficio_fecha}`, `${oficio_grado_cargo}`, `${oficio_numero}`, `${oficio_persona_destino}`

**Disponibles en codigo pero no presentes (26):**

- `accidente_*`: `${accidente_fecha}`, `${accidente_fecha_abrev}`, `${accidente_lugar}`, `${accidente_referencia}`, `${accidente_sentido}`, `${accidente_sidpol}`
- `asunto_*`: `${asunto_detalle}`, `${asunto_nombre}`
- `comisaria_*`: `${comisaria_nombre}`
- `entidad_*`: `${entidad_siglas}`
- `fallecido_*`: `${fallecido_documento}`, `${fallecido_domicilio}`, `${fallecido_lesion}`, `${fallecido_nombre_completo}`, `${fallecido_rol}`
- `grado_*`: `${grado_cargo_abrev}`, `${grado_cargo_nombre}`, `${grado_cargo_tipo}`
- `oficio_*`: `${oficio_entidad_linea}`, `${oficio_entidad_nombre}`, `${oficio_entidad_siglas}`, `${oficio_fecha_abrev}`, `${oficio_motivo}`, `${oficio_referencia}`, `${oficio_subentidad_nombre}`, `${oficio_subentidad_tipo}`

## plantillas/oficio_remitir_diligencia.docx

**Generadores relacionados:** `oficio_remitir_diligencia.php`

**Marcadores presentes (13):**

- `accidente_*`: `${accidente_fecha_abrev}`, `${accidente_hora}`, `${accidente_lugar}`, `${accidente_modalidad}`
- `anio_*`: `${anio}`
- `comisaria_*`: `${comisaria_nombre}`
- `entidad_*`: `${entidad_destino}`
- `grado_*`: `${grado_cargo}`
- `motivo_*`: `${motivo}`
- `nombre_*`: `${nombre_oficial_ano}`
- `numero_*`: `${numero}`
- `persona_*`: `${persona_destino}`
- `referencia_*`: `${referencia_texto}`

**Disponibles en codigo pero no presentes (0):**

- Ninguno detectado.

## plantillas/oficio_sunarp_historial_transferencias.docx

**Generadores relacionados:** `word_oficio_sunarp_historial_transferencias.php`

**Marcadores presentes (10):**

- `accidente_*`: `${accidente_consecuencia}`, `${accidente_fecha_abrev}`, `${accidente_hora}`, `${accidente_lugar}`, `${accidente_modalidades}`
- `comisaria_*`: `${comisaria_nombre}`
- `nombre_*`: `${nombre_oficial_ano}`
- `oficio_*`: `${oficio_anio}`, `${oficio_numero}`
- `veh_*`: `${veh_placa}`

**Disponibles en codigo pero no presentes (17):**

- `accidente_*`: `${accidente_sidpol}`
- `agraviados_*`: `${agraviados_resumen}`
- `conductor_*`: `${conductor_edad}`, `${conductor_nombre}`
- `firma_*`: `${firma_cargo}`, `${firma_grado}`, `${firma_nombre}`
- `investigador_*`: `${investigador_celular}`, `${investigador_grado}`, `${investigador_nombre}`
- `oficio_*`: `${oficio_entidad_nombre}`, `${oficio_entidad_siglas}`, `${oficio_fecha}`
- `veh_*`: `${veh_color}`, `${veh_marca}`, `${veh_modelo}`, `${veh_orden}`

## plantillas/resultado_dosaje.docx

**Generadores relacionados:** `oficio_resultado_dosaje.php`

**Marcadores presentes (10):**

- `accidente_*`: `${accidente_fecha_abrev}`, `${accidente_lugar}`, `${accidente_modalidad}`
- `anio_*`: `${anio}`
- `entidad_*`: `${entidad_destino}`
- `fiscalia_*`: `${fiscalia_nombre}`
- `grado_*`: `${grado_cargo}`
- `nombre_*`: `${nombre_oficial_ano}`
- `numero_*`: `${numero}`
- `referencia_*`: `${referencia_texto}`

**Disponibles en codigo pero no presentes (21):**

- `accidente_*`: `${accidente_fecha}`, `${accidente_hora}`, `${accidente_referencia}`
- `asunto_*`: `${asunto}`
- `carpeta_*`: `${carpeta}`
- `comisaria_*`: `${comisaria_nombre}`
- `fecha_*`: `${fecha_emision}`
- `fiscal_*`: `${fiscal_cargo}`, `${fiscal_correo}`, `${fiscal_dni}`, `${fiscal_nombre}`, `${fiscal_telefono}`
- `fiscalia_*`: `${fiscalia_correo}`, `${fiscalia_direccion}`, `${fiscalia_telefono}`
- `motivo_*`: `${motivo}`
- `oficial_*`: `${oficial_ano}`
- `person_*`: `${person_list}`
- `persona_*`: `${persona_destino}`
- `subentidad_*`: `${subentidad_destino}`
- `vehiculo_*`: `${vehiculo_list}`

## plantillas/word_informe_combinado_vehiculo.docx

**Generadores relacionados:** `word_informe_combinado_vehiculo.php`

**Marcadores presentes (109):**

- `acc_*`: `${acc_fiscal}`, `${acc_fiscalia}`, `${acc_lugar}`
- `v1_*`: `${v1_cond_abog_celular}`, `${v1_cond_abog_colegiatura}`, `${v1_cond_abog_domicilio_procesal}`, `${v1_cond_abog_email}`, `${v1_cond_abog_nombre}`, `${v1_cond_abog_registro}`, `${v1_cond_doc_num}`, `${v1_cond_doc_tipo}`, `${v1_cond_domicilio}`, `${v1_cond_edad_accidente}`, `${v1_cond_estado_civil}`, `${v1_cond_fecha_nacimiento}`, `${v1_cond_grado_instruccion}`, `${v1_cond_nacimiento}`, `${v1_cond_nombre}`, `${v1_docv_aseguradora_soat}`, `${v1_docv_certificadora_revision}`, `${v1_docv_danos_peritaje}`, `${v1_docv_fecha_peritaje}`, `${v1_docv_num_peritaje}`, `${v1_docv_num_propiedad}`, `${v1_docv_num_revision}`, `${v1_docv_num_soat}`, `${v1_docv_otros_peritaje}`, `${v1_docv_partida_propiedad}`, `${v1_docv_planta_motriz_peritaje}`, `${v1_docv_sistema_direccion_peritaje}`, `${v1_docv_sistema_electrico_peritaje}`, `${v1_docv_sistema_frenos_peritaje}`, `${v1_docv_sistema_suspension_peritaje}`, `${v1_docv_sistema_transmision_peritaje}`, `${v1_docv_titulo_propiedad}`, `${v1_docv_vencimiento_revision}`, `${v1_docv_vencimiento_soat}`, `${v1_docv_vigente_revision}`, `${v1_docv_vigente_soat}`, `${v1_dosaje_fecha}`, `${v1_dosaje_lectura_cuant}`, `${v1_dosaje_numero}`, `${v1_dosaje_registro}`, `${v1_dosaje_resultado_cual}`, `${v1_dosaje_resultado_cuant}`, `${v1_lc_categoria}`, `${v1_lc_clase}`, `${v1_lc_numero}`, `${v1_lc_vigente_desde}`, `${v1_lc_vigente_hasta}`, `${v1_man_fecha}`, `${v1_man_hora_inicio}`, `${v1_prop_abog_colegiatura}`, `${v1_prop_abog_nombre}`, `${v1_prop_abog_registro}`, `${v1_prop_man_fecha}`, `${v1_prop_man_hora_inicio}`, `${v1_prop_nat_nombre}`, `${v1_prop_nombre}`, `${v1_prop_rep_celular}`, `${v1_prop_rep_doc}`, `${v1_prop_rep_domicilio}`, `${v1_prop_rep_email}`, `${v1_prop_ruc}`, `${v1_rml_atencion}`, `${v1_rml_incapacidad}`, `${v1_rml_numero}`, `${v1_veh_alto}`, `${v1_veh_ancho}`, `${v1_veh_anio}`, `${v1_veh_carroceria}`, `${v1_veh_categoria}`, `${v1_veh_color}`, `${v1_veh_largo}`, `${v1_veh_modelo}`, `${v1_veh_placa}`, `${v1_veh_tipo}`
- `v2_*`: `${v2_docv_aseguradora_soat}`, `${v2_docv_certificadora_revision}`, `${v2_docv_danos_peritaje}`, `${v2_docv_fecha_peritaje}`, `${v2_docv_num_peritaje}`, `${v2_docv_num_propiedad}`, `${v2_docv_num_revision}`, `${v2_docv_num_soat}`, `${v2_docv_otros_peritaje}`, `${v2_docv_partida_propiedad}`, `${v2_docv_planta_motriz_peritaje}`, `${v2_docv_sistema_direccion_peritaje}`, `${v2_docv_sistema_electrico_peritaje}`, `${v2_docv_sistema_frenos_peritaje}`, `${v2_docv_sistema_suspension_peritaje}`, `${v2_docv_sistema_transmision_peritaje}`, `${v2_docv_titulo_propiedad}`, `${v2_docv_vencimiento_revision}`, `${v2_docv_vencimiento_soat}`, `${v2_docv_vigente_revision}`, `${v2_docv_vigente_soat}`, `${v2_prop_nombre}`, `${v2_veh_alto}`, `${v2_veh_ancho}`, `${v2_veh_anio}`, `${v2_veh_carroceria}`, `${v2_veh_categoria}`, `${v2_veh_color}`, `${v2_veh_largo}`, `${v2_veh_modelo}`, `${v2_veh_placa}`, `${v2_veh_tipo}`

**Disponibles en codigo pero no presentes (0):**

- Ninguno detectado.

## plantillas/word_informe_combinado_vehiculo_marcadores.docx

**Generadores relacionados:** No detectado automaticamente

**Marcadores presentes (245):**

- `acc_*`: `${acc_comisaria}`, `${acc_consecuencia}`, `${acc_departamento}`, `${acc_distrito}`, `${acc_estado}`, `${acc_fecha}`, `${acc_fecha_comunicacion}`, `${acc_fecha_intervencion}`, `${acc_fiscal}`, `${acc_fiscalia}`, `${acc_hora}`, `${acc_hora_comunicacion}`, `${acc_hora_intervencion}`, `${acc_id}`, `${acc_lugar}`, `${acc_modalidad}`, `${acc_nro_informe_policial}`, `${acc_provincia}`, `${acc_referencia}`, `${acc_registro_sidpol}`, `${acc_secuencia}`, `${acc_sentido}`, `${acc_sidpol}`
- `cond_*`: `${cond_nombre}`
- `efec1_*`: `${efec1_man_fecha}`, `${efec1_man_hora_inicio}`, `${efec1_man_hora_termino}`, `${efec1_man_modalidad}`, `${efec1_man_resumen}`
- `efec2_*`: `${efec2_man_fecha}`, `${efec2_man_hora_inicio}`, `${efec2_man_hora_termino}`, `${efec2_man_modalidad}`, `${efec2_man_resumen}`
- `efec3_*`: `${efec3_man_fecha}`, `${efec3_man_hora_inicio}`, `${efec3_man_hora_termino}`, `${efec3_man_modalidad}`, `${efec3_man_resumen}`
- `efec4_*`: `${efec4_man_fecha}`, `${efec4_man_hora_inicio}`, `${efec4_man_hora_termino}`, `${efec4_man_modalidad}`, `${efec4_man_resumen}`
- `efec5_*`: `${efec5_man_fecha}`, `${efec5_man_hora_inicio}`, `${efec5_man_hora_termino}`, `${efec5_man_modalidad}`, `${efec5_man_resumen}`
- `generado_*`: `${generado_fecha}`
- `lc_*`: `${lc_numero}`
- `policia1_*`: `${policia1_man_fecha}`, `${policia1_man_hora_inicio}`, `${policia1_man_hora_termino}`, `${policia1_man_modalidad}`, `${policia1_man_resumen}`
- `policia2_*`: `${policia2_man_fecha}`, `${policia2_man_hora_inicio}`, `${policia2_man_hora_termino}`, `${policia2_man_modalidad}`, `${policia2_man_resumen}`
- `policia3_*`: `${policia3_man_fecha}`, `${policia3_man_hora_inicio}`, `${policia3_man_hora_termino}`, `${policia3_man_modalidad}`, `${policia3_man_resumen}`
- `policia4_*`: `${policia4_man_fecha}`, `${policia4_man_hora_inicio}`, `${policia4_man_hora_termino}`, `${policia4_man_modalidad}`, `${policia4_man_resumen}`
- `policia5_*`: `${policia5_man_fecha}`, `${policia5_man_hora_inicio}`, `${policia5_man_hora_termino}`, `${policia5_man_modalidad}`, `${policia5_man_resumen}`
- `PREFIX_*`: `${PREFIX_cond_abog_casilla}`, `${PREFIX_cond_abog_celular}`, `${PREFIX_cond_abog_colegiatura}`, `${PREFIX_cond_abog_condicion}`, `${PREFIX_cond_abog_domicilio_procesal}`, `${PREFIX_cond_abog_email}`, `${PREFIX_cond_abog_nombre}`, `${PREFIX_cond_abog_registro}`, `${PREFIX_cond_celular}`, `${PREFIX_cond_doc}`, `${PREFIX_cond_doc_num}`, `${PREFIX_cond_doc_tipo}`, `${PREFIX_cond_domicilio}`, `${PREFIX_cond_domicilio_ubigeo}`, `${PREFIX_cond_edad}`, `${PREFIX_cond_edad_accidente}`, `${PREFIX_cond_email}`, `${PREFIX_cond_estado_civil}`, `${PREFIX_cond_fecha_nacimiento}`, `${PREFIX_cond_grado_instruccion}`, `${PREFIX_cond_lesion}`, `${PREFIX_cond_madre}`, `${PREFIX_cond_man_fecha}`, `${PREFIX_cond_man_hora_inicio}`, `${PREFIX_cond_man_hora_termino}`, `${PREFIX_cond_man_modalidad}`, `${PREFIX_cond_man_resumen}`, `${PREFIX_cond_nacimiento}`, `${PREFIX_cond_nacionalidad}`, `${PREFIX_cond_nombre}`, `${PREFIX_cond_observaciones}`, `${PREFIX_cond_ocupacion}`, `${PREFIX_cond_padre}`, `${PREFIX_cond_rol}`, `${PREFIX_cond_sexo}`, `${PREFIX_docv_aseguradora_soat}`, `${PREFIX_docv_certificadora_revision}`, `${PREFIX_docv_danos_peritaje}`, `${PREFIX_docv_fecha_peritaje}`, `${PREFIX_docv_num_peritaje}`, `${PREFIX_docv_num_propiedad}`, `${PREFIX_docv_num_revision}`, `${PREFIX_docv_num_soat}`, `${PREFIX_docv_otros_peritaje}`, `${PREFIX_docv_partida_propiedad}`, `${PREFIX_docv_perito_peritaje}`, `${PREFIX_docv_planta_motriz_peritaje}`, `${PREFIX_docv_sede_propiedad}`, `${PREFIX_docv_sistema_direccion_peritaje}`, `${PREFIX_docv_sistema_electrico_peritaje}`, `${PREFIX_docv_sistema_frenos_peritaje}`, `${PREFIX_docv_sistema_suspension_peritaje}`, `${PREFIX_docv_sistema_transmision_peritaje}`, `${PREFIX_docv_titulo_propiedad}`, `${PREFIX_docv_vencimiento_revision}`, `${PREFIX_docv_vencimiento_soat}`, `${PREFIX_docv_vigente_revision}`, `${PREFIX_docv_vigente_soat}`, `${PREFIX_dosaje_fecha}`, `${PREFIX_dosaje_hora}`, `${PREFIX_dosaje_lectura_cuant}`, `${PREFIX_dosaje_numero}`, `${PREFIX_dosaje_observaciones}`, `${PREFIX_dosaje_registro}`, `${PREFIX_dosaje_resultado_cual}`, `${PREFIX_dosaje_resultado_cuant}`, `${PREFIX_lc_categoria}`, `${PREFIX_lc_clase}`, `${PREFIX_lc_expedido_por}`, `${PREFIX_lc_numero}`, `${PREFIX_lc_restricciones}`, `${PREFIX_lc_vigente_desde}`, `${PREFIX_lc_vigente_hasta}`, `${PREFIX_man_fecha}`, `${PREFIX_man_hora_inicio}`, `${PREFIX_man_hora_termino}`, `${PREFIX_man_modalidad}`, `${PREFIX_prop_abog_casilla}`, `${PREFIX_prop_abog_celular}`, `${PREFIX_prop_abog_colegiatura}`, `${PREFIX_prop_abog_condicion}`, `${PREFIX_prop_abog_domicilio_procesal}`, `${PREFIX_prop_abog_email}`, `${PREFIX_prop_abog_nombre}`, `${PREFIX_prop_abog_registro}`, `${PREFIX_prop_doc}`, `${PREFIX_prop_domicilio_fiscal}`, `${PREFIX_prop_man_fecha}`, `${PREFIX_prop_man_hora_inicio}`, `${PREFIX_prop_man_hora_termino}`, `${PREFIX_prop_man_modalidad}`, `${PREFIX_prop_man_resumen}`, `${PREFIX_prop_nat_celular}`, `${PREFIX_prop_nat_doc}`, `${PREFIX_prop_nat_domicilio}`, `${PREFIX_prop_nat_email}`, `${PREFIX_prop_nat_nombre}`, `${PREFIX_prop_nombre}`, `${PREFIX_prop_observaciones}`, `${PREFIX_prop_razon_social}`, `${PREFIX_prop_rep_celular}`, `${PREFIX_prop_rep_doc}`, `${PREFIX_prop_rep_domicilio}`, `${PREFIX_prop_rep_email}`, `${PREFIX_prop_rep_man_fecha}`, `${PREFIX_prop_rep_man_hora_inicio}`, `${PREFIX_prop_rep_man_hora_termino}`, `${PREFIX_prop_rep_man_modalidad}`, `${PREFIX_prop_rep_man_resumen}`, `${PREFIX_prop_rep_nombre}`, `${PREFIX_prop_rol_legal}`, `${PREFIX_prop_ruc}`, `${PREFIX_prop_tipo}`, `${PREFIX_rml_atencion}`, `${PREFIX_rml_fecha}`, `${PREFIX_rml_incapacidad}`, `${PREFIX_rml_numero}`, `${PREFIX_rml_observaciones}`, `${PREFIX_veh_anio}`, `${PREFIX_veh_carroceria}`, `${PREFIX_veh_categoria}`, `${PREFIX_veh_color}`, `${PREFIX_veh_marca}`, `${PREFIX_veh_medidas}`, `${PREFIX_veh_modelo}`, `${PREFIX_veh_nro_motor}`, `${PREFIX_veh_observaciones}`, `${PREFIX_veh_orden}`, `${PREFIX_veh_placa}`, `${PREFIX_veh_serie_vin}`, `${PREFIX_veh_tipo}`, `${PREFIX_veh_tipo_accidente}`
- `testigo1_*`: `${testigo1_man_fecha}`, `${testigo1_man_hora_inicio}`, `${testigo1_man_hora_termino}`, `${testigo1_man_modalidad}`, `${testigo1_man_resumen}`
- `testigo2_*`: `${testigo2_man_fecha}`, `${testigo2_man_hora_inicio}`, `${testigo2_man_hora_termino}`, `${testigo2_man_modalidad}`, `${testigo2_man_resumen}`
- `testigo3_*`: `${testigo3_man_fecha}`, `${testigo3_man_hora_inicio}`, `${testigo3_man_hora_termino}`, `${testigo3_man_modalidad}`, `${testigo3_man_resumen}`
- `testigo4_*`: `${testigo4_man_fecha}`, `${testigo4_man_hora_inicio}`, `${testigo4_man_hora_termino}`, `${testigo4_man_modalidad}`, `${testigo4_man_resumen}`
- `testigo5_*`: `${testigo5_man_fecha}`, `${testigo5_man_hora_inicio}`, `${testigo5_man_hora_termino}`, `${testigo5_man_modalidad}`, `${testigo5_man_resumen}`
- `titulo_*`: `${titulo_informe}`
- `v1_*`: `${v1_cond_nombre}`, `${v1_docv_num_soat}`, `${v1_prop_nombre}`, `${v1_veh_orden}`, `${v1_veh_placa}`
- `v2_*`: `${v2_cond_nombre}`, `${v2_docv_num_soat}`, `${v2_prop_nombre}`, `${v2_veh_orden}`, `${v2_veh_placa}`
- `veh_*`: `${veh_placa}`

**Disponibles en codigo pero no presentes (0):**

- Ninguno detectado.

## plantillas/word_informe_datos_generales.docx

**Generadores relacionados:** `word_informe_datos_generales.php`, `word_informe_selector_vehiculo.php`

**Marcadores presentes (13):**

- `acc_*`: `${acc_clase_accidente}`, `${acc_clase_via_zona}`, `${acc_consecuencia}`, `${acc_fecha_hora_accidente}`, `${acc_fecha_hora_comunicacion}`, `${acc_fecha_hora_intervencion}`, `${acc_fiscalia}`, `${acc_fiscal_cargo}`, `${acc_lugar_jurisdiccion_policial}`, `${acc_nro_informe_policial}`, `${acc_unidades_participantes}`, `${acc_unidades_participantes_relato}`
- `itp_*`: `${itp_ocurrencia_policial}`

**Disponibles en codigo pero no presentes (0):**

- Ninguno detectado.

## plantillas/word_informe_descripcion_analitica.docx

**Generadores relacionados:** `word_informe_descripcion_analitica.php`, `word_informe_selector_vehiculo.php`

**Marcadores presentes (15):**

- `acc_*`: `${acc_lugar_jurisdiccion_policial}`
- `itp_*`: `${itp_fecha}`, `${itp_hora}`, `${itp_llegada_lugar}`, `${itp_via1_configuracion}`, `${itp_via1_descripcion}`, `${itp_via1_fluidez}`, `${itp_via1_iluminacion}`, `${itp_via1_intensidad}`, `${itp_via1_material}`, `${itp_via1_medidas}`, `${itp_via1_observaciones}`, `${itp_via1_ordenamiento}`, `${itp_via1_senializacion}`, `${itp_via1_visibilidad}`

**Disponibles en codigo pero no presentes (0):**

- Ninguno detectado.

## plantillas/word_informe_peaton_fallecido.docx

**Generadores relacionados:** `word_informe_peaton_fallecido.php`

**Marcadores presentes (39):**

- `acc_*`: `${acc_distrito}`, `${acc_fiscal}`, `${acc_fiscalia}`, `${acc_lugar}`
- `p1_*`: `${p1_fall_doc}`, `${p1_fall_domicilio}`, `${p1_fall_dosaje_fecha}`, `${p1_fall_dosaje_lectura_cuant}`, `${p1_fall_dosaje_numero}`, `${p1_fall_dosaje_registro}`, `${p1_fall_dosaje_resultado_cual}`, `${p1_fall_edad_accidente}`, `${p1_fall_estado_civil}`, `${p1_fall_man_hora_inicio}`, `${p1_fall_nombre}`, `${p1_fam_domicilio}`, `${p1_fam_edad_registrada}`, `${p1_fam_estado_civil}`, `${p1_fam_fecha_nacimiento}`, `${p1_fam_madre}`, `${p1_fam_man_fecha}`, `${p1_fam_nacimiento}`, `${p1_fam_nombre}`, `${p1_fam_ocupacion}`, `${p1_fam_padre}`, `${p1_fam_parentesco}`, `${p1_occ_cmp_legista}`, `${p1_occ_dosaje_protocolo}`, `${p1_occ_fecha_levantamiento}`, `${p1_occ_fecha_pericial}`, `${p1_occ_fecha_protocolo}`, `${p1_occ_hora_levantamiento}`, `${p1_occ_legista_levantamiento}`, `${p1_occ_lesiones_levantamiento}`, `${p1_occ_lugar_levantamiento}`, `${p1_occ_numero_pericial}`, `${p1_occ_numero_protocolo}`, `${p1_occ_presuntivo_levantamiento}`, `${p1_occ_presuntivo_protocolo}`

**Disponibles en codigo pero no presentes (0):**

- Ninguno detectado.

## plantillas/word_informe_peaton_fallecido_marcadores.docx

**Generadores relacionados:** No detectado automaticamente

**Marcadores presentes (230):**

- `acc_*`: `${acc_comisaria}`, `${acc_consecuencia}`, `${acc_departamento}`, `${acc_distrito}`, `${acc_estado}`, `${acc_fecha}`, `${acc_fiscal}`, `${acc_fiscalia}`, `${acc_hora}`, `${acc_id}`, `${acc_lugar}`, `${acc_modalidad}`, `${acc_nro_informe_policial}`, `${acc_provincia}`, `${acc_referencia}`, `${acc_registro_sidpol}`, `${acc_secuencia}`, `${acc_sentido}`, `${acc_sidpol}`
- `dosaje_*`: `${dosaje_resultado_cual}`, `${dosaje_resultado_cuant}`
- `efec1_*`: `${efec1_man_fecha}`, `${efec1_man_hora_inicio}`, `${efec1_man_hora_termino}`, `${efec1_man_modalidad}`, `${efec1_man_resumen}`
- `efec2_*`: `${efec2_man_fecha}`, `${efec2_man_hora_inicio}`, `${efec2_man_hora_termino}`, `${efec2_man_modalidad}`, `${efec2_man_resumen}`
- `efec3_*`: `${efec3_man_fecha}`, `${efec3_man_hora_inicio}`, `${efec3_man_hora_termino}`, `${efec3_man_modalidad}`, `${efec3_man_resumen}`
- `efec4_*`: `${efec4_man_fecha}`, `${efec4_man_hora_inicio}`, `${efec4_man_hora_termino}`, `${efec4_man_modalidad}`, `${efec4_man_resumen}`
- `efec5_*`: `${efec5_man_fecha}`, `${efec5_man_hora_inicio}`, `${efec5_man_hora_termino}`, `${efec5_man_modalidad}`, `${efec5_man_resumen}`
- `fall_*`: `${fall_doc}`, `${fall_edad_accidente}`, `${fall_nombre}`
- `fam_*`: `${fam_abog_nombre}`, `${fam_nombre}`, `${fam_parentesco}`
- `generado_*`: `${generado_fecha}`
- `occ_*`: `${occ_fecha_levantamiento}`, `${occ_hora_levantamiento}`, `${occ_numero_protocolo}`
- `p2_*`: `${p2_fall_nombre}`, `${p2_fam_nombre}`, `${p2_occ_numero_protocolo}`
- `policia1_*`: `${policia1_man_fecha}`, `${policia1_man_hora_inicio}`, `${policia1_man_hora_termino}`, `${policia1_man_modalidad}`, `${policia1_man_resumen}`
- `policia2_*`: `${policia2_man_fecha}`, `${policia2_man_hora_inicio}`, `${policia2_man_hora_termino}`, `${policia2_man_modalidad}`, `${policia2_man_resumen}`
- `policia3_*`: `${policia3_man_fecha}`, `${policia3_man_hora_inicio}`, `${policia3_man_hora_termino}`, `${policia3_man_modalidad}`, `${policia3_man_resumen}`
- `policia4_*`: `${policia4_man_fecha}`, `${policia4_man_hora_inicio}`, `${policia4_man_hora_termino}`, `${policia4_man_modalidad}`, `${policia4_man_resumen}`
- `policia5_*`: `${policia5_man_fecha}`, `${policia5_man_hora_inicio}`, `${policia5_man_hora_termino}`, `${policia5_man_modalidad}`, `${policia5_man_resumen}`
- `PREFIX_*`: `${PREFIX_dosaje_fecha}`, `${PREFIX_dosaje_hora}`, `${PREFIX_dosaje_lectura_cuant}`, `${PREFIX_dosaje_numero}`, `${PREFIX_dosaje_observaciones}`, `${PREFIX_dosaje_registro}`, `${PREFIX_dosaje_resultado_cual}`, `${PREFIX_dosaje_resultado_cuant}`, `${PREFIX_fall_abog_casilla}`, `${PREFIX_fall_abog_celular}`, `${PREFIX_fall_abog_colegiatura}`, `${PREFIX_fall_abog_condicion}`, `${PREFIX_fall_abog_domicilio_procesal}`, `${PREFIX_fall_abog_email}`, `${PREFIX_fall_abog_nombre}`, `${PREFIX_fall_abog_registro}`, `${PREFIX_fall_api_fuente}`, `${PREFIX_fall_api_ref}`, `${PREFIX_fall_celular}`, `${PREFIX_fall_creado_en}`, `${PREFIX_fall_doc}`, `${PREFIX_fall_doc_num}`, `${PREFIX_fall_doc_tipo}`, `${PREFIX_fall_domicilio}`, `${PREFIX_fall_domicilio_ubigeo}`, `${PREFIX_fall_dosaje_fecha}`, `${PREFIX_fall_dosaje_hora}`, `${PREFIX_fall_dosaje_lectura_cuant}`, `${PREFIX_fall_dosaje_numero}`, `${PREFIX_fall_dosaje_observaciones}`, `${PREFIX_fall_dosaje_registro}`, `${PREFIX_fall_dosaje_resultado_cual}`, `${PREFIX_fall_dosaje_resultado_cuant}`, `${PREFIX_fall_edad}`, `${PREFIX_fall_edad_accidente}`, `${PREFIX_fall_edad_registrada}`, `${PREFIX_fall_email}`, `${PREFIX_fall_estado_civil}`, `${PREFIX_fall_fecha_nacimiento}`, `${PREFIX_fall_foto_path}`, `${PREFIX_fall_grado_instruccion}`, `${PREFIX_fall_lesion}`, `${PREFIX_fall_madre}`, `${PREFIX_fall_man_fecha}`, `${PREFIX_fall_man_hora_inicio}`, `${PREFIX_fall_man_hora_termino}`, `${PREFIX_fall_man_modalidad}`, `${PREFIX_fall_man_resumen}`, `${PREFIX_fall_nacimiento}`, `${PREFIX_fall_nacionalidad}`, `${PREFIX_fall_nombre}`, `${PREFIX_fall_notas}`, `${PREFIX_fall_observaciones}`, `${PREFIX_fall_ocupacion}`, `${PREFIX_fall_padre}`, `${PREFIX_fall_rol}`, `${PREFIX_fall_sexo}`, `${PREFIX_fam_abog_casilla}`, `${PREFIX_fam_abog_celular}`, `${PREFIX_fam_abog_colegiatura}`, `${PREFIX_fam_abog_condicion}`, `${PREFIX_fam_abog_domicilio_procesal}`, `${PREFIX_fam_abog_email}`, `${PREFIX_fam_abog_nombre}`, `${PREFIX_fam_abog_registro}`, `${PREFIX_fam_api_fuente}`, `${PREFIX_fam_api_ref}`, `${PREFIX_fam_celular}`, `${PREFIX_fam_creado_en}`, `${PREFIX_fam_doc}`, `${PREFIX_fam_doc_num}`, `${PREFIX_fam_doc_tipo}`, `${PREFIX_fam_domicilio}`, `${PREFIX_fam_domicilio_ubigeo}`, `${PREFIX_fam_edad}`, `${PREFIX_fam_edad_registrada}`, `${PREFIX_fam_email}`, `${PREFIX_fam_estado_civil}`, `${PREFIX_fam_fecha_nacimiento}`, `${PREFIX_fam_foto_path}`, `${PREFIX_fam_grado_instruccion}`, `${PREFIX_fam_madre}`, `${PREFIX_fam_man_fecha}`, `${PREFIX_fam_man_hora_inicio}`, `${PREFIX_fam_man_hora_termino}`, `${PREFIX_fam_man_modalidad}`, `${PREFIX_fam_man_resumen}`, `${PREFIX_fam_nacimiento}`, `${PREFIX_fam_nacionalidad}`, `${PREFIX_fam_nombre}`, `${PREFIX_fam_notas}`, `${PREFIX_fam_observaciones}`, `${PREFIX_fam_ocupacion}`, `${PREFIX_fam_padre}`, `${PREFIX_fam_parentesco}`, `${PREFIX_fam_sexo}`, `${PREFIX_occ_cmp_legista}`, `${PREFIX_occ_dosaje_protocolo}`, `${PREFIX_occ_fecha_levantamiento}`, `${PREFIX_occ_fecha_pericial}`, `${PREFIX_occ_fecha_protocolo}`, `${PREFIX_occ_hora_alta_epicrisis}`, `${PREFIX_occ_hora_levantamiento}`, `${PREFIX_occ_hora_pericial}`, `${PREFIX_occ_hora_protocolo}`, `${PREFIX_occ_legista_levantamiento}`, `${PREFIX_occ_lesiones_levantamiento}`, `${PREFIX_occ_lesiones_protocolo}`, `${PREFIX_occ_lugar_levantamiento}`, `${PREFIX_occ_nosocomio_epicrisis}`, `${PREFIX_occ_numero_historia_epicrisis}`, `${PREFIX_occ_numero_pericial}`, `${PREFIX_occ_numero_protocolo}`, `${PREFIX_occ_observaciones_levantamiento}`, `${PREFIX_occ_observaciones_pericial}`, `${PREFIX_occ_posicion_cuerpo}`, `${PREFIX_occ_presuntivo_levantamiento}`, `${PREFIX_occ_presuntivo_protocolo}`, `${PREFIX_occ_toxicologico_protocolo}`, `${PREFIX_occ_tratamiento_epicrisis}`
- `testigo1_*`: `${testigo1_man_fecha}`, `${testigo1_man_hora_inicio}`, `${testigo1_man_hora_termino}`, `${testigo1_man_modalidad}`, `${testigo1_man_resumen}`
- `testigo2_*`: `${testigo2_man_fecha}`, `${testigo2_man_hora_inicio}`, `${testigo2_man_hora_termino}`, `${testigo2_man_modalidad}`, `${testigo2_man_resumen}`
- `testigo3_*`: `${testigo3_man_fecha}`, `${testigo3_man_hora_inicio}`, `${testigo3_man_hora_termino}`, `${testigo3_man_modalidad}`, `${testigo3_man_resumen}`
- `testigo4_*`: `${testigo4_man_fecha}`, `${testigo4_man_hora_inicio}`, `${testigo4_man_hora_termino}`, `${testigo4_man_modalidad}`, `${testigo4_man_resumen}`
- `testigo5_*`: `${testigo5_man_fecha}`, `${testigo5_man_hora_inicio}`, `${testigo5_man_hora_termino}`, `${testigo5_man_modalidad}`, `${testigo5_man_resumen}`
- `titulo_*`: `${titulo_informe}`

**Disponibles en codigo pero no presentes (0):**

- Ninguno detectado.

## plantillas/word_informe_un_vehiculo_fallecido.docx

**Generadores relacionados:** `word_informe_un_vehiculo_fallecido.php`

**Marcadores presentes (94):**

- `acc_*`: `${acc_fiscal}`, `${acc_fiscalia}`, `${acc_lugar}`
- `v1_*`: `${v1_cond_nombre}`, `${v1_docv_aseguradora_soat}`, `${v1_docv_certificadora_revision}`, `${v1_docv_danos_peritaje}`, `${v1_docv_fecha_peritaje}`, `${v1_docv_num_propiedad}`, `${v1_docv_num_revision}`, `${v1_docv_num_soat}`, `${v1_docv_otros_peritaje}`, `${v1_docv_partida_propiedad}`, `${v1_docv_planta_motriz_peritaje}`, `${v1_docv_sistema_direccion_peritaje}`, `${v1_docv_sistema_electrico_peritaje}`, `${v1_docv_sistema_frenos_peritaje}`, `${v1_docv_sistema_suspension_peritaje}`, `${v1_docv_sistema_transmision_peritaje}`, `${v1_docv_titulo_propiedad}`, `${v1_docv_vencimiento_revision}`, `${v1_docv_vencimiento_soat}`, `${v1_docv_vigente_revision}`, `${v1_docv_vigente_soat}`, `${v1_dosaje_fecha}`, `${v1_dosaje_hora}`, `${v1_dosaje_lectura_cuant}`, `${v1_dosaje_numero}`, `${v1_dosaje_observaciones}`, `${v1_dosaje_registro}`, `${v1_dosaje_resultado_cual}`, `${v1_dosaje_resultado_cuant}`, `${v1_fall_doc_num}`, `${v1_fall_doc_tipo}`, `${v1_fall_domicilio}`, `${v1_fall_edad}`, `${v1_fall_estado_civil}`, `${v1_fall_fecha_nacimiento}`, `${v1_fall_grado_instruccion}`, `${v1_fall_nacimiento}`, `${v1_fall_nombre}`, `${v1_fam_domicilio}`, `${v1_fam_edad}`, `${v1_fam_estado_civil}`, `${v1_fam_fecha_nacimiento}`, `${v1_fam_nacimiento}`, `${v1_fam_nombre}`, `${v1_fam_ocupacion}`, `${v1_fam_parentesco}`, `${v1_lc_categoria}`, `${v1_lc_clase}`, `${v1_lc_expedido_por}`, `${v1_lc_numero}`, `${v1_lc_restricciones}`, `${v1_lc_vigente_desde}`, `${v1_lc_vigente_hasta}`, `${v1_occ_cmp_legista}`, `${v1_occ_fecha_levantamiento}`, `${v1_occ_fecha_pericial}`, `${v1_occ_hora_levantamiento}`, `${v1_occ_legista_levantamiento}`, `${v1_occ_lesiones_levantamiento}`, `${v1_occ_lugar_levantamiento}`, `${v1_occ_numero_pericial}`, `${v1_prop_abog_colegiatura}`, `${v1_prop_abog_nombre}`, `${v1_prop_abog_registro}`, `${v1_prop_doc}`, `${v1_prop_domicilio_fiscal}`, `${v1_prop_man_fecha}`, `${v1_prop_man_hora_inicio}`, `${v1_prop_nat_doc}`, `${v1_prop_nat_domicilio}`, `${v1_prop_nat_nombre}`, `${v1_prop_nombre}`, `${v1_prop_razon_social}`, `${v1_prop_rep_celular}`, `${v1_prop_rep_doc}`, `${v1_prop_rep_domicilio}`, `${v1_prop_rep_email}`, `${v1_prop_rep_nombre}`, `${v1_prop_ruc}`, `${v1_prop_tipo}`, `${v1_veh_alto}`, `${v1_veh_ancho}`, `${v1_veh_anio}`, `${v1_veh_carroceria}`, `${v1_veh_categoria}`, `${v1_veh_color}`, `${v1_veh_largo}`, `${v1_veh_modelo}`, `${v1_veh_placa}`, `${v1_veh_tipo}`
- `v2_*`: `${v2_docv_num_peritaje}`

**Disponibles en codigo pero no presentes (0):**

- Ninguno detectado.

## plantillas/word_informe_un_vehiculo_fallecido_marcadores.docx

**Generadores relacionados:** No detectado automaticamente

**Marcadores presentes (268):**

- `acc_*`: `${acc_comisaria}`, `${acc_consecuencia}`, `${acc_departamento}`, `${acc_distrito}`, `${acc_estado}`, `${acc_fecha}`, `${acc_fiscal}`, `${acc_fiscalia}`, `${acc_hora}`, `${acc_id}`, `${acc_lugar}`, `${acc_modalidad}`, `${acc_nro_informe_policial}`, `${acc_provincia}`, `${acc_referencia}`, `${acc_registro_sidpol}`, `${acc_secuencia}`, `${acc_sentido}`, `${acc_sidpol}`
- `efec1_*`: `${efec1_man_fecha}`, `${efec1_man_hora_inicio}`, `${efec1_man_hora_termino}`, `${efec1_man_modalidad}`, `${efec1_man_resumen}`
- `efec2_*`: `${efec2_man_fecha}`, `${efec2_man_hora_inicio}`, `${efec2_man_hora_termino}`, `${efec2_man_modalidad}`, `${efec2_man_resumen}`
- `efec3_*`: `${efec3_man_fecha}`, `${efec3_man_hora_inicio}`, `${efec3_man_hora_termino}`, `${efec3_man_modalidad}`, `${efec3_man_resumen}`
- `efec4_*`: `${efec4_man_fecha}`, `${efec4_man_hora_inicio}`, `${efec4_man_hora_termino}`, `${efec4_man_modalidad}`, `${efec4_man_resumen}`
- `efec5_*`: `${efec5_man_fecha}`, `${efec5_man_hora_inicio}`, `${efec5_man_hora_termino}`, `${efec5_man_modalidad}`, `${efec5_man_resumen}`
- `fall_*`: `${fall_nombre}`
- `fam_*`: `${fam_nombre}`
- `generado_*`: `${generado_fecha}`
- `occ_*`: `${occ_fecha_levantamiento}`, `${occ_nosocomio_epicrisis}`, `${occ_numero_pericial}`, `${occ_numero_protocolo}`
- `policia1_*`: `${policia1_man_fecha}`, `${policia1_man_hora_inicio}`, `${policia1_man_hora_termino}`, `${policia1_man_modalidad}`, `${policia1_man_resumen}`
- `policia2_*`: `${policia2_man_fecha}`, `${policia2_man_hora_inicio}`, `${policia2_man_hora_termino}`, `${policia2_man_modalidad}`, `${policia2_man_resumen}`
- `policia3_*`: `${policia3_man_fecha}`, `${policia3_man_hora_inicio}`, `${policia3_man_hora_termino}`, `${policia3_man_modalidad}`, `${policia3_man_resumen}`
- `policia4_*`: `${policia4_man_fecha}`, `${policia4_man_hora_inicio}`, `${policia4_man_hora_termino}`, `${policia4_man_modalidad}`, `${policia4_man_resumen}`
- `policia5_*`: `${policia5_man_fecha}`, `${policia5_man_hora_inicio}`, `${policia5_man_hora_termino}`, `${policia5_man_modalidad}`, `${policia5_man_resumen}`
- `PREFIX_*`: `${PREFIX_cond_celular}`, `${PREFIX_cond_doc}`, `${PREFIX_cond_doc_num}`, `${PREFIX_cond_doc_tipo}`, `${PREFIX_cond_domicilio}`, `${PREFIX_cond_man_fecha}`, `${PREFIX_cond_man_hora_inicio}`, `${PREFIX_cond_man_hora_termino}`, `${PREFIX_cond_man_modalidad}`, `${PREFIX_cond_man_resumen}`, `${PREFIX_cond_nombre}`, `${PREFIX_dosaje_fecha}`, `${PREFIX_dosaje_hora}`, `${PREFIX_dosaje_lectura_cuant}`, `${PREFIX_dosaje_numero}`, `${PREFIX_dosaje_observaciones}`, `${PREFIX_dosaje_registro}`, `${PREFIX_dosaje_resultado_cual}`, `${PREFIX_dosaje_resultado_cuant}`, `${PREFIX_fall_celular}`, `${PREFIX_fall_doc}`, `${PREFIX_fall_doc_num}`, `${PREFIX_fall_doc_tipo}`, `${PREFIX_fall_domicilio}`, `${PREFIX_fall_domicilio_ubigeo}`, `${PREFIX_fall_edad}`, `${PREFIX_fall_edad_accidente}`, `${PREFIX_fall_email}`, `${PREFIX_fall_estado_civil}`, `${PREFIX_fall_fecha_nacimiento}`, `${PREFIX_fall_grado_instruccion}`, `${PREFIX_fall_lesion}`, `${PREFIX_fall_madre}`, `${PREFIX_fall_man_fecha}`, `${PREFIX_fall_man_hora_inicio}`, `${PREFIX_fall_man_hora_termino}`, `${PREFIX_fall_man_modalidad}`, `${PREFIX_fall_man_resumen}`, `${PREFIX_fall_nacimiento}`, `${PREFIX_fall_nacionalidad}`, `${PREFIX_fall_nombre}`, `${PREFIX_fall_observaciones}`, `${PREFIX_fall_ocupacion}`, `${PREFIX_fall_padre}`, `${PREFIX_fall_rol}`, `${PREFIX_fall_sexo}`, `${PREFIX_fam_abog_casilla}`, `${PREFIX_fam_abog_celular}`, `${PREFIX_fam_abog_colegiatura}`, `${PREFIX_fam_abog_condicion}`, `${PREFIX_fam_abog_domicilio_procesal}`, `${PREFIX_fam_abog_email}`, `${PREFIX_fam_abog_nombre}`, `${PREFIX_fam_abog_registro}`, `${PREFIX_fam_celular}`, `${PREFIX_fam_doc}`, `${PREFIX_fam_doc_num}`, `${PREFIX_fam_doc_tipo}`, `${PREFIX_fam_domicilio}`, `${PREFIX_fam_domicilio_ubigeo}`, `${PREFIX_fam_edad}`, `${PREFIX_fam_email}`, `${PREFIX_fam_estado_civil}`, `${PREFIX_fam_fecha_nacimiento}`, `${PREFIX_fam_grado_instruccion}`, `${PREFIX_fam_man_fecha}`, `${PREFIX_fam_man_hora_inicio}`, `${PREFIX_fam_man_hora_termino}`, `${PREFIX_fam_man_modalidad}`, `${PREFIX_fam_man_resumen}`, `${PREFIX_fam_nacimiento}`, `${PREFIX_fam_nacionalidad}`, `${PREFIX_fam_nombre}`, `${PREFIX_fam_observaciones}`, `${PREFIX_fam_ocupacion}`, `${PREFIX_fam_parentesco}`, `${PREFIX_fam_sexo}`, `${PREFIX_lc_categoria}`, `${PREFIX_lc_clase}`, `${PREFIX_lc_expedido_por}`, `${PREFIX_lc_numero}`, `${PREFIX_lc_restricciones}`, `${PREFIX_lc_vigente_desde}`, `${PREFIX_lc_vigente_hasta}`, `${PREFIX_man_fecha}`, `${PREFIX_man_hora_inicio}`, `${PREFIX_man_hora_termino}`, `${PREFIX_man_modalidad}`, `${PREFIX_occ_cmp_legista}`, `${PREFIX_occ_dosaje_protocolo}`, `${PREFIX_occ_fecha_levantamiento}`, `${PREFIX_occ_fecha_pericial}`, `${PREFIX_occ_fecha_protocolo}`, `${PREFIX_occ_hora_alta_epicrisis}`, `${PREFIX_occ_hora_levantamiento}`, `${PREFIX_occ_hora_pericial}`, `${PREFIX_occ_hora_protocolo}`, `${PREFIX_occ_legista_levantamiento}`, `${PREFIX_occ_lesiones_levantamiento}`, `${PREFIX_occ_lesiones_protocolo}`, `${PREFIX_occ_lugar_levantamiento}`, `${PREFIX_occ_nosocomio_epicrisis}`, `${PREFIX_occ_numero_historia_epicrisis}`, `${PREFIX_occ_numero_pericial}`, `${PREFIX_occ_numero_protocolo}`, `${PREFIX_occ_observaciones_levantamiento}`, `${PREFIX_occ_observaciones_pericial}`, `${PREFIX_occ_posicion_cuerpo}`, `${PREFIX_occ_presuntivo_levantamiento}`, `${PREFIX_occ_presuntivo_protocolo}`, `${PREFIX_occ_toxicologico_protocolo}`, `${PREFIX_occ_tratamiento_epicrisis}`, `${PREFIX_prop_abog_colegiatura}`, `${PREFIX_prop_abog_nombre}`, `${PREFIX_prop_abog_registro}`, `${PREFIX_prop_doc}`, `${PREFIX_prop_domicilio_fiscal}`, `${PREFIX_prop_man_fecha}`, `${PREFIX_prop_man_hora_inicio}`, `${PREFIX_prop_man_hora_termino}`, `${PREFIX_prop_man_modalidad}`, `${PREFIX_prop_man_resumen}`, `${PREFIX_prop_nat_doc}`, `${PREFIX_prop_nat_domicilio}`, `${PREFIX_prop_nat_nombre}`, `${PREFIX_prop_nombre}`, `${PREFIX_prop_razon_social}`, `${PREFIX_prop_rep_celular}`, `${PREFIX_prop_rep_doc}`, `${PREFIX_prop_rep_domicilio}`, `${PREFIX_prop_rep_email}`, `${PREFIX_prop_rep_man_fecha}`, `${PREFIX_prop_rep_man_hora_inicio}`, `${PREFIX_prop_rep_man_hora_termino}`, `${PREFIX_prop_rep_man_modalidad}`, `${PREFIX_prop_rep_man_resumen}`, `${PREFIX_prop_rep_nombre}`, `${PREFIX_prop_ruc}`, `${PREFIX_prop_tipo}`, `${PREFIX_veh_anio}`, `${PREFIX_veh_carroceria}`, `${PREFIX_veh_categoria}`, `${PREFIX_veh_color}`, `${PREFIX_veh_marca}`, `${PREFIX_veh_modelo}`, `${PREFIX_veh_nro_motor}`, `${PREFIX_veh_observaciones}`, `${PREFIX_veh_orden}`, `${PREFIX_veh_placa}`, `${PREFIX_veh_serie_vin}`, `${PREFIX_veh_tipo}`
- `testigo1_*`: `${testigo1_man_fecha}`, `${testigo1_man_hora_inicio}`, `${testigo1_man_hora_termino}`, `${testigo1_man_modalidad}`, `${testigo1_man_resumen}`
- `testigo2_*`: `${testigo2_man_fecha}`, `${testigo2_man_hora_inicio}`, `${testigo2_man_hora_termino}`, `${testigo2_man_modalidad}`, `${testigo2_man_resumen}`
- `testigo3_*`: `${testigo3_man_fecha}`, `${testigo3_man_hora_inicio}`, `${testigo3_man_hora_termino}`, `${testigo3_man_modalidad}`, `${testigo3_man_resumen}`
- `testigo4_*`: `${testigo4_man_fecha}`, `${testigo4_man_hora_inicio}`, `${testigo4_man_hora_termino}`, `${testigo4_man_modalidad}`, `${testigo4_man_resumen}`
- `testigo5_*`: `${testigo5_man_fecha}`, `${testigo5_man_hora_inicio}`, `${testigo5_man_hora_termino}`, `${testigo5_man_modalidad}`, `${testigo5_man_resumen}`
- `titulo_*`: `${titulo_informe}`
- `v1_*`: `${v1_cond_nombre}`, `${v1_dosaje_hora}`, `${v1_dosaje_numero}`, `${v1_dosaje_observaciones}`, `${v1_fall_nombre}`, `${v1_lc_expedido_por}`, `${v1_lc_numero}`, `${v1_lc_restricciones}`, `${v1_prop_doc}`, `${v1_prop_domicilio_fiscal}`, `${v1_prop_nombre}`, `${v1_prop_rep_nombre}`, `${v1_prop_tipo}`
- `v2_*`: `${v2_fall_nombre}`
- `v7_*`: `${v7_...}`

**Disponibles en codigo pero no presentes (0):**

- Ninguno detectado.

## plantillas/word_informe_un_vehiculo_ileso.docx

**Generadores relacionados:** `word_informe_un_vehiculo_ileso.php`

**Marcadores presentes (85):**

- `acc_*`: `${acc_fiscal}`, `${acc_fiscalia}`, `${acc_lugar}`
- `v1_*`: `${v1_cond_abog_casilla}`, `${v1_cond_abog_celular}`, `${v1_cond_abog_colegiatura}`, `${v1_cond_abog_domicilio_procesal}`, `${v1_cond_abog_email}`, `${v1_cond_abog_nombre}`, `${v1_cond_abog_registro}`, `${v1_cond_doc_num}`, `${v1_cond_doc_tipo}`, `${v1_cond_domicilio}`, `${v1_cond_edad}`, `${v1_cond_estado_civil}`, `${v1_cond_fecha_nacimiento}`, `${v1_cond_grado_instruccion}`, `${v1_cond_man_fecha}`, `${v1_cond_man_hora_inicio}`, `${v1_cond_nacimiento}`, `${v1_cond_nombre}`, `${v1_docv_aseguradora_soat}`, `${v1_docv_certificadora_revision}`, `${v1_docv_danos_peritaje}`, `${v1_docv_fecha_peritaje}`, `${v1_docv_num_propiedad}`, `${v1_docv_num_revision}`, `${v1_docv_num_soat}`, `${v1_docv_otros_peritaje}`, `${v1_docv_partida_propiedad}`, `${v1_docv_planta_motriz_peritaje}`, `${v1_docv_sistema_direccion_peritaje}`, `${v1_docv_sistema_electrico_peritaje}`, `${v1_docv_sistema_frenos_peritaje}`, `${v1_docv_sistema_suspension_peritaje}`, `${v1_docv_sistema_transmision_peritaje}`, `${v1_docv_titulo_propiedad}`, `${v1_docv_vencimiento_revision}`, `${v1_docv_vencimiento_soat}`, `${v1_docv_vigente_revision}`, `${v1_docv_vigente_soat}`, `${v1_dosaje_fecha}`, `${v1_dosaje_hora}`, `${v1_dosaje_lectura_cuant}`, `${v1_dosaje_numero}`, `${v1_dosaje_registro}`, `${v1_dosaje_resultado_cual}`, `${v1_dosaje_resultado_cuant}`, `${v1_lc_categoria}`, `${v1_lc_clase}`, `${v1_lc_expedido_por}`, `${v1_lc_numero}`, `${v1_lc_restricciones}`, `${v1_lc_vigente_desde}`, `${v1_lc_vigente_hasta}`, `${v1_prop_abog_colegiatura}`, `${v1_prop_abog_nombre}`, `${v1_prop_abog_registro}`, `${v1_prop_doc}`, `${v1_prop_domicilio_fiscal}`, `${v1_prop_man_fecha}`, `${v1_prop_man_hora_inicio}`, `${v1_prop_nat_doc}`, `${v1_prop_nat_domicilio}`, `${v1_prop_nat_nombre}`, `${v1_prop_nombre}`, `${v1_prop_razon_social}`, `${v1_prop_rep_celular}`, `${v1_prop_rep_doc}`, `${v1_prop_rep_domicilio}`, `${v1_prop_rep_email}`, `${v1_prop_rep_nombre}`, `${v1_prop_ruc}`, `${v1_prop_tipo}`, `${v1_veh_alto}`, `${v1_veh_ancho}`, `${v1_veh_anio}`, `${v1_veh_carroceria}`, `${v1_veh_categoria}`, `${v1_veh_color}`, `${v1_veh_largo}`, `${v1_veh_modelo}`, `${v1_veh_placa}`, `${v1_veh_tipo}`
- `v2_*`: `${v2_docv_num_peritaje}`

**Disponibles en codigo pero no presentes (0):**

- Ninguno detectado.

## plantillas/word_informe_un_vehiculo_ileso_marcadores.docx

**Generadores relacionados:** No detectado automaticamente

**Marcadores presentes (242):**

- `acc_*`: `${acc_comisaria}`, `${acc_consecuencia}`, `${acc_departamento}`, `${acc_distrito}`, `${acc_estado}`, `${acc_fecha}`, `${acc_fecha_comunicacion}`, `${acc_fecha_intervencion}`, `${acc_fiscal}`, `${acc_fiscalia}`, `${acc_hora}`, `${acc_hora_comunicacion}`, `${acc_hora_intervencion}`, `${acc_id}`, `${acc_lugar}`, `${acc_modalidad}`, `${acc_nro_informe_policial}`, `${acc_provincia}`, `${acc_referencia}`, `${acc_registro_sidpol}`, `${acc_secuencia}`, `${acc_sentido}`, `${acc_sidpol}`
- `cond_*`: `${cond_nombre}`
- `docv_*`: `${docv_num_soat}`
- `efec1_*`: `${efec1_man_fecha}`, `${efec1_man_hora_inicio}`, `${efec1_man_hora_termino}`, `${efec1_man_modalidad}`, `${efec1_man_resumen}`
- `efec2_*`: `${efec2_man_fecha}`, `${efec2_man_hora_inicio}`, `${efec2_man_hora_termino}`, `${efec2_man_modalidad}`, `${efec2_man_resumen}`
- `efec3_*`: `${efec3_man_fecha}`, `${efec3_man_hora_inicio}`, `${efec3_man_hora_termino}`, `${efec3_man_modalidad}`, `${efec3_man_resumen}`
- `efec4_*`: `${efec4_man_fecha}`, `${efec4_man_hora_inicio}`, `${efec4_man_hora_termino}`, `${efec4_man_modalidad}`, `${efec4_man_resumen}`
- `efec5_*`: `${efec5_man_fecha}`, `${efec5_man_hora_inicio}`, `${efec5_man_hora_termino}`, `${efec5_man_modalidad}`, `${efec5_man_resumen}`
- `generado_*`: `${generado_fecha}`
- `lc_*`: `${lc_numero}`
- `policia1_*`: `${policia1_man_fecha}`, `${policia1_man_hora_inicio}`, `${policia1_man_hora_termino}`, `${policia1_man_modalidad}`, `${policia1_man_resumen}`
- `policia2_*`: `${policia2_man_fecha}`, `${policia2_man_hora_inicio}`, `${policia2_man_hora_termino}`, `${policia2_man_modalidad}`, `${policia2_man_resumen}`
- `policia3_*`: `${policia3_man_fecha}`, `${policia3_man_hora_inicio}`, `${policia3_man_hora_termino}`, `${policia3_man_modalidad}`, `${policia3_man_resumen}`
- `policia4_*`: `${policia4_man_fecha}`, `${policia4_man_hora_inicio}`, `${policia4_man_hora_termino}`, `${policia4_man_modalidad}`, `${policia4_man_resumen}`
- `policia5_*`: `${policia5_man_fecha}`, `${policia5_man_hora_inicio}`, `${policia5_man_hora_termino}`, `${policia5_man_modalidad}`, `${policia5_man_resumen}`
- `PREFIX_*`: `${PREFIX_cond_abog_casilla}`, `${PREFIX_cond_abog_celular}`, `${PREFIX_cond_abog_colegiatura}`, `${PREFIX_cond_abog_condicion}`, `${PREFIX_cond_abog_domicilio_procesal}`, `${PREFIX_cond_abog_email}`, `${PREFIX_cond_abog_nombre}`, `${PREFIX_cond_abog_registro}`, `${PREFIX_cond_celular}`, `${PREFIX_cond_doc}`, `${PREFIX_cond_doc_num}`, `${PREFIX_cond_doc_tipo}`, `${PREFIX_cond_domicilio}`, `${PREFIX_cond_domicilio_ubigeo}`, `${PREFIX_cond_edad}`, `${PREFIX_cond_edad_accidente}`, `${PREFIX_cond_email}`, `${PREFIX_cond_estado_civil}`, `${PREFIX_cond_fecha_nacimiento}`, `${PREFIX_cond_grado_instruccion}`, `${PREFIX_cond_lesion}`, `${PREFIX_cond_madre}`, `${PREFIX_cond_man_fecha}`, `${PREFIX_cond_man_hora_inicio}`, `${PREFIX_cond_man_hora_termino}`, `${PREFIX_cond_man_modalidad}`, `${PREFIX_cond_man_resumen}`, `${PREFIX_cond_nacimiento}`, `${PREFIX_cond_nacionalidad}`, `${PREFIX_cond_nombre}`, `${PREFIX_cond_observaciones}`, `${PREFIX_cond_ocupacion}`, `${PREFIX_cond_padre}`, `${PREFIX_cond_rol}`, `${PREFIX_cond_sexo}`, `${PREFIX_docv_aseguradora_soat}`, `${PREFIX_docv_certificadora_revision}`, `${PREFIX_docv_danos_peritaje}`, `${PREFIX_docv_fecha_peritaje}`, `${PREFIX_docv_num_peritaje}`, `${PREFIX_docv_num_propiedad}`, `${PREFIX_docv_num_revision}`, `${PREFIX_docv_num_soat}`, `${PREFIX_docv_otros_peritaje}`, `${PREFIX_docv_partida_propiedad}`, `${PREFIX_docv_perito_peritaje}`, `${PREFIX_docv_planta_motriz_peritaje}`, `${PREFIX_docv_sede_propiedad}`, `${PREFIX_docv_sistema_direccion_peritaje}`, `${PREFIX_docv_sistema_electrico_peritaje}`, `${PREFIX_docv_sistema_frenos_peritaje}`, `${PREFIX_docv_sistema_suspension_peritaje}`, `${PREFIX_docv_sistema_transmision_peritaje}`, `${PREFIX_docv_titulo_propiedad}`, `${PREFIX_docv_vencimiento_revision}`, `${PREFIX_docv_vencimiento_soat}`, `${PREFIX_docv_vigente_revision}`, `${PREFIX_docv_vigente_soat}`, `${PREFIX_dosaje_fecha}`, `${PREFIX_dosaje_hora}`, `${PREFIX_dosaje_lectura_cuant}`, `${PREFIX_dosaje_numero}`, `${PREFIX_dosaje_observaciones}`, `${PREFIX_dosaje_registro}`, `${PREFIX_dosaje_resultado_cual}`, `${PREFIX_dosaje_resultado_cuant}`, `${PREFIX_lc_categoria}`, `${PREFIX_lc_clase}`, `${PREFIX_lc_expedido_por}`, `${PREFIX_lc_numero}`, `${PREFIX_lc_restricciones}`, `${PREFIX_lc_vigente_desde}`, `${PREFIX_lc_vigente_hasta}`, `${PREFIX_man_fecha}`, `${PREFIX_man_hora_inicio}`, `${PREFIX_man_hora_termino}`, `${PREFIX_man_modalidad}`, `${PREFIX_prop_abog_casilla}`, `${PREFIX_prop_abog_celular}`, `${PREFIX_prop_abog_colegiatura}`, `${PREFIX_prop_abog_condicion}`, `${PREFIX_prop_abog_domicilio_procesal}`, `${PREFIX_prop_abog_email}`, `${PREFIX_prop_abog_nombre}`, `${PREFIX_prop_abog_registro}`, `${PREFIX_prop_doc}`, `${PREFIX_prop_domicilio_fiscal}`, `${PREFIX_prop_man_fecha}`, `${PREFIX_prop_man_hora_inicio}`, `${PREFIX_prop_man_hora_termino}`, `${PREFIX_prop_man_modalidad}`, `${PREFIX_prop_man_resumen}`, `${PREFIX_prop_nat_celular}`, `${PREFIX_prop_nat_doc}`, `${PREFIX_prop_nat_domicilio}`, `${PREFIX_prop_nat_email}`, `${PREFIX_prop_nat_nombre}`, `${PREFIX_prop_nombre}`, `${PREFIX_prop_observaciones}`, `${PREFIX_prop_razon_social}`, `${PREFIX_prop_rep_celular}`, `${PREFIX_prop_rep_doc}`, `${PREFIX_prop_rep_domicilio}`, `${PREFIX_prop_rep_email}`, `${PREFIX_prop_rep_man_fecha}`, `${PREFIX_prop_rep_man_hora_inicio}`, `${PREFIX_prop_rep_man_hora_termino}`, `${PREFIX_prop_rep_man_modalidad}`, `${PREFIX_prop_rep_man_resumen}`, `${PREFIX_prop_rep_nombre}`, `${PREFIX_prop_rol_legal}`, `${PREFIX_prop_ruc}`, `${PREFIX_prop_tipo}`, `${PREFIX_rml_atencion}`, `${PREFIX_rml_fecha}`, `${PREFIX_rml_incapacidad}`, `${PREFIX_rml_numero}`, `${PREFIX_rml_observaciones}`, `${PREFIX_veh_anio}`, `${PREFIX_veh_carroceria}`, `${PREFIX_veh_categoria}`, `${PREFIX_veh_color}`, `${PREFIX_veh_marca}`, `${PREFIX_veh_medidas}`, `${PREFIX_veh_modelo}`, `${PREFIX_veh_nro_motor}`, `${PREFIX_veh_observaciones}`, `${PREFIX_veh_orden}`, `${PREFIX_veh_placa}`, `${PREFIX_veh_serie_vin}`, `${PREFIX_veh_tipo}`, `${PREFIX_veh_tipo_accidente}`
- `testigo1_*`: `${testigo1_man_fecha}`, `${testigo1_man_hora_inicio}`, `${testigo1_man_hora_termino}`, `${testigo1_man_modalidad}`, `${testigo1_man_resumen}`
- `testigo2_*`: `${testigo2_man_fecha}`, `${testigo2_man_hora_inicio}`, `${testigo2_man_hora_termino}`, `${testigo2_man_modalidad}`, `${testigo2_man_resumen}`
- `testigo3_*`: `${testigo3_man_fecha}`, `${testigo3_man_hora_inicio}`, `${testigo3_man_hora_termino}`, `${testigo3_man_modalidad}`, `${testigo3_man_resumen}`
- `testigo4_*`: `${testigo4_man_fecha}`, `${testigo4_man_hora_inicio}`, `${testigo4_man_hora_termino}`, `${testigo4_man_modalidad}`, `${testigo4_man_resumen}`
- `testigo5_*`: `${testigo5_man_fecha}`, `${testigo5_man_hora_inicio}`, `${testigo5_man_hora_termino}`, `${testigo5_man_modalidad}`, `${testigo5_man_resumen}`
- `titulo_*`: `${titulo_informe}`
- `v1_*`: `${v1_cond_nombre}`, `${v1_docv_num_soat}`, `${v1_lc_numero}`, `${v1_veh_placa}`
- `v2_*`: `${v2_veh_placa}`
- `v7_*`: `${v7_veh_placa}`
- `veh_*`: `${veh_placa}`

**Disponibles en codigo pero no presentes (0):**

- Ninguno detectado.

## resultado_dosaje.docx

**Generadores relacionados:** `oficio_resultado_dosaje.php`

**Marcadores presentes (10):**

- `accidente_*`: `${accidente_fecha_abrev}`, `${accidente_lugar}`, `${accidente_modalidad}`
- `anio_*`: `${anio}`
- `entidad_*`: `${entidad_destino}`
- `fiscalia_*`: `${fiscalia_nombre}`
- `grado_*`: `${grado_cargo}`
- `nombre_*`: `${nombre_oficial_ano}`
- `numero_*`: `${numero}`
- `referencia_*`: `${referencia_texto}`

**Disponibles en codigo pero no presentes (21):**

- `accidente_*`: `${accidente_fecha}`, `${accidente_hora}`, `${accidente_referencia}`
- `asunto_*`: `${asunto}`
- `carpeta_*`: `${carpeta}`
- `comisaria_*`: `${comisaria_nombre}`
- `fecha_*`: `${fecha_emision}`
- `fiscal_*`: `${fiscal_cargo}`, `${fiscal_correo}`, `${fiscal_dni}`, `${fiscal_nombre}`, `${fiscal_telefono}`
- `fiscalia_*`: `${fiscalia_correo}`, `${fiscalia_direccion}`, `${fiscalia_telefono}`
- `motivo_*`: `${motivo}`
- `oficial_*`: `${oficial_ano}`
- `person_*`: `${person_list}`
- `persona_*`: `${persona_destino}`
- `subentidad_*`: `${subentidad_destino}`
- `vehiculo_*`: `${vehiculo_list}`

## Plantilla pendiente: plantillas/acta_entrega_vehiculo.docx

**Generador:** `acta_entrega_vehiculo_descargar.php`

**Marcadores disponibles (71):**

- `accidente_*`: `${accidente_lugar}`, `${accidente_sidpol}`
- `acta_*`: `${acta_distrito}`, `${acta_estado}`, `${acta_id}`, `${acta_intro_apertura}`, `${acta_intro_cierre}`, `${acta_intro_despues_persona}`, `${acta_intro_empresa}`, `${acta_intro_persona}`, `${acta_presentacion_propietario}`, `${acta_tipo}`
- `conductor_*`: `${conductor_celular}`, `${conductor_domicilio}`, `${conductor_email}`, `${conductor_nombre}`, `${conductor_num_doc}`, `${conductor_tipo_doc}`
- `fecha_*`: `${fecha_entrega}`, `${fecha_entrega_abrev}`, `${fecha_entrega_corta}`
- `hora_*`: `${hora_culminacion}`, `${hora_inicio}`
- `observaciones_*`: `${observaciones}`
- `placa_*`: `${placa_rodaje}`
- `propietario_*`: `${propietario_celular}`, `${propietario_domicilio}`, `${propietario_email}`, `${propietario_nombre}`, `${propietario_num_doc}`, `${propietario_origen}`, `${propietario_presentacion}`, `${propietario_razon_social}`, `${propietario_rol_legal}`, `${propietario_ruc}`, `${propietario_tipo}`, `${propietario_tipo_doc}`
- `representante_*`: `${representante_celular}`, `${representante_domicilio}`, `${representante_email}`, `${representante_nombre}`, `${representante_num_doc}`, `${representante_rol_legal}`, `${representante_tipo_doc}`
- `vehiculo_*`: `${vehiculo_anio}`, `${vehiculo_anio_compuesto}`, `${vehiculo_carroceria}`, `${vehiculo_carroceria_compuesto}`, `${vehiculo_categoria}`, `${vehiculo_categoria_compuesto}`, `${vehiculo_clase}`, `${vehiculo_clase_compuesto}`, `${vehiculo_color}`, `${vehiculo_color_compuesto}`, `${vehiculo_dimensiones}`, `${vehiculo_dimensiones_compuesto}`, `${vehiculo_marca}`, `${vehiculo_marca_compuesto}`, `${vehiculo_modelo}`, `${vehiculo_modelo_compuesto}`, `${vehiculo_motor}`, `${vehiculo_motor_compuesto}`, `${vehiculo_participacion_compuesto}`, `${vehiculo_placa}`, `${vehiculo_placa_compuesto}`, `${vehiculo_tipo}`, `${vehiculo_ut}`, `${vehiculo_ut_compuesto}`, `${vehiculo_vin}`, `${vehiculo_vin_compuesto}`
- `vehiculos_*`: `${vehiculos_involucrados}`

## Plantilla pendiente: plantillas/acta_visualizacion_video.docx

**Generador:** `acta_visualizacion_descargar.php`

**Uso recomendado para reproducir el formato de referencia:**

- Primer parrafo completo: `${acta_presentacion}`.
- Parrafo del fiscal: `${ministerio_publico_parrafo}`.
- Item de oficios y respuestas de camaras: `${diligencia_oficios_parrafo}`.
- Item de discos: `${diligencia_discos_parrafo}`.
- Lista detallada de archivos: `${diligencia_archivos_detalle}`.
- Todas las observaciones temporales en texto: `${descripciones_video_detalle}`.
- Bloque dinamico repetible: `${DESCRIPCIONES_VIDEO}` ... `${/DESCRIPCIONES_VIDEO}`.
- Dentro del bloque usa `${disco_encabezado}`, `${archivo_encabezado}`, `${descripcion_tiempo}`, `${descripcion_detalle}` y `${descripcion_captura}`.
- El encabezado del disco y del archivo aparece solo una vez; los siguientes momentos muestran unicamente tiempo, detalle e imagen.
- Para controlar cada dato por separado, usa los marcadores individuales listados debajo.

**Marcadores disponibles (104):**

- `/DESCRIPCIONES_*`: `${/DESCRIPCIONES_VIDEO}`
- `abogados_*`: `${abogados_detalle}`
- `accidente_*`: `${accidente_distrito}`, `${accidente_fecha}`, `${accidente_fecha_abrev}`, `${accidente_fecha_corta}`, `${accidente_hora}`, `${accidente_id}`, `${accidente_lugar}`, `${accidente_referencia}`, `${accidente_sidpol}`
- `acta_*`: `${acta_presentacion}`, `${acta_visualizacion_estado}`, `${acta_visualizacion_fecha}`, `${acta_visualizacion_fecha_abrev}`, `${acta_visualizacion_fecha_corta}`, `${acta_visualizacion_hora_inicio}`, `${acta_visualizacion_id}`, `${acta_visualizacion_observaciones}`
- `archivo_*`: `${archivo_encabezado}`
- `archivos_*`: `${archivos_detalle}`
- `cantidad_*`: `${cantidad_archivos}`, `${cantidad_descripciones_video}`, `${cantidad_discos}`
- `desarrollo_*`: `${desarrollo_diligencia}`
- `descripcion_*`: `${descripcion_captura}`, `${descripcion_detalle}`, `${descripcion_tiempo}`
- `DESCRIPCIONES_*`: `${DESCRIPCIONES_VIDEO}`
- `descripciones_*`: `${descripciones_video_detalle}`
- `diligencia_*`: `${diligencia_archivos_detalle}`, `${diligencia_discos_parrafo}`, `${diligencia_oficios_parrafo}`
- `disco_*`: `${disco_1_archivos}`, `${disco_1_marca}`, `${disco_1_numero}`, `${disco_1_observaciones}`, `${disco_1_serie}`, `${disco_2_archivos}`, `${disco_2_marca}`, `${disco_2_numero}`, `${disco_2_observaciones}`, `${disco_2_serie}`, `${disco_3_archivos}`, `${disco_3_marca}`, `${disco_3_numero}`, `${disco_3_observaciones}`, `${disco_3_serie}`, `${disco_4_archivos}`, `${disco_4_marca}`, `${disco_4_numero}`, `${disco_4_observaciones}`, `${disco_4_serie}`, `${disco_5_archivos}`, `${disco_5_marca}`, `${disco_5_numero}`, `${disco_5_observaciones}`, `${disco_5_serie}`, `${disco_6_archivos}`, `${disco_6_marca}`, `${disco_6_numero}`, `${disco_6_observaciones}`, `${disco_6_serie}`, `${disco_7_archivos}`, `${disco_7_marca}`, `${disco_7_numero}`, `${disco_7_observaciones}`, `${disco_7_serie}`, `${disco_8_archivos}`, `${disco_8_marca}`, `${disco_8_numero}`, `${disco_8_observaciones}`, `${disco_8_serie}`, `${disco_9_archivos}`, `${disco_9_marca}`, `${disco_9_numero}`, `${disco_9_observaciones}`, `${disco_9_serie}`, `${disco_10_archivos}`, `${disco_10_marca}`, `${disco_10_numero}`, `${disco_10_observaciones}`, `${disco_10_serie}`, `${disco_encabezado}`
- `discos_*`: `${discos_detalle}`
- `documentos_*`: `${documentos_camaras_detalle}`
- `familiares_*`: `${familiares_detalle}`
- `fiscal_*`: `${fiscal_cargo}`, `${fiscal_nombre}`, `${fiscal_telefono}`
- `fiscalia_*`: `${fiscalia_nombre}`
- `instructor_*`: `${instructor_cip}`, `${instructor_grado}`, `${instructor_nombre}`
- `lugar_*`: `${lugar_diligencia}`
- `ministerio_*`: `${ministerio_publico_parrafo}`
- `oficios_*`: `${oficios_camaras_detalle}`
- `parte_*`: `${parte_agraviada}`, `${parte_investigada}`
- `participantes_*`: `${participantes_detalle}`, `${participantes_nombres}`
- `propietarios_*`: `${propietarios_detalle}`
- `respuestas_*`: `${respuestas_camaras_detalle}`
- `unidad_*`: `${unidad_nombre}`

## Plantilla pendiente: plantillas/oficio_informacion_certificado_uper.docx

**Generador:** `word_oficio_informacion_certificado_uper.php`

**Se descarga cuando el asunto contiene:** `Informacion certificado` (comparacion normalizada).

**Marcadores disponibles (84):**

- `accidente_*`: `${accidente_cod_dep}`, `${accidente_cod_dist}`, `${accidente_cod_prov}`, `${accidente_comunicacion_carpeta_nro}`, `${accidente_comunicacion_decreto}`, `${accidente_comunicacion_oficio}`, `${accidente_comunicante_nombre}`, `${accidente_comunicante_telefono}`, `${accidente_consecuencia}`, `${accidente_consecuencias}`, `${accidente_coordenadas}`, `${accidente_departamento}`, `${accidente_distrito}`, `${accidente_estado}`, `${accidente_fecha}`, `${accidente_fecha_abrev}`, `${accidente_fecha_comunicacion}`, `${accidente_fecha_comunicacion_abrev}`, `${accidente_fecha_intervencion}`, `${accidente_fecha_intervencion_abrev}`, `${accidente_folder}`, `${accidente_hora}`, `${accidente_hora_comunicacion}`, `${accidente_hora_intervencion}`, `${accidente_id}`, `${accidente_latitud}`, `${accidente_longitud}`, `${accidente_lugar}`, `${accidente_lugar_completo}`, `${accidente_modalidad}`, `${accidente_modalidades}`, `${accidente_nro_informe_policial}`, `${accidente_prioridad}`, `${accidente_provincia}`, `${accidente_referencia}`, `${accidente_resumen}`, `${accidente_secuencia}`, `${accidente_sentido}`, `${accidente_sidpol}`, `${accidente_tipo_registro}`, `${accidente_ubicacion}`
- `asunto_*`: `${asunto_detalle}`, `${asunto_nombre}`
- `comisaria_*`: `${comisaria_nombre}`
- `fiscalia_*`: `${fiscalia_nombre}`
- `grado_*`: `${grado_cargo_abrev}`, `${grado_cargo_nombre}`, `${grado_cargo_tipo}`
- `nombre_*`: `${nombre_oficial_ano}`
- `oficio_*`: `${oficio_anio}`, `${oficio_entidad_linea}`, `${oficio_entidad_nombre}`, `${oficio_entidad_siglas}`, `${oficio_fecha}`, `${oficio_fecha_abrev}`, `${oficio_grado_cargo}`, `${oficio_motivo}`, `${oficio_numero}`, `${oficio_persona_destino}`, `${oficio_referencia}`, `${oficio_subentidad_nombre}`, `${oficio_subentidad_tipo}`
- `veh_*`: `${veh_alto_mm}`, `${veh_ancho_mm}`, `${veh_anio}`, `${veh_carroceria}`, `${veh_carroceria_descripcion}`, `${veh_categoria}`, `${veh_categoria_descripcion}`, `${veh_color}`, `${veh_largo_mm}`, `${veh_marca}`, `${veh_medidas}`, `${veh_modelo}`, `${veh_notas}`, `${veh_nro_motor}`, `${veh_observaciones}`, `${veh_orden}`, `${veh_placa}`, `${veh_serie_vin}`, `${veh_tipo}`, `${veh_tipo_codigo}`, `${veh_tipo_descripcion}`, `${veh_tipo_participacion}`

## Plantilla pendiente: plantillas/oficio_informacion_diligencias_comisaria.docx

**Generador:** `word_oficio_informacion_diligencias_comisaria.php`

**Se descarga cuando el asunto contiene:** `Informacion` y `diligencias` (comparacion normalizada).

**Marcadores multilínea recomendados:** `${diligencias_solicitadas_numeradas}`, `${vehiculos_involucrados}` y `${personas_involucradas}`.

**Marcadores de diligencias solicitadas:**

- `${diligencias_solicitadas}`: texto ingresado, conservando una diligencia por linea.
- `${diligencias_solicitadas_numeradas}`: diligencias convertidas en lista numerada.
- `${diligencias_cantidad}`: cantidad de diligencias solicitadas.

**Marcadores de persona fallecida:**

- Primer fallecido: marcadores `${fallecido_*}` para identidad, documento, nacimiento, edad, sexo, estado civil, domicilio, ocupacion, contacto y lesion.
- Todos los fallecidos: `${fallecidos_involucrados}` y `${fallecidos_cantidad}`.

**Marcadores disponibles (51):**

- `accidente_*`: `${accidente_consecuencias}`, `${accidente_fecha}`, `${accidente_fecha_abrev}`, `${accidente_hora}`, `${accidente_id}`, `${accidente_lugar}`, `${accidente_lugar_completo}`, `${accidente_modalidades}`, `${accidente_referencia}`, `${accidente_sentido}`, `${accidente_sidpol}`
- `asunto_*`: `${asunto_detalle}`, `${asunto_nombre}`
- `comisaria_*`: `${comisaria_nombre}`
- `diligencias_*`: `${diligencias_cantidad}`, `${diligencias_solicitadas}`, `${diligencias_solicitadas_numeradas}`
- `fallecido_*`: `${fallecido_apellidos}`, `${fallecido_celular}`, `${fallecido_documento}`, `${fallecido_domicilio}`, `${fallecido_edad}`, `${fallecido_email}`, `${fallecido_estado_civil}`, `${fallecido_fecha_nacimiento}`, `${fallecido_fecha_nacimiento_abrev}`, `${fallecido_lesion}`, `${fallecido_nombres}`, `${fallecido_nombre_completo}`, `${fallecido_num_doc}`, `${fallecido_ocupacion}`, `${fallecido_sexo}`, `${fallecido_tipo_doc}`
- `fallecidos_*`: `${fallecidos_cantidad}`, `${fallecidos_involucrados}`
- `fiscalia_*`: `${fiscalia_nombre}`
- `nombre_*`: `${nombre_oficial_ano}`
- `oficio_*`: `${oficio_anio}`, `${oficio_entidad_nombre}`, `${oficio_entidad_siglas}`, `${oficio_fecha}`, `${oficio_grado_cargo}`, `${oficio_motivo}`, `${oficio_numero}`, `${oficio_persona_destino}`, `${oficio_referencia}`, `${oficio_subentidad_nombre}`
- `personas_*`: `${personas_cantidad}`, `${personas_involucradas}`
- `vehiculos_*`: `${vehiculos_cantidad}`, `${vehiculos_involucrados}`

