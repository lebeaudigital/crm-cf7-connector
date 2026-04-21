=== CRM CF7 Connector ===
Contributors: lebeaudigital
Tags: contact form 7, cf7, crm, leads, lead generation
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 8.0
Stable tag: 1.0.4
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
* Source par défaut, liste(s) marketing par défaut, tag entreprise par défaut.
* Abonnement automatique aux listes marketing du CRM (table `marketing_lists`) — création de la liste si elle n'existe pas.
* Envoi ignoré si CF7 détecte un spam ou une erreur de validation.
* Mises à jour via les releases GitHub (lebeaudigital/crm-cf7-connector).

== Installation ==

1. Téléversez le dossier `crm-cf7-connector` dans `wp-content/plugins/`.
2. Activez le plugin dans le menu Extensions.
3. Allez dans Réglages → CRM CF7 et collez votre clé API (générée dans le CRM, Réglages → API).
4. Cliquez sur « Tester la connexion ».
5. Ouvrez un formulaire CF7 → onglet « CRM » → activez l'envoi et configurez le mapping.

== Changelog ==

= 1.0.4 =
* Soumissions de formulaires beaucoup plus rapides : le push vers le CRM est désormais traité en arrière-plan via wp-cron (déclenché immédiatement par loopback non-bloquant).
* Le visiteur reçoit la confirmation CF7 sans attendre les 3 à 5 secondes des appels API au CRM.
* Nouvelle case à cocher dans Réglages → CRM CF7 → Comportement → « Traiter l'envoi au CRM en arrière-plan » (activée par défaut, désactivable si vous avez besoin d'un push synchrone).
* Nettoyage automatique des événements cron en attente lors de la désactivation du plugin.

= 1.0.3 =
* Nouveau lien « Vérifier la MàJ » dans la ligne du plugin (Extensions → Extensions installées) qui force une nouvelle interrogation de GitHub sans attendre les 12h de cache WordPress.
* Nouveau bouton « Vérifier les mises à jour » dans Réglages → CRM CF7 (avec affichage de la version installée).

= 1.0.2 =
* Correctif : le contact est désormais correctement associé à l'entreprise lorsque les deux sont créés dans la même soumission.
* Fallback robuste : si l'API CRM ne renvoie pas l'`id` de l'entreprise/du contact qu'elle vient de créer (cas connu lorsque les permissions empêchent la relecture), le plugin relance une recherche par nom/email pour récupérer l'`id` et garantir la liaison.
* Logs plus détaillés (statut HTTP + nom/email) en cas d'échec, pour faciliter le diagnostic dans `wp-content/debug.log`.

= 1.0.1 =
* Le champ « tags » du contact est remplacé par « list_market » : les contacts sont désormais abonnés à de vraies listes marketing du CRM (table `marketing_lists`).
* Création automatique de la liste si elle n'existe pas (insensible à la casse).
* Migration douce : l'ancien réglage « Tags contact par défaut » est repris automatiquement comme « Liste(s) marketing par défaut ».
* Nécessite le patch CRM correspondant : `Contact::update()` gère maintenant `list_market` (en plus de `Contact::create()`).

= 1.0.0 =
* Première version : connexion CF7 ↔ CRM Le Beau Digital (contacts, entreprises, affaires).
* Mapping UI dans l'éditeur CF7 + fallback par préfixe `crm-`.
* Auto-update via GitHub Releases.
