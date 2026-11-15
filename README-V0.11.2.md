# SVCP Directorio Médico 0.11.2

## Corrección de carga de comprobantes
- Se corrigió la validación de rutas absolutas en Windows, contemplando rutas `C:/...` normalizadas.
- Se añadió detección de hosts locales (`.local`, `.test`, `localhost`, `127.x` y redes privadas).
- En desarrollo local, si las carpetas externas están bloqueadas por `open_basedir` o permisos, se utiliza un directorio dedicado dentro de uploads con archivos de protección para Apache/IIS y un `index.php`.
- En producción se mantiene el requisito estricto de almacenamiento fuera del document root; no se habilita el fallback público.
- En entorno local se añade fallback de `copy()` si `move_uploaded_file()` falla, después de validar el archivo y su MIME con WordPress.
- El log de depuración ahora incluye código de error PHP de carga y detalle del fallo cuando `WP_DEBUG` está activo. El mensaje mostrado al visitante no revela rutas internas.

## Seguridad
El fallback dentro de uploads está limitado a dominios de desarrollo local. Está pensado para pruebas en LocalWP/XAMPP/WAMP; no se debe usar como almacenamiento de comprobantes en producción. Para producción configura `SVCP_PRIVATE_UPLOAD_DIR` con una ruta fuera del document root y verifica los permisos del usuario PHP.

## Validación
Sintaxis PHP comprobada y archivo ZIP verificado. No se ha ejecutado una carga HTTP real en la instalación LocalWP del usuario.
