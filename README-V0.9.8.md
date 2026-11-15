# SVCP Directorio Médico — v0.9.8

## Rediseño del directorio público

- Rediseño del catálogo público inspirado en la referencia aportada.
- Paleta aplicada: azul `#0B5A88`, azul claro `#3B9ED8`, turquesa `#4CADAF`, verde menta `#78C8A7`, naranja `#F49A22` y rojo `#FD0600`.
- Encabezado institucional, buscador destacado y selector de región.
- Accesos por región en una cuadrícula con adaptación responsive.
- Tarjetas de médicos en catálogo: foto opcional, iniciales cuando no hay foto, nombre, región, clínica, dirección/ciudad, teléfono, correo y enlaces sociales disponibles.
- Eliminado el enlace público «Ver perfil» de las tarjetas; la navegación pública a una ficha individual redirige al catálogo.
- Se mantienen las reglas existentes de publicación según aprobación y membresía.
- Se mantienen los módulos administrativos, registro, pagos, correo, SMTP y panel privado.

## Instalación

1. Realiza una copia de seguridad del sitio y de la versión actual del plugin.
2. En WordPress, desactiva la versión anterior de SVCP Directorio Médico.
3. Ve a **Plugins → Añadir nuevo → Subir plugin** y carga `svcp-directorio-medico-v0.9.8.zip`.
4. Activa el plugin.
5. Si el tema tiene su propio `archive-doctor.php`, esa plantilla del tema tiene prioridad sobre la del plugin. Retira o actualiza esa plantilla si no ves el nuevo diseño.
6. Limpia la caché del sitio/navegador si el CSS anterior sigue apareciendo.

## Validación

Versión de código actualizada a 0.9.8. Se recomienda completar pruebas visuales en el tema real y probar las acciones de teléfono, correo y redes con fichas de prueba.
