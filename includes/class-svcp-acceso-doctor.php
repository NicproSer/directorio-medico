<?php
/**
 * Acceso frontend para médicos.
 * Shortcode: [svcp_login_doctor]
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SVCP_Acceso_Doctor {
    private static $instancia = null;

    public static function instance() {
        if ( null === self::$instancia ) {
            self::$instancia = new self();
        }
        return self::$instancia;
    }

    private function __construct() {
        add_shortcode( 'svcp_login_doctor', array( $this, 'render_login' ) );
        add_filter( 'login_redirect', array( $this, 'redirigir_doctor_despues_login' ), 10, 3 );
    }

    /**
     * Busca la página que contiene [svcp_panel_doctor].
     */
    private function obtener_url_panel() {
        $paginas = get_pages(
            array(
                'post_status' => 'publish',
                'number'      => -1,
            )
        );

        foreach ( $paginas as $pagina ) {
            if ( has_shortcode( $pagina->post_content, 'svcp_panel_doctor' ) ) {
                return get_permalink( $pagina->ID );
            }
        }

        return home_url( '/' );
    }

    /**
     * Busca la página pública que contiene el formulario de registro.
     */
    private function obtener_url_registro() {
        $paginas = get_pages( array( 'post_status' => 'publish', 'number' => -1 ) );
        foreach ( $paginas as $pagina ) {
            if ( has_shortcode( $pagina->post_content, 'svcp_registro_doctor' ) ) {
                return get_permalink( $pagina->ID );
            }
        }
        return '';
    }

    /**
     * Redirige únicamente a usuarios con rol de médico.
     */
    public function redirigir_doctor_despues_login( $redirect_to, $requested_redirect_to, $user ) {
        if ( ! $user instanceof WP_User || ! $user->exists() ) {
            return $redirect_to;
        }

        if ( in_array( 'doctor_svcp', (array) $user->roles, true ) ) {
            return $this->obtener_url_panel();
        }

        return $redirect_to;
    }

    public function render_login() {
        if ( is_user_logged_in() ) {
            $user = wp_get_current_user();

            if ( in_array( 'doctor_svcp', (array) $user->roles, true ) ) {
                return '<div class="svcp-login-box">'
                    . '<p class="svcp-login-success">'
                    . esc_html__( 'Ya has iniciado sesión como médico.', 'svcp-directorio' )
                    . '</p>'
                    . '<div class="svcp-login-actions">'
                    . '<a class="svcp-btn-login" href="' . esc_url( $this->obtener_url_panel() ) . '">'
                    . esc_html__( 'Ir a Mi Perfil', 'svcp-directorio' )
                    . '</a>'
                    . '<a class="svcp-btn-logout" href="' . esc_url( wp_logout_url( $this->obtener_url_panel() ) ) . '">'
                    . esc_html__( 'Cerrar sesión', 'svcp-directorio' )
                    . '</a>'
                    . '</div>'
                    . '</div>';
            }

            return '<p class="svcp-panel-notice">'
                . esc_html__( 'Ya tienes una sesión iniciada.', 'svcp-directorio' )
                . '</p>';
        }

        $redirect = $this->obtener_url_panel();

        ob_start();
        ?>
        <div class="svcp-login-box">
            <h2><?php esc_html_e( 'Acceso de médicos', 'svcp-directorio' ); ?></h2>
            <p class="svcp-login-description">
                <?php esc_html_e( 'Ingresa con el correo electrónico o usuario de tu cuenta.', 'svcp-directorio' ); ?>
            </p>

            <?php
            wp_login_form(
                array(
                    'echo'           => true,
                    'redirect'       => $redirect,
                    'label_username' => __( 'Correo electrónico o usuario', 'svcp-directorio' ),
                    'label_password' => __( 'Contraseña', 'svcp-directorio' ),
                    'label_remember' => __( 'Recordarme', 'svcp-directorio' ),
                    'label_log_in'   => __( 'Iniciar sesión', 'svcp-directorio' ),
                    'remember'       => true,
                )
            );
            ?>

            <div class="svcp-login-links">
                <a href="<?php echo esc_url( wp_lostpassword_url( $redirect ) ); ?>">
                    <?php esc_html_e( '¿Olvidaste tu contraseña?', 'svcp-directorio' ); ?>
                </a>
            </div>

            <?php $url_registro = $this->obtener_url_registro(); ?>
            <?php if ( $url_registro ) : ?>
                <div class="svcp-login-register-cta">
                    <p><?php esc_html_e( '¿Eres médico y todavía no tienes una cuenta?', 'svcp-directorio' ); ?></p>
                    <a class="svcp-btn-register" href="<?php echo esc_url( $url_registro ); ?>">
                        <?php esc_html_e( 'Crear cuenta de doctor', 'svcp-directorio' ); ?>
                    </a>
                </div>
            <?php else : ?>
                <div class="svcp-login-register-cta">
                    <p><?php esc_html_e( '¿Eres médico y todavía no tienes una cuenta? Completa el siguiente formulario para registrarte.', 'svcp-directorio' ); ?></p>
                    <?php if ( class_exists( 'SVCP_Registro' ) ) { echo SVCP_Registro::instance()->render_shortcode(); } ?>
                </div>
            <?php endif; ?>
        </div>
        <?php

        return ob_get_clean();
    }
}
