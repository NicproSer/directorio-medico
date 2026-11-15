<?php
/**
 * Roles y capacidades del Directorio Médico SVCP.
 *
 * El rol doctor_svcp solo puede trabajar con su propia ficha.
 * Las capacidades del CPT son específicas de "doctor" y no reutilizan
 * las capacidades genéricas de Entradas (posts).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SVCP_Roles {

    private static $instancia = null;

    private static $doctor_caps = array(
        'edit_doctor',
        'read_doctor',
        'edit_doctors',
        'edit_published_doctors',
    );

    private static $admin_caps = array(
        'edit_doctor',
        'read_doctor',
        'delete_doctor',
        'edit_doctors',
        'edit_others_doctors',
        'publish_doctors',
        'read_private_doctors',
        'delete_doctors',
        'delete_private_doctors',
        'delete_published_doctors',
        'delete_others_doctors',
        'edit_private_doctors',
        'edit_published_doctors',
    );

    public static function instance() {
        if ( null === self::$instancia ) {
            self::$instancia = new self();
        }
        return self::$instancia;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'limpiar_menu_admin' ) );
        add_action( 'pre_get_posts', array( $this, 'ver_solo_su_ficha' ) );
        add_filter( 'user_has_cap', array( $this, 'bloquear_edicion_ajena' ), 10, 4 );

        // Mantiene las capacidades actualizadas aunque el plugin se actualice
        // sin pasar nuevamente por el hook de activación.
        add_action( 'init', array( __CLASS__, 'asegurar_capacidades' ), 5 );
    }

    /**
     * Crea/actualiza el rol del doctor y las capacidades administrativas.
     */
    public static function crear_rol() {
        $role = get_role( 'doctor_svcp' );

        if ( ! $role ) {
            add_role(
                'doctor_svcp',
                __( 'Doctor (SVCP)', 'svcp-directorio' ),
                array(
                    'read' => true,
                )
            );
            $role = get_role( 'doctor_svcp' );
        }

        if ( $role ) {
            foreach ( self::$doctor_caps as $cap ) {
                $role->add_cap( $cap );
            }

            // El doctor nunca recibe capacidades para editar fichas ajenas,
            // publicar por su cuenta, borrar o administrar membresías.
            foreach ( array(
                'edit_others_doctors',
                'publish_doctors',
                'delete_doctors',
                'delete_private_doctors',
                'delete_published_doctors',
                'delete_others_doctors',
                'edit_private_doctors',
                'read_private_doctors',
            ) as $cap ) {
                $role->remove_cap( $cap );
            }
        }

        self::asegurar_capacidades();
    }

    /**
     * Los administradores necesitan las capacidades específicas del CPT.
     * WordPress no concede automáticamente capacidades personalizadas a
     * administrator como sí ocurre con las capacidades nativas de post.
     */
    public static function asegurar_capacidades() {
        $admin = get_role( 'administrator' );

        if ( ! $admin ) {
            return;
        }

        foreach ( self::$admin_caps as $cap ) {
            $admin->add_cap( $cap );
        }
    }

    public static function eliminar_rol() {
        remove_role( 'doctor_svcp' );
    }

    /**
     * En el listado del CPT, un doctor solo ve su propia ficha.
     */
    public function ver_solo_su_ficha( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return;
        }

        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

        if ( ! $screen || 'doctor' !== $screen->post_type ) {
            return;
        }

        $user = wp_get_current_user();

        if ( in_array( 'doctor_svcp', (array) $user->roles, true ) ) {
            $ids = get_posts( array(
                'post_type'      => 'doctor',
                'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_key'       => 'svcp_usuario_id',
                'meta_value'     => $user->ID,
            ) );

            // Compatibilidad con fichas antiguas cuyo autor sea el doctor.
            $query->set( 'post__in', ! empty( $ids ) ? $ids : array( -1 ) );
        }
    }

    /**
     * Doble protección: aunque un doctor intente abrir directamente la URL
     * de otra ficha, no obtiene capacidades sobre ese post.
     */
    public function bloquear_edicion_ajena( $allcaps, $caps, $args, $user ) {
        if ( ! in_array( 'doctor_svcp', (array) $user->roles, true ) ) {
            return $allcaps;
        }

        if (
            isset( $args[0], $args[2] ) &&
            in_array( $args[0], array( 'edit_post', 'delete_post', 'read_post' ), true )
        ) {
            $post = get_post( $args[2] );

            if ( $post && 'doctor' === $post->post_type ) {
                $asociado = absint( get_post_meta( $post->ID, 'svcp_usuario_id', true ) );
                $es_propietario = ( $asociado && $asociado === (int) $user->ID ) || ( ! $asociado && (int) $post->post_author === (int) $user->ID );
                if ( ! $es_propietario ) {
                    foreach ( $caps as $cap ) {
                        $allcaps[ $cap ] = false;
                    }
                }
            }
        }

        return $allcaps;
    }

    /**
     * Oculta menús que un doctor no necesita.
     */
    public function limpiar_menu_admin() {
        $user = wp_get_current_user();

        if ( ! in_array( 'doctor_svcp', (array) $user->roles, true ) ) {
            return;
        }

        remove_menu_page( 'edit.php' );
        remove_menu_page( 'edit-comments.php' );
        remove_menu_page( 'tools.php' );
    }
}
