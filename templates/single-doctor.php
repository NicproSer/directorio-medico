<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
while ( have_posts() ) : the_post();
    $post_id = get_the_ID();
    $clinica = get_post_meta( $post_id, 'svcp_clinica', true );
    $telefono = get_post_meta( $post_id, 'svcp_telefono', true );
    $whatsapp = get_post_meta( $post_id, 'svcp_whatsapp', true );
    $correo = get_post_meta( $post_id, 'svcp_correo', true );
    $direccion = get_post_meta( $post_id, 'svcp_direccion', true );
    $ciudad = get_post_meta( $post_id, 'svcp_ciudad', true );
    $regiones = get_the_terms( $post_id, 'region' );
    $redes = array(
        'Facebook' => get_post_meta( $post_id, 'svcp_facebook', true ),
        'Instagram' => get_post_meta( $post_id, 'svcp_instagram', true ),
        'Twitter / X' => get_post_meta( $post_id, 'svcp_twitter', true ),
        'LinkedIn' => get_post_meta( $post_id, 'svcp_linkedin', true ),
        'Sitio web' => get_post_meta( $post_id, 'svcp_sitio_web', true ),
    );
    ?>
    <main class="svcp-perfil-publico">
        <div class="svcp-perfil-contenedor">
            <a class="svcp-volver-directorio" href="<?php echo esc_url( get_post_type_archive_link( 'doctor' ) ); ?>">&larr; <?php esc_html_e( 'Volver al directorio', 'svcp-directorio' ); ?></a>
            <div class="svcp-doctor-perfil">
                <aside class="svcp-doctor-perfil-lateral">
                    <div class="svcp-doctor-perfil-foto">
                        <?php if ( has_post_thumbnail() ) : the_post_thumbnail( 'large', array( 'loading' => 'eager' ) ); else : ?><div class="svcp-doctor-foto-placeholder" aria-hidden="true"><span><?php esc_html_e( 'Sin foto', 'svcp-directorio' ); ?></span></div><?php endif; ?>
                    </div>
                </aside>

                <section class="svcp-doctor-perfil-datos">
                    <span class="svcp-directorio-eyebrow"><?php esc_html_e( 'Directorio Médico SVCP', 'svcp-directorio' ); ?></span>
                    <h1 class="svcp-doctor-perfil-nombre"><?php the_title(); ?></h1>
                    <?php if ( $regiones && ! is_wp_error( $regiones ) ) : ?><p class="svcp-doctor-perfil-region"><?php echo esc_html( $regiones[0]->name ); ?></p><?php endif; ?>

                    <div class="svcp-perfil-grid">
                        <?php if ( $clinica ) : ?><div class="svcp-perfil-dato"><span>Clínica / Hospital</span><strong><?php echo esc_html( $clinica ); ?></strong></div><?php endif; ?>
                        <?php if ( $ciudad ) : ?><div class="svcp-perfil-dato"><span>Ciudad</span><strong><?php echo esc_html( $ciudad ); ?></strong></div><?php endif; ?>
                        <?php if ( $direccion ) : ?><div class="svcp-perfil-dato svcp-perfil-dato-ancho"><span>Dirección</span><strong><?php echo esc_html( $direccion ); ?></strong></div><?php endif; ?>
                    </div>

                    <div class="svcp-doctor-contacto svcp-perfil-contacto">
                        <?php if ( $telefono ) : ?><a class="svcp-contacto-principal" href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $telefono ) ); ?>">Llamar</a><?php endif; ?>
                        <?php if ( $whatsapp ) : ?><a class="svcp-contacto-whatsapp" href="https://wa.me/<?php echo esc_attr( preg_replace( '/[^0-9]/', '', $whatsapp ) ); ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
                        <?php if ( $correo ) : ?><a href="mailto:<?php echo esc_attr( $correo ); ?>">Correo</a><?php endif; ?>
                    </div>

                    <?php if ( array_filter( $redes ) ) : ?>
                        <div class="svcp-doctor-redes svcp-perfil-redes">
                            <h2><?php esc_html_e( 'Presencia digital', 'svcp-directorio' ); ?></h2>
                            <div><?php foreach ( $redes as $nombre => $url ) : if ( $url ) : ?><a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $nombre ); ?></a><?php endif; endforeach; ?></div>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </main>
<?php endwhile; get_footer();
