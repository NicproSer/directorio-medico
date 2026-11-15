# SVCP Directorio Médico 0.10.2

## Precio de membresía en USD y tasa BCV
- El administrador configura el precio institucional en USD desde Doctores → Configuración de membresías.
- El portal calcula el equivalente en bolívares con la tasa USD/VES asociada a la fecha de pago declarada por el médico.
- Se consulta la tasa actual o el histórico por fecha y se guarda una copia inmutable en el reporte: monto USD, tasa, fecha de vigencia, fecha del pago y equivalente en Bs.
- El reporte sigue pendiente de verificación manual; la conversión no aprueba ni activa la membresía.
- La tabla administrativa muestra el monto reportado y la tasa histórica utilizada.
- Se programa una actualización diaria mediante WP-Cron y se conserva la última tasa local.

## Fuente de datos y limitación importante
La integración utiliza BCV Today (`https://bcv.today`), un servicio comunitario no oficial que declara obtener y estructurar los datos publicados por el BCV. No es una API oficial operada por el Banco Central de Venezuela. La integración valida la respuesta y no acepta una tasa inválida; si no se puede consultar el histórico de la fecha declarada, no permite enviar el reporte para evitar guardar una conversión inventada.

## Validación
Sintaxis PHP y estructura del ZIP verificadas. La consulta en vivo, WP-Cron, correo SMTP y flujo real de pagos deben probarse en un WordPress de staging.
