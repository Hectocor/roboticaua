# Despliegue en hosting PHP + MySQL

## 1. Preparar base de datos

En el panel del hosting crea:

- Una base MySQL.
- Un usuario MySQL.
- Permisos completos de ese usuario sobre la base.

Luego importa `database/schema.sql` desde phpMyAdmin o la herramienta MySQL del hosting.

Si la base ya tiene noticias e imágenes, **no vuelvas a importar `schema.sql`**: conserva esos datos y ejecuta `database/migrate-2026-11-news-admin.sql` en la pestaña SQL de phpMyAdmin. La migración agrega `body_html` y `admin_users` sin borrar registros.

## 2. Crear configuracion privada

En el servidor, copia `config.local.example.php` como `config.local.php` y completa:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'NOMBRE_BASE');
define('DB_USER', 'USUARIO_BASE');
define('DB_PASS', 'CLAVE_BASE');
define('ADMIN_PASSWORD_HASH', 'HASH_DE_TU_CLAVE');
define('ADMIN_USERNAME', 'admin');
```

Para crear el hash de la clave del backoffice:

```bash
php -r "echo password_hash('TU-CLAVE-SEGURA', PASSWORD_DEFAULT);"
```

No subas una clave plana en `ADMIN_PASSWORD`.

Al entrar por primera vez después de la migración, usa el usuario indicado en `ADMIN_USERNAME` y tu clave actual. El sitio creará automáticamente ese primer usuario en `admin_users` con un hash de la clave. Desde entonces, el acceso se comprueba contra la tabla. Mantén `config.local.php` fuera del repositorio.

La migración fue probada con el respaldo `cro117111_roboticaua_site.sql` del 10-11-2026: conservó las 5 noticias y 20 imágenes existentes. No subas ese respaldo SQL al directorio público.

## 3. Subir archivos

Sube el contenido del proyecto al directorio publico del hosting, normalmente:

- `public_html/`
- `www/`
- `htdocs/`

No subas:

- `.git/`
- `runtime/`
- `config.local.php` local con credenciales de desarrollo.

Para esta actualización, reemplaza los archivos modificados del sitio y agrega `noticias.php`, `news-content.php`, `admin/admin.js` y `.user.ini`. Importa `database/migrate-2026-11-news-admin.sql` desde tu computador en phpMyAdmin; no hace falta subirlo a `public_html`. Conserva `uploads/` del hosting, que contiene las imágenes publicadas. El editor enriquecido usa Quill 2.0.3 desde su CDN; si no carga, queda disponible el campo de texto simple.

Si usas el ZIP generado localmente, descomprímelo dentro del directorio publico.

## 4. Permisos de escritura

Estas carpetas deben poder escribir archivos desde PHP:

- `uploads/gallery/`
- `uploads/news/`
- `uploads/alerts/`

En muchos hostings basta con permisos `755`. Si falla la subida de imagenes, probar `775` segun indique el proveedor.

## 5. Probar

Abre:

- `https://TU-DOMINIO/`
- `https://TU-DOMINIO/api/news.php`
- `https://TU-DOMINIO/api/gallery.php`
- `https://TU-DOMINIO/admin/`

En el backoffice prueba:

- Crear una noticia.
- Subir una imagen de noticia.
- Agregar una imagen a galeria.
- Enviar el formulario de contacto.

## 6. DNS del dominio

Si el dominio y hosting son del mismo proveedor, normalmente se configura automatico.

Si son proveedores distintos, apunta el dominio usando los nameservers del hosting o el registro A que te entregue el proveedor.

## 7. HTTPS

Activa SSL/HTTPS desde el panel del hosting antes de publicar el enlace final. El sitio y el backoffice funcionan mejor con HTTPS.
