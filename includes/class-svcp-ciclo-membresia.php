<?php
/** Configuración del ciclo institucional compartido por todas las membresías. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
class SVCP_Ciclo_Membresia {
    private static $instance = null;
    const OPTION = 'svcp_ciclo_membresia';
    public static function instance() { return self::$instance ?: ( self::$instance = new self() ); }
    private function __construct() { add_action( 'admin_menu', array( $this, 'menu' ), 30 ); }
    public static function defaults() { return array( 'anio_inicio' => (int) current_time( 'Y' ), 'mes_inicio' => 8, 'dia_inicio' => 1, 'dias_aviso' => '30,15,7', 'precio_membresia' => '0.00', 'precio_membresia_usd' => '0.00', 'payment_bank' => '', 'payment_account_type' => '', 'payment_account_number' => '', 'payment_holder' => '', 'payment_document' => '', 'payment_phone' => '', 'payment_email' => '', 'payment_instructions' => '' ); }
    public static function settings() { $saved = get_option( self::OPTION, array() ); if ( ! is_array( $saved ) ) { $saved = array(); } $legacy = get_option( 'svcp_smtp_config', array() ); if ( is_array( $legacy ) ) { foreach ( array( 'payment_bank','payment_account_type','payment_account_number','payment_holder','payment_document','payment_phone','payment_email','payment_instructions' ) as $key ) { if ( empty( $saved[$key] ) && ! empty( $legacy[$key] ) ) { $saved[$key] = $legacy[$key]; } } } return wp_parse_args( $saved, self::defaults() ); }
    public function menu() { add_submenu_page( 'edit.php?post_type=doctor', 'Configuración de membresías', 'Configuración de membresías', 'manage_options', 'svcp-ciclo-membresia', array( $this, 'render' ) ); }
    public function render() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        if ( isset( $_POST['svcp_guardar_ciclo'] ) ) {
            check_admin_referer( 'svcp_guardar_ciclo' );
            $anio = isset( $_POST['anio_inicio'] ) ? absint( $_POST['anio_inicio'] ) : (int) current_time( 'Y' );
            $mes = isset( $_POST['mes_inicio'] ) ? absint( $_POST['mes_inicio'] ) : 8;
            $dia = isset( $_POST['dia_inicio'] ) ? absint( $_POST['dia_inicio'] ) : 1;
            $dias = isset( $_POST['dias_aviso'] ) ? sanitize_text_field( wp_unslash( $_POST['dias_aviso'] ) ) : '30,15,7';
            $precio_raw = isset( $_POST['precio_membresia'] ) ? str_replace( ',', '.', sanitize_text_field( wp_unslash( $_POST['precio_membresia'] ) ) ) : '0';
            $precio = is_numeric( $precio_raw ) ? max( 0, min( 999999999.99, (float) $precio_raw ) ) : 0;
            $precio_usd_raw = isset( $_POST['precio_membresia_usd'] ) ? str_replace( ',', '.', sanitize_text_field( wp_unslash( $_POST['precio_membresia_usd'] ) ) ) : '0';
            $precio_usd = is_numeric( $precio_usd_raw ) ? max( 0, min( 999999999.99, (float) $precio_usd_raw ) ) : 0;
            $datos_pago = array(); foreach ( array( 'payment_bank','payment_account_type','payment_account_number','payment_holder','payment_document','payment_phone','payment_email','payment_instructions' ) as $key ) { $raw = isset( $_POST[$key] ) ? wp_unslash( $_POST[$key] ) : ''; $datos_pago[$key] = 'payment_instructions' === $key ? sanitize_textarea_field( $raw ) : ( 'payment_email' === $key ? sanitize_email( $raw ) : sanitize_text_field( $raw ) ); } if ( $datos_pago['payment_email'] && ! is_email( $datos_pago['payment_email'] ) ) { $datos_pago['payment_email'] = ''; }
            $anio = min( (int) current_time( 'Y' ) + 20, max( 2000, $anio ) );
            $mes = min( 12, max( 1, $mes ) ); $dia = min( 28, max( 1, $dia ) );
            $valid = array(); foreach ( explode( ',', $dias ) as $n ) { $n = absint( trim( $n ) ); if ( $n > 0 && $n <= 180 ) $valid[] = $n; }
            update_option( self::OPTION, array_merge( array( 'anio_inicio' => $anio, 'mes_inicio' => $mes, 'dia_inicio' => $dia, 'dias_aviso' => implode( ',', array_unique( $valid ) ), 'precio_membresia' => number_format( $precio, 2, '.', '' ), 'precio_membresia_usd' => number_format( $precio_usd, 2, '.', '' ) ), $datos_pago ), false );
            echo '<div class="notice notice-success is-dismissible"><p>Configuración guardada.</p></div>';
        }
        $s = self::settings(); $meses = array( 1=>'enero',2=>'febrero',3=>'marzo',4=>'abril',5=>'mayo',6=>'junio',7=>'julio',8=>'agosto',9=>'septiembre',10=>'octubre',11=>'noviembre',12=>'diciembre' );
        echo '<div class="wrap"><h1>Configuración de membresías SVCP</h1><p>Todos los doctores comparten el mismo ciclo institucional. El pago aprobado no suma 365 días individuales: habilita al doctor hasta el vencimiento común del ciclo correspondiente.</p><form method="post">';
        wp_nonce_field( 'svcp_guardar_ciclo' );
        echo '<table class="form-table"><tr><th><label for="anio_inicio">Año de inicio del ciclo</label></th><td><input id="anio_inicio" name="anio_inicio" type="number" min="2000" max="'.esc_attr((int)current_time('Y')+20).'" value="'.esc_attr($s['anio_inicio']).'" class="small-text"><p class="description">Año en que comienza el primer ciclo institucional. Los siguientes ciclos se calculan anualmente a partir de esta fecha.</p></td></tr><tr><th><label for="mes_inicio">Mes de inicio del ciclo</label></th><td><select id="mes_inicio" name="mes_inicio">';
        foreach ( $meses as $n=>$nombre ) echo '<option value="'.esc_attr($n).'" '.selected($s['mes_inicio'],$n,false).'>'.esc_html(ucfirst($nombre)).'</option>';
        echo '</select></td></tr><tr><th><label for="dia_inicio">Día de inicio</label></th><td><input id="dia_inicio" name="dia_inicio" type="number" min="1" max="28" value="'.esc_attr($s['dia_inicio']).'"> <p class="description">Limitado al día 28 para que el ciclo sea válido todos los años.</p></td></tr><tr><th><label for="dias_aviso">Avisos de vencimiento (días antes)</label></th><td><input id="dias_aviso" name="dias_aviso" type="text" class="regular-text" value="'.esc_attr($s['dias_aviso']).'"><p class="description">Separados por coma, por ejemplo: 30,15,7.</p></td></tr><tr><th><label for="precio_membresia_usd">Precio institucional (USD)</label></th><td><input id="precio_membresia_usd" name="precio_membresia_usd" type="number" min="0" step="0.01" max="999999999.99" value="'.esc_attr($s['precio_membresia_usd']).'" class="regular-text"><p class="description">Este será el precio base. El equivalente en bolívares se calcula con la tasa BCV vigente para la fecha del pago. Un valor de 0 oculta el precio sugerido.</p></td></tr></table><h2>Datos para realizar pagos</h2><p>Estos datos se mostrarán a los médicos en Membresía y pago.</p><table class="form-table"><tr><th>Banco</th><td><input class="regular-text" name="payment_bank" value="'.esc_attr($s['payment_bank']).'"></td></tr><tr><th>Tipo de cuenta</th><td><input class="regular-text" name="payment_account_type" value="'.esc_attr($s['payment_account_type']).'"></td></tr><tr><th>Número de cuenta</th><td><input class="regular-text" name="payment_account_number" value="'.esc_attr($s['payment_account_number']).'"></td></tr><tr><th>Titular</th><td><input class="regular-text" name="payment_holder" value="'.esc_attr($s['payment_holder']).'"></td></tr><tr><th>RIF / Cédula</th><td><input class="regular-text" name="payment_document" value="'.esc_attr($s['payment_document']).'"></td></tr><tr><th>Teléfono pago móvil</th><td><input class="regular-text" name="payment_phone" value="'.esc_attr($s['payment_phone']).'"></td></tr><tr><th>Correo asociado</th><td><input class="regular-text" type="email" name="payment_email" value="'.esc_attr($s['payment_email']).'"></td></tr><tr><th>Instrucciones</th><td><textarea class="large-text" rows="4" name="payment_instructions">'.esc_textarea($s['payment_instructions']).'</textarea></td></tr><tr><th>Tasa BCV USD/VES</th><td></td></tr></table><p><button class="button button-primary" name="svcp_guardar_ciclo" value="1">Guardar configuración</button></p></form>';
        $tasa = class_exists('SVCP_Tasa_BCV') ? SVCP_Tasa_BCV::obtener('', false) : new WP_Error('sin_servicio','Servicio no disponible');
        if ( ! is_wp_error($tasa) ) { echo '<div class="notice notice-info inline"><p><strong>USD 1 = Bs. '.esc_html(number_format_i18n((float)$tasa['tasa'], 4)).'</strong> · Vigencia: '.esc_html(wp_date('d/m/Y',strtotime($tasa['fecha_vigencia']),wp_timezone())).' · Fuente: BCV Today (replica de datos publicados por el BCV).</p></div>'; } else { echo '<div class="notice notice-warning inline"><p>No fue posible consultar la tasa BCV ahora. '.esc_html($tasa->get_error_message()).'</p></div>'; }
        $c = self::calcular_ciclo(); echo '<hr><h2>Ciclo calculado actualmente</h2><p><strong>Inicio:</strong> '.esc_html(wp_date('d/m/Y',strtotime($c['inicio']),wp_timezone())).' &nbsp; <strong>Vencimiento común:</strong> '.esc_html(wp_date('d/m/Y',strtotime($c['fin']),wp_timezone())).'</p></div>';
    }
    /** Retorna el ciclo activo o el próximo si aún no ha comenzado. */
    public static function calcular_ciclo( $fecha = '' ) {
        $s = self::settings();
        $hoy = $fecha ? $fecha : current_time( 'Y-m-d' );
        $anio_base = max( 2000, absint( $s['anio_inicio'] ) );
        $inicio_base = sprintf( '%04d-%02d-%02d', $anio_base, (int) $s['mes_inicio'], (int) $s['dia_inicio'] );
        $fecha_hoy = new DateTimeImmutable( $hoy, wp_timezone() );
        $inicio = new DateTimeImmutable( $inicio_base, wp_timezone() );
        if ( $fecha_hoy >= $inicio ) {
            while ( $inicio->modify( '+1 year' ) <= $fecha_hoy ) {
                $inicio = $inicio->modify( '+1 year' );
            }
        }
        $fin = $inicio->modify( '+1 year' )->format( 'Y-m-d' );
        return array( 'inicio' => $inicio->format( 'Y-m-d' ), 'fin' => $fin );
    }
}
