# Sincronización de fuentes UIAT NORTE — 3 de noviembre de 2026

## Procedencia y alcance

Base remota comprobada: `567cccf1`. Commit local previo revisado: `2b2b7aa751f22731a3dea20d4e598ce1aab2c5cf` (API de lectura, autorización por expediente, auditoría, pruebas ficticias, renovación de sesión y exclusión de API en service worker). Se conserva ese commit y se incorporan los controles locales existentes de limitación de solicitudes.

La carpeta principal de entregables estaba vacía. Se usó la carpeta alternativa indicada por el usuario. SHA-256 comprobados:

- Paquete v6: `826e839043faee1018b787900de16788419c322cb7b6d4dd5a02ce8d49c5e958`.
- Instalador v6: `8ada035c6619cf6eafb112e1d13b350c703eb248b33327b03b5d2b8d0fe99cc0`.
- Arnés original de servidor v6: `1cda85023e97ce770b987304454d0b3f7fce2ad7831c3443241aca2cd31df5bb` (no ejecutado ni incorporado: requiere entorno root y sondeos del servidor).

Los hashes coinciden con los manifiestos entregados y con la constante del instalador. Del paquete se recuperaron 13 archivos distintos y una prueba ausente respecto de la copia local. Las variantes anteriores se conservaron fuera del repositorio. El manifiesto `integrations/chatgpt/release/v6-manifest.json` permite comprobar cada archivo.

La comparación con `CURRENT_SOURCE` del instalador v6 demuestra que el paquete conserva la búsqueda v4 y consulta integral v5; sus diferencias son catálogo, plantilla de protocolo, generador, factoría y prueba de oficios. No se incorporó el candidato de sellos general v3 ni otros paquetes descartados. La identificación como versión desplegada proviene del usuario; no se ha establecido conexión con producción para certificar hashes actuales.

## Exclusiones

Se conservan fuentes, locks de Composer, fixtures ficticios y plantillas fuente. Se retiran solo del índice Git las dependencias `vendor/`, el volcado SQL de desarrollo con INSERT, documentos generados, respaldos de plantillas, un archivo temporal de Word y la caché de mapas. Sus copias locales permanecen. Se ignoran cachés Python, dependencias descargadas, entornos, claves, estado y auditoría. Esta limpieza no elimina archivos de commits históricos ni reescribe historia.

## Automatizaciones

No hay hooks Git locales activos; solo muestras. Antes del push, GitHub respondió: cero webhooks, cero workflows y cero entornos. `.cpanel.yml` contenía una copia recursiva a una raíz pública. Se sustituye por `/bin/true`, sin acciones de despliegue. No se cambió configuración del servidor, permisos SQL ni servicios existentes. Ningún instalador fue ejecutado.

## Validación

Con PHP 8.3.30: API SQLite 43; limitador 311; OAuth/seguridad 129; cámaras 75; diez oficios 82; necropsia 23; búsqueda 59; consulta integral 129 comprobaciones correctas. HTTPS del conector: 33 comprobaciones sin cámaras y 43 con cámaras. Se corrigieron exclusivamente dos arneses de pruebas y se registraron sus hashes separados del original.

MySQL 8.0.44 se inicializó con un datadir nuevo en un directorio temporal, puerto loopback exclusivo y sin archivos de configuración existentes. Sobre esa instancia se ejecutaron 15 comprobaciones de privilegios y 31 peticiones HTTPS de API, además del login ficticio. Se detuvo únicamente ese proceso y se eliminó su directorio temporal. No se usó el servidor MAMP ni se ejecutaron migraciones. No se repitió la validación MariaDB/Apache de servidor ni las pruebas root de instalación.
