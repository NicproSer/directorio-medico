# SVCP Directorio Médico v0.9.4

## Cambios
- Configuración administrativa del ciclo institucional compartido: mes y día de inicio, con vencimiento común para todos los doctores.
- Los pagos aprobados habilitan la membresía hasta la fecha institucional del ciclo; ya no suman 365 días individuales.
- Nueva pantalla **Directorio → Configuración de membresías**.
- Plantillas de correo HTML adaptables a móviles, con identidad SVCP, iconos, saludo personalizado y botón de acceso.
- Correos de bienvenida, pago recibido/en revisión, pago aprobado, pago rechazado, perfil aprobado (plantilla lista para uso), recordatorio de vencimiento y membresía vencida.
- Se conserva el envío mediante `wp_mail()` y, por tanto, la configuración SMTP existente.
- El directorio público y la protección de fichas por aprobación/solvencia mantienen el comportamiento previo.

## Configuración
1. Instala el ZIP desde **Plugins → Añadir nuevo → Subir plugin**.
2. Abre **Directorio → Configuración de membresías**.
3. Elige el mes y día en que inicia el ciclo anual. La interfaz limita el día al 28 para evitar fechas inexistentes en febrero.
4. Configura los días de aviso separados por coma (por ejemplo `30,15,7`). La programación efectiva de avisos depende del cron del sitio y de la configuración ya existente.

El ciclo predeterminado inicia el 1 de agosto. La fecha de vencimiento común coincide con el día de inicio del siguiente ciclo; según la regla actual del plugin, la membresía se considera vencida al llegar esa fecha. Los cambios en la configuración aplican al calcular nuevas aprobaciones; los vencimientos guardados en fichas ya aprobadas no se reescriben automáticamente.

## Validación
Se realizó validación sintáctica PHP y comprobación de integridad del ZIP. No sustituye una prueba funcional en un sitio WordPress de staging con SMTP real.

## Nota sobre el registro
El formulario de registro existente ya solicita los datos de la ficha profesional durante el alta y crea la ficha asociada a la cuenta; esta versión agrega el correo HTML de bienvenida, pero no convierte el registro en un asistente separado de varias pantallas. Se recomienda probar el flujo real con un usuario de prueba antes de producción.
