<?php
/**
 * Intégration Contact Form 7 :
 *   - Ajoute un panneau « CRM » dans l'éditeur de formulaire CF7
 *   - Sauvegarde la configuration de mapping (post meta `_crm_cf7_mapping`)
 *   - Hook `wpcf7_before_send_mail` pour envoyer au CRM à chaque soumission
 *   - Respecte les règles de skip (spam, validation, configuration incomplète)
 */

if (!defined('ABSPATH')) {
    exit;
}

class CRM_CF7_Integration {

    private const META_KEY = '_crm_cf7_mapping';

    private static ?CRM_CF7_Integration $instance = null;

    public static function get_instance(): CRM_CF7_Integration {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Onglet CRM dans l'éditeur CF7 (admin uniquement)
        add_filter('wpcf7_editor_panels', [$this, 'add_editor_panel']);
        add_action('wpcf7_save_contact_form', [$this, 'save_form_config']);

        // Soumission frontend
        add_action('wpcf7_before_send_mail', [$this, 'on_form_submitted'], 10, 3);

        // Assets admin (uniquement sur l'écran d'édition CF7)
        add_action('admin_enqueue_scripts', [$this, 'enqueue_editor_assets']);
    }

    /**
     * Ajoute notre panneau dans l'éditeur CF7.
     *
     * @param array<string, array<string, mixed>> $panels
     * @return array<string, array<string, mixed>>
     */
    public function add_editor_panel(array $panels): array {
        $panels['crm-cf7-panel'] = [
            'title'    => __('CRM', 'crm-cf7-connector'),
            'callback' => [$this, 'render_editor_panel'],
        ];
        return $panels;
    }

    /**
     * Rendu du panneau CRM dans l'éditeur CF7.
     */
    public function render_editor_panel(WPCF7_ContactForm $form): void {
        $form_id = $form->id();
        $config  = self::get_form_config($form_id);
        $fields  = CRM_CF7_Mapper::get_form_field_names($form);

        wp_nonce_field('crm_cf7_save_form_config', 'crm_cf7_form_nonce');

        $field_options = '<option value="">' . esc_html__('— Aucun —', 'crm-cf7-connector') . '</option>';
        foreach ($fields as $field_name) {
            $field_options .= sprintf('<option value="%s">%s</option>', esc_attr($field_name), esc_html($field_name));
        }

        $sections = [
            'contact' => [
                'label'  => __('Contact', 'crm-cf7-connector'),
                'fields' => CRM_CF7_Mapper::CONTACT_FIELDS,
            ],
            'client' => [
                'label'  => __('Entreprise', 'crm-cf7-connector'),
                'fields' => CRM_CF7_Mapper::CLIENT_FIELDS,
            ],
            'deal' => [
                'label'  => __('Affaire (deal)', 'crm-cf7-connector'),
                'fields' => CRM_CF7_Mapper::DEAL_FIELDS,
            ],
        ];

        $enabled     = !empty($config['enabled']);
        $create_deal = !empty($config['create_deal']);
        $mapping     = is_array($config['mapping'] ?? null) ? $config['mapping'] : [];
        ?>
        <h2><?php esc_html_e('Connexion CRM', 'crm-cf7-connector'); ?></h2>
        <fieldset>
            <legend>
                <?php esc_html_e(
                    "Envoie automatiquement chaque soumission de ce formulaire vers le CRM Le Beau Digital.",
                    'crm-cf7-connector'
                ); ?>
            </legend>

            <?php if (!CRM_CF7_Settings::has_credentials()) : ?>
                <div class="notice notice-warning inline" style="padding:8px 12px;margin:10px 0;">
                    <?php
                    printf(
                        /* translators: %s = lien vers les réglages */
                        wp_kses_post(__('La clé API CRM n\'est pas encore configurée. <a href="%s">Aller aux réglages</a>.', 'crm-cf7-connector')),
                        esc_url(admin_url('options-general.php?page=' . CRM_CF7_CONNECTOR_SLUG))
                    );
                    ?>
                </div>
            <?php endif; ?>

            <p>
                <label>
                    <input type="checkbox" name="crm_cf7_config[enabled]" value="1" <?php checked($enabled); ?> />
                    <strong><?php esc_html_e('Activer l\'envoi vers le CRM pour ce formulaire', 'crm-cf7-connector'); ?></strong>
                </label>
            </p>

            <p>
                <label>
                    <input type="checkbox" name="crm_cf7_config[create_deal]" value="1" <?php checked($create_deal); ?> />
                    <?php esc_html_e('Créer aussi une affaire (deal) à chaque soumission', 'crm-cf7-connector'); ?>
                </label>
            </p>

            <hr />

            <p class="description">
                <?php esc_html_e('Associez chaque champ CRM à un champ de votre formulaire. Vous pouvez aussi nommer vos champs CF7 avec les préfixes', 'crm-cf7-connector'); ?>
                <code>crm-</code>, <code>crm-company-</code>, <code>crm-deal-</code>
                <?php esc_html_e('pour un mapping automatique.', 'crm-cf7-connector'); ?>
            </p>

            <?php foreach ($sections as $object_key => $section) : ?>
                <h3 style="margin-top:18px;"><?php echo esc_html($section['label']); ?></h3>
                <table class="form-table" role="presentation">
                    <tbody>
                    <?php foreach ($section['fields'] as $crm_field) :
                        $selected = $mapping[$object_key][$crm_field] ?? '';
                        ?>
                        <tr>
                            <th scope="row" style="width:180px;">
                                <label for="crm_cf7_<?php echo esc_attr($object_key . '_' . $crm_field); ?>">
                                    <code><?php echo esc_html($crm_field); ?></code>
                                </label>
                            </th>
                            <td>
                                <select
                                    id="crm_cf7_<?php echo esc_attr($object_key . '_' . $crm_field); ?>"
                                    name="crm_cf7_config[mapping][<?php echo esc_attr($object_key); ?>][<?php echo esc_attr($crm_field); ?>]"
                                    class="crm-cf7-mapping-select"
                                >
                                    <?php
                                    echo str_replace(
                                        sprintf(' value="%s"', esc_attr($selected)),
                                        sprintf(' value="%s" selected', esc_attr($selected)),
                                        $field_options
                                    );
                                    ?>
                                </select>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endforeach; ?>
        </fieldset>
        <?php
    }

    /**
     * Sauvegarde la configuration CRM lorsque le formulaire CF7 est enregistré.
     */
    public function save_form_config(WPCF7_ContactForm $form): void {
        if (!isset($_POST['crm_cf7_form_nonce']) || !wp_verify_nonce(
                sanitize_text_field(wp_unslash((string) $_POST['crm_cf7_form_nonce'])),
                'crm_cf7_save_form_config'
            )) {
            return;
        }
        if (!current_user_can('wpcf7_edit_contact_form', $form->id())) {
            return;
        }

        $raw    = isset($_POST['crm_cf7_config']) && is_array($_POST['crm_cf7_config'])
            ? wp_unslash($_POST['crm_cf7_config'])
            : [];

        $config = [
            'enabled'     => !empty($raw['enabled']),
            'create_deal' => !empty($raw['create_deal']),
            'mapping'     => $this->sanitize_mapping($raw['mapping'] ?? []),
        ];

        update_post_meta($form->id(), self::META_KEY, $config);
    }

    /**
     * @param mixed $raw
     * @return array<string, array<string, string>>
     */
    private function sanitize_mapping($raw): array {
        if (!is_array($raw)) {
            return [];
        }
        $clean = [];
        $allowed = [
            'contact' => CRM_CF7_Mapper::CONTACT_FIELDS,
            'client'  => CRM_CF7_Mapper::CLIENT_FIELDS,
            'deal'    => CRM_CF7_Mapper::DEAL_FIELDS,
        ];

        foreach ($allowed as $object_key => $fields) {
            $object_raw = $raw[$object_key] ?? [];
            if (!is_array($object_raw)) {
                continue;
            }
            foreach ($fields as $crm_field) {
                $value = isset($object_raw[$crm_field]) ? sanitize_text_field((string) $object_raw[$crm_field]) : '';
                if ($value !== '') {
                    $clean[$object_key][$crm_field] = $value;
                }
            }
        }
        return $clean;
    }

    /**
     * @return array<string, mixed>
     */
    public static function get_form_config(int $form_id): array {
        $stored = get_post_meta($form_id, self::META_KEY, true);
        if (!is_array($stored)) {
            $stored = [];
        }
        return wp_parse_args($stored, [
            'enabled'     => false,
            'create_deal' => false,
            'mapping'     => [],
        ]);
    }

    /**
     * Hook principal : appelé juste avant l'envoi du mail CF7.
     *
     * On utilise ce hook (plutôt que `wpcf7_mail_sent`) pour pouvoir traiter
     * la soumission même si l'envoi du mail échoue.
     *
     * @param mixed $abort   Référence (peut être renseignée pour annuler l'envoi)
     */
    public function on_form_submitted(WPCF7_ContactForm $form, &$abort, WPCF7_Submission $submission): void {
        $config = self::get_form_config($form->id());
        if (empty($config['enabled'])) {
            return;
        }

        if (!CRM_CF7_Settings::has_credentials()) {
            error_log('[CRM CF7 Connector] Aucune clé API configurée — soumission ignorée pour le formulaire #' . $form->id());
            return;
        }

        $skip_on_spam = (bool) CRM_CF7_Settings::get_value('skip_on_spam', true);
        $status       = $submission->get_status();
        if ($skip_on_spam && in_array($status, ['spam', 'validation_failed', 'aborted'], true)) {
            return;
        }

        $posted = $submission->get_posted_data();
        if (!is_array($posted)) {
            return;
        }

        $mapper   = new CRM_CF7_Mapper();
        $payloads = $mapper->build_payloads($posted, $config);

        try {
            $this->push_to_crm($payloads, $form->id(), $submission);
        } catch (Throwable $e) {
            error_log('[CRM CF7 Connector] Exception lors de l\'envoi au CRM : ' . $e->getMessage());
        }
    }

    /**
     * Orchestration : entreprise (upsert) → contact (upsert) → affaire (create).
     *
     * @param array{contact: array<string, mixed>, client: array<string, mixed>, deal: array<string, mixed>, has_company: bool, create_deal: bool} $payloads
     */
    private function push_to_crm(array $payloads, int $form_id, WPCF7_Submission $submission): void {
        $contact_payload = $payloads['contact'];
        $client_payload  = $payloads['client'];
        $deal_payload    = $payloads['deal'];

        if (empty($contact_payload['email'])) {
            error_log('[CRM CF7 Connector] Aucun email dans la soumission #' . $form_id . ' — abandon.');
            return;
        }

        $contacts_api = new CRM_CF7_API_Contacts();
        $clients_api  = new CRM_CF7_API_Clients();
        $deals_api    = new CRM_CF7_API_Deals();

        $client_id = null;
        if ($payloads['has_company']) {
            $client_id = $this->upsert_client($clients_api, $client_payload);
            if ($client_id !== null) {
                $contact_payload['client_id'] = $client_id;
                error_log('[CRM CF7 Connector] Form #' . $form_id . ' → entreprise "' . ($client_payload['name'] ?? '') . '" liée au contact (client_id=' . $client_id . ').');
            } else {
                error_log('[CRM CF7 Connector] Form #' . $form_id . ' → impossible de récupérer client_id pour "' . ($client_payload['name'] ?? '') . '" — contact créé sans entreprise.');
            }
        }

        $contact_id = $this->upsert_contact($contacts_api, $contact_payload);

        if ($payloads['create_deal'] && $contact_id !== null) {
            $deal_payload['contact_id'] = $contact_id;
            if ($client_id !== null) {
                $deal_payload['client_id'] = $client_id;
            }
            if (empty($deal_payload['client_id'])) {
                error_log('[CRM CF7 Connector] Deal non créé : client_id manquant (formulaire #' . $form_id . ').');
            } else {
                $resp = $deals_api->create($deal_payload);
                if (!$resp['success']) {
                    error_log('[CRM CF7 Connector] Création deal échouée : ' . $resp['message']);
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function upsert_client(CRM_CF7_API_Clients $api, array $payload): ?int {
        if (empty($payload['name'])) {
            return null;
        }

        $name = (string) $payload['name'];

        $existing = $api->find_by_name($name);
        if (is_array($existing) && !empty($existing['id'])) {
            $update = $payload;
            unset($update['name']);
            if (!empty($update)) {
                $resp = $api->update((int) $existing['id'], $update);
                if (!$resp['success']) {
                    error_log('[CRM CF7 Connector] Update entreprise échoué : ' . $resp['message']);
                }
            }
            return (int) $existing['id'];
        }

        $resp = $api->create($payload);
        if (!$resp['success']) {
            error_log('[CRM CF7 Connector] Création entreprise échouée (HTTP ' . $resp['status'] . ') : ' . $resp['message']);
            return null;
        }

        $id = $this->extract_id($resp['data']);
        if ($id !== null) {
            return $id;
        }

        // Fallback : la création a réussi mais la réponse ne contient pas d'id exploitable
        // (peut arriver si la clé API ne peut pas relire le client juste créé via getById).
        // On relance une recherche par nom pour récupérer l'id et garantir l'association.
        error_log('[CRM CF7 Connector] Création entreprise OK mais id absent de la réponse — fallback recherche par nom pour "' . $name . '".');
        $found = $api->find_by_name($name);
        if (is_array($found) && !empty($found['id'])) {
            return (int) $found['id'];
        }

        error_log('[CRM CF7 Connector] Fallback échoué — entreprise "' . $name . '" introuvable après création.');
        return null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function upsert_contact(CRM_CF7_API_Contacts $api, array $payload): ?int {
        $email = (string) ($payload['email'] ?? '');
        if ($email === '') {
            return null;
        }
        $existing = $api->find_by_email($email);
        if (is_array($existing) && !empty($existing['id'])) {
            $update = $payload;
            unset($update['email']);
            if (!empty($update)) {
                $resp = $api->update((int) $existing['id'], $update);
                if (!$resp['success']) {
                    error_log('[CRM CF7 Connector] Update contact échoué : ' . $resp['message']);
                }
            }
            return (int) $existing['id'];
        }

        if (empty($payload['first_name']) || empty($payload['last_name'])) {
            error_log('[CRM CF7 Connector] Contact non créé : first_name et last_name requis.');
            return null;
        }

        $resp = $api->create($payload);
        if (!$resp['success']) {
            error_log('[CRM CF7 Connector] Création contact échouée (HTTP ' . $resp['status'] . ') : ' . $resp['message']);
            return null;
        }

        $id = $this->extract_id($resp['data']);
        if ($id !== null) {
            return $id;
        }

        // Fallback : id absent de la réponse → recherche par email
        error_log('[CRM CF7 Connector] Création contact OK mais id absent de la réponse — fallback recherche par email pour "' . $email . '".');
        $found = $api->find_by_email($email);
        if (is_array($found) && !empty($found['id'])) {
            return (int) $found['id'];
        }
        return null;
    }

    private function extract_id($data): ?int {
        if (is_array($data) && isset($data['id'])) {
            return (int) $data['id'];
        }
        return null;
    }

    /**
     * Charge les assets du panneau CRM uniquement sur l'écran d'édition CF7.
     */
    public function enqueue_editor_assets(string $hook_suffix): void {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || !in_array($screen->id, ['toplevel_page_wpcf7', 'contact_page_wpcf7-new'], true)) {
            return;
        }

        wp_enqueue_style(
            'crm-cf7-connector-admin',
            CRM_CF7_CONNECTOR_URL . 'assets/css/admin.css',
            [],
            CRM_CF7_CONNECTOR_VERSION
        );

        wp_enqueue_script(
            'crm-cf7-connector-form-mapping',
            CRM_CF7_CONNECTOR_URL . 'assets/js/cf7-form-mapping.js',
            [],
            CRM_CF7_CONNECTOR_VERSION,
            true
        );
    }
}
