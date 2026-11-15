# SVCP Directorio Médico — v0.9.0

## Novedades

- Dashboard administrativo centralizado.
- Indicadores de doctores, fichas publicables, aprobaciones y membresías.
- Indicadores de calidad: cuenta, región, correo, ciudad y fotografía.
- Estado de SMTP y WP-Cron desde administración.
- Configuración SMTP integrada utilizando el PHPMailer incluido por WordPress.
- Correo de prueba.
- Detección del último error de correo.
- Panel de estado y ejecución manual de las notificaciones de membresía.
- Migración/actualización automática al instalar la v0.9.0 sobre una v0.8.x.

## SMTP

El plugin continúa utilizando `wp_mail()`. SMTP no sustituye `wp_mail()`; configura el transporte de correo que utiliza WordPress.

Recomendado:

- SMTP autenticado.
- TLS/STARTTLS, normalmente puerto 587.
- Dirección remitente del mismo dominio del sitio.
- SPF y DKIM configurados en DNS; DMARC recomendado.
- Si el proveedor ofrece contraseña de aplicación, utilizarla.

También se puede definir la contraseña en `wp-config.php`:

```php
define( 'SVCP_SMTP_PASSWORD', 'TU_CONTRASEÑA_O_APP_PASSWORD' );
```

Si existe esta constante, tiene prioridad sobre la contraseña guardada en la pantalla de configuración.

## WP-Cron

WordPress puede ejecutar WP-Cron al recibir visitas. Para producción, especialmente cuando las notificaciones de membresía son importantes, se recomienda usar un cron real del servidor.

1. En `wp-config.php`:

```php
define( 'DISABLE_WP_CRON', true );
```

2. Crear una tarea cada 5 minutos:

```text
*/5 * * * * wget -q -O - https://TU-DOMINIO.com/wp-cron.php >/dev/null 2>&1
```

O con PHP CLI:

```text
*/5 * * * * php /ruta/a/wordpress/wp-cron.php >/dev/null 2>&1
```

El evento del plugin sigue siendo diario. El cron real cada 5 minutos únicamente garantiza que WordPress despierte y procese puntualmente los eventos programados.

## Configuración desde WordPress

- **SVCP Directorio → Correo / SMTP**: servidor, puerto, cifrado, usuario, contraseña y remitente.
- **Doctores → Cron / Notificaciones**: revisar próxima ejecución y ejecutar manualmente el proceso.
- **SVCP Directorio → Dashboard**: visión general del estado del directorio.
