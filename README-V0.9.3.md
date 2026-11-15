# SVCP Directorio Médico v0.9.3

## Mejoras

- Revisión de seguridad del flujo de pagos manuales.
- Nonces y comprobación estricta de usuario/ficha en el formulario.
- Comprobación de tipo y estado del registro antes de resolver pagos.
- Protección básica contra envíos repetidos mediante transient por usuario.
- Validación estricta de referencia, monto y método de pago.
- Prevención de procesamiento doble de pagos.
- Motivo obligatorio para rechazos.
- Escapado de datos en el panel administrativo y formularios.
- Panel de pagos renovado con estadísticas, filtros y paginación.
- Indicadores visuales de estado pendiente/aprobado/rechazado.
- UX pública renovada para el formulario de pago.
- Paleta visual alineada con el carácter institucional azul/verde de SVCP.
- Mantiene el SMTP existente y la arquitectura preparada para una futura integración con MiSpidi.

## Shortcode

`[svcp_formulario_pago]`

## Importante

Esta versión continúa usando únicamente pagos manuales. No conecta MiSpidi ni modifica credenciales de pasarelas.

Antes de producción se recomienda probar en staging el alta de un médico, envío de pago, aprobación, rechazo, renovación con membresía vigente y renovación con membresía vencida.
