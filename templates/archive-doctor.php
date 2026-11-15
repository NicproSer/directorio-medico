<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
$region_actual = isset( $_GET['region'] ) ? sanitize_title( wp_unslash( $_GET['region'] ) ) : '';
$busqueda_actual = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
$regiones = get_terms( array( 'taxonomy' => 'region', 'hide_empty' => false ) );
?>
<div class="svcp-directorio-wrap">
    <header class="svcp-directorio-hero">
        <span class="svcp-directorio-eyebrow"><?php esc_html_e( 'Sociedad Venezolana de Coloproctología', 'svcp-directorio' ); ?></span>
        <h1 class="svcp-directorio-titulo"><?php esc_html_e( 'Directorio Médico', 'svcp-directorio' ); ?></h1>
        <p class="svcp-directorio-subtitulo"><?php esc_html_e( 'Encuentra y contacta a los médicos disponibles en el directorio.', 'svcp-directorio' ); ?></p>
    </header>

    <form class="svcp-filtros svcp-filtros-directorio svcp-directorio-buscador-principal" method="get">
        <div class="svcp-filtro-busqueda">
            <label class="screen-reader-text" for="svcp-doctor-search"><?php esc_html_e( 'Buscar por nombre', 'svcp-directorio' ); ?></label>
            <input id="svcp-doctor-search" type="search" name="q" value="<?php echo esc_attr( $busqueda_actual ); ?>" placeholder="<?php esc_attr_e( 'Buscar por nombre del médico...', 'svcp-directorio' ); ?>" autocomplete="off">
        </div>
        <div>
            <label class="screen-reader-text" for="svcp-region-filter"><?php esc_html_e( 'Filtrar por región', 'svcp-directorio' ); ?></label>
            <select id="svcp-region-filter" name="region">
                <option value=""><?php esc_html_e( 'Todas las regiones', 'svcp-directorio' ); ?></option>
                <?php foreach ( $regiones as $region ) : ?>
                    <option value="<?php echo esc_attr( $region->slug ); ?>" <?php selected( $region_actual, $region->slug ); ?>><?php echo esc_html( $region->name ); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit"><?php esc_html_e( 'Buscar', 'svcp-directorio' ); ?></button>
        <?php if ( $busqueda_actual || $region_actual ) : ?>
            <a class="svcp-filtro-limpiar" href="<?php echo esc_url( get_post_type_archive_link( 'doctor' ) ); ?>"><?php esc_html_e( 'Limpiar', 'svcp-directorio' ); ?></a>
        <?php endif; ?>
    </form>

    <?php if ( ! $busqueda_actual && ! $region_actual ) : ?>
        <section class="svcp-regiones-directorio" aria-labelledby="svcp-regiones-titulo">
            <div class="svcp-regiones-cabecera">
                <div>
                    <span class="svcp-directorio-eyebrow"><?php esc_html_e( 'Explora por ubicación', 'svcp-directorio' ); ?></span>
                    <h2 id="svcp-regiones-titulo"><?php esc_html_e( 'Encuentra médicos por región', 'svcp-directorio' ); ?></h2>
                </div>
                <p><?php esc_html_e( 'Selecciona una región para consultar los profesionales disponibles en esa zona.', 'svcp-directorio' ); ?></p>
            </div>
            <div class="svcp-regiones-grid">
                <?php foreach ( $regiones as $region ) : ?>
                    <a class="svcp-region-card" href="<?php echo esc_url( add_query_arg( 'region', $region->slug, get_post_type_archive_link( 'doctor' ) ) ); ?>">
                        <span class="svcp-region-card-icon" aria-hidden="true">⌖</span>
                        <span class="svcp-region-card-name"><?php echo esc_html( $region->name ); ?></span>
                        <span class="svcp-region-card-action"><?php esc_html_e( 'Ver médicos', 'svcp-directorio' ); ?> →</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <div class="svcp-directorio-resultados">
        <?php if ( have_posts() ) : ?>
            <div class="svcp-resultados-resumen" aria-live="polite">
                <strong><?php echo esc_html( number_format_i18n( (int) $wp_query->found_posts ) ); ?></strong>
                <?php echo esc_html( _n( 'médico encontrado', 'médicos encontrados', (int) $wp_query->found_posts, 'svcp-directorio' ) ); ?>
                <?php if ( $busqueda_actual ) : ?>
                    <span>· <?php echo esc_html( sprintf( __( 'Búsqueda: “%s”', 'svcp-directorio' ), $busqueda_actual ) ); ?></span>
                <?php endif; ?>
                <?php if ( $region_actual ) : ?>
                    <?php $region_actual_obj = get_term_by( 'slug', $region_actual, 'region' ); ?>
                    <?php if ( $region_actual_obj && ! is_wp_error( $region_actual_obj ) ) : ?>
                        <span>· <?php echo esc_html( $region_actual_obj->name ); ?></span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <div class="svcp-lista-doctores">
                <?php while ( have_posts() ) : the_post();
                    $post_id = get_the_ID();
                    $clinica = get_post_meta( $post_id, 'svcp_clinica', true );
                    $ciudad = get_post_meta( $post_id, 'svcp_ciudad', true );
                    $telefono = get_post_meta( $post_id, 'svcp_telefono', true );
                    $whatsapp = get_post_meta( $post_id, 'svcp_whatsapp', true );
                    $correo = get_post_meta( $post_id, 'svcp_correo', true );
                    $direccion = get_post_meta( $post_id, 'svcp_direccion', true );
                    $facebook = get_post_meta( $post_id, 'svcp_facebook', true );
                    $instagram = get_post_meta( $post_id, 'svcp_instagram', true );
                    $linkedin = get_post_meta( $post_id, 'svcp_linkedin', true );
                    $twitter = get_post_meta( $post_id, 'svcp_twitter', true );
                    $regiones_doctor = get_the_terms( $post_id, 'region' );
                    ?>
                    <?php
                    $region_texto = ( $regiones_doctor && ! is_wp_error( $regiones_doctor ) ) ? $regiones_doctor[0]->name : '';
                    $texto_busqueda = implode( ' ', array_filter( array( get_the_title(), $clinica, $ciudad, $direccion, $region_texto ) ) );
                    ?>
                    <article class="svcp-doctor-card" data-svcp-doctor-card data-search="<?php echo esc_attr( remove_accents( strtolower( $texto_busqueda ) ) ); ?>" data-region="<?php echo esc_attr( $region_texto ); ?>">
                        <div class="svcp-doctor-foto" aria-hidden="true">
                            <?php if ( has_post_thumbnail() ) : the_post_thumbnail( 'medium', array( 'loading' => 'lazy' ) ); else : ?><div class="svcp-doctor-foto-placeholder"><span><?php echo esc_html( strtoupper( implode( '', array_slice( preg_split( '/\s+/', trim( wp_strip_all_tags( get_the_title() ) ) ), 0, 2 ) ) ) ); ?></span></div><?php endif; ?>
                        </div>
                        <div class="svcp-doctor-info">
                            <h2 class="svcp-doctor-nombre"><?php the_title(); ?></h2>
                            <?php if ( $region_texto ) : ?><p class="svcp-doctor-region"><?php echo esc_html( $region_texto ); ?></p><?php endif; ?>
                            <?php if ( $clinica ) : ?><p class="svcp-doctor-clinica"><strong><?php echo esc_html( $clinica ); ?></strong></p><?php endif; ?>
                            <?php if ( $direccion ) : ?><p class="svcp-doctor-direccion"><?php echo esc_html( $direccion ); ?></p><?php endif; ?>
                            <?php if ( $ciudad ) : ?><p class="svcp-doctor-ciudad"><?php echo esc_html( $ciudad ); ?></p><?php endif; ?>
                            <div class="svcp-doctor-acciones svcp-doctor-contact-actions">
                                <?php if ( $telefono ) : ?><a class="svcp-contact-icon" href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $telefono ) ); ?>" aria-label="<?php esc_attr_e( 'Llamar al médico', 'svcp-directorio' ); ?>">☎ <span><?php echo esc_html( $telefono ); ?></span></a><?php endif; ?>
                                <?php if ( $correo && is_email( $correo ) ) : ?><a class="svcp-contact-icon svcp-contact-email" href="mailto:<?php echo esc_attr( $correo ); ?>">✉ <span><?php echo esc_html( $correo ); ?></span></a><?php endif; ?>
                                <?php if ( $whatsapp ) : ?><a class="svcp-social-icon" href="https://wa.me/<?php echo esc_attr( preg_replace( '/[^0-9]/', '', $whatsapp ) ); ?>" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp">WA</a><?php endif; ?>
                                <?php if ( $instagram ) : ?><a class="svcp-social-icon" href="<?php echo esc_url( $instagram ); ?>" target="_blank" rel="noopener noreferrer" aria-label="Instagram">◎</a><?php endif; ?>
                                <?php if ( $facebook ) : ?><a class="svcp-social-icon" href="<?php echo esc_url( $facebook ); ?>" target="_blank" rel="noopener noreferrer" aria-label="Facebook">f</a><?php endif; ?>
                                <?php if ( $linkedin ) : ?><a class="svcp-social-icon" href="<?php echo esc_url( $linkedin ); ?>" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn">in</a><?php endif; ?>
                                <?php if ( $twitter ) : ?><a class="svcp-social-icon" href="<?php echo esc_url( $twitter ); ?>" target="_blank" rel="noopener noreferrer" aria-label="X">𝕏</a><?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endwhile; ?>
            </div>
            <div class="svcp-paginacion"><?php the_posts_pagination(); ?></div>
        <?php else : ?>
            <div class="svcp-sin-resultados-box">
                <h2><?php esc_html_e( 'No encontramos resultados', 'svcp-directorio' ); ?></h2>
                <p><?php esc_html_e( 'Prueba con otro nombre o selecciona otra región.', 'svcp-directorio' ); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php get_footer(); ?>
