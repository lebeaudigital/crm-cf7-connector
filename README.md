# CRM CF7 Connector

> Plugin WordPress qui connecte **Contact Form 7** au **CRM Le Beau Digital**.
> Chaque soumission de formulaire crée ou met à jour automatiquement un contact, une entreprise et (optionnellement) une affaire dans le CRM.

[![GitHub release](https://img.shields.io/github/v/release/lebeaudigital/crm-cf7-connector)](https://github.com/lebeaudigital/crm-cf7-connector/releases)

## Sommaire

- [Fonctionnalités](#fonctionnalités)
- [Installation](#installation)
- [Configuration](#configuration)
- [Mapping des champs](#mapping-des-champs)
- [Architecture](#architecture)
- [Mises à jour](#mises-à-jour)
- [Développement](#développement)

## Fonctionnalités

- **Réglages globaux** : URL CRM, clé API, source par défaut, tags par défaut, tag entreprise par défaut, bouton « Tester la connexion ».
- **Panneau « CRM » dans l'éditeur CF7** : un sélecteur par champ CRM, par formulaire.
- **Mapping par tag (fallback)** : nommez vos champs CF7 `crm-email`, `crm-company-name`, `crm-deal-title`… et le mapping est automatique.
- **Upsert intelligent** :
  - **Entreprise** : recherche par nom → création ou mise à jour.
  - **Contact** : recherche par email → création ou mise à jour, rattaché à l'entreprise.
  - **Affaire** : créée si activée et si un titre est mappé.
- **Skip on spam/validation** : aucune donnée n'est envoyée au CRM si CF7 a flaggé la soumission.
- **Mise à jour automatique** depuis les releases GitHub.

## Installation

### Méthode 1 — Depuis WordPress (recommandé)

1. Téléchargez la dernière release ZIP : [Releases](https://github.com/lebeaudigital/crm-cf7-connector/releases).
2. Dans WordPress, allez dans **Extensions → Ajouter → Téléverser**.
3. Activez le plugin.

### Méthode 2 — Manuelle

```bash
cd wp-content/plugins
git clone https://github.com/lebeaudigital/crm-cf7-connector.git
```

## Configuration

1. Dans le CRM, allez dans **Réglages → API** et générez une clé.
2. Dans WordPress, **Réglages → CRM CF7** :
   - Collez l'URL du CRM (`https://crm.lebeaudigital.com` par défaut).
   - Collez la clé API.
   - Cliquez sur **Tester la connexion**.
3. Renseignez vos valeurs par défaut (source, tags…).
4. Cochez « Ne pas envoyer si CF7 détecte un spam » (recommandé).

## Mapping des champs

### Option A — Interface visuelle (par formulaire)

Ouvrez un formulaire CF7 → onglet **CRM** :

- Cochez **Activer l'envoi vers le CRM pour ce formulaire**.
- Pour chaque champ CRM (Contact / Entreprise / Affaire), choisissez le champ CF7 source dans le sélecteur.
- (Optionnel) Cochez **Créer aussi une affaire**.

### Option B — Convention de nommage (sans UI)

Si vous ne configurez aucun mapping UI, le plugin lit directement les noms de champs CF7 préfixés :

| Préfixe              | Cible CRM       |
| -------------------- | --------------- |
| `crm-`               | Contact         |
| `crm-company-`       | Entreprise      |
| `crm-deal-`          | Affaire (deal)  |

Exemple :

```text
[text* crm-first_name]
[text* crm-last_name]
[email* crm-email]
[text crm-company-name]
[text crm-deal-title]
```

Les deux modes peuvent coexister : l'UI prime, le préfixe sert de fallback.

## Architecture

```
crm-cf7-connector/
├── crm-cf7-connector.php       # Bootstrap & singleton
├── api/                        # Tous les appels HTTP au CRM
│   ├── class-crm-cf7-api-client.php       # Client HTTP générique (X-API-Key)
│   ├── class-crm-cf7-api-contacts.php
│   ├── class-crm-cf7-api-clients.php
│   └── class-crm-cf7-api-deals.php
├── includes/                   # Logique métier
│   ├── class-crm-cf7-settings.php         # Helper d'options
│   ├── class-crm-cf7-mapper.php           # CF7 → payloads CRM
│   ├── class-crm-cf7-cf7-integration.php  # Hooks CF7
│   ├── class-crm-cf7-admin.php            # Page de réglages + AJAX test
│   └── class-crm-cf7-updater.php          # Updater via GitHub Releases
├── assets/
│   ├── css/admin.css
│   └── js/
│       ├── admin-settings.js              # Page de réglages
│       └── cf7-form-mapping.js            # Panneau CRM dans CF7
├── languages/
├── readme.txt
└── README.md
```

Conventions :

- **Une classe PHP par responsabilité**, namespacée par préfixe `CRM_CF7_…`.
- **Un fichier JS ES6 par fonctionnalité**, en module IIFE strict.
- **Tous les appels HTTP au CRM passent par `api/`** — aucun `wp_remote_*` en dehors.

## Mises à jour

Le plugin se met à jour comme un plugin officiel WordPress :

- Tag GitHub `vX.Y.Z` (ex: `v1.0.1`) → notification dans **Extensions → Mises à jour disponibles**.
- Cache : 6 heures (transient `crm_cf7_connector_github_release`).
- Pour forcer une vérification : visitez `/wp-admin/plugins.php?crm_cf7_force_update=1&_wpnonce=…`.

### Publier une nouvelle version

1. Bumpez `Version:` dans `crm-cf7-connector.php` **et** la constante `CRM_CF7_CONNECTOR_VERSION`.
2. Commit + push.
3. Créez une release sur GitHub avec le tag `vX.Y.Z`.
4. Décrivez les changements dans la release (le contenu remonte dans le « What's new » de WordPress).

## Développement

### Pré-requis

- PHP 8.0+
- WordPress 6.0+
- Contact Form 7 5.x
- Le CRM Le Beau Digital avec une clé API valide

### Hooks utilisés

- `wpcf7_editor_panels` — ajoute l'onglet « CRM ».
- `wpcf7_save_contact_form` — sauvegarde la config par formulaire.
- `wpcf7_before_send_mail` — déclenche l'envoi au CRM.

### Endpoints CRM appelés

| Action        | Méthode | Endpoint                              |
| ------------- | ------- | ------------------------------------- |
| Lister        | GET     | `/api/v1/contacts/list.php`           |
| Créer contact | POST    | `/api/v1/contacts/create.php`         |
| MAJ contact   | PUT     | `/api/v1/contacts/update.php?id={id}` |
| Créer entreprise | POST | `/api/v1/clients/create.php`          |
| MAJ entreprise   | PUT  | `/api/v1/clients/update.php?id={id}`  |
| Créer affaire    | POST | `/api/v1/deals/create.php`            |

Tous protégés par header `X-API-Key`.

## Licence

GPL v2 ou ultérieure — © Le Beau Digital.
