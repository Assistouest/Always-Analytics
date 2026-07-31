<div align="center">
  <img src="always-analytics.svg" alt="Logo Always Analytics" width="88" height="88">

  <h1>Always Analytics</h1>

  <p><strong>L’analytics WordPress auto-hébergé, cookieless par défaut.</strong></p>
  <p>Mesurez vos visites, vos sources d’acquisition, l’engagement de vos lecteurs et les clics sur vos liens directement depuis WordPress.</p>

  <p>
    <a href="https://github.com/Assistouest/Always-Analytics/releases"><img src="https://img.shields.io/badge/version-3.6.3-1f6feb?style=flat-square" alt="Version 3.6.3"></a>
    <img src="https://img.shields.io/badge/WordPress-5.8%2B-21759b?style=flat-square&logo=wordpress" alt="WordPress 5.8 ou supérieur">
    <img src="https://img.shields.io/badge/PHP-7.4%2B-777bb4?style=flat-square&logo=php" alt="PHP 7.4 ou supérieur">
    <img src="https://img.shields.io/badge/licence-GPL--2.0--or--later-2ea44f?style=flat-square" alt="Licence GPL 2.0 ou ultérieure">
    <img src="https://img.shields.io/badge/télémétrie-aucune-2ea44f?style=flat-square" alt="Aucune télémétrie">
  </p>

  <p><em>Gratuit · Open source · Sans compte externe · Sans abonnement</em></p>
</div>

---

## Comprendre votre audience sans céder vos données

Always Analytics est une extension de mesure d’audience conçue exclusivement pour WordPress.

Les données sont enregistrées dans des tables dédiées de votre propre base de données. Aucun compte SaaS n’est requis et aucune donnée analytique n’est transmise à l’auteur du plugin.

Le plugin privilégie une collecte locale et limitée, tout en fournissant les indicateurs réellement utiles pour piloter un site éditorial, professionnel ou communautaire.

| Données maîtrisées                                            | Rapports exploitables                                      | Collecte configurable                                              |
| ------------------------------------------------------------- | ---------------------------------------------------------- | ------------------------------------------------------------------ |
| Stockage dans WordPress, sans plateforme d’analytics distante | Acquisition, contenus, engagement, appareils et clics      | Mode cookieless par défaut ou cookie first-party après acceptation |
| Aucun SDK publicitaire ni télémétrie                          | Regroupement des moteurs, réseaux sociaux et assistants IA | Rétention, exclusions, proxy et suppression à la désinstallation   |
| Ressources d’exécution servies localement                     | Comparaison des périodes et annotations d’événements       | Filtrage local des robots et du spam référent                      |

## Fonctionnalités

### Tableau de bord WordPress

* visiteurs uniques estimés ;
* pages vues et sessions ;
* durée moyenne et taux d’engagement ;
* évolution sur la période sélectionnée ;
* graphique des visites avec comparaison des visiteurs, pages vues et sessions ;
* visiteurs récents et détail des pages consultées ;
* annotations d’événements pour relier une campagne, une publication ou une modification du site à l’évolution du trafic.

### Acquisition et sources de trafic

* référents externes et trafic direct ;
* paramètres `utm_source`, `utm_medium` et `utm_campaign` ;
* regroupement serveur des variantes d’une même source ;
* distinction entre moteurs de recherche, réseaux sociaux, sites référents et assistants IA ;
* exclusion des auto-référents provenant du site lui-même ;
* recalcul des sessions et visiteurs uniques après regroupement des sources.

Always Analytics reconnaît notamment les visites provenant de ChatGPT, Claude, Gemini, Perplexity, Copilot et d’autres assistants lorsque le navigateur transmet un référent exploitable.

### Contenus et engagement

* contenus les plus consultés ;
* temps d’engagement actif ;
* profondeur de défilement à `10 %`, `25 %`, `50 %`, `75 %` et `100 %` ;
* nombre de pages par session ;
* visiteurs revenant sur un contenu ;
* profils de lecture descriptifs ;
* score d’engagement ajusté par l’intervalle de Wilson.

Les profils de lecture servent à résumer un comportement observé dans une session. Ils ne constituent ni un profil psychologique, ni une déduction sur la personnalité du visiteur.

Le score Wilson réduit l’influence des petits échantillons. Une page très performante sur une seule session ne sera donc pas automatiquement classée devant un contenu soutenu par un volume de données plus fiable.

### Appareils et environnement technique

* ordinateur, mobile ou tablette ;
* navigateur et version ;
* système d’exploitation et version ;
* résolution d’écran ;
* répartition des appareils sur la période analysée.

Always Analytics ne réalise pas de géolocalisation des visiteurs.

### Suivi des liens

* clics sur les liens internes ;
* clics vers les domaines externes ;
* nombre total de clics ;
* liens et domaines les plus sollicités.

### Exports et gestion des données

* export CSV ou JSON ;
* protection des exports CSV contre l’injection de formules ;
* rétention configurable de `30` à `395` jours ;
* agrégation quotidienne des données historiques ;
* dissociation des identifiants visiteurs et des comptes WordPress après la période de rétention ;
* suppression facultative de toutes les données lors de la désinstallation.

## Deux modes de collecte

### Mode cookieless — recommandé

Le mode cookieless ne crée pas de cookie persistant destiné à reconnaître un visiteur.

Pour estimer les visiteurs uniques, le serveur génère un identifiant haché à durée courte à partir de signaux techniques limités. L’adresse IP est tronquée avant cette opération : le dernier octet est supprimé en IPv4 et les 80 derniers bits sont supprimés en IPv6.

Deux fenêtres sont disponibles :

* **rotation quotidienne** pour estimer les visiteurs uniques au cours d’une journée UTC ;
* **session du navigateur** pour renforcer la non-corrélation entre les visites.

Dans ce mode :

* aucun identifiant visiteur persistant n’est écrit dans le navigateur ;
* l’anonymisation de l’adresse IP est imposée par le plugin ;
* les statistiques ne sont pas associées au compte WordPress connecté ;
* la reconnaissance d’un même visiteur entre plusieurs jours n’est pas possible de manière fiable.

### Mode cookie first-party — avancé

Ce mode ajoute un identifiant persistant propriétaire après l’acceptation du visiteur.

Avant sa décision, en cas de refus ou lorsque le navigateur bloque le cookie, la collecte reste cookieless. Après acceptation, le plugin peut rattacher la session courante à l’identifiant persistant et reconnaître plus précisément les visites ultérieures.

Ce mode active automatiquement les contrôles d’acceptation et de refus fournis par Always Analytics. Il peut également enregistrer le statut de connexion et l’identifiant du compte WordPress lorsque le visiteur est connecté.

Utilisez ce mode uniquement après avoir vérifié que votre information des visiteurs, votre base légale, votre durée de conservation et votre mécanisme de consentement correspondent réellement à votre usage.

> **Important**
>
> « Cookieless » ne signifie pas automatiquement « anonyme », « exempté de consentement » ou « conforme au RGPD ». La conformité dépend notamment de la finalité, de la configuration, des informations fournies aux personnes et du contexte juridique du site. Always Analytics n’est ni certifié ni validé par la CNIL.
>
> Référence : [solutions de mesure d’audience et conditions d’exemption publiées par la CNIL](https://www.cnil.fr/fr/cookies-solutions-pour-les-outils-de-mesure-daudience).

## Cycle de vie des données

```text
Navigateur
   │
   │  Script local différé
   ▼
API REST WordPress
   │
   ├── Validation de l’URL et de la requête
   ├── Limitation du débit
   ├── Filtrage local des robots
   └── Normalisation des données
   │
   ▼
Tables Always Analytics
   │
   ├── Rapports dans l’administration
   ├── Agrégats quotidiens
   ├── Export CSV ou JSON
   └── Dissociation des anciennes données
```

À l’expiration de la durée de rétention configurée, Always Analytics conserve les agrégats nécessaires aux tendances historiques, mais rompt les liens durables entre les anciennes lignes détaillées, les visiteurs et les comptes WordPress. Le référent complet est également réduit à son domaine lorsqu’il est disponible.

La suppression complète à la désinstallation est volontairement désactivée par défaut afin d’éviter une perte accidentelle. Elle peut être activée dans les réglages avant de supprimer l’extension.

## Filtrage des robots

Le collecteur utilise un score composé de plusieurs signaux plutôt qu’une règle unique :

* cohérence de l’agent utilisateur ;
* en-têtes HTTP et Fetch Metadata ;
* origine de la requête ;
* preuve JavaScript légère ;
* signaux d’automatisation et d’environnement headless ;
* plages d’adresses de centres de données fournies localement ;
* référents indésirables et paramètres suspects ;
* fréquence et comportement récent des sessions.

Aucune blocklist externe n’est interrogée pendant la collecte. Comme tout système de filtrage, cette détection réduit le bruit sans prétendre identifier parfaitement tous les robots.

## Performance et dépendances

* script de collecte différé ;
* aucune dépendance JavaScript front-end ;
* requêtes envoyées avec `fetch()` ou `sendBeacon()` lors de la fermeture de page ;
* cache configurable pour les rapports d’administration ;
* agrégation et nettoyage planifiés avec WP-Cron ;
* chargement des ressources d’administration uniquement sur les écrans concernés ;
* Chart.js fourni localement avec le plugin.

En fonctionnement normal, Always Analytics ne contacte aucun service externe d’analytics, de publicité, de télémétrie, de police ou de géolocalisation.

### Favicons externes facultatifs

L’affichage des favicons de sites référents, navigateurs et systèmes d’exploitation peut utiliser Google S2 Favicons. Cette option est désactivée par défaut.

Lorsqu’elle est activée, le navigateur de l’administrateur consulte directement les serveurs de Google depuis les écrans Always Analytics. Les données analytiques enregistrées dans WordPress ne sont pas envoyées à Google par le plugin.

## Données enregistrées

| Catégorie   | Données principales                                                                                  |
| ----------- | ---------------------------------------------------------------------------------------------------- |
| Contenu     | URL, titre, identifiant WordPress et type de contenu                                                 |
| Acquisition | référent, domaine référent et paramètres UTM                                                         |
| Session     | identifiant de session, page d’entrée, page de sortie, durée et nombre de pages                      |
| Engagement  | temps actif et profondeur maximale de défilement                                                     |
| Technique   | appareil, navigateur, système d’exploitation et résolution d’écran                                   |
| WordPress   | statut connecté et identifiant utilisateur uniquement lorsque le mode cookie est effectivement actif |
| Liens       | URL de la page, URL cliquée et type de lien interne ou sortant                                       |

Les adresses IP brutes ne sont pas stockées dans les tables analytiques. Elles peuvent être tronquées puis utilisées en mémoire pour produire l’identifiant haché du mode cookieless et appliquer les exclusions ou le filtrage configurés.

## Shortcodes

### Résumé d’audience

```text
[always_analytics_visitors]
```

Affiche le nombre de pages vues des sept derniers jours ainsi que la principale source classique et la principale source IA disponibles.

### Contenus les plus engageants

```text
[always_analytics_popular_posts limit="5" days="30" show_reading_time="yes"]
```

Paramètres :

| Attribut            | Valeur par défaut | Description                                   |
| ------------------- | ----------------: | --------------------------------------------- |
| `limit`             |               `5` | nombre de contenus affichés, de 1 à 20        |
| `days`              |              `30` | période analysée, de 1 à 365 jours            |
| `show_reading_time` |             `yes` | affiche ou masque le temps d’engagement moyen |

Le classement combine plusieurs signaux d’engagement et applique un ajustement Wilson. Un minimum de cinq sessions est requis avant qu’un contenu puisse être classé.

## Installation

1. Téléchargez la dernière archive depuis les [versions GitHub](https://github.com/Assistouest/Always-Analytics/releases).
2. Dans WordPress, ouvrez **Extensions → Ajouter une extension → Téléverser une extension**.
3. Installez l’archive ZIP puis activez Always Analytics.
4. Ouvrez **Always Analytics → Réglages**.
5. Vérifiez le mode de collecte, les rôles et adresses IP exclus, la durée de rétention et la configuration de votre proxy éventuel.

La collecte cookieless est le profil activé par défaut. Les administrateurs WordPress sont exclus par défaut des statistiques.

## Prérequis

| Composant       |                                            Version minimale |
| --------------- | ----------------------------------------------------------: |
| WordPress       |                                                       `5.8` |
| PHP             |                                                       `7.4` |
| Base de données | compatible avec les versions prises en charge par WordPress |

Version testée déclarée : WordPress `7.0`.

## Architecture technique

Le plugin repose sur des composants WordPress standards :

* classes PHP sous l’espace de noms `Always_Analytics` ;
* routes REST publiques limitées au collecteur et routes de rapport réservées aux administrateurs ;
* contrôle de capacité `manage_options` pour les rapports et réglages ;
* tables dédiées pour les hits, sessions, agrégats, profondeurs de lecture, événements et clics ;
* tâches WP-Cron pour l’expiration des sessions, l’agrégation et la rétention ;
* transients WordPress pour le cache des rapports et des shortcodes ;
* assets locaux et traductions WordPress.

Les données de centres de données sont dérivées du projet `cloud-provider-ip-addresses` sous licence CC0. Chart.js est distribué sous licence MIT. Les mentions complètes figurent dans `THIRD-PARTY-NOTICES.txt`.

## Limites connues

* le suivi courant nécessite JavaScript ;
* les navigateurs, bloqueurs et politiques de sécurité peuvent empêcher certaines requêtes ;
* un référent supprimé par le navigateur ne peut pas être reconstitué ;
* les visiteurs uniques cookieless sont des estimations limitées à la fenêtre choisie ;
* le plugin ne fournit pas d’attribution publicitaire multi-appareils ;
* il ne réalise pas de géolocalisation ;
* il ne remplace pas une analyse juridique de votre traitement de données.

Les rapports peuvent encore signaler une ancienne source « sans JavaScript » lorsqu’une installation contient des données héritées d’une version antérieure. La version actuelle ne crée plus de nouveau hit `noscript`.

## Questions fréquentes

### Les données sont-elles envoyées à l’auteur du plugin ?

Non. Always Analytics ne contient pas de télémétrie d’usage et ne nécessite aucune connexion à un compte externe. Les données analytiques restent dans la base WordPress du site.

### Le mode cookieless reconnaît-il un visiteur pendant plusieurs jours ?

Non. Selon le réglage choisi, l’identifiant est limité à une journée UTC ou à la session du navigateur. Cette limite est volontaire.

### Le plugin remplace-t-il Google Analytics ?

Il peut le remplacer pour de nombreux besoins de mesure d’audience éditoriale et de compréhension des contenus. Il ne cherche pas à reproduire l’attribution publicitaire, les audiences intersites ou l’écosystème marketing de Google Analytics.

### Les données sont-elles supprimées après la rétention ?

Les anciennes données sont d’abord agrégées puis dissociées des visiteurs et comptes WordPress. Elles ne sont pas toutes supprimées. La suppression totale intervient uniquement lorsque l’option correspondante est activée avant la désinstallation.

### Puis-je exclure mon équipe ?

Oui. Vous pouvez exclure des rôles WordPress, des adresses IP ou des plages CIDR. Le rôle administrateur est exclu par défaut.

### Le plugin fonctionne-t-il derrière un reverse proxy ?

Oui, à condition de déclarer explicitement les adresses ou plages de proxies de confiance. Les en-têtes transférés ne sont pas approuvés par défaut.

## Développement et contribution

Les rapports de bugs et les propositions d’amélioration peuvent être ouverts dans les [issues GitHub](https://github.com/Assistouest/Always-Analytics/issues).

Avant de proposer une modification :

* conservez la compatibilité avec WordPress `5.8` et PHP `7.4` ;
* utilisez les API WordPress lorsque cela est possible ;
* validez, assainissez et échappez les données selon leur contexte ;
* évitez les services distants obligatoires ;
* documentez toute nouvelle donnée collectée ou dépendance tierce.

Consultez les [releases](https://github.com/Assistouest/Always-Analytics/releases) pour l’historique des versions.

## Soutenir le projet

Always Analytics est développé et maintenu indépendamment par Adrien Piron.

Le code est gratuit et restera disponible sous licence libre. Vous pouvez soutenir sa maintenance en [offrant un café](https://buymeacoffee.com/assistouest).

## Licence

Always Analytics est distribué sous licence [GPL-2.0-or-later](LICENSE).

Les composants tiers conservent leurs licences respectives, détaillées dans `THIRD-PARTY-NOTICES.txt`.
