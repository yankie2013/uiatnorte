# Descarga Word de los apartados de análisis

Cada pestaña del análisis tiene el botón **Descargar Word**. Exporta los datos persistidos del apartado; los cambios que sigan en el formulario deben guardarse primero. Las siete descargas usan los modelos aportados por el usuario en `plantillas/analisis/`.

El endpoint `word_analisis_descargar.php?accidente_id=ID&seccion=APARTADO` comprueba la sesión y el acceso al expediente. Los apartados admitidos son `descripcion`, `velocidad`, `danos-lesiones`, `evidencias`, `integral`, `causalidad` e `infracciones`. Los archivos temporales se eliminan tras la descarga.

`AnalisisWordService` obtiene el contenido de `accidente_analisis_informe`, los vehículos y las personas vigentes del mismo expediente. Descripción analítica conserva su contenido guardado; si aún no existe, utiliza la ITP más reciente, igual que la pantalla. El lugar de la inspección incluye el distrito. Las infracciones antiguas almacenadas en causalidad se leen sin modificar los registros originales.

Los DOCX conservan el tamaño de página, márgenes, estilos, cabeceras, pies y marca de agua de cada modelo. El contenido de ejemplo, las fotografías, las firmas y la fecha de muestra se eliminan al preparar las plantillas. La introducción del modelo de velocidad permanece literal. Las fotografías JPEG/PNG alojadas dentro de `uploads/` se incluyen con su pie; una referencia externa o no disponible se conserva como texto, sin descargar recursos de red.

No necesita cambios de esquema SQL. Requiere las tablas actuales del análisis y las extensiones PHP DOM/ZipArchive. El renderizado de las pruebas utiliza LibreOffice y no es una dependencia del servidor de producción.

Para volver a importar los modelos desde una carpeta local:

```bash
php docs/scripts/preparar_plantillas_analisis.php '/ruta/a/Componentes del Informe'
```

El importador reconoce los siete modelos por sus prefijos 6–12 y guarda la procedencia, SHA-256 y patrones de párrafo en `plantillas/analisis/modelos.json`. No necesita los originales en el VPS: se despliega la carpeta `plantillas/analisis/` ya preparada.

Verificación sin conexión a MySQL:

```bash
php tests/analisis_word_regression.php
```

La prueba cubre los siete apartados completos y vacíos, XML válido, ausencia de texto de ejemplo, símbolos, contenido largo, fotografías propias, equivalencia de señalización, selección de ITP reciente, lecturas persistidas y formato antiguo de infracciones. Genera documentos de comprobación en `tmp/analisis-word-qa/`.
