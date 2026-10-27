# Configuración local preparada — UIAT NORTE

Se preparó una copia de ensayo independiente con datos exclusivamente ficticios. La aplicación original y producción no se modificaron ni habilitaron.

## Recursos preparados

- Copia mínima del proyecto: `work/uiat-api-local/site/` dentro del espacio de trabajo de esta conversación.
- Base MySQL local nueva, con nombre aleatorio terminado en `_test`; no se reutilizó ninguna base existente.
- Cuenta API nueva: SELECT por columnas sobre las dos vistas activas y los metadatos necesarios para autenticar y autorizar.
- Cuenta de login separada, limitada a la tabla de usuarios de esta base ficticia.
- Archivo de configuración de la copia: `work/uiat-api-local/site/.env.local`, permisos 0600. No contiene credenciales administrativas.
- Auditoría privada: `work/uiat-api-local/private/`, permisos 0700; archivo 0600.
- Sesiones privadas: `work/uiat-api-local/sessions/`, permisos 0700; cookies Secure, HttpOnly, SameSite=Strict.
- Certificado HTTPS local de ensayo, no instalado en el sistema; validez de un día. Debe regenerarse para una prueba posterior si caduca.
- Credenciales ficticias de demostración: `work/uiat-api-local/demo-login.json`, permisos 0600. No se incluyen en este documento ni en el repositorio.
- Inventario de recursos de ensayo: `work/uiat-api-local/sandbox.json`.

Todas estas rutas se encuentran bajo `/Users/giancarlo/Documents/Codex/2026-10-26/referenced-chatgpt-conversation-this-is-an/`. La carpeta de ensayo tiene permisos 0700 y está fuera de la carpeta web de MAMP. No se inició ningún servidor para esa copia.

## Verificación realizada

Se comprobó lectura de los dos expedientes activos inventados; rechazo de UPDATE, lectura de la tabla base y lectura de hashes; revisión de privilegios sin escritura, FILE, EXECUTE ni GRANT OPTION; escritura y bloqueo de auditoría; y autenticación del usuario ficticio con la cuenta separada de login. No se imprimieron secretos.

`UIAT_READ_API_ENABLED=0` en la copia. No se modificó `.env.local` del proyecto original. La cuenta API no utiliza root ni hereda sus privilegios. Las credenciales administrativas de MAMP se utilizaron únicamente en memoria para provisionar recursos de ensayo, y no se guardaron.

## Alcance antes de producción

Esta preparación corresponde a MAMP local. No constituye una configuración revisada de producción. Antes de preparar producción deben conocerse el destino real, versión y definiciones de vistas, usuario del proceso PHP, terminación HTTPS y ruta privada de auditoría, además de retención y política de logs. El despliegue o cualquier cambio en producción requiere aprobación explícita.

## Reproducción

El harness `tests/read_api_https.py` acepta `--isolated-local --prepare-local RUTA_NUEVA` para preparar otra copia privada e inactiva con datos ficticios. Rechaza rutas existentes y las situadas dentro del proyecto o de la carpeta web de MAMP. Esta modalidad conserva su base y cuentas de ensayo; la modalidad normal elimina todos sus recursos temporales al finalizar. No usar credenciales de producción para ninguna modalidad.
