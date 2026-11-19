<?php
/**
 * Catálogo público reutilizable.
 * Shortcode: [svcp_directorio_medico]
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SVCP_Directorio_Publico {
    private static $instancia = null;

    public static function instance() {
        if ( null === self::$instancia ) { self::$instancia = new self(); }
        return self::$instancia;
    }

    private function __construct() {
        add_shortcode( 'svcp_directorio_medico', array( $this, 'render' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'assets' ), 30 );
    }

    public function assets() {
        if ( is_post_type_archive( 'doctor' ) ) { $cargar = true; }
        else {
            $post = get_post();
            $cargar = $post && has_shortcode( $post->post_content, 'svcp_directorio_medico' );
        }
        if ( $cargar ) {
            wp_enqueue_script( 'svcp-directorio-live-search', SVCP_DIR_URL . 'assets/js/svcp-directorio-live-search.js', array(), SVCP_DIR_VERSION, true );
        }
    }

    public function render() {
        $busqueda = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
        $region = isset( $_GET['region'] ) ? sanitize_title( wp_unslash( $_GET['region'] ) ) : '';
        $regiones = get_terms( array( 'taxonomy' => 'region', 'hide_empty' => false ) );
        if ( is_wp_error( $regiones ) ) { $regiones = array(); }

        $hoy = wp_date( 'Y-m-d' );
        $meta_query = array(
            'relation' => 'AND',
            array(
                'relation' => 'OR',
                array( 'key' => 'svcp_membresia_estado', 'value' => array( 'activa', 'por_vencer' ), 'compare' => 'IN' ),
                array( 'key' => 'svcp_membresia_vencimiento', 'value' => $hoy, 'compare' => '>=', 'type' => 'DATE' ),
                array( 'key' => 'svcp_membresia_activa', 'value' => '1', 'compare' => '=' ),
            ),
            array(
                'relation' => 'OR',
                array( 'key' => 'svcp_membresia_estado', 'compare' => 'NOT EXISTS' ),
                array( 'key' => 'svcp_membresia_estado', 'value' => 'suspendida', 'compare' => '!=' ),
            ),
        );
        if ( class_exists( 'SVCP_Aprobacion' ) ) {
            $meta_query[] = array(
                'relation' => 'OR',
                array( 'key' => SVCP_Aprobacion::META, 'value' => SVCP_Aprobacion::PUBLICADA, 'compare' => '=' ),
                array( 'key' => SVCP_Aprobacion::META, 'compare' => 'NOT EXISTS' ),
            );
        }
        $args = array(
            'post_type' => 'doctor', 'post_status' => 'publish', 'posts_per_page' => -1,
            'orderby' => 'title', 'order' => 'ASC', 'meta_query' => $meta_query,
        );
        if ( $busqueda ) { $args['s'] = $busqueda; }
        if ( $region ) {
            $args['tax_query'] = array( array( 'taxonomy' => 'region', 'field' => 'slug', 'terms' => $region ) );
        }
        $doctores = new WP_Query( $args );
        ob_start();
        ?>
        <div class="svcp-directorio-wrap">
            <form class="svcp-filtros svcp-filtros-directorio svcp-directorio-buscador-principal" method="get">
                <div class="svcp-filtro-busqueda">
                    <label class="screen-reader-text" for="svcp-doctor-search"><?php esc_html_e( 'Buscar por nombre', 'svcp-directorio' ); ?></label>
                    <input id="svcp-doctor-search" type="search" name="q" value="<?php echo esc_attr( $busqueda ); ?>" placeholder="<?php esc_attr_e( 'Buscar por nombre del médico...', 'svcp-directorio' ); ?>" autocomplete="off">
                </div>
                <div>
                    <label class="screen-reader-text" for="svcp-region-filter"><?php esc_html_e( 'Filtrar por región', 'svcp-directorio' ); ?></label>
                    <select id="svcp-region-filter" name="region">
                        <option value=""><?php esc_html_e( 'Todas las regiones', 'svcp-directorio' ); ?></option>
                        <?php foreach ( $regiones as $term ) : ?>
                            <option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $region, $term->slug ); ?>><?php echo esc_html( $term->name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit"><?php esc_html_e( 'Buscar', 'svcp-directorio' ); ?></button>
                <?php if ( $busqueda || $region ) : ?><a class="svcp-filtro-limpiar" href="<?php echo esc_url( remove_query_arg( array( 'q', 'region' ) ) ); ?>"><?php esc_html_e( 'Limpiar', 'svcp-directorio' ); ?></a><?php endif; ?>
            </form>
            <?php if ( ! $busqueda && ! $region && $regiones ) : ?>
                <section class="svcp-regiones-directorio" aria-label="<?php esc_attr_e( 'Explorar médicos por región', 'svcp-directorio' ); ?>">
                    <div class="svcp-regiones-cabecera"><div><h2><?php esc_html_e( 'Encuentra médicos por región', 'svcp-directorio' ); ?></h2></div><p><?php esc_html_e( 'Selecciona una región para consultar los profesionales disponibles en esa zona.', 'svcp-directorio' ); ?></p></div>
                    <div class="svcp-regiones-grid">
                        <?php foreach ( $regiones as $term ) : ?>
                            <a class="svcp-region-card" href="<?php echo esc_url( add_query_arg( 'region', $term->slug ) ); ?>"><span class="svcp-region-card-icon" aria-hidden="true">⌖</span><span class="svcp-region-card-name"><?php echo esc_html( $term->name ); ?></span><span class="svcp-region-card-action"><?php esc_html_e( 'Ver médicos', 'svcp-directorio' ); ?> →</span></a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
            <div class="svcp-directorio-resultados">
                <div class="svcp-resultados-resumen" aria-live="polite"><strong><?php echo esc_html( number_format_i18n( (int) $doctores->post_count ) ); ?></strong> <?php echo esc_html( _n( 'médico encontrado', 'médicos encontrados', (int) $doctores->post_count, 'svcp-directorio' ) ); ?></div>
                <?php if ( $doctores->have_posts() ) : ?>
                    <div class="svcp-lista-doctores">
                        <?php while ( $doctores->have_posts() ) : $doctores->the_post();
                            $id = get_the_ID();
                            $clinicas = get_post_meta( $id, 'svcp_clinicas', true );
                            if ( ! is_array( $clinicas ) || empty( $clinicas ) ) { $clinicas = array( get_post_meta( $id, 'svcp_clinica', true ) ); }
                            $clinicas = array_values( array_filter( array_slice( $clinicas, 0, 3 ) ) );
                            $telefonos = get_post_meta( $id, 'svcp_telefonos', true );
                            if ( ! is_array( $telefonos ) || empty( $telefonos ) ) { $telefonos = array( get_post_meta( $id, 'svcp_telefono', true ) ); }
                            $telefonos = array_values( array_filter( array_slice( $telefonos, 0, 3 ) ) );
                            $clinica = $clinicas[0] ?? '';
                            $ciudad = get_post_meta( $id, 'svcp_ciudad', true );
                            $whatsapp = get_post_meta( $id, 'svcp_whatsapp', true );
                            $correo = get_post_meta( $id, 'svcp_correo', true );
                            $direccion = get_post_meta( $id, 'svcp_direccion', true );
                            $facebook = get_post_meta( $id, 'svcp_facebook', true );
                            $instagram = get_post_meta( $id, 'svcp_instagram', true );
                            $linkedin = get_post_meta( $id, 'svcp_linkedin', true );
                            $twitter = get_post_meta( $id, 'svcp_twitter', true );
                            $terms = get_the_terms( $id, 'region' );
                            $region_nombre = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : '';
                            $texto = implode( ' ', array_filter( array_merge( array( get_the_title(), $ciudad, $direccion, $region_nombre ), $clinicas ) ) );
                        ?>
                            <article class="svcp-doctor-card" data-svcp-doctor-card data-search="<?php echo esc_attr( remove_accents( strtolower( $texto ) ) ); ?>" data-region="<?php echo esc_attr( $region_nombre ); ?>">
                                <div class="svcp-doctor-foto" aria-hidden="true"><?php if ( has_post_thumbnail() ) { the_post_thumbnail( 'medium', array( 'loading' => 'lazy' ) ); } else { ?><div class="svcp-doctor-foto-placeholder"><span><?php echo esc_html( strtoupper( implode( '', array_slice( preg_split( '/\s+/', trim( wp_strip_all_tags( get_the_title() ) ) ), 0, 2 ) ) ) ); ?></span></div><?php } ?></div>
                                <div class="svcp-doctor-info">
                                    <h2 class="svcp-doctor-nombre"><?php the_title(); ?></h2>
                                    <?php if ( $region_nombre ) : ?><p class="svcp-doctor-region"><?php echo esc_html( $region_nombre ); ?></p><?php endif; ?>
                                    <?php if ( $clinicas ) : ?><div class="svcp-doctor-clinicas"><strong><?php esc_html_e( 'Clínicas:', 'svcp-directorio' ); ?></strong><?php foreach ( $clinicas as $nombre_clinica ) : ?><p class="svcp-doctor-clinica"><?php echo esc_html( $nombre_clinica ); ?></p><?php endforeach; ?></div><?php endif; ?>
                                    <?php if ( $direccion ) : ?><p class="svcp-doctor-direccion"><?php echo esc_html( $direccion ); ?></p><?php endif; ?>
                                    <?php if ( $ciudad ) : ?><p class="svcp-doctor-ciudad"><?php echo esc_html( $ciudad ); ?></p><?php endif; ?>
                                    <div class="svcp-doctor-acciones svcp-doctor-contact-actions">
                                        <?php foreach ( $telefonos as $telefono ) : ?><a class="svcp-contact-icon" href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $telefono ) ); ?>">☎ <span><?php echo esc_html( $telefono ); ?></span></a><?php endforeach; ?>
                                        <?php if ( $correo && is_email( $correo ) ) : ?><a class="svcp-contact-icon svcp-contact-email" href="mailto:<?php echo esc_attr( $correo ); ?>">✉ <span><?php echo esc_html( $correo ); ?></span></a><?php endif; ?>
                                        <?php if ( $whatsapp ) : ?><a class="svcp-social-icon" href="https://wa.me/<?php echo esc_attr( preg_replace( '/[^0-9]/', '', $whatsapp ) ); ?>" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i></a><?php endif; ?>
                                        <?php if ( $facebook ) : ?><a class="svcp-social-icon" href="<?php echo esc_url( $facebook ); ?>" target="_blank" rel="noopener noreferrer" aria-label="Facebook"><i class="fa-brands fa-facebook-f" aria-hidden="true"></i></a><?php endif; ?>
                                        <?php if ( $twitter ) : ?><a class="svcp-social-icon" href="<?php echo esc_url( $twitter ); ?>" target="_blank" rel="noopener noreferrer" aria-label="X"><i class="fa-brands fa-x-twitter" aria-hidden="true"></i></a><?php endif; ?>
                                        <?php if ( $instagram ) : ?><a class="svcp-social-icon" href="<?php echo esc_url( $instagram ); ?>" target="_blank" rel="noopener noreferrer" aria-label="Instagram"><i class="fa-brands fa-instagram" aria-hidden="true"></i></a><?php endif; ?>
                                        <?php if ( $linkedin ) : ?><a class="svcp-social-icon" href="<?php echo esc_url( $linkedin ); ?>" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn"><i class="fa-brands fa-linkedin-in" aria-hidden="true"></i></a><?php endif; ?>
                                        <?php if ( get_post_meta( $id, 'svcp_sitio_web', true ) ) : ?><a class="svcp-social-icon" href="<?php echo esc_url( get_post_meta( $id, 'svcp_sitio_web', true ) ); ?>" target="_blank" rel="noopener noreferrer" aria-label="Sitio web"><i class="fa-solid fa-globe" aria-hidden="true"></i></a><?php endif; ?>
                                    </div>
                                </div>
                            </article>
                        <?php endwhile; wp_reset_postdata(); ?>
                    </div>
                <?php else : ?><div class="svcp-sin-resultados-box"><h2><?php esc_html_e( 'No encontramos resultados', 'svcp-directorio' ); ?></h2><p><?php esc_html_e( 'Prueba con otro nombre o selecciona otra región.', 'svcp-directorio' ); ?></p></div><?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
