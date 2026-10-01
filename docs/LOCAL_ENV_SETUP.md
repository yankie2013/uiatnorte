# Configuracion local

El proyecto ahora carga variables desde estos archivos, en este orden:

1. `.env`
2. `.env.local`

`.env.local` esta ignorado por git y es donde deben vivir las credenciales reales del entorno local.

## Variables principales

- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`
- `DB_LOCAL_ONLY` (usar `true` en desarrollo: bloquea servidores remotos y nombres de base sin sufijo `_dev` o `_test` antes de conectar)
- `SEEKER_BASE_URL`
- `SEEKER_TOKEN`
- `SEEKER_DNI_URL`
- `SEEKER_PLACA_URL`

## Arranque rapido

1. Copia `.env.example` a `.env.local` si aun no existe.
2. Completa las credenciales reales de base de datos y token.
3. Reinicia Apache/PHP si tu entorno mantiene cache agresiva.

## Nota

`config_api.php` y `config_seeker.php` siguen existiendo solo como puente de compatibilidad para archivos legacy, pero ya no deben guardar secretos fijos.

## Importar una copia del VPS en MAMP

Conserva tu `.env.local` con `DB_HOST=127.0.0.1`, `DB_PORT=8889`, `DB_NAME=uiatnorte_dev` y `DB_LOCAL_ONLY=true`. Importa el SQL en esa base local. Una importación copia los datos; no configura por sí misma replicación con el VPS.

Los objetos SQL pueden conservar un `DEFINER` del servidor de origen. Comprueba las vistas locales con:

```sh
/Applications/MAMP/bin/php/php8.3.30/bin/php docs/scripts/reparar_definers_desarrollo.php
```

Para corregir los definidores inexistentes, añade `--apply`. El script exige esta instalación de MAMP y esta base, rechaza replicación y tablas remotas, respalda las definiciones originales en `~/.uiatnorte/backups/` y cambia únicamente el definidor de las vistas afectadas. No modifica filas ni desactiva los permisos multiusuario.

El esquema multiusuario puede comprobarse con `docs/scripts/diagnosticar_multiusuario.php`, que solo realiza lecturas. Los errores `Sin permiso para modificar este registro` también pueden ser rechazos válidos de permisos o actualizaciones innecesarias desde la aplicación: no se corrigen eliminando los disparadores.

## Copia histórica de datos personales

Los datos variables de la persona se guardan en la relación persona-accidente. Ejecuta `/Applications/MAMP/bin/php/php8.3.30/bin/php docs/scripts/migrar_persona_snapshots_desarrollo.php` desde esta carpeta para agregar las columnas locales. El script solo admite MAMP local en el puerto 8889 y una base cuyo nombre termine en `_dev`. Los registros ya existentes se inicializan con la información disponible hoy en `personas`; no es posible recuperar cambios históricos que antes no se guardaban.
