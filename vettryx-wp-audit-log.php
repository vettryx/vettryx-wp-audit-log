<?php
/**
 * Plugin Name: VETTRYX WP Audit Log
 * Plugin URI:  https://github.com/vettryx/vettryx-wp-core
 * Description: Submódulo do VETTRYX WP Core para registro de atividades, monitoramento e auditoria de segurança.
 * Version:     1.0.3
 * Author:      VETTRYX Tech
 * Author URI:  https://vettryx.com.br
 * License:     Proprietária (Uso Comercial Exclusivo)
 * Vettryx Icon: dashicons-visibility
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ==============================================================================
 * 1. INSTALAÇÃO DO BANCO DE DADOS
 * Cria a tabela isolada para não inflar a wp_options ou wp_posts.
 * ==============================================================================
 */
add_action('admin_init', 'vettryx_audit_check_and_create_table');
function vettryx_audit_check_and_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'vettryx_audit_log';
    $db_version = '1.0.0';
    
    if (get_option('vettryx_audit_db_version') !== $db_version || $wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) DEFAULT 0 NOT NULL,
            user_name varchar(255) DEFAULT 'Sistema' NOT NULL,
            action varchar(255) NOT NULL,
            object_type varchar(50) NOT NULL,
            object_name text NOT NULL,
            ip_address varchar(50) DEFAULT '' NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        update_option('vettryx_audit_db_version', $db_version);
    }
}

/**
 * Função utilitária para inserir o log no banco
 */
function vettryx_insert_audit_log($action, $object_type, $object_name) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'vettryx_audit_log';
    
    $current_user = wp_get_current_user();
    $user_id = $current_user->exists() ? $current_user->ID : 0;
    $user_name = $current_user->exists() ? $current_user->user_login : 'Sistema Automático';
    
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field($_SERVER['REMOTE_ADDR']) : 'Desconhecido';

    $wpdb->insert($table_name, [
        'user_id'     => $user_id,
        'user_name'   => $user_name,
        'action'      => $action,
        'object_type' => $object_type,
        'object_name' => $object_name,
        'ip_address'  => $ip
    ]);
}

/**
 * ==============================================================================
 * 2. OS ESPIÕES (HOOKS DE MONITORAMENTO)
 * ==============================================================================
 */

// A. Monitora Logins
add_action('wp_login', 'vettryx_audit_log_user_login', 10, 2);
function vettryx_audit_log_user_login($user_login, $user) {
    vettryx_insert_audit_log('Login com sucesso', 'Sessão', 'Usuário: ' . $user_login);
}

// B. Monitora Criação/Edição de Conteúdo
add_action('save_post', 'vettryx_audit_log_save_post', 10, 3);
function vettryx_audit_log_save_post($post_id, $post, $update) {
    if (wp_is_post_revision($post_id) || defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    
    // Ignora se for rascunho automático ou se estiver indo para a lixeira
    if (in_array($post->post_status, ['auto-draft', 'trash'])) return;

    $action = $update ? 'Editou' : 'Criou';
    $post_type_obj = get_post_type_object($post->post_type);
    $type_name = $post_type_obj ? $post_type_obj->labels->singular_name : $post->post_type;

    vettryx_insert_audit_log($action, $type_name, $post->post_title);
}

// B.2. Monitora Envio para a Lixeira
add_action('wp_trash_post', 'vettryx_audit_log_trash_post');
function vettryx_audit_log_trash_post($post_id) {
    $post = get_post($post_id);
    $post_type_obj = get_post_type_object($post->post_type);
    $type_name = $post_type_obj ? $post_type_obj->labels->singular_name : $post->post_type;
    vettryx_insert_audit_log('Moveu para a Lixeira', $type_name, $post->post_title);
}

// B.3. Monitora Restauração da Lixeira
add_action('untrash_post', 'vettryx_audit_log_untrash_post');
function vettryx_audit_log_untrash_post($post_id) {
    $post = get_post($post_id);
    $post_type_obj = get_post_type_object($post->post_type);
    $type_name = $post_type_obj ? $post_type_obj->labels->singular_name : $post->post_type;
    vettryx_insert_audit_log('Restaurou da Lixeira', $type_name, $post->post_title);
}

// B.4. Monitora Exclusão Permanente
add_action('before_delete_post', 'vettryx_audit_log_delete_post');
function vettryx_audit_log_delete_post($post_id) {
    $post = get_post($post_id);
    // Ignora exclusões de revisões (o WP apaga revisões antigas sozinho)
    if ($post->post_type === 'revision') return;

    $post_type_obj = get_post_type_object($post->post_type);
    $type_name = $post_type_obj ? $post_type_obj->labels->singular_name : $post->post_type;
    vettryx_insert_audit_log('Excluiu Permanentemente', $type_name, $post->post_title);
}

// C. Monitora Atualizações de Plugins/Temas/Core (Crucial para o seu relatório)
add_action('upgrader_process_complete', 'vettryx_audit_log_updates', 10, 2);
function vettryx_audit_log_updates($upgrader_object, $options) {
    if ($options['action'] == 'update' && $options['type'] == 'plugin') {
        if (isset($options['plugins'])) {
            foreach ($options['plugins'] as $plugin) {
                vettryx_insert_audit_log('Atualizou Plugin', 'Sistema', $plugin);
            }
        }
    } elseif ($options['action'] == 'update' && $options['type'] == 'theme') {
        if (isset($options['themes'])) {
            foreach ($options['themes'] as $theme) {
                vettryx_insert_audit_log('Atualizou Tema', 'Sistema', $theme);
            }
        }
    } elseif ($options['action'] == 'update' && $options['type'] == 'core') {
        vettryx_insert_audit_log('Atualizou WordPress', 'Core', 'Versão Nova');
    }
}

/**
 * ==============================================================================
 * 3. INTERFACE DE VISUALIZAÇÃO NO PAINEL
 * ==============================================================================
 */
add_action('admin_menu', 'vettryx_audit_add_submenu', 99);
function vettryx_audit_add_submenu() {
    add_submenu_page(
        'vettryx-core-modules',
        'Audit Log - VETTRYX Tech',
        'Audit Log',
        'manage_options',
        'vettryx-wp-audit-log',
        'vettryx_audit_dashboard_html'
    );
}

function vettryx_audit_dashboard_html() {
    if (!current_user_can('manage_options')) return;

    global $wpdb;
    $table_name = $wpdb->prefix . 'vettryx_audit_log';

    // Pega os últimos 100 registros
    $logs = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 100");
    ?>
    <div class="wrap">
        <h1 style="display:flex; align-items:center; gap:10px; margin-bottom: 20px;">
            <span class="dashicons dashicons-visibility" style="font-size: 28px; width: 28px; height: 28px;"></span> 
            VETTRYX WP Audit Log
        </h1>
        <p>Monitoramento contínuo de segurança e edições do sistema. Os dados daqui alimentarão seus relatórios.</p>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 150px;">Data / Hora</th>
                    <th style="width: 150px;">Usuário</th>
                    <th style="width: 150px;">Ação</th>
                    <th style="width: 120px;">Tipo</th>
                    <th>Detalhe (Item)</th>
                    <th style="width: 120px;">IP</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)) : ?>
                    <tr><td colspan="6" style="padding: 15px; text-align: center;">Nenhum registro encontrado ainda. Faça um teste editando uma página!</td></tr>
                <?php else : ?>
                    <?php foreach ($logs as $log) : ?>
                        <tr>
                            <td><?php echo esc_html(wp_date('d/m/Y H:i', strtotime($log->created_at))); ?></td>
                            <td><strong><?php echo esc_html($log->user_name); ?></strong></td>
                            <td><?php echo esc_html($log->action); ?></td>
                            <td><?php echo esc_html($log->object_type); ?></td>
                            <td><?php echo esc_html($log->object_name); ?></td>
                            <td><?php echo esc_html($log->ip_address); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

/**
 * ==============================================================================
 * 4. ROTAÇÃO E LIMPEZA DE REGISTOS (CRON JOB)
 * Mantém a base de dados otimizada apagando registos com mais de 180 dias.
 * ==============================================================================
 */

// Agendamento seguro atrelado à inicialização do painel, sem usar hooks de desativação
add_action('admin_init', 'vettryx_audit_schedule_cleanup');
function vettryx_audit_schedule_cleanup() {
    if (!wp_next_scheduled('vettryx_audit_daily_cleanup')) {
        wp_schedule_event(time(), 'daily', 'vettryx_audit_daily_cleanup');
    }
}

// A função que faz a limpeza na base de dados
add_action('vettryx_audit_daily_cleanup', 'vettryx_audit_purge_old_records');
function vettryx_audit_purge_old_records() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'vettryx_audit_log';
    
    // Elimina tudo o que for mais antigo que 180 dias
    $wpdb->query("DELETE FROM $table_name WHERE created_at < DATE_SUB(NOW(), INTERVAL 180 DAY)");
}
