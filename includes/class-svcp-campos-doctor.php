<?php
/** Campos de la ficha del doctor y membresía administrativa. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SVCP_Campos_Doctor {
    private static $instancia = null;

    private static function campos_editables_por_doctor() {
        return array(
            'svcp_whatsapp'   => array( 'WhatsApp', 'text', false, 'sanitize_text_field' ),
            'svcp_direccion'  => array( 'Dirección', 'text', false, 'sanitize_text_field' ),
            'svcp_ciudad'     => array( 'Ciudad', 'text', true, 'sanitize_text_field' ),
            'svcp_correo'     => array( 'Correo', 'email', true, 'sanitize_email' ),
            'svcp_facebook'   => array( 'Facebook (opcional)', 'url', false, 'esc_url_raw' ),
            'svcp_instagram'  => array( 'Instagram (opcional)', 'url', false, 'esc_url_raw' ),
            'svcp_twitter'    => array( 'Twitter / X (opcional)', 'url', false, 'esc_url_raw' ),
            'svcp_linkedin'   => array( 'LinkedIn (opcional)', 'url', false, 'esc_url_raw' ),
            'svcp_sitio_web'  => array( 'Sitio web (opcional)', 'url', false, 'esc_url_raw' ),
        );
    }

    public static function instance() {
        if ( null === self::$instancia ) { self::$instancia = new self(); }
        return self::$instancia;
    }

    private function __construct() {
        add_action( 'init', array( $this, 'registrar_meta' ) );
        add_action( 'add_meta_boxes', array( $this, 'agregar_metabox' ) );
        add_action( 'save_post_doctor', array( $this, 'guardar_metabox' ) );
        add_action( 'admin_post_svcp_crear_cuenta_doctor', array( $this, 'crear_cuenta_doctor' ) );
    }

    public function registrar_meta() {
        foreach ( self::campos_editables_por_doctor() as $key => $config ) {
            $sanitizer = $config[3];
            register_post_meta( 'doctor', $key, array(
                'type'              => 'string', 'single' => true, 'show_in_rest' => true,
                'sanitize_callback' => $sanitizer,
                'auth_callback'     => function() { return current_user_can( 'edit_doctor' ); },
            ) );
        }

        foreach ( array( 'svcp_telefono', 'svcp_clinica' ) as $key ) {
            register_post_meta( 'doctor', $key, array(
                'type' => 'string', 'single' => true, 'show_in_rest' => true,
                'sanitize_callback' => 'sanitize_text_field',
                'auth_callback' => function() { return current_user_can( 'edit_doctor' ); },
            ) );
        }

        foreach ( array( 'svcp_telefonos', 'svcp_clinicas' ) as $key ) {
            register_post_meta( 'doctor', $key, array(
                'type' => 'array', 'single' => true, 'show_in_rest' => array(
                    'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'maxItems' => 3 ),
                ),
                'sanitize_callback' => function( $values ) {
                    if ( ! is_array( $values ) ) { return array(); }
                    $values = array_slice( array_map( function( $value ) { return is_scalar( $value ) ? sanitize_text_field( $value ) : ''; }, $values ), 0, 3 );
                    return array_values( array_filter( $values, function( $value ) { return '' !== trim( $value ); } ) );
                },
                'auth_callback' => function() { return current_user_can( 'edit_doctor' ); },
            ) );
        }

        foreach ( array( 'svcp_membresia_estado', 'svcp_membresia_inicio', 'svcp_membresia_vencimiento', 'svcp_membresia_ultimo_pago', 'svcp_membresia_metodo_pago', 'svcp_membresia_referencia', 'svcp_membresia_monto' ) as $key ) {
            register_post_meta( 'doctor', $key, array(
                'type' => 'string', 'single' => true, 'show_in_rest' => false,
                'auth_callback' => function() { return current_user_can( 'manage_options' ); },
            ) );
        }
        register_post_meta( 'doctor', 'svcp_membresia_activa', array(
            'type' => 'boolean', 'single' => true, 'default' => false, 'show_in_rest' => false,
            'auth_callback' => function() { return current_user_can( 'manage_options' ); },
        ) );
    }

    public function agregar_metabox() {
        add_meta_box( 'svcp_info_doctor', __( 'Ficha del Doctor', 'svcp-directorio' ), array( $this, 'render_metabox' ), 'doctor', 'normal', 'high' );
        if ( current_user_can( 'manage_options' ) ) {
            add_meta_box( 'svcp_membresia_doctor', __( 'Membresía', 'svcp-directorio' ), array( $this, 'render_metabox_membresia' ), 'doctor', 'side', 'high' );
            add_meta_box( 'svcp_cuenta_doctor', __( 'Cuenta del Doctor', 'svcp-directorio' ), array( $this, 'render_metabox_cuenta' ), 'doctor', 'side', 'default' );
        }
    }

    public function render_metabox( $post ) {
        wp_nonce_field( 'svcp_guardar_campos', 'svcp_campos_nonce' );
        echo '<p class="description">' . esc_html__( 'Especialidad: Coloproctología. La región se selecciona en el panel de Regiones.', 'svcp-directorio' ) . '</p>';
        echo '<table class="form-table">';
        $telefonos = get_post_meta( $post->ID, 'svcp_telefonos', true );
        if ( ! is_array( $telefonos ) || empty( $telefonos ) ) { $telefonos = array( get_post_meta( $post->ID, 'svcp_telefono', true ) ); }
        $clinicas = get_post_meta( $post->ID, 'svcp_clinicas', true );
        if ( ! is_array( $clinicas ) || empty( $clinicas ) ) { $clinicas = array( get_post_meta( $post->ID, 'svcp_clinica', true ) ); }
        echo '<tr><th><label>Teléfonos de contacto *</label></th><td>';
        for ( $i = 0; $i < 3; $i++ ) {
            printf( '<p><input type="tel" class="widefat" name="svcp_telefonos[]" value="%1$s" placeholder="%2$s" %3$s></p>',
                esc_attr( $telefonos[ $i ] ?? '' ), esc_attr( 0 === $i ? 'Teléfono principal' : 'Teléfono adicional (opcional)' ), 0 === $i ? 'required' : ''
            );
        }
        echo '<p class="description">Máximo 3 teléfonos. El primero es obligatorio.</p></td></tr>';
        echo '<tr><th><label>Clínicas u hospitales *</label></th><td>';
        for ( $i = 0; $i < 3; $i++ ) {
            printf( '<p><input type="text" class="widefat" name="svcp_clinicas[]" value="%1$s" placeholder="%2$s" %3$s></p>',
                esc_attr( $clinicas[ $i ] ?? '' ), esc_attr( 0 === $i ? 'Clínica u hospital principal' : 'Clínica u hospital adicional (opcional)' ), 0 === $i ? 'required' : ''
            );
        }
        echo '<p class="description">Máximo 3 clínicas. La primera es obligatoria.</p></td></tr>';
        foreach ( self::campos_editables_por_doctor() as $key => $config ) {
            list( $label, $tipo, $requerido ) = $config;
            $valor = get_post_meta( $post->ID, $key, true );
            printf(
                '<tr><th><label for="%1$s">%2$s%5$s</label></th><td><input type="%3$s" id="%1$s" name="%1$s" value="%4$s" class="widefat" %6$s /></td></tr>',
                esc_attr( $key ), esc_html( $label ), esc_attr( $tipo ), esc_attr( $valor ), $requerido ? ' *' : '', $requerido ? 'required' : ''
            );
        }
        echo '</table>';
        echo '<p class="description">' . esc_html__( 'La fotografía debe ser cuadrada (formato 1:1). El sistema rechazará imágenes con otra proporción.', 'svcp-directorio' ) . '</p>';
    }

    public function render_metabox_cuenta( $post ) {
        wp_nonce_field( 'svcp_guardar_cuenta_doctor', 'svcp_cuenta_nonce' );
        $usuario_id = absint( get_post_meta( $post->ID, 'svcp_usuario_id', true ) );
        $usuarios = get_users( array(
            'role'    => 'doctor_svcp',
            'orderby' => 'display_name',
            'order'   => 'ASC',
        ) );
        ?>
        <p class="description"><?php esc_html_e( 'Asocia esta ficha con la cuenta WordPress del doctor. Esto permite que el doctor gestione su propia ficha desde el panel.', 'svcp-directorio' ); ?></p>
        <?php if ( $usuario_id && ( $usuario = get_user_by( 'id', $usuario_id ) ) ) : ?>
            <div style="padding:10px;background:#f6f7f7;border:1px solid #dcdcde;margin:10px 0;">
                <strong><?php echo esc_html( $usuario->display_name ); ?></strong><br>
                <span><?php echo esc_html( $usuario->user_email ); ?></span><br>
                <small><?php esc_html_e( 'Rol:', 'svcp-directorio' ); ?> <?php echo esc_html( implode( ', ', $usuario->roles ) ); ?></small>
            </div>
            <select name="svcp_usuario_id" class="widefat">
                <option value="0"><?php esc_html_e( 'Desvincular cuenta', 'svcp-directorio' ); ?></option>
                <?php foreach ( $usuarios as $usuario_option ) : ?>
                    <option value="<?php echo esc_attr( $usuario_option->ID ); ?>" <?php selected( $usuario_id, $usuario_option->ID ); ?>><?php echo esc_html( $usuario_option->display_name . ' — ' . $usuario_option->user_email ); ?></option>
                <?php endforeach; ?>
            </select>
        <?php else : ?>
            <select name="svcp_usuario_id" class="widefat">
                <option value="0"><?php esc_html_e( 'Sin cuenta asociada', 'svcp-directorio' ); ?></option>
                <?php foreach ( $usuarios as $usuario ) : ?>
                    <option value="<?php echo esc_attr( $usuario->ID ); ?>"><?php echo esc_html( $usuario->display_name . ' — ' . $usuario->user_email ); ?></option>
                <?php endforeach; ?>
            </select>
            <p style="margin-top:10px;">
                <?php
                $correo_ficha = get_post_meta( $post->ID, 'svcp_correo', true );
                $puede_crear = ! empty( $correo_ficha ) && is_email( $correo_ficha );
                ?>
                <?php if ( $puede_crear ) : ?>
                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=svcp_crear_cuenta_doctor&doctor_id=' . $post->ID ), 'svcp_crear_cuenta_doctor_' . $post->ID ) ); ?>" class="button button-primary">
                        <?php esc_html_e( 'Crear cuenta del doctor', 'svcp-directorio' ); ?>
                    </a>
                    <br><small><?php esc_html_e( 'Se utilizará el correo de la ficha y se enviará al doctor un enlace para establecer su contraseña.', 'svcp-directorio' ); ?></small>
                <?php else : ?>
                    <span class="description"><?php esc_html_e( 'Guarda primero un correo válido en la ficha para poder crear la cuenta.', 'svcp-directorio' ); ?></span>
                <?php endif; ?>
            </p>
        <?php endif; ?>
        <?php
    }

    public function render_metabox_membresia( $post ) {
        wp_nonce_field( 'svcp_guardar_membresia', 'svcp_membresia_nonce' );
        $estado = SVCP_Membresia::obtener_estado( $post->ID );
        $inicio = get_post_meta( $post->ID, 'svcp_membresia_inicio', true );
        $vencimiento = get_post_meta( $post->ID, 'svcp_membresia_vencimiento', true );
        $ultimo_pago = get_post_meta( $post->ID, 'svcp_membresia_ultimo_pago', true );
        $metodo_pago = get_post_meta( $post->ID, 'svcp_membresia_metodo_pago', true );
        $referencia = get_post_meta( $post->ID, 'svcp_membresia_referencia', true );
        $monto = get_post_meta( $post->ID, 'svcp_membresia_monto', true );
        ?>
        <p><strong><?php esc_html_e( 'Estado actual:', 'svcp-directorio' ); ?></strong><br><?php echo esc_html( SVCP_Membresia::estados()[ $estado ] ?? $estado ); ?></p>
        <p><label for="svcp_membresia_estado_manual"><strong><?php esc_html_e( 'Estado administrativo', 'svcp-directorio' ); ?></strong></label>
        <select name="svcp_membresia_estado_manual" id="svcp_membresia_estado_manual" class="widefat">
            <option value=""><?php esc_html_e( 'Automático por fecha', 'svcp-directorio' ); ?></option>
            <option value="suspendida" <?php selected( $estado, SVCP_Membresia::ESTADO_SUSPENDIDA ); ?>><?php esc_html_e( 'Suspendida', 'svcp-directorio' ); ?></option>
        </select></p>
        <?php foreach ( array(
            'svcp_membresia_inicio' => array( 'Inicio', 'date', $inicio ),
            'svcp_membresia_vencimiento' => array( 'Vencimiento', 'date', $vencimiento ),
            'svcp_membresia_ultimo_pago' => array( 'Último pago', 'date', $ultimo_pago ),
            'svcp_membresia_metodo_pago' => array( 'Método de pago', 'text', $metodo_pago ),
            'svcp_membresia_referencia' => array( 'Referencia', 'text', $referencia ),
            'svcp_membresia_monto' => array( 'Monto', 'text', $monto ),
        ) as $key => $field ) : ?>
            <p><label for="<?php echo esc_attr( $key ); ?>"><strong><?php echo esc_html( $field[0] ); ?></strong></label>
            <input type="<?php echo esc_attr( $field[1] ); ?>" class="widefat" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $field[2] ); ?>"></p>
        <?php endforeach; ?>
        <p class="description"><?php printf( esc_html__( '“Por vencer” se calcula automáticamente cuando faltan %d días o menos. “Suspendida” es manual.', 'svcp-directorio' ), SVCP_Membresia::DIAS_POR_VENCER ); ?></p>
        <?php
    }

    /**
     * Crea la cuenta WordPress del doctor desde la ficha administrativa.
     * La contraseña nunca se conoce ni se almacena por el administrador: WordPress
     * envía al usuario un enlace para establecerla.
     */
    public function crear_cuenta_doctor() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No tienes permisos para realizar esta acción.', 'svcp-directorio' ) );
        }

        $doctor_id = isset( $_GET['doctor_id'] ) ? absint( $_GET['doctor_id'] ) : 0;
        if ( ! $doctor_id || 'doctor' !== get_post_type( $doctor_id ) ) {
            wp_die( esc_html__( 'La ficha del doctor no es válida.', 'svcp-directorio' ) );
        }

        check_admin_referer( 'svcp_crear_cuenta_doctor_' . $doctor_id );

        $existente = absint( get_post_meta( $doctor_id, 'svcp_usuario_id', true ) );
        if ( $existente && get_user_by( 'id', $existente ) ) {
            wp_safe_redirect( add_query_arg( 'svcp_cuenta', 'ya_existia', get_edit_post_link( $doctor_id, 'url' ) ) );
            exit;
        }

        $correo = sanitize_email( get_post_meta( $doctor_id, 'svcp_correo', true ) );
        $nombre = sanitize_text_field( get_the_title( $doctor_id ) );

        if ( ! is_email( $correo ) ) {
            wp_safe_redirect( add_query_arg( 'svcp_cuenta', 'correo_invalido', get_edit_post_link( $doctor_id, 'url' ) ) );
            exit;
        }

        $usuario_existente = get_user_by( 'email', $correo );
        if ( $usuario_existente ) {
            $roles = (array) $usuario_existente->roles;
            if ( in_array( 'doctor_svcp', $roles, true ) ) {
                update_post_meta( $doctor_id, 'svcp_usuario_id', $usuario_existente->ID );
                wp_update_post( array( 'ID' => $doctor_id, 'post_author' => $usuario_existente->ID ) );
                wp_safe_redirect( add_query_arg( 'svcp_cuenta', 'asociada', get_edit_post_link( $doctor_id, 'url' ) ) );
                exit;
            }

            wp_safe_redirect( add_query_arg( 'svcp_cuenta', 'correo_en_uso', get_edit_post_link( $doctor_id, 'url' ) ) );
            exit;
        }

        // Evita nombres de usuario inválidos y genera uno único.
        $base = sanitize_user( remove_accents( strtolower( $nombre ) ), true );
        $base = preg_replace( '/[^a-z0-9._-]+/', '.', $base );
        $base = trim( $base, '.-_ ' );
        if ( '' === $base ) {
            $base = 'doctor';
        }

        $username = $base;
        $suffix = 2;
        while ( username_exists( $username ) ) {
            $username = $base . $suffix;
            $suffix++;
        }

        $user_id = wp_insert_user( array(
            'user_login'   => $username,
            'user_email'   => $correo,
            'display_name' => $nombre,
            'first_name'   => $nombre,
            'role'         => 'doctor_svcp',
            'user_pass'    => wp_generate_password( 32, true, true ),
        ) );

        if ( is_wp_error( $user_id ) ) {
            wp_safe_redirect( add_query_arg( 'svcp_cuenta', 'error', get_edit_post_link( $doctor_id, 'url' ) ) );
            exit;
        }

        update_post_meta( $doctor_id, 'svcp_usuario_id', $user_id );
        wp_update_post( array( 'ID' => $doctor_id, 'post_author' => $user_id ) );

        // Envía el enlace oficial de WordPress para que el doctor establezca su contraseña.
        if ( function_exists( 'wp_new_user_notification' ) ) {
            wp_new_user_notification( $user_id, null, 'user' );
        }

        wp_safe_redirect( add_query_arg( 'svcp_cuenta', 'creada', get_edit_post_link( $doctor_id, 'url' ) ) );
        exit;
    }

    public function guardar_metabox( $post_id ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
        if ( wp_is_post_revision( $post_id ) ) { return; }
        if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }

        if ( isset( $_POST['svcp_campos_nonce'] ) && wp_verify_nonce( $_POST['svcp_campos_nonce'], 'svcp_guardar_campos' ) ) {
            foreach ( self::campos_editables_por_doctor() as $key => $config ) {
                if ( isset( $_POST[ $key ] ) ) {
                    $sanitizer = $config[3];
                    $valor = wp_unslash( $_POST[ $key ] );
                    update_post_meta( $post_id, $key, call_user_func( $sanitizer, $valor ) );
                }
            }
            foreach ( array( 'telefonos' => 'svcp_telefono', 'clinicas' => 'svcp_clinica' ) as $plural => $legacy_key ) {
                $values = isset( $_POST[ 'svcp_' . $plural ] ) && is_array( $_POST[ 'svcp_' . $plural ] )
                    ? array_slice( array_map( function( $value ) { return is_scalar( $value ) ? sanitize_text_field( $value ) : ''; }, wp_unslash( $_POST[ 'svcp_' . $plural ] ) ), 0, 3 )
                    : array();
                $values = array_values( array_filter( $values, function( $value ) { return '' !== trim( $value ); } ) );
                update_post_meta( $post_id, 'svcp_' . $plural, $values );
                update_post_meta( $post_id, $legacy_key, $values[0] ?? '' );
            }
        }

        if ( current_user_can( 'manage_options' ) && isset( $_POST['svcp_cuenta_nonce'] ) && wp_verify_nonce( $_POST['svcp_cuenta_nonce'], 'svcp_guardar_cuenta_doctor' ) ) {
            $usuario_id = isset( $_POST['svcp_usuario_id'] ) ? absint( $_POST['svcp_usuario_id'] ) : 0;
            if ( $usuario_id ) {
                $usuario = get_user_by( 'id', $usuario_id );
                if ( $usuario && in_array( 'doctor_svcp', (array) $usuario->roles, true ) ) {
                    update_post_meta( $post_id, 'svcp_usuario_id', $usuario_id );
                    // Mantener post_author sincronizado ayuda a compatibilidad con WordPress.
                    if ( (int) get_post_field( 'post_author', $post_id ) !== $usuario_id ) {
                        remove_action( 'save_post_doctor', array( $this, 'guardar_metabox' ) );
                        wp_update_post( array( 'ID' => $post_id, 'post_author' => $usuario_id ) );
                        add_action( 'save_post_doctor', array( $this, 'guardar_metabox' ) );
        add_action( 'admin_post_svcp_crear_cuenta_doctor', array( $this, 'crear_cuenta_doctor' ) );
                    }
                }
            } else {
                delete_post_meta( $post_id, 'svcp_usuario_id' );
            }
        }

        if ( current_user_can( 'manage_options' ) && isset( $_POST['svcp_membresia_nonce'] ) && wp_verify_nonce( $_POST['svcp_membresia_nonce'], 'svcp_guardar_membresia' ) ) {
            $estado_manual = isset( $_POST['svcp_membresia_estado_manual'] ) ? sanitize_key( wp_unslash( $_POST['svcp_membresia_estado_manual'] ) ) : '';
            if ( SVCP_Membresia::ESTADO_SUSPENDIDA === $estado_manual ) { update_post_meta( $post_id, 'svcp_membresia_estado', SVCP_Membresia::ESTADO_SUSPENDIDA ); }
            else { delete_post_meta( $post_id, 'svcp_membresia_estado' ); }
            foreach ( array( 'svcp_membresia_inicio', 'svcp_membresia_vencimiento', 'svcp_membresia_ultimo_pago', 'svcp_membresia_metodo_pago', 'svcp_membresia_referencia', 'svcp_membresia_monto' ) as $key ) {
                if ( isset( $_POST[ $key ] ) ) { update_post_meta( $post_id, $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) ); }
            }
            if ( ! empty( $_POST['svcp_membresia_vencimiento'] ) ) { update_post_meta( $post_id, 'svcp_membresia_activa', true ); }
        }
    }
}
