<?php
/**
 * Panel frontend del doctor.
 * Shortcode: [svcp_panel_doctor]
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SVCP_Panel_Doctor {
    private static $instancia = null;
    private $errores = array();
    private $exito = false;

    public static function instance() {
        if ( null === self::$instancia ) { self::$instancia = new self(); }
        return self::$instancia;
    }

    private function __construct() {
        add_shortcode( 'svcp_panel_doctor', array( $this, 'render_shortcode' ) );
        add_action( 'init', array( $this, 'procesar_envio' ) );
    }

    private function obtener_doctor_actual() {
        $user = wp_get_current_user();
        if ( ! $user || ! $user->exists() || ! in_array( 'doctor_svcp', (array) $user->roles, true ) ) {
            return 0;
        }

        // 1) Asociación explícita: es la fuente de verdad.
        $posts = get_posts( array(
            'post_type'      => 'doctor',
            'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_key'       => 'svcp_usuario_id',
            'meta_value'     => $user->ID,
        ) );
        if ( ! empty( $posts ) ) {
            return (int) $posts[0];
        }

        // 2) Compatibilidad: fichas creadas por el registro público con post_author.
        $posts = get_posts( array(
            'post_type'      => 'doctor',
            'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
            'author'         => $user->ID,
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ) );
        if ( ! empty( $posts ) ) {
            update_post_meta( (int) $posts[0], 'svcp_usuario_id', $user->ID );
            return (int) $posts[0];
        }

        // 3) Compatibilidad con fichas antiguas creadas por un administrador:
        // si el correo de la ficha coincide con el correo de la cuenta del doctor,
        // asociamos automáticamente esa ficha una sola vez.
        $posts = get_posts( array(
            'post_type'      => 'doctor',
            'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_key'       => 'svcp_correo',
            'meta_value'     => $user->user_email,
        ) );
        if ( ! empty( $posts ) ) {
            update_post_meta( (int) $posts[0], 'svcp_usuario_id', $user->ID );
            return (int) $posts[0];
        }

        return 0;
    }

    public function procesar_envio() {
        if ( ! isset( $_POST['svcp_panel_nonce'] ) || ! wp_verify_nonce( $_POST['svcp_panel_nonce'], 'svcp_actualizar_panel' ) ) {
            return;
        }

        if ( ! is_user_logged_in() ) { return; }

        $post_id = $this->obtener_doctor_actual();
        $user = wp_get_current_user();

        // El permiso del panel se determina por la asociación real entre la
        // cuenta y la ficha. No dependemos exclusivamente de una capability
        // personalizada, ya que instalaciones existentes pueden conservar
        // capacidades antiguas del rol.
        $usuario_es_doctor = $user && $user->exists() && in_array( 'doctor_svcp', (array) $user->roles, true );
        $usuario_asociado = $post_id ? absint( get_post_meta( $post_id, 'svcp_usuario_id', true ) ) : 0;
        $es_autor = $post_id ? ( (int) get_post_field( 'post_author', $post_id ) === (int) $user->ID ) : false;
        $es_propietario = $usuario_asociado === (int) $user->ID || ( ! $usuario_asociado && $es_autor );

        if ( ! $post_id || ! $usuario_es_doctor || ! $es_propietario ) {
            $this->errores[] = __( 'No tienes permisos para modificar tu cuenta.', 'svcp-directorio' );
            return;
        }

        $nombre    = sanitize_text_field( wp_unslash( $_POST['svcp_nombre'] ?? '' ) );
        $correo    = sanitize_email( wp_unslash( $_POST['svcp_correo'] ?? '' ) );
        $telefono  = sanitize_text_field( wp_unslash( $_POST['svcp_telefono'] ?? '' ) );
        $whatsapp  = sanitize_text_field( wp_unslash( $_POST['svcp_whatsapp'] ?? '' ) );
        $clinica   = sanitize_text_field( wp_unslash( $_POST['svcp_clinica'] ?? '' ) );
        $direccion = sanitize_text_field( wp_unslash( $_POST['svcp_direccion'] ?? '' ) );
        $ciudad    = sanitize_text_field( wp_unslash( $_POST['svcp_ciudad'] ?? '' ) );
        $region_id = absint( $_POST['svcp_region'] ?? 0 );
        $facebook  = esc_url_raw( wp_unslash( $_POST['svcp_facebook'] ?? '' ) );
        $instagram = esc_url_raw( wp_unslash( $_POST['svcp_instagram'] ?? '' ) );
        $twitter   = esc_url_raw( wp_unslash( $_POST['svcp_twitter'] ?? '' ) );
        $linkedin  = esc_url_raw( wp_unslash( $_POST['svcp_linkedin'] ?? '' ) );
        $sitio_web = esc_url_raw( wp_unslash( $_POST['svcp_sitio_web'] ?? '' ) );

        if ( empty( $nombre ) || empty( $correo ) || empty( $telefono ) || empty( $clinica ) || empty( $ciudad ) || ! $region_id ) {
            $this->errores[] = __( 'Completa todos los campos obligatorios.', 'svcp-directorio' );
            return;
        }

        if ( ! is_email( $correo ) ) {
            $this->errores[] = __( 'El correo no es válido.', 'svcp-directorio' );
            return;
        }

        $region = get_term( $region_id, 'region' );
        if ( ! $region || is_wp_error( $region ) ) {
            $this->errores[] = __( 'La región seleccionada no es válida.', 'svcp-directorio' );
            return;
        }

        $user = wp_get_current_user();
        $existing = email_exists( $correo );
        if ( $existing && (int) $existing !== (int) $user->ID ) {
            $this->errores[] = __( 'Ese correo ya pertenece a otra cuenta.', 'svcp-directorio' );
            return;
        }

        if ( ! empty( $_FILES['svcp_foto']['name'] ) ) {
            $validation = $this->validar_fotografia( $_FILES['svcp_foto'] );
            if ( is_wp_error( $validation ) ) {
                $this->errores[] = $validation->get_error_message();
                return;
            }
        }

        $updated = wp_update_post( array(
            'ID'         => $post_id,
            'post_title' => $nombre,
        ), true );

        if ( is_wp_error( $updated ) ) {
            $this->errores[] = __( 'No se pudo actualizar la ficha.', 'svcp-directorio' );
            return;
        }

        $campos = array(
            'svcp_telefono'  => $telefono,
            'svcp_whatsapp'  => $whatsapp,
            'svcp_clinica'   => $clinica,
            'svcp_direccion' => $direccion,
            'svcp_ciudad'    => $ciudad,
            'svcp_correo'    => $correo,
            'svcp_facebook'  => $facebook,
            'svcp_instagram' => $instagram,
            'svcp_twitter'   => $twitter,
            'svcp_linkedin'  => $linkedin,
            'svcp_sitio_web' => $sitio_web,
        );

        foreach ( $campos as $key => $value ) {
            update_post_meta( $post_id, $key, $value );
        }

        wp_set_object_terms( $post_id, array( $region_id ), 'region' );

        // El correo de contacto de la ficha se mantiene sincronizado con el usuario.
        if ( $user->user_email !== $correo ) {
            $user_update = wp_update_user( array( 'ID' => $user->ID, 'user_email' => $correo ) );
            if ( is_wp_error( $user_update ) ) {
                $this->errores[] = __( 'La ficha fue actualizada, pero no se pudo actualizar el correo de la cuenta.', 'svcp-directorio' );
            }
        }

        wp_update_user( array( 'ID' => $user->ID, 'display_name' => $nombre ) );

        // Una ficha ya publicada vuelve a revisión cuando el propio doctor
        // modifica sus datos. La información queda guardada, pero requiere
        // una nueva aprobación administrativa antes de volver al directorio.
        if ( class_exists( 'SVCP_Aprobacion' ) && SVCP_Aprobacion::PUBLICADA === SVCP_Aprobacion::obtener_estado( $post_id ) ) {
            SVCP_Aprobacion::establecer( $post_id, SVCP_Aprobacion::REVISION_REQUERIDA, true );
        }

        if ( ! empty( $_FILES['svcp_foto']['name'] ) ) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            $adjunto_id = media_handle_upload( 'svcp_foto', $post_id );
            if ( is_wp_error( $adjunto_id ) ) {
                $this->errores[] = __( 'La ficha se actualizó, pero no se pudo guardar la fotografía.', 'svcp-directorio' );
            } else {
                set_post_thumbnail( $post_id, $adjunto_id );
            }
        }

        if ( empty( $this->errores ) ) {
            $this->exito = true;
            // Avisar al equipo administrativo con el mismo diseño institucional
            // utilizado para los reportes de pago y los registros nuevos.
            if ( class_exists( 'SVCP_Correos' ) ) {
                SVCP_Correos::notificar_admin( 'perfil_modificado', array(
                    'nombre' => $nombre,
                    'correo' => $correo,
                    'clinica' => $clinica,
                    'doctor_id' => $post_id,
                    'url' => admin_url( 'edit.php?post_type=doctor&page=svcp-aprobaciones' ),
                ) );
                SVCP_Correos::enviar( $correo, 'perfil_modificado', array(
                    'nombre' => $nombre,
                    'url_panel' => home_url( '/' ),
                ) );
            }
        }
    }

    private function validar_fotografia( $file ) {
        if ( ! isset( $file['error'], $file['tmp_name'], $file['size'] ) || UPLOAD_ERR_OK !== (int) $file['error'] ) {
            return new WP_Error( 'svcp_foto_error', __( 'No se pudo procesar la fotografía.', 'svcp-directorio' ) );
        }

        if ( (int) $file['size'] > 5 * MB_IN_BYTES ) {
            return new WP_Error( 'svcp_foto_size', __( 'La fotografía no puede superar los 5 MB.', 'svcp-directorio' ) );
        }

        $info = @getimagesize( $file['tmp_name'] );
        if ( ! $info || empty( $info[0] ) || empty( $info[1] ) ) {
            return new WP_Error( 'svcp_foto_type', __( 'La fotografía no es válida.', 'svcp-directorio' ) );
        }

        $allowed = array( 'image/jpeg', 'image/png', 'image/webp' );
        $mime = isset( $info['mime'] ) ? $info['mime'] : '';
        if ( ! in_array( $mime, $allowed, true ) ) {
            return new WP_Error( 'svcp_foto_mime', __( 'La fotografía debe ser JPG, PNG o WebP.', 'svcp-directorio' ) );
        }

        $ratio = (float) $info[0] / (float) $info[1];
        if ( abs( $ratio - 1 ) > 0.03 && abs( $ratio - ( 4 / 3 ) ) > 0.03 ) {
            return new WP_Error( 'svcp_foto_ratio', __( 'La fotografía debe tener formato 1:1 o 4:3.', 'svcp-directorio' ) );
        }

        return true;
    }

    private function campo( $key, $label, $value, $type = 'text', $required = false ) {
        printf(
            '<div class="svcp-panel-field"><label for="%1$s">%2$s%3$s</label><input type="%4$s" id="%1$s" name="%1$s" value="%5$s"%6$s></div>',
            esc_attr( $key ),
            esc_html( $label ),
            $required ? ' *' : '',
            esc_attr( $type ),
            esc_attr( $value ),
            $required ? ' required' : ''
        );
    }

    public function render_shortcode() {
        if ( ! is_user_logged_in() ) {
            return '<p class="svcp-panel-notice">' . esc_html__( 'Debes iniciar sesión para acceder a tu perfil.', 'svcp-directorio' ) . '</p>';
        }

        $post_id = $this->obtener_doctor_actual();
        if ( ! $post_id ) {
            return '<p class="svcp-panel-notice">' . esc_html__( 'No encontramos una ficha de doctor asociada a tu cuenta.', 'svcp-directorio' ) . '</p>';
        }

        $user = wp_get_current_user();
        $estado = SVCP_Membresia::obtener_estado( $post_id );
        $estados = SVCP_Membresia::estados();
        $estado_aprobacion = class_exists( 'SVCP_Aprobacion' ) ? SVCP_Aprobacion::obtener_estado( $post_id ) : '';
        $estados_aprobacion = class_exists( 'SVCP_Aprobacion' ) ? SVCP_Aprobacion::estados() : array();
        $vencimiento = get_post_meta( $post_id, 'svcp_membresia_vencimiento', true );
        $regiones = get_terms( array( 'taxonomy' => 'region', 'hide_empty' => false ) );
        $terms = get_the_terms( $post_id, 'region' );
        $region_id = ( ! is_wp_error( $terms ) && ! empty( $terms ) ) ? (int) $terms[0]->term_id : 0;
        $region_nombre = ( ! is_wp_error( $terms ) && ! empty( $terms ) ) ? $terms[0]->name : '';
        $public_url = get_permalink( $post_id );
        $password_url = wp_lostpassword_url( get_permalink() );
        $foto_url = get_the_post_thumbnail_url( $post_id, 'medium' );
        $estado_label = $estados[ $estado ] ?? $estado;

        $pago_url = apply_filters( 'svcp_url_portal_pagos', home_url( '/reportar-pago/' ) );
        $portal_url = apply_filters( 'svcp_url_portal_medico', get_permalink() );
        $pagos_realizados_url = $portal_url . '#pagos-realizados';
        $membership_label = $estado_label;
        $approval_label = $estado_aprobacion ? ( $estados_aprobacion[ $estado_aprobacion ] ?? $estado_aprobacion ) : __( 'En revisión', 'svcp-directorio' );
        $vigencia_label = $vencimiento ? date_i18n( get_option( 'date_format' ), strtotime( $vencimiento ) ) : __( 'No registrada', 'svcp-directorio' );
        $correo_acceso = $user->user_email;
        $foto_inicial = function_exists( 'mb_substr' ) ? mb_substr( get_the_title( $post_id ), 0, 1 ) : substr( get_the_title( $post_id ), 0, 1 );

        ob_start();
        ?>
        <div class="svcp-portal-layout">
            <aside class="svcp-portal-sidebar" aria-label="<?php esc_attr_e( 'Navegación del portal', 'svcp-directorio' ); ?>">
                <a class="svcp-portal-brand" href="<?php echo esc_url( $portal_url ); ?>"><strong>SVCP</strong><span><?php esc_html_e( 'Portal del médico', 'svcp-directorio' ); ?></span></a>
                <nav class="svcp-portal-nav">
                    <a class="is-active" href="<?php echo esc_url( $portal_url ); ?>"><span aria-hidden="true">♙</span><?php esc_html_e( 'Mi perfil', 'svcp-directorio' ); ?></a>
                    <a href="<?php echo esc_url( $pago_url ); ?>"><span aria-hidden="true">▤</span><?php esc_html_e( 'Membresía y pago', 'svcp-directorio' ); ?></a>
                    <div class="svcp-portal-subnav"><a href="<?php echo esc_url( $pagos_realizados_url ); ?>" class="svcp-portal-subnav-link"><span aria-hidden="true">↳</span><?php esc_html_e( 'Pagos realizados', 'svcp-directorio' ); ?></a></div>
                    <a href="<?php echo esc_url( get_post_type_archive_link( 'doctor' ) ?: home_url( '/directorio/' ) ); ?>"><span aria-hidden="true">◎</span><?php esc_html_e( 'Ver directorio', 'svcp-directorio' ); ?></a>
                </nav>
                <a class="svcp-portal-logout" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>"><span aria-hidden="true">⇥</span><?php esc_html_e( 'Cerrar sesión', 'svcp-directorio' ); ?></a>
            </aside>

            <main class="svcp-portal-main">
                <header class="svcp-portal-topbar">
                    <div><span class="svcp-panel-eyebrow"><?php esc_html_e( 'Portal del médico', 'svcp-directorio' ); ?></span><h1><?php esc_html_e( 'Mi perfil', 'svcp-directorio' ); ?></h1></div>
                    <div class="svcp-portal-user" aria-label="<?php echo esc_attr( $user->display_name ); ?>">
                        <?php if ( $foto_url ) : ?><img src="<?php echo esc_url( $foto_url ); ?>" alt=""><?php else : ?><span><?php echo esc_html( strtoupper( $foto_inicial ) ); ?></span><?php endif; ?>
                    </div>
                </header>

                <div class="svcp-portal-content">
                    <?php if ( $this->exito ) : ?><div class="svcp-panel-success" role="status"><?php esc_html_e( 'Tus cambios fueron recibidos. Si la ficha estaba publicada, quedará pendiente de revisión administrativa.', 'svcp-directorio' ); ?></div><?php endif; ?>
                    <?php if ( ! empty( $this->errores ) ) : ?><div class="svcp-panel-errors" role="alert"><?php foreach ( $this->errores as $error ) : ?><p><?php echo esc_html( $error ); ?></p><?php endforeach; ?></div><?php endif; ?>

                    <section class="svcp-portal-stats" aria-label="<?php esc_attr_e( 'Resumen de cuenta', 'svcp-directorio' ); ?>">
                        <article class="svcp-portal-stat"><span class="svcp-portal-stat-icon">✧</span><div><small><?php esc_html_e( 'Membresía', 'svcp-directorio' ); ?></small><strong class="<?php echo esc_attr( 'svcp-stat-' . sanitize_html_class( $estado ) ); ?>"><?php echo esc_html( $membership_label ); ?></strong></div></article>
                        <article class="svcp-portal-stat"><span class="svcp-portal-stat-icon">♢</span><div><small><?php esc_html_e( 'Ficha', 'svcp-directorio' ); ?></small><strong><?php echo esc_html( $approval_label ); ?></strong></div></article>
                        <article class="svcp-portal-stat"><span class="svcp-portal-stat-icon">◷</span><div><small><?php esc_html_e( 'Vigencia', 'svcp-directorio' ); ?></small><strong><?php echo esc_html( $vigencia_label ); ?></strong></div></article>
                        <article class="svcp-portal-stat"><span class="svcp-portal-stat-icon">✉</span><div><small><?php esc_html_e( 'Correo de acceso', 'svcp-directorio' ); ?></small><strong><?php echo esc_html( $correo_acceso ); ?></strong></div></article>
                    </section>

                    <form class="svcp-panel-form svcp-portal-workspace" method="post" enctype="multipart/form-data">
                        <?php wp_nonce_field( 'svcp_actualizar_panel', 'svcp_panel_nonce' ); ?>
                        <div class="svcp-portal-profile-column">
                            <section class="svcp-portal-card svcp-portal-preview-card">
                                <h2><?php esc_html_e( 'Vista previa', 'svcp-directorio' ); ?></h2><p class="svcp-portal-muted"><?php esc_html_e( 'Así te ven los visitantes.', 'svcp-directorio' ); ?></p>
                                <div class="svcp-portal-preview-photo"><?php if ( $foto_url ) : ?><img src="<?php echo esc_url( $foto_url ); ?>" alt="<?php echo esc_attr( get_the_title( $post_id ) ); ?>"><?php else : ?><span><?php echo esc_html( strtoupper( $foto_inicial ) ); ?></span><?php endif; ?></div>
                                <h3><?php echo esc_html( get_the_title( $post_id ) ); ?></h3><p class="svcp-portal-region"><?php echo esc_html( strtoupper( $region_nombre ) ); ?><?php echo $this->valor_visible( get_post_meta( $post_id, 'svcp_ciudad', true ) ) ? ' · ' . esc_html( strtoupper( get_post_meta( $post_id, 'svcp_ciudad', true ) ) ) : ''; ?></p>
                                <?php if ( $public_url ) : ?><a class="svcp-portal-outline-button" href="<?php echo esc_url( $public_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Ver ficha pública', 'svcp-directorio' ); ?></a><?php endif; ?>
                            </section>
                            <section class="svcp-portal-card svcp-portal-photo-card"><h2><?php esc_html_e( 'Fotografía de perfil', 'svcp-directorio' ); ?></h2><p class="svcp-portal-muted"><?php esc_html_e( 'JPG, PNG o WebP · máx. 5 MB · formato 1:1 o 4:3', 'svcp-directorio' ); ?></p>
                                <label class="svcp-portal-upload" for="svcp_foto"><span aria-hidden="true">⇧</span><span><?php esc_html_e( 'Seleccionar nueva fotografía', 'svcp-directorio' ); ?></span><input type="file" id="svcp_foto" name="svcp_foto" accept="image/jpeg,image/png,image/webp"></label>
                            </section>
                        </div>

                        <div class="svcp-portal-form-column">
                            <section class="svcp-portal-card"><div class="svcp-panel-section-heading svcp-portal-section-heading"><h2><?php esc_html_e( 'Datos profesionales y de contacto', 'svcp-directorio' ); ?></h2><p><?php esc_html_e( 'Estos datos aparecen en tu ficha pública.', 'svcp-directorio' ); ?></p></div>
                                <div class="svcp-panel-form-grid">
                                    <?php $this->campo( 'svcp_nombre', 'Nombre y apellido', get_the_title( $post_id ), 'text', true ); $this->campo( 'svcp_correo', 'Correo de contacto', get_post_meta( $post_id, 'svcp_correo', true ) ?: $user->user_email, 'email', true ); $this->campo( 'svcp_telefono', 'Teléfono', get_post_meta( $post_id, 'svcp_telefono', true ), 'text', true ); $this->campo( 'svcp_whatsapp', 'WhatsApp', get_post_meta( $post_id, 'svcp_whatsapp', true ) ); $this->campo( 'svcp_clinica', 'Clínica u Hospital', get_post_meta( $post_id, 'svcp_clinica', true ), 'text', true ); $this->campo( 'svcp_ciudad', 'Ciudad', get_post_meta( $post_id, 'svcp_ciudad', true ), 'text', true ); ?>
                                </div>
                                <div class="svcp-panel-field"><label for="svcp_direccion"><?php esc_html_e( 'Dirección', 'svcp-directorio' ); ?></label><textarea id="svcp_direccion" name="svcp_direccion" rows="3"><?php echo esc_textarea( get_post_meta( $post_id, 'svcp_direccion', true ) ); ?></textarea></div>
                                <div class="svcp-panel-field"><label for="svcp_region"><?php esc_html_e( 'Región', 'svcp-directorio' ); ?> *</label><select id="svcp_region" name="svcp_region" required><option value=""><?php esc_html_e( 'Selecciona…', 'svcp-directorio' ); ?></option><?php foreach ( $regiones as $region ) : ?><option value="<?php echo esc_attr( $region->term_id ); ?>" <?php selected( $region_id, $region->term_id ); ?>><?php echo esc_html( $region->name ); ?></option><?php endforeach; ?></select></div>
                            </section>
                            <section class="svcp-portal-card"><div class="svcp-panel-section-heading svcp-portal-section-heading"><h2><?php esc_html_e( 'Redes y sitio web', 'svcp-directorio' ); ?></h2><p><?php esc_html_e( 'Agrega únicamente enlaces profesionales.', 'svcp-directorio' ); ?></p></div><div class="svcp-panel-form-grid"><?php $this->campo( 'svcp_facebook', 'Facebook', get_post_meta( $post_id, 'svcp_facebook', true ), 'url' ); $this->campo( 'svcp_instagram', 'Instagram', get_post_meta( $post_id, 'svcp_instagram', true ), 'url' ); $this->campo( 'svcp_twitter', 'Twitter / X', get_post_meta( $post_id, 'svcp_twitter', true ), 'url' ); $this->campo( 'svcp_linkedin', 'LinkedIn', get_post_meta( $post_id, 'svcp_linkedin', true ), 'url' ); $this->campo( 'svcp_sitio_web', 'Sitio web', get_post_meta( $post_id, 'svcp_sitio_web', true ), 'url' ); ?></div></section>
                            <div class="svcp-portal-form-actions"><a class="svcp-portal-outline-button" href="<?php echo esc_url( $portal_url ); ?>"><?php esc_html_e( 'Cancelar', 'svcp-directorio' ); ?></a><button type="submit" class="svcp-btn-registro"><?php esc_html_e( 'Guardar cambios', 'svcp-directorio' ); ?></button></div>
                            <p class="svcp-portal-password-link"><a href="<?php echo esc_url( $password_url ); ?>"><?php esc_html_e( 'Cambiar o recuperar contraseña', 'svcp-directorio' ); ?></a></p>
                        </div>
                    </form>
                    <?php
                    $deuda_usd = max(0, (float) get_post_meta($post_id, 'svcp_membresia_saldo_pendiente_usd', true));
                    $tasa_hoy = class_exists('SVCP_Tasa_BCV') ? SVCP_Tasa_BCV::obtener(current_time('Y-m-d'), false) : new WP_Error('sin_tasa','');
                    $deuda_bs_hoy = (!is_wp_error($tasa_hoy) && $deuda_usd > 0) ? $deuda_usd * (float)$tasa_hoy['tasa'] : 0;
                    $pagos_doctor = get_posts(array('post_type'=>'svcp_pago','post_status'=>'private','posts_per_page'=>50,'orderby'=>'date','order'=>'DESC','fields'=>'ids','meta_query'=>array(array('key'=>'_svcp_doctor_id','value'=>$post_id,'compare'=>'='))));
                    ?>
                    <section id="pagos-realizados" class="svcp-portal-card svcp-portal-payments" style="margin-top:22px">
                        <div class="svcp-panel-section-heading"><h2>Mis pagos y estado de cuenta</h2><p>Consulta tus reportes, pagos aprobados y comprobantes.</p></div>
                        <div class="svcp-portal-tabs" role="tablist" aria-label="Información de pagos">
                            <button type="button" class="svcp-portal-tab is-active" role="tab" aria-selected="true" aria-controls="svcp-tab-estado" data-svcp-tab="svcp-tab-estado">Estado de cuenta</button>
                            <button type="button" class="svcp-portal-tab" role="tab" aria-selected="false" aria-controls="svcp-tab-pagos" data-svcp-tab="svcp-tab-pagos">Mis pagos</button>
                        </div>
                        <div id="svcp-tab-estado" class="svcp-portal-tab-panel is-active" role="tabpanel">
                            <div><small>Saldo pendiente en divisa</small><strong style="display:block;font-size:22px;color:<?php echo $deuda_usd>0?'#b45309':'#087f72'; ?>">USD <?php echo esc_html(number_format_i18n($deuda_usd,4)); ?></strong></div>
                            <div><small>Equivalente al día de hoy</small><strong style="display:block;font-size:22px"> <?php echo is_wp_error($tasa_hoy) ? 'Tasa no disponible' : 'Bs. '.esc_html(number_format_i18n($deuda_bs_hoy,2)); ?></strong><?php if(!is_wp_error($tasa_hoy)&&$deuda_usd>0): ?><small>Tasa BCV <?php echo esc_html(number_format_i18n((float)$tasa_hoy['tasa'],4)); ?> · Vigencia <?php echo esc_html($tasa_hoy['fecha_vigencia']); ?></small><?php endif; ?></div>
                        </div>
                        </div>
                        <div id="svcp-tab-pagos" class="svcp-portal-tab-panel" role="tabpanel" hidden>
                        <?php if($pagos_doctor): ?><div class="svcp-portal-table-wrap"><table class="widefat striped"><thead><tr><th>Fecha</th><th>Referencia</th><th>Monto recibido</th><th>Equivalente USD</th><th>Estado</th><th>Comprobante</th></tr></thead><tbody>
                        <?php foreach($pagos_doctor as $pago_id): $pestado=get_post_meta($pago_id,'_svcp_estado',true); $pmonto=(float)get_post_meta($pago_id,'_svcp_monto',true); $pusd=(float)get_post_meta($pago_id,'_svcp_pago_equivalente_usd',true); $recibo=wp_nonce_url(admin_url('admin-post.php?action=svcp_recibo_pdf&pago_id='.$pago_id),'svcp_recibo_'.$pago_id); ?>
                        <tr><td><?php echo esc_html(get_post_meta($pago_id,'_svcp_fecha_pago',true)); ?></td><td><?php echo esc_html(get_post_meta($pago_id,'_svcp_referencia',true)); ?></td><td>Bs. <?php echo esc_html(number_format_i18n($pmonto,2)); ?></td><td>USD <?php echo esc_html(number_format_i18n($pusd,4)); ?></td><td><?php echo esc_html(ucfirst($pestado)); ?></td><td><?php if('aprobado'===$pestado): ?><a class="button" href="<?php echo esc_url($recibo); ?>">Descargar PDF</a><?php else: ?>—<?php endif; ?></td></tr>
                        <?php endforeach; ?></tbody></table></div><?php else: ?><p class="svcp-portal-empty">Aún no tienes pagos registrados.</p><?php endif; ?>
                        </div>
                        <p class="svcp-portal-payment-action"><a class="svcp-portal-outline-button" href="<?php echo esc_url( $pago_url ); ?>">Reportar un nuevo pago</a></p>
                    </section>
                    <script>
                    (function(){
                        var root=document.getElementById('pagos-realizados');
                        if(!root || root.dataset.tabsReady==='1') return;
                        root.dataset.tabsReady='1';
                        var tabs=root.querySelectorAll('[data-svcp-tab]');
                        tabs.forEach(function(tab){
                            tab.addEventListener('click',function(){
                                tabs.forEach(function(item){
                                    var active=item===tab;
                                    item.classList.toggle('is-active',active);
                                    item.setAttribute('aria-selected',active?'true':'false');
                                });
                                root.querySelectorAll('.svcp-portal-tab-panel').forEach(function(panel){
                                    var active=panel.id===tab.getAttribute('data-svcp-tab');
                                    panel.classList.toggle('is-active',active);
                                    panel.hidden=!active;
                                });
                            });
                        });
                    })();
                    </script>
                </div>
            </main>
        </div>
        <?php
        return ob_get_clean();
    }

    private function valor_visible( $value ) {
        return '' !== trim( (string) $value );
    }
}
