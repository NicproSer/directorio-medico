<?php
/**
 * Panel administrativo de membresías.
 *
 * Permite a los administradores consultar el estado calculado de las
 * membresías, días restantes, vencimientos y datos básicos de pago.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SVCP_Admin_Membresias {

    private static $instancia = null;

    public static function instance() {
        if ( null === self::$instancia ) {
            self::$instancia = new self();
        }
        return self::$instancia;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'registrar_menu' ) );
    }

    public function registrar_menu() {
        add_submenu_page(
            'edit.php?post_type=doctor',
            __( 'Membresías SVCP', 'svcp-directorio' ),
            __( 'Membresías', 'svcp-directorio' ),
            'edit_doctors',
            'svcp-membresias',
            array( $this, 'renderizar' )
        );
    }

    public function renderizar() {
        if ( ! current_user_can( 'edit_doctors' ) ) {
            wp_die( esc_html__( 'No tienes permisos para acceder a esta sección.', 'svcp-directorio' ) );
        }

        $estado_filtro = isset( $_GET['estado'] ) ? sanitize_key( wp_unslash( $_GET['estado'] ) ) : 'todos';
        $region_filtro = isset( $_GET['region'] ) ? sanitize_title( wp_unslash( $_GET['region'] ) ) : '';
        $busqueda      = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        $pagina        = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
        $por_pagina    = 20;

        $estados = SVCP_Membresia::estados();

        $args = array(
            'post_type'      => 'doctor',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'orderby'        => 'title',
            'order'          => 'ASC',
        );

        if ( $busqueda ) {
            $args['s'] = $busqueda;
        }

        if ( $region_filtro ) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'region',
                    'field'    => 'slug',
                    'terms'    => $region_filtro,
                ),
            );
        }

        $ids = get_posts( $args );
        $filtrados = array();
        $conteos = array(
            'todos' => 0,
            SVCP_Membresia::ESTADO_ACTIVA => 0,
            SVCP_Membresia::ESTADO_POR_VENCER => 0,
            SVCP_Membresia::ESTADO_VENCIDA => 0,
            SVCP_Membresia::ESTADO_SUSPENDIDA => 0,
        );

        foreach ( $ids as $doctor_id ) {
            $estado = SVCP_Membresia::obtener_estado( $doctor_id );

            if ( isset( $conteos[ $estado ] ) ) {
                $conteos[ $estado ]++;
            }
            $conteos['todos']++;

            if ( 'todos' !== $estado_filtro && $estado !== $estado_filtro ) {
                continue;
            }

            $filtrados[] = $doctor_id;
        }

        // Orden administrativo por vencimiento: primero los que vencen antes.
        usort(
            $filtrados,
            function ( $a, $b ) {
                $fecha_a = get_post_meta( $a, 'svcp_membresia_vencimiento', true );
                $fecha_b = get_post_meta( $b, 'svcp_membresia_vencimiento', true );

                if ( ! $fecha_a ) {
                    return 1;
                }
                if ( ! $fecha_b ) {
                    return -1;
                }
                return strcmp( $fecha_a, $fecha_b );
            }
        );

        $total = count( $filtrados );
        $total_paginas = max( 1, (int) ceil( $total / $por_pagina ) );
        $pagina = min( $pagina, $total_paginas );
        $inicio = ( $pagina - 1 ) * $por_pagina;
        $items = array_slice( $filtrados, $inicio, $por_pagina );

        $regiones = get_terms(
            array(
                'taxonomy'   => 'region',
                'hide_empty' => false,
                'orderby'    => 'name',
                'order'      => 'ASC',
            )
        );

        ?>
        <div class="wrap svcp-admin-membresias">
            <h1><?php esc_html_e( 'Membresías SVCP', 'svcp-directorio' ); ?></h1>
            <p class="description">
                <?php esc_html_e( 'Consulta el estado calculado de cada membresía, su vencimiento y los datos principales de pago.', 'svcp-directorio' ); ?>
            </p>

            <div class="svcp-resumen-membresias">
                <?php
                $tarjetas = array(
                    'todos' => __( 'Total', 'svcp-directorio' ),
                    SVCP_Membresia::ESTADO_ACTIVA => __( 'Activas', 'svcp-directorio' ),
                    SVCP_Membresia::ESTADO_POR_VENCER => __( 'Por vencer', 'svcp-directorio' ),
                    SVCP_Membresia::ESTADO_VENCIDA => __( 'Vencidas', 'svcp-directorio' ),
                    SVCP_Membresia::ESTADO_SUSPENDIDA => __( 'Suspendidas', 'svcp-directorio' ),
                );
                foreach ( $tarjetas as $clave => $label ) :
                    $url_args = array( 'post_type' => 'doctor', 'page' => 'svcp-membresias' );
                    if ( 'todos' !== $clave ) {
                        $url_args['estado'] = $clave;
                    }
                    if ( $region_filtro ) {
                        $url_args['region'] = $region_filtro;
                    }
                    ?>
                    <a class="svcp-resumen-card <?php echo $estado_filtro === $clave ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( $url_args, admin_url( 'edit.php' ) ) ); ?>">
                        <span class="svcp-resumen-label"><?php echo esc_html( $label ); ?></span>
                        <strong><?php echo esc_html( $conteos[ $clave ] ?? 0 ); ?></strong>
                    </a>
                <?php endforeach; ?>
            </div>

            <form method="get" class="svcp-filtros-membresias">
                <input type="hidden" name="post_type" value="doctor">
                <input type="hidden" name="page" value="svcp-membresias">

                <label>
                    <span class="screen-reader-text"><?php esc_html_e( 'Buscar doctor', 'svcp-directorio' ); ?></span>
                    <input type="search" name="s" value="<?php echo esc_attr( $busqueda ); ?>" placeholder="<?php esc_attr_e( 'Buscar por nombre...', 'svcp-directorio' ); ?>">
                </label>

                <select name="estado">
                    <option value="todos"><?php esc_html_e( 'Todos los estados', 'svcp-directorio' ); ?></option>
                    <?php foreach ( $estados as $valor => $label ) : ?>
                        <option value="<?php echo esc_attr( $valor ); ?>" <?php selected( $estado_filtro, $valor ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="region">
                    <option value=""><?php esc_html_e( 'Todas las regiones', 'svcp-directorio' ); ?></option>
                    <?php if ( ! is_wp_error( $regiones ) ) : ?>
                        <?php foreach ( $regiones as $region ) : ?>
                            <option value="<?php echo esc_attr( $region->slug ); ?>" <?php selected( $region_filtro, $region->slug ); ?>><?php echo esc_html( $region->name ); ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>

                <button type="submit" class="button button-primary"><?php esc_html_e( 'Filtrar', 'svcp-directorio' ); ?></button>
                <a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=doctor&page=svcp-membresias' ) ); ?>"><?php esc_html_e( 'Limpiar', 'svcp-directorio' ); ?></a>
            </form>

            <div class="tablenav top">
                <div class="alignleft actions">
                    <span><?php printf( esc_html__( '%d membresías encontradas', 'svcp-directorio' ), $total ); ?></span>
                </div>
                <?php $this->paginacion( $pagina, $total_paginas, $busqueda, $estado_filtro, $region_filtro ); ?>
                <br class="clear">
            </div>

            <table class="widefat fixed striped svcp-tabla-membresias">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Doctor', 'svcp-directorio' ); ?></th>
                        <th><?php esc_html_e( 'Región', 'svcp-directorio' ); ?></th>
                        <th><?php esc_html_e( 'Estado', 'svcp-directorio' ); ?></th>
                        <th><?php esc_html_e( 'Vencimiento', 'svcp-directorio' ); ?></th>
                        <th><?php esc_html_e( 'Días restantes', 'svcp-directorio' ); ?></th>
                        <th><?php esc_html_e( 'Último pago', 'svcp-directorio' ); ?></th>
                        <th><?php esc_html_e( 'Método / referencia', 'svcp-directorio' ); ?></th>
                        <th><?php esc_html_e( 'Monto', 'svcp-directorio' ); ?></th>
                        <th><?php esc_html_e( 'Acciones', 'svcp-directorio' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $items ) ) : ?>
                        <tr>
                            <td colspan="9"><?php esc_html_e( 'No se encontraron membresías con los filtros seleccionados.', 'svcp-directorio' ); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $items as $doctor_id ) : ?>
                            <?php $this->pintar_fila( $doctor_id ); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="tablenav bottom">
                <?php $this->paginacion( $pagina, $total_paginas, $busqueda, $estado_filtro, $region_filtro ); ?>
                <br class="clear">
            </div>
        </div>

        <style>
            .svcp-admin-membresias .svcp-resumen-membresias{display:grid;grid-template-columns:repeat(5,minmax(130px,1fr));gap:12px;margin:20px 0}.svcp-resumen-card{background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px 16px;text-decoration:none;color:#1d2327;display:flex;flex-direction:column;gap:6px}.svcp-resumen-card:hover,.svcp-resumen-card.is-active{border-color:#2271b1;box-shadow:0 0 0 1px #2271b1}.svcp-resumen-card strong{font-size:24px}.svcp-resumen-label{font-weight:600}.svcp-filtros-membresias{display:flex;gap:8px;align-items:center;margin:18px 0}.svcp-filtros-membresias input[type=search]{min-width:260px}.svcp-tabla-membresias th{font-weight:600}.svcp-estado{display:inline-block;border-radius:999px;padding:4px 9px;font-size:12px;font-weight:700}.svcp-estado-activa{background:#e7f6ec;color:#18733a}.svcp-estado-por-vencer{background:#fff4d6;color:#8a5a00}.svcp-estado-vencida{background:#fbeaea;color:#a61b1b}.svcp-estado-suspendida{background:#ececec;color:#444}.svcp-dias-alerta{font-weight:700;color:#a61b1b}.svcp-dias-pronto{font-weight:700;color:#8a5a00}@media(max-width:1200px){.svcp-admin-membresias .svcp-resumen-membresias{grid-template-columns:repeat(3,1fr)}.svcp-tabla-membresias{min-width:1100px}.svcp-admin-membresias{overflow-x:auto}.svcp-filtros-membresias{flex-wrap:wrap}}
        </style>
        <?php
    }

    private function pintar_fila( $doctor_id ) {
        $estado = SVCP_Membresia::obtener_estado( $doctor_id );
        $estados = SVCP_Membresia::estados();
        $vencimiento = get_post_meta( $doctor_id, 'svcp_membresia_vencimiento', true );
        $ultimo_pago = get_post_meta( $doctor_id, 'svcp_membresia_ultimo_pago', true );
        $metodo = get_post_meta( $doctor_id, 'svcp_membresia_metodo_pago', true );
        $referencia = get_post_meta( $doctor_id, 'svcp_membresia_referencia', true );
        $monto = get_post_meta( $doctor_id, 'svcp_membresia_monto', true );
        $regiones = wp_get_post_terms( $doctor_id, 'region', array( 'fields' => 'names' ) );
        $region = ! empty( $regiones ) && ! is_wp_error( $regiones ) ? $regiones[0] : '—';
        $dias = $this->dias_restantes( $vencimiento );
        $clase_estado = 'svcp-estado-' . sanitize_html_class( $estado );
        $edit_url = get_edit_post_link( $doctor_id, '' );
        ?>
        <tr>
            <td>
                <strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( get_the_title( $doctor_id ) ); ?></a></strong>
                <?php $correo = get_post_meta( $doctor_id, 'svcp_correo', true ); ?>
                <?php if ( $correo ) : ?><br><small><?php echo esc_html( $correo ); ?></small><?php endif; ?>
            </td>
            <td><?php echo esc_html( $region ); ?></td>
            <td><span class="svcp-estado <?php echo esc_attr( $clase_estado ); ?>"><?php echo esc_html( $estados[ $estado ] ?? $estado ); ?></span></td>
            <td><?php echo esc_html( $this->formatear_fecha( $vencimiento ) ); ?></td>
            <td class="<?php echo $dias !== null && $dias <= 7 ? 'svcp-dias-alerta' : ( $dias !== null && $dias <= 30 ? 'svcp-dias-pronto' : '' ); ?>">
                <?php echo esc_html( $this->texto_dias( $dias, $estado ) ); ?>
            </td>
            <td><?php echo esc_html( $this->formatear_fecha( $ultimo_pago ) ); ?></td>
            <td>
                <?php echo esc_html( $metodo ?: '—' ); ?>
                <?php if ( $referencia ) : ?><br><small><?php echo esc_html( $referencia ); ?></small><?php endif; ?>
            </td>
            <td><?php echo esc_html( $monto !== '' ? $monto : '—' ); ?></td>
            <td><a class="button button-small" href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Editar ficha', 'svcp-directorio' ); ?></a></td>
        </tr>
        <?php
    }

    private function dias_restantes( $vencimiento ) {
        if ( empty( $vencimiento ) ) {
            return null;
        }

        $fecha = DateTimeImmutable::createFromFormat( 'Y-m-d', $vencimiento, wp_timezone() );
        if ( false === $fecha ) {
            return null;
        }

        $hoy = new DateTimeImmutable( 'today', wp_timezone() );
        return (int) $hoy->diff( $fecha )->format( '%r%a' );
    }

    private function texto_dias( $dias, $estado ) {
        if ( null === $dias ) {
            return '—';
        }
        if ( SVCP_Membresia::ESTADO_SUSPENDIDA === $estado ) {
            return 'Suspendida';
        }
        if ( $dias <= 0 ) {
            return $dias === 0 ? 'Vence hoy' : sprintf( '%d días vencida', abs( $dias ) );
        }
        return sprintf( '%d días', $dias );
    }

    private function formatear_fecha( $fecha ) {
        if ( empty( $fecha ) ) {
            return '—';
        }
        $objeto = DateTimeImmutable::createFromFormat( 'Y-m-d', $fecha, wp_timezone() );
        if ( false === $objeto ) {
            return $fecha;
        }
        return wp_date( get_option( 'date_format' ), $objeto->getTimestamp(), wp_timezone() );
    }

    private function paginacion( $pagina, $total_paginas, $busqueda, $estado, $region ) {
        if ( $total_paginas <= 1 ) {
            return;
        }

        $base = admin_url( 'edit.php' );
        $args = array(
            'post_type' => 'doctor',
            'page'      => 'svcp-membresias',
            's'         => $busqueda,
            'estado'    => $estado,
            'region'    => $region,
        );

        echo '<div class="tablenav-pages">';
        echo wp_kses_post(
            paginate_links(
                array(
                    'base'      => add_query_arg( 'paged', '%#%', $base ),
                    'format'    => '',
                    'current'   => $pagina,
                    'total'     => $total_paginas,
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'type'      => 'plain',
                    'add_args'  => $args,
                )
            )
        );
        echo '</div>';
    }
}
