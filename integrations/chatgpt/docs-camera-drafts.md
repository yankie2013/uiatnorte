## Actualización local pendiente de despliegue — autocompletar y número manual

El número del oficio se solicita siempre mediante `numero_oficio`, se muestra en la vista previa y se incluye literalmente en el Word. No se consulta ni calcula un correlativo y no se reserva o registra ningún número. Se aceptan números con ceros iniciales y sufijos simples (por ejemplo `018-B`); se conserva el texto.

Con la opción privada `camera_case_context_enabled` habilitada, la proyección de borradores recupera referencia, fiscalía, modalidades y consecuencias. Las consultas mantienen el alcance de autorización en la consulta final; modalidades y consecuencias usan sus vistas activas, ordenadas por identificador, sin duplicados y con límite de 50. No se sobrescriben datos registrados mediante argumentos de la herramienta. Solo se piden los campos ausentes, el número manual y las decisiones propias de la solicitud. No se elige automáticamente destinatario ni horario desde otro oficio del expediente.

Antes de habilitarlo hay que comprobar el esquema real y aprobar 12 permisos adicionales de SELECT por columna (47 en total). No se amplían a tablas completas ni a escritura. La opción está deshabilitada por defecto. Esta versión local no se ha desplegado. El paquete candidato previamente probado y desplegado no se ha reemplazado.

Verificación local actual: 66 comprobaciones de borradores/autocompletado con SQLite ficticio, 118 de OAuth/consultas y 43 del recorrido HTTPS con datos ficticios. Word generado y revisado visualmente. Pendientes metadatos y pruebas MariaDB con los nuevos permisos antes del despliegue.

### Documentación de la primera versión desplegada (histórico)

# Borradores de solicitud de cámaras

Implementación local, deshabilitada por defecto y sin despliegue. La producción conserva el conector existente.

## Recorrido

La herramienta opcional preparar_borrador_camaras exige OAuth y acceso actual al expediente. Obtiene una proyección explícita de código, fecha y lugar desde accidentes_activos con el mismo alcance por responsable, colaboración o archivo. El lugar pasa a ser un dato de salida para preparar el oficio: antes el conector solo devolvía el indicador de ausencia. No se modifican los permisos SQL: esa columna ya está entre las 35 concedidas.

Si faltan datos del documento, devuelve preguntas. Destinatario, cargo, entidad, localidad/fecha de emisión, referencia, fiscalía, modalidad, consecuencia y horario se aportan y confirman por el usuario en esta primera versión; no se finge haberlos consultado automáticamente en catálogos o informes. Fecha y lugar solo pueden aportarse manualmente si no están registrados; no se sobrescriben los valores existentes ni se escribe en la base.

Con todos los campos válidos devuelve una vista previa y un identificador de confirmación aleatorio válido durante cinco minutos, ligado al usuario, versión de autenticación y expediente. El modelo debe mostrar la vista previa y pedir confirmación expresa antes de llamar otra vez con expediente_id y confirmacion_id. Cambiar los datos exige preparar otra vista previa; la confirmación no puede reutilizarse. La revisión del usuario en ChatGPT sigue siendo parte del flujo: un identificador técnico no demuestra por sí solo una confirmación humana.

Al confirmar se genera un Word temporal desde una plantilla saneada y fijada por SHA-256. No admite rutas, plantillas, identificadores de usuario ni número oficial aportados por el modelo. No incluye firmas o sellos personales, datos fijos del firmante original ni una conexión a una plantilla externa de Word. Mantiene el membrete, estilos y distribución de la plantilla revisada. No registra, numera, firma ni envía oficios y no invoca OficioService::create.

La herramienta declara readOnlyHint=false porque crea archivos temporales, destructiveHint=false e idempotentHint=false. No se presenta como una consulta sin efectos, aunque la conexión de negocio siga siendo exclusivamente de lectura. No añade un permiso de escritura SQL ni nuevas columnas.

## Entrega y privacidad

La respuesta contiene un enlace HTTPS del propio sitio UIAT. Debe abrirlo el usuario en su navegador; no se ha implementado un adjunto nativo en el almacenamiento de ChatGPT. La descarga exige sesión UIAT de la misma identidad y versión, usuario activo y permisos actuales sobre el expediente. El identificador de URL, por sí solo, no permite descargarlo. Si no hay sesión, el puente usa el inicio de sesión existente. El archivo caduca a los quince minutos.

La ruta GET propuesta es /oauth-uiat/borrador?archivo=IDENTIFICADOR. Devuelve un adjunto DOCX con Cache-Control no-store/private, Referrer-Policy no-referrer y nosniff. Debe añadirse únicamente al virtualhost HTTPS de UIAT y conservar los logs privados sin consulta, Authorization, Cookie ni Referer. No se crea una ruta pública estática ni se expone el directorio de documentos.

El almacenamiento queda fuera de la raíz web, en un directorio privado 0700, con archivos 0600. Está limitado a diez temporales vivos por usuario y quinientos globalmente. Los textos, argumentos, identificadores de descarga y documentos no se registran en la auditoría. Si falla la auditoría de una generación, no se entrega el resultado y se retira el temporal correspondiente.

Los caducados se eliminan oportunistamente y se incluye una tarea de limpieza propuesta cada cinco minutos. El acceso vence exactamente según el plazo almacenado; la retirada física programada puede ocurrir en el siguiente ciclo. Si la tarea está detenida, los archivos siguen bloqueados al caducar y se requiere revisar la limpieza. No se ha instalado la tarea en producción.

## Activación pendiente

La factoría solo crea el servicio si camera_drafts_enabled es booleano true y el directorio privado ya está provisionado. Sin él se anuncian las cinco herramientas anteriores. No crea carpetas, tablas ni migraciones desde una petición web.

Archivos principales: src/CameraDrafts.php, CaseQueries.php, McpKernel.php, HttpKernel.php, public.php, assets/camera-draft-template.docx y deploy/factory.php. Se conserva la corrección CSP aprobada para permitir el retorno del consentimiento a https://chatgpt.com. El emisor de cabeceras evita duplicar Cache-Control y mantiene Set-Cookie como cabeceras independientes.

Para un futuro despliegue se requieren DOM y ZIP en PHP; una actualización revisable de fuentes/factoría/configuración; la ruta limitada de descarga; el directorio privado; y, si se aprueba, el servicio/timer de limpieza. Las verificaciones de integridad deben actualizarse con hashes del paquete nuevo: el verificador antiguo no debe usarse como si esta actualización no existiera. La reversión debe retirar la nueva ruta y la herramienta, restaurar fuentes/factoría previas y preservar la auditoría.

## Límites de esta versión

Solo solicitud de cámaras. No evalúa documentos adjuntos ni lee automáticamente vehículos, destinatarios o fiscalías. No ofrece acceso general a toda la información del expediente. El rango de video debe ser creciente dentro del mismo día del accidente; un rango que cruza medianoche requiere una ampliación posterior. El resultado debe revisarse en Word antes de cualquier uso oficial. Los textos extensos pueden modificar la paginación de la plantilla; se revisó visualmente el caso ficticio entregado.

## Pruebas

- tests/security.php: 118 comprobaciones anteriores de OAuth, permisos y revocación.
- tests/camera_drafts.php: 56 comprobaciones de datos faltantes, confirmación ligada a identidad/caso, denegaciones, fechas/horas, XML, caducidad, cuotas, descarga y auditoría. Usa una conexión SQLite de datos con query_only.
- tests/https_flow.py: 33 comprobaciones con borradores deshabilitados; 43 cuando UIAT_TEST_CAMERA_FEATURE=1, incluido Word descargable con sesión y rechazo sin ella.
- Prueba local aislada Ubuntu 24.04/Apache mod_php/PHP 8.3/MariaDB 10.11: 54 comprobaciones HTTPS con datos ficticios; la copia de prueba no contiene .env ni credenciales del proyecto. La cuenta ficticia de datos tiene las mismas 35 columnas SELECT.

Las pruebas no demuestran que ChatGPT haya descubierto o ejecutado la sexta herramienta ni que el nuevo enlace funcione en la cuenta real. Ese recorrido queda pendiente de despliegue aprobado y prueba controlada del cliente.
