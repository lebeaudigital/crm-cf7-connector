<?php
/**
 * Repository API « Affaires (deals) » — endpoints /api/v1/deals/*
 */

if (!defined('ABSPATH')) {
    exit;
}

class CRM_CF7_API_Deals {

    private CRM_CF7_API_Client $client;

    public function __construct(?CRM_CF7_API_Client $client = null) {
        $this->client = $client ?? new CRM_CF7_API_Client();
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{success: bool, status: int, data: mixed, message: string}
     */
    public function list(array $filters = []): array {
        return $this->client->get('/api/v1/deals/list.php', $filters);
    }

    /**
     * @return array{success: bool, status: int, data: mixed, message: string}
     */
    public function get(int $id): array {
        return $this->client->get('/api/v1/deals/get.php', ['id' => $id]);
    }

    /**
     * @param array<string, mixed> $data
     * @return array{success: bool, status: int, data: mixed, message: string}
     */
    public function create(array $data): array {
        return $this->client->post('/api/v1/deals/create.php', $data);
    }

    /**
     * @param array<string, mixed> $data
     * @return array{success: bool, status: int, data: mixed, message: string}
     */
    public function update(int $id, array $data): array {
        return $this->client->put('/api/v1/deals/update.php', ['id' => $id], $data);
    }

    /**
     * @return array{success: bool, status: int, data: mixed, message: string}
     */
    public function delete(int $id): array {
        return $this->client->delete('/api/v1/deals/delete.php', ['id' => $id]);
    }
}
