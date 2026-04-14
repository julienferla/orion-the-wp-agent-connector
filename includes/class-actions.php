<?php
if (!defined('ABSPATH')) {
    exit;
}

class OrionWPAgent_Actions
{
    private const ORION_BACKUP_PREFIX = '_orion_backup_';

    /** Types MIME acceptés pour upload (URL, base64 ou multipart). */
    private const ALLOWED_UPLOAD_MIMES = array(
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'application/pdf',
    );

    /**
     * Sauvegarde le contenu dans postmeta avant modification ; max. 5 sauvegardes par article.
     *
     * @param int $post_id
     * @param string $content Contenu actuel (post_content brut)
     */
    private static function backup_post_content_before_write($post_id, $content)
    {
        $ts = sprintf('%018.0f', microtime(true) * 1000000);
        $meta_key = self::ORION_BACKUP_PREFIX . $ts;
        update_post_meta($post_id, $meta_key, wp_slash($content));
        self::prune_content_backups($post_id);
    }

    /**
     * @param int $post_id
     */
    private static function prune_content_backups($post_id)
    {
        global $wpdb;
        $like = $wpdb->esc_like(self::ORION_BACKUP_PREFIX) . '%';
        $keys = $wpdb->get_col($wpdb->prepare(
            "SELECT meta_key FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s ORDER BY meta_key DESC",
            $post_id,
            $like
        ));
        if (count($keys) <= 5) {
            return;
        }
        $to_delete = array_slice($keys, 5);
        foreach ($to_delete as $key) {
            delete_post_meta($post_id, $key);
        }
    }

    /**
     * Variantes de la chaîne search pour le matching (trim, entités HTML, slashes).
     *
     * @param string $raw
     * @return array<int, string>
     */
    private static function patch_search_candidates($raw)
    {
        $candidates = array();
        $t = trim((string) $raw);
        if ($t !== '') {
            $candidates[] = $t;
        }
        $dec = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($dec !== '' && !in_array($dec, $candidates, true)) {
            $candidates[] = $dec;
        }
        $sl = stripslashes($t);
        if ($sl !== '' && !in_array($sl, $candidates, true)) {
            $candidates[] = $sl;
        }
        $sldec = stripslashes($dec);
        if ($sldec !== '' && !in_array($sldec, $candidates, true)) {
            $candidates[] = $sldec;
        }
        return $candidates;
    }

    /**
     * Trouve la première variante de search présente dans le contenu.
     *
     * @param string $content
     * @param string $raw_search
     * @return array{0:string,1:int}|null [needle, nombre d’occurrences]
     */
    private static function resolve_patch_needle($content, $raw_search)
    {
        foreach (self::patch_search_candidates($raw_search) as $needle) {
            if ($needle === '') {
                continue;
            }
            if (strpos($content, $needle) !== false) {
                return array($needle, substr_count($content, $needle));
            }
        }
        return null;
    }

    /**
     * Indice pour l’agent : extrait du contenu (texte) autour d’un mot-clé déduit de search.
     *
     * @param string $content post_content
     * @param string $raw_search
     * @return string
     */
    private static function patch_not_found_content_hint($content, $raw_search)
    {
        $plain = wp_strip_all_tags($content);
        $t = trim((string) $raw_search);
        $keyword = '';
        if (preg_match('/\S+/u', $t, $m)) {
            $keyword = $m[0];
        }
        if (mb_strlen($keyword, 'UTF-8') < 3) {
            $keyword = mb_substr($t, 0, 20, 'UTF-8');
        }
        if ($keyword === '') {
            return '';
        }
        $pos = stripos($plain, $keyword);
        if ($pos === false) {
            return sprintf(
                'Mot-clé « %s » introuvable dans le contenu (texte seul).',
                $keyword
            );
        }
        $radius = 140;
        $start = max(0, $pos - $radius);
        $plain_len = mb_strlen($plain, 'UTF-8');
        $kw_len = mb_strlen($keyword, 'UTF-8');
        $len = min($plain_len - $start, $radius * 2 + $kw_len);
        if ($len < 1) {
            return '';
        }
        $snippet = mb_substr($plain, $start, $len, 'UTF-8');
        if ($snippet === '') {
            return '';
        }
        $snippet = preg_replace('/\s+/u', ' ', $snippet);
        if (!is_string($snippet)) {
            $snippet = '';
        }
        return sprintf(
            'Extrait possible (texte seul autour de « %s ») : …%s…',
            $keyword,
            $snippet
        );
    }

    /**
     * @param int $post_id
     * @return array<int, array<string, mixed>>
     */
    private static function collect_backup_entries($post_id)
    {
        global $wpdb;
        $like = $wpdb->esc_like(self::ORION_BACKUP_PREFIX) . '%';
        $keys = $wpdb->get_col($wpdb->prepare(
            "SELECT meta_key FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s ORDER BY meta_key DESC",
            $post_id,
            $like
        ));
        $out = array();
        foreach ($keys as $key) {
            $suffix = substr($key, strlen(self::ORION_BACKUP_PREFIX));
            $out[] = array(
                'backup_key' => $key,
                'timestamp' => $suffix,
            );
        }
        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public static function ping()
    {
        return array(
            'ok' => true,
            'version' => ORION_WPAGENT_VERSION,
            'site_url' => get_site_url(),
            'site_name' => get_bloginfo('name'),
        );
    }

    /**
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>|WP_Error
     */
    public static function get_posts($params)
    {
        $status = isset($params['status']) ? (string) $params['status'] : 'all';
        $per_page = isset($params['per_page']) ? (int) $params['per_page'] : 10;
        $per_page = max(1, min(100, $per_page));

        $post_status = array('publish', 'draft', 'pending', 'private');
        if ($status === 'publish' || $status === 'draft') {
            $post_status = $status;
        } elseif ($status === 'all') {
            $post_status = 'any';
        }

        $q = new WP_Query(array(
            'post_type' => 'post',
            'post_status' => $post_status,
            'posts_per_page' => $per_page,
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,
        ));

        $out = array();
        foreach ($q->posts as $p) {
            if (!$p instanceof WP_Post) {
                continue;
            }
            $out[] = self::serialize_post($p);
        }
        return $out;
    }

    /**
     * Recherche dans titre + contenu (WP_Query 's').
     *
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>|WP_Error
     */
    public static function search_posts($params)
    {
        $search = isset($params['search']) ? trim((string) $params['search']) : '';
        if ($search === '') {
            return new WP_Error('orion_invalid', 'search est requis', array('status' => 400));
        }

        $q = new WP_Query(array(
            'post_type' => 'post',
            'post_status' => array('publish', 'draft', 'pending', 'private'),
            's' => $search,
            'posts_per_page' => 20,
            'orderby' => 'relevance',
            'no_found_rows' => true,
        ));

        $out = array();
        foreach ($q->posts as $p) {
            if (!$p instanceof WP_Post) {
                continue;
            }
            $excerpt = $p->post_excerpt !== ''
                ? $p->post_excerpt
                : wp_trim_words(wp_strip_all_tags($p->post_content), 55, '…');
            $out[] = array(
                'id' => (int) $p->ID,
                'title' => get_the_title($p),
                'url' => (string) get_permalink($p),
                'excerpt' => $excerpt,
            );
        }
        return $out;
    }

    /**
     * Remplacement en masse dans post_content via SQL (LIKE + str_replace par ligne).
     *
     * @param array<string, mixed> $params search, replace, post_type ('post'|'page'|'all'), max_posts (optionnel, défaut 200, max 500)
     * @return array<string, mixed>|WP_Error
     */
    public static function bulk_patch_content($params)
    {
        global $wpdb;

        $search = isset($params['search']) ? (string) $params['search'] : '';
        if (trim($search) === '') {
            return new WP_Error('orion_invalid', 'search ne peut pas être vide', array('status' => 400));
        }
        $replace = isset($params['replace']) ? (string) $params['replace'] : '';
        $post_type = isset($params['post_type']) ? (string) $params['post_type'] : 'post';
        if (!in_array($post_type, array('post', 'page', 'all'), true)) {
            return new WP_Error('orion_invalid', 'post_type doit être post, page ou all', array('status' => 400));
        }
        $max_posts = isset($params['max_posts']) ? (int) $params['max_posts'] : 200;
        $max_posts = max(1, min(500, $max_posts));

        if ($post_type === 'post') {
            $type_sql = "post_type = 'post'";
        } elseif ($post_type === 'page') {
            $type_sql = "post_type = 'page'";
        } else {
            $type_sql = "post_type IN ('post','page')";
        }

        $like = '%' . $wpdb->esc_like($search) . '%';
        $sql = "SELECT ID, post_content FROM {$wpdb->posts}
            WHERE post_status = 'publish'
            AND ({$type_sql})
            AND post_content LIKE %s
            LIMIT %d";
        $prepared = $wpdb->prepare($sql, $like, $max_posts);
        $rows = $wpdb->get_results($prepared);
        if (!is_array($rows)) {
            return new WP_Error('orion_db', 'Requête bulk impossible', array('status' => 500));
        }

        $posts_modified = array();
        foreach ($rows as $row) {
            if (!is_object($row) || !isset($row->ID, $row->post_content)) {
                continue;
            }
            $post_id = (int) $row->ID;
            $content = (string) $row->post_content;
            $new_content = str_replace($search, $replace, $content);
            if ($new_content === $content) {
                continue;
            }
            self::backup_post_content_before_write($post_id, $content);
            $updated = $wpdb->update(
                $wpdb->posts,
                array('post_content' => wp_slash($new_content)),
                array('ID' => $post_id),
                array('%s'),
                array('%d')
            );
            if ($updated === false) {
                return new WP_Error('orion_db', 'Échec mise à jour post ID ' . $post_id, array('status' => 500));
            }
            clean_post_cache($post_id);
            $posts_modified[] = $post_id;
        }

        return array(
            'success' => true,
            'modified_count' => count($posts_modified),
            'posts_modified' => $posts_modified,
        );
    }

    /**
     * Résout une URL vers un contenu publié (page, article, CPT public) via url_to_postid.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>|WP_Error
     */
    public static function get_post_by_url($params)
    {
        $url = isset($params['url']) ? trim((string) $params['url']) : '';
        if ($url === '') {
            return new WP_Error('orion_invalid', 'url est requis', array('status' => 400));
        }

        $post_id = (int) url_to_postid($url);
        if ($post_id <= 0) {
            return new WP_Error('orion_not_found', 'Page introuvable pour cette URL', array('status' => 404));
        }

        $post = get_post($post_id);
        if (!$post instanceof WP_Post) {
            return new WP_Error('orion_not_found', 'Page introuvable pour cette URL', array('status' => 404));
        }

        $ptype = $post->post_type;
        $pto = get_post_type_object($ptype);
        if (!$pto instanceof WP_Post_Type || !$pto->public) {
            return new WP_Error('orion_not_found', 'Page introuvable pour cette URL', array('status' => 404));
        }

        return array(
            'post_id' => $post_id,
            'title' => (string) $post->post_title,
            'url' => (string) get_permalink($post_id),
            'type' => (string) $ptype,
            'id' => $post_id,
        );
    }

    /**
     * Contenu HTML complet d’un article.
     *
     * @param int $id
     * @return array<string, mixed>|WP_Error
     */
    public static function get_post_content($id)
    {
        $post = get_post($id);
        if (!$post instanceof WP_Post || $post->post_type !== 'post') {
            return new WP_Error('orion_not_found', 'Article introuvable', array('status' => 404));
        }

        return array(
            'id' => (int) $post->ID,
            'title' => get_the_title($post),
            'content' => $post->post_content,
            'url' => (string) get_permalink($post),
        );
    }

    /**
     * Contenu HTML brut d’une page (route GET /pages/{id}/content).
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function get_page_content($request)
    {
        $id = (int) $request->get_param('id');
        $post = get_post($id);
        if (!$post instanceof WP_Post || $post->post_type !== 'page') {
            return new WP_REST_Response(array('error' => 'introuvable'), 404);
        }

        return new WP_REST_Response(array(
            'id' => $id,
            'title' => $post->post_title,
            'content' => $post->post_content,
        ), 200);
    }

    /**
     * Remplacement chirurgical dans post_content (type post uniquement).
     *
     * @param array<string, mixed> $params post_id, search, replace
     * @return array<string, mixed>|WP_Error
     */
    public static function patch_post_content($params)
    {
        $post_id = isset($params['post_id']) ? (int) $params['post_id'] : 0;
        $search = isset($params['search']) ? (string) $params['search'] : '';
        $replace = isset($params['replace']) ? (string) $params['replace'] : '';

        if ($post_id <= 0) {
            return new WP_Error('orion_invalid', 'post_id invalide', array('status' => 400));
        }
        if ($search === '') {
            return new WP_Error('orion_invalid', 'search ne peut pas être vide', array('status' => 400));
        }

        $post = get_post($post_id);
        if (!$post instanceof WP_Post || $post->post_type !== 'post') {
            return new WP_Error('orion_not_found', 'Article introuvable', array('status' => 404));
        }

        $content = $post->post_content;
        $resolved = self::resolve_patch_needle($content, $search);
        if ($resolved === null) {
            $hint = self::patch_not_found_content_hint($content, $search);
            $msg = 'La chaîne search est introuvable dans le contenu de l’article.';
            if ($hint !== '') {
                $msg .= ' ' . $hint;
            }
            return new WP_Error(
                'orion_not_found',
                $msg,
                array('status' => 400, 'hint' => $hint)
            );
        }

        list($needle, $replacements_count) = $resolved;

        self::backup_post_content_before_write($post_id, $content);
        $new_content = str_replace($needle, $replace, $content);

        $res = wp_update_post(array(
            'ID' => $post_id,
            'post_content' => wp_slash($new_content),
        ), true);

        if (is_wp_error($res)) {
            return $res;
        }

        $url = get_permalink($post_id);
        return array(
            'success' => true,
            'replacements_count' => $replacements_count,
            'post_url' => $url ? (string) $url : '',
        );
    }

    /**
     * Remplacement chirurgical dans post_content (type page uniquement).
     *
     * @param array<string, mixed> $params page_id, search, replace
     * @return array<string, mixed>|WP_Error
     */
    public static function patch_page_content($params)
    {
        $page_id = isset($params['page_id']) ? (int) $params['page_id'] : 0;
        $search = isset($params['search']) ? (string) $params['search'] : '';
        $replace = isset($params['replace']) ? (string) $params['replace'] : '';

        if ($page_id <= 0) {
            return new WP_Error('orion_invalid', 'page_id invalide', array('status' => 400));
        }
        if ($search === '') {
            return new WP_Error('orion_invalid', 'search ne peut pas être vide', array('status' => 400));
        }

        $post = get_post($page_id);
        if (!$post instanceof WP_Post || $post->post_type !== 'page') {
            return new WP_Error('orion_not_found', 'Page introuvable', array('status' => 404));
        }

        $content = $post->post_content;
        $resolved = self::resolve_patch_needle($content, $search);
        if ($resolved === null) {
            $hint = self::patch_not_found_content_hint($content, $search);
            $msg = 'La chaîne search est introuvable dans le contenu de la page.';
            if ($hint !== '') {
                $msg .= ' ' . $hint;
            }
            return new WP_Error(
                'orion_not_found',
                $msg,
                array('status' => 400, 'hint' => $hint)
            );
        }

        list($needle, $replacements_count) = $resolved;

        self::backup_post_content_before_write($page_id, $content);
        $new_content = str_replace($needle, $replace, $content);

        $res = wp_update_post(array(
            'ID' => $page_id,
            'post_content' => wp_slash($new_content),
        ), true);

        if (is_wp_error($res)) {
            return $res;
        }

        $url = get_permalink($page_id);
        return array(
            'success' => true,
            'replacements_count' => $replacements_count,
            'post_url' => $url ? (string) $url : '',
        );
    }

    /**
     * Liste ou restaure une sauvegarde de contenu (articles ou pages).
     *
     * @param array<string, mixed> $params post_id, backup_key (optionnel)
     * @return array<string, mixed>|WP_Error
     */
    public static function restore_post_backup($params)
    {
        $post_id = isset($params['post_id']) ? (int) $params['post_id'] : 0;
        if ($post_id <= 0) {
            return new WP_Error('orion_invalid', 'post_id invalide', array('status' => 400));
        }

        $post = get_post($post_id);
        if (!$post instanceof WP_Post || !in_array($post->post_type, array('post', 'page'), true)) {
            return new WP_Error('orion_not_found', 'Article ou page introuvable', array('status' => 404));
        }

        $backup_key = isset($params['backup_key']) ? trim((string) $params['backup_key']) : '';
        if ($backup_key === '') {
            return array(
                'post_id' => $post_id,
                'post_type' => $post->post_type,
                'backups' => self::collect_backup_entries($post_id),
            );
        }

        if (strpos($backup_key, self::ORION_BACKUP_PREFIX) !== 0) {
            return new WP_Error('orion_invalid', 'backup_key invalide', array('status' => 400));
        }

        $blob = get_post_meta($post_id, $backup_key, true);
        if (!is_string($blob) || $blob === '') {
            return new WP_Error('orion_not_found', 'Sauvegarde introuvable', array('status' => 404));
        }

        self::backup_post_content_before_write($post_id, $post->post_content);
        $res = wp_update_post(array(
            'ID' => $post_id,
            'post_content' => wp_slash(wp_unslash($blob)),
        ), true);

        if (is_wp_error($res)) {
            return $res;
        }

        $url = get_permalink($post_id);
        return array(
            'restored' => true,
            'backup_key' => $backup_key,
            'post_url' => $url ? (string) $url : '',
        );
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>|WP_Error
     */
    public static function create_post($body)
    {
        $title = isset($body['title']) ? (string) $body['title'] : '';
        $content = isset($body['content']) ? (string) $body['content'] : '';
        if ($title === '' || $content === '') {
            return new WP_Error('orion_invalid', 'title et content sont requis', array('status' => 400));
        }
        $status = isset($body['status']) && in_array($body['status'], array('publish', 'draft'), true)
            ? (string) $body['status']
            : 'draft';
        $excerpt = isset($body['excerpt']) ? (string) $body['excerpt'] : '';

        $id = wp_insert_post(array(
            'post_title' => $title,
            'post_content' => $content,
            'post_excerpt' => $excerpt,
            'post_status' => $status,
            'post_type' => 'post',
        ), true);

        if (is_wp_error($id)) {
            return $id;
        }
        $post = get_post($id);
        return $post instanceof WP_Post ? self::serialize_post($post) : array('id' => (int) $id);
    }

    /**
     * @param int $id
     * @param array<string, mixed> $body
     * @return array<string, mixed>|WP_Error
     */
    public static function update_post($id, $body)
    {
        $post = get_post($id);
        if (!$post instanceof WP_Post || $post->post_type !== 'post') {
            return new WP_Error('orion_not_found', 'Article introuvable', array('status' => 404));
        }
        if (array_key_exists('content', $body)) {
            return new WP_Error(
                'orion_forbidden',
                'La modification du contenu HTML via update_post est interdite : utilisez patch_post_content.',
                array('status' => 400)
            );
        }
        $data = array('ID' => $id);
        if (array_key_exists('title', $body)) {
            $data['post_title'] = (string) $body['title'];
        }
        if (array_key_exists('excerpt', $body)) {
            $data['post_excerpt'] = (string) $body['excerpt'];
        }
        if (isset($body['status']) && is_string($body['status'])) {
            $data['post_status'] = $body['status'];
        }
        if (isset($body['featured_media']) && is_numeric($body['featured_media'])) {
            set_post_thumbnail($id, (int) $body['featured_media']);
        }
        $res = wp_update_post($data, true);
        if (is_wp_error($res)) {
            return $res;
        }
        $updated = get_post($id);
        return $updated instanceof WP_Post ? self::serialize_post($updated) : array('id' => $id);
    }

    /**
     * @param int $id
     * @return array<string, mixed>|WP_Error
     */
    public static function delete_post($id)
    {
        $post = get_post($id);
        if (!$post instanceof WP_Post || $post->post_type !== 'post') {
            return new WP_Error('orion_not_found', 'Article introuvable', array('status' => 404));
        }
        $ok = wp_delete_post($id, true);
        if (!$ok) {
            return new WP_Error('orion_delete', 'Suppression impossible', array('status' => 500));
        }
        return array('deleted' => true, 'id' => (int) $id);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>|WP_Error
     */
    public static function get_pages($params)
    {
        $per_page = isset($params['per_page']) ? (int) $params['per_page'] : 10;
        $per_page = max(1, min(100, $per_page));

        $q = new WP_Query(array(
            'post_type' => 'page',
            'post_status' => 'any',
            'posts_per_page' => $per_page,
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,
        ));

        $out = array();
        foreach ($q->posts as $p) {
            if (!$p instanceof WP_Post) {
                continue;
            }
            $out[] = self::serialize_post($p);
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>|WP_Error
     */
    public static function create_page($body)
    {
        $title = isset($body['title']) ? (string) $body['title'] : '';
        $content = isset($body['content']) ? (string) $body['content'] : '';
        $status = isset($body['status']) && in_array($body['status'], array('publish', 'draft'), true)
            ? (string) $body['status']
            : 'draft';

        $id = wp_insert_post(array(
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => $status,
            'post_type' => 'page',
        ), true);

        if (is_wp_error($id)) {
            return $id;
        }
        $post = get_post($id);
        return $post instanceof WP_Post ? self::serialize_post($post) : array('id' => (int) $id);
    }

    /**
     * @param int $id
     * @param array<string, mixed> $body
     * @return array<string, mixed>|WP_Error
     */
    public static function update_page($id, $body)
    {
        $post = get_post($id);
        if (!$post instanceof WP_Post || $post->post_type !== 'page') {
            return new WP_Error('orion_not_found', 'Page introuvable', array('status' => 404));
        }
        if (array_key_exists('content', $body)) {
            return new WP_Error(
                'orion_forbidden',
                'La modification du contenu HTML via update_page est interdite : utilisez patch_page_content.',
                array('status' => 400)
            );
        }
        $data = array('ID' => $id);
        if (array_key_exists('title', $body)) {
            $data['post_title'] = (string) $body['title'];
        }
        if (isset($body['status']) && is_string($body['status'])) {
            $data['post_status'] = $body['status'];
        }
        $res = wp_update_post($data, true);
        if (is_wp_error($res)) {
            return $res;
        }
        $updated = get_post($id);
        return $updated instanceof WP_Post ? self::serialize_post($updated) : array('id' => $id);
    }

    /**
     * Indication des limites PHP pour les messages d’erreur d’upload.
     */
    public static function php_upload_limits_hint()
    {
        return sprintf(
            'upload_max_filesize=%s post_max_size=%s',
            ini_get('upload_max_filesize'),
            ini_get('post_max_size')
        );
    }

    /**
     * @return void
     */
    private static function ensure_media_includes()
    {
        if (!function_exists('media_handle_sideload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }
    }

    /**
     * @param int $code UPLOAD_ERR_*
     * @return WP_Error
     */
    public static function upload_err_to_wp_error($code)
    {
        $hint = self::php_upload_limits_hint();
        if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
            return new WP_Error(
                'orion_upload_size',
                'Fichier trop volumineux pour PHP (' . $hint . ')',
                array('status' => 413)
            );
        }
        return new WP_Error(
            'orion_upload',
            'Échec de l’upload (code ' . (int) $code . '). ' . $hint,
            array('status' => 400)
        );
    }

    /**
     * @param string $tmp_path
     * @param string $filename
     * @return string|false MIME détecté ou false
     */
    private static function validate_upload_mime($tmp_path, $filename)
    {
        self::ensure_media_includes();
        $checked = wp_check_filetype_and_ext($tmp_path, $filename);
        $type = isset($checked['type']) ? (string) $checked['type'] : '';
        if ($type === '' || !in_array($type, self::ALLOWED_UPLOAD_MIMES, true)) {
            return false;
        }
        return $type;
    }

    /**
     * @param array<string, mixed> $file_array Tableau style $_FILES (name, tmp_name, size, error)
     * @param string $title
     * @param string $alt
     * @param bool $unlink_tmp Après sideload, supprimer tmp_name si différent du fichier final
     * @return array<string, mixed>|WP_Error
     */
    private static function finalize_upload_from_tmp($file_array, $title, $alt, $unlink_tmp)
    {
        self::ensure_media_includes();
        $tmp = isset($file_array['tmp_name']) ? (string) $file_array['tmp_name'] : '';
        $name = isset($file_array['name']) ? (string) $file_array['name'] : '';
        if ($tmp === '' || !is_readable($tmp)) {
            return new WP_Error('orion_invalid', 'Fichier temporaire illisible', array('status' => 400));
        }
        $mime_ok = self::validate_upload_mime($tmp, $name);
        if ($mime_ok === false) {
            if ($unlink_tmp) {
                @unlink($tmp);
            }
            return new WP_Error(
                'orion_invalid',
                'Type MIME non autorisé. Autorisés : ' . implode(', ', self::ALLOWED_UPLOAD_MIMES),
                array('status' => 400)
            );
        }

        $overrides = array('test_form' => false);
        $att_id = media_handle_sideload($file_array, 0, $title, $overrides);
        if ($unlink_tmp && is_string($tmp) && is_file($tmp)) {
            @unlink($tmp);
        }
        if (is_wp_error($att_id)) {
            return $att_id;
        }
        $att_id = (int) $att_id;
        if ($alt !== '') {
            update_post_meta($att_id, '_wp_attachment_image_alt', $alt);
        }
        return self::serialize_attachment($att_id);
    }

    /**
     * Upload depuis multipart (champ file).
     *
     * @param array<string, mixed> $file Champ $_FILES['file']
     * @param string $title
     * @param string $alt
     * @return array<string, mixed>|WP_Error
     */
    public static function upload_media_from_upload_array($file, $title, $alt)
    {
        if (!is_array($file)) {
            return new WP_Error('orion_invalid', 'Fichier invalide', array('status' => 400));
        }
        $err = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
        if ($err !== UPLOAD_ERR_OK) {
            return self::upload_err_to_wp_error($err);
        }
        $tmp = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return new WP_Error('orion_invalid', 'Upload non reconnu', array('status' => 400));
        }
        return self::finalize_upload_from_tmp($file, $title, $alt, false);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>|WP_Error
     */
    public static function upload_media($body)
    {
        $title = isset($body['title']) ? (string) $body['title'] : '';
        $alt = isset($body['alt_text']) ? (string) $body['alt_text'] : '';

        $file_data = isset($body['file_data']) ? (string) $body['file_data'] : '';
        $file_name = isset($body['file_name']) ? (string) $body['file_name'] : '';
        if ($file_data !== '') {
            if ($file_name === '') {
                return new WP_Error('orion_invalid', 'file_name est requis avec file_data', array('status' => 400));
            }
            $decoded = base64_decode($file_data, true);
            if ($decoded === false || $decoded === '') {
                return new WP_Error('orion_invalid', 'file_data base64 invalide', array('status' => 400));
            }
            $tmp = wp_tempnam($file_name);
            if (!$tmp) {
                return new WP_Error('orion_temp', 'Impossible de créer un fichier temporaire', array('status' => 500));
            }
            if (file_put_contents($tmp, $decoded) === false) {
                @unlink($tmp);
                return new WP_Error('orion_temp', 'Écriture fichier temporaire impossible', array('status' => 500));
            }
            $file_array = array(
                'name' => $file_name,
                'tmp_name' => $tmp,
                'size' => strlen($decoded),
                'error' => 0,
                'type' => '',
            );
            return self::finalize_upload_from_tmp($file_array, $title, $alt, true);
        }

        $file_url = isset($body['file_url']) ? (string) $body['file_url'] : '';
        if ($file_url === '') {
            return new WP_Error(
                'orion_invalid',
                'Fournir file_url, ou file_data + file_name, ou un multipart avec le champ file. ' . self::php_upload_limits_hint(),
                array('status' => 400)
            );
        }

        self::ensure_media_includes();
        $att_id = media_sideload_image($file_url, 0, $title, 'id');
        if (is_wp_error($att_id)) {
            return $att_id;
        }
        $att_id = (int) $att_id;
        if ($alt !== '') {
            update_post_meta($att_id, '_wp_attachment_image_alt', $alt);
        }
        return self::serialize_attachment($att_id);
    }

    /**
     * Remplace href="old" par href="new" (correspondance exacte de la valeur href, guillemets doubles).
     *
     * @param array<string, mixed> $params post_id, is_page, old_href, new_href
     * @return array<string, mixed>|WP_Error
     */
    public static function replace_link_in_content($params)
    {
        $post_id = isset($params['post_id']) ? (int) $params['post_id'] : 0;
        $is_page = !empty($params['is_page']);
        $old_href = isset($params['old_href']) ? (string) $params['old_href'] : '';
        $new_href = isset($params['new_href']) ? (string) $params['new_href'] : '';

        if ($post_id <= 0) {
            return new WP_Error('orion_invalid', 'post_id invalide', array('status' => 400));
        }
        if ($old_href === '' || $new_href === '') {
            return new WP_Error('orion_invalid', 'old_href et new_href sont requis', array('status' => 400));
        }
        if (strpos($old_href, '"') !== false || strpos($new_href, '"') !== false) {
            return new WP_Error('orion_invalid', 'Les href ne doivent pas contenir de guillemet double', array('status' => 400));
        }

        $post = get_post($post_id);
        if (!$post instanceof WP_Post) {
            return new WP_Error('orion_not_found', 'Contenu introuvable', array('status' => 404));
        }
        $expected = $is_page ? 'page' : 'post';
        if ($post->post_type !== $expected) {
            return new WP_Error('orion_invalid', 'Type de contenu incompatible avec is_page', array('status' => 400));
        }

        $content = $post->post_content;
        $needle = 'href="' . $old_href . '"';
        $replacement = 'href="' . $new_href . '"';
        if (strpos($content, $needle) === false) {
            return new WP_Error(
                'orion_not_found',
                'Aucune occurrence exacte de ' . $needle . ' dans le contenu',
                array('status' => 400)
            );
        }

        self::backup_post_content_before_write($post_id, $content);
        $count = substr_count($content, $needle);
        $new_content = str_replace($needle, $replacement, $content);
        $res = wp_update_post(array(
            'ID' => $post_id,
            'post_content' => wp_slash($new_content),
        ), true);
        if (is_wp_error($res)) {
            return $res;
        }
        $url = get_permalink($post_id);
        return array(
            'success' => true,
            'replacements_count' => $count,
            'post_url' => $url ? (string) $url : '',
        );
    }

    /**
     * @param string $tag Shortcode d’ouverture complet [...]
     * @param string $attr_name
     * @param string $raw_value Valeur brute ; échappée pour l’attribut
     * @param bool $as_url Si true, applique esc_url_raw + esc_attr (src, url, background_image)
     * @return string
     */
    private static function divi_set_attribute_in_tag($tag, $attr_name, $raw_value, $as_url = true)
    {
        $escaped = $as_url ? esc_attr(esc_url_raw($raw_value)) : esc_attr($raw_value);
        $pattern = '/\b' . preg_quote($attr_name, '/') . '="[^"]*"/';
        if (preg_match($pattern, $tag)) {
            return (string) preg_replace($pattern, $attr_name . '="' . $escaped . '"', $tag, 1);
        }
        if (substr($tag, -1) === ']') {
            return substr($tag, 0, -1) . ' ' . $attr_name . '="' . $escaped . '"]';
        }
        return $tag;
    }

    /**
     * @return array<int, array{0:string,1:int}>
     */
    private static function divi_find_opening_shortcodes($content, $shortcode_base)
    {
        $pattern = '/\[' . preg_quote($shortcode_base, '/') . '\b[^\]]*\]/';
        preg_match_all($pattern, $content, $m, PREG_OFFSET_CAPTURE);
        $pairs = array();
        if (!empty($m[0]) && is_array($m[0])) {
            foreach ($m[0] as $row) {
                if (is_array($row) && isset($row[0])) {
                    $pairs[] = array((string) $row[0], isset($row[1]) ? (int) $row[1] : 0);
                }
            }
        }
        return $pairs;
    }

    /**
     * @param array<string, mixed> $params post_id, is_page, section_index (0-based), image_url
     * @return array<string, mixed>|WP_Error
     */
    public static function update_divi_background_image($params)
    {
        $post_id = isset($params['post_id']) ? (int) $params['post_id'] : 0;
        $is_page = !empty($params['is_page']);
        $section_index = isset($params['section_index']) ? (int) $params['section_index'] : 0;
        $image_url = isset($params['image_url']) ? (string) $params['image_url'] : '';

        if ($post_id <= 0 || $image_url === '') {
            return new WP_Error('orion_invalid', 'post_id et image_url sont requis', array('status' => 400));
        }

        $post = get_post($post_id);
        if (!$post instanceof WP_Post) {
            return new WP_Error('orion_not_found', 'Contenu introuvable', array('status' => 404));
        }
        $expected = $is_page ? 'page' : 'post';
        if ($post->post_type !== $expected) {
            return new WP_Error('orion_invalid', 'Type de contenu incompatible avec is_page', array('status' => 400));
        }

        $content = $post->post_content;
        $sections = self::divi_find_opening_shortcodes($content, 'et_pb_section');
        if ($section_index < 0 || $section_index >= count($sections)) {
            return new WP_Error(
                'orion_not_found',
                'section_index hors plage (0-based, trouvés : ' . count($sections) . ')',
                array('status' => 400)
            );
        }
        $old_tag = $sections[$section_index][0];
        $new_tag = self::divi_set_attribute_in_tag($old_tag, 'background_image', $image_url);
        if ($old_tag === $new_tag) {
            return new WP_Error('orion_invalid', 'Impossible de modifier le shortcode section', array('status' => 400));
        }

        self::backup_post_content_before_write($post_id, $content);
        $pos = strpos($content, $old_tag);
        if ($pos === false) {
            return new WP_Error('orion_not_found', 'Shortcode section introuvable', array('status' => 400));
        }
        $new_content = substr($content, 0, $pos) . $new_tag . substr($content, $pos + strlen($old_tag));
        $res = wp_update_post(array(
            'ID' => $post_id,
            'post_content' => wp_slash($new_content),
        ), true);
        if (is_wp_error($res)) {
            return $res;
        }
        $url = get_permalink($post_id);
        return array(
            'success' => true,
            'section_index' => $section_index,
            'post_url' => $url ? (string) $url : '',
        );
    }

    /**
     * @param array<string, mixed> $params post_id, is_page, image_index (0-based), image_url, alt_text (optionnel)
     * @return array<string, mixed>|WP_Error
     */
    public static function update_divi_image_module($params)
    {
        $post_id = isset($params['post_id']) ? (int) $params['post_id'] : 0;
        $is_page = !empty($params['is_page']);
        $image_index = isset($params['image_index']) ? (int) $params['image_index'] : 0;
        $image_url = isset($params['image_url']) ? (string) $params['image_url'] : '';
        $alt_text = isset($params['alt_text']) ? (string) $params['alt_text'] : '';

        if ($post_id <= 0 || $image_url === '') {
            return new WP_Error('orion_invalid', 'post_id et image_url sont requis', array('status' => 400));
        }

        $post = get_post($post_id);
        if (!$post instanceof WP_Post) {
            return new WP_Error('orion_not_found', 'Contenu introuvable', array('status' => 404));
        }
        $expected = $is_page ? 'page' : 'post';
        if ($post->post_type !== $expected) {
            return new WP_Error('orion_invalid', 'Type de contenu incompatible avec is_page', array('status' => 400));
        }

        $content = $post->post_content;
        $images = self::divi_find_opening_shortcodes($content, 'et_pb_image');
        if ($image_index < 0 || $image_index >= count($images)) {
            return new WP_Error(
                'orion_not_found',
                'image_index hors plage (0-based, trouvés : ' . count($images) . ')',
                array('status' => 400)
            );
        }
        $old_tag = $images[$image_index][0];
        $new_tag = self::divi_set_attribute_in_tag($old_tag, 'src', $image_url, true);
        if (preg_match('/\burl="/', $old_tag)) {
            $new_tag = self::divi_set_attribute_in_tag($new_tag, 'url', $image_url, true);
        }
        if ($alt_text !== '') {
            $new_tag = self::divi_set_attribute_in_tag($new_tag, 'alt', $alt_text, false);
        }
        if ($old_tag === $new_tag) {
            return new WP_Error('orion_invalid', 'Impossible de modifier le shortcode image', array('status' => 400));
        }

        self::backup_post_content_before_write($post_id, $content);
        $pos = strpos($content, $old_tag);
        if ($pos === false) {
            return new WP_Error('orion_not_found', 'Shortcode image introuvable', array('status' => 400));
        }
        $new_content = substr($content, 0, $pos) . $new_tag . substr($content, $pos + strlen($old_tag));
        $res = wp_update_post(array(
            'ID' => $post_id,
            'post_content' => wp_slash($new_content),
        ), true);
        if (is_wp_error($res)) {
            return $res;
        }
        $url = get_permalink($post_id);
        return array(
            'success' => true,
            'image_index' => $image_index,
            'post_url' => $url ? (string) $url : '',
        );
    }

    /**
     * @param int $per_page
     * @return array<int, array<string, mixed>>
     */
    public static function list_media($per_page)
    {
        $per_page = max(1, min(100, $per_page));
        $q = new WP_Query(array(
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => $per_page,
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,
        ));
        $out = array();
        foreach ($q->posts as $p) {
            if (!$p instanceof WP_Post) {
                continue;
            }
            $out[] = self::serialize_attachment((int) $p->ID);
        }
        return $out;
    }

    /**
     * @param int $id
     * @return array<string, mixed>|WP_Error
     */
    public static function delete_media($id)
    {
        $post = get_post($id);
        if (!$post instanceof WP_Post || $post->post_type !== 'attachment') {
            return new WP_Error('orion_not_found', 'Média introuvable', array('status' => 404));
        }
        $ok = wp_delete_attachment($id, true);
        if (!$ok) {
            return new WP_Error('orion_delete', 'Suppression impossible', array('status' => 500));
        }
        return array('deleted' => true, 'id' => (int) $id);
    }

    /**
     * @return array<string, mixed>
     */
    public static function site_info()
    {
        $theme = wp_get_theme();
        $plugins = get_option('active_plugins', array());
        if (!is_array($plugins)) {
            $plugins = array();
        }
        return array(
            'name' => get_bloginfo('name'),
            'description' => get_bloginfo('description'),
            'url' => get_site_url(),
            'wp_version' => get_bloginfo('version'),
            'language' => get_locale(),
            'theme' => array(
                'name' => $theme->get('Name'),
                'version' => $theme->get('Version'),
                'stylesheet' => $theme->get_stylesheet(),
            ),
            'active_plugins' => array_values($plugins),
        );
    }

    /**
     * @param WP_Post $post
     * @return array<string, mixed>
     */
    private static function serialize_post(WP_Post $post)
    {
        $thumb = get_post_thumbnail_id($post->ID);
        return array(
            'id' => (int) $post->ID,
            'title' => get_the_title($post),
            'status' => $post->post_status,
            'excerpt' => $post->post_excerpt,
            'content' => $post->post_content,
            'date' => $post->post_date_gmt,
            'modified' => $post->post_modified_gmt,
            'featured_media' => $thumb ? (int) $thumb : null,
            'link' => get_permalink($post),
        );
    }

    /**
     * Exposé pour les handlers REST (GET post/page unique).
     *
     * @param WP_Post $post
     * @return array<string, mixed>
     */
    public static function serialize_post_public(WP_Post $post)
    {
        return self::serialize_post($post);
    }

    /**
     * @param int $id
     * @return array<string, mixed>
     */
    private static function serialize_attachment($id)
    {
        $src = wp_get_attachment_url($id);
        $url = $src ? (string) $src : '';
        $mime = get_post_mime_type($id);
        $mime_str = is_string($mime) ? $mime : '';
        return array(
            'id' => (int) $id,
            'url' => $url,
            'type' => $mime_str,
            'title' => get_the_title($id),
            'mime_type' => $mime_str,
            'source_url' => $url,
            'alt' => (string) get_post_meta($id, '_wp_attachment_image_alt', true),
        );
    }
}
