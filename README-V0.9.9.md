# SVCP Directorio Médico v0.9.9

## Portal del médico adaptable

- Reestructura el panel frontal del médico con navegación lateral, encabezado institucional y resumen de membresía, estado de ficha, vigencia y correo de acceso.
- Divide la pantalla de perfil en una columna de vista previa/fotografía y una columna para datos profesionales, contacto, región y redes.
- Aplica estilos responsive para escritorio, tablet y móvil, utilizando la paleta institucional teal/azul.
- Conserva el flujo de actualización, validación de campos, carga de foto, nonce de seguridad y avisos de aprobación.
- Añade filtros para personalizar las URLs del portal y pagos mediante `svcp_url_portal_medico` y `svcp_url_portal_pagos`.

## Configuración

El shortcode existente `[svcp_panel_doctor]` no cambia. El enlace de pago apunta por defecto a `/reportar-pago/`; si la página de pagos usa otra URL, puede personalizarse con el filtro `svcp_url_portal_pagos` o cambiando el slug por el que use el sitio. El portal usa la URL de la página que contiene el shortcode como enlace de perfil.

## Verificación

Validación estática de sintaxis PHP y estructura del ZIP. Se recomienda probar visualmente en el tema y las páginas reales de WordPress, incluidos los enlaces de navegación.
