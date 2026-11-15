# SVCP Directorio Médico v0.10.0

## Cambios
- Ajustes de precio institucional de membresía desde Doctores → Configuración de membresías.
- El formulario de reporte de pago muestra el precio configurado como referencia; no fuerza ni aprueba automáticamente el monto.
- Correo de recuperación de contraseña con instrucciones claras y aviso de seguridad.
- Correo HTML institucional cuando la contraseña se cambia correctamente.
- Se mantienen los módulos, formularios y opciones SMTP existentes.

## Compatibilidad y seguridad
- El precio se configura en bolívares y admite decimales. Si se establece en 0, el precio de referencia no se muestra.
- Las solicitudes de cambio de contraseña siguen usando el flujo nativo de WordPress y su enlace de restablecimiento.
- Recomendado probar los correos en una instalación de pruebas y verificar el envío SMTP antes de producción.
