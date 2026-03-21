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
