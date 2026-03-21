# Inventario Legacy y Runtime

## Activo

Estas rutas forman parte de la base actual del proyecto y conviene mantenerlas como fuente principal de trabajo:

- `app/`
- `bootstrap/`
- `config/`
- `vendor/`
- `plantillas/`
- modulos PHP de la raiz que ya fueron migrados progresivamente a `Repositories` y `Services`

## Runtime

Estas rutas contienen archivos de ejecucion o datos locales del entorno y no deberian mezclarse con cambios funcionales del codigo:

- `tmp/`
- `logs/`
- `uploads/`
- `google/*.json`
- `error_log`
- `php_errors.log`
- `.cf_cookie_jar.txt`

## Legacy Controlado

Estas rutas existen hoy como respaldo o dependencia auxiliar, pero no deberian usarse como punto de extension principal del sistema:

- `vendor_old/`
  - Inventario rapido local: 415 archivos.
  - Uso esperado: solo referencia historica. No agregar cambios nuevos aqui.
- `PHPWord-1.4.0/`
  - Inventario rapido local: 654 archivos.
  - Uso esperado: fallback de autoload para generadores DOCX/PDF heredados mientras no se unifique todo en `vendor/`.
- `dompdf/`
  - Inventario rapido local: 5 archivos visibles en esta copia raiz.
  - Estado: incompleto para uso directo. El fallback util hoy viene desde `PHPWord-1.4.0/vendor/autoload.php`.
- `__MACOSX/`
  - Basura de empaquetado. Mantener fuera de flujos normales.
- `*.zip`
  - Artefactos de distribucion o respaldo (`vendor.zip`, `PHPWord-1.4.0.zip`, `dompdf-3.1.2.zip`).

## Wrappers de Compatibilidad

Estas rutas ya no mantienen logica propia y se conservaron para no romper enlaces antiguos:

- `accidente_exportar_word.php` -> `exportar_accidente.php`
- `word_informe_atropello_safe.php` -> `word_informe_atropello.php`
- `diligencias_pendientes_listar.php` -> `diligenciapendiente_listar.php`
- `documento_occiso_delete.php` -> `documento_occiso_eliminar.php`
- `documentos_recibidos_listar.php` -> `documento_recibido_listar.php`

## Siguiente Limpieza Recomendada

1. Decidir si `PHPWord-1.4.0/` seguira como fallback temporal o si sus dependencias se moveran a Composer real.
2. Revisar scripts de prueba o diagnostico que aun viven en raiz, por ejemplo `exportar_word.php`, `word_informe_atropello_probe.php` y `ver_log_informe_atropello.php`.
3. Mover documentacion operativa a `docs/` y seguir reduciendo duplicados en la raiz del proyecto.
