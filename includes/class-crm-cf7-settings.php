<?php
/**
 * Helper centralisé pour lire/écrire les réglages globaux du plugin.
 *
 * Stocké dans une option unique (CRM_CF7_CONNECTOR_OPTION) pour limiter
 * le nombre de lookups DB.
 */

if (!defined('ABSPATH')) {
    exit;
}

class CRM_CF7_Settings {

    /**
     * @return array<string, mixed>
     */
    public static function get_defaults(): array {
        return [
            'api_url'             => CRM_CF7_CONNECTOR_DEFAULT_API_URL,
            'api_key'             => '',
            'default_source'      => 'Site web',
            'default_list_market' => '',
            'default_company_tag' => '',
            'skip_on_spam'        => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function get(): array {
        $stored = get_option(CRM_CF7_CONNECTOR_OPTION, []);
        if (!is_array($stored)) {
            $stored = [];
        }
        // Migration douce : ancienne clé default_contact_tags → default_list_market
        if (!isset($stored['default_list_market']) && isset($stored['default_contact_tags'])) {
            $stored['default_list_market'] = $stored['default_contact_tags'];
        }
        return array_merge(self::get_defaults(), $stored);
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    public static function update(array $values): array {
        $current = self::get();
        $merged  = array_merge($current, $values);
        update_option(CRM_CF7_CONNECTOR_OPTION, $merged);
        return $merged;
    }

    public static function get_value(string $key, $default = null) {
        $all = self::get();
        return $all[$key] ?? $default;
    }

    /**
     * Retourne une URL de base saine pour les appels API.
     */
    public static function get_api_url(): string {
        $url = (string) self::get_value('api_url', CRM_CF7_CONNECTOR_DEFAULT_API_URL);
        $url = rtrim(trim($url), '/');
        return $url !== '' ? $url : CRM_CF7_CONNECTOR_DEFAULT_API_URL;
    }

    public static function get_api_key(): string {
        return (string) self::get_value('api_key', '');
    }

    public static function has_credentials(): bool {
        return self::get_api_key() !== '';
    }
}
