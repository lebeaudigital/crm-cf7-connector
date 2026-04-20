<?php
/**
 * Mapper CF7 → CRM.
 *
 * Extrait les données d'une soumission Contact Form 7 et les convertit en
 * payloads compatibles avec les endpoints CRM (contacts, clients, deals).
 *
 * Sources de mapping :
 *   1. Mapping UI configuré par formulaire (post meta `_crm_cf7_mapping`).
 *   2. Fallback : tags CF7 préfixés `crm-` (ex: [text* crm-first_name]).
 */

if (!defined('ABSPATH')) {
    exit;
}

class CRM_CF7_Mapper {

    /** Champs supportés pour le mapping UI. */
    public const CONTACT_FIELDS = [
        'first_name', 'last_name', 'email', 'phone',
        'position', 'source', 'notes', 'tags',
    ];

    public const CLIENT_FIELDS = [
        'name', 'email', 'phone', 'website', 'sector',
        'address', 'city', 'postal_code', 'country', 'siret', 'status',
    ];

    public const DEAL_FIELDS = [
        'title', 'stage', 'probability', 'expected_close_date',
        'amount', 'notes',
    ];

    /**
     * Construit les payloads contact / client / deal à partir d'une soumission CF7.
     *
     * @param array<string, mixed>  $posted_data Données envoyées (clé = nom du champ CF7)
     * @param array<string, mixed>  $form_config Config CRM enregistrée pour le formulaire
     * @return array{contact: array<string, mixed>, client: array<string, mixed>, deal: array<string, mixed>, has_company: bool, create_deal: bool}
     */
    public function build_payloads(array $posted_data, array $form_config): array {
        $mapping = is_array($form_config['mapping'] ?? null) ? $form_config['mapping'] : [];

        $contact = $this->collect('contact', self::CONTACT_FIELDS, $mapping, $posted_data);
        $client  = $this->collect('client',  self::CLIENT_FIELDS,  $mapping, $posted_data);
        $deal    = $this->collect('deal',    self::DEAL_FIELDS,    $mapping, $posted_data);

        $contact = $this->merge_crm_prefixed_fields($contact, $posted_data, self::CONTACT_FIELDS, 'crm-');
        $client  = $this->merge_crm_prefixed_fields($client,  $posted_data, self::CLIENT_FIELDS,  'crm-company-');
        $deal    = $this->merge_crm_prefixed_fields($deal,    $posted_data, self::DEAL_FIELDS,    'crm-deal-');

        $contact = $this->normalize_contact($contact);
        $client  = $this->normalize_client($client);
        $deal    = $this->normalize_deal($deal);

        return [
            'contact'     => $contact,
            'client'      => $client,
            'deal'        => $deal,
            'has_company' => !empty($client['name']),
            'create_deal' => !empty($form_config['create_deal']) && !empty($deal['title']),
        ];
    }

    /**
     * Récupère les valeurs des champs configurés pour un objet (contact|client|deal).
     *
     * @param array<string, array<string, string>> $mapping
     * @param array<string, mixed>                 $posted_data
     * @param string[]                             $fields
     * @return array<string, mixed>
     */
    private function collect(string $object, array $fields, array $mapping, array $posted_data): array {
        $object_map = is_array($mapping[$object] ?? null) ? $mapping[$object] : [];
        $output     = [];

        foreach ($fields as $crm_field) {
            $cf7_field = $object_map[$crm_field] ?? '';
            if ($cf7_field === '' || !array_key_exists($cf7_field, $posted_data)) {
                continue;
            }
            $value = $posted_data[$cf7_field];
            if (is_array($value)) {
                $value = implode(', ', array_map('strval', $value));
            }
            $value = trim((string) $value);
            if ($value !== '') {
                $output[$crm_field] = $value;
            }
        }

        return $output;
    }

    /**
     * Fallback : pour chaque champ CRM non encore renseigné, regarde si un champ CF7
     * existe avec le préfixe (ex: crm-first_name pour contact, crm-company-name pour client).
     *
     * @param array<string, mixed> $current
     * @param array<string, mixed> $posted_data
     * @param string[]             $fields
     * @return array<string, mixed>
     */
    private function merge_crm_prefixed_fields(array $current, array $posted_data, array $fields, string $prefix): array {
        foreach ($fields as $crm_field) {
            if (isset($current[$crm_field])) {
                continue;
            }
            $candidate = $prefix . $crm_field;
            if (!array_key_exists($candidate, $posted_data)) {
                continue;
            }
            $value = $posted_data[$candidate];
            if (is_array($value)) {
                $value = implode(', ', array_map('strval', $value));
            }
            $value = trim((string) $value);
            if ($value !== '') {
                $current[$crm_field] = $value;
            }
        }
        return $current;
    }

    /**
     * Normalise / renforce le payload contact :
     *   - Sépare automatiquement first_name/last_name si seul un nom complet est fourni
     *     dans un champ courant (your-name, name, full-name, fullname).
     *   - Ajoute la source par défaut.
     *   - Ajoute les tags par défaut.
     *
     * @param array<string, mixed> $contact
     * @return array<string, mixed>
     */
    private function normalize_contact(array $contact): array {
        $settings = CRM_CF7_Settings::get();

        if (!isset($contact['source']) && !empty($settings['default_source'])) {
            $contact['source'] = (string) $settings['default_source'];
        }

        $default_tags = trim((string) ($settings['default_contact_tags'] ?? ''));
        if ($default_tags !== '') {
            $contact['tags'] = isset($contact['tags']) && $contact['tags'] !== ''
                ? rtrim((string) $contact['tags'], ', ') . ', ' . $default_tags
                : $default_tags;
        }

        // Si on n'a qu'un nom complet (cas le plus fréquent en CF7), tente un split.
        if (empty($contact['first_name']) && empty($contact['last_name']) && !empty($contact['full_name'])) {
            [$first, $last] = $this->split_name((string) $contact['full_name']);
            $contact['first_name'] = $first;
            $contact['last_name']  = $last;
            unset($contact['full_name']);
        }

        return $contact;
    }

    /**
     * @param array<string, mixed> $client
     * @return array<string, mixed>
     */
    private function normalize_client(array $client): array {
        $settings = CRM_CF7_Settings::get();

        $default_tag = trim((string) ($settings['default_company_tag'] ?? ''));
        if ($default_tag !== '') {
            // Le CRM clients n'expose pas de champ tags officiel : on le pose en notes.
            $client['notes'] = isset($client['notes']) && $client['notes'] !== ''
                ? trim((string) $client['notes']) . "\n[Tag] " . $default_tag
                : '[Tag] ' . $default_tag;
        }

        if (!empty($client['name']) && empty($client['status'])) {
            $client['status'] = 'prospect';
        }

        return $client;
    }

    /**
     * @param array<string, mixed> $deal
     * @return array<string, mixed>
     */
    private function normalize_deal(array $deal): array {
        if (empty($deal)) {
            return $deal;
        }
        if (!isset($deal['stage']))               $deal['stage']               = 'Prospection';
        if (!isset($deal['probability']))         $deal['probability']         = 10;
        if (!isset($deal['expected_close_date'])) $deal['expected_close_date'] = gmdate('Y-m-d', strtotime('+30 days'));

        $deal['probability'] = (int) $deal['probability'];
        if (isset($deal['amount'])) {
            $deal['amount'] = (float) str_replace([' ', ','], ['', '.'], (string) $deal['amount']);
        }

        return $deal;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function split_name(string $full): array {
        $full = trim(preg_replace('/\s+/', ' ', $full) ?? '');
        if ($full === '') {
            return ['', ''];
        }
        $parts = explode(' ', $full, 2);
        return [$parts[0], $parts[1] ?? ''];
    }

    /**
     * Liste les noms de champs disponibles dans un formulaire CF7 (pour l'UI de mapping).
     *
     * @return string[]
     */
    public static function get_form_field_names(WPCF7_ContactForm $form): array {
        $names = [];
        if (method_exists($form, 'scan_form_tags')) {
            $tags = $form->scan_form_tags();
            foreach ($tags as $tag) {
                $name = is_object($tag) ? ($tag->name ?? '') : ($tag['name'] ?? '');
                if ($name !== '' && !in_array($name, $names, true)) {
                    $names[] = $name;
                }
            }
        }
        sort($names);
        return $names;
    }
}
