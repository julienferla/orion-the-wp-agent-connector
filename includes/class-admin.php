<?php
/**
 * Pages d’administration Réglages.
 *
 * @package OrionWPAgent
 */

if (!defined('ABSPATH')) {
    exit;
}

class OrionWPAgent_Admin
{
    public static function register_menu()
    {
        add_options_page(
            'Orion The WP Agent',
            'Orion The WP Agent',
            'manage_options',
            'orion-the-wp-agent',
            array(__CLASS__, 'render_settings')
        );

        add_submenu_page(
            'options-general.php',
            __('Orion The WP Agent — À propos', 'orion-the-wp-agent-connector'),
            __('Orion — À propos', 'orion-the-wp-agent-connector'),
            'manage_options',
            'orion-the-wp-agent-about',
            array(__CLASS__, 'render_about')
        );

        add_submenu_page(
            'options-general.php',
            __('Orion — Debug mises à jour (temporaire)', 'orion-the-wp-agent-connector'),
            __('Orion — Debug MAJ', 'orion-the-wp-agent-connector'),
            'manage_options',
            'orion-the-wp-agent-update-debug',
            array(__CLASS__, 'render_update_debug')
        );
    }

    /**
     * Traitement POST : vider le cache manifeste + rafraîchir les mises à jour WordPress.
     */
    public static function handle_admin_post()
    {
        if (empty($_POST['orion_wpagent_clear_update_cache'])) {
            return;
        }
        if (!current_user_can('manage_options')) {
            return;
        }
        check_admin_referer('orion_wpagent_clear_update_cache');
        OrionWPAgent_Updater::clear_remote_manifest_cache();
        wp_update_plugins();
        $referer = wp_get_referer();
        if (!$referer) {
            $referer = admin_url('options-general.php?page=orion-the-wp-agent-about');
        }
        wp_safe_redirect(add_query_arg('orion_cache_cleared', '1', $referer));
        exit;
    }

    public static function render_settings()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $token = (string) get_option('orion_wpagent_token', '');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Orion The WP Agent Connector', 'orion-the-wp-agent-connector'); ?></h1>
            <p><?php echo esc_html__('Copiez ce jeton dans votre tableau de bord Orion :', 'orion-the-wp-agent-connector'); ?></p>
            <input
                type="text"
                id="orion-wpagent-token-field"
                value="<?php echo esc_attr($token); ?>"
                readonly
                style="width:100%;font-family:monospace;padding:8px"
            />
            <p style="margin-top:16px">
                <?php echo esc_html__('URL du site :', 'orion-the-wp-agent-connector'); ?>
                <strong><?php echo esc_html(get_site_url()); ?></strong>
            </p>
            <p>
                <button type="button" class="button" id="orion-wpagent-copy-btn">
                    <?php echo esc_html__('Copier le jeton', 'orion-the-wp-agent-connector'); ?>
                </button>
            </p>
            <p class="description">
                <?php echo esc_html__('Limites PHP pour l’upload (REST multipart) : voir Réglages → Orion — À propos.', 'orion-the-wp-agent-connector'); ?>
            </p>
        </div>
        <script>
        (function () {
            var btn = document.getElementById('orion-wpagent-copy-btn');
            var field = document.getElementById('orion-wpagent-token-field');
            if (btn && field) {
                btn.addEventListener('click', function () {
                    field.select();
                    field.setSelectionRange(0, 99999);
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(field.value);
                    } else {
                        document.execCommand('copy');
                    }
                });
            }
        })();
        </script>
        <?php
    }

    public static function render_about()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        if (isset($_GET['orion_cache_cleared']) && $_GET['orion_cache_cleared'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo esc_html__(
                'Le cache du manifeste distant a été vidé et WordPress a relancé la vérification des mises à jour.',
                'orion-the-wp-agent-connector'
            );
            echo '</p></div>';
        }
        $upload_max = ini_get('upload_max_filesize');
        $post_max = ini_get('post_max_size');
        $mem = ini_get('memory_limit');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Orion The WP Agent — À propos', 'orion-the-wp-agent-connector'); ?></h1>
            <table class="widefat striped" style="max-width:640px">
                <tbody>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Version du connecteur', 'orion-the-wp-agent-connector'); ?></th>
                        <td><code><?php echo esc_html(ORION_WPAGENT_VERSION); ?></code></td>
                    </tr>
                    <tr>
                        <th scope="row">upload_max_filesize</th>
                        <td><code><?php echo esc_html($upload_max ? $upload_max : '—'); ?></code></td>
                    </tr>
                    <tr>
                        <th scope="row">post_max_size</th>
                        <td><code><?php echo esc_html($post_max ? $post_max : '—'); ?></code></td>
                    </tr>
                    <tr>
                        <th scope="row">memory_limit</th>
                        <td><code><?php echo esc_html($mem ? $mem : '—'); ?></code></td>
                    </tr>
                </tbody>
            </table>
            <p class="description" style="max-width:640px;margin-top:1em">
                <?php echo esc_html__('Les uploads via l’API (multipart) sont limités par upload_max_filesize et post_max_size. Si un upload échoue sans message clair, augmentez ces valeurs dans php.ini ou la configuration du serveur.', 'orion-the-wp-agent-connector'); ?>
            </p>
            <p class="description" style="max-width:640px">
                <?php echo esc_html__('Mises à jour automatiques : définissez l’URL d’un manifest JSON (clé update_manifest_url dans version.json, ou filtre orion_wpagent_update_manifest_url).', 'orion-the-wp-agent-connector'); ?>
            </p>
            <form method="post" action="<?php echo esc_url(admin_url('options-general.php?page=orion-the-wp-agent-about')); ?>" style="margin-top:1.5em">
                <?php wp_nonce_field('orion_wpagent_clear_update_cache'); ?>
                <input type="hidden" name="orion_wpagent_clear_update_cache" value="1" />
                <p>
                    <button type="submit" class="button button-secondary">
                        <?php echo esc_html__('Vider le cache de mise à jour', 'orion-the-wp-agent-connector'); ?>
                    </button>
                </p>
                <p class="description" style="max-width:640px">
                    <?php echo esc_html__(
                        'Supprime le transient du manifeste distant (orion_wpagent_remote_manifest) et appelle wp_update_plugins() pour forcer une nouvelle vérification.',
                        'orion-the-wp-agent-connector'
                    ); ?>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Page admin temporaire : diagnostic manifeste / transient / versions.
     */
    public static function render_update_debug()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $manifest_url = OrionWPAgent_Updater::resolve_manifest_url();
        $live = OrionWPAgent_Updater::debug_http_fetch_manifest($manifest_url);
        $cached = get_site_transient(OrionWPAgent_Updater::TRANSIENT_KEY);
        $would_from_cache = is_array($cached)
            ? OrionWPAgent_Updater::debug_would_offer_update($cached)
            : null;
        $would_from_live = is_array($live['decoded'])
            ? OrionWPAgent_Updater::debug_would_offer_update($live['decoded'])
            : null;
        $plugin_file = plugin_basename(ORION_WPAGENT_PLUGIN_DIR . 'orion-the-wp-agent-connector.php');
        $core_update = get_site_transient('update_plugins');
        $in_response = false;
        if (is_object($core_update) && isset($core_update->response) && is_array($core_update->response)) {
            $in_response = isset($core_update->response[$plugin_file]);
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Orion — Debug mises à jour', 'orion-the-wp-agent-connector'); ?></h1>
            <p class="description">
                <?php echo esc_html__(
                    'Page temporaire pour vérifier le manifeste distant et le cache. À retirer du menu une fois le problème résolu.',
                    'orion-the-wp-agent-connector'
                ); ?>
            </p>
            <h2><?php echo esc_html__('URL du manifeste résolue', 'orion-the-wp-agent-connector'); ?></h2>
            <p><code style="word-break:break-all"><?php echo esc_html($manifest_url !== '' ? $manifest_url : '(vide)'); ?></code></p>
            <h2><?php echo esc_html__('Versions', 'orion-the-wp-agent-connector'); ?></h2>
            <table class="widefat striped" style="max-width:900px">
                <tbody>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Version locale (ORION_WPAGENT_VERSION)', 'orion-the-wp-agent-connector'); ?></th>
                        <td><code><?php echo esc_html(ORION_WPAGENT_VERSION); ?></code></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Version dans le transient (cache manifeste)', 'orion-the-wp-agent-connector'); ?></th>
                        <td>
                            <?php
                            if (is_array($cached) && isset($cached['version'])) {
                                echo '<code>' . esc_html((string) $cached['version']) . '</code>';
                            } else {
                                echo '—';
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Version dans le manifeste HTTP (live)', 'orion-the-wp-agent-connector'); ?></th>
                        <td>
                            <?php
                            if (is_array($live['decoded']) && isset($live['decoded']['version'])) {
                                echo '<code>' . esc_html((string) $live['decoded']['version']) . '</code>';
                            } else {
                                echo '—';
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Mise à jour injectée (transient update_plugins)', 'orion-the-wp-agent-connector'); ?></th>
                        <td><?php echo $in_response ? esc_html__('Oui (entrée response pour ce plugin)', 'orion-the-wp-agent-connector') : esc_html__('Non', 'orion-the-wp-agent-connector'); ?></td>
                    </tr>
                </tbody>
            </table>
            <h2><?php echo esc_html__('Analyse « offrirait une mise à jour »', 'orion-the-wp-agent-connector'); ?></h2>
            <table class="widefat striped" style="max-width:900px">
                <tbody>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Depuis le transient', 'orion-the-wp-agent-connector'); ?></th>
                        <td><pre style="white-space:pre-wrap"><?php echo esc_html(wp_json_encode($would_from_cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Depuis le manifeste live', 'orion-the-wp-agent-connector'); ?></th>
                        <td><pre style="white-space:pre-wrap"><?php echo esc_html(wp_json_encode($would_from_live, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre></td>
                    </tr>
                </tbody>
            </table>
            <h2><?php echo esc_html__('Requête HTTP live', 'orion-the-wp-agent-connector'); ?></h2>
            <p>
                <code>HTTP <?php echo esc_html((string) $live['code']); ?></code>
                <?php if ($live['error'] !== '') { ?>
                    — <span style="color:#b32d2e"><?php echo esc_html($live['error']); ?></span>
                <?php } ?>
                <?php if ($live['ok']) { ?>
                    — <span style="color:#00a32a"><?php echo esc_html__('JSON valide avec clé version', 'orion-the-wp-agent-connector'); ?></span>
                <?php } ?>
            </p>
            <h3><?php echo esc_html__('Corps brut du manifeste', 'orion-the-wp-agent-connector'); ?></h3>
            <pre style="max-height:320px;overflow:auto;background:#f6f7f7;padding:12px;border:1px solid #c3c4c7"><?php echo esc_html($live['body']); ?></pre>
            <h2><?php echo esc_html__('Transient WordPress (get_site_transient)', 'orion-the-wp-agent-connector'); ?></h2>
            <p><code><?php echo esc_html(OrionWPAgent_Updater::TRANSIENT_KEY); ?></code></p>
            <pre style="max-height:320px;overflow:auto;background:#f6f7f7;padding:12px;border:1px solid #c3c4c7"><?php
            echo esc_html(
                $cached === false
                    ? '(false — transient absent)'
                    : wp_json_encode($cached, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );
            ?></pre>
        </div>
        <?php
    }
}
