<?php
/**
 * Auto-registro público de doctores.
 * Shortcode: [svcp_registro_doctor]
 *
 * Al enviar el formulario:
 *   1. Crea un usuario WP con rol 'doctor_svcp' (login y contraseña que él elige).
 *   2. Crea la ficha (CPT doctor) con post_status = 'pending' (revisión del admin)
 *      y svcp_membresia_activa = false (no aparece en el directorio hasta que
 *      el admin la active, aunque luego la publique).
 *   3. Notifica por email al administrador.
 *
 * Nota: mantenemos "pending" como resguardo extra, aunque la visibilidad
 * real ya depende del meta de membresía. Así el admin también revisa
 * que los datos no sean spam antes de publicar.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SVCP_Registro {

    private static $instancia = null;
    private $errores = array();
    private $exito   = false;

    public static function instance() {
        if ( null === self::$instancia ) {
            self::$instancia = new self();
        }
        return self::$instancia;
    }

    private function __construct() {
        add_shortcode( 'svcp_registro_doctor', array( $this, 'render_shortcode' ) );
        add_action( 'init', array( $this, 'procesar_envio' ) );
    }

    public function procesar_envio() {
        if ( ! isset( $_POST['svcp_registro_nonce'] ) || ! wp_verify_nonce( $_POST['svcp_registro_nonce'], 'svcp_registro' ) ) {
            return;
        }

        // Honeypot anti-spam: campo oculto que un humano nunca llena.
        if ( ! empty( $_POST['svcp_web'] ) ) {
            return;
        }

        $nombre    = sanitize_text_field( $_POST['svcp_nombre'] ?? '' );
        $correo    = sanitize_email( $_POST['svcp_correo'] ?? '' );
        $clave     = $_POST['svcp_clave'] ?? '';
        $clinica   = sanitize_text_field( $_POST['svcp_clinica'] ?? '' );
        $telefono  = sanitize_text_field( $_POST['svcp_telefono'] ?? '' );
        $whatsapp  = sanitize_text_field( $_POST['svcp_whatsapp'] ?? '' );
        $direccion = sanitize_text_field( $_POST['svcp_direccion'] ?? '' );
        $ciudad    = sanitize_text_field( $_POST['svcp_ciudad'] ?? '' );
        $region_id = absint( $_POST['svcp_region'] ?? 0 );
        $facebook  = esc_url_raw( $_POST['svcp_facebook'] ?? '' );
        $instagram = esc_url_raw( $_POST['svcp_instagram'] ?? '' );
        $twitter   = esc_url_raw( $_POST['svcp_twitter'] ?? '' );
        $linkedin  = esc_url_raw( $_POST['svcp_linkedin'] ?? '' );
        $sitio_web = esc_url_raw( $_POST['svcp_sitio_web'] ?? '' );

        if ( empty( $nombre ) || empty( $correo ) || empty( $clave ) || empty( $clinica ) || empty( $telefono ) || empty( $ciudad ) || empty( $region_id ) ) {
            $this->errores[] = __( 'Completa todos los campos obligatorios.', 'svcp-directorio' );
            return;
        }
        if ( ! is_email( $correo ) ) {
            $this->errores[] = __( 'El correo no es válido.', 'svcp-directorio' );
            return;
        }
        if ( email_exists( $correo ) ) {
            $this->errores[] = __( 'Ya existe una cuenta con ese correo.', 'svcp-directorio' );
            return;
        }
        if ( strlen( $clave ) < 8 ) {
            $this->errores[] = __( 'La contraseña debe tener al menos 8 caracteres.', 'svcp-directorio' );
            return;
        }

        // La imagen es opcional. WordPress valida el tipo MIME real al procesar la subida;
        // el recorte y la proporción se aplican visualmente en las tarjetas del directorio.

        // 1. Crear usuario.
        $username = sanitize_user( current( explode( '@', $correo ) ) . '_' . wp_generate_password( 4, false ) );
        $user_id  = wp_create_user( $username, $clave, $correo );
        if ( is_wp_error( $user_id ) ) {
            $this->errores[] = $user_id->get_error_message();
            return;
        }
        wp_update_user( array( 'ID' => $user_id, 'display_name' => $nombre, 'role' => 'doctor_svcp' ) );

        // 2. Crear la ficha del doctor.
        $post_id = wp_insert_post(
            array(
                'post_type'   => 'doctor',
                'post_title'  => $nombre,
                'post_status' => 'pending', // el admin revisa y publica.
                'post_author' => $user_id,
            )
        );

        if ( is_wp_error( $post_id ) || ! $post_id ) {
            $this->errores[] = __( 'No se pudo crear el perfil, contacta al administrador.', 'svcp-directorio' );
            return;
        }

        update_post_meta( $post_id, 'svcp_clinica', $clinica );
        update_post_meta( $post_id, 'svcp_telefono', $telefono );
        update_post_meta( $post_id, 'svcp_whatsapp', $whatsapp );
        update_post_meta( $post_id, 'svcp_direccion', $direccion );
        update_post_meta( $post_id, 'svcp_ciudad', $ciudad );
        update_post_meta( $post_id, 'svcp_correo', $correo );
        update_post_meta( $post_id, 'svcp_facebook', $facebook );
        update_post_meta( $post_id, 'svcp_instagram', $instagram );
        update_post_meta( $post_id, 'svcp_twitter', $twitter );
        update_post_meta( $post_id, 'svcp_linkedin', $linkedin );
        update_post_meta( $post_id, 'svcp_sitio_web', $sitio_web );
        update_post_meta( $post_id, 'svcp_membresia_activa', false ); // explícito: nace moroso hasta que se le active.
        if ( class_exists( 'SVCP_Aprobacion' ) ) { update_post_meta( $post_id, SVCP_Aprobacion::META, SVCP_Aprobacion::PENDIENTE_REVISION ); }
        update_post_meta( $post_id, 'svcp_usuario_id', $user_id ); // asociación explícita usuario ↔ ficha.
        wp_set_object_terms( $post_id, array( $region_id ), 'region' );

        // 3. Foto opcional.
        if ( ! empty( $_FILES['svcp_foto']['name'] ) ) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            $adjunto_id = media_handle_upload( 'svcp_foto', $post_id );
            if ( ! is_wp_error( $adjunto_id ) ) {
                set_post_thumbnail( $post_id, $adjunto_id );
            }
        }

        // Notificación al administrador en cada registro completado correctamente.
        $datos_notificacion = array(
            'nombre' => $nombre,
            'correo' => $correo,
            'clinica' => $clinica,
            'doctor_id' => $post_id,
            'url' => admin_url( 'edit.php?post_type=doctor&page=svcp-aprobaciones' ),
        );
        if ( class_exists( 'SVCP_Correos' ) ) {
            SVCP_Correos::notificar_admin( 'doctor_nuevo', $datos_notificacion );
        } else {
            $admin_email = sanitize_email( get_option( 'admin_email' ) );
            if ( is_email( $admin_email ) ) {
                $asunto = 'Nuevo registro de doctor pendiente de revisión · SVCP';
                $mensaje = "Se ha creado una nueva cuenta de doctor.\n\nNombre: {$nombre}\nCorreo: {$correo}\nClínica: {$clinica}\n\nRevisar: " . admin_url( 'edit.php?post_type=doctor&page=svcp-aprobaciones' );
                wp_mail( $admin_email, $asunto, $mensaje );
            }
        }

        if ( class_exists( 'SVCP_Correos' ) ) {
            SVCP_Correos::enviar( $correo, 'registro', array( 'nombre' => $nombre, 'url_panel' => home_url( '/admin-miperfil/' ) ) );
        } else {
            wp_mail( $correo, 'Gracias por registrarte en la SVCP', 'Hola ' . $nombre . ', gracias por registrarte. Tu ficha está pendiente de revisión.' );
        }

        $this->exito = true;
    }

    public function render_shortcode() {
        ob_start();

        if ( $this->exito ) {
            echo '<p class="svcp-registro-exito">' . esc_html__( '¡Gracias por registrarte! Tu ficha fue creada y está pendiente de revisión. Ya puedes iniciar sesión para revisar tu perfil y continuar con el proceso de membresía.', 'svcp-directorio' ) . '</p>';
            return ob_get_clean();
        }

        $regiones = get_terms( array( 'taxonomy' => 'region', 'hide_empty' => false ) );
        ?>
        <form class="svcp-form svcp-form-registro svcp-auth-register" method="post" enctype="multipart/form-data">
            <header class="svcp-auth-heading svcp-register-heading">
                <img class="svcp-auth-logo" src="https://svcp86.org/wp-content/uploads/2021/10/SVCP_Logo__ICO.png" alt="SVCP" loading="eager">
                <h2><?php esc_html_e( 'Crear cuenta', 'svcp-directorio' ); ?></h2>
            </header>
            <?php wp_nonce_field( 'svcp_registro', 'svcp_registro_nonce' ); ?>
            <input type="text" name="svcp_web" style="display:none" tabindex="-1" autocomplete="off" />

            <?php if ( ! empty( $this->errores ) ) : ?>
                <div class="svcp-form-errores">
                    <?php foreach ( $this->errores as $error ) : ?>
                        <p><?php echo esc_html( $error ); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="svcp-form-row">
                <label><?php esc_html_e( 'Nombre y Apellido *', 'svcp-directorio' ); ?></label>
                <input type="text" name="svcp_nombre" required />
            </div>
            <div class="svcp-form-row">
                <label><?php esc_html_e( 'Clínica u Hospital *', 'svcp-directorio' ); ?></label>
                <input type="text" name="svcp_clinica" required />
            </div>
            <div class="svcp-form-row">
                <label><?php esc_html_e( 'Teléfono *', 'svcp-directorio' ); ?></label>
                <input type="text" name="svcp_telefono" required />
            </div>
            <div class="svcp-form-row">
                <label><?php esc_html_e( 'Correo *', 'svcp-directorio' ); ?></label>
                <input type="email" name="svcp_correo" required />
            </div>
            <div class="svcp-form-row">
                <label><?php esc_html_e( 'Contraseña *', 'svcp-directorio' ); ?></label>
                <input type="password" name="svcp_clave" minlength="8" required />
            </div>
            <div class="svcp-form-row">
                <label><?php esc_html_e( 'WhatsApp (opcional)', 'svcp-directorio' ); ?></label>
                <input type="text" name="svcp_whatsapp" />
            </div>
            <div class="svcp-form-row">
                <label><?php esc_html_e( 'Dirección (opcional)', 'svcp-directorio' ); ?></label>
                <input type="text" name="svcp_direccion" />
            </div>
            <div class="svcp-form-row">
                <label><?php esc_html_e( 'Ciudad *', 'svcp-directorio' ); ?></label>
                <input type="text" name="svcp_ciudad" required />
            </div>
            <div class="svcp-form-row">
                <label><?php esc_html_e( 'Región *', 'svcp-directorio' ); ?></label>
                <select name="svcp_region" required>
                    <option value=""><?php esc_html_e( 'Selecciona…', 'svcp-directorio' ); ?></option>
                    <?php foreach ( $regiones as $region ) : ?>
                        <option value="<?php echo esc_attr( $region->term_id ); ?>"><?php echo esc_html( $region->name ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="svcp-form-row">
                <label><?php esc_html_e( 'Facebook (opcional)', 'svcp-directorio' ); ?></label>
                <input type="url" name="svcp_facebook" />
            </div>
            <div class="svcp-form-row">
                <label><?php esc_html_e( 'Instagram (opcional)', 'svcp-directorio' ); ?></label>
                <input type="url" name="svcp_instagram" />
            </div>
            <div class="svcp-form-row">
                <label><?php esc_html_e( 'Twitter / X (opcional)', 'svcp-directorio' ); ?></label>
                <input type="url" name="svcp_twitter" />
            </div>
            <div class="svcp-form-row">
                <label><?php esc_html_e( 'LinkedIn (opcional)', 'svcp-directorio' ); ?></label>
                <input type="url" name="svcp_linkedin" />
            </div>
            <div class="svcp-form-row">
                <label><?php esc_html_e( 'Sitio web (opcional)', 'svcp-directorio' ); ?></label>
                <input type="url" name="svcp_sitio_web" />
            </div>
            <div class="svcp-form-row">
                <label><?php esc_html_e( 'Fotografía de perfil (opcional)', 'svcp-directorio' ); ?></label>
                <input type="file" name="svcp_foto" accept="image/*" />
                <small><?php esc_html_e( 'La imagen se ajustará automáticamente al formato de las tarjetas del directorio.', 'svcp-directorio' ); ?></small>
            </div>

            <p class="svcp-auth-required-note"><?php esc_html_e( '* Campos obligatorios. Tu ficha quedará pendiente de revisión administrativa.', 'svcp-directorio' ); ?></p>
            <button type="submit" class="svcp-btn-registro"><?php esc_html_e( 'Crear cuenta', 'svcp-directorio' ); ?></button>
        </form>
        <?php
        return ob_get_clean();
    }
}
