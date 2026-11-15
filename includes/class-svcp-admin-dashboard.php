<?php
/** Dashboard administrativo del Directorio Médico SVCP. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SVCP_Admin_Dashboard {
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'menu' ), 5 );
    }

    public function menu() {
        add_menu_page(
            __( 'Dashboard SVCP', 'svcp-directorio' ),
            __( 'SVCP Directorio', 'svcp-directorio' ),
            'manage_options',
            'svcp-dashboard',
            array( $this, 'render' ),
            'dashicons-id-alt',
            25
        );
        add_submenu_page( 'svcp-dashboard', __( 'Dashboard', 'svcp-directorio' ), __( 'Dashboard', 'svcp-directorio' ), 'manage_options', 'svcp-dashboard', array( $this, 'render' ) );
    }

    private function ids() {
        return get_posts( array( 'post_type'=>'doctor', 'post_status'=>'any', 'posts_per_page'=>-1, 'fields'=>'ids', 'no_found_rows'=>true ) );
    }

    public function render() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'No tienes permisos.', 'svcp-directorio' ) ); }
        $ids = $this->ids();
        $total = count( $ids );
        $aprobacion = array_fill_keys( array_keys( SVCP_Aprobacion::estados() ), 0 );
        $membresia = array_fill_keys( array_keys( SVCP_Membresia::estados() ), 0 );
        $calidad = array( 'sin_cuenta'=>0, 'sin_region'=>0, 'sin_correo'=>0, 'sin_ciudad'=>0, 'sin_foto'=>0 );
        $publicables = 0;
        $con_cuenta = 0;

        foreach ( $ids as $id ) {
            $a = SVCP_Aprobacion::obtener_estado( $id ); if ( isset( $aprobacion[$a] ) ) { $aprobacion[$a]++; }
            $m = SVCP_Membresia::obtener_estado( $id ); if ( isset( $membresia[$m] ) ) { $membresia[$m]++; }
            if ( ! absint( get_post_meta( $id, 'svcp_usuario_id', true ) ) ) { $calidad['sin_cuenta']++; } else { $con_cuenta++; }
            if ( empty( wp_get_post_terms( $id, 'region', array('fields'=>'ids') ) ) ) { $calidad['sin_region']++; }
            if ( ! is_email( get_post_meta( $id, 'svcp_correo', true ) ) ) { $calidad['sin_correo']++; }
            if ( '' === trim( (string) get_post_meta( $id, 'svcp_ciudad', true ) ) ) { $calidad['sin_ciudad']++; }
            if ( ! has_post_thumbnail( $id ) ) { $calidad['sin_foto']++; }
            if ( SVCP_Aprobacion::puede_publicarse( $id ) && SVCP_Membresia::puede_aparecer_en_directorio( $id ) ) { $publicables++; }
        }

        $next_cron = wp_next_scheduled( SVCP_Membresia_Cron::CRON_HOOK );
        $smtp = class_exists( 'SVCP_SMTP' ) ? SVCP_SMTP::config() : array();
        $cron_desactivado = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
        $ultimos = $this->ultimos_cambios();
        $finanzas = $this->finanzas();
        ?>
        <div class="wrap svcp-dashboard">
            <h1>Dashboard — SVCP Directorio Médico</h1>
            <p class="description">Resumen operativo del directorio, membresías, aprobaciones, calidad de datos y correo.</p>

            <div class="svcp-grid svcp-kpis">
                <?php $this->kpi( 'Doctores', $total, 'edit.php?post_type=doctor' ); ?>
                <?php $this->kpi( 'Publicables', $publicables, 'edit.php?post_type=doctor&page=svcp-aprobaciones&estado=publicada' ); ?>
                <?php $this->kpi( 'Pendientes de revisión', $aprobacion[SVCP_Aprobacion::PENDIENTE_REVISION] + $aprobacion[SVCP_Aprobacion::REVISION_REQUERIDA], 'edit.php?post_type=doctor&page=svcp-aprobaciones' ); ?>
                <?php $this->kpi( 'Por vencer', $membresia[SVCP_Membresia::ESTADO_POR_VENCER], 'edit.php?post_type=doctor&page=svcp-membresias&estado=por_vencer' ); ?>
                <?php $this->kpi( 'Vencidas', $membresia[SVCP_Membresia::ESTADO_VENCIDA], 'edit.php?post_type=doctor&page=svcp-membresias&estado=vencida' ); ?>
                <?php $this->kpi( 'Pagos pendientes', $finanzas['pendientes'], 'edit.php?post_type=doctor&page=svcp-pagos' ); ?>
                <?php $this->kpi( 'Recaudado aprobado (Bs.)', 'Bs. ' . number_format_i18n($finanzas['ves'],2), 'edit.php?post_type=doctor&page=svcp-pagos&estado=aprobado' ); ?>
                <?php $this->kpi( 'Equivalente registrado (USD)', 'USD ' . number_format_i18n($finanzas['usd'],4), 'edit.php?post_type=doctor&page=svcp-pagos&estado=aprobado' ); ?>
                <?php $this->kpi( 'Saldo pendiente (USD)', 'USD ' . number_format_i18n($finanzas['deuda_usd'],4), 'edit.php?post_type=doctor&page=svcp-membresias' ); ?>
                <?php $this->kpi( 'Saldo pendiente (Bs. hoy)', 'Bs. ' . number_format_i18n($finanzas['deuda_bs'],2), 'edit.php?post_type=doctor&page=svcp-membresias' ); ?>
            </div>

            <div class="svcp-grid svcp-two">
                <section class="postbox"><h2 class="hndle">Aprobaciones</h2><div class="inside"><?php foreach ( SVCP_Aprobacion::estados() as $key=>$label ) : ?><div class="svcp-row"><span><?php echo esc_html($label); ?></span><strong><?php echo esc_html($aprobacion[$key]); ?></strong></div><?php endforeach; ?></div></section>
                <section class="postbox"><h2 class="hndle">Membresías</h2><div class="inside"><?php foreach ( SVCP_Membresia::estados() as $key=>$label ) : ?><div class="svcp-row"><span><?php echo esc_html($label); ?></span><strong><?php echo esc_html($membresia[$key]); ?></strong></div><?php endforeach; ?></div></section>
            </div>

            <div class="svcp-grid svcp-three">
                <section class="postbox"><h2 class="hndle">Calidad de fichas</h2><div class="inside"><?php foreach ( array('sin_cuenta'=>'Sin cuenta','sin_region'=>'Sin región','sin_correo'=>'Correo inválido/vacío','sin_ciudad'=>'Sin ciudad','sin_foto'=>'Sin fotografía') as $key=>$label ) : ?><div class="svcp-row"><span><?php echo esc_html($label); ?></span><strong><?php echo esc_html($calidad[$key]); ?></strong></div><?php endforeach; ?></div></section>
                <section class="postbox"><h2 class="hndle">Correo</h2><div class="inside">
                    <div class="svcp-status"><span>SMTP</span><strong><?php echo ! empty($smtp['activo']) ? 'Activo' : 'No configurado'; ?></strong></div>
                    <p><?php echo ! empty($smtp['from_email']) ? 'Remitente: ' . esc_html($smtp['from_email']) : 'Configura el remitente del directorio.'; ?></p>
                    <a class="button button-primary" href="<?php echo esc_url(admin_url('edit.php?post_type=doctor&page=svcp-correo')); ?>">Configurar correo</a>
                </div></section>
                <section class="postbox"><h2 class="hndle">WP-Cron</h2><div class="inside">
                    <div class="svcp-status"><span>Evento diario</span><strong><?php echo $next_cron ? esc_html(wp_date('d/m/Y H:i', $next_cron, wp_timezone())) : 'No programado'; ?></strong></div>
                    <p><?php echo $cron_desactivado ? '<strong>WP-Cron está desactivado.</strong> Debes usar un cron real del servidor.' : 'WP-Cron está gestionado por WordPress.'; ?></p>
                    <a class="button" href="<?php echo esc_url(admin_url('edit.php?post_type=doctor&page=svcp-correo-cron')); ?>">Ver configuración del cron</a>
                </div></section>
            </div>

            <section class="postbox"><h2 class="hndle">Actividad reciente</h2><div class="inside"><?php if ( empty($ultimos) ) : ?><p>Aún no hay actividad registrada.</p><?php else : ?><table class="widefat striped"><thead><tr><th>Fecha</th><th>Doctor</th><th>Acción</th><th>Campo</th><th>Usuario</th></tr></thead><tbody><?php foreach($ultimos as $item) : ?><tr><td><?php echo esc_html(mysql2date('d/m/Y H:i',$item->fecha)); ?></td><td><?php echo esc_html(get_the_title($item->doctor_id)); ?></td><td><?php echo esc_html(ucfirst($item->accion)); ?></td><td><?php echo esc_html($item->campo ?: '—'); ?></td><td><?php $u=get_user_by('id',$item->user_id); echo esc_html($u ? $u->display_name : 'Sistema'); ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></div></section>

            <style>
                .svcp-dashboard .svcp-grid{display:grid;gap:16px;margin:18px 0}.svcp-dashboard .svcp-kpis{grid-template-columns:repeat(auto-fit,minmax(155px,1fr))}.svcp-dashboard .svcp-two{grid-template-columns:repeat(2,minmax(0,1fr))}.svcp-dashboard .svcp-three{grid-template-columns:repeat(3,minmax(0,1fr))}.svcp-dashboard .postbox{margin:0}.svcp-dashboard .svcp-kpi{display:block;background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:18px;text-decoration:none;color:#1d2327}.svcp-dashboard .svcp-kpi span{display:block;color:#50575e;font-size:13px}.svcp-dashboard .svcp-kpi strong{display:block;font-size:30px;margin-top:8px}.svcp-dashboard .svcp-row,.svcp-dashboard .svcp-status{display:flex;justify-content:space-between;gap:15px;padding:8px 0;border-bottom:1px solid #f0f0f1}.svcp-dashboard .svcp-row:last-child{border-bottom:0}.svcp-dashboard .svcp-status strong{color:#2271b1}@media(max-width:1100px){.svcp-dashboard .svcp-kpis{grid-template-columns:repeat(3,1fr)}.svcp-dashboard .svcp-three{grid-template-columns:1fr 1fr}}@media(max-width:700px){.svcp-dashboard .svcp-kpis,.svcp-dashboard .svcp-two,.svcp-dashboard .svcp-three{grid-template-columns:1fr}}
            </style>
        </div>
        <?php
    }

    private function finanzas() {
        $r=array('pendientes'=>0,'ves'=>0.0,'usd'=>0.0,'deuda_usd'=>0.0,'deuda_bs'=>0.0);
        $ids=get_posts(array('post_type'=>'svcp_pago','post_status'=>'private','posts_per_page'=>-1,'fields'=>'ids','no_found_rows'=>true));
        foreach($ids as $id){$estado=get_post_meta($id,'_svcp_estado',true);if('pendiente'===$estado)$r['pendientes']++;if('aprobado'===$estado){$r['ves']+=(float)get_post_meta($id,'_svcp_monto',true);$r['usd']+=(float)get_post_meta($id,'_svcp_pago_equivalente_usd',true);}}
        $doctores=get_posts(array('post_type'=>'doctor','post_status'=>'any','posts_per_page'=>-1,'fields'=>'ids','no_found_rows'=>true));
        foreach($doctores as $doctor_id){$r['deuda_usd']+=max(0,(float)get_post_meta($doctor_id,'svcp_membresia_saldo_pendiente_usd',true));}
        if($r['deuda_usd']>0 && class_exists('SVCP_Tasa_BCV')){$t=SVCP_Tasa_BCV::obtener(current_time('Y-m-d'),false);if(!is_wp_error($t))$r['deuda_bs']=$r['deuda_usd']*(float)$t['tasa'];}
        return $r;
    }

    private function kpi($label,$value,$url) { echo '<a class="svcp-kpi" href="'.esc_url(admin_url($url)).'"><span>'.esc_html($label).'</span><strong>'.esc_html($value).'</strong></a>'; }

    private function ultimos_cambios() {
        if ( ! class_exists('SVCP_Auditoria') ) { return array(); }
        return SVCP_Auditoria::ultimos( 10 );
    }
}
