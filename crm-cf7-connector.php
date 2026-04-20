<?php
/**
 * Plugin Name: CRM CF7 Connector
 * Plugin URI: https://github.com/lebeaudigital/crm-cf7-connector
 * Description: Connecte Contact Form 7 à votre CRM Le Beau Digital. Crée/met à jour automatiquement contacts, entreprises et affaires depuis vos formulaires.
 * Version: 1.0.0
 * Author: Le Beau Digital
 * Author URI: https://lebeaudigital.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: crm-cf7-connector
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * GitHub Plugin URI: lebeaudigital/crm-cf7-connector
 * GitHub Branch: main
 */

if (!defined('ABSPATH')) {
    exit;
}

define('CRM_CF7_CONNECTOR_VERSION', '1.0.0');
define('CRM_CF7_CONNECTOR_FILE', __FILE__);
define('CRM_CF7_CONNECTOR_DIR', plugin_dir_path(__FILE__));
define('CRM_CF7_CONNECTOR_URL', plugin_dir_url(__FILE__));
define('CRM_CF7_CONNECTOR_BASENAME', plugin_basename(__FILE__));
define('CRM_CF7_CONNECTOR_SLUG', 'crm-cf7-connector');
define('CRM_CF7_CONNECTOR_OPTION', 'crm_cf7_connector_settings');
define('CRM_CF7_CONNECTOR_DEFAULT_API_URL', 'https://crm.lebeaudigital.com');

/**
 * Classe principale (singleton) du plugin CRM CF7 Connector.
 */
final class CRM_CF7_Connector {

    /** @var CRM_CF7_Connector|null */
    private static ?CRM_CF7_Connector $instance = null;

    public static function get_instance(): CRM_CF7_Connector {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    private function load_dependencies(): void {
        // API
        require_once CRM_CF7_CONNECTOR_DIR . 'api/class-crm-cf7-api-client.php';
        require_once CRM_CF7_CONNECTOR_DIR . 'api/class-crm-cf7-api-contacts.php';
        require_once CRM_CF7_CONNECTOR_DIR . 'api/class-crm-cf7-api-clients.php';
        require_once CRM_CF7_CONNECTOR_DIR . 'api/class-crm-cf7-api-deals.php';

        // Includes
        require_once CRM_CF7_CONNECTOR_DIR . 'includes/class-crm-cf7-settings.php';
        require_once CRM_CF7_CONNECTOR_DIR . 'includes/class-crm-cf7-mapper.php';
        require_once CRM_CF7_CONNECTOR_DIR . 'includes/class-crm-cf7-cf7-integration.php';
        require_once CRM_CF7_CONNECTOR_DIR . 'includes/class-crm-cf7-admin.php';
        require_once CRM_CF7_CONNECTOR_DIR . 'includes/class-crm-cf7-updater.php';
    }

    private function init_hooks(): void {
        register_activation_hook(CRM_CF7_CONNECTOR_FILE, [$this, 'activate']);
        register_deactivation_hook(CRM_CF7_CONNECTOR_FILE, [$this, 'deactivate']);

        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_action('plugins_loaded', [$this, 'maybe_warn_cf7_missing'], 20);

        // Intégration CF7 (uniquement si CF7 est actif)
        add_action('plugins_loaded', function (): void {
            if ($this->is_cf7_active()) {
                CRM_CF7_Integration::get_instance();
            }
        }, 30);

        // Admin
        if (is_admin()) {
            CRM_CF7_Admin::get_instance();
            CRM_CF7_Updater::get_instance();
        }
    }

    public function load_textdomain(): void {
        load_plugin_textdomain(
            'crm-cf7-connector',
            false,
            dirname(CRM_CF7_CONNECTOR_BASENAME) . '/languages'
        );
    }

    public function activate(): void {
        $defaults = CRM_CF7_Settings::get_defaults();
        if (!get_option(CRM_CF7_CONNECTOR_OPTION)) {
            add_option(CRM_CF7_CONNECTOR_OPTION, $defaults);
        }
    }

    public function deactivate(): void {
        delete_transient('crm_cf7_connector_github_release');
    }

    public function is_cf7_active(): bool {
        return class_exists('WPCF7') || function_exists('wpcf7');
    }

    public function maybe_warn_cf7_missing(): void {
        if ($this->is_cf7_active() || !is_admin()) {
            return;
        }
        add_action('admin_notices', function (): void {
            echo '<div class="notice notice-warning"><p><strong>CRM CF7 Connector</strong> : ';
            echo esc_html__('Contact Form 7 doit être installé et activé pour utiliser ce plugin.', 'crm-cf7-connector');
            echo '</p></div>';
        });
    }
}

CRM_CF7_Connector::get_instance();
