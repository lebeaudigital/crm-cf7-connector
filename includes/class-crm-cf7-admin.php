<?php
/**
 * Page de réglages globaux du plugin (Réglages → CRM CF7).
 *
 * Inclut un endpoint admin-ajax pour le bouton "Tester la connexion".
 */

if (!defined('ABSPATH')) {
    exit;
}

class CRM_CF7_Admin {

    private static ?CRM_CF7_Admin $instance = null;

    public static function get_instance(): CRM_CF7_Admin {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_crm_cf7_test_connection', [$this, 'ajax_test_connection']);

        // Lien "Réglages" sur la page Plugins
        add_filter('plugin_action_links_' . CRM_CF7_CONNECTOR_BASENAME, [$this, 'plugin_action_links']);
    }

    public function register_menu(): void {
        add_options_page(
            __('CRM CF7 Connector', 'crm-cf7-connector'),
            __('CRM CF7', 'crm-cf7-connector'),
            'manage_options',
            CRM_CF7_CONNECTOR_SLUG,
            [$this, 'render_settings_page']
        );
    }

    public function register_settings(): void {
        register_setting(
            'crm_cf7_connector_group',
            CRM_CF7_CONNECTOR_OPTION,
            [
                'type'              => 'array',
                'sanitize_callback' => [$this, 'sanitize_settings'],
                'default'           => CRM_CF7_Settings::get_defaults(),
            ]
        );
    }

    /**
     * @param mixed $input
     * @return array<string, mixed>
     */
    public function sanitize_settings($input): array {
        if (!is_array($input)) {
            return CRM_CF7_Settings::get();
        }

        $current = CRM_CF7_Settings::get();
        $clean   = [];

        $api_url = isset($input['api_url']) ? esc_url_raw(trim((string) $input['api_url'])) : '';
        $clean['api_url'] = $api_url !== '' ? rtrim($api_url, '/') : CRM_CF7_CONNECTOR_DEFAULT_API_URL;

        if (isset($input['api_key'])) {
            $api_key = trim((string) $input['api_key']);
            // Si l'utilisateur laisse le placeholder masqué tel quel, on ne change rien.
            if ($api_key !== '' && $api_key !== '__keep__') {
                $clean['api_key'] = sanitize_text_field($api_key);
            } else {
                $clean['api_key'] = $current['api_key'];
            }
        }

        $clean['default_source']       = sanitize_text_field((string) ($input['default_source'] ?? ''));
        $clean['default_list_market']  = sanitize_text_field((string) ($input['default_list_market'] ?? ''));
        $clean['default_company_tag']  = sanitize_text_field((string) ($input['default_company_tag'] ?? ''));
        $clean['skip_on_spam']         = !empty($input['skip_on_spam']);

        // Nettoyage de l'ancienne clé après migration vers default_list_market
        unset($clean['default_contact_tags'], $current['default_contact_tags']);

        return array_merge($current, $clean);
    }

    public function render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        $settings   = CRM_CF7_Settings::get();
        $masked_key = $settings['api_key'] !== '' ? '__keep__' : '';
        ?>
        <div class="wrap crm-cf7-admin-wrap">
            <h1><?php esc_html_e('CRM CF7 Connector', 'crm-cf7-connector'); ?></h1>
            <p class="description">
                <?php esc_html_e('Connecte vos formulaires Contact Form 7 au CRM Le Beau Digital.', 'crm-cf7-connector'); ?>
            </p>

            <form method="post" action="options.php" class="crm-cf7-settings-form">
                <?php settings_fields('crm_cf7_connector_group'); ?>

                <h2 class="title"><?php esc_html_e('Connexion à l\'API CRM', 'crm-cf7-connector'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="crm_cf7_api_url"><?php esc_html_e('URL du CRM', 'crm-cf7-connector'); ?></label>
                        </th>
                        <td>
                            <input
                                type="url"
                                id="crm_cf7_api_url"
                                name="<?php echo esc_attr(CRM_CF7_CONNECTOR_OPTION); ?>[api_url]"
                                value="<?php echo esc_attr($settings['api_url']); ?>"
                                class="regular-text"
                                placeholder="<?php echo esc_attr(CRM_CF7_CONNECTOR_DEFAULT_API_URL); ?>"
                                required
                            />
                            <p class="description">
                                <?php esc_html_e('Ex.', 'crm-cf7-connector'); ?>
                                <code><?php echo esc_html(CRM_CF7_CONNECTOR_DEFAULT_API_URL); ?></code>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="crm_cf7_api_key"><?php esc_html_e('Clé API', 'crm-cf7-connector'); ?></label>
                        </th>
                        <td>
                            <input
                                type="password"
                                id="crm_cf7_api_key"
                                name="<?php echo esc_attr(CRM_CF7_CONNECTOR_OPTION); ?>[api_key]"
                                value="<?php echo esc_attr($masked_key); ?>"
                                class="regular-text"
                                autocomplete="off"
                                placeholder="<?php esc_attr_e('Collez votre clé X-API-Key ici', 'crm-cf7-connector'); ?>"
                            />
                            <button
                                type="button"
                                class="button button-secondary"
                                id="crm-cf7-toggle-key"
                            >
                                <?php esc_html_e('Afficher / Masquer', 'crm-cf7-connector'); ?>
                            </button>
                            <p class="description">
                                <?php
                                printf(
                                    /* translators: %s: lien vers la page Réglages → API du CRM */
                                    wp_kses_post(__('Générez une clé dans %s.', 'crm-cf7-connector')),
                                    '<a href="' . esc_url($settings['api_url'] . '/settings.php') . '" target="_blank" rel="noopener">'
                                    . esc_html__('le CRM (Réglages → API)', 'crm-cf7-connector') . '</a>'
                                );
                                ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Test de connexion', 'crm-cf7-connector'); ?></th>
                        <td>
                            <button
                                type="button"
                                class="button button-primary"
                                id="crm-cf7-test-connection"
                                data-nonce="<?php echo esc_attr(wp_create_nonce('crm_cf7_test_connection')); ?>"
                            >
                                <?php esc_html_e('Tester la connexion', 'crm-cf7-connector'); ?>
                            </button>
                            <span id="crm-cf7-test-result" class="crm-cf7-test-result"></span>
                            <p class="description">
                                <?php esc_html_e('Effectue un appel GET sur /api/v1/contacts/list.php pour valider l\'URL et la clé.', 'crm-cf7-connector'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <h2 class="title"><?php esc_html_e('Valeurs par défaut', 'crm-cf7-connector'); ?></h2>
                <p class="description">
                    <?php esc_html_e('Appliquées à toutes les soumissions, sauf si le formulaire fournit déjà une valeur.', 'crm-cf7-connector'); ?>
                </p>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="crm_cf7_default_source"><?php esc_html_e('Source par défaut', 'crm-cf7-connector'); ?></label>
                        </th>
                        <td>
                            <input
                                type="text"
                                id="crm_cf7_default_source"
                                name="<?php echo esc_attr(CRM_CF7_CONNECTOR_OPTION); ?>[default_source]"
                                value="<?php echo esc_attr($settings['default_source']); ?>"
                                class="regular-text"
                                placeholder="<?php esc_attr_e('Site web', 'crm-cf7-connector'); ?>"
                            />
                            <p class="description">
                                <?php esc_html_e('Champ « source » du contact (ex: Site web, Landing 2026, etc.).', 'crm-cf7-connector'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="crm_cf7_default_list_market"><?php esc_html_e('Liste(s) marketing par défaut', 'crm-cf7-connector'); ?></label>
                        </th>
                        <td>
                            <input
                                type="text"
                                id="crm_cf7_default_list_market"
                                name="<?php echo esc_attr(CRM_CF7_CONNECTOR_OPTION); ?>[default_list_market]"
                                value="<?php echo esc_attr($settings['default_list_market']); ?>"
                                class="regular-text"
                                placeholder="<?php esc_attr_e('Newsletter, Contact CF7', 'crm-cf7-connector'); ?>"
                            />
                            <p class="description">
                                <?php esc_html_e('Séparées par des virgules. Chaque contact sera abonné à ces listes marketing (créées automatiquement dans le CRM si elles n\'existent pas).', 'crm-cf7-connector'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="crm_cf7_default_company_tag"><?php esc_html_e('Tag entreprise par défaut', 'crm-cf7-connector'); ?></label>
                        </th>
                        <td>
                            <input
                                type="text"
                                id="crm_cf7_default_company_tag"
                                name="<?php echo esc_attr(CRM_CF7_CONNECTOR_OPTION); ?>[default_company_tag]"
                                value="<?php echo esc_attr($settings['default_company_tag']); ?>"
                                class="regular-text"
                                placeholder="<?php esc_attr_e('cf7', 'crm-cf7-connector'); ?>"
                            />
                            <p class="description">
                                <?php esc_html_e('Stocké dans le champ « notes » de l\'entreprise (le CRM n\'expose pas encore de champ tags pour les entreprises).', 'crm-cf7-connector'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <h2 class="title"><?php esc_html_e('Comportement', 'crm-cf7-connector'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Spam & validation', 'crm-cf7-connector'); ?></th>
                        <td>
                            <label>
                                <input
                                    type="checkbox"
                                    name="<?php echo esc_attr(CRM_CF7_CONNECTOR_OPTION); ?>[skip_on_spam]"
                                    value="1"
                                    <?php checked(!empty($settings['skip_on_spam'])); ?>
                                />
                                <?php esc_html_e('Ne pas envoyer au CRM si CF7 détecte un spam ou une erreur de validation.', 'crm-cf7-connector'); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function enqueue_assets(string $hook_suffix): void {
        if ($hook_suffix !== 'settings_page_' . CRM_CF7_CONNECTOR_SLUG) {
            return;
        }

        wp_enqueue_style(
            'crm-cf7-connector-admin',
            CRM_CF7_CONNECTOR_URL . 'assets/css/admin.css',
            [],
            CRM_CF7_CONNECTOR_VERSION
        );

        wp_enqueue_script(
            'crm-cf7-connector-admin-settings',
            CRM_CF7_CONNECTOR_URL . 'assets/js/admin-settings.js',
            [],
            CRM_CF7_CONNECTOR_VERSION,
            true
        );

        wp_localize_script('crm-cf7-connector-admin-settings', 'CrmCf7Admin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'i18n'    => [
                'testing' => __('Test en cours…', 'crm-cf7-connector'),
                'success' => __('Connexion OK', 'crm-cf7-connector'),
                'failure' => __('Échec', 'crm-cf7-connector'),
            ],
        ]);
    }

    /**
     * Endpoint AJAX : effectue un ping sur l'API CRM avec les credentials
     * actuellement saisis dans le formulaire (sans avoir besoin de sauvegarder).
     */
    public function ajax_test_connection(): void {
        check_ajax_referer('crm_cf7_test_connection', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission refusée.', 'crm-cf7-connector')], 403);
        }

        $api_url = isset($_POST['api_url']) ? esc_url_raw(wp_unslash((string) $_POST['api_url'])) : '';
        $api_key = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash((string) $_POST['api_key'])) : '';

        if ($api_key === '' || $api_key === '__keep__') {
            $api_key = CRM_CF7_Settings::get_api_key();
        }
        if ($api_url === '') {
            $api_url = CRM_CF7_Settings::get_api_url();
        }

        $client   = new CRM_CF7_API_Client($api_url, $api_key, 10);
        $response = $client->ping();

        if ($response['success']) {
            wp_send_json_success([
                'message' => sprintf(
                    /* translators: %d = HTTP status code */
                    __('Connexion réussie (HTTP %d).', 'crm-cf7-connector'),
                    $response['status']
                ),
            ]);
        }

        wp_send_json_error([
            'message' => sprintf(
                /* translators: 1: HTTP code, 2: message d'erreur */
                __('Échec (HTTP %1$d) : %2$s', 'crm-cf7-connector'),
                $response['status'],
                $response['message'] ?: __('Erreur inconnue', 'crm-cf7-connector')
            ),
        ]);
    }

    /**
     * @param string[] $links
     * @return string[]
     */
    public function plugin_action_links(array $links): array {
        $settings = '<a href="' . esc_url(admin_url('options-general.php?page=' . CRM_CF7_CONNECTOR_SLUG)) . '">'
            . esc_html__('Réglages', 'crm-cf7-connector') . '</a>';
        array_unshift($links, $settings);
        return $links;
    }
}
