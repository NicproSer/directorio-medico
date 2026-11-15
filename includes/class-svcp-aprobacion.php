<?php
/**
 * Sistema de aprobación del Directorio Médico SVCP.
 *
 * El estado de aprobación es independiente del post_status de WordPress.
 * Flujo:
 * - borrador
 * - pendiente_revision
 * - aprobada
 * - publicada
 * - revision_requerida
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SVCP_Aprobacion {
    private static $instancia = null;

    const META = 'svcp_estado_aprobacion';

    const BORRADOR            = 'borrador';
    const PENDIENTE_REVISION  = 'pendiente_revision';
    const APROBADA            = 'aprobada';
    const PUBLICADA           = 'publicada';
    const REVISION_REQUERIDA  = 'revision_requerida';

    public static function instance() {
        if ( null === self::$instancia ) { self::$instancia = new self(); }
        return self::$instancia;
    }

    private function __construct() {
        add_action( 'init', array( $this, 'registrar_meta' ) );
        add_action( 'init', array( $this, 'migrar_estados' ), 30 );
        add_action( 'add_meta_boxes', array( $this, 'agregar_metabox' ) );
        add_action( 'save_post_doctor', array( $this, 'guardar_metabox' ), 20, 3 );
        add_filter( 'manage_doctor_posts_columns', array( $this, 'columna' ) );
        add_action( 'manage_doctor_posts_custom_column', array( $this, 'columna_contenido' ), 10, 2 );
        add_action( 'admin_menu', array( $this, 'registrar_menu' ) );
        add_filter( 'parent_file', array( $this, 'actualizar_badge_menu' ) );
        add_action( 'admin_post_svcp_aprobacion_accion', array( $this, 'procesar_accion' ) );
    }

    public static function estados() {
        return array(
            self::BORRADOR           => __( 'Borrador', 'svcp-directorio' ),
            self::PENDIENTE_REVISION => __( 'Pendiente de revisión', 'svcp-directorio' ),
            self::APROBADA           => __( 'Aprobada', 'svcp-directorio' ),
            self::PUBLICADA          => __( 'Publicada', 'svcp-directorio' ),
            self::REVISION_REQUERIDA => __( 'Revisión requerida', 'svcp-directorio' ),
        );
    }

    public static function obtener_estado( $post_id ) {
        $estado = get_post_meta( $post_id, self::META, true );
        if ( isset( self::estados()[ $estado ] ) ) { return $estado; }

        // Compatibilidad con fichas creadas antes de v0.8.0.
        $post_status = get_post_status( $post_id );
        if ( 'publish' === $post_status ) { return self::PUBLICADA; }
        if ( 'draft' === $post_status ) { return self::BORRADOR; }
        if ( 'private' === $post_status ) { return self::REVISION_REQUERIDA; }
        return self::PENDIENTE_REVISION;
    }

    public static function puede_publicarse( $post_id ) {
        return self::PUBLICADA === self::obtener_estado( $post_id );
    }

    public static function establecer( $post_id, $estado, $sincronizar_post = true ) {
        if ( ! isset( self::estados()[ $estado ] ) || 'doctor' !== get_post_type( $post_id ) ) {
            return false;
        }

        $anterior = self::obtener_estado( $post_id );
        update_post_meta( $post_id, self::META, $estado );

        if ( $sincronizar_post ) {
            $post_status = self::post_status_para_estado( $estado );
            if ( $post_status && get_post_status( $post_id ) !== $post_status ) {
                wp_update_post( array( 'ID' => $post_id, 'post_status' => $post_status ) );
            }
        }

        if ( $anterior !== $estado && class_exists( 'SVCP_Auditoria' ) && is_user_logged_in() ) {
            SVCP_Auditoria::registrar( $post_id, 'actualizado', self::META, $anterior, $estado, 'aprobacion' );
        }
        return true;
    }

    private static function post_status_para_estado( $estado ) {
        switch ( $estado ) {
            case self::BORRADOR:           return 'draft';
            case self::PENDIENTE_REVISION: return 'pending';
            case self::APROBADA:           return 'pending';
            case self::PUBLICADA:          return 'publish';
            case self::REVISION_REQUERIDA: return 'pending';
        }
        return '';
    }

    public function registrar_meta() {
        register_post_meta( 'doctor', self::META, array(
            'type'              => 'string',
            'single'            => true,
            'show_in_rest'      => false,
            'sanitize_callback' => 'sanitize_key',
            'auth_callback'     => function() { return current_user_can( 'edit_doctors' ); },
        ) );
    }

    public function migrar_estados() {
        if ( '1.0.0' === get_option( 'svcp_aprobacion_version' ) ) { return; }

        $ids = get_posts( array(
            'post_type'      => 'doctor',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ) );

        foreach ( $ids as $doctor_id ) {
            if ( '' !== get_post_meta( $doctor_id, self::META, true ) ) { continue; }
            update_post_meta( $doctor_id, self::META, self::obtener_estado( $doctor_id ) );
        }

        update_option( 'svcp_aprobacion_version', '1.0.0' );
    }

    public function agregar_metabox() {
        add_meta_box(
            'svcp_aprobacion_doctor',
            __( 'Aprobación del directorio', 'svcp-directorio' ),
            array( $this, 'render_metabox' ),
            'doctor',
            'side',
            'high'
        );
    }

    public function render_metabox( $post ) {
        if ( ! current_user_can( 'edit_post', $post->ID ) ) {
            echo '<p>' . esc_html__( 'No tienes permisos para gestionar la aprobación.', 'svcp-directorio' ) . '</p>';
            return;
        }
        wp_nonce_field( 'svcp_guardar_aprobacion', 'svcp_aprobacion_nonce' );
        $estado = self::obtener_estado( $post->ID );
        echo '<p><label for="svcp_estado_aprobacion"><strong>' . esc_html__( 'Estado', 'svcp-directorio' ) . '</strong></label></p>';
        echo '<select style="width:100%" id="svcp_estado_aprobacion" name="svcp_estado_aprobacion">';
        foreach ( self::estados() as $key => $label ) {
            echo '<option value="' . esc_attr( $key ) . '" ' . selected( $estado, $key, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__( 'La publicación pública requiere el estado “Publicada” y una membresía vigente.', 'svcp-directorio' ) . '</p>';
    }

    public function guardar_metabox( $post_id, $post, $update ) {
        if ( ! $update || wp_is_post_revision( $post_id ) || ! isset( $_POST['svcp_aprobacion_nonce'] ) ) { return; }
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['svcp_aprobacion_nonce'] ) ), 'svcp_guardar_aprobacion' ) ) { return; }
        if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }
        if ( ! isset( $_POST['svcp_estado_aprobacion'] ) ) { return; }

        $estado = sanitize_key( wp_unslash( $_POST['svcp_estado_aprobacion'] ) );
        if ( isset( self::estados()[ $estado ] ) ) {
            self::establecer( $post_id, $estado, true );
        }
    }

    public function columna( $columns ) {
        $nuevo = array();
        foreach ( $columns as $key => $label ) {
            $nuevo[ $key ] = $label;
            if ( 'title' === $key ) {
                $nuevo['svcp_aprobacion'] = __( 'Aprobación', 'svcp-directorio' );
            }
        }
        return $nuevo;
    }

    public function columna_contenido( $column, $post_id ) {
        if ( 'svcp_aprobacion' !== $column ) { return; }
        $estado = self::obtener_estado( $post_id );
        $label = self::estados()[ $estado ] ?? $estado;
        echo '<strong>' . esc_html( $label ) . '</strong>';
    }

    public function registrar_menu() {
        add_submenu_page(
            'edit.php?post_type=doctor',
            __( 'Aprobaciones', 'svcp-directorio' ),
            $this->etiqueta_menu_aprobaciones(),
            'edit_doctors',
            'svcp-aprobaciones',
            array( $this, 'render_admin' )
        );
    }

    private function contar_pendientes_revision() {
        $q = new WP_Query(array(
            'post_type' => 'doctor', 'post_status' => 'any', 'posts_per_page' => 1,
            'fields' => 'ids', 'no_found_rows' => false,
            'meta_query' => array(array('key' => self::META, 'value' => array(self::PENDIENTE_REVISION, self::REVISION_REQUERIDA), 'compare' => 'IN')),
        ));
        return (int) $q->found_posts;
    }

    private function etiqueta_menu_aprobaciones() {
        $n = $this->contar_pendientes_revision();
        return 'Aprobaciones' . ( $n > 0 ? ' <span class="awaiting-mod count-' . absint($n) . '"><span class="pending-count">' . number_format_i18n($n) . '</span></span>' : '' );
    }

    public function actualizar_badge_menu( $parent_file ) {
        global $submenu;
        $clave = 'edit.php?post_type=doctor';
        if ( isset($submenu[$clave]) && is_array($submenu[$clave]) ) {
            foreach ( $submenu[$clave] as &$item ) {
                if ( isset($item[2]) && 'svcp-aprobaciones' === $item[2] ) { $item[0] = $this->etiqueta_menu_aprobaciones(); break; }
            }
            unset($item);
        }
        return $parent_file;
    }

    public function render_admin() {
        if ( ! current_user_can( 'edit_doctors' ) ) { wp_die( esc_html__( 'No tienes permisos.', 'svcp-directorio' ) ); }
        $estado_filtro = isset( $_GET['estado'] ) ? sanitize_key( wp_unslash( $_GET['estado'] ) ) : '';
        $args = array(
            'post_type'      => 'doctor',
            'post_status'    => 'any',
            'posts_per_page' => 50,
            'orderby'        => 'title',
            'order'          => 'ASC',
        );
        if ( $estado_filtro && isset( self::estados()[ $estado_filtro ] ) ) {
            $args['meta_query'] = array( array( 'key' => self::META, 'value' => $estado_filtro ) );
        }
        $doctores = get_posts( $args );
        $conteos = array_fill_keys( array_keys( self::estados() ), 0 );
        foreach ( get_posts( array( 'post_type'=>'doctor', 'post_status'=>'any', 'posts_per_page'=>-1, 'fields'=>'ids' ) ) as $id ) {
            $e = self::obtener_estado( $id ); if ( isset( $conteos[$e] ) ) { $conteos[$e]++; }
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Aprobaciones del Directorio', 'svcp-directorio' ); ?></h1>
            <p class="description">Gestiona el ciclo de revisión de las fichas sin depender únicamente del estado interno de WordPress.</p>
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin:18px 0;">
                <?php foreach ( self::estados() as $key => $label ) : ?>
                    <a class="button" href="<?php echo esc_url( add_query_arg( array( 'post_type'=>'doctor', 'page'=>'svcp-aprobaciones', 'estado'=>$key ), admin_url('edit.php') ) ); ?>"><strong><?php echo esc_html( $conteos[$key] ); ?></strong> <?php echo esc_html( $label ); ?></a>
                <?php endforeach; ?>
                <a class="button" href="<?php echo esc_url( admin_url('edit.php?post_type=doctor&page=svcp-aprobaciones') ); ?>">Todos</a>
            </div>
            <table class="widefat striped">
                <thead><tr><th>Doctor</th><th>Región</th><th>Aprobación</th><th>WordPress</th><th>Membresía</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php if ( empty($doctores) ) : ?><tr><td colspan="6">No hay fichas para este filtro.</td></tr><?php endif; ?>
                <?php foreach ( $doctores as $doctor ) :
                    $estado = self::obtener_estado($doctor->ID);
                    $terms = get_the_terms($doctor->ID, 'region');
                    $region = ( $terms && !is_wp_error($terms) ) ? $terms[0]->name : '—';
                    $memb = class_exists('SVCP_Membresia') ? SVCP_Membresia::estados()[ SVCP_Membresia::obtener_estado($doctor->ID) ] ?? '—' : '—';
                    ?>
                    <tr>
                        <td><strong><a href="<?php echo esc_url(get_edit_post_link($doctor->ID)); ?>"><?php echo esc_html($doctor->post_title); ?></a></strong></td>
                        <td><?php echo esc_html($region); ?></td>
                        <td><?php echo esc_html(self::estados()[$estado] ?? $estado); ?></td>
                        <td><?php echo esc_html($doctor->post_status); ?></td>
                        <td><?php echo esc_html($memb); ?></td>
                        <td>
                            <?php foreach ( array(self::PENDIENTE_REVISION=>'Enviar a revisión', self::APROBADA=>'Aprobar', self::PUBLICADA=>'Publicar', self::REVISION_REQUERIDA=>'Solicitar corrección') as $accion => $texto ) : ?>
                                <a class="button button-small" style="margin:2px 2px 2px 0" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=svcp_aprobacion_accion&doctor_id='.$doctor->ID.'&estado='.$accion), 'svcp_aprobacion_'.$doctor->ID)); ?>"><?php echo esc_html($texto); ?></a>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function procesar_accion() {
        $doctor_id = absint( $_GET['doctor_id'] ?? 0 );
        $estado = sanitize_key( wp_unslash( $_GET['estado'] ?? '' ) );
        if ( ! $doctor_id || ! current_user_can( 'edit_post', $doctor_id ) || ! isset( self::estados()[ $estado ] ) ) {
            wp_die( esc_html__( 'Acción no permitida.', 'svcp-directorio' ) );
        }
        check_admin_referer( 'svcp_aprobacion_' . $doctor_id );
        self::establecer( $doctor_id, $estado, true );
        wp_safe_redirect( add_query_arg( array( 'post_type'=>'doctor', 'page'=>'svcp-aprobaciones', 'svcp_aprobado'=>'1' ), admin_url('edit.php') ) );
        exit;
    }
}
