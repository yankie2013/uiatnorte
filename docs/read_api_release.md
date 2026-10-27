# Revisión previa a habilitar la API

Estado: implementación local validada; API original deshabilitada. Solo la copia privada con datos ficticios se habilitó para demostración. Este documento no autoriza despliegue, cambios de permisos ni consultas de datos reales.

## Evidencia disponible

- 43 comprobaciones de lógica y autorización con SQLite en memoria.
- 15 comprobaciones de privilegios sobre MySQL 8.0.44 y otras 15 sobre MariaDB 11.4.13, con bases y cuentas efímeras.
- 29 peticiones API HTTPS por motor, además del formulario real de login: CIP/correo, rechazo de contraseña y CSRF inválidos, rotación de sesión, aislamiento y auditoría.
- Navegador en copia ficticia: búsqueda, expediente propio y diligencias devuelven 200; expediente ajeno devuelve 404 sin datos.
- Sin modificación ni consulta de registros de producción. No se incluyen secretos, sesiones, certificados ni copias de bases en Git.

## Información necesaria del destino

Registrar en un canal privado el host/dominio, ruta de instalación y versión de PHP/MariaDB. No copiar contraseñas, .env, hashes, cookies ni registros policiales a chats, incidencias o Git. Verificar primero que se está inspeccionando el servidor correcto y acordar ventana y responsable del despliegue.

Solo después de autorizar la revisión del destino, un administrador puede inspeccionar metadatos con su acceso habitual. Sustituir el nombre de esquema en los siguientes ejemplos. No ejecutar migraciones ni cambios de permisos durante esta revisión.

```sql
SELECT VERSION();
SELECT TABLE_NAME, SECURITY_TYPE, IS_UPDATABLE
FROM information_schema.VIEWS
WHERE TABLE_SCHEMA = 'ESQUEMA_A_REVISAR'
  AND TABLE_NAME IN ('accidentes_activos','diligencias_pendientes_activos');

SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'ESQUEMA_A_REVISAR'
  AND TABLE_NAME IN ('accidentes_activos','diligencias_pendientes_activos',
                    'usuarios','expediente_colaboradores','expediente_transferencias')
ORDER BY TABLE_NAME, ORDINAL_POSITION;
```

Revisar las definiciones de las vistas en el servidor: deben excluir filas eliminadas y no reintroducirlas por joins. El definidor y SQL SECURITY deben permitir la lectura con la cuenta dedicada sin acceso directo a tablas base. Contrastar los campos y las reglas de responsable/colaborador/archivo con `docs/read_api.md`. La comprobación local utiliza un esquema ficticio reducido y no garantiza que el destino coincida.

## Antes de una propuesta de habilitación

- Cuenta nueva dedicada sin privilegios globales ni roles heredados. Revisar privilegios efectivos en el servidor; no exportar SHOW GRANTS completo, que en algunas versiones puede incluir material de autenticación.
- Cuenta API con SELECT únicamente en las columnas documentadas. No reutilizar root ni el usuario de escritura de la aplicación.
- Directorio de auditoría fuera de todas las raíces web, propietario del proceso PHP y permisos 0700; archivos 0600. Definir rotación, retención y responsable de monitoreo.
- HTTPS y cookies Secure/HttpOnly/SameSite; revisar terminación TLS y señal HTTPS confiable del servidor. No confiar en cabeceras enviadas por el cliente.
- Acceso local al motor desde PHP; para un motor remoto se requiere implementar y verificar TLS de base de datos antes de habilitar esta versión.
- Acceso directo por URL a .env, fuentes auxiliares y pruebas bloqueado por el servidor. No publicar tests ni docs/scripts. Proxies y service worker sin caché para /api/.
- Logs sin cookies, claves ni parámetros de consultas. Límites anónimos en el servidor y límites autenticados disponibles en la API.
- APP_DEBUG desactivado. Variables nuevas revisadas sin imprimir secretos. API permanece apagada durante la preparación.
- Sesiones anteriores al cambio requieren nuevo login. API vence por inactividad a los 15 minutos y de forma absoluta a las 8 horas.

## Aprobación y reversión

La propuesta debe identificar revisión Git exacta, destino, archivos a instalar, cuenta y privilegios a crear, configuración a modificar y validación prevista. Presentarla sin credenciales para aprobación explícita antes de cualquier cambio de producción.

Habilitar primero en un entorno de ensayo del destino con usuarios y expedientes ficticios. Repetir autenticación, rechazo por usuario inactivo/versión, expediente ajeno, revocación, eliminados y fallo de auditoría. La prueba de rechazo de escrituras se realiza exclusivamente en una base ficticia, nunca contra registros reales.

Plan de reversión a aprobar: apagar UIAT_READ_API_ENABLED en el entorno correspondiente; confirmar 503 sin datos; revocar la cuenta nueva si procede; preservar auditoría según su política; revertir los archivos a la revisión previa. No borrar registros ni ejecutar restauraciones sobre producción como parte de una prueba.
