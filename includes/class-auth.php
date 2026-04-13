<?php
/**
 * Vérifie le jeton Bearer-like envoyé par Orion The WP Agent.
 */
if (!defined('ABSPATH')) {
    exit;
}

class OrionWPAgent_Auth
{
    /**
     * @param WP_REST_Request $request
     * @return bool
     */
    public static function verify_token($request)
    {
        $stored = (string) get_option('orion_wpagent_token', '');
        if ($stored === '') {
            return false;
        }
        $header = $request->get_header('X-OrionWPAgent-Token');
        if (!is_string($header) || $header === '') {
            return false;
        }
        return hash_equals($stored, $header);
    }
}
