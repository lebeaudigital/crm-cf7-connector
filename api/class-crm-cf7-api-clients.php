<?php
/**
 * Repository API « Entreprises (clients) » — endpoints /api/v1/clients/*
 */

if (!defined('ABSPATH')) {
    exit;
}

class CRM_CF7_API_Clients {

    private CRM_CF7_API_Client $client;

    public function __construct(?CRM_CF7_API_Client $client = null) {
        $this->client = $client ?? new CRM_CF7_API_Client();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{success: bool, status: int, data: mixed, message: string}
     */
    public function list(array $filters = []): array {
        return $this->client->get('/api/v1/clients/list.php', $filters);
    }

    /**
     * @return array{success: bool, status: int, data: mixed, message: string}
     */
    public function get(int $id): array {
        return $this->client->get('/api/v1/clients/get.php', ['id' => $id]);
    }

    /**
     * Recherche une entreprise par son nom (correspondance exacte, insensible à la casse).
     *
     * @return array<string, mixed>|null
     */
    public function find_by_name(string $name): ?array {
        $name = trim($name);
        if ($name === '') {
            return null;
        }
        $resp = $this->list(['search' => $name, 'limit' => 10, 'page' => 1]);
        if (!$resp['success'] || !is_array($resp['data'])) {
            return null;
        }
        foreach ($resp['data'] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            if (strcasecmp((string) ($entry['name'] ?? ''), $name) === 0) {
                return $entry;
            }
        }
        return null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array{success: bool, status: int, data: mixed, message: string}
     */
    public function create(array $data): array {
        return $this->client->post('/api/v1/clients/create.php', $data);
    }

    /**
     * @param array<string, mixed> $data
     * @return array{success: bool, status: int, data: mixed, message: string}
     */
    public function update(int $id, array $data): array {
        return $this->client->put('/api/v1/clients/update.php', ['id' => $id], $data);
    }

    /**
     * @return array{success: bool, status: int, data: mixed, message: string}
     */
    public function delete(int $id): array {
        return $this->client->delete('/api/v1/clients/delete.php', ['id' => $id]);
    }
}
