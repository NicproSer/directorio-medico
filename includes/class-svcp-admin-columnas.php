<?php
/**
 * Columnas extra en el listado admin de "Doctores": región, clínica,
 * correo y el estado calculado de membresía y días restantes de un vistazo.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SVCP_Admin_Columnas {

    private static $instancia = null;

    public static function instance() {
        if ( null === self::$instancia ) {
            self::$instancia = new self();
        }
        return self::$instancia;
    }

    private function __construct() {
        add_filter( 'manage_doctor_posts_columns', array( $this, 'agregar_columnas' ) );
        add_action( 'manage_doctor_posts_custom_column', array( $this, 'pintar_columnas' ), 10, 2 );
    }

    public function agregar_columnas( $columnas ) {
        $nuevas = array();
        foreach ( $columnas as $key => $label ) {
            $nuevas[ $key ] = $label;
            if ( 'title' === $key ) {
                $nuevas['svcp_region']     = __( 'Región', 'svcp-directorio' );
                $nuevas['svcp_clinica']    = __( 'Clínica/Hospital', 'svcp-directorio' );
                $nuevas['svcp_correo_col'] = __( 'Correo', 'svcp-directorio' );
                $nuevas['svcp_membresia']  = __( 'Membresía', 'svcp-directorio' );
            }
        }
        return $nuevas;
    }

    public function pintar_columnas( $columna, $post_id ) {
        switch ( $columna ) {
            case 'svcp_region':
                echo esc_html( wp_get_post_terms( $post_id, 'region', array( 'fields' => 'names' ) )[0] ?? '—' );
                break;
            case 'svcp_clinica':
                echo esc_html( get_post_meta( $post_id, 'svcp_clinica', true ) ?: '—' );
                break;
            case 'svcp_correo_col':
                echo esc_html( get_post_meta( $post_id, 'svcp_correo', true ) ?: '—' );
                break;
            case 'svcp_membresia':
                $estado = SVCP_Membresia::obtener_estado( $post_id );
                $estados = SVCP_Membresia::estados();
                $vencimiento = get_post_meta( $post_id, 'svcp_membresia_vencimiento', true );
                $dias = '';

                if ( $vencimiento ) {
                    $fecha = DateTimeImmutable::createFromFormat( 'Y-m-d', $vencimiento, wp_timezone() );
                    if ( false !== $fecha ) {
                        $hoy = new DateTimeImmutable( 'today', wp_timezone() );
                        $dias = (int) $hoy->diff( $fecha )->format( '%r%a' );
                    }
                }

                $colores = array(
                    SVCP_Membresia::ESTADO_ACTIVA => '#18733a',
                    SVCP_Membresia::ESTADO_POR_VENCER => '#8a5a00',
                    SVCP_Membresia::ESTADO_VENCIDA => '#a61b1b',
                    SVCP_Membresia::ESTADO_SUSPENDIDA => '#555',
                );
                $color = $colores[ $estado ] ?? '#555';
                echo '<span style="color:' . esc_attr( $color ) . ';font-weight:bold;">● ' . esc_html( $estados[ $estado ] ?? $estado ) . '</span>';
                if ( '' !== $dias && SVCP_Membresia::ESTADO_SUSPENDIDA !== $estado ) {
                    echo '<br><small>' . esc_html( $dias > 0 ? $dias . ' días' : ( $dias === 0 ? 'Vence hoy' : abs( $dias ) . ' días vencida' ) ) . '</small>';
                }
                break;
        }
    }
}
