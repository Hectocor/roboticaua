# Despliegue en hosting PHP + MySQL

## 1. Preparar base de datos

En el panel del hosting crea:

- Una base MySQL.
- Un usuario MySQL.
- Permisos completos de ese usuario sobre la base.

Luego importa `database/schema.sql` desde phpMyAdmin o la herramienta MySQL del hosting.

## 2. Crear configuracion privada

En el servidor, copia `config.local.example.php` como `config.local.php` y completa:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'NOMBRE_BASE');
define('DB_USER', 'USUARIO_BASE');
define('DB_PASS', 'CLAVE_BASE');
define('ADMIN_PASSWORD_HASH', 'HASH_DE_TU_CLAVE');
```

Para crear el hash de la clave del backoffice:

```bash
php -r "echo password_hash('TU-CLAVE-SEGURA', PASSWORD_DEFAULT);"
```

No subas una clave plana en `ADMIN_PASSWORD`.

## 3. Subir archivos

Sube el contenido del proyecto al directorio publico del hosting, normalmente:

- `public_html/`
- `www/`
- `htdocs/`

No subas:

- `.git/`
- `runtime/`
- `config.local.php` local con credenciales de desarrollo.

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
