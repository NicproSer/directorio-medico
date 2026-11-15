<?php
/**
 * Registra el Custom Post Type "doctor".
 *
 * Reglas clave del negocio:
 * - Un doctor creado desde el formulario público nace en estado "pending"
 *   (WordPress ya trae este estado nativo, no hay que crear uno custom).
 * - Desde v0.8.0 la visibilidad pública requiere membresía vigente y
 *   aprobación interna en estado "publicada". El flujo de aprobación es
 *   independiente del post_status de WordPress.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SVCP_CPT_Doctor {

    private static $instancia = null;

    public static function instance() {
        if ( null === self::$instancia ) {
            self::$instancia = new self();
        }
        return self::$instancia;
    }

    private function __construct() {
        add_action( 'init', array( __CLASS__, 'registrar_cpt' ) );
    }

    /**
     * Registro del CPT. Es un método estático porque también se llama
     * directamente en la activación del plugin (antes de que 'init' corra).
     */
    public static function registrar_cpt() {

        $labels = array(
            'name'                  => __( 'Doctores', 'svcp-directorio' ),
            'singular_name'         => __( 'Doctor', 'svcp-directorio' ),
            'menu_name'             => __( 'Directorio Médico', 'svcp-directorio' ),
            'add_new'               => __( 'Añadir doctor', 'svcp-directorio' ),
            'add_new_item'          => __( 'Añadir nuevo doctor', 'svcp-directorio' ),
            'edit_item'             => __( 'Editar doctor', 'svcp-directorio' ),
            'new_item'              => __( 'Nuevo doctor', 'svcp-directorio' ),
            'view_item'             => __( 'Ver doctor', 'svcp-directorio' ),
            'search_items'          => __( 'Buscar doctores', 'svcp-directorio' ),
            'not_found'             => __( 'No se encontraron doctores', 'svcp-directorio' ),
            'not_found_in_trash'    => __( 'No hay doctores en la papelera', 'svcp-directorio' ),
            'all_items'             => __( 'Todos los doctores', 'svcp-directorio' ),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'has_archive'        => 'directorio', // /directorio/
            'rewrite'            => array(
                'slug'       => 'directorio',
                'with_front' => false,
            ),
            'menu_icon'          => 'dashicons-groups',
            'menu_position'      => 20,
            // 'author' es clave: así el post_author = usuario WP del doctor,
            // y WordPress ya sabe restringir edición a "solo sus propios posts"
            // en combinación con el rol 'doctor_svcp' (ver class-svcp-roles.php).
            'supports'           => array( 'title', 'thumbnail', 'author', 'custom-fields' ),
            'show_in_rest'       => true, // Necesario si luego se consume por REST (headless futuro).
            'capability_type'    => array( 'doctor', 'doctors' ),
            'map_meta_cap'       => true,
            // El rol doctor_svcp no recibe publish_doctors, por lo que no puede
            // publicar ni despublicar fichas por su cuenta.
        );

        register_post_type( 'doctor', $args );
    }
}
