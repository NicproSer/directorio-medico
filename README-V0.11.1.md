# SVCP Directorio Médico v0.11.1

## Correcciones

- Se corrige la selección del almacenamiento privado de comprobantes: intenta primero la ruta configurada, luego una carpeta hermana de WordPress y finalmente el directorio temporal del servidor. Solo acepta rutas absolutas escribibles fuera del document root y fuera de ABSPATH.
- Se registra en el log de PHP el motivo técnico del fallo cuando WP_DEBUG está habilitado, mientras el usuario recibe un mensaje seguro.
- Buscador y filtro por región en tiempo real, sin recarga de página, con filtrado instantáneo del catálogo público cargado.
- La clase `.svcp-region-card` utiliza `min-height: 70px`.
- Fotos del directorio y vista previa del panel del médico con proporción 1:1, `object-fit: cover` y `object-position: center 25%`.
- Se ocultan los títulos automáticos del tema WordPress en páginas que contienen los shortcodes del panel médico y del formulario de pago, manteniendo los encabezados propios del plugin.

## Almacenamiento privado

El plugin comprueba que la carpeta esté fuera del document root y fuera de la instalación de WordPress. La ruta preferida se puede establecer en `wp-config.php`:

```php
define( 'SVCP_PRIVATE_UPLOAD_DIR', '/ruta/absoluta/privada/svcp-comprobantes' );
```

Si esa ruta no existe o no es escribible, se prueban las rutas alternativas seguras. Si el hosting restringe PHP con `open_basedir`, el administrador debe solicitar al proveedor una ruta privada permitida.

## Buscador

El directorio carga los médicos publicables para filtrar nombre, clínica, ciudad, dirección y región de forma instantánea en el navegador. En directorios extraordinariamente grandes se recomienda migrar el filtrado a endpoint AJAX paginado.

## Pruebas realizadas

- Lint de sintaxis PHP de todos los archivos del plugin.
- Verificación sintáctica de JavaScript.
- Verificación de integridad del ZIP.
- No sustituye una prueba de carga real en el hosting ni la validación de permisos/open_basedir del servidor.
