# Primera API de consulta UIAT NORTE

Implementación local, deshabilitada por defecto. No se ejecutaron migraciones ni GRANT sobre bases existentes. Los GRANT de pruebas se limitaron a cuentas y bases ficticias efímeras. No desplegar ni habilitar producción sin aprobación explícita.

## Contrato

Solo GET con cookie de sesión del inicio de sesión existente, HTTPS directo y mismo sitio. No hay autenticación mediante usuario_id, rol, token en URL ni clave compartida. No se habilita CORS. Los consumidores externos necesitan un diseño posterior de autenticación delegado; esta versión está destinada al frontend autenticado.

- `api/v1/buscar_expedientes.php?q=FIC&limite=20`: prefijo de SIDPOL o registro SIDPOL, mínimo 3 caracteres.
- `api/v1/consultar_expediente.php?expediente_id=1`
- `api/v1/consultar_diligencias.php?expediente_id=1&limite=20`

Listados: máximo 50 filas, orden estable por id, `siguiente_id` para enviar como `despues_id`. Sin totales globales. Parámetros desconocidos o arrays devuelven 422. Respuestas JSON con `ok`, `solicitud`, `datos`; errores sin SQL ni detalles internos. 401 sesión inválida; 403 perfil/transporte no autorizado; 404 tanto expediente ajeno como inexistente; 405 método; 429 límite; 503 configuración/auditoría no disponible. La búsqueda devuelve únicamente coincidencias autorizadas.

Campos de expediente: id, sidpol, registro_sidpol, fecha_accidente, estado. Diligencias: id, accidente_id, tipo_diligencia_id, estado. Sin personas, DNI, placas, coordenadas, teléfonos, narrativas, contenido, rutas ni documentos.

## Decisiones de acceso

El código existente separa el buscador general del espacio de trabajo (`App\Support\Access`). Esta API adopta deliberadamente el acceso más restringido por expediente:

| Perfil | Alcance |
| --- | --- |
| admin | Expedientes activos |
| jefe_emi | Responsable actual |
| adjunto | Colaboración sin revocación |
| secretaria / administracion | Transferencia de archivo aceptada a ese usuario |
| guardia / viewer / editor / desconocido | Denegado |

Toda búsqueda filtra en SQL; diligencias vuelve a aplicar el alcance en la consulta final. Las vistas `accidentes_activos` y `diligencias_pendientes_activos` excluyen eliminados. Se consulta la tabla de usuarios en cada petición y se exige activo, sin cambio de contraseña pendiente y `auth_version` vigente. La API requiere nuevo login tras instalar esta versión: las sesiones antiguas no tienen marca de autenticación. Caducidad API: 15 minutos sin uso y 8 horas absolutas. No prolonga esa caducidad mediante actividad en otras páginas. El login existente regenera la sesión; el cambio en LoginService solo agrega y limpia marcas de tiempo API.

## Configuración pendiente, sin secretos en el repositorio

Provisionar fuera del document root un directorio privado con propietario del proceso PHP y permisos 0700. Configurar en el entorno del proceso PHP:

```
UIAT_READ_API_ENABLED=1
UIAT_READ_API_DSN=mysql:host=127.0.0.1;port=3306;dbname=uiatnorte_test;charset=utf8mb4
UIAT_READ_API_USER=uiat_api_reader
UIAT_READ_API_PASSWORD=<provisionar fuera del repositorio>
UIAT_READ_API_PRIVATE_DIR=<ruta absoluta privada fuera del document root>
SESSION_SECURE_COOKIE=1
SESSION_SAME_SITE=Strict
```

La conexión dedicada no usa las credenciales de Database::connection ni permite fallback. Ajustar DSN únicamente tras revisar el destino. No se han configurado variables ni creado una cuenta para la API del proyecto; las cuentas y variables de las pruebas fueron temporales. Requiere columnas de seguridad `usuarios.must_change_password` y `usuarios.auth_version`: falla cerrado si faltan. Para bases remotas exigir TLS verificado en la conexión antes de habilitar; esta primera versión está prevista para MariaDB local al servidor PHP.

Revisar manualmente y conceder a una cuenta nueva solamente SELECT sobre:

- `accidentes_activos`: id, sidpol, registro_sidpol, fecha_accidente, estado, responsable_id.
- `diligencias_pendientes_activos`: id, accidente_id, tipo_diligencia_id, estado.
- `usuarios`: id, rol, activo, must_change_password, auth_version.
- `expediente_colaboradores`: accidente_id, usuario_id, revocado_en.
- `expediente_transferencias`: accidente_id, destino_id, tipo, estado.

No conceder SELECT global, acceso a tablas base de expedientes/diligencias, INSERT/UPDATE/DELETE, FILE, EXECUTE, DDL ni GRANT OPTION. El administrador debe verificar `SQL SECURITY` y el definidor de las vistas para permitir leerlas sin privilegios sobre sus tablas base. Revisar `SHOW GRANTS` de esa cuenta y probar que una escritura es rechazada en una base ficticia antes de habilitar. No se incluye un script ejecutable de creación de usuarios ni contraseñas.

## Auditoría y operación

`audit.jsonl`, permisos 0600, bloqueo exclusivo y escritura antes de emitir datos. Registra UTC, identificador de petición aleatorio, usuario revalidado (o null), endpoint fijo y estado HTTP. No registra búsquedas, expedientes solicitados, respuestas, IP, cookies, credenciales ni errores de PDO. Si falla la escritura, se descartan los datos y se responde 503. Si la API está deshabilitada o falta el directorio no puede registrar; en ese caso nunca consulta datos. Los intentos previos a autenticarse llevan usuario null.

Límite compartido por usuario: 60 peticiones/minuto mediante archivo privado con bloqueo (no solo por sesión). El servidor/reverse proxy debe añadir límites a solicitudes anónimas. Restringir el acceso de lectura a la auditoría, definir retención y rotación externa y vigilar disponibilidad/espacio del directorio. Desactivar parámetros y cookies en los access logs de estas rutas. Revisar que service worker y proxies nunca almacenen `/api/`; se envía Cache-Control no-store. TLS se verifica con HTTPS del servidor y no con cabeceras reenviadas proporcionadas por el cliente. Si hay terminación TLS en proxy, configurar una señal confiable del servidor antes de habilitar; no eliminar esta verificación.

## Validación

`/Applications/MAMP/bin/php/php8.3.30/bin/php tests/read_api.php`

Base SQLite efímera con vistas y datos inventados; no carga bootstrap, .env ni una conexión de producción. Cubre aislamiento, colaboraciones revocadas, archivo pendiente/aceptado, borrado lógico, paginación, campos mínimos, inyección, validación, caducidad, usuario desactivado, versión y perfil. No reemplaza una prueba MariaDB de permisos reales y vistas en una base ficticia antes de habilitar. El formulario de login se validó sobre una copia aislada con usuarios ficticios; queda la revisión del entorno de destino antes de habilitar.

Referencia PDO: https://www.php.net/manual/en/pdo.prepare.php (consultas preparadas nativas y parámetros separados).

## Prueba local adicional

Se ejecutó `php tests/read_api_mysql.php --isolated-local` en MAMP, motor MySQL 8.0.44. Pasaron 15 comprobaciones con una base y cuenta efímeras creadas exclusivamente para datos ficticios; ambas fueron eliminadas al finalizar. Se verificaron consultas nativas, aislamiento, revocación y rechazo de escritura, DDL, lectura de tablas base y hashes de contraseña. No se consultaron bases existentes ni se cargó .env.

El script admite credenciales administrativas de prueba mediante UIAT_TEST_ADMIN_USER y UIAT_TEST_ADMIN_PASSWORD; por defecto usa la configuración estándar local de MAMP. Nunca imprime credenciales ni errores SQL. Requiere ejecución explícita por CLI con --isolated-local. Esta primera prueba se amplió posteriormente con el formulario real y MariaDB temporal, según el resultado final al cierre de este documento. La API continúa deshabilitada.

## Prueba integral HTTPS completada

`python3 tests/read_api_https.py --isolated-local`: 23 peticiones correctas mediante un servidor TLS temporal y PHP CGI real, con certificado local verificado por el cliente y MySQL 8.0.44. Se copiaron únicamente fuentes necesarias a un directorio temporal, sin .env, sesiones existentes ni archivos de usuarios. Las sesiones ficticias se crearon fuera de HTTP usando el inicializador de sesión real LoginService::beginSession; no se añadió una ruta pública para generar sesiones. En la primera ejecución se usaron sesiones inicializadas fuera de HTTP; la ampliación posterior sí probó el formulario y la validación de contraseña.

Casos: los tres endpoints autorizados; rechazo anónimo y por perfil; expediente ajeno/inexistente/eliminado; revocación; archivo pendiente/aceptado; inactividad y vencimiento absoluto; sesión antigua; usuario desactivado; cambio de auth_version; método no permitido; origen cross-site; parámetros desconocidos; límite compartido entre sesiones; auditoría mínima y rechazo cuando su almacenamiento falla. Cookies Secure, HttpOnly y SameSite=Strict, JSON y no-store comprobados.

Al terminar se eliminaron base, cuenta, sesiones, certificado y directorios ficticios. El harness transmite HTTPS=on exclusivamente desde su servidor TLS de pruebas a CGI; no modifica Apache/MAMP, certificados del sistema ni la configuración real. El service worker excluye expresamente /api/ de CacheStorage y actualiza la versión de caché estática.

La ampliación final validó el formulario y MariaDB 11.4.13 en un entorno temporal; sigue pendiente revisar la versión, esquema, vistas y configuración del entorno de destino. No se activó la API local del proyecto ni se desplegó.

## Resultado final de la validación local — 27 de octubre de 2026

Se amplió el harness HTTPS para servir el `login.php` real con `Auth`, `Access`, `Database`, `UserRepository` y `LoginService` originales sobre una copia temporal sin .env ni datos reales. El formulario rechazó contraseña incorrecta y CSRF inválido; permitió autenticación por CIP y correo; rotó el identificador de sesión; invalidó el identificador anterior; la sesión resultante consultó el expediente propio y recibió 404 para el ajeno. Se utilizó una cuenta de prueba separada para el login; la cuenta API mantuvo sus permisos mínimos y no recibió acceso al hash de contraseña.

Resultados: 43 comprobaciones en SQLite; 15 comprobaciones de permisos en MySQL 8.0.44; 29 peticiones HTTPS con MySQL y formulario real. Se repitieron las 15 comprobaciones de permisos y las 29 peticiones HTTPS en MariaDB 11.4.13, mediante un contenedor temporal publicado exclusivamente en 127.0.0.1 y con almacenamiento efímero. Todas pasaron. Las peticiones del formulario son adicionales a las 29 peticiones API contabilizadas por el harness.

Los scripts aceptan UIAT_TEST_PORT para conexión TCP exclusivamente a 127.0.0.1 y credenciales administrativas de prueba por entorno; sin esa variable usan el socket local MAMP. Se ejecutan solo con --isolated-local. Cada ejecución crea sus propias bases y usuarios aleatorios, y los elimina al terminar. El contenedor MariaDB también fue eliminado, sin volúmenes persistentes. No se accedió a datos ni credenciales del proyecto.

La API permanece deshabilitada en el proyecto. Para habilitarla en el entorno elegido falta revisar su versión real de MariaDB, definiciones de vistas, privilegios efectivos, HTTPS/cookies, ubicación y rotación de auditoría, y configuración de logs. Ninguna configuración de producción fue modificada. El despliegue o habilitación en producción requiere aprobación explícita del usuario.

## Configuración local preparada

Se provisionó una copia privada e inactiva con base y cuentas nuevas, exclusivamente ficticias. Ver `docs/read_api_local.md` para inventario y verificaciones. La aplicación original no cambió su configuración y la API no se habilitó.
