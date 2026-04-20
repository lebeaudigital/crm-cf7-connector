=== CRM CF7 Connector ===
Contributors: lebeaudigital
Tags: contact form 7, cf7, crm, leads, lead generation
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connecte vos formulaires Contact Form 7 au CRM Le Beau Digital : crée et met à jour automatiquement contacts, entreprises et affaires.

== Description ==

CRM CF7 Connector envoie chaque soumission Contact Form 7 vers l'API REST du CRM Le Beau Digital (https://crm.lebeaudigital.com).

= Fonctionnalités =

* Une page de réglages globale (clé API + URL CRM, valeurs par défaut, bouton de test de connexion).
* Un panneau « CRM » dans l'éditeur de chaque formulaire CF7 pour mapper visuellement les champs.
* Mapping par tag : nommez vos champs CF7 `crm-first_name`, `crm-company-name`, `crm-deal-title`… et le mapping est automatique.
* Upsert intelligent : recherche par email pour un contact, par nom pour une entreprise, puis création ou mise à jour.
* Création optionnelle d'une affaire (deal) à chaque soumission.
* Source par défaut, tags contact par défaut, tag entreprise par défaut.
* Envoi ignoré si CF7 détecte un spam ou une erreur de validation.
* Mises à jour via les releases GitHub (lebeaudigital/crm-cf7-connector).

== Installation ==

1. Téléversez le dossier `crm-cf7-connector` dans `wp-content/plugins/`.
2. Activez le plugin dans le menu Extensions.
3. Allez dans Réglages → CRM CF7 et collez votre clé API (générée dans le CRM, Réglages → API).
4. Cliquez sur « Tester la connexion ».
5. Ouvrez un formulaire CF7 → onglet « CRM » → activez l'envoi et configurez le mapping.

== Changelog ==

= 1.0.0 =
* Première version : connexion CF7 ↔ CRM Le Beau Digital (contacts, entreprises, affaires).
* Mapping UI dans l'éditeur CF7 + fallback par préfixe `crm-`.
* Auto-update via GitHub Releases.
