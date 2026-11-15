<?php
/** Estado y administración del WP-Cron del directorio. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class SVCP_Admin_Cron {
    public static function instance() {
        static $instance = null;
        if ( null === $instance ) { $instance = new self(); }
        return $instance;
    }
    private function __construct() {
        add_action('admin_menu', array($this,'menu'));
        add_action('admin_post_svcp_cron_ejecutar', array($this,'ejecutar'));
    }
    public function menu() {
        add_submenu_page('edit.php?post_type=doctor','Cron y notificaciones','Cron / Notificaciones','manage_options','svcp-correo-cron',array($this,'render'));
    }
    public function ejecutar() {
        if ( ! current_user_can('manage_options') ) { wp_die('No tienes permisos.'); }
        check_admin_referer('svcp_cron_ejecutar');
        $resultado = do_action('svcp_membresia_notificaciones_diarias');
        set_transient('svcp_cron_manual','1',HOUR_IN_SECONDS);
        wp_safe_redirect(add_query_arg(array('post_type'=>'doctor','page'=>'svcp-correo-cron','svcp_cron'=>'ok'),admin_url('edit.php')));
        exit;
    }
    public function render() {
        if ( ! current_user_can('manage_options') ) { wp_die('No tienes permisos.'); }
        $next = wp_next_scheduled(SVCP_Membresia_Cron::CRON_HOOK);
        $disabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        $url = home_url('/wp-cron.php');
        $estado = isset($_GET['svcp_cron']) ? sanitize_key(wp_unslash($_GET['svcp_cron'])) : '';
        ?>
        <div class="wrap">
            <h1>Cron y notificaciones</h1>
            <?php if('ok'===$estado): ?><div class="notice notice-success is-dismissible"><p>Se ejecutó manualmente el proceso de notificaciones.</p></div><?php endif; ?>
            <div style="background:#fff;border:1px solid #dcdcde;padding:20px;max-width:900px;margin-top:18px">
                <h2 style="margin-top:0">Estado actual</h2>
                <p><strong>Evento:</strong> <code><?php echo esc_html(SVCP_Membresia_Cron::CRON_HOOK); ?></code></p>
                <p><strong>Próxima ejecución:</strong> <?php echo $next ? esc_html(wp_date('d/m/Y H:i:s',$next,wp_timezone())) : '<span style="color:#a61b1b">No programada</span>'; ?></p>
                <p><strong>WP-Cron:</strong> <?php echo $disabled ? '<span style="color:#a61b1b">Desactivado</span>' : '<span style="color:#18733a">Activo</span>'; ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="svcp_cron_ejecutar"><?php wp_nonce_field('svcp_cron_ejecutar'); ?>
                    <?php submit_button('Ejecutar notificaciones ahora','secondary','submit',false); ?>
                </form>
            </div>
            <div style="background:#f6f7f7;border:1px solid #dcdcde;padding:20px;max-width:900px;margin-top:18px">
                <h2 style="margin-top:0">Configuración recomendada para producción</h2>
                <p>WP-Cron depende de visitas al sitio. Para un sitio con membresías, es preferible un cron real del servidor cada 5 minutos.</p>
                <p><strong>1. En <code>wp-config.php</code>:</strong></p>
                <pre style="background:#1d2327;color:#fff;padding:14px;overflow:auto"><code>define( 'DISABLE_WP_CRON', true );</code></pre>
                <p><strong>2. En el cron del hosting/servidor:</strong></p>
                <pre style="background:#1d2327;color:#fff;padding:14px;overflow:auto"><code>*/5 * * * * wget -q -O - <?php echo esc_html($url); ?> >/dev/null 2>&1</code></pre>
                <p>Alternativa con PHP/CLI si tu hosting lo permite:</p>
                <pre style="background:#1d2327;color:#fff;padding:14px;overflow:auto"><code>*/5 * * * * php /ruta/a/wordpress/wp-cron.php >/dev/null 2>&1</code></pre>
                <p><strong>Importante:</strong> el plugin mantiene el proceso de membresías programado diariamente; el cron real cada 5 minutos solamente despierta WP-Cron con regularidad y WordPress ejecutará los eventos que correspondan.</p>
                <p>Si usas cPanel/Plesk, la misma frecuencia puede configurarse desde la sección <em>Cron Jobs / Tareas programadas</em>.</p>
            </div>
        </div>
        <?php
    }
}
