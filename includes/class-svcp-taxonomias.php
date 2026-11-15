<?php
/**
 * Taxonomía "region" para el directorio SVCP.
 * La especialidad es fija: Coloproctología.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SVCP_Taxonomias {
    private static $instancia = null;

    public static function regiones() {
        return array(
            'Region Capital',
            'Centro-Occidental',
            'De los Andes',
            'Zuliana',
            'De los Llanos',
            'Nor-Oriental',
            'Guayana',
            'Insular',
        );
    }

    public static function instance() {
        if ( null === self::$instancia ) { self::$instancia = new self(); }
        return self::$instancia;
    }

    private function __construct() {
        add_action( 'init', array( __CLASS__, 'registrar_taxonomias' ) );
    }

    public static function registrar_taxonomias() {
        register_taxonomy(
            'region',
            'doctor',
            array(
                'labels' => array(
                    'name'          => __( 'Regiones', 'svcp-directorio' ),
                    'singular_name' => __( 'Región', 'svcp-directorio' ),
                    'all_items'     => __( 'Todas las regiones', 'svcp-directorio' ),
                    'edit_item'     => __( 'Editar región', 'svcp-directorio' ),
                    'search_items'  => __( 'Buscar regiones', 'svcp-directorio' ),
                ),
                'public'       => true,
                'hierarchical' => true,
                'show_in_rest' => true,
                'rewrite'      => array(
                    'slug'       => 'directorio/region',
                    'with_front' => false,
                ),
            )
        );
    }

    public static function precargar_regiones() {
        foreach ( self::regiones() as $region ) {
            if ( ! term_exists( $region, 'region' ) ) {
                wp_insert_term( $region, 'region' );
            }
        }
    }

    /**
     * Migra los estados utilizados por versiones anteriores a las nuevas regiones.
     * No elimina términos antiguos; solo reasigna la ficha del doctor.
     */
    public static function migrar_estados_a_regiones() {
        $mapa = array(
            'Distrito Capital' => 'Region Capital', 'Miranda' => 'Region Capital', 'La Guaira' => 'Region Capital',
            'Aragua' => 'Region Capital',
            'Lara' => 'Centro-Occidental', 'Falcón' => 'Centro-Occidental', 'Yaracuy' => 'Centro-Occidental',
            'Carabobo' => 'Centro-Occidental', 'Cojedes' => 'Centro-Occidental',
            'Mérida' => 'De los Andes', 'Táchira' => 'De los Andes', 'Trujillo' => 'De los Andes',
            'Zulia' => 'Zuliana',
            'Barinas' => 'De los Llanos', 'Apure' => 'De los Llanos', 'Guárico' => 'De los Llanos', 'Portuguesa' => 'De los Llanos',
            'Anzoátegui' => 'Nor-Oriental', 'Monagas' => 'Nor-Oriental', 'Sucre' => 'Nor-Oriental',
            'Nueva Esparta' => 'Insular',
            'Bolívar' => 'Guayana', 'Amazonas' => 'Guayana', 'Delta Amacuro' => 'Guayana',
        );
        foreach ( $mapa as $estado => $region ) {
            $estado_term = get_term_by( 'name', $estado, 'region' );
            $region_term = get_term_by( 'name', $region, 'region' );
            if ( $estado_term && $region_term ) {
                $doctores = get_posts( array(
                    'post_type'      => 'doctor',
                    'post_status'    => 'any',
                    'posts_per_page' => -1,
                    'fields'         => 'ids',
                    'tax_query'      => array(array(
                        'taxonomy' => 'region', 'field' => 'term_id', 'terms' => $estado_term->term_id,
                    )),
                ) );
                foreach ( $doctores as $doctor_id ) {
                    wp_set_object_terms( $doctor_id, array( $region_term->term_id ), 'region', false );
                }
            }
        }
    }
}
