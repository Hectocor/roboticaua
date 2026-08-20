# Grupo de Interes en Robotica Educativa

Sitio para presentar el Grupo de Interes en Robotica Educativa de Experiencia UA, Universidad Autonoma de Chile.

## Estructura

- `index.html`: pagina principal.
- `galeria.html`: galeria completa de actividades.
- `directiva.html`: subpagina para directiva, cargos y contactos.
- `noticia.php`: detalle publico de noticias.
- `styles.css`: estilos del sitio.
- `script.js`: carga dinamica de galeria, noticias, accesibilidad y modales.
- `api/`: endpoints publicos para galeria, noticias y alertas.
- `admin/`: backoffice privado.
- `database/schema.sql`: estructura inicial de MySQL.
- `gallery/`: fotos de actividades.
- `uploads/`: imagenes subidas desde el backoffice.

## Backoffice PHP + MySQL

El sitio usa PHP y MySQL para administrar galeria y noticias.

1. Crea una base MySQL en el hosting.
2. Importa `database/schema.sql`.
3. Copia `config.local.example.php` como `config.local.php`.
4. Completa las credenciales de MySQL y la clave del panel.
5. Asegura permisos de escritura para:
   - `uploads/gallery/`
   - `uploads/news/`
   - `uploads/alerts/`
6. Entra a `https://TU-DOMINIO/admin/`.

## Editar directiva

Abre `directiva.html` y reemplaza:

- Las fotos `assets/directiva/default-member.svg` por archivos reales de cada integrante.
- El atributo `src` de cada imagen por la ruta de la nueva foto, por ejemplo `assets/directiva/presidencia.jpg`.
- Enlaces de Instagram si cada integrante tiene uno propio.

No se publican RUT en la pagina por resguardo de datos personales.

Para generar una clave segura puedes usar:

```bash
php -r "echo password_hash('TU-CLAVE', PASSWORD_DEFAULT);"
```

Luego pega el resultado en `ADMIN_PASSWORD_HASH`.

## Publicar en hosting

Revisa `DEPLOYMENT.md` para el paso a paso de subida por cPanel, FTP o administrador de archivos.
