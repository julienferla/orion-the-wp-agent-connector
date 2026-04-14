<?php
/**
 * Page d’administration unique sous Réglages (onglets natifs WordPress).
 *
 * @package OrionWPAgent
 */

if (!defined('ABSPATH')) {
    exit;
}

class OrionWPAgent_Admin
{
    public const PAGE_SLUG = 'orion-the-wp-agent';

    /**
     * @return array<string, string>
     */
    private static function tabs()
    {
        return array(
            'connexion' => __('Connexion', 'orion-the-wp-agent-connector'),
            'about' => __('À propos', 'orion-the-wp-agent-connector'),
            'debug' => __('Debug', 'orion-the-wp-agent-connector'),
        );
    }

    public static function register_menu()
    {
        add_options_page(
            __('Orion The WP Agent', 'orion-the-wp-agent-connector'),
            __('Orion The WP Agent', 'orion-the-wp-agent-connector'),
            'manage_options',
            self::PAGE_SLUG,
            array(__CLASS__, 'render_page')
        );
    }

    /**
     * @return string
     */
    private static function current_tab()
    {
        $tabs = self::tabs();
        $raw = isset($_GET['tab']) ? sanitize_key((string) wp_unslash($_GET['tab'])) : 'connexion';
        if ($raw === '') {
            $raw = 'connexion';
        }
        if (!isset($tabs[$raw])) {
            return 'connexion';
        }

        return $raw;
    }

    /**
     * @return string
     */
    private static function page_url($tab = null)
    {
        $args = array('page' => self::PAGE_SLUG);
        if ($tab !== null && $tab !== '') {
            $args['tab'] = $tab;
        }

        return add_query_arg($args, admin_url('options-general.php'));
    }

    /**
     * Traitement POST : cache manifeste, vérification MAJ, régénération jeton.
     */
    public static function handle_admin_post()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!empty($_POST['orion_wpagent_clear_update_cache'])) {
            check_admin_referer('orion_wpagent_clear_update_cache');
            OrionWPAgent_Updater::clear_remote_manifest_cache();
            wp_update_plugins();
            wp_safe_redirect(
                add_query_arg(
                    array(
                        'page' => self::PAGE_SLUG,
                        'tab' => 'debug',
                        'orion_cache_cleared' => '1',
                    ),
                    admin_url('options-general.php')
                )
            );
            exit;
        }

        if (!empty($_POST['orion_wpagent_check_updates'])) {
            check_admin_referer('orion_wpagent_check_updates');
            OrionWPAgent_Updater::clear_remote_manifest_cache();
            wp_update_plugins();
            wp_safe_redirect(
                add_query_arg(
                    array(
                        'page' => self::PAGE_SLUG,
                        'tab' => 'about',
                        'orion_updates_checked' => '1',
                    ),
                    admin_url('options-general.php')
                )
            );
            exit;
        }

        if (!empty($_POST['orion_wpagent_regenerate_token'])) {
            check_admin_referer('orion_wpagent_regenerate_token');
            update_option('orion_wpagent_token', bin2hex(random_bytes(32)));
            wp_safe_redirect(
                add_query_arg(
                    array(
                        'page' => self::PAGE_SLUG,
                        'tab' => 'connexion',
                        'orion_token_regenerated' => '1',
                    ),
                    admin_url('options-general.php')
                )
            );
            exit;
        }
    }

    public static function render_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $tab = self::current_tab();
        $tabs = self::tabs();

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Orion The WP Agent Connector', 'orion-the-wp-agent-connector'); ?></h1>

            <?php self::render_notices(); ?>

            <nav class="nav-tab-wrapper wp-clearfix" aria-label="<?php esc_attr_e('Onglets des réglages Orion', 'orion-the-wp-agent-connector'); ?>">
                <?php
                foreach ($tabs as $slug => $label) {
                    $url = self::page_url($slug);
                    $classes = 'nav-tab';
                    if ($tab === $slug) {
                        $classes .= ' nav-tab-active';
                    }
                    printf(
                        '<a href="%s" class="%s">%s</a>',
                        esc_url($url),
                        esc_attr($classes),
                        esc_html($label)
                    );
                }
                ?>
            </nav>

            <div class="orion-wpagent-tab-panel" style="margin-top:1.25em">
                <?php
                if ($tab === 'connexion') {
                    self::render_tab_connexion();
                } elseif ($tab === 'about') {
                    self::render_tab_about();
                } else {
                    self::render_tab_debug();
                }
                ?>
            </div>
        </div>
        <?php
    }

    private static function render_notices()
    {
        if (isset($_GET['orion_cache_cleared']) && $_GET['orion_cache_cleared'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo esc_html__(
                'Le cache du manifeste distant a été vidé et WordPress a relancé la vérification des mises à jour.',
                'orion-the-wp-agent-connector'
            );
            echo '</p></div>';
        }
        if (isset($_GET['orion_updates_checked']) && $_GET['orion_updates_checked'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo esc_html__(
                'Vérification des mises à jour effectuée. Consultez Extensions pour installer une mise à jour éventuelle.',
                'orion-the-wp-agent-connector'
            );
            echo '</p></div>';
        }
        if (isset($_GET['orion_token_regenerated']) && $_GET['orion_token_regenerated'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo esc_html__(
                'Un nouveau jeton a été généré. Mettez à jour la valeur dans votre tableau de bord Orion.',
                'orion-the-wp-agent-connector'
            );
            echo '</p></div>';
        }
    }

    private static function render_tab_connexion()
    {
        $token = (string) get_option('orion_wpagent_token', '');
        ?>
        <p><?php echo esc_html__('Copiez ce jeton dans votre tableau de bord Orion :', 'orion-the-wp-agent-connector'); ?></p>
        <input
            type="text"
            id="orion-wpagent-token-field"
            value="<?php echo esc_attr($token); ?>"
            readonly
            class="large-text code"
            style="font-family:monospace;padding:8px"
        />
        <p style="margin-top:16px">
            <?php echo esc_html__('URL du site :', 'orion-the-wp-agent-connector'); ?>
            <strong><?php echo esc_html(get_site_url()); ?></strong>
        </p>
        <p>
            <button type="button" class="button button-primary" id="orion-wpagent-copy-btn">
                <?php echo esc_html__('Copier le jeton', 'orion-the-wp-agent-connector'); ?>
            </button>
        </p>
        <p class="description">
            <?php echo esc_html__('Après une régénération du jeton (onglet Debug), mettez à jour Orion avec la nouvelle valeur.', 'orion-the-wp-agent-connector'); ?>
        </p>
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

    private static function render_tab_about()
    {
        $releases = 'https://github.com/julienferla/orion-the-wp-agent-connector/releases';
        ?>
        <table class="widefat striped" style="max-width:640px">
            <tbody>
                <tr>
                    <th scope="row"><?php echo esc_html__('Version installée', 'orion-the-wp-agent-connector'); ?></th>
                    <td><code><?php echo esc_html(ORION_WPAGENT_VERSION); ?></code></td>
                </tr>
            </tbody>
        </table>
        <p style="margin-top:1.25em">
            <form method="post" action="<?php echo esc_url(self::page_url('about')); ?>" style="display:inline">
                <?php wp_nonce_field('orion_wpagent_check_updates'); ?>
                <input type="hidden" name="orion_wpagent_check_updates" value="1" />
                <button type="submit" class="button button-secondary">
                    <?php echo esc_html__('Vérifier les mises à jour', 'orion-the-wp-agent-connector'); ?>
                </button>
            </form>
            <span class="description" style="margin-left:8px">
                <?php echo esc_html__('Interroge le manifeste distant et le tableau des mises à jour WordPress.', 'orion-the-wp-agent-connector'); ?>
            </span>
        </p>
        <h2 class="title" style="margin-top:1.5em"><?php echo esc_html__('Changelog', 'orion-the-wp-agent-connector'); ?></h2>
        <p>
            <?php
            echo wp_kses_post(
                sprintf(
                    /* translators: %s: GitHub releases URL */
                    __('Les notes de version détaillées sont publiées sur <a href="%s" target="_blank" rel="noopener noreferrer">GitHub Releases</a>.', 'orion-the-wp-agent-connector'),
                    esc_url($releases)
                )
            );
            ?>
        </p>
        <ul class="ul-disc" style="max-width:640px">
            <li><strong>1.1.1</strong> — <?php echo esc_html__('Patch : hint d’échec = extrait post_content brut (~500 car.) autour d’un mot-clé (DB réelle, &nbsp; / shortcodes).', 'orion-the-wp-agent-connector'); ?></li>
            <li><strong>1.1.0</strong> — <?php echo esc_html__('Patch / bulk_patch : candidats search incluant texte sans balises (shortcodes Divi) ; LIKE multi-candidats en bulk.', 'orion-the-wp-agent-connector'); ?></li>
            <li><strong>1.0.9</strong> — <?php echo esc_html__('bulk_patch_content (post_type post/page/all, SQL) ; patch search normalisé + indice si échec.', 'orion-the-wp-agent-connector'); ?></li>
            <li><strong>1.0.8</strong> — <?php echo esc_html__('Interface d’administration à onglets unique ; README GitHub.', 'orion-the-wp-agent-connector'); ?></li>
            <li><strong>1.0.7</strong> — <?php echo esc_html__('Mises à jour automatiques via manifeste JSON.', 'orion-the-wp-agent-connector'); ?></li>
        </ul>
        <?php
    }

    private static function render_tab_debug()
    {
        $upload_max = ini_get('upload_max_filesize');
        $post_max = ini_get('post_max_size');
        $mem = ini_get('memory_limit');
        $php_ver = PHP_VERSION;
        ?>
        <table class="widefat striped" style="max-width:640px">
            <tbody>
                <tr>
                    <th scope="row"><?php echo esc_html__('Version PHP', 'orion-the-wp-agent-connector'); ?></th>
                    <td><code><?php echo esc_html($php_ver); ?></code></td>
                </tr>
                <tr>
                    <th scope="row"><code>upload_max_filesize</code></th>
                    <td><code><?php echo esc_html($upload_max ? $upload_max : '—'); ?></code></td>
                </tr>
                <tr>
                    <th scope="row"><code>post_max_size</code></th>
                    <td><code><?php echo esc_html($post_max ? $post_max : '—'); ?></code></td>
                </tr>
                <tr>
                    <th scope="row"><code>memory_limit</code></th>
                    <td><code><?php echo esc_html($mem ? $mem : '—'); ?></code></td>
                </tr>
            </tbody>
        </table>
        <p class="description" style="max-width:640px;margin-top:1em">
            <?php echo esc_html__('Les uploads via l’API (multipart) sont limités par upload_max_filesize et post_max_size.', 'orion-the-wp-agent-connector'); ?>
        </p>

        <h2 class="title" style="margin-top:2em"><?php echo esc_html__('Mises à jour', 'orion-the-wp-agent-connector'); ?></h2>
        <form method="post" action="<?php echo esc_url(self::page_url('debug')); ?>" style="margin-bottom:1.5em">
            <?php wp_nonce_field('orion_wpagent_clear_update_cache'); ?>
            <input type="hidden" name="orion_wpagent_clear_update_cache" value="1" />
            <p>
                <button type="submit" class="button button-secondary">
                    <?php echo esc_html__('Vider le cache de mise à jour', 'orion-the-wp-agent-connector'); ?>
                </button>
            </p>
            <p class="description" style="max-width:640px">
                <?php echo esc_html__(
                    'Supprime le transient du manifeste distant et appelle wp_update_plugins() pour forcer une nouvelle vérification.',
                    'orion-the-wp-agent-connector'
                ); ?>
            </p>
        </form>

        <h2 class="title"><?php echo esc_html__('Sécurité', 'orion-the-wp-agent-connector'); ?></h2>
        <form method="post" action="<?php echo esc_url(self::page_url('debug')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Régénérer le jeton ? Les clients (Orion) devront être mis à jour avec la nouvelle valeur.', 'orion-the-wp-agent-connector')); ?>');">
            <?php wp_nonce_field('orion_wpagent_regenerate_token'); ?>
            <input type="hidden" name="orion_wpagent_regenerate_token" value="1" />
            <p>
                <button type="submit" class="button button-secondary">
                    <?php echo esc_html__('Régénérer le jeton', 'orion-the-wp-agent-connector'); ?>
                </button>
            </p>
            <p class="description" style="max-width:640px">
                <?php echo esc_html__(
                    'Génère un nouveau jeton d’API et invalide l’ancien. À utiliser en cas de fuite ou de rotation planifiée.',
                    'orion-the-wp-agent-connector'
                ); ?>
            </p>
        </form>

        <?php self::render_debug_advanced(); ?>
        <?php
    }

    /**
     * Diagnostic manifeste / transient (ex-page « Debug MAJ »).
     */
    private static function render_debug_advanced()
    {
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
        <details style="margin-top:2em;max-width:960px">
            <summary style="cursor:pointer;font-weight:600">
                <?php echo esc_html__('Diagnostic mises à jour (avancé)', 'orion-the-wp-agent-connector'); ?>
            </summary>
            <div style="margin-top:1em">
                <h3><?php echo esc_html__('URL du manifeste résolue', 'orion-the-wp-agent-connector'); ?></h3>
                <p><code style="word-break:break-all"><?php echo esc_html($manifest_url !== '' ? $manifest_url : '(vide)'); ?></code></p>
                <h3><?php echo esc_html__('Versions', 'orion-the-wp-agent-connector'); ?></h3>
                <table class="widefat striped" style="max-width:900px">
                    <tbody>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Version locale', 'orion-the-wp-agent-connector'); ?></th>
                            <td><code><?php echo esc_html(ORION_WPAGENT_VERSION); ?></code></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo esc_html__('Version dans le transient (cache)', 'orion-the-wp-agent-connector'); ?></th>
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
                            <th scope="row"><?php echo esc_html__('Entrée dans update_plugins', 'orion-the-wp-agent-connector'); ?></th>
                            <td><?php echo $in_response ? esc_html__('Oui', 'orion-the-wp-agent-connector') : esc_html__('Non', 'orion-the-wp-agent-connector'); ?></td>
                        </tr>
                    </tbody>
                </table>
                <h3><?php echo esc_html__('Analyse « offrirait une mise à jour »', 'orion-the-wp-agent-connector'); ?></h3>
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
                <h3><?php echo esc_html__('Requête HTTP live', 'orion-the-wp-agent-connector'); ?></h3>
                <p>
                    <code>HTTP <?php echo esc_html((string) $live['code']); ?></code>
                    <?php if ($live['error'] !== '') { ?>
                        — <span style="color:#b32d2e"><?php echo esc_html($live['error']); ?></span>
                    <?php } ?>
                    <?php if ($live['ok']) { ?>
                        — <span style="color:#00a32a"><?php echo esc_html__('JSON valide avec clé version', 'orion-the-wp-agent-connector'); ?></span>
                    <?php } ?>
                </p>
                <h4><?php echo esc_html__('Corps brut du manifeste', 'orion-the-wp-agent-connector'); ?></h4>
                <pre style="max-height:320px;overflow:auto;background:#f6f7f7;padding:12px;border:1px solid #c3c4c7"><?php echo esc_html($live['body']); ?></pre>
                <h4><?php echo esc_html__('Transient', 'orion-the-wp-agent-connector'); ?> <code><?php echo esc_html(OrionWPAgent_Updater::TRANSIENT_KEY); ?></code></h4>
                <pre style="max-height:320px;overflow:auto;background:#f6f7f7;padding:12px;border:1px solid #c3c4c7"><?php
                echo esc_html(
                    $cached === false
                        ? '(false — transient absent)'
                        : wp_json_encode($cached, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                );
                ?></pre>
            </div>
        </details>
        <?php
    }
}
