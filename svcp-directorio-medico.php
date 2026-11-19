<?php
/**
 * Plugin Name: SVCP Directorio Médico
 * Description: Directorio de doctores con membresía y aprobación manual.
 * Version: 1.0.5
 * Author: NICPRODEV
 * Text Domain: svcp-directorio
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Seguridad: no acceso directo.
}

define( 'SVCP_DIR_PATH', plugin_dir_path( __FILE__ ) );
define( 'SVCP_DIR_URL', plugin_dir_url( __FILE__ ) );
define( 'SVCP_DIR_VERSION', '1.0.5' );

/**
 * Carga de módulos del plugin.
 * Por ahora solo el módulo de Doctores (CPT + taxonomías + campos).
 * El módulo de Notificaciones usa WP-Cron + wp_mail() para avisos de membresía.
 */
require_once SVCP_DIR_PATH . 'includes/class-svcp-cpt-doctor.php';
require_once SVCP_DIR_PATH . 'includes/class-svcp-taxonomias.php';
require_once SVCP_DIR_PATH . 'includes/class-svcp-campos-doctor.php';
require_once SVCP_DIR_PATH . 'includes/class-svcp-admin-columnas.php';
require_once SVCP_DIR_PATH . 'includes/class-svcp-roles.php';
require_once SVCP_DIR_PATH . 'includes/class-svcp-membresia.php';
require_once SVCP_DIR_PATH . 'includes/class-svcp-registro.php';
require_once SVCP_DIR_PATH . 'includes/class-svcp-panel-doctor.php';
require_once SVCP_DIR_PATH . 'includes/class-svcp-acceso-doctor.php';
require_once SVCP_DIR_PATH . 'includes/class-svcp-membresia-cron.php';
require_once SVCP_DIR_PATH . 'includes/class-svcp-admin-membresias.php';
require_once SVCP_DIR_PATH . 'includes/class-svcp-importacion.php';
require_once SVCP_DIR_PATH . 'includes/class-svcp-auditoria.php';
require_once SVCP_DIR_PATH . 'includes/class-svcp-admin-gestion.php';
require_once SVCP_DIR_PATH . 'includes/class-svcp-aprobacion.php';
require_once SVCP_DIR_PATH . 'includes/class-svcp-smtp.php';
require_once SVCP_DIR_PATH . 'includes/class-svcp-admin-dashboard.php';
require_once SVCP_DIR_PATH . 'includes/class-svcp-admin-cron.php';
require_once SVCP_DIR_PATH . 'includes/class-svcp-pagos.php';
require_once SVCP_DIR_PATH . 'includes/class-svcp-ciclo-membresia.php';
require_once SVCP_DIR_PATH . 'includes/class-svcp-tasa-bcv.php';
require_once SVCP_DIR_PATH . 'includes/class-svcp-correos.php';
require_once SVCP_DIR_PATH . 'includes/class-svcp-directorio-publico.php';

/** Permite cerrar los modales del portal con sus botones o con Escape. */
function svcp_dir_modal_interactions() {
    ?>
    <script>
    document.addEventListener('click', function(event) {
        var close = event.target.closest('[data-svcp-modal-close]');
        if (close) {
            var modal = close.closest('[data-svcp-modal]');
            if (modal) {
                modal.classList.remove('svcp-modal-open');
                window.setTimeout(function() { modal.remove(); }, 220);
            }
        }
        if (event.target.matches('[data-svcp-modal]')) {
            event.target.classList.remove('svcp-modal-open');
            window.setTimeout(function() { event.target.remove(); }, 220);
        }
    });
    document.addEventListener('click', function(event) {
        var add = event.target.closest('[data-svcp-add-field]');
        if (!add) return;
        event.preventDefault();
        var group = add.closest('[data-svcp-repeatable]');
        if (!group) return;
        var list = group.querySelector('[data-svcp-field-list]');
        var max = parseInt(group.getAttribute('data-max') || '3', 10);
        if (!list || list.querySelectorAll('input').length >= max) return;
        var wrapper = document.createElement('div');
        wrapper.className = 'svcp-repeatable-field-row';
        var input = document.createElement('input');
        input.type = group.getAttribute('data-field-type') || 'text';
        input.name = group.getAttribute('data-field-name') || '';
        input.placeholder = group.getAttribute('data-field-placeholder') || '';
        wrapper.appendChild(input);
        var remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'svcp-remove-field-link';
        remove.setAttribute('aria-label', 'Quitar campo');
        remove.textContent = 'Quitar';
        wrapper.appendChild(remove);
        list.appendChild(wrapper);
        if (list.querySelectorAll('input').length >= max) add.hidden = true;
    });
    document.addEventListener('click', function(event) {
        var remove = event.target.closest('.svcp-remove-field-link');
        if (!remove) return;
        var group = remove.closest('[data-svcp-repeatable]');
        var row = remove.closest('.svcp-repeatable-field-row');
        if (row) row.remove();
        if (group) {
            var add = group.querySelector('[data-svcp-add-field]');
            if (add) add.hidden = false;
        }
    });
    document.querySelectorAll('[data-svcp-repeatable]').forEach(function(group) {
        var list = group.querySelector('[data-svcp-field-list]');
        var add = group.querySelector('[data-svcp-add-field]');
        var max = parseInt(group.getAttribute('data-max') || '3', 10);
        if (list && add && list.querySelectorAll('input').length >= max) add.hidden = true;
    });
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            document.querySelectorAll('[data-svcp-modal].svcp-modal-open').forEach(function(modal) {
                modal.classList.remove('svcp-modal-open');
                window.setTimeout(function() { modal.remove(); }, 220);
            });
        }
    });
    </script>
    <?php
}
add_action( 'wp_footer', 'svcp_dir_modal_interactions', 99 );

/** Carga Font Awesome 7.3.1 para los iconos sociales del portal y directorio. */
function svcp_dir_cargar_font_awesome() {
    wp_enqueue_style(
        'svcp-font-awesome',
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.1/css/all.min.css',
        array(),
        '7.3.1'
    );
}
add_action( 'wp_enqueue_scripts', 'svcp_dir_cargar_font_awesome', 5 );

function svcp_dir_font_awesome_integrity( $html, $handle, $href, $media ) {
    if ( 'svcp-font-awesome' !== $handle ) {
        return $html;
    }
    return '<link rel="stylesheet" id="svcp-font-awesome-css" href="' . esc_url( $href ) . '" integrity="sha512-QeR2VH+lsBE5LSAe1Q5EnTBbe7XTBubt8dG93Y7gidSgdMCr8nVqKcfKAMyN96SV8KDbZVTDXChatu5G2KQGzg==" crossorigin="anonymous" referrerpolicy="no-referrer" media="' . esc_attr( $media ) . '" />';
}
add_filter( 'style_loader_tag', 'svcp_dir_font_awesome_integrity', 10, 4 );

/**
 * Inicializa el plugin.
 */
function svcp_dir_init() {
    SVCP_CPT_Doctor::instance();
    SVCP_Taxonomias::instance();
    SVCP_Campos_Doctor::instance();
    SVCP_Admin_Columnas::instance();
    SVCP_Roles::instance();
    SVCP_Membresia::instance();
    SVCP_Registro::instance();
    SVCP_Panel_Doctor::instance();
    SVCP_Acceso_Doctor::instance();
    SVCP_Membresia_Cron::instance();
    SVCP_Admin_Membresias::instance();
    SVCP_Importacion::instance();
    SVCP_Auditoria::instance();
    SVCP_Admin_Gestion::instance();
    SVCP_Aprobacion::instance();
    SVCP_SMTP::instance();
    SVCP_Admin_Dashboard::instance();
    SVCP_Admin_Cron::instance();
    SVCP_Pagos::instance();
    SVCP_Ciclo_Membresia::instance();
    SVCP_Tasa_BCV::inicializar();
    SVCP_Correos::inicializar_seguridad_clave();
    SVCP_Directorio_Publico::instance();
    svcp_dir_actualizar_version();
}
add_action( 'plugins_loaded', 'svcp_dir_init' );

/**
 * Ejecuta migraciones menores al actualizar el plugin sin exigir una
 * desactivación/reactivación manual.
 */
function svcp_dir_actualizar_version() {
    $version = get_option( 'svcp_directorio_version', '' );
    if ( version_compare( $version, SVCP_DIR_VERSION, '>=' ) ) {
        return;
    }

    if ( class_exists( 'SVCP_Membresia_Cron' ) ) {
        SVCP_Membresia_Cron::programar();
    }
    if ( class_exists( 'SVCP_Auditoria' ) ) {
        SVCP_Auditoria::instalar();
    }
    if ( class_exists( 'SVCP_Aprobacion' ) ) {
        update_option( 'svcp_aprobacion_version', '1.0.0' );
    }

    // Actualiza las reglas para retirar el archivo automático del CPT doctor.
    if ( class_exists( 'SVCP_CPT_Doctor' ) ) {
        SVCP_CPT_Doctor::registrar_cpt();
        flush_rewrite_rules( false );
    }

    update_option( 'svcp_directorio_version', SVCP_DIR_VERSION, false );
}

/**
 * Al activar el plugin, registramos el CPT y hacemos flush de rewrite rules
 * para que las URLs bonitas (/directorio/estado/florida) funcionen de inmediato.
 */
function svcp_dir_activar() {
    require_once SVCP_DIR_PATH . 'includes/class-svcp-cpt-doctor.php';
    require_once SVCP_DIR_PATH . 'includes/class-svcp-taxonomias.php';
    require_once SVCP_DIR_PATH . 'includes/class-svcp-roles.php';

    SVCP_CPT_Doctor::registrar_cpt();
    SVCP_Taxonomias::registrar_taxonomias();
    SVCP_Taxonomias::precargar_regiones();
    SVCP_Taxonomias::migrar_estados_a_regiones();
    SVCP_Roles::crear_rol();
    SVCP_Roles::asegurar_capacidades();
    require_once SVCP_DIR_PATH . 'includes/class-svcp-membresia-cron.php';
    SVCP_Membresia_Cron::programar();
    require_once SVCP_DIR_PATH . 'includes/class-svcp-auditoria.php';
require_once SVCP_DIR_PATH . 'includes/class-svcp-admin-gestion.php';
require_once SVCP_DIR_PATH . 'includes/class-svcp-aprobacion.php';
require_once SVCP_DIR_PATH . 'includes/class-svcp-smtp.php';
require_once SVCP_DIR_PATH . 'includes/class-svcp-admin-dashboard.php';
require_once SVCP_DIR_PATH . 'includes/class-svcp-admin-cron.php';
    SVCP_Auditoria::instalar();
    update_option( 'svcp_directorio_version', SVCP_DIR_VERSION, false );

    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'svcp_dir_activar' );

/**
 * Al desactivar quitamos el rol (por limpieza), pero los doctores que ya
 * tenían ese rol simplemente quedan sin rol asignado; sus fichas y datos
 * no se tocan. Si reactivas el plugin, el rol se vuelve a crear.
 */
function svcp_dir_desactivar() {
    require_once SVCP_DIR_PATH . 'includes/class-svcp-roles.php';
    require_once SVCP_DIR_PATH . 'includes/class-svcp-membresia-cron.php';
    SVCP_Roles::eliminar_rol();
    $pago_cron = wp_next_scheduled('svcp_recordatorio_pagos_pendientes'); if ( $pago_cron ) wp_unschedule_event($pago_cron, 'svcp_recordatorio_pagos_pendientes');
    SVCP_Membresia_Cron::desprogramar();
    if ( class_exists('SVCP_Tasa_BCV') ) { SVCP_Tasa_BCV::cancelar(); }
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'svcp_dir_desactivar' );

/**
 * Sistema de plantillas: si el tema activo tiene su propio
 * archive-doctor.php o single-doctor.php, ese manda (así el tema
 * puede personalizar el HTML). Si no, usamos el del plugin.
 */
function svcp_dir_cargar_template( $template ) {
    if ( is_post_type_archive( 'doctor' ) ) {
        $template_tema = locate_template( 'archive-doctor.php' );
        return $template_tema ? $template_tema : SVCP_DIR_PATH . 'templates/archive-doctor.php';
    }
    if ( is_singular( 'doctor' ) ) {
        $template_tema = locate_template( 'single-doctor.php' );
        return $template_tema ? $template_tema : SVCP_DIR_PATH . 'templates/single-doctor.php';
    }
    return $template;
}
add_filter( 'template_include', 'svcp_dir_cargar_template' );

/**
 * Impide que una ficha individual no vigente sea visible públicamente.
 * El administrador y el propio doctor autenticado conservan acceso.
 */
function svcp_dir_proteger_ficha_publica() {
    if ( ! is_singular( 'doctor' ) || is_admin() ) {
        return;
    }

    $post_id = get_queried_object_id();
    if ( ! $post_id ) {
        return;
    }

    if ( current_user_can( 'manage_options' ) || current_user_can( 'edit_doctor' ) ) {
        return;
    }

    if ( ( class_exists( 'SVCP_Membresia' ) && ! SVCP_Membresia::puede_aparecer_en_directorio( $post_id ) ) || ( class_exists( 'SVCP_Aprobacion' ) && ! SVCP_Aprobacion::puede_publicarse( $post_id ) ) ) {
        global $wp_query;
        $wp_query->set_404();
        status_header( 404 );
        nocache_headers();
    }
}
add_action( 'template_redirect', 'svcp_dir_proteger_ficha_publica' );

/** En la web pública se utiliza un único catálogo; no se muestran fichas individuales. */
function svcp_dir_unificar_vista_publica() {
    if ( is_singular( 'doctor' ) && ! is_admin() && ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_doctor' ) ) {
        $archivo = get_post_type_archive_link( 'doctor' );
        if ( $archivo ) {
            wp_safe_redirect( $archivo, 301 );
            exit;
        }
    }
}
add_action( 'template_redirect', 'svcp_dir_unificar_vista_publica', 20 );

/**
 * SEO básico para las fichas públicas, compatible con plugins SEO porque
 * solamente actúa si no hay una descripción/canonical equivalente definida.
 * No expone especialidad ni información de membresía en la ficha pública.
 */
function svcp_dir_seo_ficha() {
    if ( ! is_singular( 'doctor' ) ) {
        return;
    }

    $post_id = get_queried_object_id();
    if ( ! $post_id || ( class_exists( 'SVCP_Membresia' ) && ! SVCP_Membresia::puede_aparecer_en_directorio( $post_id ) ) || ( class_exists( 'SVCP_Aprobacion' ) && ! SVCP_Aprobacion::puede_publicarse( $post_id ) ) ) {
        return;
    }

    $nombre  = get_the_title( $post_id );
    $ciudad  = get_post_meta( $post_id, 'svcp_ciudad', true );
    $clinica = get_post_meta( $post_id, 'svcp_clinica', true );
    $partes  = array_filter( array( $ciudad, $clinica ) );
    $desc    = sprintf( 'Perfil profesional de %s%s.', $nombre, $partes ? ' en ' . implode( ', ', $partes ) : '' );
    $url     = get_permalink( $post_id );

    echo '<meta name="description" content="' . esc_attr( wp_trim_words( $desc, 30, '' ) ) . '" />' . "\n";
    echo '<link rel="canonical" href="' . esc_url( $url ) . '" />' . "\n";
    echo '<meta property="og:type" content="profile" />' . "\n";
    echo '<meta property="og:title" content="' . esc_attr( $nombre . ' | SVCP' ) . '" />' . "\n";
    echo '<meta property="og:description" content="' . esc_attr( $desc ) . '" />' . "\n";
    echo '<meta property="og:url" content="' . esc_url( $url ) . '" />' . "\n";
    if ( has_post_thumbnail( $post_id ) ) {
        echo '<meta property="og:image" content="' . esc_url( get_the_post_thumbnail_url( $post_id, 'large' ) ) . '" />' . "\n";
    }
}
add_action( 'wp_head', 'svcp_dir_seo_ficha', 5 );

/**
 * CSS del directorio. Se carga siempre (es liviano); si prefieres que
 * solo cargue en las páginas del directorio, envuelve el enqueue en
 * un if ( is_post_type_archive('doctor') || is_singular('doctor') ... ).
 */
function svcp_dir_cargar_estilos() {
    wp_enqueue_style(
        'svcp-directorio',
        SVCP_DIR_URL . 'assets/css/svcp-directorio.css',
        array(),
        SVCP_DIR_VERSION // usar la versión como "cache-buster": ver sección de actualización.
    );
}
add_action( 'wp_enqueue_scripts', 'svcp_dir_cargar_estilos' );


/** Identifica páginas del portal para ocultar el título duplicado que imprime el tema. */
function svcp_dir_body_classes_portal( $classes ) {
    if ( is_singular() ) {
        $post = get_post();
        if ( $post && has_shortcode( $post->post_content, 'svcp_panel_doctor' ) ) { $classes[] = 'svcp-pagina-panel-doctor'; }
        if ( $post && has_shortcode( $post->post_content, 'svcp_formulario_pago' ) ) { $classes[] = 'svcp-pagina-reporte-pago'; }
    }
    return $classes;
}
add_filter( 'body_class', 'svcp_dir_body_classes_portal' );

function svcp_dir_busqueda_tiempo_real() {
    if ( ! is_post_type_archive( 'doctor' ) ) { return; }
    wp_enqueue_script( 'svcp-directorio-live-search', SVCP_DIR_URL . 'assets/js/svcp-directorio-live-search.js', array(), SVCP_DIR_VERSION, true );
}
add_action( 'wp_enqueue_scripts', 'svcp_dir_busqueda_tiempo_real', 20 );

/** Normaliza las URL públicas del portal SVCP sin reemplazar el contenido existente. */
function svcp_dir_normalizar_urls_portal() {
    if ( ! current_user_can( 'manage_options' ) ) { return; }
    $rutas = array( 'svcp_login_doctor' => 'admin-iniciosesion', 'svcp_registro_doctor' => 'admin-crearcuenta', 'svcp_panel_doctor' => 'admin-miperfil', 'svcp_formulario_pago' => 'admin-reportarpagos', 'svcp_pagos_realizados' => 'admin-pagosrealizados' );
    foreach ( $rutas as $shortcode => $slug ) {
        $paginas = get_pages( array( 'post_status' => 'publish', 'number' => -1 ) );
        foreach ( $paginas as $pagina ) {
            if ( has_shortcode( $pagina->post_content, $shortcode ) && $pagina->post_name !== $slug ) { wp_update_post( array( 'ID' => $pagina->ID, 'post_name' => $slug ) ); }
        }
    }
    $pagos = get_page_by_path( 'admin-pagosrealizados', OBJECT, 'page' );
    if ( ! $pagos ) { $old = get_page_by_path( 'pagos-realizados', OBJECT, 'page' ); if ( $old ) { wp_update_post( array( 'ID' => $old->ID, 'post_name' => 'admin-pagosrealizados' ) ); } }
    $pagos = get_page_by_path( 'admin-pagosrealizados', OBJECT, 'page' );
    if ( ! $pagos ) { $id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Pagos realizados', 'post_name' => 'admin-pagosrealizados', 'post_content' => '[svcp_pagos_realizados]' ), true ); if ( ! is_wp_error( $id ) && $id ) { update_option( 'svcp_pagos_realizados_page_id', absint( $id ) ); } }
}
add_action( 'admin_init', 'svcp_dir_normalizar_urls_portal', 30 );
