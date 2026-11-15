<?php
/**
 * Notificaciones automáticas de membresía.
 *
 * Envía recordatorios por correo usando wp_mail(), por lo que el envío real
 * queda a cargo de WordPress/SMTP configurado en el sitio.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SVCP_Membresia_Cron {

    const CRON_HOOK = 'svcp_membresia_notificaciones_diarias';
    const CRON_SCHEDULE = 'daily';

    /** Días de anticipación disponibles para recordatorios. */
    const RECORDATORIOS = array( 30, 15, 7 );

    public static function instance() {
        static $instancia = null;

        if ( null === $instancia ) {
            $instancia = new self();
        }

        return $instancia;
    }

    private function __construct() {
        add_action( self::CRON_HOOK, array( $this, 'procesar_notificaciones' ) );
        add_action( 'init', array( $this, 'asegurar_evento_programado' ) );
    }

    /**
     * Programa el proceso diario si todavía no existe.
     */
    public function asegurar_evento_programado() {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, self::CRON_SCHEDULE, self::CRON_HOOK );
        }
    }

    /**
     * Programa el cron al activar el plugin.
     */
    public static function programar() {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, self::CRON_SCHEDULE, self::CRON_HOOK );
        }
    }

    /**
     * Elimina el evento al desactivar el plugin.
     */
    public static function desprogramar() {
        $timestamp = wp_next_scheduled( self::CRON_HOOK );

        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::CRON_HOOK );
        }
    }

    /**
     * Revisa todas las fichas y envía como máximo un recordatorio por día y
     * por ciclo de vencimiento. La deduplicación se guarda en post meta.
     */
    public function procesar_notificaciones() {
        $doctores = get_posts(
            array(
                'post_type'      => 'doctor',
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'no_found_rows'  => true,
            )
        );

        if ( empty( $doctores ) ) {
            return;
        }

        $hoy = new DateTimeImmutable( 'today', wp_timezone() );

        foreach ( $doctores as $doctor_id ) {
            $this->procesar_doctor( (int) $doctor_id, $hoy );
        }
    }

    /**
     * Procesa una ficha individual.
     */
    private function procesar_doctor( $doctor_id, DateTimeImmutable $hoy ) {
        $vencimiento = get_post_meta( $doctor_id, 'svcp_membresia_vencimiento', true );
        $estado_manual = get_post_meta( $doctor_id, 'svcp_membresia_estado', true );

        // Las membresías suspendidas no generan recordatorios automáticos.
        if ( SVCP_Membresia::ESTADO_SUSPENDIDA === $estado_manual || empty( $vencimiento ) ) {
            return;
        }

        $fecha_vencimiento = DateTimeImmutable::createFromFormat( 'Y-m-d', $vencimiento, wp_timezone() );

        if ( false === $fecha_vencimiento ) {
            return;
        }

        $dias_restantes = (int) $hoy->diff( $fecha_vencimiento )->format( '%r%a' );
        $destinatario   = $this->obtener_destinatario( $doctor_id );

        if ( ! $destinatario ) {
            return;
        }

        // Una vez vencida, enviar un único aviso por cada fecha de vencimiento.
        if ( $dias_restantes <= 0 ) {
            $this->enviar_si_corresponde( $doctor_id, $destinatario, 'vencida', $vencimiento, $dias_restantes );
            return;
        }

        // Elegimos el recordatorio más cercano que ya corresponde. Esto evita
        // perder el aviso si WP-Cron no se ejecutó exactamente el día 30/15/7.
        $recordatorio = 0;

        foreach ( self::RECORDATORIOS as $dias ) {
            if ( $dias_restantes <= $dias ) {
                $recordatorio = $dias;
                break;
            }
        }

        if ( $recordatorio > 0 ) {
            $this->enviar_si_corresponde( $doctor_id, $destinatario, 'recordatorio_' . $recordatorio, $vencimiento, $dias_restantes );
        }
    }

    /**
     * Devuelve el correo de la cuenta vinculada o, como respaldo, el correo
     * guardado en la ficha.
     */
    private function obtener_destinatario( $doctor_id ) {
        $usuario_id = absint( get_post_meta( $doctor_id, 'svcp_usuario_id', true ) );

        if ( $usuario_id ) {
            $usuario = get_userdata( $usuario_id );

            if ( $usuario && is_email( $usuario->user_email ) ) {
                return $usuario->user_email;
            }
        }

        $correo = sanitize_email( get_post_meta( $doctor_id, 'svcp_correo', true ) );

        return is_email( $correo ) ? $correo : '';
    }

    /**
     * Envía una notificación si todavía no fue enviada para esta fecha de
     * vencimiento.
     */
    private function enviar_si_corresponde( $doctor_id, $destinatario, $tipo, $vencimiento, $dias_restantes ) {
        $clave = 'svcp_membresia_notificacion_' . sanitize_key( $tipo ) . '_' . $vencimiento;

        if ( get_post_meta( $doctor_id, $clave, true ) ) {
            return;
        }

        $nombre = get_the_title( $doctor_id );

        if ( 'vencida' === $tipo ) {
            $asunto = sprintf(
                __( 'SVCP: su membresía ha vencido', 'svcp-directorio' )
            );

            $mensaje = sprintf(
                __( "Estimado/a Dr./Dra. %1\$s:\n\nLe informamos que su membresía en la Sociedad Venezolana de Coloproctología (SVCP) ha vencido.\n\nFecha de vencimiento: %2$s\n\nPara renovar su membresía, por favor comuníquese con la SVCP o utilice el medio de renovación habilitado por la sociedad.\n\nEste mensaje fue generado automáticamente.", 'svcp-directorio' ),
                $nombre,
                $this->formatear_fecha( $vencimiento )
            );
        } else {
            $dias_mostrados = max( 0, (int) $dias_restantes );

            $asunto = sprintf(
                __( 'SVCP: su membresía vence en %d días', 'svcp-directorio' ),
                $dias_mostrados
            );

            $mensaje = sprintf(
                __( "Estimado/a Dr./Dra. %1\$s:\n\nLe recordamos que su membresía en la Sociedad Venezolana de Coloproctología (SVCP) está próxima a vencer.\n\nDías restantes aproximados: %2\$d\nFecha de vencimiento: %3\$s\n\nLe recomendamos gestionar la renovación antes de la fecha indicada para mantener su membresía vigente y continuar apareciendo en el directorio médico público.\n\nEste mensaje fue generado automáticamente.", 'svcp-directorio' ),
                $nombre,
                $dias_mostrados,
                $this->formatear_fecha( $vencimiento )
            );
        }

        $asunto = apply_filters( 'svcp_membresia_email_asunto', $asunto, $doctor_id, $tipo );
        $mensaje = apply_filters( 'svcp_membresia_email_mensaje', $mensaje, $doctor_id, $tipo, $dias_restantes );

        if ( class_exists( 'SVCP_Correos' ) ) {
            $cuenta = get_user_by( 'email', $destinatario );
            $tipo_correo = ( 'vencida' === $tipo ) ? 'membresia_vencida' : 'vencimiento';
            $enviado = SVCP_Correos::enviar( $destinatario, $tipo_correo, array(
                'nombre' => $cuenta ? $cuenta->display_name : $nombre,
                'fecha_vencimiento' => $this->formatear_fecha( $vencimiento ),
                'mensaje' => ( 'vencida' === $tipo ) ? '' : 'Días restantes aproximados: ' . max( 0, (int) $dias_restantes ),
                'url_panel' => home_url( '/' ),
            ) );
        } else {
            $headers = array( 'Content-Type: text/plain; charset=UTF-8' );
            $enviado = wp_mail( $destinatario, $asunto, $mensaje, $headers );
        }

        if ( $enviado ) {
            update_post_meta( $doctor_id, $clave, current_time( 'mysql' ) );
        }
    }

    /**
     * Formatea una fecha almacenada como Y-m-d según la configuración del sitio.
     */
    private function formatear_fecha( $fecha ) {
        $objeto = DateTimeImmutable::createFromFormat( 'Y-m-d', $fecha, wp_timezone() );

        if ( false === $objeto ) {
            return $fecha;
        }

        return wp_date( get_option( 'date_format' ), $objeto->getTimestamp(), wp_timezone() );
    }
}
