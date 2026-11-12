<?php
/**
 * Mise à jour automatique du plugin via les releases GitHub.
 *
 * Le plugin se met à jour comme un plugin du repository officiel WordPress :
 * dès qu'une nouvelle release est publiée sur GitHub avec un tag de version
 * supérieur (ex: v1.0.1), une notification de mise à jour apparaît dans
 * Extensions → Mises à jour disponibles.
 */

if (!defined('ABSPATH')) {
    exit;
}

class CRM_CF7_Updater {

    private static ?CRM_CF7_Updater $instance = null;

    private string $slug;
    private string $plugin_file;
    private string $github_username;
    private string $github_repo;
    private string $github_api_url;
    private string $access_token;
    private array $plugin_data;
    private ?object $github_response = null;
    private string $last_error = '';

    public static function get_instance(): CRM_CF7_Updater {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->slug            = CRM_CF7_CONNECTOR_SLUG;
        $this->plugin_file     = CRM_CF7_CONNECTOR_BASENAME;
        $this->github_username = 'lebeaudigital';
        $this->github_repo     = 'crm-cf7-connector';
        $this->github_api_url  = 'https://api.github.com/repos/' . $this->github_username . '/' . $this->github_repo;
        $this->access_token    = ''; // Vide pour repo public

        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        // Sans traduction : ce constructeur tourne pendant le chargement du plugin,
        // avant que le text domain soit prêt.
        $this->plugin_data = get_plugin_data(CRM_CF7_CONNECTOR_FILE, false, false);

        $this->init_hooks();
    }

    private function init_hooks(): void {
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_update']);
        add_filter('plugins_api', [$this, 'plugin_info'], 20, 3);
        // Le zip GitHub s'extrait dans un dossier du type
        // lebeaudigital-crm-cf7-connector-<hash>. On le renomme avant la copie
        // pour que WordPress remplace le dossier déjà installé.
        add_filter('upgrader_source_selection', [$this, 'source_selection'], 10, 4);
        add_filter('upgrader_post_install', [$this, 'post_install'], 10, 3);
        add_filter('plugin_row_meta', [$this, 'plugin_row_meta'], 10, 2);
        add_filter('plugin_action_links_' . CRM_CF7_CONNECTOR_BASENAME, [$this, 'plugin_action_links']);
        add_action('admin_init', [$this, 'handle_force_check']);
    }

    /**
     * Ajoute un lien « Vérifier la MàJ » dans la ligne du plugin.
     *
     * @param string[] $links
     * @return string[]
     */
    public function plugin_action_links($links) {
        if (!is_array($links)) {
            return $links;
        }

        $url = wp_nonce_url(
            add_query_arg('crm_cf7_force_update', '1', admin_url('plugins.php')),
            'crm_cf7_force_update'
        );
        $links[] = '<a href="' . esc_url($url) . '">'
                 . esc_html__('Vérifier la MàJ', 'crm-cf7-connector') . '</a>';
        return $links;
    }

    private function get_github_release(): ?object {
        if ($this->github_response !== null) {
            return $this->github_response;
        }

        $cached = get_transient('crm_cf7_connector_github_release');
        if (is_object($cached)) {
            $this->github_response = $cached;
            return $this->github_response;
        }

        $response = $this->github_request($this->github_api_url . '/releases/latest');
        if (is_wp_error($response)) {
            $this->last_error = $response->get_error_message();
            error_log('[CRM CF7 Connector Updater] API Error: ' . $this->last_error);
            return null;
        }
        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            $this->last_error = 'HTTP ' . $code;
            return null;
        }
        $data = json_decode(wp_remote_retrieve_body($response));
        if (!is_object($data) || !isset($data->tag_name) || (string) $data->tag_name === '') {
            return null;
        }
        set_transient('crm_cf7_connector_github_release', $data, 6 * HOUR_IN_SECONDS);
        $this->github_response = $data;
        return $this->github_response;
    }

    /**
     * WordPress (et d'autres extensions) peuvent passer false, null ou un tableau
     * à ce filtre. Un typage `object` provoque alors un TypeError fatal, ce qui
     * déclenche l'e-mail de protection de WordPress.
     *
     * @param mixed $transient
     * @return mixed
     */
    public function check_update($transient) {
        if (!is_object($transient) || empty($transient->checked) || !is_array($transient->checked)) {
            return $transient;
        }

        $release = $this->get_github_release();
        if ($release === null || !isset($release->tag_name)) {
            return $transient;
        }

        $github_version  = ltrim((string) $release->tag_name, 'v');
        $current_version = $this->plugin_data['Version'] ?? CRM_CF7_CONNECTOR_VERSION;

        if (!isset($transient->response) || !is_array($transient->response)) {
            $transient->response = [];
        }

        if (version_compare($github_version, $current_version, '>')) {
            $transient->response[$this->plugin_file] = (object) [
                'id'            => $this->plugin_file,
                'slug'          => $this->slug,
                'plugin'        => $this->plugin_file,
                'new_version'   => $github_version,
                'url'           => 'https://github.com/' . $this->github_username . '/' . $this->github_repo,
                'package'       => $this->get_download_url($release),
                'tested'        => '6.4',
                'requires'      => '6.0',
                'requires_php'  => '8.0',
                'icons'         => [],
                'banners'       => [],
                'banners_rtl'   => [],
                'compatibility' => new stdClass(),
            ];
        }

        return $transient;
    }

    private function get_download_url(object $release): string {
        if (!empty($release->assets) && (is_array($release->assets) || $release->assets instanceof Traversable)) {
            foreach ($release->assets as $asset) {
                if (!is_object($asset)) {
                    continue;
                }
                $name = (string) ($asset->name ?? '');
                if ($name !== '' && substr($name, -4) === '.zip') {
                    return (string) ($asset->browser_download_url ?? '');
                }
            }
        }
        return (string) ($release->zipball_url ?? '');
    }

    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information' || !is_object($args)) return $result;
        if (!isset($args->slug) || $args->slug !== $this->slug) return $result;

        $release = $this->get_github_release();
        if ($release === null) return $result;

        return (object) [
            'name'           => $this->plugin_data['Name'] ?? 'CRM CF7 Connector',
            'slug'           => $this->slug,
            'version'        => ltrim((string) ($release->tag_name ?? ''), 'v'),
            'author'         => $this->plugin_data['Author'] ?? 'Le Beau Digital',
            'author_profile' => 'https://github.com/' . $this->github_username,
            'homepage'       => 'https://github.com/' . $this->github_username . '/' . $this->github_repo,
            'requires'       => '6.0',
            'tested'         => '6.4',
            'requires_php'   => '8.0',
            'last_updated'   => $release->published_at ?? '',
            'sections'       => [
                'description' => $this->plugin_data['Description'] ?? '',
                'changelog'   => $this->format_changelog($release),
            ],
            'download_link'  => $this->get_download_url($release),
        ];
    }

    private function format_changelog(object $release): string {
        $out = '<h4>' . esc_html((string) $release->tag_name) . '</h4>';
        if (!empty($release->body)) {
            $body = esc_html((string) $release->body);
            $body = preg_replace('/^### (.+)$/m', '<h5>$1</h5>', $body);
            $body = preg_replace('/^## (.+)$/m', '<h4>$1</h4>', $body);
            $body = preg_replace('/^[\*\-] (.+)$/m', '<li>$1</li>', $body);
            $out .= '<p>' . nl2br((string) $body) . '</p>';
        }
        $out .= '<p><a href="' . esc_url((string) $release->html_url) . '" target="_blank">'
              . esc_html__('Voir la release sur GitHub', 'crm-cf7-connector') . '</a></p>';
        return $out;
    }

    /**
     * Renomme le dossier extrait du zipball GitHub pour qu'il porte le même
     * nom que le dossier déjà installé (crm-cf7-connector, ou crm-cf7).
     *
     * @param string|WP_Error $source
     * @param mixed           $hook_extra
     * @return string|WP_Error
     */
    public function source_selection($source, $remote_source = '', $upgrader = null, $hook_extra = []) {
        if (is_wp_error($source) || !is_string($source)) {
            return $source;
        }
        if (!is_array($hook_extra) || ($hook_extra['plugin'] ?? '') !== $this->plugin_file) {
            return $source;
        }

        $folder = dirname($this->plugin_file);
        if ($folder === '.' || $folder === '') {
            return $source;
        }

        global $wp_filesystem;
        if (!$wp_filesystem || !is_string($remote_source) || $remote_source === '') {
            return $source;
        }

        $from = trailingslashit($source);
        $to   = trailingslashit($remote_source) . $folder . '/';
        if (untrailingslashit($from) === untrailingslashit($to)) {
            return $to;
        }

        if ($wp_filesystem->exists($to)) {
            $wp_filesystem->delete($to, true);
        }
        if (!$wp_filesystem->move($from, $to)) {
            return new WP_Error(
                'crm_cf7_rename_failed',
                __('Impossible de préparer le dossier de la mise à jour GitHub.', 'crm-cf7-connector')
            );
        }

        return $to;
    }

    public function post_install($response, $hook_extra, $result) {
        if (!is_array($result)) {
            return $result;
        }

        global $wp_filesystem;
        if (!is_array($hook_extra) || ($hook_extra['plugin'] ?? '') !== $this->plugin_file) {
            return $result;
        }

        $folder = dirname($this->plugin_file);
        if ($folder !== '.' && $folder !== '' && $wp_filesystem && !empty($result['destination'])) {
            $plugin_folder = wp_normalize_path(untrailingslashit(WP_PLUGIN_DIR . '/' . $folder));
            $installed_to  = wp_normalize_path(untrailingslashit((string) $result['destination']));
            if ($installed_to !== $plugin_folder) {
                if ($wp_filesystem->exists($plugin_folder)) {
                    $wp_filesystem->delete($plugin_folder, true);
                }
                if ($wp_filesystem->move($result['destination'], $plugin_folder)) {
                    $result['destination'] = $plugin_folder;
                }
            }
        }

        activate_plugin($this->plugin_file);
        delete_transient('crm_cf7_connector_github_release');
        $this->github_response = null;
        return $result;
    }

    /**
     * @param string[] $links
     * @return string[]
     */
    public function plugin_row_meta($links, $file) {
        if (!is_array($links) || !is_string($file) || $file !== $this->plugin_file) {
            return $links;
        }
        $repo_url = 'https://github.com/' . $this->github_username . '/' . $this->github_repo;
        $links[] = '<a href="' . esc_url($repo_url) . '" target="_blank">GitHub</a>';
        $links[] = '<a href="' . esc_url($repo_url . '/issues') . '" target="_blank">'
                 . esc_html__('Signaler un bug', 'crm-cf7-connector') . '</a>';
        return $links;
    }

    /**
     * Permet de forcer une nouvelle vérification depuis l'admin via
     * /wp-admin/plugins.php?crm_cf7_force_update=1
     */
    public function handle_force_check(): void {
        if (!isset($_GET['crm_cf7_force_update'])) {
            return;
        }
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash((string) ($_GET['_wpnonce'] ?? ''))), 'crm_cf7_force_update')) {
            return;
        }
        if (!current_user_can('update_plugins')) {
            return;
        }

        delete_transient('crm_cf7_connector_github_release');
        delete_site_transient('update_plugins');
        $this->github_response = null;
        $this->last_error = '';

        $release = $this->get_github_release();
        $current_version = $this->plugin_data['Version'] ?? CRM_CF7_CONNECTOR_VERSION;

        if ($release === null) {
            $error = $this->last_error;
            add_action('admin_notices', static function () use ($error): void {
                $message = __('CRM CF7 Connector : impossible de lire la dernière release GitHub.', 'crm-cf7-connector');
                if ($error !== '') {
                    $message .= ' ' . $error;
                }
                echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html($message) . '</p></div>';
            });
            return;
        }

        $github_version = ltrim((string) $release->tag_name, 'v');

        if (version_compare($github_version, $current_version, '>')) {
            add_action('admin_notices', static function () use ($github_version, $current_version): void {
                echo '<div class="notice notice-success is-dismissible"><p>';
                echo esc_html(sprintf(
                    /* translators: 1: version installée, 2: version GitHub */
                    __('CRM CF7 Connector : mise à jour disponible, version %1$s → %2$s.', 'crm-cf7-connector'),
                    $current_version,
                    $github_version
                ));
                echo ' <a href="' . esc_url(admin_url('update-core.php')) . '">'
                    . esc_html__('Mettre à jour maintenant', 'crm-cf7-connector') . '</a>';
                echo '</p></div>';
            });
            return;
        }

        add_action('admin_notices', static function () use ($current_version, $github_version): void {
            echo '<div class="notice notice-success is-dismissible"><p>'
                . esc_html(sprintf(
                    /* translators: 1: version installée, 2: version GitHub */
                    __('CRM CF7 Connector est à jour (version %1$s). Dernière release GitHub : %2$s.', 'crm-cf7-connector'),
                    $current_version,
                    $github_version
                ))
                . '</p></div>';
        });
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    private function github_request(string $url) {
        $args = [
            'timeout' => 15,
            'headers' => [
                'Accept'     => 'application/vnd.github.v3+json',
                'User-Agent' => 'CRM-CF7-Connector-Updater',
            ],
        ];
        if ($this->access_token !== '') {
            $args['headers']['Authorization'] = 'token ' . $this->access_token;
        }

        $response = wp_remote_get($url, $args);
        if (!is_wp_error($response)) {
            return $response;
        }

        // MAMP local : le certificat du système n'est pas toujours reconnu.
        $args['sslverify'] = false;
        return wp_remote_get($url, $args);
    }
}
