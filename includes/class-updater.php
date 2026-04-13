<?php
/**
 * Mises à jour distantes (manifest JSON).
 *
 * Filtre : `orion_wpagent_update_manifest_url` — URL renvoyant un JSON du type :
 * `{ "version": "1.0.2", "package": "https://…/orion-the-wp-agent-connector.zip" }`
 *
 * Si `version.json` contient une clé `update_manifest_url` non vide, elle est utilisée
 * sauf si le filtre retourne une chaîne non vide (le filtre prime).
 *
 * @package OrionWPAgent
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * URL du manifest JSON sur GitHub (raw). Surchargeable avant le chargement du fichier :
 * define('ORION_WPAGENT_UPDATE_URL', 'https://…/version.json');
 */
if (!defined('ORION_WPAGENT_UPDATE_URL')) {
    define(
        'ORION_WPAGENT_UPDATE_URL',
        'https://raw.githubusercontent.com/julienferla/orion-the-wp-agent-connector/main/version.json'
    );
}

class OrionWPAgent_Updater
{
    /** @var string Clé du cache site (manifest JSON distant). */
    public const TRANSIENT_KEY = 'orion_wpagent_remote_manifest';

    public static function init()
    {
        add_filter('site_transient_update_plugins', array(__CLASS__, 'maybe_inject_update'), 20, 1);
    }

    /**
     * @param object|false $transient
     * @return object|false
     */
    public static function maybe_inject_update($transient)
    {
        if (!is_object($transient) || empty($transient->checked)) {
            return $transient;
        }

        $plugin_file = plugin_basename(ORION_WPAGENT_PLUGIN_DIR . 'orion-the-wp-agent-connector.php');
        $manifest_url = self::resolve_manifest_url();
        if ($manifest_url === '') {
            return $transient;
        }

        $remote = get_site_transient(self::TRANSIENT_KEY);
        if (!is_array($remote)) {
            $response = wp_remote_get(
                $manifest_url,
                array(
                    'timeout' => 10,
                    'redirection' => 2,
                )
            );
            if (is_wp_error($response)) {
                return $transient;
            }
            $code = (int) wp_remote_retrieve_response_code($response);
            if ($code < 200 || $code >= 300) {
                return $transient;
            }
            $body = wp_remote_retrieve_body($response);
            $remote = json_decode($body, true);
            if (!is_array($remote) || empty($remote['version'])) {
                return $transient;
            }
            set_site_transient(self::TRANSIENT_KEY, $remote, 12 * HOUR_IN_SECONDS);
        }

        $new_ver = (string) $remote['version'];
        $package = '';
        if (!empty($remote['package']) && is_string($remote['package'])) {
            $package = (string) $remote['package'];
        } elseif (!empty($remote['download_url']) && is_string($remote['download_url'])) {
            $package = (string) $remote['download_url'];
        }
        if ($package === '' || !self::version_is_newer(ORION_WPAGENT_VERSION, $new_ver)) {
            return $transient;
        }

        if (!isset($transient->response) || !is_array($transient->response)) {
            $transient->response = array();
        }

        $transient->response[$plugin_file] = (object) array(
            'slug' => 'orion-the-wp-agent-connector',
            'plugin' => $plugin_file,
            'new_version' => $new_ver,
            'url' => isset($remote['url']) ? (string) $remote['url'] : '',
            'package' => $package,
            'id' => 'orion-the-wp-agent-connector',
        );

        return $transient;
    }

    /**
     * Vide le cache du manifeste distant (prochain passage re-fetch).
     * Utiliser delete_site_transient car set_site_transient est utilisé pour le cache.
     */
    public static function clear_remote_manifest_cache()
    {
        delete_site_transient(self::TRANSIENT_KEY);
    }

    /**
     * Récupère le manifeste HTTP pour debug (ne modifie pas le transient).
     *
     * @return array{ok:bool, code:int, body:string, decoded:?array, error:string}
     */
    public static function debug_http_fetch_manifest($url)
    {
        $out = array(
            'ok' => false,
            'code' => 0,
            'body' => '',
            'decoded' => null,
            'error' => '',
        );
        if ($url === '') {
            $out['error'] = 'empty_url';

            return $out;
        }
        $response = wp_remote_get(
            $url,
            array(
                'timeout' => 15,
                'redirection' => 3,
            )
        );
        if (is_wp_error($response)) {
            $out['error'] = $response->get_error_message();

            return $out;
        }
        $out['code'] = (int) wp_remote_retrieve_response_code($response);
        $out['body'] = (string) wp_remote_retrieve_body($response);
        $decoded = json_decode($out['body'], true);
        $out['decoded'] = is_array($decoded) ? $decoded : null;
        $out['ok'] = $out['code'] >= 200 && $out['code'] < 300
            && is_array($out['decoded'])
            && !empty($out['decoded']['version']);

        return $out;
    }

    /**
     * Indique si une mise à jour serait proposée (même logique que maybe_inject_update, sans toucher au transient core WP).
     *
     * @return array{would_update:bool, reason:string, package:string, remote_version:string}
     */
    public static function debug_would_offer_update($remote_row)
    {
        $new_ver = '';
        $package = '';
        if (is_array($remote_row) && !empty($remote_row['version'])) {
            $new_ver = (string) $remote_row['version'];
        }
        if (is_array($remote_row)) {
            if (!empty($remote_row['package']) && is_string($remote_row['package'])) {
                $package = (string) $remote_row['package'];
            } elseif (!empty($remote_row['download_url']) && is_string($remote_row['download_url'])) {
                $package = (string) $remote_row['download_url'];
            }
        }
        if ($package === '') {
            return array(
                'would_update' => false,
                'reason' => 'missing_package',
                'package' => '',
                'remote_version' => $new_ver,
            );
        }
        if ($new_ver === '') {
            return array(
                'would_update' => false,
                'reason' => 'missing_version',
                'package' => $package,
                'remote_version' => '',
            );
        }
        if (!self::version_is_newer(ORION_WPAGENT_VERSION, $new_ver)) {
            return array(
                'would_update' => false,
                'reason' => 'not_newer_than_local',
                'package' => $package,
                'remote_version' => $new_ver,
            );
        }

        return array(
            'would_update' => true,
            'reason' => 'ok',
            'package' => $package,
            'remote_version' => $new_ver,
        );
    }

    /**
     * @return string
     */
    public static function resolve_manifest_url()
    {
        $filtered = apply_filters('orion_wpagent_update_manifest_url', '');
        if (is_string($filtered) && $filtered !== '') {
            return $filtered;
        }
        $path = ORION_WPAGENT_PLUGIN_DIR . 'version.json';
        if (is_readable($path)) {
            $j = json_decode((string) file_get_contents($path), true);
            if (is_array($j) && !empty($j['update_manifest_url']) && is_string($j['update_manifest_url'])) {
                return $j['update_manifest_url'];
            }
        }
        if (defined('ORION_WPAGENT_UPDATE_URL') && is_string(ORION_WPAGENT_UPDATE_URL) && ORION_WPAGENT_UPDATE_URL !== '') {
            return ORION_WPAGENT_UPDATE_URL;
        }
        return '';
    }

    /**
     * Comparaison simple semver-like (segments numériques).
     *
     * @param string $current
     * @param string $remote
     */
    private static function version_is_newer($current, $remote)
    {
        return version_compare($current, $remote, '<');
    }
}

OrionWPAgent_Updater::init();
