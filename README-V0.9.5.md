# SVCP Directorio Médico v0.9.5

## Cambio principal: crear cuenta desde el acceso
- El shortcode `[svcp_login_doctor]` ahora incluye la opción **Crear cuenta de doctor**.
- Si existe una página publicada con `[svcp_registro_doctor]`, el botón lleva a esa página.
- Si no existe una página de registro, el formulario completo aparece directamente debajo del acceso, para que la opción no desaparezca por falta de configuración.
- Se mantiene el registro de usuario y ficha profesional existente, validaciones, nonce y protección anti-spam.

## Instalación
1. Haz una copia de seguridad.
2. Sube el ZIP desde **Plugins → Añadir nuevo → Subir plugin**.
3. En la página de acceso médico usa `[svcp_login_doctor]`.
4. Opcionalmente crea una página llamada “Crear cuenta” con `[svcp_registro_doctor]`; el botón de acceso la detectará automáticamente.

## Validación
Se validaron la sintaxis PHP y la integridad del ZIP. Se recomienda probar registro y acceso en un entorno de pruebas antes de producción.
