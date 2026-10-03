# Despliegue de los cambios multiusuario y copias históricas

Este procedimiento cubre el código hasta `08daa4d1` más la migración de producción
incluida en este documento. Debe llegar todo mediante Git antes de ejecutarlo.
No se ha inspeccionado el VPS: su ruta, rama, versión desplegada y servicio web
siguen pendientes de confirmar. No garantiza compatibilidad con migraciones
anteriores que el servidor no tenga. Si un paso falla, detenerse y mantener el
sitio en mantenimiento; no ejecutar el resto ignorando el error.

## 1. Publicar desde desarrollo

Revisar el diff y subir estos archivos con el resto de cambios ya confirmados:

```sh
git add docs/scripts/migrar_persona_snapshots.php docs/scripts/migrar_persona_snapshots_desarrollo.php docs/DESPLIEGUE_VPS_2026_10_01.md
git commit -m "Preparar migracion de copias historicas para produccion"
git push
```

No subir `.env.local`, los JSON de `google/`, sesiones ni el SQL de la base local.

## 2. Preparar el VPS y respaldar antes del pull

Hacerlo en una ventana sin escrituras, con el sitio en mantenimiento y sus tareas
programadas pausadas. El método para mantenimiento depende de Apache/Nginx/panel;
no detener todos los sitios del VPS por asumir un servicio. Mantener esa condición
hasta terminar las comprobaciones. Usar PHP CLI de la misma versión que el sitio.
La migración multiusuario existente requiere MySQL 8 y permisos de DDL, vistas,
funciones y triggers; verificarlo antes de aplicar.

Estos valores son ejemplos de posición: sustituirlos por los reales. Las
credenciales de respaldo deben poder leer todas las tablas, vistas y rutinas.

```bash
set -euo pipefail
APP_DIR='/RUTA/REAL/uiatnorte'
DB_HOST='HOST_MYSQL_DEL_VPS'
DB_PORT='3306'
DB_NAME='BASE_REAL_DE_PRODUCCION'
DB_USER='USUARIO_MYSQL_DE_RESPALDO'
cd "$APP_DIR"
git status --short
git log -1 --oneline
php -v
mysql --version

umask 077
BACKUP_DIR="$HOME/uiatnorte-backups/$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"
git rev-parse HEAD > "$BACKUP_DIR/commit-anterior.txt"
tar --exclude=.git -czf "$BACKUP_DIR/aplicacion.tar.gz" -C "$APP_DIR" .
mysqldump -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p \
  --single-transaction --routines --triggers --events --no-tablespaces \
  "$DB_NAME" > "$BACKUP_DIR/base.sql"
test -s "$BACKUP_DIR/base.sql"
tar -tzf "$BACKUP_DIR/aplicacion.tar.gz" > /dev/null
```

La contraseña se solicita de forma interactiva. El respaldo queda fuera del sitio
y contiene información sensible. Verificar que ambos comandos finalizaron sin
error; el tamaño del archivo no sustituye comprobar una restauración en una base
separada. Si hay tablas que no sean InnoDB, mantener también detenidas todas sus
escrituras durante el dump. No usar `--force` para omitir errores de vistas.
Si `git status` muestra cambios locales, revisarlos antes del pull, sin borrarlos.

## 3. Código y configuración

```bash
git pull --ff-only
test -f docs/scripts/migrar_persona_snapshots.php
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
composer check-platform-reqs --no-dev
```

Usar `install`, no `update`: se respeta `composer.lock`. Composer no se debe
omitir por encontrar una carpeta `vendor` antigua. Mantener los propietarios y
permisos del despliegue existente; no usar `chmod -R 777`.

Conservar el `.env.local` del VPS. Este archivo prevalece sobre `.env`. Confirmar
que DB_HOST/DB_PORT/DB_NAME/DB_USER/DB_PASS corresponden a producción y establecer:

```dotenv
DB_LOCAL_ONLY=false
APP_DEBUG=false
```

No copiar el puerto 8889, el sufijo `_dev` ni las credenciales de MAMP. Tampoco
copiar los tokens Google locales sobre los de producción. Conservar `uploads/`,
plantillas personalizadas, sesiones y demás archivos persistentes. El usuario PHP
necesita escritura en las carpetas operativas que ya usa la aplicación.

## 4. Revisar e instalar la estructura

Primero el diagnóstico existente, de solo lectura:

```bash
php docs/scripts/diagnosticar_multiusuario.php
```

Revisar los `FALTA` y errores antes de seguir. Si faltan tablas antiguas de módulos,
no importar todos los SQL de `docs/sql/`: determinar primero la migración faltante.
Debe existir un administrador activo con rol `admin`. No convertir cuentas ni
reasignar expedientes con `--owner=2`: ese ID solo correspondía al entorno local.

El orden siguiente actualiza multiusuario, crea el catálogo auxiliar, instala
las copias históricas y finalmente regenera auditoría incluyendo columnas nuevas:

```bash
php docs/scripts/migrar_multiusuario.php --schema-only
php docs/scripts/migrar_multiusuario.php --schema-only --apply

php -r 'require "bootstrap/app.php"; $pdo=\App\Database\Database::connection(); $pdo->exec(file_get_contents("docs/sql/migracion_catalogo_aportaciones.sql")); echo "Catalogo auxiliar disponible\n";'

php docs/scripts/migrar_persona_snapshots.php --database="$DB_NAME"
php docs/scripts/migrar_persona_snapshots.php --database="$DB_NAME" --apply

php docs/scripts/migrar_multiusuario.php --schema-only --apply
```

### Pase a Archivo (referencias de informe y oficio)

Después del `git pull`, instalar primero la recepción de Archivo y luego las
referencias de remisión. La primera migración asegura que exista la columna
`expediente_transferencias.tipo` y actualiza los disparadores; la segunda agrega
`informe_remision` y `oficio_remision`. El código de `accidente_listar.php`
consulta estas columnas; Git no modifica tablas MySQL.

```bash
php docs/scripts/migrar_recepcion_archivo.php
php docs/scripts/migrar_referencias_archivo.php
php docs/scripts/migrar_referencias_archivo.php --apply
php docs/scripts/migrar_referencias_archivo.php
```

Ejecutar los comandos después del respaldo de MySQL. El segundo informa las
referencias pendientes sin escribir; el tercero es repetible y también completa
los números de informe históricos que tengan formato válido. El último debe
indicar que no hay columnas pendientes. Si falta el esquema multiusuario,
completar antes su instalación indicada arriba.

Antes de añadir `--apply` a snapshots, comprobar el destino que muestra el comando
anterior. La cuenta configurada en la aplicación necesita los permisos de migración
mencionados; si falla por privilegios, corregir el acceso con el administrador de
MySQL, sin desactivar las reglas RBAC ni cambiar variables globales a ciegas.

La migración de snapshots agrega `numero_hijos` a personas y copias variables a
involucrados, familiares, efectivos policiales y propietarios/representantes.
Inicializa solo `snapshot_guardado=0`, incluidos los expedientes eliminados.
Los valores iniciales provienen de la ficha disponible hoy: no reconstruye el
pasado. Las copias ya guardadas, incluso campos vacíos, se conservan. Los abogados
ya almacenan sus datos de contacto por registro y no requieren columnas nuevas.

Solo el bloque de inicialización de snapshots usa la bandera de migración de la
sesión CLI para incluir expedientes eliminados; no desactiva reglas globales.
Los ALTER y CREATE no se revierten con ROLLBACK: si ocurre un error parcial,
conservar el mantenimiento y revisar antes de continuar. El bloque de datos sí
usa una transacción. La auditoría se regenera en el último comando para contemplar
los campos nuevos; no se reasignan casos ni se restablecen claves.

## 5. Verificar antes de reabrir

```bash
php docs/scripts/diagnosticar_multiusuario.php
php docs/scripts/migrar_persona_snapshots.php --database="$DB_NAME"
composer check-platform-reqs --no-dev
```

El segundo comando debe mostrar `Columnas pendientes: 0`; el diagnóstico debe
mostrar las vistas y funciones presentes y ninguna columna omitida en las vistas.
Estos comandos no equivalen a una prueba completa del sitio.

Recargar el servicio PHP del sitio para invalidar OPcache (PHP-FPM o Apache con
mod_php según la instalación; no asumir nombre ni versión). Acceder desde un
navegador, recargar recursos y comprobar en un expediente de prueba autorizado:

- Inicio con administrador, JEFE EMI, adjunto y comandante de guardia.
- Personas y vehículos en Directorio; buscador general y vistas de guardia.
- Guardar/consultar los contactos de familiar, propietario, representante y
  efectivo; un cambio en un accidente conserva los datos de otro accidente.
- Citaciones y generación de documentos Word.
- Calendar solo para el JEFE EMI con CIP `31486778`. Los demás usuarios crean
  citaciones sin escribir en ese calendario. Esto no configura calendarios
  individuales para cada usuario.

Conservar `google/credentials.json` y `google/token.json` de producción. La URI de
retorno OAuth en el código es `https://korkaystore.com/uiatnorte/google_oauth_callback.php`;
debe corresponder al sitio real y a la configuración Google existente. Los JSON
deben quedar inaccesibles vía HTTP: Apache usa los `.htaccess` incluidos; Nginx
requiere la regla equivalente en su configuración. Verificar sin mostrar su contenido.

Revisar los logs PHP/web tras las comprobaciones y entonces reabrir el sitio y
reanudar tareas. Si falla, mantener mantenimiento. Volver solo al código anterior
no deshace DDL ni datos: una restauración se planifica con ambos respaldos y debe
considerar cualquier escritura posterior. No ejecutar una restauración destructiva
automática sobre producción.
