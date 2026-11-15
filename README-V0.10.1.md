# SVCP Directorio Médico v0.10.1

## Ajustes de integración con temas WordPress y responsive

- Se ajustó el portal del médico para respetar el ancho del contenedor de contenido del tema activo, evitando desbordamientos horizontales y márgenes negativos a pantalla completa.
- Se incorporaron container queries para adaptar el panel según el ancho real disponible, además de breakpoints tradicionales como compatibilidad.
- El menú lateral se convierte en navegación superior en contenedores estrechos.
- Las tarjetas de resumen, la vista previa, la fotografía y los formularios se reorganizan progresivamente para tablet y móvil.
- Se mejoró el comportamiento de campos largos, correos, botones y carga de archivos para evitar desbordamientos.
- El directorio público ahora mantiene un ancho máximo centrado, márgenes consistentes, tarjetas con alturas uniformes y fotografías con recorte más equilibrado.
- En móvil, las tarjetas del directorio pasan a un formato horizontal y a una columna; en pantallas muy estrechas se apilan verticalmente.
- Los estilos están aislados bajo las clases SVCP para reducir conflictos con el tema WordPress.

## Compatibilidad y pruebas

- Se conserva la funcionalidad PHP y los módulos de la versión 0.10.0.
- Versión del plugin y constante de assets actualizadas a 0.10.1 para evitar caché de CSS anterior.
- Se recomienda probar en la plantilla/tema activo y limpiar caché del navegador, plugin de caché y CDN después de actualizar.
