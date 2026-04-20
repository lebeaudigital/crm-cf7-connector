/**
 * CRM CF7 Connector — Réglages globaux (page Réglages → CRM CF7).
 *
 * Gère :
 *   - Le bouton « Afficher/Masquer » la clé API.
 *   - Le bouton « Tester la connexion » (AJAX vers wp-admin/admin-ajax.php).
 *
 * @module admin-settings
 */

(() => {
    'use strict';

    const init = () => {
        bindToggleApiKey();
        bindTestConnection();
    };

    const bindToggleApiKey = () => {
        const button = document.getElementById('crm-cf7-toggle-key');
        const input = document.getElementById('crm_cf7_api_key');
        if (!button || !input) return;

        button.addEventListener('click', () => {
            input.type = input.type === 'password' ? 'text' : 'password';
            // Si l'utilisateur veut voir la clé sauvegardée, on remplace le placeholder __keep__
            if (input.type === 'text' && input.value === '__keep__') {
                input.value = '';
                input.placeholder = '••••••••••••';
            }
        });
    };

    const bindTestConnection = () => {
        const button = document.getElementById('crm-cf7-test-connection');
        const result = document.getElementById('crm-cf7-test-result');
        if (!button || !result) return;

        button.addEventListener('click', async () => {
            const apiUrlInput = document.getElementById('crm_cf7_api_url');
            const apiKeyInput = document.getElementById('crm_cf7_api_key');

            const apiUrl = apiUrlInput?.value?.trim() ?? '';
            const apiKey = apiKeyInput?.value?.trim() ?? '';
            const nonce = button.dataset.nonce ?? '';

            setStatus(result, 'pending', window.CrmCf7Admin?.i18n?.testing ?? 'Test…');
            button.disabled = true;

            try {
                const body = new URLSearchParams({
                    action: 'crm_cf7_test_connection',
                    nonce,
                    api_url: apiUrl,
                    api_key: apiKey,
                });

                const response = await fetch(window.CrmCf7Admin?.ajaxUrl ?? '/wp-admin/admin-ajax.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body,
                });

                const json = await response.json();
                if (json?.success) {
                    setStatus(result, 'success', json.data?.message ?? 'OK');
                } else {
                    setStatus(result, 'error', json?.data?.message ?? 'Erreur inconnue');
                }
            } catch (error) {
                setStatus(result, 'error', error?.message ?? String(error));
            } finally {
                button.disabled = false;
            }
        });
    };

    /**
     * @param {HTMLElement} target
     * @param {'pending'|'success'|'error'} state
     * @param {string} message
     */
    const setStatus = (target, state, message) => {
        target.textContent = message;
        target.dataset.state = state;
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
