<?php
/**
 * Repository API « Contacts » — encapsule les endpoints /api/v1/contacts/*
 */

if (!defined('ABSPATH')) {
    exit;
}

class CRM_CF7_API_Contacts {

    private CRM_CF7_API_Client $client;

    public function __construct(?CRM_CF7_API_Client $client = null) {
        $this->client = $client ?? new CRM_CF7_API_Client();
    }

    /**
     * Liste paginée de contacts (avec filtres optionnels).
     *
     * @param array<string, mixed> $filters
     * @return array{success: bool, status: int, data: mixed, message: string}
     */
    public function list(array $filters = []): array {
        return $this->client->get('/api/v1/contacts/list.php', $filters);
    }

    /**
     * Récupère un contact par son ID.
     *
     * @return array{success: bool, status: int, data: mixed, message: string}
     */
    public function get(int $id): array {
        return $this->client->get('/api/v1/contacts/get.php', ['id' => $id]);
    }

    /**
     * Recherche par email (utilise list.php?search=).
     * Retourne le premier match exact ou null.
     *
     * @return array<string, mixed>|null
     */
    public function find_by_email(string $email): ?array {
        if (!is_email($email)) {
            return null;
        }
        $resp = $this->list(['search' => $email, 'limit' => 5, 'page' => 1]);
        if (!$resp['success'] || !is_array($resp['data'])) {
            return null;
        }
        foreach ($resp['data'] as $contact) {
            if (!is_array($contact)) {
                continue;
            }
            if (strcasecmp((string) ($contact['email'] ?? ''), $email) === 0) {
                return $contact;
            }
        }
        return null;
    }

    /**
     * Crée un contact.
     *
     * @param array<string, mixed> $data
     * @return array{success: bool, status: int, data: mixed, message: string}
     */
    public function create(array $data): array {
        return $this->client->post('/api/v1/contacts/create.php', $data);
    }

    /**
     * Met à jour un contact.
     *
     * @param array<string, mixed> $data
     * @return array{success: bool, status: int, data: mixed, message: string}
     */
    public function update(int $id, array $data): array {
        return $this->client->put('/api/v1/contacts/update.php', ['id' => $id], $data);
    }

    /**
     * Supprime un contact.
     *
     * @return array{success: bool, status: int, data: mixed, message: string}
     */
    public function delete(int $id): array {
        return $this->client->delete('/api/v1/contacts/delete.php', ['id' => $id]);
    }
}
