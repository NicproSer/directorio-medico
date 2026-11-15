<?php
/**
 * Núcleo de membresías del directorio.
 *
 * Reglas:
 * - Activa: faltan más de 30 días para vencer.
 * - Por vencer: faltan entre 1 y 30 días.
 * - Vencida: la fecha de vencimiento ya pasó o es hoy.
 * - Suspendida: estado administrativo manual; siempre tiene prioridad.
 *
 * La membresía no se elimina ni despublica el doctor. Simplemente se excluye
 * del directorio público cuando no corresponde.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SVCP_Membresia {

    private static $instancia = null;

    /** Número de días que define "Por vencer". */
    const DIAS_POR_VENCER = 30;

    /** Estados permitidos. */
    const ESTADO_ACTIVA     = 'activa';
    const ESTADO_POR_VENCER = 'por_vencer';
    const ESTADO_VENCIDA    = 'vencida';
    const ESTADO_SUSPENDIDA = 'suspendida';

    public static function instance() {
        if ( null === self::$instancia ) {
            self::$instancia = new self();
        }
        return self::$instancia;
    }

    private function __construct() {
        add_action( 'pre_get_posts', array( $this, 'ocultar_morosos' ) );
    }

    /**
     * Obtiene el estado actual de la membresía de un doctor.
     * El estado se calcula a partir de la fecha de vencimiento para evitar
     * depender de un cron para que el directorio deje de mostrar vencidos.
     */
    public static function obtener_estado( $post_id ) {
        $estado_manual = get_post_meta( $post_id, 'svcp_membresia_estado', true );

        // Suspendida es el único estado manual y tiene prioridad.
        if ( self::ESTADO_SUSPENDIDA === $estado_manual ) {
            return self::ESTADO_SUSPENDIDA;
        }

        $vencimiento = get_post_meta( $post_id, 'svcp_membresia_vencimiento', true );

        // Compatibilidad temporal con la versión MVP anterior.
        // Si todavía no existe fecha, respetamos svcp_membresia_activa.
        if ( empty( $vencimiento ) ) {
            $activa_legacy = get_post_meta( $post_id, 'svcp_membresia_activa', true );
            return $activa_legacy ? self::ESTADO_ACTIVA : self::ESTADO_VENCIDA;
        }

        $hoy = new DateTimeImmutable( 'today', wp_timezone() );
        $fecha_vencimiento = DateTimeImmutable::createFromFormat( 'Y-m-d', $vencimiento, wp_timezone() );

        if ( false === $fecha_vencimiento ) {
            return self::ESTADO_VENCIDA;
        }

        $dias_restantes = (int) $hoy->diff( $fecha_vencimiento )->format( '%r%a' );

        if ( $dias_restantes <= 0 ) {
            return self::ESTADO_VENCIDA;
        }

        if ( $dias_restantes <= self::DIAS_POR_VENCER ) {
            return self::ESTADO_POR_VENCER;
        }

        return self::ESTADO_ACTIVA;
    }

    /**
     * Indica si el doctor puede aparecer en el directorio público.
     */
    public static function puede_aparecer_en_directorio( $post_id ) {
        $estado = self::obtener_estado( $post_id );

        return in_array(
            $estado,
            array( self::ESTADO_ACTIVA, self::ESTADO_POR_VENCER ),
            true
        );
    }

    /**
     * Devuelve los estados disponibles para interfaces administrativas.
     */
    public static function estados() {
        return array(
            self::ESTADO_ACTIVA     => __( 'Activa', 'svcp-directorio' ),
            self::ESTADO_POR_VENCER => __( 'Por vencer', 'svcp-directorio' ),
            self::ESTADO_VENCIDA    => __( 'Vencida', 'svcp-directorio' ),
            self::ESTADO_SUSPENDIDA => __( 'Suspendida', 'svcp-directorio' ),
        );
    }

    /**
     * Excluye del archivo público a los doctores cuya membresía no esté vigente.
     */
    public function ocultar_morosos( $query ) {
        if ( is_admin() || ! $query->is_main_query() ) {
            return;
        }

        if ( 'doctor' !== $query->get( 'post_type' ) || ! $query->is_post_type_archive( 'doctor' ) ) {
            return;
        }

        // Directorio público: carga el catálogo vigente completo para permitir
        // filtrado instantáneo en el navegador sin recargar la página.
        $query->set( 'posts_per_page', -1 );
        $query->set( 'orderby', 'title' );
        $query->set( 'order', 'ASC' );

        // Búsqueda pública por nombre del doctor.
        if ( isset( $_GET['q'] ) ) {
            $busqueda = sanitize_text_field( wp_unslash( $_GET['q'] ) );
            if ( '' !== $busqueda ) {
                $query->set( 's', $busqueda );
            }
        }

        // Filtro por región desde la URL: /directorio/?region=region-capital
        if ( ! empty( $_GET['region'] ) ) {
            $tax_query   = (array) $query->get( 'tax_query' );
            $tax_query[] = array(
                'taxonomy' => 'region',
                'field'    => 'slug',
                'terms'    => sanitize_title( wp_unslash( $_GET['region'] ) ),
            );
            $query->set( 'tax_query', $tax_query );
        }

        /*
         * Para la consulta pública filtramos por fecha. Suspendidos no pasan
         * porque no se consideran en la publicación pública.
         *
         * Por compatibilidad con registros antiguos que todavía solo tienen
         * svcp_membresia_activa, se mantiene además la condición legacy.
         */
        $hoy = wp_date( 'Y-m-d' );

        $meta_query = (array) $query->get( 'meta_query' );
        $meta_query[] = array(
            'relation' => 'OR',
            array(
                'key'     => 'svcp_membresia_estado',
                'value'   => array( self::ESTADO_ACTIVA, self::ESTADO_POR_VENCER ),
                'compare' => 'IN',
            ),
            array(
                'key'     => 'svcp_membresia_vencimiento',
                'value'   => $hoy,
                'compare' => '>=',
                'type'    => 'DATE',
            ),
            array(
                'key'     => 'svcp_membresia_activa',
                'value'   => '1',
                'compare' => '=',
            ),
        );

        // Excluir explícitamente suspendidos.
        $meta_query[] = array(
            'relation' => 'OR',
            array(
                'key'     => 'svcp_membresia_estado',
                'compare' => 'NOT EXISTS',
            ),
            array(
                'key'     => 'svcp_membresia_estado',
                'value'   => self::ESTADO_SUSPENDIDA,
                'compare' => '!=',
            ),
        );

        // La membresía vigente no basta: la ficha también debe estar publicada por el flujo de aprobación.
        if ( class_exists( 'SVCP_Aprobacion' ) ) {
            $meta_query[] = array(
                'relation' => 'OR',
                array(
                    'key'     => SVCP_Aprobacion::META,
                    'value'   => SVCP_Aprobacion::PUBLICADA,
                    'compare' => '=',
                ),
                array(
                    'key'     => SVCP_Aprobacion::META,
                    'compare' => 'NOT EXISTS',
                ),
            );
        }

        $query->set( 'meta_query', $meta_query );
    }
}
