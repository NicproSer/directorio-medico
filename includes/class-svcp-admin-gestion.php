<?php
/**
 * Gestión administrativa avanzada del directorio.
 *
 * Añade indicadores de calidad, filtros de atención y acciones masivas
 * sobre las fichas de doctores. Todas las acciones administrativas respetan
 * las capacidades del CPT y dejan rastro en la auditoría cuando corresponde.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SVCP_Admin_Gestion {
    private static $instancia = null;

    public static function instance() {
        if ( null === self::$instancia ) { self::$instancia = new self(); }
        return self::$instancia;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'registrar_menu' ) );
        add_action( 'restrict_manage_posts', array( $this, 'filtros_listado' ) );
        add_action( 'pre_get_posts', array( $this, 'filtrar_listado' ), 20 );
        add_filter( 'bulk_actions-edit-doctor', array( $this, 'acciones_masivas' ) );
        add_filter( 'handle_bulk_actions-edit-doctor', array( $this, 'procesar_accion_masiva' ), 10, 3 );
        add_action( 'admin_notices', array( $this, 'avisos_acciones' ) );
    }

    public function registrar_menu() {
        add_submenu_page(
            'edit.php?post_type=doctor',
            __( 'Gestión del directorio', 'svcp-directorio' ),
            __( 'Gestión', 'svcp-directorio' ),
            'edit_doctors',
            'svcp-gestion',
            array( $this, 'renderizar' )
        );
    }

    private function ids_doctores() {
        return get_posts( array(
            'post_type'      => 'doctor',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ) );
    }

    private function diagnosticos() {
        $conteos = array(
            'sin_cuenta' => 0,
            'sin_region' => 0,
            'sin_correo' => 0,
            'sin_ciudad' => 0,
            'sin_foto'   => 0,
            'pendientes' => 0,
            'publicados' => 0,
        );

        foreach ( $this->ids_doctores() as $doctor_id ) {
            if ( ! absint( get_post_meta( $doctor_id, 'svcp_usuario_id', true ) ) ) { $conteos['sin_cuenta']++; }
            $region = wp_get_post_terms( $doctor_id, 'region', array( 'fields' => 'ids' ) );
            if ( empty( $region ) || is_wp_error( $region ) ) { $conteos['sin_region']++; }
            if ( ! is_email( get_post_meta( $doctor_id, 'svcp_correo', true ) ) ) { $conteos['sin_correo']++; }
            if ( '' === trim( (string) get_post_meta( $doctor_id, 'svcp_ciudad', true ) ) ) { $conteos['sin_ciudad']++; }
            if ( ! has_post_thumbnail( $doctor_id ) ) { $conteos['sin_foto']++; }
            if ( 'pending' === get_post_status( $doctor_id ) ) { $conteos['pendientes']++; }
            if ( 'publish' === get_post_status( $doctor_id ) ) { $conteos['publicados']++; }
        }
        return $conteos;
    }

    public function renderizar() {
        if ( ! current_user_can( 'edit_doctors' ) ) {
            wp_die( esc_html__( 'No tienes permisos para acceder a esta sección.', 'svcp-directorio' ) );
        }
        $d = $this->diagnosticos();
        $url_listado = admin_url( 'edit.php?post_type=doctor' );
        $enlaces = array(
            'sin_cuenta' => array( 'Sin cuenta asociada', 'svcp_sin_cuenta' ),
            'sin_region' => array( 'Sin región', 'svcp_sin_region' ),
            'sin_correo' => array( 'Sin correo válido', 'svcp_sin_correo' ),
            'sin_ciudad' => array( 'Sin ciudad', 'svcp_sin_ciudad' ),
            'sin_foto'   => array( 'Sin fotografía', 'svcp_sin_foto' ),
        );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Gestión del directorio', 'svcp-directorio' ); ?></h1>
            <p class="description">Revisa rápidamente las fichas que necesitan atención y accede al listado para aplicar acciones masivas.</p>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px;margin:20px 0;max-width:1100px;">
                <?php foreach ( $enlaces as $clave => $item ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( $item[1], '1', $url_listado ) ); ?>" style="display:block;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:18px;text-decoration:none;box-shadow:0 1px 2px rgba(0,0,0,.04);">
                        <strong style="display:block;font-size:15px;color:#1d2327;"><?php echo esc_html( $item[0] ); ?></strong>
                        <span style="display:block;font-size:28px;line-height:1.2;margin-top:8px;color:#2271b1;"><?php echo esc_html( $d[ $clave ] ); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
            <div style="background:#fff;border:1px solid #dcdcde;padding:18px;max-width:1100px;">
                <h2 style="margin-top:0;">Resumen</h2>
                <p><strong><?php echo esc_html( $d['publicados'] ); ?></strong> fichas publicadas · <strong><?php echo esc_html( $d['pendientes'] ); ?></strong> pendientes.</p>
                <p><a class="button button-primary" href="<?php echo esc_url( $url_listado ); ?>">Abrir listado de doctores</a> <a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=doctor&page=svcp-membresias' ) ); ?>">Ver membresías</a></p>
            </div>
        </div>
        <?php
    }

    public function filtros_listado( $post_type ) {
        if ( 'doctor' !== $post_type || ! current_user_can( 'edit_doctors' ) ) { return; }
        $actual = isset( $_GET['svcp_alerta'] ) ? sanitize_key( wp_unslash( $_GET['svcp_alerta'] ) ) : '';
        $regiones = get_terms( array( 'taxonomy' => 'region', 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC' ) );
        ?>
        <select name="svcp_alerta">
            <option value="">Todas las fichas</option>
            <option value="sin_cuenta" <?php selected( $actual, 'sin_cuenta' ); ?>>Sin cuenta asociada</option>
            <option value="sin_region" <?php selected( $actual, 'sin_region' ); ?>>Sin región</option>
            <option value="sin_correo" <?php selected( $actual, 'sin_correo' ); ?>>Sin correo válido</option>
            <option value="sin_ciudad" <?php selected( $actual, 'sin_ciudad' ); ?>>Sin ciudad</option>
            <option value="sin_foto" <?php selected( $actual, 'sin_foto' ); ?>>Sin fotografía</option>
        </select>
        <?php if ( ! is_wp_error( $regiones ) ) : ?>
            <select name="region">
                <option value="">Todas las regiones</option>
                <?php $actual_region = isset( $_GET['region'] ) ? sanitize_title( wp_unslash( $_GET['region'] ) ) : ''; foreach ( $regiones as $region ) : ?>
                    <option value="<?php echo esc_attr( $region->slug ); ?>" <?php selected( $actual_region, $region->slug ); ?>><?php echo esc_html( $region->name ); ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
        <?php
    }

    public function filtrar_listado( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() || 'doctor' !== $query->get( 'post_type' ) || ! current_user_can( 'edit_doctors' ) ) { return; }
        $alerta = isset( $_GET['svcp_alerta'] ) ? sanitize_key( wp_unslash( $_GET['svcp_alerta'] ) ) : '';
        $mapa = array(
            'sin_cuenta' => array( 'key' => 'svcp_usuario_id', 'compare' => 'NOT EXISTS' ),
            'sin_ciudad' => array( 'key' => 'svcp_ciudad', 'compare' => 'NOT EXISTS' ),
            'sin_foto'   => null,
        );
        if ( 'sin_correo' === $alerta ) {
            $query->set( 'meta_query', array( 'relation' => 'OR', array( 'key' => 'svcp_correo', 'compare' => 'NOT EXISTS' ), array( 'key' => 'svcp_correo', 'value' => '', 'compare' => '=' ) ) );
        } elseif ( isset( $mapa[ $alerta ] ) && null !== $mapa[ $alerta ] ) {
            $query->set( 'meta_query', array( $mapa[ $alerta] ) );
        } elseif ( 'sin_region' === $alerta ) {
            $tax_query = (array) $query->get( 'tax_query' );
            $tax_query[] = array( 'taxonomy' => 'region', 'operator' => 'NOT EXISTS' );
            $query->set( 'tax_query', $tax_query );
        }

        if ( 'sin_foto' === $alerta ) {
            $query->set( 'meta_query', array( 'relation' => 'OR', array( 'key' => '_thumbnail_id', 'compare' => 'NOT EXISTS' ), array( 'key' => '_thumbnail_id', 'value' => '', 'compare' => '=' ) ) );
        }
    }

    public function acciones_masivas( $acciones ) {
        if ( ! current_user_can( 'edit_doctors' ) ) { return $acciones; }
        $acciones['svcp_publicar'] = 'SVCP: Publicar fichas';
        $acciones['svcp_pendiente'] = 'SVCP: Pasar a pendiente';
        $regiones = get_terms( array( 'taxonomy' => 'region', 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC' ) );
        if ( ! is_wp_error( $regiones ) ) {
            foreach ( $regiones as $region ) {
                $acciones['svcp_region_' . $region->slug] = 'SVCP: Región — ' . $region->name;
            }
        }
        return $acciones;
    }

    public function procesar_accion_masiva( $redirect, $accion, $post_ids ) {
        if ( ! current_user_can( 'edit_doctors' ) ) { return $redirect; }
        if ( ! in_array( $accion, array( 'svcp_publicar', 'svcp_pendiente' ), true ) && 0 !== strpos( $accion, 'svcp_region_' ) ) { return $redirect; }
        $procesados = 0;
        $errores = 0;
        foreach ( array_map( 'absint', (array) $post_ids ) as $doctor_id ) {
            if ( 'doctor' !== get_post_type( $doctor_id ) || ! current_user_can( 'edit_post', $doctor_id ) ) { $errores++; continue; }
            if ( 'svcp_publicar' === $accion ) {
                $resultado = wp_update_post( array( 'ID' => $doctor_id, 'post_status' => 'publish' ), true );
                if ( ! is_wp_error( $resultado ) && class_exists( 'SVCP_Aprobacion' ) ) { SVCP_Aprobacion::establecer( $doctor_id, SVCP_Aprobacion::PUBLICADA, false ); }
                $resultado instanceof WP_Error ? $errores++ : $procesados++;
                continue;
            }
            if ( 'svcp_pendiente' === $accion ) {
                $resultado = wp_update_post( array( 'ID' => $doctor_id, 'post_status' => 'pending' ), true );
                if ( ! is_wp_error( $resultado ) && class_exists( 'SVCP_Aprobacion' ) ) { SVCP_Aprobacion::establecer( $doctor_id, SVCP_Aprobacion::PENDIENTE_REVISION, false ); }
                $resultado instanceof WP_Error ? $errores++ : $procesados++;
                continue;
            }
            $slug = sanitize_title( substr( $accion, strlen( 'svcp_region_' ) ) );
            $term = get_term_by( 'slug', $slug, 'region' );
            if ( ! $term || is_wp_error( $term ) ) { $errores++; continue; }
            $antes = wp_get_post_terms( $doctor_id, 'region', array( 'fields' => 'names' ) );
            $resultado = wp_set_post_terms( $doctor_id, array( (int) $term->term_id ), 'region', false );
            if ( is_wp_error( $resultado ) ) { $errores++; continue; }
            $procesados++;
            if ( class_exists( 'SVCP_Auditoria' ) ) {
                SVCP_Auditoria::registrar( $doctor_id, 'actualizado', 'region', implode( ', ', $antes ), $term->name, 'administracion_masiva' );
            }
        }
        return add_query_arg( array( 'svcp_masiva' => 1, 'svcp_procesados' => $procesados, 'svcp_errores' => $errores ), $redirect );
    }

    public function avisos_acciones() {
        if ( ! is_admin() || ! current_user_can( 'edit_doctors' ) || ! isset( $_GET['svcp_masiva'] ) ) { return; }
        $procesados = isset( $_GET['svcp_procesados'] ) ? absint( $_GET['svcp_procesados'] ) : 0;
        $errores = isset( $_GET['svcp_errores'] ) ? absint( $_GET['svcp_errores'] ) : 0;
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( 'Acción SVCP completada: %d ficha(s) procesada(s).', $procesados ) );
        if ( $errores ) { echo ' ' . esc_html( sprintf( '%d ficha(s) no pudieron procesarse.', $errores ) ); }
        echo '</p></div>';
    }
}
