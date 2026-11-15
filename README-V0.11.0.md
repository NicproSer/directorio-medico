# SVCP Directorio Médico 0.11.0

## Gestión financiera (sin MiSpidi)
- Se conserva el precio institucional en USD y la conversión con tasa USD/VES consultada para la fecha de pago declarada.
- Se mantiene un historial local de tasas consultadas por fecha (hasta 500 fechas) y cada reporte guarda su snapshot: precio USD, tasa, fecha de vigencia y equivalente en Bs.
- El panel financiero muestra recaudación aprobada en Bs., equivalente USD, pagos aprobados del mes y una tabla de tasas históricas utilizadas.
- Exportación CSV de pagos con datos de operación, monto, precio, tasa, estado, administrador que resolvió y motivo de rechazo.
- El Dashboard SVCP incorpora métricas de pagos pendientes y recaudación aprobada.
- Se envía un recordatorio diario al correo administrativo mientras existan reportes pendientes (máximo uno por día). Los avisos de vencimiento de membresía existentes se conservan.
- Las acciones de reporte, aprobación y rechazo de pagos se registran en la auditoría asociada al médico.

## Comprobantes privados
- Los nuevos comprobantes se almacenan fuera del directorio público de WordPress y solo se sirven mediante un endpoint que exige `manage_options`, nonce, ruta real dentro del directorio privado y MIME permitido.
- Las capturas antiguas almacenadas en la biblioteca multimedia se migran progresivamente al abrir la pantalla de pagos y luego se elimina su adjunto público.
- Por seguridad, si el directorio propuesto cae dentro de `DOCUMENT_ROOT`, la carga se bloquea. En instalaciones donde WordPress está en subdirectorio, define `SVCP_PRIVATE_UPLOAD_DIR` en `wp-config.php` con una ruta absoluta escribible fuera del document root, por ejemplo `/var/private/svcp-comprobantes`.
- El correo administrativo dirige al panel de pagos; la captura se abre desde el panel ya autenticado para no enviar enlaces públicos ni nonces ligados a la sesión del médico.

## Integración BCV
- Se mantiene BCV Today como fuente de datos de terceros que replica datos publicados por el BCV; no es una API oficial del BCV.
- La consulta histórica falla de forma cerrada si el proveedor no devuelve una tasa USD válida. No se inventa una tasa de respaldo.

## Fuera de alcance
- No se incorpora MiSpidi ni ningún gateway de pago automático. La revisión y aprobación siguen siendo manuales.

## Validación
- Sintaxis PHP de todos los archivos y ZIP verificados.
- Requiere pruebas funcionales en WordPress staging para la API, permisos de almacenamiento privado, SMTP, WP-Cron, descarga de comprobantes, migración de adjuntos y exportación CSV.
