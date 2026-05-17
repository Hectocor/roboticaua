# Grupo de Interes - Robotica Educativa

Sitio estatico para presentar el Grupo de Interes - Robotica Educativa de Experiencia UA, Universidad Autonoma de Chile.

## Estructura

- `index.html`: pagina principal.
- `galeria.html`: galeria completa de actividades.
- `directiva.html`: subpagina para directiva, cargos y contactos.
- `styles.css`: estilos del sitio.
- `script.js`: carga dinamica de la galeria.
- `gallery/`: fotos de actividades.
- `gallery-data.js`: respaldo directo para previsualizacion local con `file://`.
- `gallery/manifest.json`: respaldo para listar fotos cuando se use un servidor sin indice de directorio.

## Galeria

En GitHub Pages, la galeria intenta leer automaticamente los archivos de `gallery` desde la API publica de GitHub.

Para agregar fotos:

1. Sube las imagenes a `gallery/`.
2. Usa extensiones `.jpg`, `.jpeg`, `.png`, `.webp` o `.gif`.
3. Para previsualizar abriendo el HTML localmente, agrega el nombre del archivo a `gallery-data.js`.
4. Si quieres que tambien funcione en servidores sin API de GitHub, agrega el nombre del archivo a `gallery/manifest.json`.

## Crear repositorio y publicar en GitHub Pages

1. Crea un repositorio publico en GitHub, por ejemplo `robotica-ua`.
2. Sube todos los archivos de esta carpeta al repositorio.
3. En GitHub entra a `Settings` > `Pages`.
4. En `Build and deployment`, selecciona:
   - Source: `Deploy from a branch`
   - Branch: `main`
   - Folder: `/root`
5. Guarda los cambios.

La URL quedara con este formato:

```text
https://TU-USUARIO.github.io/robotica-ua/
```

## Editar directiva

Abre `directiva.html` y reemplaza:

- Las fotos `assets/directiva/default-member.svg` por archivos reales de cada integrante.
- El atributo `src` de cada imagen por la ruta de la nueva foto, por ejemplo `assets/directiva/presidencia.jpg`.
- Enlaces de Instagram si cada integrante tiene uno propio.

No se publican RUT en la pagina por resguardo de datos personales.
