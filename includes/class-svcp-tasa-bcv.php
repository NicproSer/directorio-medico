<?php
/** Servicio de tasas USD/VES con snapshots y caché local. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
class SVCP_Tasa_BCV {
    const OPTION = 'svcp_bcv_tasa_actual';
    const HISTORY_OPTION = 'svcp_bcv_historial_tasas';
    const HOOK = 'svcp_bcv_actualizar_tasa';
    const API = 'https://bcv.today/api/v1';

    public static function inicializar() {
        add_action( self::HOOK, array( __CLASS__, 'actualizar' ) );
        if ( ! wp_next_scheduled( self::HOOK ) ) {
            wp_schedule_event( time() + 120, 'daily', self::HOOK );
        }
    }

    /** Consulta la tasa correspondiente a una fecha de Caracas. */
    public static function obtener( $fecha = '', $forzar = false ) {
        $fecha = $fecha ? sanitize_text_field( $fecha ) : current_time( 'Y-m-d' );
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $fecha ) || ! checkdate((int)substr($fecha,5,2),(int)substr($fecha,8,2),(int)substr($fecha,0,4)) ) return new WP_Error( 'fecha_invalida', 'La fecha indicada no es válida.' );
        $hoy = current_time( 'Y-m-d' );
        if ( $fecha > $hoy ) return new WP_Error( 'fecha_futura', 'No se puede consultar una tasa futura.' );
        $history = get_option( self::HISTORY_OPTION, array() );
        if ( ! $forzar && is_array($history) && isset($history[$fecha]['tasa']) ) return $history[$fecha];
        $cached = get_option( self::OPTION, array() );
        if ( ! $forzar && $fecha === $hoy && is_array( $cached ) && isset( $cached['fecha_consulta'], $cached['tasa'] ) && $cached['fecha_consulta'] === $fecha ) return $cached;

        $endpoint = ( $fecha === $hoy ) ? self::API . '/rate.json' : self::API . '/history/' . $fecha . '.json';
        $response = wp_remote_get( $endpoint, array( 'timeout' => 12, 'redirection' => 2, 'headers' => array( 'Accept' => 'application/json', 'Cache-Control' => 'no-cache' ), 'user-agent' => 'SVCP-Directorio/' . ( defined('SVCP_DIR_VERSION') ? SVCP_DIR_VERSION : '1.0' ) ) );
        if ( is_wp_error( $response ) ) return $response;
        $status = (int) wp_remote_retrieve_response_code( $response );
        if ( 200 !== $status ) return new WP_Error( 'bcv_http', 'El servicio de tasas respondió HTTP ' . $status . '.' );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $body ) || empty( $body['USD'] ) || ! is_numeric( $body['USD'] ) || (float) $body['USD'] <= 0 ) return new WP_Error( 'bcv_formato', 'La respuesta de tasas no contiene un valor USD válido.' );
        $effective = ! empty( $body['effective_date'] ) ? sanitize_text_field( $body['effective_date'] ) : ( ! empty( $body['date'] ) ? sanitize_text_field( $body['date'] ) : $fecha );
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $effective ) || $effective > $fecha ) return new WP_Error( 'bcv_fecha', 'La fecha de vigencia devuelta por el servicio no es válida.' );
        $snapshot = array(
            'tasa' => number_format( (float) $body['USD'], 8, '.', '' ),
            'fecha_consulta' => $fecha,
            'fecha_vigencia' => $effective,
            'actualizado_utc' => isset( $body['updated_at'] ) ? sanitize_text_field( $body['updated_at'] ) : '',
            'fuente' => 'BCV (datos replicados por BCV Today)',
            'endpoint' => esc_url_raw( $endpoint ),
        );
        if ( ! is_array($history) ) $history = array();
        $history[$fecha] = $snapshot;
        if ( count($history) > 500 ) { uasort($history, function($a,$b){ return strcmp(isset($b['fecha_consulta'])?$b['fecha_consulta']:'',isset($a['fecha_consulta'])?$a['fecha_consulta']:''); }); $history = array_slice($history,0,500,true); }
        update_option( self::HISTORY_OPTION, $history, false );
        if ( $fecha === $hoy ) update_option( self::OPTION, $snapshot, false );
        return $snapshot;
    }

    public static function actualizar() { return self::obtener( current_time( 'Y-m-d' ), true ); }
    public static function cancelar() { $ts = wp_next_scheduled( self::HOOK ); if ( $ts ) wp_unschedule_event( $ts, self::HOOK ); }
}
