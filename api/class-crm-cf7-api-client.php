<?php
/**
 * Client HTTP bas-niveau pour l'API REST du CRM Le Beau Digital.
 *
 * Encapsule les appels wp_remote_request avec authentification X-API-Key,
 * gestion d'erreurs uniformisée et parsing JSON.
 */

if (!defined('ABSPATH')) {
    exit;
}

class CRM_CF7_API_Client {

    private string $base_url;
    private string $api_key;
    private int $timeout;

    public function __construct(?string $base_url = null, ?string $api_key = null, int $timeout = 15) {
        $settings        = CRM_CF7_Settings::get();
        $this->base_url  = rtrim($base_url ?? ($settings['api_url'] ?? CRM_CF7_CONNECTOR_DEFAULT_API_URL), '/');
        $this->api_key   = $api_key ?? ($settings['api_key'] ?? '');
        $this->timeout   = $timeout;
    }

    /**
     * Effectue une requête GET.
     *
     * @param string               $path  Chemin relatif (ex: /api/v1/contacts/list.php)
     * @param array<string, mixed> $query Paramètres GET
     * @return array{success: bool, status: int, data: mixed, message: string}
     */
    public function get(string $path, array $query = []): array {
        $url = $this->build_url($path, $query);
        return $this->request('GET', $url, null);
    }

    /**
     * Effectue une requête POST avec corps JSON.
     *
     * @param string               $path Chemin relatif
     * @param array<string, mixed> $body Données à envoyer
     * @return array{success: bool, status: int, data: mixed, message: string}
     */
    public function post(string $path, array $body = []): array {
        return $this->request('POST', $this->build_url($path), $body);
    }

    /**
     * Effectue une requête PUT avec corps JSON.
     *
     * @param string               $path  Chemin relatif
     * @param array<string, mixed> $query Paramètres GET (ex: id)
     * @param array<string, mixed> $body  Données à envoyer
     * @return array{success: bool, status: int, data: mixed, message: string}
     */
    public function put(string $path, array $query = [], array $body = []): array {
        return $this->request('PUT', $this->build_url($path, $query), $body);
    }

    /**
     * Effectue une requête DELETE.
     *
     * @param string               $path  Chemin relatif
     * @param array<string, mixed> $query Paramètres GET
     * @return array{success: bool, status: int, data: mixed, message: string}
     */
    public function delete(string $path, array $query = []): array {
        return $this->request('DELETE', $this->build_url($path, $query), null);
    }

    /**
     * Test de connectivité : tente un GET /api/v1/contacts/list.php?limit=1
     *
     * @return array{success: bool, status: int, data: mixed, message: string}
     */
    public function ping(): array {
        return $this->get('/api/v1/contacts/list.php', ['limit' => 1, 'page' => 1]);
    }

    private function build_url(string $path, array $query = []): string {
        $url = $this->base_url . '/' . ltrim($path, '/');
        if (!empty($query)) {
            $url = add_query_arg($query, $url);
        }
        return $url;
    }

    /**
     * @return array{success: bool, status: int, data: mixed, message: string}
     */
    private function request(string $method, string $url, ?array $body): array {
        if (empty($this->api_key)) {
            return [
                'success' => false,
                'status'  => 0,
                'data'    => null,
                'message' => __('Clé API CRM manquante. Configurez-la dans Réglages → CRM CF7.', 'crm-cf7-connector'),
            ];
        }

        $args = [
            'method'  => $method,
            'timeout' => $this->timeout,
            'headers' => [
                'X-API-Key'    => $this->api_key,
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
                'User-Agent'   => 'CRM-CF7-Connector/' . CRM_CF7_CONNECTOR_VERSION . '; ' . home_url(),
            ],
        ];

        if ($body !== null) {
            $args['body'] = wp_json_encode($body);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'status'  => 0,
                'data'    => null,
                'message' => $response->get_error_message(),
            ];
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $raw    = wp_remote_retrieve_body($response);
        $json   = json_decode($raw, true);

        if (!is_array($json)) {
            return [
                'success' => false,
                'status'  => $status,
                'data'    => $raw,
                'message' => sprintf(
                    /* translators: %d: HTTP status code */
                    __('Réponse non-JSON du CRM (HTTP %d).', 'crm-cf7-connector'),
                    $status
                ),
            ];
        }

        $success = !empty($json['success']);

        return [
            'success' => $success && $status >= 200 && $status < 300,
            'status'  => $status,
            'data'    => $json['data'] ?? $json,
            'message' => (string) ($json['message'] ?? ''),
        ];
    }
}
