<?php
/** Plantillas HTML institucionales reutilizables para correos SVCP. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
class SVCP_Correos {
    public static function inicializar_seguridad_clave() {
        add_filter( 'retrieve_password_title', array( __CLASS__, 'asunto_recuperacion' ), 10, 3 );
        add_filter( 'retrieve_password_message', array( __CLASS__, 'mensaje_recuperacion' ), 10, 4 );
        add_action( 'after_password_reset', array( __CLASS__, 'notificar_clave_actualizada' ), 10, 2 );
    }
    public static function asunto_recuperacion( $title, $user_login = '', $user = null ) { return 'Recuperación de acceso al Portal del Médico · SVCP'; }
    public static function mensaje_recuperacion( $message, $key, $user_login, $user_data ) {
        $url = network_site_url( 'wp-login.php?action=rp&key=' . rawurlencode( $key ) . '&login=' . rawurlencode( $user_login ), 'login' );
        $nombre = $user_data instanceof WP_User ? $user_data->display_name : $user_login;
        return "SOCIEDAD VENEZOLANA DE COLOPROCTOLOGÍA (SVCP)\nPORTAL DEL MÉDICO\n\nHola {$nombre}:\n\nRecibimos una solicitud para restablecer la contraseña de tu cuenta. Para crear una contraseña nueva, abre este enlace seguro: \n{$url}\n\nSi no solicitaste este cambio, ignora este mensaje. Tu contraseña no cambiará mientras no completes el proceso. Por seguridad, no compartas este enlace con nadie.\n\nSi el enlace expiró, solicita una nueva recuperación desde la pantalla de acceso.\n\nEste mensaje fue enviado automáticamente por el Portal del Médico de la SVCP.";
    }
    public static function notificar_clave_actualizada( $user, $new_pass ) {
        if ( ! ( $user instanceof WP_User ) || ! is_email( $user->user_email ) ) return;
        $nombre = esc_html( $user->display_name );
        $html = '<!doctype html><html><body style="margin:0;background:#f2f6f9;font-family:Arial,sans-serif;color:#1c2933;padding:24px 10px"><div style="max-width:600px;margin:auto;background:#fff;border:1px solid #dce7ed;border-radius:14px;overflow:hidden"><header style="background:#0b5a88;color:#fff;padding:24px 28px"><div style="font-size:11px;letter-spacing:2px">SOCIEDAD VENEZOLANA DE COLOPROCTOLOGÍA</div><h1 style="font-size:23px;margin:12px 0 0">Contraseña actualizada</h1></header><main style="padding:26px 28px;line-height:1.7"><p>Hola <strong>' . $nombre . '</strong>,</p><p>La contraseña de tu cuenta del Portal del Médico se cambió correctamente.</p><div style="padding:14px 16px;background:#eaf7f4;border-left:4px solid #4cadaf;border-radius:8px"><strong>¿No reconoces este cambio?</strong><br>Restablece tu contraseña inmediatamente y contacta con la administración de la SVCP.</div><p style="text-align:center;margin:24px 0"><a href="' . esc_url( wp_login_url() ) . '" style="background:#0b5a88;color:#fff;text-decoration:none;padding:12px 20px;border-radius:7px;display:inline-block;font-weight:bold">Ir al portal</a></p><p style="font-size:12px;color:#647784">Por seguridad, este correo nunca incluye tu contraseña.</p></main><footer style="padding:16px 28px;background:#f7f9fa;color:#647784;font-size:12px">Notificación automática de seguridad · SVCP</footer></div></body></html>';
        wp_mail( $user->user_email, 'Tu contraseña fue actualizada · Portal SVCP', $html, array( 'Content-Type: text/html; charset=UTF-8' ) );
    }

    /** Envía al administrador una notificación HTML institucional sobre una acción pendiente. */
    public static function notificar_admin( $tipo, $datos = array() ) {
        $plantillas = array(
            'doctor_nuevo' => array('subject'=>'🩺 Nuevo doctor pendiente de revisión · SVCP','title'=>'Nuevo registro de doctor','icon'=>'🩺','intro'=>'Se ha registrado una nueva ficha profesional y requiere revisión administrativa.','boton'=>'Revisar ficha'),
            'perfil_modificado' => array('subject'=>'✏️ Modificación de ficha pendiente · SVCP','title'=>'Ficha profesional modificada','icon'=>'✏️','intro'=>'Un doctor actualizó sus datos profesionales. La ficha requiere revisión antes de volver a publicarse.','boton'=>'Revisar modificación'),
        );
        if ( ! isset( $plantillas[$tipo] ) ) return false;
        $p = $plantillas[$tipo];
        $d = wp_parse_args($datos, array('nombre'=>'Doctor/a','correo'=>'','clinica'=>'','doctor_id'=>0,'url'=>admin_url('edit.php?post_type=doctor&page=svcp-aprobaciones')));
        $nombre = esc_html($d['nombre']);
        $correo = esc_html($d['correo']);
        $clinica = esc_html($d['clinica']);
        $url = esc_url($d['url']);
        $html = '<!doctype html><html><body style="margin:0;background:#f3f6f8;font-family:Arial,Helvetica,sans-serif;color:#243746"><div style="max-width:640px;margin:24px auto;background:#fff;border:1px solid #e1e8ed;border-radius:14px;overflow:hidden"><div style="background:#173f5f;padding:26px 30px;color:#fff"><div style="font-size:30px">'.esc_html($p['icon']).'</div><div style="font-size:12px;letter-spacing:2px;margin-top:8px;color:#cde6eb">SOCIEDAD VENEZOLANA DE COLOPROCTOLOGÍA</div><h1 style="font-size:24px;line-height:1.3;margin:12px 0 0">'.esc_html($p['title']).'</h1></div><div style="padding:28px 30px"><p style="font-size:16px;line-height:1.7">'.esc_html($p['intro']).'</p><div style="background:#f2f7f9;border:1px solid #e1e8ed;border-radius:10px;padding:16px;margin:20px 0"><p style="margin:0 0 9px"><strong>Doctor:</strong> '.$nombre.'</p><p style="margin:0 0 9px"><strong>Correo:</strong> '.$correo.'</p><p style="margin:0"><strong>Clínica:</strong> '.$clinica.'</p></div><p style="text-align:center;margin:28px 0"><a href="'.$url.'" style="display:inline-block;background:#e5232e;color:#fff;text-decoration:none;font-weight:bold;padding:13px 22px;border-radius:6px">'.esc_html($p['boton']).'</a></p><p style="font-size:13px;color:#667783;line-height:1.6">Si el botón no funciona, copia este enlace en tu navegador:<br><a href="'.$url.'" style="color:#1f6f8b;word-break:break-all">'.$url.'</a></p></div><div style="background:#f7f9fa;padding:18px 30px;color:#667783;font-size:12px">Notificación automática del sistema de Directorio Médico SVCP.</div></div></body></html>';
        return wp_mail(get_option('admin_email'), $p['subject'], $html, array('Content-Type: text/html; charset=UTF-8'));
    }
    public static function enviar( $destino, $tipo, $datos = array() ) {
        if ( ! is_email( $destino ) ) return false;
        $defaults = array('nombre'=>'Doctor/a','mensaje'=>'','fecha_vencimiento'=>'','motivo'=>'','url_panel'=>home_url('/'),'referencia'=>'','monto_pagado_usd'=>'','saldo_pendiente_bs'=>'0.00','saldo_pendiente_usd'=>'0.0000');
        $d = wp_parse_args($datos,$defaults); $nombre = esc_html($d['nombre']); $contenido = '';
        $plantillas = array(
            'registro'=>array('subject'=>'¡Gracias por registrarte en la SVCP!','icon'=>'👋','title'=>'¡Te damos la bienvenida!','text'=>'Gracias por crear tu cuenta en la Sociedad Venezolana de Coloproctología. Completa tu ficha profesional y sigue los pasos para reportar tu membresía.'),
            'pago_pendiente'=>array('subject'=>'Reporte de pago recibido · SVCP','icon'=>'🧾','title'=>'Recibimos tu reporte de pago','text'=>'Tu reporte fue recibido correctamente y está pendiente de verificación. La membresía no se activa hasta que la administración confirme el pago.'),
            'pago_aprobado'=>array('subject'=>'Pago aprobado · Membresía SVCP','icon'=>'✅','title'=>'¡Tu pago fue aprobado!','text'=>'La administración confirmó tu pago. Tu membresía queda solvente hasta la fecha institucional indicada.'),
            'pago_rechazado'=>array('subject'=>'Tu reporte de pago requiere corrección · SVCP','icon'=>'⚠️','title'=>'Necesitamos revisar tu reporte','text'=>'No fue posible aprobar el reporte de pago. Revisa el motivo indicado y comunícate con la Sociedad si necesitas ayuda.'),
            'perfil_modificado'=>array('subject'=>'Actualizamos tu ficha · SVCP','icon'=>'✏️','title'=>'Tu ficha fue actualizada','text'=>'Recibimos los cambios que hiciste en tu ficha profesional. La administración debe revisarlos antes de volver a publicar tu perfil en el directorio.'),
            'perfil_aprobado'=>array('subject'=>'Tu ficha profesional fue aprobada · SVCP','icon'=>'🩺','title'=>'Tu ficha está aprobada','text'=>'La administración aprobó tu ficha profesional. Si tu membresía está vigente, tu perfil podrá mostrarse en el directorio público.'),
            'vencimiento'=>array('subject'=>'Recordatorio de membresía SVCP','icon'=>'📅','title'=>'Tu membresía está próxima a vencer','text'=>'Te recordamos revisar tu estado de solvencia y realizar la renovación correspondiente al ciclo institucional.'),
            'membresia_vencida'=>array('subject'=>'Tu período de membresía ha vencido · SVCP','icon'=>'🔔','title'=>'Tu membresía requiere renovación','text'=>'El período institucional de membresía ha vencido. Reporta tu pago y espera la validación administrativa para recuperar la solvencia.')
        );
        if ( ! isset($plantillas[$tipo]) ) return false; $p = $plantillas[$tipo];
        $contenido = '<p style="margin:0 0 16px;line-height:1.7">'.esc_html($p['text']).'</p>';
        if ( $d['referencia'] !== '' ) $contenido .= '<p style="padding:12px;background:#f2f7f9;border-radius:8px"><strong>Referencia:</strong> '.esc_html($d['referencia']).'</p>';
        if ( $tipo === 'pago_aprobado' && $d['monto_pagado_usd'] !== '' ) {
            $contenido .= '<p style="padding:14px;background:#f2f7f9;border-radius:8px;line-height:1.7"><strong>Equivalente recibido:</strong> USD '.esc_html($d['monto_pagado_usd']).'</p>';
            if ( (float)$d['saldo_pendiente_bs'] > 0 ) {
                $contenido .= '<div style="padding:14px;background:#fff7e8;border-left:4px solid #f49a22;border-radius:8px;line-height:1.7"><strong>Saldo pendiente por completar</strong><br>Bs. '.esc_html($d['saldo_pendiente_bs']).' · Aproximadamente USD '.esc_html($d['saldo_pendiente_usd']).'<br><span style="font-size:13px">Este saldo se calculó con la tasa BCV guardada para la fecha de tu pago. Coordina con la administración cómo completar la diferencia.</span></div>';
            } else {
                $contenido .= '<p style="padding:12px;background:#e8f7f1;border-radius:8px"><strong>Saldo pendiente:</strong> No registra diferencia pendiente.</p>';
            }
        }
        if ( $d['fecha_vencimiento'] !== '' ) $contenido .= '<p style="padding:12px;background:#e8f7f1;border-radius:8px"><strong>Vigencia hasta:</strong> '.esc_html($d['fecha_vencimiento']).'</p>';
        if ( $d['motivo'] !== '' ) $contenido .= '<p style="padding:12px;background:#fff1f0;border-left:4px solid #c62828"><strong>Motivo:</strong><br>'.nl2br(esc_html($d['motivo'])).'</p>';
        if ( $d['mensaje'] !== '' ) $contenido .= '<p style="line-height:1.7">'.nl2br(esc_html($d['mensaje'])).'</p>';
        $url = esc_url($d['url_panel']);
        $html = '<!doctype html><html><body style="margin:0;background:#f3f6f8;font-family:Arial,Helvetica,sans-serif;color:#243746"><div style="max-width:620px;margin:24px auto;background:#fff;border-radius:14px;overflow:hidden;border:1px solid #e1e8ed"><div style="background:#173f5f;padding:26px 30px;color:#fff"><div style="font-size:30px">'.esc_html($p['icon']).'</div><div style="font-size:12px;letter-spacing:2px;margin-top:8px;color:#cde6eb">SOCIEDAD VENEZOLANA DE COLOPROCTOLOGÍA</div><h1 style="font-size:24px;line-height:1.3;margin:12px 0 0">'.esc_html($p['title']).'</h1></div><div style="padding:28px 30px"><p style="font-size:16px">Hola <strong>'.$nombre.'</strong>,</p>'.$contenido.'<p style="text-align:center;margin:28px 0"><a href="'.$url.'" style="display:inline-block;background:#e5232e;color:#fff;text-decoration:none;font-weight:bold;padding:13px 22px;border-radius:6px">Ir a mi panel</a></p><p style="font-size:13px;line-height:1.6;color:#667783">Si el botón no funciona, copia este enlace en tu navegador:<br><a style="color:#1f6f8b;word-break:break-all" href="'.$url.'">'.$url.'</a></p></div><div style="background:#f7f9fa;padding:18px 30px;color:#667783;font-size:12px">Este mensaje fue enviado automáticamente por la SVCP. Por favor, no respondas a este correo.</div></div></body></html>';
        $headers = array('Content-Type: text/html; charset=UTF-8');
        return wp_mail($destino,$p['subject'],$html,$headers);
    }
}
