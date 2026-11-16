# Manual del administrador
## SVCP Directorio Médico — versión 1.0.0

Guía para el personal autorizado que administra el directorio, las fichas, las membresías y los pagos de la Sociedad Venezolana de Coloproctología.

## 1. Acceso y navegación
Ingresa al escritorio de WordPress con una cuenta autorizada. En el menú lateral encontrarás **SVCP Directorio** y las opciones de **Directorio Médico / Doctores**. No compartas credenciales; cada operador debe utilizar su propia cuenta.

## 2. Dashboard
Abre **SVCP Directorio → Dashboard**. El panel resume el total de doctores, fichas publicables, registros por revisar, membresías próximas a vencer o vencidas, pagos pendientes, importes aprobados y alertas sobre fichas incompletas. Los indicadores enlazan a los listados correspondientes.

## 3. Gestión de fichas
1. Abre **Directorio Médico / Doctores → Todos los doctores**.
2. Busca por nombre y abre la ficha.
3. Verifica nombre, correo, teléfono, clínica, ciudad, región y fotografía.
4. Corrige la información cuando corresponda y guarda los cambios.

La fotografía es opcional. Para mantener una presentación uniforme, utiliza imágenes cuadradas (1:1). La publicación pública requiere que la ficha esté aprobada y que la membresía permita su aparición.

## 4. Revisar registros nuevos
Cuando una persona se registra, su ficha queda pendiente de revisión. Comprueba que los datos correspondan al profesional, revisa que la información esté completa y utiliza el módulo de aprobaciones para aprobarla o solicitar correcciones. No des por aprobada una ficha solo porque recibiste un correo.

## 5. Configuración de membresías
En **Directorio Médico / Doctores → Configuración de membresías** puedes establecer:
- Mes y día de inicio del ciclo institucional compartido.
- Días de anticipación para avisos de vencimiento (por ejemplo, 30, 15 y 7).
- Precio institucional de membresía en las monedas disponibles.
- Datos bancarios: banco, tipo y número de cuenta, titular, documento, teléfono de pago móvil, correo e instrucciones.

Pulsa **Guardar configuración** y verifica cuidadosamente los datos bancarios: los médicos los consultan desde su portal. El ciclo es común para la institución.

## 6. Verificación de pagos
1. Abre el módulo **Pagos** dentro de la administración del directorio.
2. Localiza los reportes pendientes.
3. Compara depositante, referencia, fecha, monto y soporte con los movimientos bancarios.
4. Aprueba únicamente cuando el dinero haya sido confirmado.
5. Si rechazas un reporte, registra un motivo claro.

Un reporte enviado no equivale a un pago verificado. Mantén el historial para conservar la trazabilidad.

## 7. Estados de membresía
En **Directorio Médico / Doctores → Membresías** puedes filtrar por estado:
- **Activa:** vigente y fuera del período de aviso.
- **Por vencer:** restan 30 días o menos.
- **Vencida:** alcanzó o superó la fecha de vencimiento.
- **Suspendida:** estado administrativo manual que prevalece sobre el cálculo por fecha.

La aparición en el directorio público depende tanto de la aprobación como de la elegibilidad de la membresía.

## 8. Historial y comprobantes
Los médicos consultan sus reportes desde **Pagos realizados**. El PDF está disponible para pagos aprobados y funciona como constancia interna; no sustituye una factura fiscal.

## 9. Correo SMTP
En **Correo / SMTP**, configura el host, puerto, cifrado, autenticación, usuario y remitente conforme a los datos de tu proveedor. Guarda y ejecuta una prueba si está disponible. Protege las contraseñas y no las compartas en capturas o tickets. Si falla el correo, revisa credenciales, registros de error y restricciones de salida del hosting.

## 10. Direcciones públicas
- Inicio de sesión: https://svcp86.org/admin-iniciosesion/
- Crear cuenta: https://svcp86.org/admin-crearcuenta/
- Mi perfil: https://svcp86.org/admin-miperfil/
- Reportar pagos: https://svcp86.org/admin-reportarpagos/
- Pagos realizados: https://svcp86.org/admin-pagosrealizados/
- Directorio médico: https://svcp86.org/sociedad-directorio-medico/

Si una dirección no abre, comprueba que la página esté publicada y tenga el shortcode correspondiente. Después ve a **Ajustes → Enlaces permanentes** y pulsa **Guardar cambios**.

## 11. Rutina y seguridad
**Diariamente:** revisar registros nuevos y pagos pendientes; contrastar pagos con el banco.  
**Semanalmente:** revisar membresías por vencer, fichas incompletas y funcionamiento del correo.  
**Antes de actualizar:** respaldar archivos y base de datos; comprobar acceso, registro, pagos y PDF tras la actualización.

Asigna solo los permisos necesarios, utiliza contraseñas únicas, valida cada pago y mantén WordPress y sus extensiones actualizados.
