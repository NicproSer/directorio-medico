# SVCP Directorio Médico v0.9.2

Incluye el flujo inicial de reportes manuales de pago de membresía.

## Configuración

1. Actualice el plugin desde el ZIP `svcp-directorio-medico-v0.9.2.zip` (haga respaldo previo).
2. Cree o edite una página privada para médicos e inserte el shortcode `[svcp_formulario_pago]`.
3. Los médicos deben iniciar sesión y tener una ficha vinculada a su usuario mediante `svcp_usuario_id`.
4. En WordPress, abra **Doctores → Pagos de membresía** para revisar los reportes.
5. Apruebe o rechace los reportes pendientes. Al aprobar, se extiende la vigencia 365 días desde hoy o desde el vencimiento vigente, si este es posterior.

Los pagos se almacenan como pendientes y no activan la membresía al enviarse. Los correos usan `wp_mail()` y por tanto respetan la configuración SMTP existente.

## Alcance

Esta versión implementa reportes y verificación manual. No incluye MiSpidi, pagos en línea, carga de comprobantes ni pagos de eventos.
