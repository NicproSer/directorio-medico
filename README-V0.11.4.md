# SVCP Directorio Médico v0.11.4

## Novedades

- El indicador administrativo **Equivalente registrado (USD)** suma el equivalente real de los montos recibidos (`_svcp_pago_equivalente_usd`), no el precio institucional de la membresía.
- El dashboard general muestra la cartera pendiente en USD y su conversión a bolívares con la tasa BCV vigente al consultar el panel.
- El panel del médico incorpora **Mis pagos y estado de cuenta**, con historial de reportes, montos en Bs., equivalentes USD y estado de cada reporte.
- Los pagos aprobados permiten descargar un comprobante PDF privado, protegido por sesión, asociación de la ficha, estado aprobado y nonce.
- El saldo pendiente se conserva como deuda USD. Su equivalente en Bs. se recalcula con la tasa BCV vigente en el panel del médico y en el dashboard administrativo.
- Si el médico ya tiene deuda pendiente, el siguiente reporte muestra esa deuda como importe de referencia. Al aprobarlo, el monto recibido en USD se aplica primero a reducir la deuda existente. El saldo remanente se guarda en USD y no queda congelado a una tasa antigua.
- Los equivalentes pagados se almacenan con ocho decimales para mejorar la precisión de los acumulados; la interfaz presenta cuatro decimales.

## Consideraciones

- Los recibos PDF son constancias internas de registro y aprobación; no sustituyen facturas fiscales.
- El saldo pendiente se actualiza en USD al aprobar cada nuevo pago. Su equivalente en Bs. se calcula con la tasa BCV vigente cuando se visualiza; si la consulta BCV falla, se informa que la tasa no está disponible en lugar de mostrar una conversión desactualizada como si fuera actual.
- Los reportes pendientes o rechazados no generan recibos PDF descargables.
- Se mantiene la verificación manual y no se incorpora MiSpidi.

## Validaciones realizadas

- Sintaxis PHP de todos los archivos PHP del plugin.
- Integridad del archivo ZIP.
- No se ha ejecutado una prueba funcional en la instalación WordPress/Local del usuario; deben probarse allí el flujo de pago, la consulta BCV y la descarga PDF.
