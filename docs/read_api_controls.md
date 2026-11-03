# Propuesta adicional: límite previo a autenticación y privacidad de access logs

Estado: preparada y probada localmente, pendiente de aprobación de aplicación en producción. API de producción permanece apagada. No se cambia la política global de cookies.

## Presupuesto previo a sesión y base de datos

RequestLimiter se ejecuta después de comprobar habilitación, directorio privado y disponibilidad de auditoría, antes de validar método, TLS, crear sesión o abrir PDO. Presupuesto de 300 solicitudes por dirección de origen y minuto fijo. Se aplica a todas las solicitudes habilitadas, incluidas anónimas y autenticadas; el presupuesto de 60/minuto por usuario sigue separado. Usuarios que comparten NAT comparten este nuevo presupuesto. No es protección de red ni evita consumo inicial de Apache/PHP. En el límite de dos ventanas consecutivas son posibles ráfagas de hasta 600 solicitudes.

Se usa REMOTE_ADDR, nunca X-Forwarded-For. Si existe proxy/CDN, revisar cómo Apache establece REMOTE_ADDR antes de aplicar: no habilitar confianza general en cabeceras. Sin esa revisión podría agrupar todos los visitantes bajo la dirección del proxy.

Archivo único privado request-rate.json, bloqueo exclusivo, permisos 0600. Guarda ventana actual, contador y HMAC de la dirección normalizada usando el secreto dedicado existente; no guarda IP en claro, cookie, búsqueda ni secreto. Máximo 4096 claves por ventana, sin proliferación de archivos por IP. Si se alcanza presupuesto/capacidad, responde 429 y Retry-After: 60. Si falla o se corrompe el estado, responde 503 sin datos. No requiere permisos SQL adicionales ni cuenta nueva. Registra rechazos en la auditoría mínima existente.

## Access logs del sitio UIAT

Proponer un formato nuevo llamado uiat_privado:

```
LogFormat "%a %t \"%m %U %H\" %>s %b" uiat_privado
```

Aplicarlo reemplazando solo el nombre de formato de los CustomLog activos en los dos VirtualHost de UIAT, conservando sus destinos originales. El alcance es todo el sitio UIAT, no únicamente /api/, para evitar parámetros en otras rutas y referencias cruzadas. Se mantienen dirección de origen, fecha, método, ruta sin consulta, protocolo, estado y tamaño. Se excluyen consulta, Referer, User-Agent, Cookie y Authorization. No se cambia el registro de otros dominios ni se borran logs anteriores.

Revisar condiciones, redirecciones, otros logs heredados y cualquier proxy/CDN del destino. Este ajuste describe access logs, no garantiza limpieza de mensajes arbitrarios emitidos por módulos, error logs o servicios externos. No publicar resultados de registros reales.

Referencias oficiales: https://httpd.apache.org/docs/2.4/mod/mod_log_config.html (%U omite query string); https://httpd.apache.org/docs/2.4/logs.html .

## Evidencia ficticia

311 comprobaciones del limitador: presupuesto, renovación, aislamiento por IP, normalización IPv6, ausencia de IP/secreto en estado, permisos, rechazo de corrupción y capacidad máxima. Harness ampliado: 31 peticiones API HTTPS correctas, además del formulario real; incluye anónimo 429 aun con X-Forwarded-For falso y estado corrupto 503. Apache 2.4 real en contenedor temporal: formato válido y petición con marcadores de consulta, cookie y Referer registrada sin ninguno de esos marcadores. Se eliminaron los recursos temporales.

## Aplicación y reversión propuestas

Después de aprobación: respaldar _entry.php y archivos Apache fuera de la raíz web; instalar RequestLimiter.php y nueva entrada con API apagada; añadir formato y cambiar exclusivamente los CustomLog de UIAT tras revisar configuración exacta. apache2ctl configtest antes de recargar. Confirmar login 200, API 503 y acceso ficticio sin marcadores en todos los destinos de access log relevantes, sin imprimir registros existentes. Probar el rechazo de presupuesto con datos ficticios en copia de ensayo del destino, no con consultas reales ni activación general.

Reversión: mantener API apagada, restaurar entrada y configuración Apache previa, retirar únicamente la nueva clase instalada si no existía, comprobar configuración y recargar. Conservar auditoría y logs según retención; el estado de limitación no necesita borrado para revertir. La activación requiere aprobación separada.
