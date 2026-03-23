# Flujo de Trabajo Entre Windows y Mac

Esta guia resume como mover y sincronizar este proyecto entre una PC con Windows y una Mac sin perder cambios.

## 1. Que se sincroniza con Git

Git y GitHub sincronizan muy bien:

- Codigo fuente del proyecto
- Archivos PHP, CSS, HTML y JS
- Cambios hechos desde VS Code o Codex sobre archivos versionados
- Documentacion dentro del proyecto

## 2. Que no se sincroniza automaticamente

Estos elementos se manejan por separado:

- Base de datos
- Archivo `.env.local`
- Carpeta `uploads` si contiene archivos importantes
- Logs y temporales

## 3. Rutina diaria recomendada

Antes de empezar a trabajar en cualquier maquina:

```bash
git pull
```

Luego haces tus cambios normalmente.

Cuando termines:

```bash
git add .
git commit -m "Describe el cambio"
git push
```

Cuando abras la otra maquina:

```bash
git pull
```

## 4. Regla simple para evitar conflictos

- Antes de editar: `git pull`
- Al terminar: `git add .`, `git commit -m "..."`, `git push`
- En la otra maquina: `git pull`

No conviene editar el mismo archivo en Windows y Mac al mismo tiempo sin antes subir y bajar cambios.

## 5. Flujo Windows -> Mac

En Windows:

```powershell
cd C:\laragon\www\uiatnorte
git pull
```

Haces cambios y luego:

```powershell
git add .
git commit -m "Describe el cambio"
git push
```

En Mac:

```bash
cd /ruta/de/uiatnorte
git pull
```

## 6. Flujo Mac -> Windows

En Mac:

```bash
cd /ruta/de/uiatnorte
git pull
```

Haces cambios y luego:

```bash
git add .
git commit -m "Describe el cambio"
git push
```

En Windows:

```powershell
cd C:\laragon\www\uiatnorte
git pull
```

## 7. Base de datos

Git no guarda la base de datos. Para moverla entre maquinas:

1. Exporta la base de datos a un archivo `.sql`
2. Lleva ese archivo a la otra maquina
3. Importalo en MySQL o MariaDB

Si haces cambios importantes en la estructura o contenido de la base, recuerda exportarla otra vez.

## 8. Archivo de entorno local

El archivo `.env.local` no debe subirse al repositorio si contiene claves, contrasenas o configuracion privada.

Para usar el proyecto en otra maquina:

1. Copia `.env.local` manualmente
2. Ajusta rutas, credenciales o claves segun esa maquina

## 9. Carpeta uploads

Si el sistema guarda documentos o archivos en `uploads`, esos archivos no se sincronizaran por Git si estan ignorados.

Si necesitas esos archivos en otra maquina:

1. Copia la carpeta `uploads`
2. Pegala en la otra instalacion del proyecto

## 10. Recomendacion final

Usar estas 3 capas es lo mas seguro:

- GitHub privado para el codigo
- Backup `.sql` para la base de datos
- Copia manual de `.env.local` y `uploads` cuando sea necesario

## 11. Chuleta rapida

```bash
# Antes de trabajar
git pull

# Haces cambios

# Guardas y subes
git add .
git commit -m "Describe el cambio"
git push

# En la otra maquina
git pull
```
