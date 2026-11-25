# Manual del administrador / tesorero
## SVCP Directorio Médico — versión 1.0.7

Guía operativa para el personal autorizado que administra fichas, membresías y pagos de la Sociedad Venezolana de Coloproctología (SVCP).

## 1. Acceso y navegación
Ingresa al escritorio de WordPress con una cuenta autorizada. Utiliza tu propia cuenta y no compartas credenciales. El tesorero debe tener permisos de administración para acceder al módulo de pagos.

## 2. Dashboard y fichas
En **SVCP Directorio → Dashboard** consulta los indicadores de doctores, fichas por revisar, membresías y pagos pendientes. En **Directorio Médico / Doctores → Todos los doctores** puedes buscar una ficha y corregir datos de contacto, región, fotografía y demás información autorizada.

La fotografía del médico es opcional. Para una presentación uniforme, utiliza una imagen cuadrada, nítida y profesional.

## 3. Revisar registros
1. Abre **Aprobaciones del Directorio**.
2. Filtra por registros pendientes.
3. Comprueba identidad profesional, correo, teléfonos, ubicación y datos de la ficha.
4. Aprueba o solicita correcciones según el procedimiento institucional.

La aprobación de la ficha y la vigencia de la membresía son controles relacionados, pero distintos. La publicación pública exige ambos.

## 4. Configuración de membresías y datos bancarios
En **Directorio Médico / Doctores → Configuración de membresías** configura:
- Mes/día de inicio del ciclo institucional.
- Precio de membresía en USD.
- Anticipación de avisos de vencimiento.
- Banco, tipo y número de cuenta, titular, RIF/cédula, teléfono de pago móvil, correo e instrucciones.

Verifica dos veces los datos bancarios antes de guardarlos. Son los que verá el médico en el portal.

## 5. Función del tesorero: revisar y aprobar pagos
Abre **Directorio Médico / Doctores → Pagos de membresía**. El contador rojo indica reportes pendientes.

Para cada reporte:
1. Abre el pago pendiente y revisa nombre del médico y del depositante.
2. Compara fecha, método, últimos seis caracteres de referencia y monto reportado.
3. Descarga/abre la captura del comprobante y contrástala con el movimiento bancario real.
4. Si se consultó la tasa BCV, úsala solo como referencia. La consulta es opcional: el médico puede reportar el monto efectivamente pagado en bolívares aunque el servicio de tasas esté temporalmente fuera de línea.
5. No apruebes basándote únicamente en la captura: confirma que el dinero esté acreditado en la cuenta institucional.
6. Si todo coincide, pulsa **Aprobar**. Si no coincide, pulsa **Rechazar** e indica un motivo concreto.

**Efecto de aprobar:** el pago cambia a aprobado, se actualiza la vigencia de la membresía y la ficha se marca como publicada automáticamente en el directorio. No es necesario aprobarla otra vez en Aprobaciones del Directorio. Si la ficha estuviera suspendida por una decisión administrativa, revisa esa condición antes de continuar; la suspensión no debe levantarse sin autorización.

**Importante sobre la divisa:** cuando no se consultó una tasa, el sistema conserva el monto reportado en Bs. y deja sin conversión automática el equivalente USD. El tesorero debe validar el monto en el banco y registrar/ajustar cualquier saldo institucional desde los controles financieros disponibles. No interpretes un equivalente USD vacío o cero como evidencia de que el pago fue cero.

## 6. Estados de membresía
- **Activa:** más de 30 días para vencer.
- **Por vencer:** quedan 30 días o menos y aún no ha vencido.
- **Vencida:** fecha de vencimiento alcanzada o superada.
- **Suspendida:** estado administrativo manual; prevalece sobre el cálculo por fecha.

## 7. Historial, comprobantes y reportes
El médico consulta su historial en **Pagos realizados**. El PDF se habilita para los pagos aprobados. Incluye identificación SVCP, RIF J-30849139-0, datos del pago y la nota: “Este documento no constituye un documento fiscal, sino que tiene carácter informativo”. Es una constancia informativa interna y no sustituye factura ni comprobante fiscal.

Puedes exportar el listado de pagos a CSV para conciliación. Conserva los comprobantes bancarios y los registros de aprobación según las políticas de la institución.

## 8. Correo SMTP
En **Correo / SMTP**, configura host, puerto, cifrado, autenticación y remitente conforme al proveedor. Ejecuta una prueba si está disponible. No compartas contraseñas en capturas ni tickets.

## 9. Páginas públicas
- Inicio de sesión: https://svcp86.org/admin-iniciosesion/
- Crear cuenta: https://svcp86.org/admin-crearcuenta/
- Mi perfil: https://svcp86.org/admin-miperfil/
- Reportar pagos: https://svcp86.org/admin-reportarpagos/
- Pagos realizados: https://svcp86.org/admin-pagosrealizados/
- Directorio médico: https://svcp86.org/sociedad-directorio-medico/

Comprueba que cada página esté publicada y tenga su shortcode correspondiente.

## 10. Imágenes institucionales: qué colocar y dónde
1. **Logo institucional principal (obligatorio):** utiliza el archivo PNG transparente/cuadrado que ya aparece en el inicio de sesión. URL actual: https://svcp86.org/wp-content/uploads/2021/10/SVCP_Logo__ICO.png. Se usa como identidad del encabezado del comprobante PDF; no hace falta que el tesorero lo cargue en cada pago.
2. **Fotografía del médico (opcional):** se carga en la ficha del doctor desde el panel o el registro. Recomendación: JPG/PNG/WebP, rostro centrado, buena iluminación, formato cuadrado.
3. **Captura bancaria (obligatoria por reporte):** el médico la adjunta en Reportar pago. JPG/PNG/WebP, máximo 5 MB, completa y legible; no se debe publicar en la biblioteca pública. El plugin la guarda en almacenamiento privado.
4. **Imagen de portada del manual (opcional):** si se desea personalizar los Word, colocar una versión horizontal del logo SVCP en la portada. No sustituye el logo que el plugin utiliza en el PDF.

No subas capturas de comprobantes bancarios como logo ni como fotografía pública.

## 11. Rutina del tesorero
**Diariamente:** revisar pagos pendientes, verificar abonos en el banco, aprobar/rechazar con motivo y comprobar que las notificaciones se envíen.  
**Semanalmente:** conciliar el CSV, revisar saldos y membresías por vencer.  
**Antes de actualizar:** respaldo de base de datos y archivos; probar un pago de prueba en staging y verificar el PDF.

## 12. Seguridad
Usa cuentas individuales, permisos mínimos, contraseñas únicas y doble verificación cuando esté disponible. Nunca marques un pago como aprobado sin comprobar el abono bancario.
