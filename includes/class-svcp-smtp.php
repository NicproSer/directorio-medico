<?php
/**
 * Configuración SMTP del Directorio Médico SVCP.
 *
 * Usa el PHPMailer que WordPress ya carga para wp_mail(). No instala una
 * segunda librería ni reemplaza wp_mail(); solamente configura su transporte.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SVCP_SMTP {
    const OPTION = 'svcp_smtp_config';

    public static function instance() {
        static $instance = null;
        if ( null === $instance ) { $instance = new self(); }
        return $instance;
    }

    private function __construct() {
        add_action( 'phpmailer_init', array( $this, 'configurar_phpmailer' ) );
        add_action( 'wp_mail_failed', array( $this, 'registrar_error' ) );
        add_action( 'admin_menu', array( $this, 'registrar_menu' ) );
        add_action( 'admin_post_svcp_smtp_guardar', array( $this, 'guardar' ) );
        add_action( 'admin_post_svcp_smtp_probar', array( $this, 'probar' ) );
    }

    public static function defaults() {
        return array(
            'activo'       => 0,
            'host'         => '',
            'port'         => 587,
            'encryption'   => 'tls',
            'auth'         => 1,
            'username'     => '',
            'password'     => '',
            'from_email'   => '',
            'from_name'    => 'Sociedad Venezolana de Coloproctología',
            'pagos_cc_email' => '',
            'payment_bank' => '',
            'payment_account_type' => '',
            'payment_account_number' => '',
            'payment_holder' => '',
            'payment_document' => '',
            'payment_phone' => '',
            'payment_email' => '',
            'payment_instructions' => '',
        );
    }

    public static function config() {
        $config = get_option( self::OPTION, array() );
        return wp_parse_args( is_array( $config ) ? $config : array(), self::defaults() );
    }

    public function configurar_phpmailer( $phpmailer ) {
        $config = self::config();
        $const_password = defined( 'SVCP_SMTP_PASSWORD' ) ? (string) SVCP_SMTP_PASSWORD : '';

        if ( empty( $config['activo'] ) || empty( $config['host'] ) ) {
            return;
        }

        $password = $const_password !== '' ? $const_password : (string) $config['password'];

        try {
            $phpmailer->isSMTP();
            $phpmailer->Host = sanitize_text_field( $config['host'] );
            $phpmailer->Port = absint( $config['port'] ) ?: 587;
            $phpmailer->SMTPAuth = ! empty( $config['auth'] );
            $phpmailer->Username = sanitize_text_field( $config['username'] );
            $phpmailer->Password = $password;
            $phpmailer->SMTPAutoTLS = true;

            $encryption = sanitize_key( $config['encryption'] );
            if ( 'ssl' === $encryption ) {
                $phpmailer->SMTPSecure = 'ssl';
            } elseif ( 'tls' === $encryption ) {
                $phpmailer->SMTPSecure = 'tls';
            } else {
                $phpmailer->SMTPSecure = '';
                $phpmailer->SMTPAutoTLS = false;
            }

            if ( is_email( $config['from_email'] ) ) {
                $phpmailer->setFrom( $config['from_email'], $config['from_name'], false );
            }
        } catch ( Exception $e ) {
            set_transient( 'svcp_smtp_last_error', $e->getMessage(), HOUR_IN_SECONDS );
        }
    }

    public function registrar_error( $wp_error ) {
        if ( is_wp_error( $wp_error ) ) {
            set_transient( 'svcp_smtp_last_error', $wp_error->get_error_message(), HOUR_IN_SECONDS );
        }
    }

    public function registrar_menu() {
        add_submenu_page(
            'edit.php?post_type=doctor',
            __( 'Correo y SMTP', 'svcp-directorio' ),
            __( 'Correo / SMTP', 'svcp-directorio' ),
            'manage_options',
            'svcp-correo',
            array( $this, 'render' )
        );
    }

    public function guardar() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'No tienes permisos.', 'svcp-directorio' ) ); }
        check_admin_referer( 'svcp_smtp_guardar' );

        $actual = self::config();
        $password = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
        if ( '' === $password ) { $password = $actual['password']; }

        $config = array(
            'activo'     => ! empty( $_POST['activo'] ) ? 1 : 0,
            'host'       => isset( $_POST['host'] ) ? sanitize_text_field( wp_unslash( $_POST['host'] ) ) : '',
            'port'       => isset( $_POST['port'] ) ? absint( $_POST['port'] ) : 587,
            'encryption' => isset( $_POST['encryption'] ) ? sanitize_key( wp_unslash( $_POST['encryption'] ) ) : 'tls',
            'auth'       => ! empty( $_POST['auth'] ) ? 1 : 0,
            'username'   => isset( $_POST['username'] ) ? sanitize_text_field( wp_unslash( $_POST['username'] ) ) : '',
            'password'   => $password,
            'from_email' => isset( $_POST['from_email'] ) ? sanitize_email( wp_unslash( $_POST['from_email'] ) ) : '',
            'from_name'  => isset( $_POST['from_name'] ) ? sanitize_text_field( wp_unslash( $_POST['from_name'] ) ) : '',
            'pagos_cc_email' => isset( $_POST['pagos_cc_email'] ) ? sanitize_email( wp_unslash( $_POST['pagos_cc_email'] ) ) : '',
            'payment_bank' => isset( $_POST['payment_bank'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_bank'] ) ) : '',
            'payment_account_type' => isset( $_POST['payment_account_type'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_account_type'] ) ) : '',
            'payment_account_number' => isset( $_POST['payment_account_number'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_account_number'] ) ) : '',
            'payment_holder' => isset( $_POST['payment_holder'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_holder'] ) ) : '',
            'payment_document' => isset( $_POST['payment_document'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_document'] ) ) : '',
            'payment_phone' => isset( $_POST['payment_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_phone'] ) ) : '',
            'payment_email' => isset( $_POST['payment_email'] ) ? sanitize_email( wp_unslash( $_POST['payment_email'] ) ) : '',
            'payment_instructions' => isset( $_POST['payment_instructions'] ) ? sanitize_textarea_field( wp_unslash( $_POST['payment_instructions'] ) ) : '',
        );

        if ( ! in_array( $config['encryption'], array( 'tls', 'ssl', 'none' ), true ) ) { $config['encryption'] = 'tls'; }
        if ( ! empty( $config['pagos_cc_email'] ) && ! is_email( $config['pagos_cc_email'] ) ) { $config['pagos_cc_email'] = ''; }
        if ( ! empty( $config['payment_email'] ) && ! is_email( $config['payment_email'] ) ) { $config['payment_email'] = ''; }
        update_option( self::OPTION, $config, false );
        wp_safe_redirect( add_query_arg( array( 'post_type'=>'doctor', 'page'=>'svcp-correo', 'svcp_smtp'=>'saved' ), admin_url( 'edit.php' ) ) );
        exit;
    }

    public function probar() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'No tienes permisos.', 'svcp-directorio' ) ); }
        check_admin_referer( 'svcp_smtp_probar' );
        $usuario = wp_get_current_user();
        $destino = $usuario && is_email( $usuario->user_email ) ? $usuario->user_email : get_option( 'admin_email' );
        $config = self::config();
        $asunto = 'SVCP Directorio Médico — prueba de correo';
        $mensaje = "Este es un correo de prueba del SVCP Directorio Médico.\n\nSi recibes este mensaje, wp_mail() está utilizando correctamente la configuración de correo del sitio.\n\nServidor: " . ( $config['activo'] ? 'SMTP configurado' : 'transporte de WordPress' ) . "\nFecha: " . current_time( 'mysql' );
        $enviado = wp_mail( $destino, $asunto, $mensaje, array( 'Content-Type: text/plain; charset=UTF-8' ) );

        $args = array( 'post_type'=>'doctor', 'page'=>'svcp-correo', 'svcp_smtp'=>$enviado ? 'test_ok' : 'test_error' );
        wp_safe_redirect( add_query_arg( $args, admin_url( 'edit.php' ) ) );
        exit;
    }

    public function render() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'No tienes permisos.', 'svcp-directorio' ) ); }
        $config = self::config();
        $error = get_transient( 'svcp_smtp_last_error' );
        $estado = isset( $_GET['svcp_smtp'] ) ? sanitize_key( wp_unslash( $_GET['svcp_smtp'] ) ) : '';
        $password_const = defined( 'SVCP_SMTP_PASSWORD' );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Correo y SMTP', 'svcp-directorio' ); ?></h1>
            <p>Las notificaciones del directorio continúan utilizando <code>wp_mail()</code>. Al activar SMTP, WordPress entregará esos mensajes mediante el servidor SMTP indicado.</p>
            <?php if ( 'saved' === $estado ) : ?><div class="notice notice-success is-dismissible"><p>Configuración guardada.</p></div><?php endif; ?>
            <?php if ( 'test_ok' === $estado ) : ?><div class="notice notice-success is-dismissible"><p>Correo de prueba enviado a tu correo de usuario.</p></div><?php endif; ?>
            <?php if ( 'test_error' === $estado ) : ?><div class="notice notice-error is-dismissible"><p>No se pudo enviar el correo de prueba. Revisa la configuración.</p></div><?php endif; ?>
            <?php if ( $error ) : ?><div class="notice notice-error"><p><strong>Último error de correo:</strong> <?php echo esc_html( $error ); ?></p></div><?php endif; ?>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:800px;background:#fff;padding:20px;border:1px solid #dcdcde;margin-top:18px;">
                <input type="hidden" name="action" value="svcp_smtp_guardar">
                <?php wp_nonce_field( 'svcp_smtp_guardar' ); ?>
                <table class="form-table" role="presentation">
                    <tr><th scope="row">SMTP activo</th><td><label><input type="checkbox" name="activo" value="1" <?php checked( $config['activo'], 1 ); ?>> Usar SMTP para los correos del directorio</label></td></tr>
                    <tr><th scope="row"><label for="svcp_smtp_host">Servidor SMTP</label></th><td><input class="regular-text" id="svcp_smtp_host" name="host" value="<?php echo esc_attr( $config['host'] ); ?>" placeholder="smtp.tudominio.com"></td></tr>
                    <tr><th scope="row"><label for="svcp_smtp_port">Puerto</label></th><td><input type="number" min="1" max="65535" id="svcp_smtp_port" name="port" value="<?php echo esc_attr( $config['port'] ); ?>"></td></tr>
                    <tr><th scope="row"><label for="svcp_smtp_encryption">Cifrado</label></th><td><select id="svcp_smtp_encryption" name="encryption"><option value="tls" <?php selected( $config['encryption'], 'tls' ); ?>>TLS / STARTTLS</option><option value="ssl" <?php selected( $config['encryption'], 'ssl' ); ?>>SSL</option><option value="none" <?php selected( $config['encryption'], 'none' ); ?>>Sin cifrado</option></select></td></tr>
                    <tr><th scope="row">Autenticación</th><td><label><input type="checkbox" name="auth" value="1" <?php checked( $config['auth'], 1 ); ?>> El servidor requiere usuario y contraseña</label></td></tr>
                    <tr><th scope="row"><label for="svcp_smtp_username">Usuario SMTP</label></th><td><input class="regular-text" id="svcp_smtp_username" name="username" value="<?php echo esc_attr( $config['username'] ); ?>"></td></tr>
                    <tr><th scope="row"><label for="svcp_smtp_password">Contraseña SMTP</label></th><td><input class="regular-text" type="password" id="svcp_smtp_password" name="password" value="" autocomplete="new-password"><p class="description"><?php echo $password_const ? 'La contraseña está definida mediante SVCP_SMTP_PASSWORD en wp-config.php.' : 'Déjala vacía para conservar la contraseña guardada actualmente.'; ?></p></td></tr>
                    <tr><th scope="row"><label for="svcp_smtp_from_email">Correo remitente</label></th><td><input class="regular-text" type="email" id="svcp_smtp_from_email" name="from_email" value="<?php echo esc_attr( $config['from_email'] ); ?>"><p class="description">Preferiblemente usa una dirección del mismo dominio del sitio.</p></td></tr>
                    <tr><th scope="row"><label for="svcp_smtp_from_name">Nombre remitente</label></th><td><input class="regular-text" id="svcp_smtp_from_name" name="from_name" value="<?php echo esc_attr( $config['from_name'] ); ?>"></td></tr>
                    <tr><th scope="row"><label for="svcp_pagos_cc_email">Correo para copias de pagos</label></th><td><input class="regular-text" type="email" id="svcp_pagos_cc_email" name="pagos_cc_email" value="<?php echo esc_attr( $config['pagos_cc_email'] ); ?>" placeholder="tesoreria@svcp86.org"><p class="description">Recibirá una copia de las notificaciones cuando un médico reporte un pago. Es opcional.</p></td></tr>
                </table>

                <hr style="margin:24px 0">
                <h2>Datos de pago visibles para los médicos</h2>
                <p>Actualiza aquí las cuentas y las instrucciones que aparecerán en la sección «Membresía y pago» del portal.</p>
                <table class="form-table" role="presentation">
                    <tr><th scope="row"><label for="svcp_payment_bank">Banco</label></th><td><input class="regular-text" id="svcp_payment_bank" name="payment_bank" value="<?php echo esc_attr( $config['payment_bank'] ); ?>" placeholder="Nombre del banco"></td></tr>
                    <tr><th scope="row"><label for="svcp_payment_account_type">Tipo de cuenta</label></th><td><input class="regular-text" id="svcp_payment_account_type" name="payment_account_type" value="<?php echo esc_attr( $config['payment_account_type'] ); ?>" placeholder="Corriente / Ahorro"></td></tr>
                    <tr><th scope="row"><label for="svcp_payment_account_number">Número de cuenta</label></th><td><input class="regular-text" id="svcp_payment_account_number" name="payment_account_number" value="<?php echo esc_attr( $config['payment_account_number'] ); ?>"></td></tr>
                    <tr><th scope="row"><label for="svcp_payment_holder">Titular de la cuenta</label></th><td><input class="regular-text" id="svcp_payment_holder" name="payment_holder" value="<?php echo esc_attr( $config['payment_holder'] ); ?>"></td></tr>
                    <tr><th scope="row"><label for="svcp_payment_document">RIF / Cédula del titular</label></th><td><input class="regular-text" id="svcp_payment_document" name="payment_document" value="<?php echo esc_attr( $config['payment_document'] ); ?>"></td></tr>
                    <tr><th scope="row"><label for="svcp_payment_phone">Teléfono para pago móvil</label></th><td><input class="regular-text" id="svcp_payment_phone" name="payment_phone" value="<?php echo esc_attr( $config['payment_phone'] ); ?>"></td></tr>
                    <tr><th scope="row"><label for="svcp_payment_email">Correo asociado al pago</label></th><td><input class="regular-text" type="email" id="svcp_payment_email" name="payment_email" value="<?php echo esc_attr( $config['payment_email'] ); ?>"></td></tr>
                    <tr><th scope="row"><label for="svcp_payment_instructions">Instrucciones adicionales</label></th><td><textarea class="large-text" rows="4" id="svcp_payment_instructions" name="payment_instructions"><?php echo esc_textarea( $config['payment_instructions'] ); ?></textarea></td></tr>
                </table>
                <?php submit_button( 'Guardar configuración' ); ?>
            </form>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px;">
                <input type="hidden" name="action" value="svcp_smtp_probar">
                <?php wp_nonce_field( 'svcp_smtp_probar' ); ?>
                <?php submit_button( 'Enviar correo de prueba', 'secondary', 'submit', false ); ?>
            </form>

            <div style="max-width:800px;background:#f6f7f7;border:1px solid #dcdcde;padding:18px;margin-top:20px;">
                <h2 style="margin-top:0">Recomendaciones</h2>
                <ul style="list-style:disc;padding-left:20px;">
                    <li>Usa un buzón real del dominio, por ejemplo <code>directorio@tudominio.com</code>.</li>
                    <li>Configura SPF y DKIM en el DNS del dominio. DMARC también es recomendable.</li>
                    <li>Si tu proveedor ofrece contraseña de aplicación, úsala en lugar de la contraseña principal.</li>
                    <li>Para mayor seguridad puedes definir <code>SVCP_SMTP_PASSWORD</code> en <code>wp-config.php</code>; en ese caso la contraseña no se guarda en la configuración del plugin.</li>
                </ul>
            </div>
        </div>
        <?php
    }
}
