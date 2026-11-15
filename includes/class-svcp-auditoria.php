<?php
/** Historial de cambios del Directorio Médico. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SVCP_Auditoria {
    private static $instancia = null;
    private static $tabla = null;

    public static function instance() {
        if ( null === self::$instancia ) { self::$instancia = new self(); }
        return self::$instancia;
    }

    public static function tabla() {
        global $wpdb;
        if ( null === self::$tabla ) {
            self::$tabla = $wpdb->prefix . 'svcp_auditoria';
        }
        return self::$tabla;
    }

    private function __construct() {
        add_action( 'update_postmeta', array( $this, 'meta_actualizada' ), 10, 4 );
        add_action( 'wp_after_insert_post', array( $this, 'post_insertado' ), 10, 4 );
        add_action( 'init', array( $this, 'maybe_install' ), 1 );
        add_action( 'added_post_meta', array( $this, 'meta_agregada' ), 10, 4 );
        add_action( 'deleted_post_meta', array( $this, 'meta_eliminada' ), 10, 4 );
        add_action( 'post_updated', array( $this, 'post_actualizado' ), 10, 3 );
        add_action( 'add_meta_boxes_doctor', array( $this, 'agregar_metabox' ) );
        add_action( 'admin_menu', array( $this, 'agregar_menu' ) );
    }

    public static function instalar() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $tabla = self::tabla();
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$tabla} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            doctor_id bigint(20) unsigned NOT NULL DEFAULT 0,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            accion varchar(60) NOT NULL DEFAULT '',
            campo varchar(100) NOT NULL DEFAULT '',
            valor_anterior longtext NULL,
            valor_nuevo longtext NULL,
            contexto varchar(60) NOT NULL DEFAULT '',
            fecha datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY doctor_id (doctor_id),
            KEY user_id (user_id),
            KEY accion (accion),
            KEY fecha (fecha)
        ) {$charset};";
        dbDelta( $sql );
        update_option( 'svcp_auditoria_db_version', '1.0.0' );
    }

    public static function registrar( $doctor_id, $accion, $campo = '', $anterior = '', $nuevo = '', $contexto = '' ) {
        $doctor_id = absint( $doctor_id );
        if ( ! $doctor_id || 'doctor' !== get_post_type( $doctor_id ) ) { return; }
        if ( ! is_user_logged_in() ) { return; }
        $user_id = get_current_user_id();
        if ( ! $user_id ) { return; }
        if ( self::normalizar_valor( $anterior ) === self::normalizar_valor( $nuevo ) && 'creado' !== $accion ) { return; }

        global $wpdb;
        $wpdb->insert(
            self::tabla(),
            array(
                'doctor_id'      => $doctor_id,
                'user_id'        => $user_id,
                'accion'         => sanitize_key( $accion ),
                'campo'          => sanitize_key( $campo ),
                'valor_anterior' => self::preparar_valor( $anterior ),
                'valor_nuevo'    => self::preparar_valor( $nuevo ),
                'contexto'       => sanitize_key( $contexto ),
                'fecha'          => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
        );
    }

    private static function normalizar_valor( $valor ) {
        if ( is_array( $valor ) || is_object( $valor ) ) { return wp_json_encode( $valor ); }
        return trim( (string) $valor );
    }

    private static function preparar_valor( $valor ) {
        if ( is_array( $valor ) || is_object( $valor ) ) { return wp_json_encode( $valor ); }
        return wp_check_invalid_utf8( (string) $valor );
    }

    private function claves_auditar() {
        return array(
            'svcp_telefono', 'svcp_whatsapp', 'svcp_clinica', 'svcp_direccion', 'svcp_ciudad',
            'svcp_correo', 'svcp_facebook', 'svcp_instagram', 'svcp_twitter', 'svcp_linkedin',
            'svcp_sitio_web', 'svcp_usuario_id', 'svcp_membresia_estado', 'svcp_membresia_inicio',
            'svcp_membresia_vencimiento', 'svcp_membresia_ultimo_pago', 'svcp_membresia_metodo_pago',
            'svcp_membresia_referencia', 'svcp_membresia_monto', 'svcp_membresia_activa'
        );
    }

    private function etiqueta_campo( $campo ) {
        $mapa = array(
            'svcp_telefono' => 'Teléfono', 'svcp_whatsapp' => 'WhatsApp', 'svcp_clinica' => 'Clínica u Hospital',
            'svcp_direccion' => 'Dirección', 'svcp_ciudad' => 'Ciudad', 'svcp_correo' => 'Correo',
            'svcp_facebook' => 'Facebook', 'svcp_instagram' => 'Instagram', 'svcp_twitter' => 'Twitter / X',
            'svcp_linkedin' => 'LinkedIn', 'svcp_sitio_web' => 'Sitio web', 'svcp_usuario_id' => 'Cuenta del doctor',
            'svcp_membresia_estado' => 'Estado de membresía', 'svcp_membresia_inicio' => 'Inicio de membresía',
            'svcp_membresia_vencimiento' => 'Vencimiento de membresía', 'svcp_membresia_ultimo_pago' => 'Último pago',
            'svcp_membresia_metodo_pago' => 'Método de pago', 'svcp_membresia_referencia' => 'Referencia de pago',
            'svcp_membresia_monto' => 'Monto de membresía', 'svcp_membresia_activa' => 'Membresía activa'
        );
        return isset( $mapa[ $campo ] ) ? $mapa[ $campo ] : $campo;
    }

    public function meta_actualizada( $meta_id, $post_id, $meta_key, $meta_value ) {
        if ( 'doctor' !== get_post_type( $post_id ) || ! in_array( $meta_key, $this->claves_auditar(), true ) ) { return; }
        $previous = get_metadata_by_mid( 'post', $meta_id );
        $old = is_object( $previous ) && isset( $previous->meta_value ) ? $previous->meta_value : '';
        $contexto = strpos( $meta_key, 'membresia_' ) === 0 ? 'membresia' : 'perfil';
        self::registrar( $post_id, 'actualizado', $meta_key, $old, $meta_value, $contexto );
    }

    public function meta_agregada( $meta_id, $post_id, $meta_key, $meta_value ) {
        if ( 'doctor' !== get_post_type( $post_id ) || ! in_array( $meta_key, $this->claves_auditar(), true ) ) { return; }
        $contexto = strpos( $meta_key, 'membresia_' ) === 0 ? 'membresia' : 'perfil';
        self::registrar( $post_id, 'agregado', $meta_key, '', $meta_value, $contexto );
    }

    public function meta_eliminada( $meta_ids, $post_id, $meta_key, $meta_value ) {
        if ( 'doctor' !== get_post_type( $post_id ) || ! in_array( $meta_key, $this->claves_auditar(), true ) ) { return; }
        $contexto = strpos( $meta_key, 'membresia_' ) === 0 ? 'membresia' : 'perfil';
        self::registrar( $post_id, 'eliminado', $meta_key, $meta_value, '', $contexto );
    }

    public function post_insertado( $post_id, $post, $update, $post_before ) {
        if ( 'doctor' !== get_post_type( $post_id ) || $update || wp_is_post_revision( $post_id ) ) { return; }
        self::registrar( $post_id, 'creado', 'post_title', '', $post->post_title, 'ficha' );
    }

    public function maybe_install() {
        if ( '1.0.0' !== get_option( 'svcp_auditoria_db_version' ) ) {
            self::instalar();
        }
    }

    public function post_actualizado( $post_id, $post_after, $post_before ) {
        if ( 'doctor' !== get_post_type( $post_id ) || wp_is_post_revision( $post_id ) ) { return; }
        if ( $post_before->post_title !== $post_after->post_title ) {
            self::registrar( $post_id, 'actualizado', 'post_title', $post_before->post_title, $post_after->post_title, 'ficha' );
        }
        if ( $post_before->post_status !== $post_after->post_status ) {
            self::registrar( $post_id, 'actualizado', 'post_status', $post_before->post_status, $post_after->post_status, 'ficha' );
        }
    }

    public function agregar_metabox() {
        add_meta_box( 'svcp_auditoria_doctor', 'Historial de cambios', array( $this, 'render_metabox' ), 'doctor', 'normal', 'default' );
    }

    public function render_metabox( $post ) {
        if ( ! current_user_can( 'manage_options' ) ) {
            echo '<p>El historial está disponible para administradores.</p>';
            return;
        }
        $items = $this->obtener( array( 'doctor_id' => $post->ID, 'limit' => 15 ) );
        if ( empty( $items ) ) {
            echo '<p>Aún no hay cambios registrados.</p>';
            return;
        }
        echo '<div style="max-height:360px;overflow:auto;"><table class="widefat striped"><thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Campo</th><th>Cambio</th></tr></thead><tbody>';
        foreach ( $items as $item ) {
            $user = get_user_by( 'id', $item->user_id );
            $nombre = $user ? $user->display_name : 'Usuario #' . absint( $item->user_id );
            $campo = $item->campo ? $this->etiqueta_campo( $item->campo ) : '—';
            echo '<tr><td>' . esc_html( mysql2date( 'd/m/Y H:i', $item->fecha ) ) . '</td><td>' . esc_html( $nombre ) . '</td><td>' . esc_html( ucfirst( $item->accion ) ) . '</td><td>' . esc_html( $campo ) . '</td><td>' . esc_html( $this->resumen_cambio( $item ) ) . '</td></tr>';
        }
        echo '</tbody></table></div>';
        echo '<p><a class="button" href="' . esc_url( $this->url_admin( array( 'doctor_id' => $post->ID ) ) ) . '">Ver historial completo</a></p>';
    }

    private function resumen_cambio( $item ) {
        if ( 'svcp_membresia_monto' === $item->campo ) {
            return ( '' !== $item->valor_anterior ? $item->valor_anterior : '—' ) . ' → ' . ( '' !== $item->valor_nuevo ? $item->valor_nuevo : '—' );
        }
        $old = $item->valor_anterior;
        $new = $item->valor_nuevo;
        if ( '' === $old && '' !== $new ) { return 'Añadido: ' . wp_trim_words( $new, 12, '…' ); }
        if ( '' !== $old && '' === $new ) { return 'Eliminado: ' . wp_trim_words( $old, 12, '…' ); }
        return wp_trim_words( $old, 8, '…' ) . ' → ' . wp_trim_words( $new, 8, '…' );
    }

    public function agregar_menu() {
        add_submenu_page( 'edit.php?post_type=doctor', 'Historial de cambios', 'Historial', 'manage_options', 'svcp-auditoria', array( $this, 'render_admin' ) );
    }

    public static function ultimos( $limite = 10 ) {
        global $wpdb;
        $tabla = self::tabla();
        $limite = min( 50, max( 1, absint( $limite ) ) );
        return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . $tabla . ' ORDER BY fecha DESC, id DESC LIMIT %d', $limite ) );
    }

    private function obtener( $args = array() ) {
        global $wpdb;
        $defaults = array( 'doctor_id' => 0, 'user_id' => 0, 'accion' => '', 'desde' => '', 'hasta' => '', 'limit' => 50, 'offset' => 0 );
        $args = wp_parse_args( $args, $defaults );
        $where = array( '1=1' );
        $params = array();
        if ( $args['doctor_id'] ) { $where[] = 'doctor_id = %d'; $params[] = absint( $args['doctor_id'] ); }
        if ( $args['user_id'] ) { $where[] = 'user_id = %d'; $params[] = absint( $args['user_id'] ); }
        if ( $args['accion'] ) { $where[] = 'accion = %s'; $params[] = sanitize_key( $args['accion'] ); }
        if ( $args['desde'] ) { $where[] = 'fecha >= %s'; $params[] = sanitize_text_field( $args['desde'] ) . ' 00:00:00'; }
        if ( $args['hasta'] ) { $where[] = 'fecha <= %s'; $params[] = sanitize_text_field( $args['hasta'] ) . ' 23:59:59'; }
        $sql = 'SELECT * FROM ' . self::tabla() . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY fecha DESC, id DESC LIMIT %d OFFSET %d';
        $params[] = min( 200, max( 1, absint( $args['limit'] ) ) );
        $params[] = max( 0, absint( $args['offset'] ) );
        return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
    }

    private function contar( $args = array() ) {
        global $wpdb;
        $defaults = array( 'doctor_id' => 0, 'user_id' => 0, 'accion' => '', 'desde' => '', 'hasta' => '' );
        $args = wp_parse_args( $args, $defaults );
        $where = array( '1=1' ); $params = array();
        if ( $args['doctor_id'] ) { $where[] = 'doctor_id = %d'; $params[] = absint( $args['doctor_id'] ); }
        if ( $args['user_id'] ) { $where[] = 'user_id = %d'; $params[] = absint( $args['user_id'] ); }
        if ( $args['accion'] ) { $where[] = 'accion = %s'; $params[] = sanitize_key( $args['accion'] ); }
        if ( $args['desde'] ) { $where[] = 'fecha >= %s'; $params[] = sanitize_text_field( $args['desde'] ) . ' 00:00:00'; }
        if ( $args['hasta'] ) { $where[] = 'fecha <= %s'; $params[] = sanitize_text_field( $args['hasta'] ) . ' 23:59:59'; }
        $sql = 'SELECT COUNT(*) FROM ' . self::tabla() . ' WHERE ' . implode( ' AND ', $where );
        return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
    }

    private function url_admin( $args = array() ) {
        return add_query_arg( array_merge( array( 'post_type' => 'doctor', 'page' => 'svcp-auditoria' ), $args ), admin_url( 'edit.php' ) );
    }

    public function render_admin() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'No tienes permisos para ver el historial.', 'svcp-directorio' ) ); }
        $args = array(
            'doctor_id' => isset( $_GET['doctor_id'] ) ? absint( $_GET['doctor_id'] ) : 0,
            'user_id'   => isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0,
            'accion'    => isset( $_GET['accion'] ) ? sanitize_key( wp_unslash( $_GET['accion'] ) ) : '',
            'desde'     => isset( $_GET['desde'] ) ? sanitize_text_field( wp_unslash( $_GET['desde'] ) ) : '',
            'hasta'     => isset( $_GET['hasta'] ) ? sanitize_text_field( wp_unslash( $_GET['hasta'] ) ) : '',
        );
        $pagina = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
        $por_pagina = 50;
        $total = $this->contar( $args );
        $items = $this->obtener( array_merge( $args, array( 'limit' => $por_pagina, 'offset' => ( $pagina - 1 ) * $por_pagina ) ) );
        $doctores = get_posts( array( 'post_type' => 'doctor', 'post_status' => 'any', 'numberposts' => 200, 'orderby' => 'title', 'order' => 'ASC' ) );
        $usuarios = get_users( array( 'fields' => array( 'ID', 'display_name' ), 'orderby' => 'display_name', 'order' => 'ASC', 'number' => 200 ) );
        ?>
        <div class="wrap">
            <h1>Historial de cambios</h1>
            <p>Registro de cambios realizados sobre las fichas de doctores, sus cuentas y datos administrativos.</p>
            <form method="get" style="background:#fff;padding:15px;margin:15px 0;border:1px solid #dcdcde;">
                <input type="hidden" name="post_type" value="doctor"><input type="hidden" name="page" value="svcp-auditoria">
                <select name="doctor_id"><option value="0">Todos los doctores</option><?php foreach ( $doctores as $doctor ) : ?><option value="<?php echo esc_attr( $doctor->ID ); ?>" <?php selected( $args['doctor_id'], $doctor->ID ); ?>><?php echo esc_html( $doctor->post_title ); ?></option><?php endforeach; ?></select>
                <select name="user_id"><option value="0">Todos los usuarios</option><?php foreach ( $usuarios as $usuario ) : ?><option value="<?php echo esc_attr( $usuario->ID ); ?>" <?php selected( $args['user_id'], $usuario->ID ); ?>><?php echo esc_html( $usuario->display_name ); ?></option><?php endforeach; ?></select>
                <select name="accion"><option value="">Todas las acciones</option><option value="actualizado" <?php selected( $args['accion'], 'actualizado' ); ?>>Actualizado</option><option value="agregado" <?php selected( $args['accion'], 'agregado' ); ?>>Agregado</option><option value="eliminado" <?php selected( $args['accion'], 'eliminado' ); ?>>Eliminado</option></select>
                <input type="date" name="desde" value="<?php echo esc_attr( $args['desde'] ); ?>"><input type="date" name="hasta" value="<?php echo esc_attr( $args['hasta'] ); ?>">
                <button class="button button-primary">Filtrar</button>
                <a class="button" href="<?php echo esc_url( $this->url_admin() ); ?>">Limpiar</a>
            </form>
            <p><strong><?php echo esc_html( number_format_i18n( $total ) ); ?></strong> cambios registrados.</p>
            <table class="widefat fixed striped"><thead><tr><th>Fecha</th><th>Doctor</th><th>Usuario</th><th>Acción</th><th>Campo</th><th>Antes</th><th>Después</th></tr></thead><tbody>
            <?php if ( empty( $items ) ) : ?><tr><td colspan="7">No hay cambios que coincidan con los filtros.</td></tr><?php else : foreach ( $items as $item ) : $user = get_user_by( 'id', $item->user_id ); ?>
                <tr><td><?php echo esc_html( mysql2date( 'd/m/Y H:i', $item->fecha ) ); ?></td><td><a href="<?php echo esc_url( get_edit_post_link( $item->doctor_id ) ); ?>"><?php echo esc_html( get_the_title( $item->doctor_id ) ?: 'Doctor #' . $item->doctor_id ); ?></a></td><td><?php echo esc_html( $user ? $user->display_name : 'Usuario #' . $item->user_id ); ?></td><td><?php echo esc_html( ucfirst( $item->accion ) ); ?></td><td><?php echo esc_html( $this->etiqueta_campo( $item->campo ) ); ?></td><td><?php echo esc_html( wp_trim_words( $item->valor_anterior, 18, '…' ) ); ?></td><td><?php echo esc_html( wp_trim_words( $item->valor_nuevo, 18, '…' ) ); ?></td></tr>
            <?php endforeach; endif; ?></tbody></table>
            <?php if ( $total > $por_pagina ) : ?><div style="margin-top:15px;"><?php echo wp_kses_post( paginate_links( array( 'base' => add_query_arg( 'paged', '%#%', $this->url_admin( $args ) ), 'format' => '', 'current' => $pagina, 'total' => ceil( $total / $por_pagina ), 'type' => 'list' ) ) ); ?></div><?php endif; ?>
        </div>
        <?php
    }
}
