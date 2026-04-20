/**
 * CRM CF7 Connector — UI du panneau « CRM » dans l'éditeur Contact Form 7.
 *
 * Améliorations UX :
 *   - Met en évidence les selects qui pointent sur un champ inexistant
 *     (utile quand on supprime un tag CF7 sans mettre à jour le mapping).
 *   - Replie automatiquement les sections « Entreprise » et « Affaire »
 *     tant qu'aucun mapping n'est défini, pour alléger la vue.
 *
 * @module cf7-form-mapping
 */

(() => {
    'use strict';

    const init = () => {
        flagBrokenMappings();
        collapseEmptySections();
        bindEnableToggle();
    };

    /**
     * Récupère la liste des noms de champs déclarés dans le formulaire CF7
     * en lisant directement le textarea principal (#wpcf7-form).
     *
     * @returns {Set<string>}
     */
    const readFormFieldNames = () => {
        const textarea = document.getElementById('wpcf7-form');
        const names = new Set();
        if (!textarea?.value) return names;

        const tagRegex = /\[[^\]]+]/g;
        const matches = textarea.value.match(tagRegex) ?? [];

        for (const tag of matches) {
            const inside = tag.slice(1, -1).trim().split(/\s+/);
            // 0: type (ex: text*), 1: name, suite: options
            if (inside.length >= 2) {
                const name = inside[1].replace(/[^a-zA-Z0-9_\-]/g, '');
                if (name) names.add(name);
            }
        }
        return names;
    };

    const flagBrokenMappings = () => {
        const known = readFormFieldNames();
        document.querySelectorAll('.crm-cf7-mapping-select').forEach((select) => {
            if (!(select instanceof HTMLSelectElement)) return;
            const value = select.value;
            if (value && !known.has(value)) {
                select.classList.add('crm-cf7-mapping-broken');
                select.title = `⚠ Champ "${value}" introuvable dans le formulaire`;
            } else {
                select.classList.remove('crm-cf7-mapping-broken');
                select.title = '';
            }
        });
    };

    const collapseEmptySections = () => {
        const headings = document.querySelectorAll('#crm-cf7-panel h3');
        headings.forEach((heading) => {
            const table = heading.nextElementSibling;
            if (!(table instanceof HTMLTableElement)) return;

            const hasMapping = Array.from(table.querySelectorAll('select')).some(
                (s) => s instanceof HTMLSelectElement && s.value !== ''
            );

            if (hasMapping) return;
            // Section vide : repliage léger via display none au début
            table.style.display = 'none';
            heading.style.cursor = 'pointer';
            heading.insertAdjacentHTML('beforeend', ' <span class="crm-cf7-section-toggle">▸</span>');

            heading.addEventListener('click', () => {
                const visible = table.style.display !== 'none';
                table.style.display = visible ? 'none' : '';
                const toggle = heading.querySelector('.crm-cf7-section-toggle');
                if (toggle) toggle.textContent = visible ? '▸' : '▾';
            });
        });
    };

    /**
     * Si l'utilisateur décoche « Activer », grise visuellement le reste du panneau.
     */
    const bindEnableToggle = () => {
        const checkbox = document.querySelector('input[name="crm_cf7_config[enabled]"]');
        const fieldset = checkbox?.closest('fieldset');
        if (!checkbox || !fieldset) return;

        const apply = () => {
            fieldset.classList.toggle('crm-cf7-disabled', !checkbox.checked);
        };
        checkbox.addEventListener('change', apply);
        apply();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
