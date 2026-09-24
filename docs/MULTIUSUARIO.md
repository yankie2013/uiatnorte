# Perfiles y expedientes

Implementación del 24 de septiembre de 2026. Base local verificada: `uiatnorte_dev`.

## Uso

- **Usuarios y perfiles**: el administrador crea cuentas, asigna el perfil, completa grado, CIP, cargo, unidad y contacto; también puede desactivar usuarios y cambiar contraseñas. No puede desactivar/cambiar de perfil a un responsable con expedientes activos sin reasignarlos previamente.
- **Responsables y colaboración**: consulta general y búsqueda por SIDPOL, lugar, responsable, nombres/apellidos de involucrados y placa. Filtros de responsabilidad, colaboración y transferencias pendientes. El JEFE EMI comparte con adjuntos, revoca y solicita transferencias. El destinatario acepta; se cierran las colaboraciones previas. El administrador puede cambiar al responsable directamente desde la columna Responsable de la lista, indicando un motivo. Se muestra grado y nombre tanto en la lista como en los selectores. El cambio conserva la autoría original y cierra las colaboraciones y transferencias pendientes.
- **Comunicaciones de guardia**: ficha preliminar independiente. Se guarda el instante original en el servidor. Su autor puede corregirla hasta antes de cumplirse 12 horas, aunque haya sido asignada. El límite no depende de la hora de llamada declarada. La asignación de una llamada todavía pendiente sigue disponible después del plazo; no permite editar su contenido. La apertura del expediente es única, transaccional y requiere ubigeo y JEFE EMI activo. La fecha del accidente queda pendiente, sin utilizar la hora de llamada como si fuera la del hecho.
- **Eliminación**: solo administrador. Los expedientes y comunicaciones usan eliminación lógica. Los expedientes se restauran desde el filtro Eliminados; las comunicaciones, desde Eliminadas. Sus documentos se conservan y se excluyen de las consultas operativas mientras el expediente está eliminado. Los borrados heredados de registros individuales siguen siendo físicos, exclusivos del administrador, con copia de valores en auditoría; no tienen restauración automática en pantalla.
- **Estadísticas de gestión**: administrador. Período por fecha de registro, conteos, estados, carga por responsable y demora registro–asignación de comunicaciones. CSV agregado sin nombres ni identificadores personales. El mapa distingue ubicación preliminar/verificada; cambiar coordenadas invalida la verificación anterior.

## Permisos

Todos los perfiles activos pueden consultar expedientes. Secretaría y perfiles antiguos `viewer`/`editor` no escriben. JEFE EMI solo edita expedientes a su cargo; ADJUNTO solo aquellos compartidos activamente. Guardia escribe exclusivamente su ficha inicial durante el plazo establecido. Administrador tiene gestión global y eliminación.

Los cambios de personas o vehículos compartidos requieren autorización sobre **todos** sus expedientes vinculados; no basta presentar un `accidente_id` distinto. Los datos nuevos sin vínculos quedan asociados al creador. Las bajas de relaciones internas (modalidad/consecuencia y composición de un acta) son parte de la edición del expediente, no la eliminación de un expediente o documento autónomo.

## Migración y respaldo

Respaldo local anterior a los cambios:
`/Users/giancarlo/.codex/backups/uiatnorte/antes_multiusuario_20260924_112734.sql`

La copia contiene tablas, datos, rutinas/disparadores existentes y definiciones de vistas. Una vista de exportación anterior ya tenía referencias inválidas; su definición se conservó por separado dentro del mismo SQL.

La migración asignó los 24 expedientes existentes al usuario 2, Giancarlo Jorge MERINO SANCHO, perfil `jefe_emi`, grado ST3.PNP, cargo JEFE EMI, unidad DEPIAT. Conservó correo y contraseña. No inventó una cuenta creadora histórica. La asignación inicial está documentada en auditoría.

En otro entorno, respaldar primero y verificar el ID real de esa cuenta. Ejecutar **antes de habilitar el nuevo código**:

```sh
php docs/scripts/migrar_multiusuario.php --owner=2
```

La migración es reejecutable; conserva asignaciones realizadas posteriormente. Requiere MySQL 8 y permisos para crear vistas, funciones y disparadores. El DDL de MySQL no es transaccional: mantener la aplicación sin tráfico durante instalación/actualización. No se ha desplegado a un servidor remoto.

## Protección y trazabilidad

`Database::connection()` establece `@actor_id` desde la sesión y vuelve a consultar el usuario activo. Las funciones/disparadores `rbac_*` protegen las escrituras incluso en formularios heredados con SQL directo. No retirar estos disparadores: forman parte del control de acceso, no son opcionales. Las banderas de asignación solo se establecen internamente por los servicios, no a partir de formularios.

`auditoria` guarda actor, fecha, tabla, registro y valores anteriores/posteriores, excluyendo hashes de contraseña. El historial reciente se consulta en cada expediente. Los permisos de transferencia se comprueban bajo bloqueo de la fila; las operaciones de asignación son transaccionales. Los formularios y AJAX internos incluyen protección CSRF.

Las consultas operativas usan vistas `*_activos`. Los registros eliminados permanecen en tablas base para restauración/auditoría. Las relaciones existentes y los IDs no cambian.

Las plantillas se personalizan **en una copia temporal antes de incorporar datos de los involucrados**: se sustituye la identidad fija del instructor sin modificar las plantillas originales ni los documentos guardados. Oficios, actas, citaciones y manifestaciones conservan un perfil histórico del responsable; los documentos nuevos toman el perfil de su expediente. Completar CIP y teléfono en Usuarios y perfiles para usarlos en documentos nuevos. Los datos no consignados se señalan como tales.

## Verificación

```sh
php tests/multiusuario_integration.php --owner=2
```

Crea una base temporal `uiat_rbac_test_*`, copia tablas, instala controles y elimina la copia al terminar. No inserta usuarios ni expedientes de prueba en la base de trabajo. Comprueba permisos con SQL directo y servicios, revocación, transferencias, límites de guardia, asignación duplicada, eliminación/restauración, ubicación y firma histórica en XML Word.

## Acceso por CIP y primer ingreso

Las cuentas nuevas requieren CIP numérico único (conserva ceros iniciales). Se ingresa con CIP y contraseña inicial igual al CIP, almacenada como hash. El alta ya no pide elegir una contraseña al administrador.

El primer acceso solo crea una sesión temporal de 15 minutos para cambiar contraseña: no habilita consultas ni edición. La nueva contraseña requiere al menos 10 caracteres, mayúscula, minúscula, número y carácter especial, con máximo de 72 bytes. Se pide confirmación y se rechaza reutilizar la inicial. Después del cambio se habilita la sesión normal y el CIP deja de funcionar como contraseña.

Las cuentas existentes conservan sus contraseñas. El correo sigue disponible como identificador para evitar bloquear cuentas antiguas sin CIP. Completar el CIP real desde Usuarios y perfiles; no se deduce de documentos ni se inventa un número para el administrador.

El restablecimiento administrativo aplica la misma composición y obliga a cambiar la contraseña al ingresar. Una versión de credenciales invalida sesiones anteriores. Ni la auditoría ni las respuestas de pantalla incluyen contraseñas o hashes.

La migración principal incluye `acceso_cip_schema.php`: agrega CIP único, `must_change_password` y `auth_version` sin restablecer claves existentes. Ejecutar la migración con la aplicación en mantenimiento antes de habilitar esta versión. Las pruebas de integración incluyen primer acceso, composición, CIP duplicado, vencimiento, desactivación, reutilización y bloqueo de cambios de perfil durante el cambio de contraseña.


### Espacio de trabajo y consulta general

El resumen general, sus indicadores y años disponibles, la lista de accidentes
(incluso «Ver todos», favoritos y contadores por comisaría) y el mapa filtran en
el servidor por el usuario autenticado. El administrador ve todos los expedientes
activos; JEFE EMI ve aquellos de los que es responsable; ADJUNTO ve sus
colaboraciones vigentes; guardia ve los expedientes originados en sus comunicaciones.
Los perfiles exclusivamente de consulta no tienen expedientes personales.
Una transferencia cambia el espacio personal cuando el destinatario la acepta;
revocar una colaboración retira inmediatamente el expediente del espacio del adjunto.
La consulta institucional continúa en «Responsables y colaboración»
(`gestion_expedientes.php`), también enlazada desde el resumen como «Consulta general».
Este filtro no modifica responsables ni registros existentes.
