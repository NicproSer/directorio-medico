# SVCP Directorio Médico — v0.9.7

## Avisos de modificaciones y acciones pendientes

- El submenú **Aprobaciones** muestra el contador estilo WordPress cuando hay fichas pendientes de revisión o con revisión requerida.
- El registro de un doctor genera una notificación HTML al administrador con identidad visual SVCP y enlace a la pantalla de aprobaciones.
- Cuando un doctor modifica su ficha desde su panel, el administrador recibe un correo HTML institucional con el nombre, correo y clínica, y enlace para revisar.
- El doctor recibe una confirmación HTML indicando que los cambios fueron recibidos y requieren revisión administrativa.
- Se conserva el badge de pagos pendientes introducido en v0.9.6 y la configuración SMTP existente.

## Validación

Se verificó la sintaxis PHP de todos los archivos del plugin y la integridad del ZIP. No sustituye una prueba funcional en un WordPress de staging con el SMTP real configurado.
