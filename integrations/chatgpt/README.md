# Conector UIAT NORTE — fuentes de la versión v6

Esta carpeta reúne las fuentes del paquete `uiat-necropsia-fiel-candidato-v6.tar.gz`, identificado por el usuario como versión final instalada mediante `corregir-necropsia-fiel-uiat-v6.py`. Incluye búsqueda ampliada v4, consulta integral v5 y corrección fiel de necropsia v6. La corrección general de sellos v3 sigue excluida. No se ha consultado producción para certificar su estado actual.

La entrada `public.php` exige habilitación explícita y configuración privada fuera de la raíz web. Ningún commit habilita el servicio. El repositorio no constituye un paquete para copiar a una raíz pública: contiene pruebas, documentación y herramientas administrativas.

## Reconstrucción

Requiere PHP 8.3, Composer 2 y las extensiones PDO SQLite/MySQL, OpenSSL, mbstring, DOM/XML y ZIP. Python 3, OpenSSL CLI y PHP CGI se usan en pruebas HTTPS. En la raíz del proyecto y en esta carpeta, instalar dependencias desde sus respectivos locks:

```sh
composer install --no-dev --no-scripts --no-plugins
composer --working-dir=integrations/chatgpt install --no-dev --no-scripts --no-plugins
python3 integrations/chatgpt/release/verify.py
```

Ejecutar estos comandos desde la raíz del repositorio. Las dependencias descargadas, configuración privada, claves, auditoría y estado OAuth no se versionan. Los DOCX de `assets/` son plantillas fuente con marcadores, recursos institucionales y pies originales, necesarios para reproducir la salida; no son documentos generados de expedientes.

## Pruebas ficticias

```sh
php tests/read_api.php
php tests/read_api_request_limit.php
php integrations/chatgpt/tests/security.php
php integrations/chatgpt/tests/camera_drafts.php
php integrations/chatgpt/tests/office_drafts.php
php integrations/chatgpt/tests/necropsia_fidelity.php
php integrations/chatgpt/tests/search_queries.php
php integrations/chatgpt/tests/integral_queries.php
python3 integrations/chatgpt/tests/https_flow.py
UIAT_TEST_CAMERA_FEATURE=1 python3 integrations/chatgpt/tests/https_flow.py
```

Las pruebas PHP anteriores crean exclusivamente SQLite en memoria o archivos dentro de directorios temporales. Los tres fixtures SQL se cargan solo allí. No ejecutar esos SQL en una base real. No cargar el bootstrap de la aplicación para probar consultas contra su base configurada.

El arnés HTTPS del conector crea claves, sesiones, certificados e identidades ficticias; usa PHP de MAMP por defecto. Se puede configurar `UIAT_TEST_PHP` y `UIAT_TEST_PHP_CGI`. Limpiar variables `UIAT_*` heredadas antes de correr pruebas y establecer únicamente las opciones de ensayo necesarias.

Las pruebas MySQL `tests/read_api_mysql.php --isolated-local` y `tests/read_api_https.py --isolated-local` requieren una instancia **desechable y aislada** previamente creada, con un puerto loopback exclusivo indicado mediante `UIAT_TEST_PORT`. Nunca usar su socket predeterminado de MAMP ni credenciales de producción. `UIAT_TEST_ADMIN_USER` y `UIAT_TEST_ADMIN_PASSWORD` deben pertenecer solo a esa instancia temporal. Eliminar toda la instancia al terminar. No ejecutar instaladores ni aprovisionamiento para verificar la sincronización.

## Integridad y mantenimiento

`release/v6-manifest.json` conserva los SHA-256 del paquete original, de sus 41 archivos y del instalador. Todos los archivos funcionales coinciden byte a byte con el paquete. Solo dos pruebas tienen ajustes locales registrados en `test_only_overrides`: el recuento HTTPS incluye la herramienta integral, y la muestra de necropsia usa una ruta temporal por defecto. El verificador comprueba ambos conjuntos de hashes.

`release/corregir-necropsia-fiel-uiat-v6.py` es una referencia del instalador aprobado, conservada sin cambios. No se ejecutó ni es parte de un flujo automático. Sus acciones de despliegue, reversión, recarga y comprobación en servidor requieren una tarea y autorización separadas. `deploy/install.py` corresponde al aprovisionamiento histórico, no a una actualización automática de esta versión. `tests/deployment.py` requiere un contenedor root desechable y no se ejecutó en el equipo anfitrión.

Los documentos históricos `docs-camera-drafts.md` y `docs/read_api*.md` deben leerse junto con [la revisión de sincronización](../../docs/sync_uiat_v6.md); no acreditan el estado actual de producción.
