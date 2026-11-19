<?php
/**
 * Importación y exportación CSV del Directorio Médico.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SVCP_Importacion {
    private static $instancia = null;

    private $campos = array(
        'id' => 'ID',
        'nombre' => 'Nombre',
        'correo' => 'Correo',
        'correo_2' => 'Correo 2',
        'correo_3' => 'Correo 3',
        'telefono' => 'Telefono',
        'telefono_2' => 'Telefono 2',
        'telefono_3' => 'Telefono 3',
        'whatsapp' => 'WhatsApp',
        'clinica' => 'Clinica',
        'clinica_2' => 'Clinica 2',
        'clinica_3' => 'Clinica 3',
        'direccion' => 'Direccion',
        'direccion_2' => 'Direccion 2',
        'direccion_3' => 'Direccion 3',
        'ciudad' => 'Ciudad',
        'region' => 'Region',
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'twitter' => 'Twitter',
        'linkedin' => 'LinkedIn',
        'sitio_web' => 'Sitio web',
        'estado_ficha' => 'Estado ficha',
        'membresia_estado' => 'Estado membresia',
        'membresia_inicio' => 'Membresia inicio',
        'membresia_vencimiento' => 'Membresia vencimiento',
        'membresia_ultimo_pago' => 'Ultimo pago',
        'membresia_metodo_pago' => 'Metodo pago',
        'membresia_referencia' => 'Referencia pago',
        'membresia_monto' => 'Monto',
    );

    public static function instance() {
        if ( null === self::$instancia ) { self::$instancia = new self(); }
        return self::$instancia;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'menu' ), 25 );
        add_action( 'admin_post_svcp_exportar_csv', array( $this, 'exportar' ) );
        add_action( 'admin_post_svcp_importar_csv', array( $this, 'importar' ) );
    }

    public function menu() {
        add_submenu_page(
            'edit.php?post_type=doctor',
            __( 'Importar / Exportar', 'svcp-directorio' ),
            __( 'Importar / Exportar', 'svcp-directorio' ),
            'manage_options',
            'svcp-importar-exportar',
            array( $this, 'pagina' )
        );
    }

    private function url_admin( $args = array() ) {
        return add_query_arg( $args, admin_url( 'edit.php?post_type=doctor&page=svcp-importar-exportar' ) );
    }

    public function pagina() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'No tienes permisos para acceder a esta sección.', 'svcp-directorio' ) ); }
        $mensaje = isset( $_GET['svcp_importado'] ) ? sanitize_key( wp_unslash( $_GET['svcp_importado'] ) ) : '';
        $errores = isset( $_GET['svcp_errores'] ) ? absint( $_GET['svcp_errores'] ) : 0;
        $creados = isset( $_GET['svcp_creados'] ) ? absint( $_GET['svcp_creados'] ) : 0;
        $actualizados = isset( $_GET['svcp_actualizados'] ) ? absint( $_GET['svcp_actualizados'] ) : 0;
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Importar / Exportar médicos', 'svcp-directorio' ); ?></h1>
            <?php if ( 'ok' === $mensaje ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php printf( esc_html__( 'Importación completada: %d creados, %d actualizados, %d filas con error.', 'svcp-directorio' ), $creados, $actualizados, $errores ); ?></p></div>
            <?php endif; ?>

            <div style="max-width:900px;display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:20px;margin-top:20px;">
                <div style="background:#fff;border:1px solid #dcdcde;padding:24px;">
                    <h2><?php esc_html_e( 'Exportar directorio', 'svcp-directorio' ); ?></h2>
                    <p><?php esc_html_e( 'Descarga todos los médicos en un CSV compatible con Excel. Incluye datos de contacto, región, estado de ficha y datos administrativos de membresía.', 'svcp-directorio' ); ?></p>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="svcp_exportar_csv">
                        <?php wp_nonce_field( 'svcp_exportar_csv' ); ?>
                        <button class="button button-primary"><?php esc_html_e( 'Descargar CSV', 'svcp-directorio' ); ?></button>
                    </form>
                </div>

                <div style="background:#fff;border:1px solid #dcdcde;padding:24px;">
                    <h2><?php esc_html_e( 'Importar directorio', 'svcp-directorio' ); ?></h2>
                    <p><?php esc_html_e( 'Carga un CSV. Las filas se actualizan por ID o por correo. Si no existe coincidencia, se crea una nueva ficha. No se permiten duplicados por correo ni por nombre + región + ciudad + clínica.', 'svcp-directorio' ); ?></p>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="svcp_importar_csv">
                        <?php wp_nonce_field( 'svcp_importar_csv' ); ?>
                        <input type="file" name="svcp_csv" accept=".csv,text/csv" required>
                        <p><label><input type="checkbox" name="actualizar_existentes" value="1" checked> <?php esc_html_e( 'Actualizar fichas existentes cuando haya coincidencia', 'svcp-directorio' ); ?></label></p>
                        <button class="button button-primary"><?php esc_html_e( 'Importar CSV', 'svcp-directorio' ); ?></button>
                    </form>
                </div>
            </div>

            <div style="background:#fff;border:1px solid #dcdcde;padding:24px;margin-top:20px;max-width:900px;">
                <h2><?php esc_html_e( 'Columnas admitidas', 'svcp-directorio' ); ?></h2>
                <p><code><?php echo esc_html( implode( ', ', array_values( $this->campos ) ) ); ?></code></p>
                <p class="description"><?php esc_html_e( 'Puedes usar el CSV exportado como plantilla. La primera fila debe contener los encabezados. Se detectan automáticamente separadores coma (,) y punto y coma (;).', 'svcp-directorio' ); ?></p>
                <p><strong><?php esc_html_e( 'Importante:', 'svcp-directorio' ); ?></strong> <?php esc_html_e( 'Las columnas de membresía solo son aplicadas por administradores. La importación no publica automáticamente fichas pendientes.', 'svcp-directorio' ); ?></p>
            </div>
        </div>
        <?php
    }

    public function exportar() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'No tienes permisos.', 'svcp-directorio' ) ); }
        check_admin_referer( 'svcp_exportar_csv' );

        $filename = 'svcp-directorio-' . gmdate( 'Y-m-d-H-i' ) . '.csv';
        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=' . $filename );
        header( 'Pragma: no-cache' );

        $out = fopen( 'php://output', 'w' );
        // BOM para que Excel reconozca UTF-8.
        fwrite( $out, "\xEF\xBB\xBF" );
        fputcsv( $out, array_values( $this->campos ) );

        $ids = get_posts( array(
            'post_type' => 'doctor', 'post_status' => 'any', 'posts_per_page' => -1,
            'orderby' => 'title', 'order' => 'ASC', 'fields' => 'ids',
        ) );

        foreach ( $ids as $id ) {
            $region = wp_get_post_terms( $id, 'region', array( 'fields' => 'names' ) );
            $telefonos = get_post_meta( $id, 'svcp_telefonos', true );
            if ( ! is_array( $telefonos ) || empty( $telefonos ) ) { $telefonos = array( get_post_meta( $id, 'svcp_telefono', true ) ); }
            $clinicas = get_post_meta( $id, 'svcp_clinicas', true );
            if ( ! is_array( $clinicas ) || empty( $clinicas ) ) { $clinicas = array( get_post_meta( $id, 'svcp_clinica', true ) ); }
            $direcciones = get_post_meta( $id, 'svcp_direcciones', true );
            if ( ! is_array( $direcciones ) || empty( $direcciones ) ) { $direcciones = array( get_post_meta( $id, 'svcp_direccion', true ) ); }
            $correos = get_post_meta( $id, 'svcp_correos', true );
            if ( ! is_array( $correos ) || empty( $correos ) ) { $correos = array( get_post_meta( $id, 'svcp_correo', true ) ); }
            $row = array(
                $id,
                get_the_title( $id ),
                $correos[0] ?? '',
                $correos[1] ?? '',
                $correos[2] ?? '',
                $telefonos[0] ?? '',
                $telefonos[1] ?? '',
                $telefonos[2] ?? '',
                get_post_meta( $id, 'svcp_whatsapp', true ),
                $clinicas[0] ?? '',
                $clinicas[1] ?? '',
                $clinicas[2] ?? '',
                $direcciones[0] ?? '',
                $direcciones[1] ?? '',
                $direcciones[2] ?? '',
                get_post_meta( $id, 'svcp_ciudad', true ),
                ! empty( $region ) ? $region[0] : '',
                get_post_meta( $id, 'svcp_facebook', true ),
                get_post_meta( $id, 'svcp_instagram', true ),
                get_post_meta( $id, 'svcp_twitter', true ),
                get_post_meta( $id, 'svcp_linkedin', true ),
                get_post_meta( $id, 'svcp_sitio_web', true ),
                get_post_status( $id ),
                class_exists( 'SVCP_Membresia' ) ? SVCP_Membresia::obtener_estado( $id ) : get_post_meta( $id, 'svcp_membresia_estado', true ),
                get_post_meta( $id, 'svcp_membresia_inicio', true ),
                get_post_meta( $id, 'svcp_membresia_vencimiento', true ),
                get_post_meta( $id, 'svcp_membresia_ultimo_pago', true ),
                get_post_meta( $id, 'svcp_membresia_metodo_pago', true ),
                get_post_meta( $id, 'svcp_membresia_referencia', true ),
                get_post_meta( $id, 'svcp_membresia_monto', true ),
            );
            fputcsv( $out, $row );
        }
        fclose( $out );
        exit;
    }

    private function detectar_delimitador( $linea ) {
        return substr_count( $linea, ';' ) > substr_count( $linea, ',' ) ? ';' : ',';
    }

    private function limpiar_encabezado( $value ) {
        $value = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $value );
        return str_replace( '-', '_', sanitize_title( remove_accents( trim( $value ) ) ) );
    }

    private function normalizar_nombre( $value ) {
        return strtolower( trim( preg_replace( '/\s+/', ' ', remove_accents( wp_strip_all_tags( (string) $value ) ) ) ) );
    }

    private function normalizar_email( $value ) {
        return strtolower( trim( sanitize_email( $value ) ) );
    }

    private function encontrar_doctor( $data ) {
        $id = isset( $data['id'] ) ? absint( $data['id'] ) : 0;
        if ( $id && 'doctor' === get_post_type( $id ) ) { return $id; }

        $email = isset( $data['correo'] ) ? $this->normalizar_email( $data['correo'] ) : '';
        if ( $email ) {
            $ids = get_posts( array(
                'post_type' => 'doctor', 'post_status' => 'any', 'posts_per_page' => 2, 'fields' => 'ids',
                'meta_query' => array(array( 'key' => 'svcp_correo', 'value' => $email, 'compare' => '=' )),
            ) );
            if ( count( $ids ) === 1 ) { return (int) $ids[0]; }
            if ( count( $ids ) > 1 ) { return -1; }
        }

        $nombre = isset( $data['nombre'] ) ? $this->normalizar_nombre( $data['nombre'] ) : '';
        $region = isset( $data['region'] ) ? $this->normalizar_nombre( $data['region'] ) : '';
        $ciudad = isset( $data['ciudad'] ) ? $this->normalizar_nombre( $data['ciudad'] ) : '';
        $clinica = isset( $data['clinica'] ) ? $this->normalizar_nombre( $data['clinica'] ) : '';
        if ( ! $nombre ) { return 0; }

        $ids = get_posts( array( 'post_type' => 'doctor', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) );
        $matches = array();
        foreach ( $ids as $doctor_id ) {
            if ( $this->normalizar_nombre( get_the_title( $doctor_id ) ) !== $nombre ) { continue; }
            $terms = wp_get_post_terms( $doctor_id, 'region', array( 'fields' => 'names' ) );
            $r = ! empty( $terms ) ? $this->normalizar_nombre( $terms[0] ) : '';
            $c = $this->normalizar_nombre( get_post_meta( $doctor_id, 'svcp_ciudad', true ) );
            $cl = $this->normalizar_nombre( get_post_meta( $doctor_id, 'svcp_clinica', true ) );
            if ( $region && $r !== $region ) { continue; }
            if ( $ciudad && $c !== $ciudad ) { continue; }
            if ( $clinica && $cl !== $clinica ) { continue; }
            $matches[] = (int) $doctor_id;
        }
        if ( count( $matches ) === 1 ) { return $matches[0]; }
        if ( count( $matches ) > 1 ) { return -1; }
        return 0;
    }

    private function mapear_region( $region ) {
        $objetivo = $this->normalizar_nombre( $region );
        foreach ( SVCP_Taxonomias::regiones() as $permitida ) {
            if ( $this->normalizar_nombre( $permitida ) === $objetivo ) { return $permitida; }
        }
        return '';
    }

    private function actualizar_meta( $id, $data, $clave, $meta ) {
        if ( ! array_key_exists( $clave, $data ) ) { return; }
        $value = trim( (string) $data[ $clave ] );
        if ( '' === $value ) { delete_post_meta( $id, $meta ); }
        else { update_post_meta( $id, $meta, sanitize_text_field( $value ) ); }
    }

    public function importar() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'No tienes permisos.', 'svcp-directorio' ) ); }
        check_admin_referer( 'svcp_importar_csv' );
        if ( empty( $_FILES['svcp_csv']['tmp_name'] ) || ! empty( $_FILES['svcp_csv']['error'] ) ) { wp_die( esc_html__( 'No se pudo leer el archivo CSV.', 'svcp-directorio' ) ); }

        $file = $_FILES['svcp_csv'];
        if ( ! empty( $file['size'] ) && $file['size'] > 5 * MB_IN_BYTES ) { wp_die( esc_html__( 'El archivo supera el límite de 5 MB.', 'svcp-directorio' ) ); }
        $handle = fopen( $file['tmp_name'], 'r' );
        if ( ! $handle ) { wp_die( esc_html__( 'No se pudo abrir el CSV.', 'svcp-directorio' ) ); }

        $first = fgets( $handle );
        if ( false === $first ) { fclose( $handle ); wp_die( esc_html__( 'El CSV está vacío.', 'svcp-directorio' ) ); }
        $delimiter = $this->detectar_delimitador( $first );
        rewind( $handle );
        $headers = fgetcsv( $handle, 0, $delimiter );
        if ( ! is_array( $headers ) ) { fclose( $handle ); wp_die( esc_html__( 'No se pudieron leer los encabezados.', 'svcp-directorio' ) ); }
        $map = array();
        foreach ( $headers as $i => $header ) { $map[ $i ] = $this->limpiar_encabezado( $header ); }

        $permitidas = array_keys( $this->campos );
        $creados = $actualizados = $errores = 0;
        $fila = 1;
        $actualizar_existentes = ! empty( $_POST['actualizar_existentes'] );

        while ( ( $row = fgetcsv( $handle, 0, $delimiter ) ) !== false ) {
            $fila++;
            if ( count( array_filter( $row, 'strlen' ) ) === 0 ) { continue; }
            $data = array();
            foreach ( $row as $i => $value ) {
                if ( isset( $map[ $i ] ) && in_array( $map[ $i ], $permitidas, true ) ) { $data[ $map[ $i ] ] = trim( $value ); }
            }
            $nombre = isset( $data['nombre'] ) ? sanitize_text_field( $data['nombre'] ) : '';
            $correo = isset( $data['correo'] ) ? $this->normalizar_email( $data['correo'] ) : '';
            if ( '' === $nombre ) { $errores++; continue; }
            if ( $correo && ! is_email( $correo ) ) { $errores++; continue; }

            $region = isset( $data['region'] ) ? $this->mapear_region( $data['region'] ) : '';
            if ( isset( $data['region'] ) && '' !== trim( $data['region'] ) && ! $region ) { $errores++; continue; }

            $id = $this->encontrar_doctor( $data );
            if ( -1 === $id ) { $errores++; continue; }
            if ( $id && ! $actualizar_existentes ) { $errores++; continue; }

            if ( ! $id ) {
                $id = wp_insert_post( array( 'post_type' => 'doctor', 'post_title' => $nombre, 'post_status' => 'pending' ), true );
                if ( ! is_wp_error( $id ) && $id && class_exists( 'SVCP_Aprobacion' ) ) { update_post_meta( $id, SVCP_Aprobacion::META, SVCP_Aprobacion::PENDIENTE_REVISION ); }
                if ( is_wp_error( $id ) ) { $errores++; continue; }
                $creados++;
            } else {
                wp_update_post( array( 'ID' => $id, 'post_title' => $nombre ) );
                $actualizados++;
            }

            $campos = array(
                'correo' => 'svcp_correo', 'telefono' => 'svcp_telefono', 'whatsapp' => 'svcp_whatsapp',
                'clinica' => 'svcp_clinica', 'direccion' => 'svcp_direccion', 'ciudad' => 'svcp_ciudad',
                'facebook' => 'svcp_facebook', 'instagram' => 'svcp_instagram', 'twitter' => 'svcp_twitter',
                'linkedin' => 'svcp_linkedin', 'sitio_web' => 'svcp_sitio_web',
                'membresia_inicio' => 'svcp_membresia_inicio', 'membresia_vencimiento' => 'svcp_membresia_vencimiento',
                'membresia_ultimo_pago' => 'svcp_membresia_ultimo_pago', 'membresia_metodo_pago' => 'svcp_membresia_metodo_pago',
                'membresia_referencia' => 'svcp_membresia_referencia', 'membresia_monto' => 'svcp_membresia_monto',
            );
            foreach ( $campos as $source => $meta ) { $this->actualizar_meta( $id, $data, $source, $meta ); }
            // Mantiene sincronizados los campos históricos y las nuevas listas al importar CSV.
            if ( array_key_exists( 'direccion', $data ) || array_key_exists( 'direccion_2', $data ) || array_key_exists( 'direccion_3', $data ) ) {
                $direcciones_importadas = array();
                foreach ( array( 'direccion', 'direccion_2', 'direccion_3' ) as $clave_direccion ) {
                    $valor = sanitize_text_field( $data[ $clave_direccion ] ?? '' );
                    if ( '' !== trim( $valor ) ) { $direcciones_importadas[] = $valor; }
                }
                update_post_meta( $id, 'svcp_direcciones', $direcciones_importadas );
                update_post_meta( $id, 'svcp_direccion', $direcciones_importadas[0] ?? '' );
            }
            if ( array_key_exists( 'correo', $data ) || array_key_exists( 'correo_2', $data ) || array_key_exists( 'correo_3', $data ) ) {
                $correos_importados = array();
                foreach ( array( 'correo', 'correo_2', 'correo_3' ) as $clave_correo ) {
                    $valor = sanitize_email( $data[ $clave_correo ] ?? '' );
                    if ( is_email( $valor ) ) { $correos_importados[] = $valor; }
                }
                update_post_meta( $id, 'svcp_correos', $correos_importados );
                update_post_meta( $id, 'svcp_correo', $correos_importados[0] ?? '' );
            }
            if ( array_key_exists( 'telefono', $data ) || array_key_exists( 'telefono_2', $data ) || array_key_exists( 'telefono_3', $data ) ) {
                $telefonos_importados = array();
                foreach ( array( 'telefono', 'telefono_2', 'telefono_3' ) as $clave_telefono ) {
                    $valor = sanitize_text_field( $data[ $clave_telefono ] ?? '' );
                    if ( '' !== $valor ) { $telefonos_importados[] = $valor; }
                }
                update_post_meta( $id, 'svcp_telefonos', $telefonos_importados );
                update_post_meta( $id, 'svcp_telefono', $telefonos_importados[0] ?? '' );
            }
            if ( array_key_exists( 'clinica', $data ) || array_key_exists( 'clinica_2', $data ) || array_key_exists( 'clinica_3', $data ) ) {
                $clinicas_importadas = array();
                foreach ( array( 'clinica', 'clinica_2', 'clinica_3' ) as $clave_clinica ) {
                    $valor = sanitize_text_field( $data[ $clave_clinica ] ?? '' );
                    if ( '' !== $valor ) { $clinicas_importadas[] = $valor; }
                }
                update_post_meta( $id, 'svcp_clinicas', $clinicas_importadas );
                update_post_meta( $id, 'svcp_clinica', $clinicas_importadas[0] ?? '' );
            }
            if ( $region ) { wp_set_object_terms( $id, array( $region ), 'region', false ); }

            if ( isset( $data['membresia_estado'] ) ) {
                $estado = sanitize_key( $data['membresia_estado'] );
                if ( SVCP_Membresia::ESTADO_SUSPENDIDA === $estado ) { update_post_meta( $id, 'svcp_membresia_estado', $estado ); }
                else { delete_post_meta( $id, 'svcp_membresia_estado' ); }
            }
            // Importar estado de ficha solo permite estados seguros; no permite publicar desde CSV.
            if ( isset( $data['estado_ficha'] ) && in_array( $data['estado_ficha'], array( 'pending', 'draft', 'private', 'trash' ), true ) ) {
                wp_update_post( array( 'ID' => $id, 'post_status' => $data['estado_ficha'] ) );
            }
        }
        fclose( $handle );

        wp_safe_redirect( $this->url_admin( array( 'svcp_importado' => 'ok', 'svcp_creados' => $creados, 'svcp_actualizados' => $actualizados, 'svcp_errores' => $errores ) ) );
        exit;
    }
}
