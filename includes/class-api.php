<?php
if (!defined('ABSPATH')) {
    exit;
}

class OrionWPAgent_API
{
    private const NS = 'orion-wpagent/v1';

    public function register_routes()
    {
        register_rest_route(self::NS, '/ping', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'handle_ping'),
            'permission_callback' => array('OrionWPAgent_Auth', 'verify_token'),
        ));

        register_rest_route(self::NS, '/version', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'handle_version'),
            'permission_callback' => array('OrionWPAgent_Auth', 'verify_token'),
        ));

        register_rest_route(self::NS, '/posts', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'handle_get_posts'),
                'permission_callback' => array('OrionWPAgent_Auth', 'verify_token'),
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'handle_create_post'),
                'permission_callback' => array('OrionWPAgent_Auth', 'verify_token'),
            ),
        ));

        register_rest_route(self::NS, '/posts/search', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'handle_search_posts'),
            'permission_callback' => array('OrionWPAgent_Auth', 'verify_token'),
        ));

        register_rest_route(self::NS, '/posts/by-url', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'handle_get_post_by_url'),
            'permission_callback' => array('OrionWPAgent_Auth', 'verify_token'),
        ));

        register_rest_route(self::NS, '/posts/(?P<id>\\d+)/content', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'handle_get_post_content'),
            'permission_callback' => array('OrionWPAgent_Auth', 'verify_token'),
        ));

        register_rest_route(self::NS, '/posts/(?P<id>\\d+)/patch-content', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'handle_patch_post_content'),
            'permission_callback' => array('OrionWPAgent_Auth', 'verify_token'),
        ));

        register_rest_route(self::NS, '/posts/(?P<id>\\d+)/replace-link', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'handle_replace_link_post'),
            'permission_callback' => array('OrionWPAgent_Auth', 'verify_token'),
        ));

        register_rest_route(self::NS, '/posts/(?P<id>\\d+)/links', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'handle_replace_link_post'),
            'permission_callback' => array('OrionWPAgent_Auth', 'verify_token'),
        ));

        register_rest_route(self::NS, '/posts/(?P<id>\\d+)/divi/background', array(
            'methods' => 'POST, PUT, PATCH, DELETE',
            'callback' => array($this, 'handle_divi_bg_post'),
            'permission_callback' => array('OrionWPAgent_Auth', 'verify_token'),
        ));

        register_rest_route(self::NS, '/posts/(?P<id>\\d+)/divi/image', array(
            'methods' => 'POST, PUT, PATCH, DELETE',
            'callback' => array($this, 'handle_divi_image_post'),
            'permission_callback' => array('OrionWPAgent_Auth', 'verify_token'),
        ));

        register_rest_route(self::NS, '/posts/(?P<id>\\d+)/divi/background-image', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'handle_divi_bg_post'),
            'permission_callback' => array('OrionWPAgent_Auth', 'verify_token'),
        ));

        register_rest_route(self::NS, '/posts/(?P<id>\\d+)/divi/image-module', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'handle_divi_image_post'),
            'permission_callback' => array('OrionWPAgent_Auth', 'verify_token'),
        ));

        register_rest_route(self::NS, '/posts/(?P<id>\\d+)/restore-backup', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'handle_restore_post_backup'),
            'permission_callback' => array('OrionWPAgent_Auth', 'verify_token'),
        ));

        register_rest_route(self::NS, '/posts/(?P<id>\\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'handle_get_post'),
                'permission_callback' => array('OrionWPAgent_Auth', 'verify_token'),
            ),
            array(
                'methods' => 'POST, PUT, PATCH, DELETE',
                'callback' => array($this, 'handle_post_by_id_write'),
                'permission_callback' => array('OrionWPAgent_Auth', 'verify_token'),
            ),
        ));

        register_rest_route(self::NS, '/pages', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'handle_get_pages'),
                'permission_callback' => array('OrionWPAgent_Auth', 'verify_token'),
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'handle_create_page'),
                'permission_callback' => array('OrionWPAgent_Auth', 'verify_token'),
            ),
        ));

        register_rest_route(self::NS, '/pages/(?P<id>\\d+)/content', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'handle_get_page_content'),
            'permission_callback' => array('OrionWPAgent_Auth', 'verify_token'),
        ));

        register_rest_route(self::NS, '/pages/(?P<id>\\d+)/patch-content', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'handle_patch_page_content'),
            'permission_callback' => array('OrionWPAgent_Auth', 'verify_token'),
        ));

        register_rest_route(self::NS, '/pages/(?P<id>\\d+)/replace-link', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'handle_replace_link_page'),
            'permission_callback' => array('OrionWPAgent_Auth', 'verify_token'),
        ));

        register_rest_route(self::NS, '/pages/(?P<id>\\d+)/links', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'handle_replace_link_page'),
            'permission_callback' => array('OrionWPAgent_Auth', 'verify_token'),
        ));

        register_rest_route(self::NS, '/pages/(?P<id>\\d+)/divi/background', array(
            'methods' => 'POST, PUT, PATCH, DELETE',
            'callback' => array($this, 'handle_divi_bg_page'),
            'permission_callback' => array('OrionWPAgent_Auth', 'verify_token'),
        ));

        register_rest_route(self::NS, '/pages/(?P<id>\\d+)/divi/image', array(
            'methods' => 'POST, PUT, PATCH, DELETE',
            'callback' => array($this, 'handle_divi_image_page'),
            'permission_callback' => array('OrionWPAgent_Auth', 'verify_token'),
        ));

        register_rest_route(self::NS, '/pages/(?P<id>\\d+)/divi/background-image', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'handle_divi_bg_page'),
            'permission_callback' => array('OrionWPAgent_Auth', 'verify_token'),
        ));

        register_rest_route(self::NS, '/pages/(?P<id>\\d+)/divi/image-module', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'handle_divi_image_page'),
            'permission_callback' => array('OrionWPAgent_Auth', 'verify_token'),
        ));

        register_rest_route(self::NS, '/pages/(?P<id>\\d+)/restore-backup', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'handle_restore_post_backup'),
            'permission_callback' => array('OrionWPAgent_Auth', 'verify_token'),
        ));

        register_rest_route(self::NS, '/pages/(?P<id>\\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'handle_get_page'),
                'permission_callback' => array('OrionWPAgent_Auth', 'verify_token'),
            ),
            array(
                'methods' => 'POST, PUT, PATCH',
                'callback' => array($this, 'handle_page_by_id_write'),
                'permission_callback' => array('OrionWPAgent_Auth', 'verify_token'),
            ),
        ));

        register_rest_route(self::NS, '/media', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'handle_get_media'),
                'permission_callback' => array('OrionWPAgent_Auth', 'verify_token'),
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'handle_post_media'),
                'permission_callback' => array('OrionWPAgent_Auth', 'verify_token'),
            ),
        ));

        register_rest_route(self::NS, '/media/(?P<id>\\d+)', array(
            'methods' => 'POST, DELETE',
            'callback' => array($this, 'handle_media_by_id_write'),
            'permission_callback' => array('OrionWPAgent_Auth', 'verify_token'),
        ));

        register_rest_route(self::NS, '/site-info', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'handle_site_info'),
            'permission_callback' => array('OrionWPAgent_Auth', 'verify_token'),
        ));
    }

    /**
     * Méthode HTTP « réelle » : tunnel POST (Apache) via en-tête ou corps JSON.
     *
     * @param WP_REST_Request $request
     * @return string GET, POST, PUT, PATCH, DELETE, …
     */
    private function get_effective_http_method($request)
    {
        $h = $request->get_header('X-HTTP-Method-Override');
        if (is_string($h) && $h !== '') {
            return strtoupper(trim($h));
        }
        $body = $request->get_json_params();
        if (is_array($body) && !empty($body['_method']) && is_string($body['_method'])) {
            return strtoupper(trim($body['_method']));
        }
        $p = $request->get_param('_method');
        if (is_string($p) && $p !== '') {
            return strtoupper(trim($p));
        }
        return strtoupper((string) $request->get_method());
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_ping($request)
    {
        return rest_ensure_response(OrionWPAgent_Actions::ping());
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_version($request)
    {
        return new WP_REST_Response(array('version' => ORION_WPAGENT_VERSION), 200);
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_get_posts($request)
    {
        $params = array(
            'status' => $request->get_param('status'),
            'per_page' => $request->get_param('per_page'),
        );
        $data = OrionWPAgent_Actions::get_posts($params);
        if (is_wp_error($data)) {
            return $data;
        }
        return rest_ensure_response($data);
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_search_posts($request)
    {
        $params = array(
            'search' => $request->get_param('search'),
        );
        $data = OrionWPAgent_Actions::search_posts($params);
        if (is_wp_error($data)) {
            return $data;
        }
        return rest_ensure_response($data);
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_get_post_by_url($request)
    {
        $params = array(
            'url' => $request->get_param('url'),
        );
        $data = OrionWPAgent_Actions::get_post_by_url($params);
        if (is_wp_error($data)) {
            return $data;
        }
        return rest_ensure_response($data);
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_get_post_content($request)
    {
        $id = (int) $request['id'];
        $data = OrionWPAgent_Actions::get_post_content($id);
        if (is_wp_error($data)) {
            return $data;
        }
        return rest_ensure_response($data);
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_patch_post_content($request)
    {
        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = array();
        }
        $params = array(
            'post_id' => (int) $request['id'],
            'search' => isset($body['search']) ? (string) $body['search'] : '',
            'replace' => isset($body['replace']) ? (string) $body['replace'] : '',
        );
        $data = OrionWPAgent_Actions::patch_post_content($params);
        if (is_wp_error($data)) {
            return $data;
        }
        return rest_ensure_response($data);
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_restore_post_backup($request)
    {
        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = array();
        }
        $params = array(
            'post_id' => (int) $request['id'],
            'backup_key' => isset($body['backup_key']) ? (string) $body['backup_key'] : '',
        );
        $data = OrionWPAgent_Actions::restore_post_backup($params);
        if (is_wp_error($data)) {
            return $data;
        }
        return rest_ensure_response($data);
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_get_page_content($request)
    {
        $id = (int) $request['id'];
        $data = OrionWPAgent_Actions::get_page_content($id);
        if (is_wp_error($data)) {
            return $data;
        }
        return rest_ensure_response($data);
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_patch_page_content($request)
    {
        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = array();
        }
        $params = array(
            'page_id' => (int) $request['id'],
            'search' => isset($body['search']) ? (string) $body['search'] : '',
            'replace' => isset($body['replace']) ? (string) $body['replace'] : '',
        );
        $data = OrionWPAgent_Actions::patch_page_content($params);
        if (is_wp_error($data)) {
            return $data;
        }
        return rest_ensure_response($data);
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_create_post($request)
    {
        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = array();
        }
        $data = OrionWPAgent_Actions::create_post($body);
        if (is_wp_error($data)) {
            return $data;
        }
        return rest_ensure_response($data);
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_get_post($request)
    {
        $id = (int) $request['id'];
        $post = get_post($id);
        if (!$post instanceof WP_Post || $post->post_type !== 'post') {
            return new WP_Error('orion_not_found', 'Article introuvable', array('status' => 404));
        }
        return rest_ensure_response(OrionWPAgent_Actions::serialize_post_public($post));
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_update_post($request)
    {
        $id = (int) $request['id'];
        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = array();
        }
        unset($body['_method']);
        $data = OrionWPAgent_Actions::update_post($id, $body);
        if (is_wp_error($data)) {
            return $data;
        }
        return rest_ensure_response($data);
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_delete_post($request)
    {
        $id = (int) $request['id'];
        $data = OrionWPAgent_Actions::delete_post($id);
        if (is_wp_error($data)) {
            return $data;
        }
        return rest_ensure_response($data);
    }

    /**
     * PUT / PATCH / DELETE (ou POST + override) sur /posts/{id}.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_post_by_id_write($request)
    {
        $method = $this->get_effective_http_method($request);
        if ($method === 'DELETE') {
            return $this->handle_delete_post($request);
        }
        if (in_array($method, array('PUT', 'PATCH', 'POST'), true)) {
            return $this->handle_update_post($request);
        }
        return new WP_Error('orion_method_not_allowed', __('Method not allowed.', 'orion-wp-agent'), array('status' => 405));
    }

    /**
     * PUT / PATCH (ou POST + override) sur /pages/{id}.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_page_by_id_write($request)
    {
        $method = $this->get_effective_http_method($request);
        if (in_array($method, array('PUT', 'PATCH', 'POST'), true)) {
            return $this->handle_update_page($request);
        }
        return new WP_Error('orion_method_not_allowed', __('Method not allowed.', 'orion-wp-agent'), array('status' => 405));
    }

    /**
     * DELETE (ou POST + override) sur /media/{id}.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_media_by_id_write($request)
    {
        $method = $this->get_effective_http_method($request);
        if ($method === 'DELETE') {
            return $this->handle_delete_media($request);
        }
        return new WP_Error('orion_method_not_allowed', __('Method not allowed.', 'orion-wp-agent'), array('status' => 405));
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_get_pages($request)
    {
        $params = array(
            'per_page' => $request->get_param('per_page'),
        );
        $data = OrionWPAgent_Actions::get_pages($params);
        if (is_wp_error($data)) {
            return $data;
        }
        return rest_ensure_response($data);
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_create_page($request)
    {
        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = array();
        }
        $data = OrionWPAgent_Actions::create_page($body);
        if (is_wp_error($data)) {
            return $data;
        }
        return rest_ensure_response($data);
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_get_page($request)
    {
        $id = (int) $request['id'];
        $post = get_post($id);
        if (!$post instanceof WP_Post || $post->post_type !== 'page') {
            return new WP_Error('orion_not_found', 'Page introuvable', array('status' => 404));
        }
        return rest_ensure_response(OrionWPAgent_Actions::serialize_post_public($post));
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_update_page($request)
    {
        $id = (int) $request['id'];
        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = array();
        }
        unset($body['_method']);
        $data = OrionWPAgent_Actions::update_page($id, $body);
        if (is_wp_error($data)) {
            return $data;
        }
        return rest_ensure_response($data);
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_get_media($request)
    {
        $per = (int) $request->get_param('per_page');
        if ($per <= 0) {
            $per = 20;
        }
        return rest_ensure_response(OrionWPAgent_Actions::list_media($per));
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_post_media($request)
    {
        $files = $request->get_file_params();
        $upload = null;
        if (!empty($files['file']) && is_array($files['file'])) {
            $upload = $files['file'];
        } elseif (!empty($_FILES['file']) && is_array($_FILES['file'])) {
            // Repli : certains hébergeurs / proxies n’alimentent pas get_file_params() alors que $_FILES est rempli.
            $upload = $_FILES['file'];
        }
        if ($upload !== null) {
            $title = (string) $request->get_param('title');
            $alt = (string) $request->get_param('alt_text');
            $data = OrionWPAgent_Actions::upload_media_from_upload_array($upload, $title, $alt);
            if (is_wp_error($data)) {
                return $data;
            }
            return rest_ensure_response($data);
        }

        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = array();
        }
        $data = OrionWPAgent_Actions::upload_media($body);
        if (is_wp_error($data)) {
            return $data;
        }
        return rest_ensure_response($data);
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_replace_link_post($request)
    {
        return $this->run_replace_link($request, false);
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_replace_link_page($request)
    {
        return $this->run_replace_link($request, true);
    }

    /**
     * @param WP_REST_Request $request
     * @param bool $is_page
     * @return WP_REST_Response|WP_Error
     */
    private function run_replace_link($request, $is_page)
    {
        $id = (int) $request['id'];
        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = array();
        }
        $data = OrionWPAgent_Actions::replace_link_in_content(array(
            'post_id' => $id,
            'is_page' => $is_page,
            'old_href' => isset($body['old_href']) ? (string) $body['old_href'] : '',
            'new_href' => isset($body['new_href']) ? (string) $body['new_href'] : '',
        ));
        if (is_wp_error($data)) {
            return $data;
        }
        return rest_ensure_response($data);
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_divi_bg_post($request)
    {
        return $this->run_divi_background($request, false);
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_divi_bg_page($request)
    {
        return $this->run_divi_background($request, true);
    }

    /**
     * @param WP_REST_Request $request
     * @param bool $is_page
     * @return WP_REST_Response|WP_Error
     */
    private function run_divi_background($request, $is_page)
    {
        $method = $this->get_effective_http_method($request);
        if ($method !== 'PUT') {
            return new WP_Error('orion_method_not_allowed', __('Method not allowed for this resource.', 'orion-wp-agent'), array('status' => 405));
        }
        $id = (int) $request['id'];
        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = array();
        }
        unset($body['_method']);
        $data = OrionWPAgent_Actions::update_divi_background_image(array(
            'post_id' => $id,
            'is_page' => $is_page,
            'section_index' => isset($body['section_index']) ? (int) $body['section_index'] : 0,
            'image_url' => isset($body['image_url']) ? (string) $body['image_url'] : '',
        ));
        if (is_wp_error($data)) {
            return $data;
        }
        return rest_ensure_response($data);
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_divi_image_post($request)
    {
        return $this->run_divi_image($request, false);
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_divi_image_page($request)
    {
        return $this->run_divi_image($request, true);
    }

    /**
     * @param WP_REST_Request $request
     * @param bool $is_page
     * @return WP_REST_Response|WP_Error
     */
    private function run_divi_image($request, $is_page)
    {
        $method = $this->get_effective_http_method($request);
        if ($method !== 'PUT') {
            return new WP_Error('orion_method_not_allowed', __('Method not allowed for this resource.', 'orion-wp-agent'), array('status' => 405));
        }
        $id = (int) $request['id'];
        $body = $request->get_json_params();
        if (!is_array($body)) {
            $body = array();
        }
        unset($body['_method']);
        $data = OrionWPAgent_Actions::update_divi_image_module(array(
            'post_id' => $id,
            'is_page' => $is_page,
            'image_index' => isset($body['image_index']) ? (int) $body['image_index'] : 0,
            'image_url' => isset($body['image_url']) ? (string) $body['image_url'] : '',
            'alt_text' => isset($body['alt_text']) ? (string) $body['alt_text'] : '',
        ));
        if (is_wp_error($data)) {
            return $data;
        }
        return rest_ensure_response($data);
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_delete_media($request)
    {
        $id = (int) $request['id'];
        $data = OrionWPAgent_Actions::delete_media($id);
        if (is_wp_error($data)) {
            return $data;
        }
        return rest_ensure_response($data);
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_site_info($request)
    {
        return rest_ensure_response(OrionWPAgent_Actions::site_info());
    }
}
