<?php
/**
 * Gestión de reportes de pagos manuales de membresía SVCP.
 *
 * @package SVCP_Directorio
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SVCP_Pagos {
    private static $instance = null;
    private const CPT = 'svcp_pago';
    private const META_DOCTOR = '_svcp_doctor_id';
    private const META_DEPOSITANTE = '_svcp_depositante';
    private const META_REFERENCIA = '_svcp_referencia';
    private const META_TELEFONO = '_svcp_telefono';
    private const META_MONTO = '_svcp_monto';
    private const META_FECHA_PAGO = '_svcp_fecha_pago';
    private const META_TASA_BCV = '_svcp_tasa_bcv';
    private const META_TASA_FECHA = '_svcp_tasa_fecha_vigencia';
    private const META_PRECIO_USD = '_svcp_precio_usd';
    private const META_EQUIVALENTE_BS = '_svcp_equivalente_bs';
    private const META_PAGO_USD = '_svcp_pago_equivalente_usd';
    private const META_SALDO_BS = '_svcp_saldo_pendiente_bs';
    private const META_SALDO_USD = '_svcp_saldo_pendiente_usd';
    private const META_METODO = '_svcp_metodo';
    private const META_ESTADO = '_svcp_estado';
    private const META_FECHA = '_svcp_fecha';
    private const META_RESUELTO_POR = '_svcp_resuelto_por';
    private const META_RESUELTO_EN = '_svcp_resuelto_en';
    private const META_MOTIVO = '_svcp_motivo_rechazo';

    public static function instance() {
        return self::$instance ?: ( self::$instance = new self() );
    }

    private function __construct() {
        add_action( 'init', array( $this, 'registrar_cpt' ) );
        add_shortcode( 'svcp_formulario_pago', array( $this, 'shortcode' ) );
        add_shortcode( 'svcp_pagos_realizados', array( $this, 'shortcode_pagos_realizados' ) );
        add_action( 'admin_init', array( $this, 'asegurar_pagina_pagos_realizados' ) );
        add_action( 'admin_post_svcp_enviar_pago', array( $this, 'enviar' ) );
        add_action( 'admin_post_nopriv_svcp_enviar_pago', array( $this, 'enviar' ) );
        add_action( 'admin_menu', array( $this, 'menu' ) );
        add_filter( 'parent_file', array( $this, 'badge_menu_pagos' ) );
        add_action( 'admin_post_svcp_resolver_pago', array( $this, 'resolver' ) );
        add_action( 'admin_post_svcp_descargar_comprobante', array( $this, 'descargar_comprobante' ) );
        add_action( 'admin_post_svcp_recibo_pdf', array( $this, 'descargar_recibo_pdf' ) );
        add_action( 'admin_post_svcp_exportar_pagos', array( $this, 'exportar_csv' ) );
        add_action( 'before_delete_post', array( $this, 'eliminar_comprobante_privado' ) );
        add_action( 'svcp_recordatorio_pagos_pendientes', array( $this, 'notificar_pagos_pendientes' ) );
        if ( ! wp_next_scheduled('svcp_recordatorio_pagos_pendientes') ) wp_schedule_event( time() + 2 * HOUR_IN_SECONDS, 'daily', 'svcp_recordatorio_pagos_pendientes' );
        add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
    }

    public function registrar_cpt() {
        register_post_type(
            self::CPT,
            array(
                'labels' => array(
                    'name' => 'Pagos SVCP',
                    'singular_name' => 'Pago SVCP',
                ),
                'public' => false,
                'show_ui' => false,
                'show_in_rest' => false,
                'supports' => array( 'title', 'author' ),
                'capability_type' => 'post',
                'map_meta_cap' => true,
            )
        );
    }

    public function menu() {
        add_submenu_page(
            'edit.php?post_type=doctor',
            'Pagos de membresía',
            $this->etiqueta_menu_pagos(),
            'manage_options',
            'svcp-pagos',
            array( $this, 'admin_page' )
        );
    }


    private function contar_pendientes() {
        $q = new WP_Query(array('post_type'=>self::CPT,'post_status'=>'private','posts_per_page'=>1,'fields'=>'ids','meta_query'=>array(array('key'=>self::META_ESTADO,'value'=>'pendiente')),'no_found_rows'=>false));
        return (int) $q->found_posts;
    }

    private function etiqueta_menu_pagos() {
        $n = $this->contar_pendientes();
        return 'Pagos de membresía' . ( $n > 0 ? ' <span class="awaiting-mod count-' . absint($n) . '"><span class="pending-count">' . number_format_i18n($n) . '</span></span>' : '' );
    }

    public function badge_menu_pagos( $parent_file ) {
        // WordPress puede reconstruir el menú durante la navegación; el contador también se actualiza allí.
        global $submenu;
        if ( isset($submenu['edit.php?post_type=doctor']) && is_array($submenu['edit.php?post_type=doctor']) ) {
            foreach ( $submenu['edit.php?post_type=doctor'] as &$item ) {
                if ( isset($item[2]) && 'svcp-pagos' === $item[2] ) { $item[0] = $this->etiqueta_menu_pagos(); break; }
            }
            unset($item);
        }
        return $parent_file;
    }

    public function admin_assets( $hook ) {
        if ( 'doctor_page_svcp-pagos' !== $hook ) {
            return;
        }
        wp_register_style( 'svcp-pagos-admin', false, array(), SVCP_DIR_VERSION );
        wp_enqueue_style( 'svcp-pagos-admin' );
        wp_add_inline_style( 'svcp-pagos-admin', $this->admin_css() );
    }

    private function admin_css() {
        return <<<'CSS'
.svcp-pagos-admin{--svcp-primary:#173f5f;--svcp-primary-2:#1f6f8b;--svcp-accent:#0f8b8d;--svcp-bg:#f4f7fa;--svcp-border:#dfe7ee}.svcp-pagos-header{display:flex;justify-content:space-between;gap:20px;align-items:flex-end;margin:22px 0}.svcp-pagos-header h1{margin:0 0 6px;color:var(--svcp-primary)}.svcp-pagos-header p{margin:0;color:#5f6f7a}.svcp-pagos-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:0 0 18px}.svcp-pago-stat{background:#fff;border:1px solid var(--svcp-border);border-radius:14px;padding:16px;box-shadow:0 2px 10px rgba(23,63,95,.05)}.svcp-pago-stat small{display:block;color:#687782;text-transform:uppercase;letter-spacing:.06em;font-weight:700;font-size:11px}.svcp-pago-stat strong{display:block;margin-top:5px;font-size:24px;color:var(--svcp-primary)}.svcp-pagos-filtros{background:#fff;border:1px solid var(--svcp-border);border-radius:14px;padding:14px;margin:0 0 18px;display:flex;gap:8px;flex-wrap:wrap;align-items:center}.svcp-pagos-filtros input,.svcp-pagos-filtros select{min-height:36px}.svcp-pagos-tabla{border:1px solid var(--svcp-border);border-radius:14px;overflow:hidden;background:#fff}.svcp-pagos-tabla table{border:0}.svcp-pagos-tabla th{color:var(--svcp-primary);font-size:12px;text-transform:uppercase;letter-spacing:.03em}.svcp-pago-estado{display:inline-flex;padding:5px 9px;border-radius:999px;font-size:11px;font-weight:700}.svcp-pago-estado-pendiente{background:#fff7e6;color:#9a6700}.svcp-pago-estado-aprobado{background:#e8f7f1;color:#087f5b}.svcp-pago-estado-rechazado{background:#fdecec;color:#b42318}.svcp-pago-acciones{display:flex;gap:6px;flex-wrap:wrap}.svcp-pago-acciones .button-primary{background:var(--svcp-primary-2);border-color:var(--svcp-primary-2)}.svcp-pago-rechazo{margin-top:7px;display:flex;gap:5px}.svcp-pago-rechazo input{max-width:220px}.svcp-pago-vacio{padding:35px;text-align:center;color:#687782}.svcp-pagos-admin .tablenav{padding:10px 12px}.svcp-pago-dato-secundario{display:block;color:#6b7780;font-size:11px;margin-top:3px}.svcp-pagos-alerta{padding:12px 14px;border-left:4px solid var(--svcp-accent);background:#eef8f8;border-radius:6px;margin:0 0 18px}.svcp-pagos-admin .notice{margin:15px 0}.svcp-pagos-admin .button-link-delete{color:#b42318}.svcp-pagos-admin .svcp-pago-modal-form{margin:0}
@media(max-width:900px){.svcp-pagos-stats{grid-template-columns:1fr}.svcp-pagos-tabla{overflow:auto}.svcp-pagos-header{display:block}.svcp-pagos-header .button{margin-top:12px}}
CSS;
    }


    /**
     * Crea la página del historial una sola vez si todavía no existe.
     * Si el administrador ya creó una página con ese slug, se respeta su contenido.
     */
    public function asegurar_pagina_pagos_realizados() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $saved_page_id = absint( get_option( 'svcp_pagos_realizados_page_id' ) );
        if ( $saved_page_id && 'page' === get_post_type( $saved_page_id ) ) {
            return;
        }
        delete_option( 'svcp_pagos_realizados_page_id' );

        $pagina = get_page_by_path( 'pagos-realizados', OBJECT, 'page' );
        if ( $pagina ) {
            update_option( 'svcp_pagos_realizados_page_id', (int) $pagina->ID );
            return;
        }

        $page_id = wp_insert_post( array(
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_title'   => 'Pagos realizados',
            'post_name'    => 'pagos-realizados',
            'post_content' => '[svcp_pagos_realizados]',
        ), true );

        if ( ! is_wp_error( $page_id ) && $page_id ) {
            update_option( 'svcp_pagos_realizados_page_id', absint( $page_id ) );
        }
    }

    /**
     * Obtiene la ficha vinculada a la cuenta del médico, con compatibilidad
     * para fichas antiguas asociadas por autor o correo.
     */
    private function obtener_doctor_del_usuario( $user_id ) {
        $user = get_user_by( 'id', $user_id );
        if ( ! $user || ! in_array( 'doctor_svcp', (array) $user->roles, true ) ) {
            return 0;
        }

        $ids = get_posts( array(
            'post_type'      => 'doctor',
            'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_key'       => 'svcp_usuario_id',
            'meta_value'     => $user_id,
        ) );
        if ( $ids ) {
            return (int) $ids[0];
        }

        $ids = get_posts( array(
            'post_type'      => 'doctor',
            'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
            'author'         => $user_id,
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ) );
        if ( $ids ) {
            update_post_meta( (int) $ids[0], 'svcp_usuario_id', $user_id );
            return (int) $ids[0];
        }

        $ids = get_posts( array(
            'post_type'      => 'doctor',
            'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_key'       => 'svcp_correo',
            'meta_value'     => $user->user_email,
        ) );
        if ( $ids ) {
            update_post_meta( (int) $ids[0], 'svcp_usuario_id', $user_id );
            return (int) $ids[0];
        }

        return 0;
    }

    /** Página independiente del historial de pagos del médico. */
    public function shortcode_pagos_realizados() {
        if ( ! is_user_logged_in() ) {
            return '<div class="svcp-pago-aviso svcp-pago-aviso-login"><strong>Acceso requerido</strong><span>Inicia sesión con tu cuenta de médico para consultar tus pagos.</span></div>';
        }

        $user = wp_get_current_user();
        $doctor_id = $this->obtener_doctor_del_usuario( $user->ID );
        if ( ! $doctor_id ) {
            return '<div class="svcp-pago-aviso"><strong>Ficha no encontrada</strong><span>No se encontró una ficha médica asociada a esta cuenta. Contacta a la SVCP para regularizar tu registro.</span></div>';
        }

        $portal_url = apply_filters( 'svcp_url_portal_medico', home_url( '/admin-miperfil/' ) );
        $pago_url = apply_filters( 'svcp_url_portal_pagos', home_url( '/admin-reportarpagos/' ) );
        $directorio_url = get_post_type_archive_link( 'doctor' ) ?: home_url( '/sociedad-directorio-medico/' );
        $pagina_pagos_url = get_permalink( get_queried_object_id() );
        if ( ! $pagina_pagos_url ) {
            $pagina_id = absint( get_option( 'svcp_pagos_realizados_page_id' ) );
            $pagina_pagos_url = $pagina_id ? get_permalink( $pagina_id ) : home_url( '/admin-pagosrealizados/' );
        }

        $pagos = get_posts( array(
            'post_type'      => self::CPT,
            'post_status'    => 'private',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => array(
                array(
                    'key'     => self::META_DOCTOR,
                    'value'   => $doctor_id,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ),
            ),
        ) );

        $stats = array( 'total' => count( $pagos ), 'aprobado' => 0, 'pendiente' => 0, 'rechazado' => 0 );
        foreach ( $pagos as $pago_id ) {
            $estado = get_post_meta( $pago_id, self::META_ESTADO, true );
            if ( isset( $stats[ $estado ] ) ) {
                ++$stats[ $estado ];
            }
        }

        $deuda_usd = max( 0, (float) get_post_meta( $doctor_id, 'svcp_membresia_saldo_pendiente_usd', true ) );
        $tasa_hoy = class_exists( 'SVCP_Tasa_BCV' ) ? SVCP_Tasa_BCV::obtener( current_time( 'Y-m-d' ), false ) : new WP_Error( 'sin_tasa', '' );
        $deuda_bs = ( ! is_wp_error( $tasa_hoy ) && $deuda_usd > 0 ) ? $deuda_usd * (float) $tasa_hoy['tasa'] : 0;
        $foto = get_the_post_thumbnail_url( $doctor_id, 'thumbnail' );
        $inicial = function_exists( 'mb_substr' ) ? mb_substr( get_the_title( $doctor_id ), 0, 1 ) : substr( get_the_title( $doctor_id ), 0, 1 );

        ob_start();
        ?>
        <div class="svcp-portal-layout svcp-portal-payment-layout svcp-portal-history-layout">
            <aside class="svcp-portal-sidebar" aria-label="Navegación del portal">
                <a class="svcp-portal-brand" href="<?php echo esc_url( $portal_url ); ?>"><strong>SVCP</strong><span>Portal del médico</span></a>
                <nav class="svcp-portal-nav">
                    <a href="<?php echo esc_url( $portal_url ); ?>"><span aria-hidden="true">♙</span>Mi perfil</a>
                    <a href="<?php echo esc_url( $pago_url ); ?>"><span aria-hidden="true">▤</span>Membresía y pago</a>
                    <div class="svcp-portal-subnav"><a class="svcp-portal-subnav-link is-active" aria-current="page" href="<?php echo esc_url( $pagina_pagos_url ); ?>"><span aria-hidden="true">↳</span>Pagos realizados</a></div>
                    <a href="<?php echo esc_url( $directorio_url ); ?>"><span aria-hidden="true">◎</span>Ver directorio</a>
                </nav>
                <a class="svcp-portal-logout" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><span aria-hidden="true">⇥</span>Cerrar sesión</a>
            </aside>
            <main class="svcp-portal-main">
                <header class="svcp-portal-topbar">
                    <div><span class="svcp-panel-eyebrow">Portal del médico</span><h1>Pagos realizados</h1></div>
                    <div class="svcp-portal-user" aria-label="<?php echo esc_attr( $user->display_name ); ?>">
                        <?php if ( $foto ) : ?><img src="<?php echo esc_url( $foto ); ?>" alt=""><?php else : ?><span><?php echo esc_html( strtoupper( $inicial ) ); ?></span><?php endif; ?>
                    </div>
                </header>
                <div class="svcp-portal-content">
                    <section class="svcp-portal-history-intro">
                        <div><span class="svcp-panel-eyebrow">Membresía SVCP</span><h2>Historial de pagos</h2><p>Consulta el estado de tus reportes y descarga el comprobante PDF de cada pago aprobado.</p></div>
                        <a class="svcp-portal-primary-button" href="<?php echo esc_url( $pago_url ); ?>">＋ Reportar un nuevo pago</a>
                    </section>

                    <section class="svcp-portal-history-stats" aria-label="Resumen de pagos">
                        <article class="svcp-portal-history-stat"><span>Total de reportes</span><strong><?php echo esc_html( number_format_i18n( $stats['total'] ) ); ?></strong></article>
                        <article class="svcp-portal-history-stat"><span>Aprobados</span><strong class="is-approved"><?php echo esc_html( number_format_i18n( $stats['aprobado'] ) ); ?></strong></article>
                        <article class="svcp-portal-history-stat"><span>Pendientes</span><strong class="is-pending"><?php echo esc_html( number_format_i18n( $stats['pendiente'] ) ); ?></strong></article>
                        <article class="svcp-portal-history-stat"><span>Rechazados</span><strong class="is-rejected"><?php echo esc_html( number_format_i18n( $stats['rechazado'] ) ); ?></strong></article>
                    </section>

                    <section class="svcp-portal-card svcp-portal-account-card">
                        <div><h2>Estado de cuenta</h2><p>Saldo pendiente de membresía</p></div>
                        <div class="svcp-portal-account-values">
                            <strong>USD <?php echo esc_html( number_format_i18n( $deuda_usd, 4 ) ); ?></strong>
                            <span><?php echo is_wp_error( $tasa_hoy ) ? 'Tasa BCV no disponible' : 'Bs. ' . esc_html( number_format_i18n( $deuda_bs, 2 ) ); ?></span>
                        </div>
                    </section>

                    <section class="svcp-portal-card svcp-portal-payments">
                        <div class="svcp-panel-section-heading"><h2>Pagos registrados</h2><p>Los comprobantes PDF estarán disponibles una vez aprobado cada pago.</p></div>
                        <?php if ( $pagos ) : ?>
                            <div class="svcp-portal-table-wrap">
                                <table class="svcp-portal-history-table">
                                    <thead><tr><th>Fecha</th><th>Referencia</th><th>Monto recibido</th><th>Equivalente USD</th><th>Estado</th><th>Comprobante</th></tr></thead>
                                    <tbody>
                                    <?php foreach ( $pagos as $pago_id ) :
                                        $estado = get_post_meta( $pago_id, self::META_ESTADO, true ) ?: 'pendiente';
                                        $monto = (float) get_post_meta( $pago_id, self::META_MONTO, true );
                                        $usd = (float) get_post_meta( $pago_id, self::META_PAGO_USD, true );
                                        $fecha = get_post_meta( $pago_id, self::META_FECHA_PAGO, true );
                                        $referencia = get_post_meta( $pago_id, self::META_REFERENCIA, true );
                                        $recibo = wp_nonce_url( admin_url( 'admin-post.php?action=svcp_recibo_pdf&pago_id=' . $pago_id ), 'svcp_recibo_' . $pago_id );
                                        ?>
                                        <tr>
                                            <td><?php echo esc_html( $fecha ? date_i18n( get_option( 'date_format' ), strtotime( $fecha ) ) : get_the_date( get_option( 'date_format' ), $pago_id ) ); ?></td>
                                            <td><?php echo esc_html( $referencia ? $referencia : '—' ); ?></td>
                                            <td>Bs. <?php echo esc_html( number_format_i18n( $monto, 2 ) ); ?></td>
                                            <td>USD <?php echo esc_html( number_format_i18n( $usd, 4 ) ); ?></td>
                                            <td><span class="svcp-pago-estado svcp-pago-estado-<?php echo esc_attr( sanitize_html_class( $estado ) ); ?>"><?php echo esc_html( ucfirst( $estado ) ); ?></span></td>
                                            <td><?php if ( 'aprobado' === $estado ) : ?><a class="svcp-portal-pdf-button" href="<?php echo esc_url( $recibo ); ?>">Descargar PDF</a><?php else : ?><span class="svcp-portal-pdf-pending">Disponible al aprobarse</span><?php endif; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else : ?>
                            <div class="svcp-portal-empty-state"><span aria-hidden="true">▤</span><strong>Aún no tienes pagos registrados</strong><p>Cuando envíes un reporte de pago, aparecerá aquí con su estado de verificación.</p><a class="svcp-portal-outline-button" href="<?php echo esc_url( $pago_url ); ?>">Reportar mi primer pago</a></div>
                        <?php endif; ?>
                    </section>
                </div>
            </main>
        </div>
        <?php
        return ob_get_clean();
    }

    public function shortcode() {
        if ( ! is_user_logged_in() ) {
            return '<div class="svcp-pago-aviso svcp-pago-aviso-login"><strong>Acceso requerido</strong><span>Inicia sesión con tu cuenta de médico para reportar el pago de tu membresía.</span></div>';
        }

        $uid = get_current_user_id();
        $doctor_ids = get_posts(
            array(
                'post_type' => 'doctor',
                'post_status' => 'any',
                'posts_per_page' => 1,
                'fields' => 'ids',
                'meta_key' => 'svcp_usuario_id',
                'meta_value' => $uid,
            )
        );

        if ( empty( $doctor_ids ) ) {
            return '<div class="svcp-pago-aviso"><strong>Ficha no encontrada</strong><span>No se encontró una ficha médica asociada a esta cuenta. Contacta a la SVCP para regularizar tu registro.</span></div>';
        }

        $doctor_id = (int) $doctor_ids[0];
        $vence = get_post_meta( $doctor_id, 'svcp_membresia_vencimiento', true );
        $estado = get_post_meta( $doctor_id, 'svcp_membresia_estado', true );
        $activa = (bool) get_post_meta( $doctor_id, 'svcp_membresia_activa', true );
        $estado_visual = 'Sin vigencia registrada';
        if ( $vence && $vence >= current_time( 'Y-m-d' ) && $activa ) {
            $estado_visual = 'Vigente hasta ' . wp_date( 'd/m/Y', strtotime( $vence ), wp_timezone() );
        } elseif ( $estado ) {
            $estado_visual = ucfirst( sanitize_text_field( $estado ) );
        } elseif ( $vence ) {
            $estado_visual = 'Vencida desde ' . wp_date( 'd/m/Y', strtotime( $vence ), wp_timezone() );
        }

        $mensaje = '';
        if ( isset( $_GET['svcp_pago_enviado'] ) && '1' === sanitize_key( wp_unslash( $_GET['svcp_pago_enviado'] ) ) ) {
            $mensaje = '<dialog class="svcp-pago-modal" id="svcp-pago-modal" aria-labelledby="svcp-pago-modal-titulo"><div class="svcp-pago-modal-icon" aria-hidden="true">✓</div><h2 id="svcp-pago-modal-titulo">¡Reporte enviado!</h2><p>Tu pago quedó registrado y está pendiente de verificación por la administración.</p><p>Te enviaremos un correo cuando sea aprobado o rechazado.</p><button type="button" class="svcp-pago-modal-cerrar" onclick="document.getElementById(\'svcp-pago-modal\').close()">Entendido</button></dialog><style>.svcp-pago-modal{width:min(440px,calc(100% - 32px));border:0;border-radius:18px;padding:30px;text-align:center;color:#173f5f;box-shadow:0 24px 80px rgba(12,35,52,.28)}.svcp-pago-modal::backdrop{background:rgba(13,29,43,.62);backdrop-filter:blur(3px)}.svcp-pago-modal-icon{width:58px;height:58px;margin:0 auto 16px;border-radius:50%;display:grid;place-items:center;background:#e7f7ef;color:#087f5b;font-size:30px;font-weight:700}.svcp-pago-modal h2{margin:0 0 12px;font-size:24px}.svcp-pago-modal p{color:#61717c;line-height:1.6;margin:0 0 10px}.svcp-pago-modal-cerrar{margin-top:14px;border:0;border-radius:9px;background:#173f5f;color:#fff;padding:12px 24px;font-weight:700;cursor:pointer}.svcp-pago-modal-cerrar:hover{background:#1f6f8b}</style><script>(function(){var d=document.getElementById("svcp-pago-modal");if(d&&typeof d.showModal==="function"){d.showModal();d.addEventListener("click",function(e){if(e.target===d){d.close();}});}})();</script>';
        } elseif ( isset( $_GET['svcp_pago_error'] ) ) {
            $error = sanitize_text_field( rawurldecode( wp_unslash( $_GET['svcp_pago_error'] ) ) );
            if ( $error ) {
                $mensaje = '<div class="svcp-pago-alerta-publico svcp-pago-alerta-error" role="alert"><strong>No se pudo enviar el reporte.</strong><span>' . esc_html( $error ) . '</span></div>';
            }
        }

        $portal_url = apply_filters( 'svcp_url_portal_medico', home_url( '/admin-miperfil/' ) );
        $pagos_realizados_id = absint( get_option( 'svcp_pagos_realizados_page_id' ) );
        $pagos_realizados_page = get_page_by_path( 'pagos-realizados', OBJECT, 'page' );
        $pagos_realizados_url = $pagos_realizados_id ? get_permalink( $pagos_realizados_id ) : ( $pagos_realizados_page ? get_permalink( $pagos_realizados_page ) : home_url( '/admin-pagosrealizados/' ) );
        $user = wp_get_current_user();
        $doctor_foto = get_the_post_thumbnail_url( $doctor_id, 'thumbnail' );
        $doctor_inicial = function_exists( 'mb_substr' ) ? mb_substr( get_the_title( $doctor_id ), 0, 1 ) : substr( get_the_title( $doctor_id ), 0, 1 );

        ob_start();
        echo '<div class="svcp-portal-layout svcp-portal-payment-layout">';
        echo '<aside class="svcp-portal-sidebar" aria-label="Navegación del portal">';
        echo '<a class="svcp-portal-brand" href="' . esc_url( $portal_url ) . '"><strong>SVCP</strong><span>Portal del médico</span></a>';
        echo '<nav class="svcp-portal-nav">';
        echo '<a href="' . esc_url( $portal_url ) . '"><span aria-hidden="true">♙</span>Mi perfil</a>';
        echo '<a class="is-active" href="' . esc_url( get_permalink() ) . '"><span aria-hidden="true">▤</span>Membresía y pago</a>';
        echo '<div class="svcp-portal-subnav"><a class="svcp-portal-subnav-link" href="' . esc_url( $pagos_realizados_url ) . '"><span aria-hidden="true">↳</span>Pagos realizados</a></div>';
        echo '<a href="' . esc_url( get_post_type_archive_link( 'doctor' ) ?: home_url( '/sociedad-directorio-medico/' ) ) . '"><span aria-hidden="true">◎</span>Ver directorio</a>';
        echo '</nav><a class="svcp-portal-logout" href="' . esc_url( wp_logout_url( home_url( '/' ) ) ) . '"><span aria-hidden="true">⇥</span>Cerrar sesión</a></aside>';
        echo '<main class="svcp-portal-main"><header class="svcp-portal-topbar"><div><span class="svcp-panel-eyebrow">Portal del médico</span><h1>Membresía y pago</h1></div><div class="svcp-portal-user" aria-label="' . esc_attr( $user->display_name ) . '">';
        if ( $doctor_foto ) { echo '<img src="' . esc_url( $doctor_foto ) . '" alt="">'; } else { echo '<span>' . esc_html( strtoupper( $doctor_inicial ) ) . '</span>'; }
        echo '</div></header><div class="svcp-portal-content">';
        echo '<section class="svcp-pago-wrap" aria-labelledby="svcp-pago-titulo">';
        echo '<div class="svcp-pago-encabezado"><span class="svcp-pago-kicker">Membresía SVCP</span><h2 id="svcp-pago-titulo">Reportar pago</h2><p>Completa los datos exactamente como aparecen en tu comprobante. La membresía no se activa hasta que la sociedad verifique el pago.</p></div>';
        echo '<div class="svcp-pago-resumen"><div><span>Afiliado</span><strong>' . esc_html( get_the_title( $doctor_id ) ) . '</strong></div><div><span>Estado actual</span><strong>' . esc_html( $estado_visual ) . '</strong></div></div>';
        $ajustes_membresia = class_exists( 'SVCP_Ciclo_Membresia' ) ? SVCP_Ciclo_Membresia::settings() : array();
        $precio_usd = isset( $ajustes_membresia['precio_membresia_usd'] ) ? (float) $ajustes_membresia['precio_membresia_usd'] : 0;
        $saldo_usd_actual = max( 0, (float) get_post_meta( $doctor_id, 'svcp_membresia_saldo_pendiente_usd', true ) );
        $fecha_pago_default = current_time('Y-m-d');
        $tasa_actual = class_exists('SVCP_Tasa_BCV') ? SVCP_Tasa_BCV::obtener($fecha_pago_default, false) : new WP_Error('sin_servicio','Servicio de tasas no disponible');
        if ( $saldo_usd_actual > 0 ) { $precio_usd = $saldo_usd_actual; }
        if ( $precio_usd > 0 && ! is_wp_error($tasa_actual) ) { $equivalente = $precio_usd * (float) $tasa_actual['tasa']; echo '<div style="margin:16px 0"><button type="button" class="svcp-pago-boton" onclick="document.getElementById(\'svcp-precio-modal\').showModal()">Consultar monto de membresía</button></div><dialog id="svcp-precio-modal" class="svcp-precio-modal"><button type="button" class="svcp-precio-cerrar" aria-label="Cerrar" onclick="document.getElementById(\'svcp-precio-modal\').close()">×</button><span class="svcp-pago-kicker">Membresía SVCP</span><h2>'.( $saldo_usd_actual > 0 ? 'Saldo pendiente' : 'Monto institucional' ).'</h2><strong class="svcp-precio-usd">USD '.esc_html(number_format_i18n($precio_usd,2)).'</strong><strong class="svcp-precio-bs">Bs. '.esc_html(number_format_i18n($equivalente,2)).'</strong><p>Tasa BCV: Bs. '.esc_html(number_format_i18n((float)$tasa_actual['tasa'],4)).' por USD<br>Vigencia: '.esc_html(wp_date('d/m/Y',strtotime($tasa_actual['fecha_vigencia']),wp_timezone())).'</p><small>La cotización corresponde a hoy; si seleccionas otra fecha de pago, se guardará su tasa histórica.</small><button type="button" class="svcp-pago-boton" onclick="document.getElementById(\'svcp-precio-modal\').close()">Entendido</button></dialog><style>.svcp-precio-modal{width:min(440px,calc(100% - 32px));border:0;border-radius:18px;padding:30px;color:#173f5f;box-shadow:0 24px 80px rgba(12,35,52,.28)}.svcp-precio-modal::backdrop{background:rgba(13,29,43,.62);backdrop-filter:blur(3px)}.svcp-precio-modal h2{margin:8px 0 18px}.svcp-precio-usd,.svcp-precio-bs{display:block;font-size:26px;margin:8px 0}.svcp-precio-usd{color:#0b5a88}.svcp-precio-bs{color:#087f72}.svcp-precio-modal p,.svcp-precio-modal small{color:#61717c;line-height:1.6}.svcp-precio-cerrar{float:right;border:0;background:transparent;font-size:28px;cursor:pointer;color:#61717c}</style>'; } elseif ( $precio_usd > 0 ) { echo '<div class="svcp-pago-alerta-publico svcp-pago-alerta-error" role="alert"><strong>No se pudo consultar la tasa BCV.</strong><span>Por seguridad, no se muestra una conversión estimada. Intenta más tarde o contacta a la administración.</span></div>'; }
        $datos_pago = class_exists( 'SVCP_Ciclo_Membresia' ) ? SVCP_Ciclo_Membresia::settings() : array();
        $campos_pago = array(
            'Banco' => isset( $datos_pago['payment_bank'] ) ? $datos_pago['payment_bank'] : '',
            'Tipo de cuenta' => isset( $datos_pago['payment_account_type'] ) ? $datos_pago['payment_account_type'] : '',
            'Número de cuenta' => isset( $datos_pago['payment_account_number'] ) ? $datos_pago['payment_account_number'] : '',
            'Titular' => isset( $datos_pago['payment_holder'] ) ? $datos_pago['payment_holder'] : '',
            'RIF / Cédula' => isset( $datos_pago['payment_document'] ) ? $datos_pago['payment_document'] : '',
            'Teléfono pago móvil' => isset( $datos_pago['payment_phone'] ) ? $datos_pago['payment_phone'] : '',
            'Correo asociado' => isset( $datos_pago['payment_email'] ) ? $datos_pago['payment_email'] : '',
        );
        $hay_datos_pago = (bool) array_filter( $campos_pago );
        if ( $hay_datos_pago || ! empty( $datos_pago['payment_instructions'] ) ) {
            echo '<section class="svcp-pago-datos-institucionales" style="margin:18px 0;padding:20px;border:1px solid #dce7ed;border-radius:14px;background:#fff"><div style="margin-bottom:14px"><span class="svcp-pago-kicker">Canales oficiales</span><h3 style="margin:5px 0;color:#173f5f">Datos para realizar el pago</h3><p style="margin:0;color:#647681">Utiliza estos datos para efectuar tu transferencia, depósito o pago móvil.</p></div><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:12px">';
            foreach ( $campos_pago as $etiqueta => $valor ) {
                if ( '' === trim( (string) $valor ) ) { continue; }
                echo '<div style="padding:12px;border:1px solid #e8eef2;border-radius:9px;background:#f8fafb"><small style="display:block;color:#687782;margin-bottom:4px">' . esc_html( $etiqueta ) . '</small><strong style="color:#173f5f;overflow-wrap:anywhere">' . esc_html( $valor ) . '</strong></div>';
            }
            echo '</div>';
            if ( ! empty( $datos_pago['payment_instructions'] ) ) {
                echo '<p style="margin:14px 0 0;padding:12px;background:#edf8f8;border-radius:8px;color:#36545f;white-space:pre-line">' . esc_html( $datos_pago['payment_instructions'] ) . '</p>';
            }
            echo '</section>';
        }
        echo $mensaje; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- contenido generado y escapado internamente.
        ?>
        <form class="svcp-form-pago" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate>
            <input type="hidden" name="action" value="svcp_enviar_pago">
            <input type="hidden" name="doctor_id" value="<?php echo esc_attr( $doctor_id ); ?>">
            <?php wp_nonce_field( 'svcp_enviar_pago', 'svcp_pago_nonce' ); ?>
            <div class="svcp-pago-grid">
                <p class="svcp-pago-campo"><label for="svcp-depositante">Nombre y apellido del depositante <span aria-hidden="true">*</span></label><input id="svcp-depositante" type="text" name="depositante" required maxlength="160" autocomplete="name"><small>Nombre del titular de la cuenta que realizó la operación.</small></p>
                <p class="svcp-pago-campo"><label for="svcp-referencia">Referencia bancaria <span aria-hidden="true">*</span></label><input id="svcp-referencia" type="text" name="referencia" required inputmode="text" pattern="[A-Za-z0-9]{6}" minlength="6" maxlength="6" autocomplete="off" aria-describedby="svcp-ref-ayuda"><small id="svcp-ref-ayuda">Solo los últimos 6 caracteres.</small></p>
                <p class="svcp-pago-campo"><label for="svcp-telefono">Teléfono del propietario <span aria-hidden="true">*</span></label><input id="svcp-telefono" type="tel" name="telefono" required maxlength="40" autocomplete="tel"></p>
                <p class="svcp-pago-campo"><label for="svcp-fecha-pago">Fecha de realización del pago <span aria-hidden="true">*</span></label><input id="svcp-fecha-pago" type="date" name="fecha_pago" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" max="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" required></p>
                <p class="svcp-pago-campo"><label for="svcp-monto">Monto transferido en Bs. <span aria-hidden="true">*</span></label><input id="svcp-monto" type="number" name="monto" min="0.01" max="999999999.99" step="0.01" required inputmode="decimal"></p>
                <p class="svcp-pago-campo svcp-pago-campo-ancho"><label for="svcp-metodo">Método de pago <span aria-hidden="true">*</span></label><select id="svcp-metodo" name="metodo" required><option value="">Seleccionar método</option><option value="Transferencia">Transferencia bancaria</option><option value="Pago móvil">Pago móvil</option><option value="Depósito">Depósito bancario</option><option value="Otro">Otro</option></select></p>
                <p class="svcp-pago-campo svcp-pago-campo-ancho"><label for="svcp-comprobante">Captura del comprobante <span aria-hidden="true">*</span></label><input id="svcp-comprobante" type="file" name="comprobante" accept="image/jpeg,image/png,image/webp" required aria-describedby="svcp-comprobante-ayuda"><small id="svcp-comprobante-ayuda">Sube una imagen JPG, PNG o WebP, máximo 5 MB. No cargues datos que no sean necesarios para verificar el pago.</small></p>
            </div>
            <div class="svcp-pago-seguridad"><span aria-hidden="true">✓</span><p><strong>Verificación manual</strong><br>El reporte se almacena de forma segura y queda pendiente de revisión por la administración.</p></div>
            <button class="svcp-pago-boton" type="submit">Enviar reporte de pago</button>
        </form>
        <?php
        echo '</section></div></main></div>';
        return ob_get_clean();
    }

    public function enviar() {
        if ( ! is_user_logged_in() ) {
            $this->fallo_publico( 'Debes iniciar sesión para reportar un pago.' );
        }
        $nonce = isset( $_POST['svcp_pago_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['svcp_pago_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'svcp_enviar_pago' ) ) {
            $this->fallo_publico( 'La sesión del formulario expiró. Recarga la página e inténtalo nuevamente.' );
        }

        $uid = get_current_user_id();
        $doctor = isset( $_POST['doctor_id'] ) ? absint( $_POST['doctor_id'] ) : 0;
        if ( ! $doctor || 'doctor' !== get_post_type( $doctor ) || (int) get_post_meta( $doctor, 'svcp_usuario_id', true ) !== $uid ) {
            $this->fallo_publico( 'No estás autorizado para reportar pagos de esta ficha.' );
        }

        $limite_key = 'svcp_pago_rate_' . $uid;
        if ( get_transient( $limite_key ) ) {
            $this->fallo_publico( 'Ya registraste un reporte recientemente. Espera unos minutos antes de enviar otro.' );
        }

        $depositante = isset( $_POST['depositante'] ) ? sanitize_text_field( wp_unslash( $_POST['depositante'] ) ) : '';
        $telefono    = isset( $_POST['telefono'] ) ? sanitize_text_field( wp_unslash( $_POST['telefono'] ) ) : '';
        $metodo      = isset( $_POST['metodo'] ) ? sanitize_text_field( wp_unslash( $_POST['metodo'] ) ) : '';
        $referencia  = isset( $_POST['referencia'] ) ? strtoupper( preg_replace( '/[^A-Z0-9]/i', '', sanitize_text_field( wp_unslash( $_POST['referencia'] ) ) ) ) : '';
        $monto_raw   = isset( $_POST['monto'] ) ? str_replace( ',', '.', sanitize_text_field( wp_unslash( $_POST['monto'] ) ) ) : '';
        $monto       = is_numeric( $monto_raw ) ? (float) $monto_raw : 0;

        $metodos_permitidos = array( 'Transferencia', 'Pago móvil', 'Depósito', 'Otro' );
        if ( '' === $depositante || mb_strlen( $depositante ) > 160 || '' === $telefono || mb_strlen( $telefono ) > 40 || ! in_array( $metodo, $metodos_permitidos, true ) ) {
            $this->fallo_publico( 'Completa correctamente todos los campos requeridos.' );
        }
        if ( 6 !== strlen( $referencia ) || ! preg_match( '/^[A-Z0-9]{6}$/', $referencia ) ) {
            $this->fallo_publico( 'La referencia bancaria debe contener exactamente 6 caracteres alfanuméricos.' );
        }
        if ( $monto <= 0 || $monto > 999999999.99 ) {
            $this->fallo_publico( 'El monto indicado no es válido.' );
        }
        $fecha_pago = isset($_POST['fecha_pago']) ? sanitize_text_field(wp_unslash($_POST['fecha_pago'])) : '';
        if ( ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_pago) || ! checkdate((int)substr($fecha_pago,5,2),(int)substr($fecha_pago,8,2),(int)substr($fecha_pago,0,4)) || $fecha_pago > current_time('Y-m-d') ) { $this->fallo_publico('Indica una fecha de pago válida, no futura.'); }
        $snapshot_bcv = class_exists('SVCP_Tasa_BCV') ? SVCP_Tasa_BCV::obtener($fecha_pago, true) : new WP_Error('sin_servicio','Servicio de tasas no disponible');
        if ( is_wp_error($snapshot_bcv) ) { $this->fallo_publico('No fue posible verificar la tasa BCV correspondiente a la fecha seleccionada. Intenta más tarde.'); }
        $ajustes_snapshot = class_exists('SVCP_Ciclo_Membresia') ? SVCP_Ciclo_Membresia::settings() : array();
        $precio_usd_snapshot = isset($ajustes_snapshot['precio_membresia_usd']) ? (float) $ajustes_snapshot['precio_membresia_usd'] : 0;
        $saldo_usd_previo = max(0, (float) get_post_meta($doctor, 'svcp_membresia_saldo_pendiente_usd', true));
        if ($saldo_usd_previo > 0) { $precio_usd_snapshot = $saldo_usd_previo; }
        $equivalente_snapshot = $precio_usd_snapshot * (float) $snapshot_bcv['tasa'];
        if ( empty($_FILES['comprobante']['name']) || ! isset($_FILES['comprobante']['tmp_name']) ) {
            $this->fallo_publico( 'Debes adjuntar la captura del comprobante de pago.' );
        }
        $archivo = $_FILES['comprobante'];
        if ( ! empty($archivo['size']) && (int)$archivo['size'] > 5 * MB_IN_BYTES ) {
            $this->fallo_publico( 'La captura no puede superar los 5 MB.' );
        }
        $tipo_archivo = wp_check_filetype_and_ext($archivo['tmp_name'], sanitize_file_name($archivo['name']));
        $mime_permitidos = array('image/jpeg','image/png','image/webp');
        if ( empty($tipo_archivo['type']) || ! in_array($tipo_archivo['type'], $mime_permitidos, true) ) {
            $this->fallo_publico( 'Formato no permitido. Adjunta una imagen JPG, PNG o WebP.' );
        }

        $duplicado = get_posts(
            array(
                'post_type' => self::CPT,
                'post_status' => 'private',
                'posts_per_page' => 1,
                'fields' => 'ids',
                'meta_query' => array(
                    array( 'key' => self::META_REFERENCIA, 'value' => $referencia, 'compare' => '=' ),
                    array( 'key' => self::META_ESTADO, 'value' => array( 'pendiente', 'aprobado' ), 'compare' => 'IN' ),
                ),
            )
        );
        if ( ! empty( $duplicado ) ) {
            $this->fallo_publico( 'Ya existe un reporte registrado con esa referencia. Si necesitas corregirlo, contacta a la sociedad.' );
        }

        $id = wp_insert_post(
            array(
                'post_type' => self::CPT,
                'post_status' => 'private',
                'post_title' => sprintf( 'Pago - %s - %s', get_the_title( $doctor ), current_time( 'Y-m-d' ) ),
                'post_author' => $uid,
            ),
            true
        );
        if ( is_wp_error( $id ) ) {
            $this->fallo_publico( 'No se pudo registrar el pago. Inténtalo nuevamente.' );
        }

        $archivo['type'] = $tipo_archivo['type'];
        $privado = $this->guardar_comprobante_privado( $archivo );
        if ( is_wp_error( $privado ) ) {
            if ( defined('WP_DEBUG') && WP_DEBUG ) { error_log( '[SVCP pagos] Almacenamiento privado: ' . $privado->get_error_code() . ' - ' . $privado->get_error_message() . ' | tmp=' . ( isset($archivo['tmp_name']) ? $archivo['tmp_name'] : 'n/a' ) . ' | upload_error=' . ( isset($archivo['error']) ? (int) $archivo['error'] : -1 ) ); }
            wp_delete_post( $id, true );
            $this->fallo_publico( 'No se pudo guardar el comprobante en almacenamiento privado. Contacta a la administración.' );
        }
        update_post_meta( $id, '_svcp_comprobante_path', $privado['path'] );
        update_post_meta( $id, '_svcp_comprobante_mime', $privado['mime'] );
        update_post_meta( $id, '_svcp_comprobante_nombre', $privado['name'] );

        $data = array(
            self::META_DOCTOR => $doctor,
            self::META_DEPOSITANTE => $depositante,
            self::META_REFERENCIA => $referencia,
            self::META_TELEFONO => $telefono,
            self::META_MONTO => number_format( $monto, 2, '.', '' ),
            self::META_FECHA_PAGO => $fecha_pago,
            self::META_TASA_BCV => $snapshot_bcv['tasa'],
            self::META_TASA_FECHA => $snapshot_bcv['fecha_vigencia'],
            self::META_PRECIO_USD => number_format($precio_usd_snapshot, 2, '.', ''),
            self::META_EQUIVALENTE_BS => number_format($equivalente_snapshot, 2, '.', ''),
            self::META_PAGO_USD => number_format( $monto / max( (float) $snapshot_bcv['tasa'], 0.00000001 ), 8, '.', '' ),
            '_svcp_deuda_previa_usd' => number_format($saldo_usd_previo, 8, '.', ''),
            self::META_METODO => $metodo,
            self::META_ESTADO => 'pendiente',
            self::META_FECHA => current_time( 'mysql' ),
        );
        foreach ( $data as $key => $value ) {
            update_post_meta( $id, $key, $value );
        }
        set_transient( $limite_key, 1, 5 * MINUTE_IN_SECONDS );
        if ( class_exists('SVCP_Auditoria') ) { SVCP_Auditoria::registrar( $doctor, 'pago_reportado', 'pago_' . $id, '', 'pendiente · Bs. ' . number_format($monto,2,'.','') . ' · ref. ' . $referencia, 'finanzas' ); }

        $admin = get_option( 'admin_email' );
        $doctor_title = get_the_title( $doctor );
        if ( is_email( $admin ) ) {
            $subject = '🧾 Nuevo reporte de pago pendiente · SVCP';
            $url_admin = esc_url(admin_url('edit.php?post_type=doctor&page=svcp-pagos'));
            $url_comprobante = $url_admin; // El enlace del correo lleva al panel; la descarga privada genera nonce en sesión administrativa.
            $nombre_seguro = esc_html($doctor_title);
            $body = '<!doctype html><html><body style="margin:0;background:#f2f5f8;font-family:Arial,Helvetica,sans-serif;color:#243746;padding:24px 10px"><div style="max-width:640px;margin:0 auto;background:#fff;border:1px solid #dfe7ee;border-radius:16px;overflow:hidden"><div style="background:#173f5f;padding:26px 30px;color:#fff"><div style="font-size:30px">🧾</div><div style="font-size:11px;letter-spacing:2px;color:#cde6eb;margin-top:8px">SOCIEDAD VENEZOLANA DE COLOPROCTOLOGÍA</div><h1 style="font-size:24px;margin:12px 0 4px">Nuevo pago por verificar</h1><p style="margin:0;color:#e2edf2">Se requiere revisión administrativa</p></div><div style="padding:26px 30px"><p style="font-size:16px;line-height:1.6">Se recibió un nuevo reporte de pago de <strong>'.$nombre_seguro.'</strong>.</p><div style="background:#f6f9fb;border:1px solid #e1e8ed;border-radius:12px;padding:18px"><p style="margin:0 0 10px"><span style="color:#667783">Depositante</span><br><strong>'.esc_html($depositante).'</strong></p><p style="margin:0 0 10px"><span style="color:#667783">Monto reportado</span><br><strong style="font-size:22px;color:#173f5f">Bs. '.esc_html($data[self::META_MONTO]).'</strong><br><span style="color:#667783">Equivalente recibido a la tasa de esa fecha</span><br><strong>USD '.esc_html($data[self::META_PAGO_USD]).'</strong></p><p style="margin:0 0 10px"><span style="color:#667783">Fecha del pago / tasa BCV</span><br><strong>'.esc_html($fecha_pago).' · Bs. '.esc_html($snapshot_bcv['tasa']).' por USD (vigencia '.esc_html($snapshot_bcv['fecha_vigencia']).')</strong></p><p style="margin:0 0 10px"><span style="color:#667783">Precio institucional</span><br><strong>USD '.esc_html(number_format($precio_usd_snapshot,2,'.','')).' · Equivalente Bs. '.esc_html(number_format($equivalente_snapshot,2,'.','')).'</strong></p><p style="margin:0 0 10px"><span style="color:#667783">Referencia</span><br><strong>'.esc_html($referencia).'</strong></p><p style="margin:0"><span style="color:#667783">Método</span><br><strong>'.esc_html($metodo).'</strong></p></div><p style="text-align:center;margin:26px 0 12px"><a href="'.$url_comprobante.'" style="display:inline-block;background:#e5232e;color:#fff;text-decoration:none;font-weight:bold;padding:12px 20px;border-radius:7px">Abrir reporte y comprobante privado</a></p><p style="font-size:12px;color:#667783;line-height:1.6;margin-top:24px">Verifica la información y el comprobante antes de aprobar. El envío del formulario no activa la membresía.</p></div><div style="background:#f7f9fa;padding:16px 30px;color:#667783;font-size:12px">Notificación automática del Directorio Médico SVCP · NICPRODEV</div></div></body></html>';
            $headers = array( 'Content-Type: text/html; charset=UTF-8' );
            wp_mail( $admin, $subject, $body, $headers );
        }

        $uid_doctor = absint( get_post_meta( $doctor, 'svcp_usuario_id', true ) );
        $cuenta_doctor = $uid_doctor ? get_user_by( 'id', $uid_doctor ) : false;
        if ( $cuenta_doctor && $cuenta_doctor->user_email && class_exists( 'SVCP_Correos' ) ) {
            SVCP_Correos::enviar( $cuenta_doctor->user_email, 'pago_pendiente', array( 'nombre' => $cuenta_doctor->display_name, 'referencia' => $referencia, 'url_panel' => home_url( '/' ) ) );
        }

        $url = wp_get_referer() ?: home_url( '/' );
        wp_safe_redirect( add_query_arg( 'svcp_pago_enviado', '1', remove_query_arg( array( 'svcp_pago_error' ), $url ) ) );
        exit;
    }

    private function fallo_publico( $mensaje ) {
        $url = wp_get_referer() ?: home_url( '/' );
        wp_safe_redirect( add_query_arg( 'svcp_pago_error', rawurlencode( $mensaje ), $url ) );
        exit;
    }

    public function admin_page() {
        if ( current_user_can('manage_options') ) { $this->migrar_comprobantes_legacy(); }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No tienes permisos suficientes para gestionar pagos.', 'svcp-directorio' ) );
        }

        $estado_filtro = isset( $_GET['estado'] ) ? sanitize_key( wp_unslash( $_GET['estado'] ) ) : 'pendiente';
        $permitidos = array( 'pendiente', 'aprobado', 'rechazado', 'todos' );
        if ( ! in_array( $estado_filtro, $permitidos, true ) ) {
            $estado_filtro = 'pendiente';
        }
        $buscar = isset( $_GET['buscar'] ) ? sanitize_text_field( wp_unslash( $_GET['buscar'] ) ) : '';
        $pagina = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
        $por_pagina = 25;

        $args = array(
            'post_type' => self::CPT,
            'post_status' => 'private',
            'posts_per_page' => $por_pagina,
            'paged' => $pagina,
            'orderby' => 'date',
            'order' => 'DESC',
        );
        $meta_query = array();
        if ( 'todos' !== $estado_filtro ) {
            $meta_query[] = array( 'key' => self::META_ESTADO, 'value' => $estado_filtro );
        }
        if ( $meta_query ) {
            $args['meta_query'] = $meta_query;
        }
        if ( $buscar ) {
            $args['s'] = $buscar;
        }
        $query = new WP_Query( $args );
        $stats = $this->estadisticas();

        echo '<div class="wrap svcp-pagos-admin">';
        echo '<div class="svcp-pagos-header"><div><h1>Pagos de membresía</h1><p>Verifica manualmente los reportes antes de activar o renovar una membresía.</p></div><a class="button" href="' . esc_url( admin_url( 'edit.php?post_type=doctor' ) ) . '">Volver a doctores</a></div>';
        echo '<div class="svcp-pagos-alerta">Los reportes se verifican manualmente. Al aprobar, el médico queda solvente hasta el vencimiento común del ciclo institucional.</div>';
        echo '<div class="svcp-pagos-stats"><div class="svcp-pago-stat"><small>Pendientes</small><strong>' . esc_html( number_format_i18n( $stats['pendiente'] ) ) . '</strong></div><div class="svcp-pago-stat"><small>Aprobados</small><strong>' . esc_html( number_format_i18n( $stats['aprobado'] ) ) . '</strong></div><div class="svcp-pago-stat"><small>Rechazados</small><strong>' . esc_html( number_format_i18n( $stats['rechazado'] ) ) . '</strong></div></div>';
        $this->render_notice();
        $finanzas = $this->resumen_financiero();
        echo '<div class="svcp-pagos-stats"><div class="svcp-pago-stat"><small>Recaudado aprobado (Bs.)</small><strong>Bs. ' . esc_html(number_format_i18n($finanzas['ves'],2)) . '</strong></div><div class="svcp-pago-stat"><small>Equivalente registrado (USD)</small><strong>USD ' . esc_html(number_format_i18n($finanzas['usd'],4)) . '</strong></div><div class="svcp-pago-stat"><small>Pagos aprobados este mes</small><strong>' . esc_html(number_format_i18n($finanzas['mes'])) . '</strong></div></div>';
        $this->render_historial_tasas();

        echo '<form class="svcp-pagos-filtros" method="get"><input type="hidden" name="post_type" value="doctor"><input type="hidden" name="page" value="svcp-pagos"><input type="search" name="buscar" value="' . esc_attr( $buscar ) . '" placeholder="Buscar médico o referencia…" aria-label="Buscar pagos"><select name="estado"><option value="pendiente" ' . selected( $estado_filtro, 'pendiente', false ) . '>Pendientes</option><option value="aprobado" ' . selected( $estado_filtro, 'aprobado', false ) . '>Aprobados</option><option value="rechazado" ' . selected( $estado_filtro, 'rechazado', false ) . '>Rechazados</option><option value="todos" ' . selected( $estado_filtro, 'todos', false ) . '>Todos</option></select><button class="button button-primary">Filtrar</button><a class="button" href="' . esc_url( admin_url( 'edit.php?post_type=doctor&page=svcp-pagos' ) ) . '">Limpiar</a></form>';

        echo '<p><a class="button" href="' . esc_url( wp_nonce_url( admin_url('admin-post.php?action=svcp_exportar_pagos'), 'svcp_exportar_pagos' ) ) . '">Exportar reporte CSV</a></p>';
        echo '<div class="svcp-pagos-tabla"><table class="widefat striped"><thead><tr><th>Fecha</th><th>Médico</th><th>Operación</th><th>Comprobante</th><th>Monto</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>';
        if ( ! $query->have_posts() ) {
            echo '<tr><td colspan="7" class="svcp-pago-vacio">No hay reportes que coincidan con los filtros.</td></tr>';
        } else {
            while ( $query->have_posts() ) {
                $query->the_post();
                $id = get_the_ID();
                $doctor_id = absint( get_post_meta( $id, self::META_DOCTOR, true ) );
                $estado = get_post_meta( $id, self::META_ESTADO, true ) ?: 'pendiente';
                $fecha = get_post_meta( $id, self::META_FECHA, true );
                $depositante = get_post_meta( $id, self::META_DEPOSITANTE, true );
                $referencia = get_post_meta( $id, self::META_REFERENCIA, true );
                $telefono = get_post_meta( $id, self::META_TELEFONO, true );
                $monto = get_post_meta( $id, self::META_MONTO, true );
                $metodo = get_post_meta( $id, self::META_METODO, true );
                echo '<tr><td>' . esc_html( $fecha ? mysql2date( 'd/m/Y H:i', $fecha ) : get_the_date( 'd/m/Y H:i' ) ) . '</td>';
                echo '<td><strong>' . esc_html( get_the_title( $doctor_id ) ?: 'Doctor #' . $doctor_id ) . '</strong><span class="svcp-pago-dato-secundario">' . esc_html( $depositante ) . '</span></td>';
                echo '<td><strong>' . esc_html( $referencia ) . '</strong><span class="svcp-pago-dato-secundario">' . esc_html( $metodo . ' · ' . $telefono ) . '</span></td>';
                $comprobante_path = get_post_meta($id, '_svcp_comprobante_path', true);
                $comprobante_url = $comprobante_path ? wp_nonce_url( admin_url('admin-post.php?action=svcp_descargar_comprobante&pago_id=' . absint($id)), 'svcp_descargar_comprobante_' . absint($id) ) : '';
                echo '<td>' . ( $comprobante_url ? '<a class="button button-small" href="' . esc_url($comprobante_url) . '">Ver captura privada</a>' : '<span class="svcp-pago-dato-secundario">No adjunta</span>' ) . '</td>';
                $tasa_guardada = get_post_meta($id, self::META_TASA_BCV, true); $fecha_tasa = get_post_meta($id, self::META_TASA_FECHA, true); $fecha_operacion = get_post_meta($id, self::META_FECHA_PAGO, true); $usd_guardado = get_post_meta($id, self::META_PRECIO_USD, true);
                echo '<td><strong>Bs. ' . esc_html( $monto ) . '</strong><br><small>Pagó: USD '.esc_html(get_post_meta($id,self::META_PAGO_USD,true) ?: ( $tasa_guardada ? number_format((float)$monto/(float)$tasa_guardada,4,'.','') : '—' )).'<br>Pago: '.esc_html($fecha_operacion ?: '—').'<br>BCV: '.esc_html($tasa_guardada ?: '—').' · '.esc_html($fecha_tasa ?: '—').'<br>Precio: USD '.esc_html($usd_guardado ?: '—').'<br>Equivalente requerido: Bs. '.esc_html(get_post_meta($id, self::META_EQUIVALENTE_BS, true) ?: '—');
                $saldo_guardado = get_post_meta($id,self::META_SALDO_BS,true);
                if ( $saldo_guardado !== '' && (float)$saldo_guardado > 0 ) echo '<br><strong style="color:#b45309">Saldo pendiente: Bs. '.esc_html($saldo_guardado).' · USD '.esc_html(get_post_meta($id,self::META_SALDO_USD,true)).'</strong>';
                echo '</small></td>';
                echo '<td><span class="svcp-pago-estado svcp-pago-estado-' . esc_attr( $estado ) . '">' . esc_html( ucfirst( $estado ) ) . '</span>';
                if ( 'rechazado' === $estado ) {
                    echo '<span class="svcp-pago-dato-secundario">' . esc_html( get_post_meta( $id, self::META_MOTIVO, true ) ) . '</span>';
                }
                echo '</td><td>';
                if ( 'pendiente' === $estado ) {
                    echo '<div class="svcp-pago-acciones">';
                    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="svcp-pago-modal-form"><input type="hidden" name="action" value="svcp_resolver_pago"><input type="hidden" name="pago_id" value="' . esc_attr( $id ) . '">';
                    wp_nonce_field( 'svcp_resolver_' . $id, 'nonce' );
                    $requerido_bs = (float) get_post_meta($id,self::META_EQUIVALENTE_BS,true);
                    $pagado_bs = (float) $monto;
                    $deuda_doctor_actual = max(0, (float) get_post_meta($doctor_id, 'svcp_membresia_saldo_pendiente_usd', true));
                    $sugerido_faltante = $deuda_doctor_actual > 0 ? 0 : max(0, $requerido_bs - $pagado_bs);
                    echo '<label class="svcp-pago-faltante" style="display:block;margin:7px 0;font-size:12px">Monto que falta (Bs.)<input type="number" name="saldo_pendiente_bs" min="0" max="999999999.99" step="0.01" value="'.esc_attr(number_format($sugerido_faltante,2,'.','')).'" style="display:block;width:100%;max-width:190px"><small>'.( $deuda_doctor_actual > 0 ? 'Este reporte se aplicará primero al saldo pendiente anterior, expresado en USD.' : 'Calculado automáticamente; puedes ajustarlo. Cero si no queda saldo.' ).'</small></label>';
                    echo '<button type="submit" class="button button-primary" name="decision" value="aprobar">Aprobar y registrar saldo</button></form>';
                    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="svcp-pago-rechazo"><input type="hidden" name="action" value="svcp_resolver_pago"><input type="hidden" name="pago_id" value="' . esc_attr( $id ) . '">';
                    wp_nonce_field( 'svcp_resolver_' . $id, 'nonce' );
                    echo '<input type="text" name="motivo" required maxlength="300" placeholder="Motivo del rechazo" aria-label="Motivo del rechazo"><button type="submit" class="button" name="decision" value="rechazar">Rechazar</button></form></div>';
                } else {
                    echo '<span class="svcp-pago-dato-secundario">Resuelto ' . esc_html( get_post_meta( $id, self::META_RESUELTO_EN, true ) ? mysql2date( 'd/m/Y H:i', get_post_meta( $id, self::META_RESUELTO_EN, true ) ) : '—' ) . '</span>';
                }
                echo '</td></tr>';
            }
            wp_reset_postdata();
        }
        echo '</tbody></table>';
        $pagination = paginate_links( array( 'base' => add_query_arg( 'paged', '%#%' ), 'format' => '', 'current' => $pagina, 'total' => max( 1, (int) $query->max_num_pages ), 'type' => 'list', 'add_args' => array_filter( array( 'post_type' => 'doctor', 'page' => 'svcp-pagos', 'estado' => $estado_filtro, 'buscar' => $buscar ) ) ) );
        if ( $pagination ) {
            echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( $pagination ) . '</div></div>';
        }
        echo '</div></div>';
    }

    /** Detecta instalaciones locales para permitir un fallback protegido solo en desarrollo. */
    private function es_entorno_local() {
        $host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
        return (bool) ( preg_match( '/(^localhost$|\.localhost$|\.local$|\.test$|^127\.|^192\.168\.)/', $host ) );
    }

    /** Prepara reglas de bloqueo para directorios privados alojados bajo el árbol web. */
    private function proteger_directorio_privado( $dir ) {
        if ( ! file_exists( $dir . '/index.php' ) ) {
            @file_put_contents( $dir . '/index.php', "<?php http_response_code(404); exit;" );
        }
        if ( ! file_exists( $dir . '/.htaccess' ) ) {
            @file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" );
        }
        if ( ! file_exists( $dir . '/web.config' ) ) {
            @file_put_contents( $dir . '/web.config', '<?xml version="1.0"?><configuration><system.webServer><security><authorization><remove users="*" roles="" verbs=""/><add accessType="Deny" users="*"/></authorization></security></system.webServer></configuration>' );
        }
    }

    /** Resuelve un almacenamiento privado escribible. En producción exige estar fuera del document root. */
    private function directorio_privado() {
        $candidatos = array();
        if ( defined( 'SVCP_PRIVATE_UPLOAD_DIR' ) && SVCP_PRIVATE_UPLOAD_DIR ) {
            $candidatos[] = SVCP_PRIVATE_UPLOAD_DIR;
        }
        $candidatos[] = dirname( untrailingslashit( ABSPATH ) ) . '/svcp-private-comprobantes';
        if ( function_exists( 'sys_get_temp_dir' ) ) {
            $candidatos[] = rtrim( sys_get_temp_dir(), '/\\' ) . '/svcp-private-comprobantes-' . substr( md5( home_url() ), 0, 10 );
        }
        $docroot = isset( $_SERVER['DOCUMENT_ROOT'] ) ? realpath( wp_normalize_path( $_SERVER['DOCUMENT_ROOT'] ) ) : false;
        $abspath_real = realpath( ABSPATH );
        foreach ( array_unique( $candidatos ) as $candidato ) {
            $base = untrailingslashit( wp_normalize_path( $candidato ) );
            // path_is_absolute() ha tenido diferencias entre versiones/plataformas; validar Unix y Windows.
            if ( ! ( path_is_absolute( $base ) || preg_match( '/^[A-Za-z]:\//', $base ) ) ) { continue; }
            if ( ! file_exists( $base ) && ! wp_mkdir_p( $base ) ) { continue; }
            $real = realpath( $base );
            if ( ! $real || ! is_dir( $real ) || ! is_writable( $real ) ) { continue; }
            $real = untrailingslashit( wp_normalize_path( $real ) );
            $dentro_docroot = $docroot && ( $real === untrailingslashit( wp_normalize_path( $docroot ) ) || strpos( $real, trailingslashit( untrailingslashit( wp_normalize_path( $docroot ) ) ) ) === 0 );
            $dentro_wp = $abspath_real && ( $real === untrailingslashit( wp_normalize_path( $abspath_real ) ) || strpos( $real, trailingslashit( untrailingslashit( wp_normalize_path( $abspath_real ) ) ) ) === 0 );
            if ( $dentro_docroot || $dentro_wp ) { continue; }
            $this->proteger_directorio_privado( $real );
            return $real;
        }

        // En sitios locales (.local/.test/localhost) algunos PHP locales restringen temp y carpetas hermanas.
        // Se permite un fallback dentro de uploads SOLO en desarrollo, protegido con reglas del servidor.
        if ( $this->es_entorno_local() ) {
            $uploads = wp_upload_dir();
            if ( empty( $uploads['error'] ) && ! empty( $uploads['basedir'] ) ) {
                $local = trailingslashit( $uploads['basedir'] ) . 'svcp-private-comprobantes';
                if ( ! file_exists( $local ) ) { wp_mkdir_p( $local ); }
                $real = realpath( $local );
                if ( $real && is_dir( $real ) && is_writable( $real ) ) {
                    $this->proteger_directorio_privado( $real );
                    return wp_normalize_path( $real );
                }
            }
        }
        return '';
    }

    /** Aviso diario al administrador cuando existen pagos por revisar (máximo uno por día). */
    public function notificar_pagos_pendientes() {
        $cantidad=$this->contar_pendientes(); if($cantidad<1) return;
        $hoy=current_time('Y-m-d'); if(get_option('svcp_pagos_pendientes_aviso_fecha')===$hoy) return;
        $destino=get_option('admin_email'); if(!is_email($destino)) return;
        $url=admin_url('edit.php?post_type=doctor&page=svcp-pagos');
        $asunto='SVCP · Hay ' . $cantidad . ' reporte(s) de pago pendiente(s)';
        $body='<div style="font-family:Arial,sans-serif;max-width:600px;margin:auto;color:#173f5f"><div style="background:#0b5a88;color:#fff;padding:24px"><strong>SVCP · DIRECTORIO MÉDICO</strong><h2>Pagos pendientes de revisión</h2></div><div style="padding:24px;background:#f5f8fa"><p>Actualmente hay <strong>'.absint($cantidad).'</strong> reporte(s) que esperan verificación administrativa.</p><p><a href="'.esc_url($url).'" style="background:#0b5a88;color:#fff;padding:12px 18px;text-decoration:none;border-radius:6px">Revisar pagos</a></p><small>Recordatorio automático diario. No se aprueban pagos de forma automática.</small></div></div>';
        $headers=array('Content-Type: text/html; charset=UTF-8');
        if(wp_mail($destino,$asunto,$body,$headers)) update_option('svcp_pagos_pendientes_aviso_fecha',$hoy,false);
    }

    /** Migra capturas de versiones anteriores desde la biblioteca pública al directorio privado. */
    private function migrar_comprobantes_legacy() {
        $ids=get_posts(array('post_type'=>self::CPT,'post_status'=>'private','posts_per_page'=>50,'fields'=>'ids','meta_query'=>array(array('key'=>'_svcp_comprobante_id','compare'=>'EXISTS')),'no_found_rows'=>true));
        foreach($ids as $id){
            $att=absint(get_post_meta($id,'_svcp_comprobante_id',true)); $src=$att?get_attached_file($att):'';
            if(!$src||!is_file($src)) continue;
            $mime=get_post_mime_type($att); $exts=array('image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'); if(!isset($exts[$mime])) continue;
            $base=$this->directorio_privado(); if(!$base) continue; if(!file_exists($base)) wp_mkdir_p($base); if(!is_dir($base)||!wp_is_writable($base)) continue;
            if(!file_exists($base.'/index.php')) file_put_contents($base.'/index.php',"<?php http_response_code(404); exit;"); if(!file_exists($base.'/.htaccess')) file_put_contents($base.'/.htaccess',"Deny from all\nRequire all denied\n");
            $name=wp_unique_filename($base,'svcp-migrado-'.wp_generate_password(16,false,false).'.'.$exts[$mime]); $dest=trailingslashit($base).$name;
            if(@copy($src,$dest)){@chmod($dest,0600);update_post_meta($id,'_svcp_comprobante_path',$dest);update_post_meta($id,'_svcp_comprobante_mime',$mime);update_post_meta($id,'_svcp_comprobante_nombre',basename($src));delete_post_meta($id,'_svcp_comprobante_id');wp_delete_attachment($att,true);}
        }
    }

    /** Almacena comprobantes fuera del directorio público de WordPress. */
    private function guardar_comprobante_privado( $archivo ) {
        if ( empty( $archivo['tmp_name'] ) || ! is_file( $archivo['tmp_name'] ) || ( ! is_uploaded_file( $archivo['tmp_name'] ) && ! $this->es_entorno_local() ) ) return new WP_Error('archivo_invalido','PHP no reconoce el archivo como una carga HTTP válida.');
        $base = $this->directorio_privado();
        if ( ! $base ) return new WP_Error('almacenamiento_no_privado','No se encontró una carpeta privada escribible. Define SVCP_PRIVATE_UPLOAD_DIR con una ruta absoluta.');
        if ( ! file_exists( $base ) && ! wp_mkdir_p( $base ) ) return new WP_Error('directorio_privado','No se pudo crear el directorio privado.');
        if ( ! is_dir( $base ) || ! is_writable( $base ) ) return new WP_Error('directorio_no_escribible','El almacenamiento privado no tiene permisos de escritura.');
        $mime = isset($archivo['type']) ? sanitize_mime_type($archivo['type']) : '';
        $exts = array('image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp');
        if ( ! isset($exts[$mime]) ) return new WP_Error('mime_invalido','Formato no permitido.');
        $name = wp_unique_filename( $base, 'svcp-' . wp_generate_password(20,false,false) . '.' . $exts[$mime] );
        $dest = trailingslashit($base) . $name;
        $movido = @move_uploaded_file( $archivo['tmp_name'], $dest );
        // Fallback únicamente en entornos locales: algunos stacks Windows/Local pueden entregar
        // temporales que no pasan is_uploaded_file() aunque WordPress ya validó el MIME.
        if ( ! $movido && $this->es_entorno_local() && is_file( $archivo['tmp_name'] ) ) {
            $movido = @copy( $archivo['tmp_name'], $dest );
        }
        if ( ! $movido ) {
            $error = error_get_last();
            return new WP_Error('move_failed','PHP no pudo mover/copiar el temporal. ' . ( $error && ! empty($error['message']) ? sanitize_text_field($error['message']) : 'Revisa permisos y open_basedir.' ) );
        }
        @chmod( $dest, 0600 );
        return array('path'=>$dest,'mime'=>$mime,'name'=>sanitize_file_name($archivo['name']));
    }

    public function descargar_comprobante() {
        if ( ! current_user_can('manage_options') ) wp_die('No autorizado.', '', array('response'=>403));
        $id = isset($_GET['pago_id']) ? absint($_GET['pago_id']) : 0;
        check_admin_referer('svcp_descargar_comprobante_' . $id);
        if ( self::CPT !== get_post_type($id) ) wp_die('Reporte no válido.', '', array('response'=>404));
        $path = get_post_meta($id,'_svcp_comprobante_path',true);
        $mime = get_post_meta($id,'_svcp_comprobante_mime',true);
        $base = realpath($this->directorio_privado());
        $real = $path ? realpath($path) : false;
        if ( ! $real || ! $base || strpos($real, $base . DIRECTORY_SEPARATOR) !== 0 || ! is_file($real) ) wp_die('Comprobante no disponible.', '', array('response'=>404));
        if ( ! in_array($mime,array('image/jpeg','image/png','image/webp'),true) ) wp_die('Tipo de archivo no permitido.', '', array('response'=>403));
        nocache_headers();
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($real));
        header('Content-Disposition: inline; filename="comprobante-' . $id . '.' . pathinfo($real,PATHINFO_EXTENSION) . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($real); exit;
    }

    public function eliminar_comprobante_privado( $post_id ) {
        if ( self::CPT !== get_post_type($post_id) ) return;
        $path = get_post_meta($post_id,'_svcp_comprobante_path',true);
        $base = realpath($this->directorio_privado());
        $real = $path ? realpath($path) : false;
        if ( $real && $base && strpos($real,$base . DIRECTORY_SEPARATOR) === 0 && is_file($real) ) @unlink($real);
    }

    /** Descarga un recibo PDF de un pago aprobado, solo para el médico propietario. */
    public function descargar_recibo_pdf() {
        if ( ! is_user_logged_in() ) { wp_die('Debes iniciar sesión.', '', array('response'=>403)); }
        $id = isset($_GET['pago_id']) ? absint($_GET['pago_id']) : 0;
        if ( ! $id || ! wp_verify_nonce( sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? '')), 'svcp_recibo_' . $id ) ) { wp_die('Enlace no válido.', '', array('response'=>403)); }
        $doctor = absint(get_post_meta($id,self::META_DOCTOR,true));
        $uid = absint(get_post_meta($doctor,'svcp_usuario_id',true));
        if ( ! $uid ) { $uid = absint(get_post_field('post_author',$doctor)); }
        if ( $uid !== get_current_user_id() || 'aprobado' !== get_post_meta($id,self::META_ESTADO,true) ) { wp_die('No tienes acceso a este comprobante.', '', array('response'=>403)); }
        $monto = (float)get_post_meta($id,self::META_MONTO,true);
        $usd = (float)get_post_meta($id,self::META_PAGO_USD,true);
        $tasa = (float)get_post_meta($id,self::META_TASA_BCV,true);
        $saldo_usd = (float)get_post_meta($id,self::META_SALDO_USD,true);
        $saldo_bs = (float)get_post_meta($id,self::META_SALDO_BS,true);
        $rows = array(
            'SOCIEDAD VENEZOLANA DE COLOPROCTOLOGIA', 'COMPROBANTE DE PAGO DE MEMBRESIA',
            'Medico: ' . get_the_title($doctor), 'Comprobante No.: SVCP-' . $id,
            'Fecha de operacion: ' . get_post_meta($id,self::META_FECHA_PAGO,true),
            'Fecha de aprobacion: ' . get_post_meta($id,self::META_RESUELTO_EN,true),
            'Referencia bancaria: ' . get_post_meta($id,self::META_REFERENCIA,true),
            'Metodo: ' . get_post_meta($id,self::META_METODO,true),
            'Monto recibido: Bs. ' . number_format($monto,2,',','.'),
            'Equivalente registrado: USD ' . number_format($usd,4,',','.'),
            'Tasa BCV aplicada: Bs. ' . number_format($tasa,4,',','.') . ' por USD',
            'Vigencia de tasa: ' . get_post_meta($id,self::META_TASA_FECHA,true),
            'Saldo pendiente: USD ' . number_format($saldo_usd,4,',','.') . ' (Bs. ' . number_format($saldo_bs,2,',','.') . ')',
            'Estado: APROBADO', 'Este documento acredita el registro y aprobacion del pago indicado.',
            'No sustituye una factura fiscal.'
        );
        $esc = function( $value ) { return $this->pdf_escape( $value ); };
        $content = "q\n";
        // Encabezado institucional y acento cromático SVCP.
        $content .= "0.09 0.22 0.34 rg 0 650 612 192 re f\n";
        $content .= "0.89 0.14 0.18 rg 0 644 612 6 re f\n";
        // El color de relleno del PDF también afecta al texto (rg); definirlo explícitamente.
        $content .= "1 1 1 rg\n";
        $content .= "BT /F2 10 Tf 48 800 Td (SOCIEDAD VENEZOLANA DE COLOPROCTOLOGIA) Tj ET\n";
        $content .= "BT /F2 22 Tf 48 758 Td (Comprobante de pago) Tj ET\n";
        $content .= "BT /F1 10 Tf 48 735 Td (Membresia institucional - constancia de pago aprobado) Tj ET\n";
        $content .= "0.10 0.55 0.43 rg 454 760 110 28 re f\n";
        $content .= "1 1 1 rg BT /F2 10 Tf 477 770 Td (APROBADO) Tj ET\n";
        // Identificador y datos del afiliado.
        $content .= "0.95 0.97 0.98 rg 42 560 528 58 re f\n";
        $content .= "0.82 0.87 0.90 RG 42 560 528 58 re S\n";
        $content .= "0.35 0.42 0.48 rg BT /F2 8 Tf 58 598 Td (MEDICO AFILIADO) Tj ET\n";
        $content .= "0.09 0.16 0.22 rg BT /F2 13 Tf 58 578 Td (" . $esc( get_the_title($doctor) ) . ") Tj ET\n";
        $content .= "0.35 0.42 0.48 rg BT /F1 9 Tf 400 598 Td (COMPROBANTE No.) Tj ET\n";
        $content .= "0.09 0.16 0.22 rg BT /F2 12 Tf 400 578 Td (SVCP-" . $id . ") Tj ET\n";
        // Bloques de importes.
        $content .= "0.93 0.96 0.98 rg 42 475 252 62 re f\n";
        $content .= "0.91 0.97 0.95 rg 318 475 252 62 re f\n";
        $content .= "0.35 0.42 0.48 rg BT /F1 9 Tf 58 515 Td (MONTO RECIBIDO) Tj ET\n";
        $content .= "0.09 0.16 0.22 rg BT /F2 18 Tf 58 488 Td (Bs. " . $esc(number_format($monto,2,',','.')) . ") Tj ET\n";
        $content .= "0.35 0.42 0.48 rg BT /F1 9 Tf 334 515 Td (EQUIVALENTE REGISTRADO) Tj ET\n";
        $content .= "0.09 0.16 0.22 rg BT /F2 18 Tf 334 488 Td (USD " . $esc(number_format($usd,4,',','.')) . ") Tj ET\n";
        // Detalle de la operacion en filas.
        $details = array(
            array('Fecha de operacion', get_post_meta($id,self::META_FECHA_PAGO,true)),
            array('Fecha de aprobacion', get_post_meta($id,self::META_RESUELTO_EN,true)),
            array('Referencia bancaria', get_post_meta($id,self::META_REFERENCIA,true)),
            array('Metodo de pago', get_post_meta($id,self::META_METODO,true)),
            array('Tasa BCV aplicada', 'Bs. ' . number_format($tasa,4,',','.') . ' por USD'),
            array('Vigencia de tasa', get_post_meta($id,self::META_TASA_FECHA,true)),
            array('Saldo pendiente', 'USD ' . number_format($saldo_usd,4,',','.') . ' / Bs. ' . number_format($saldo_bs,2,',','.'))
        );
        $y = 432;
        foreach ( $details as $index => $detail ) {
            if ( 0 === $index % 2 ) { $content .= "0.97 0.98 0.99 rg 42 " . ($y-9) . " 528 29 re f\n"; }
            $content .= "0.35 0.42 0.48 rg BT /F1 9 Tf 58 " . $y . " Td (" . $esc($detail[0]) . ") Tj ET\n";
            $content .= "0.09 0.16 0.22 rg BT /F2 9 Tf 250 " . $y . " Td (" . $esc($detail[1]) . ") Tj ET\n";
            $y -= 34;
        }
        // Pie y nota legal.
        $content .= "0.82 0.87 0.90 RG 42 165 m 570 165 l S\n";
        $content .= "0.09 0.16 0.22 rg BT /F2 9 Tf 42 142 Td (Constancia interna de registro y aprobacion de pago) Tj ET\n";
        $content .= "0.35 0.42 0.48 rg BT /F1 8 Tf 42 125 Td (Este documento no sustituye una factura fiscal.) Tj ET\n";
        $content .= "0.35 0.42 0.48 rg BT /F1 8 Tf 42 80 Td (Sociedad Venezolana de Coloproctologia | Portal del Medico SVCP) Tj ET\n";
        $content .= "0.35 0.42 0.48 rg BT /F1 8 Tf 42 64 Td (Documento generado automaticamente. Conserve este comprobante para sus registros.) Tj ET\n";
        $content .= "Q";
        $stream = $content;
        $objects = array();
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
        $objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 842] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';
        $objects[] = '<< /Length ' . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";
        $pdf = "%PDF-1.4\n"; $offsets = array(0);
        foreach ($objects as $i=>$obj) { $offsets[] = strlen($pdf); $pdf .= ($i+1) . " 0 obj\n" . $obj . "\nendobj\n"; }
        $xref = strlen($pdf); $total = count($objects) + 1; $pdf .= "xref\n0 " . $total . "\n0000000000 65535 f \n";
        for ($i=1;$i<$total;$i++) { $pdf .= sprintf('%010d 00000 n ', $offsets[$i]) . "\n"; }
        $pdf .= "trailer\n<< /Size " . $total . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
        nocache_headers(); header('Content-Type: application/pdf'); header('Content-Disposition: attachment; filename="comprobante-svcp-' . $id . '.pdf"'); header('Content-Length: ' . strlen($pdf)); echo $pdf; exit;
    }

    private function pdf_escape($text) {
        $text = remove_accents((string)$text);
        $text = preg_replace('/[^\\x20-\\x7E]/', '', $text);
        return str_replace(array('\\','(',')'), array('\\\\','\\(','\\)'), $text);
    }

    public function exportar_csv() {
        if ( ! current_user_can('manage_options') ) wp_die('No autorizado.');
        check_admin_referer('svcp_exportar_pagos');
        $ids = get_posts(array('post_type'=>self::CPT,'post_status'=>'private','posts_per_page'=>-1,'fields'=>'ids','orderby'=>'date','order'=>'DESC'));
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=svcp-reporte-pagos-' . current_time('Y-m-d') . '.csv');
        $out = fopen('php://output','w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out,array('ID','Médico','Fecha reporte','Fecha pago','Depositante','Referencia','Método','Monto Bs','Precio USD','Tasa BCV','Vigencia tasa','Equivalente Bs','Pagado equivalente USD','Saldo pendiente Bs','Saldo pendiente USD','Estado','Resuelto por','Fecha resolución','Motivo'));
        foreach($ids as $id) {
            $doctor=absint(get_post_meta($id,self::META_DOCTOR,true)); $resolver=absint(get_post_meta($id,self::META_RESUELTO_POR,true)); $u=$resolver?get_user_by('id',$resolver):false;
            fputcsv($out,array($id,get_the_title($doctor),get_post_meta($id,self::META_FECHA,true),get_post_meta($id,self::META_FECHA_PAGO,true),get_post_meta($id,self::META_DEPOSITANTE,true),get_post_meta($id,self::META_REFERENCIA,true),get_post_meta($id,self::META_METODO,true),get_post_meta($id,self::META_MONTO,true),get_post_meta($id,self::META_PRECIO_USD,true),get_post_meta($id,self::META_TASA_BCV,true),get_post_meta($id,self::META_TASA_FECHA,true),get_post_meta($id,self::META_EQUIVALENTE_BS,true),get_post_meta($id,self::META_PAGO_USD,true),get_post_meta($id,self::META_SALDO_BS,true),get_post_meta($id,self::META_SALDO_USD,true),get_post_meta($id,self::META_ESTADO,true),$u?$u->display_name:'',get_post_meta($id,self::META_RESUELTO_EN,true),get_post_meta($id,self::META_MOTIVO,true)));
        }
        fclose($out); exit;
    }

    private function resumen_financiero() {
        $r=array('ves'=>0.0,'usd'=>0.0,'mes'=>0); $ids=get_posts(array('post_type'=>self::CPT,'post_status'=>'private','posts_per_page'=>-1,'fields'=>'ids','no_found_rows'=>true)); $mes=current_time('Y-m');
        foreach($ids as $id) if('aprobado'===get_post_meta($id,self::META_ESTADO,true)) { $r['ves']+=(float)get_post_meta($id,self::META_MONTO,true); $r['usd']+=(float)get_post_meta($id,self::META_PAGO_USD,true); if(0===strpos((string)get_post_meta($id,self::META_RESUELTO_EN,true),$mes)) $r['mes']++; }
        return $r;
    }

    private function render_historial_tasas() {
        $ids=get_posts(array('post_type'=>self::CPT,'post_status'=>'private','posts_per_page'=>100,'fields'=>'ids','orderby'=>'date','order'=>'DESC','no_found_rows'=>true)); $seen=array();
        foreach($ids as $id){$f=get_post_meta($id,self::META_FECHA_PAGO,true);$t=get_post_meta($id,self::META_TASA_BCV,true);$v=get_post_meta($id,self::META_TASA_FECHA,true);if($f&&$t)$seen[$f]=array($t,$v);}
        if(!$seen)return; echo '<h2>Historial de tasas aplicadas a pagos</h2><div class="svcp-pagos-tabla"><table class="widefat striped"><thead><tr><th>Fecha declarada de pago</th><th>Tasa guardada (Bs./USD)</th><th>Vigencia BCV</th></tr></thead><tbody>';
        foreach(array_slice($seen,0,10,true) as $fecha=>$d) echo '<tr><td>'.esc_html($fecha).'</td><td>'.esc_html($d[0]).'</td><td>'.esc_html($d[1]).'</td></tr>';
        echo '</tbody></table></div>';
    }

    private function estadisticas() {
        $stats = array( 'pendiente' => 0, 'aprobado' => 0, 'rechazado' => 0 );
        $ids = get_posts( array( 'post_type' => self::CPT, 'post_status' => 'private', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true ) );
        foreach ( $ids as $id ) {
            $estado = get_post_meta( $id, self::META_ESTADO, true );
            if ( isset( $stats[ $estado ] ) ) {
                ++$stats[ $estado ];
            }
        }
        return $stats;
    }

    private function render_notice() {
        if ( empty( $_GET['svcp_pago_admin'] ) ) {
            return;
        }
        $estado = sanitize_key( wp_unslash( $_GET['svcp_pago_admin'] ) );
        $mensajes = array(
            'aprobado' => 'El pago fue aprobado y la membresía fue actualizada.',
            'rechazado' => 'El pago fue rechazado y se notificó al médico.',
            'error' => 'No fue posible procesar la operación.',
        );
        if ( isset( $mensajes[ $estado ] ) ) {
            $clase = 'error' === $estado ? 'notice-error' : 'notice-success';
            echo '<div class="notice ' . esc_attr( $clase ) . ' is-dismissible"><p>' . esc_html( $mensajes[ $estado ] ) . '</p></div>';
        }
    }

    public function resolver() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No autorizado.', 'svcp-directorio' ) );
        }
        $id = isset( $_POST['pago_id'] ) ? absint( $_POST['pago_id'] ) : 0;
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! $id || ! wp_verify_nonce( $nonce, 'svcp_resolver_' . $id ) ) {
            wp_die( esc_html__( 'Solicitud no válida o expirada.', 'svcp-directorio' ) );
        }
        if ( self::CPT !== get_post_type( $id ) || 'private' !== get_post_status( $id ) ) {
            wp_die( esc_html__( 'El reporte de pago no es válido.', 'svcp-directorio' ) );
        }
        if ( 'pendiente' !== get_post_meta( $id, self::META_ESTADO, true ) ) {
            wp_die( esc_html__( 'Este pago ya fue procesado.', 'svcp-directorio' ) );
        }

        $decision = isset( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : '';
        $doctor = absint( get_post_meta( $id, self::META_DOCTOR, true ) );
        $uid = absint( get_post_meta( $doctor, 'svcp_usuario_id', true ) );
        $user = $uid ? get_user_by( 'id', $uid ) : false;
        $monto = get_post_meta( $id, self::META_MONTO, true );
        $ref = get_post_meta( $id, self::META_REFERENCIA, true );
        $admin_id = get_current_user_id();

        if ( 'aprobar' === $decision ) {
            if ( ! $doctor || 'doctor' !== get_post_type( $doctor ) ) {
                wp_die( esc_html__( 'La ficha médica asociada no es válida.', 'svcp-directorio' ) );
            }
            $saldo_raw = isset($_POST['saldo_pendiente_bs']) ? str_replace(',', '.', sanitize_text_field(wp_unslash($_POST['saldo_pendiente_bs']))) : '0';
            if ( ! is_numeric($saldo_raw) || (float)$saldo_raw < 0 || (float)$saldo_raw > 999999999.99 ) { $this->redirigir_admin('error'); }
            $saldo_bs_manual = round((float)$saldo_raw, 2);
            $tasa_pago = (float) get_post_meta($id, self::META_TASA_BCV, true);
            $pagado_usd = (float) get_post_meta($id,self::META_PAGO_USD,true);
            $deuda_previa_usd = max(0, (float) get_post_meta($doctor, 'svcp_membresia_saldo_pendiente_usd', true));
            $saldo_usd_manual = $tasa_pago > 0 ? $saldo_bs_manual / $tasa_pago : 0;
            if ($deuda_previa_usd > 0) {
                // Los nuevos pagos se aplican primero a la deuda ya existente.
                $abono_deuda_usd = min($deuda_previa_usd, $pagado_usd);
                $saldo_usd = max(0, $deuda_previa_usd - $abono_deuda_usd);
                $saldo_bs = $saldo_usd * (float) $tasa_pago;
                update_post_meta($id, '_svcp_abono_deuda_previa_usd', number_format($abono_deuda_usd,8,'.',''));
                update_post_meta($id, '_svcp_deuda_restante_usd', number_format($saldo_usd,8,'.',''));
            } else {
                $saldo_usd = $saldo_usd_manual;
                $saldo_bs = $saldo_bs_manual;
                update_post_meta($id, '_svcp_abono_deuda_previa_usd', '0');
                update_post_meta($id, '_svcp_deuda_restante_usd', number_format($saldo_usd,8,'.',''));
            }
            update_post_meta($id, self::META_SALDO_BS, number_format($saldo_bs,2,'.',''));
            update_post_meta($id, self::META_SALDO_USD, number_format($saldo_usd,8,'.',''));
            update_post_meta($id, '_svcp_pago_usd_confirmado', number_format($pagado_usd,8,'.',''));
            update_post_meta($doctor, 'svcp_membresia_saldo_pendiente_usd', number_format($saldo_usd,8,'.',''));
            update_post_meta($doctor, 'svcp_membresia_saldo_pendiente_bs', number_format($saldo_bs,2,'.',''));
            $hoy = current_time( 'Y-m-d' );
            // Todos los doctores comparten el vencimiento institucional configurado.
            $ciclo = class_exists( 'SVCP_Ciclo_Membresia' ) ? SVCP_Ciclo_Membresia::calcular_ciclo( $hoy ) : array( 'inicio' => $hoy, 'fin' => gmdate( 'Y-m-d', strtotime( $hoy . ' +365 days' ) ) );
            $nuevo = $ciclo['fin'];
            update_post_meta( $doctor, 'svcp_membresia_inicio', $ciclo['inicio'] );
            update_post_meta( $doctor, 'svcp_membresia_periodo', $ciclo['inicio'] . '/' . $ciclo['fin'] );
            update_post_meta( $doctor, 'svcp_membresia_vencimiento', $nuevo );
            update_post_meta( $doctor, 'svcp_membresia_activa', 1 );
            update_post_meta( $doctor, 'svcp_membresia_estado', '' );
            update_post_meta( $doctor, 'svcp_membresia_ultimo_pago', $hoy );
            update_post_meta( $doctor, 'svcp_membresia_metodo_pago', 'Reporte manual' );
            update_post_meta( $doctor, 'svcp_membresia_referencia', $ref );
            update_post_meta( $doctor, 'svcp_membresia_monto', $monto );
            update_post_meta( $doctor, 'svcp_membresia_moneda', 'VES' );
            update_post_meta( $doctor, 'svcp_membresia_tasa_bcv', get_post_meta($id, self::META_TASA_BCV, true) );
            update_post_meta( $doctor, 'svcp_membresia_tasa_fecha', get_post_meta($id, self::META_TASA_FECHA, true) );
            update_post_meta( $doctor, 'svcp_membresia_precio_usd', get_post_meta($id, self::META_PRECIO_USD, true) );

            update_post_meta( $id, self::META_ESTADO, 'aprobado' );
            if ( class_exists('SVCP_Auditoria') ) { SVCP_Auditoria::registrar( $doctor, 'pago_aprobado', 'pago_' . $id, 'pendiente', 'aprobado · Bs. ' . $monto . ' · USD ' . number_format($pagado_usd,4,'.','') . ' · saldo Bs. ' . number_format($saldo_bs,2,'.','') . ' · ref. ' . $ref, 'finanzas' ); }
            update_post_meta( $id, self::META_RESUELTO_POR, $admin_id );
            update_post_meta( $id, self::META_RESUELTO_EN, current_time( 'mysql' ) );

            if ( $user && $user->user_email ) {
                if ( class_exists( 'SVCP_Correos' ) ) {
                    SVCP_Correos::enviar( $user->user_email, 'pago_aprobado', array( 'nombre' => $user->display_name, 'fecha_vencimiento' => wp_date( 'd/m/Y', strtotime( $nuevo ), wp_timezone() ), 'referencia' => $ref, 'monto_pagado_usd' => number_format($pagado_usd,4,'.',''), 'saldo_pendiente_bs' => number_format($saldo_bs,2,'.',''), 'saldo_pendiente_usd' => number_format($saldo_usd,4,'.',''), 'url_panel' => home_url( '/' ) ) );
                } else {
                    wp_mail( $user->user_email, 'Pago aprobado · Membresía SVCP', 'Hola ' . $user->display_name . ', tu pago fue aprobado. Vigencia hasta ' . $nuevo );
                }
            }
            $url_aprobaciones = add_query_arg(
                array(
                    'post_type' => 'doctor',
                    'page' => 'svcp-aprobaciones',
                    'doctor_id' => $doctor,
                    'svcp_pago_aprobado' => '1',
                ),
                admin_url( 'edit.php' )
            );
            wp_safe_redirect( $url_aprobaciones );
            exit;
        }

        if ( 'rechazar' === $decision ) {
            $motivo = isset( $_POST['motivo'] ) ? sanitize_textarea_field( wp_unslash( $_POST['motivo'] ) ) : '';
            if ( '' === $motivo ) {
                $this->redirigir_admin( 'error' );
            }
            update_post_meta( $id, self::META_ESTADO, 'rechazado' );
            if ( class_exists('SVCP_Auditoria') ) { SVCP_Auditoria::registrar( $doctor, 'pago_rechazado', 'pago_' . $id, 'pendiente', 'rechazado · ' . $motivo, 'finanzas' ); }
            update_post_meta( $id, self::META_MOTIVO, $motivo );
            update_post_meta( $id, self::META_RESUELTO_POR, $admin_id );
            update_post_meta( $id, self::META_RESUELTO_EN, current_time( 'mysql' ) );

            if ( $user && $user->user_email ) {
                if ( class_exists( 'SVCP_Correos' ) ) {
                    SVCP_Correos::enviar( $user->user_email, 'pago_rechazado', array( 'nombre' => $user->display_name, 'motivo' => $motivo, 'url_panel' => home_url( '/' ) ) );
                } else {
                    wp_mail( $user->user_email, 'Reporte de pago rechazado · SVCP', 'Hola ' . $user->display_name . '. Motivo: ' . $motivo );
                }
            }
            $this->redirigir_admin( 'rechazado' );
        }

        $this->redirigir_admin( 'error' );
    }

    private function redirigir_admin( $estado ) {
        $url = admin_url( 'edit.php?post_type=doctor&page=svcp-pagos' );
        wp_safe_redirect( add_query_arg( 'svcp_pago_admin', sanitize_key( $estado ), $url ) );
        exit;
    }
}
