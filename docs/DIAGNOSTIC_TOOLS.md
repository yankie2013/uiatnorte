# Herramientas de Diagnostico

Estas rutas se conservan como utilidades internas para verificar entorno, plantillas y exportacion de documentos. No deben usarse como punto principal del flujo de negocio.

## Exportacion / Informes

- `word_informe_atropello_probe.php`
  - Probe liviano del entorno, base de datos y disponibilidad de plantilla.
- `word_informe_atropello_tplcheck.php`
  - Verifica que la plantilla DOCX pueda abrirse y guardarse con `TemplateProcessor`.
- `ver_log_informe_atropello.php`
  - Muestra las ultimas lineas del log especifico de informe atropello.
- `exportar_accidente_debug.php`
  - Prueba diagnostica de exportacion DOCX para accidentes.
- `oficio_peritaje_diag.php`
  - Diagnostico de dependencias y escritura DOCX para el oficio de peritaje.

## Criterio de uso

1. Se usan para soporte tecnico o validacion local.
2. Deben producir salida controlada en texto plano.
3. No deben dejar archivos generados permanentemente en la raiz del proyecto.
4. Si una utilidad termina convirtiendose en flujo funcional, debe migrarse a la capa principal y dejar este archivo como wrapper o eliminarse.
