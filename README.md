<div align="center">
  <img src="always-analytics.svg" alt="Always Analytics Logo" width="80" />
</div>

# Always Analytics
### Le plugin WordPress qui concilie 100% de vos données et 100% du RGPD

[![Version](https://img.shields.io/badge/version-2.6.2-1db954?style=flat-square)](https://github.com/Assistouest/Always-Analytics/releases) [![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-21759b?style=flat-square&logo=wordpress)](https://wordpress.org) [![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4?style=flat-square&logo=php)](https://php.net) [![Privacy](https://img.shields.io/badge/RGPD-conforme-1db954?style=flat-square)](#)

*Gratuit • Open Source • Sans abonnement*

***

Ne perdez plus aucune donnée en attendant le consentement de vos visiteurs. **Always Analytics** est une solution analytique innovante auto hébergée qui capture l'activité pré consentement sans utiliser de cookie, puis la fusionne avec l'identité de l'utilisateur dès son approbation. Un suivi complet, prêt pour le web de demain.

## Fonctionnalités clés

* **0% de perte de données** Capturez chaque visite, que le consentement soit donné, refusé ou ignoré. Un script de secours sécurise même les audiences bloquant le JavaScript.
* **100% Souverain** Vos données restent chez vous, sur votre propre base de données. Aucune fuite vers des tiers.
* **Impact Web Vitals Minimisé (< 5 Ko)** Script asynchrone conçu pour ne pas bloquer le fil d'exécution principal. Préservez votre score LCP.
* **Filtrage Anti Spam Natif** Exclusion automatique des bots connus et referrers spammys via une analyse côté serveur pour des données pures.
* **Conformité CNIL Native** Anonymisation irréversible des adresses IP par design et outil d'audit RGPD intégré.

***

## L'algorithme de collecte résiliente (Tracking Inversé)

La plupart des outils d'analyse conditionnent le suivi au consentement préalable, ce qui ampute souvent les rapports de la moitié du trafic. **Always Analytics fonctionne à l'envers, le suivi sans cookie est la fondation, le cookie n'est qu'une option.**

Peu importe l'action du visiteur face à la bannière, une visite est toujours enregistrée. Le consentement décide uniquement si le visiteur peut être reconnu lors d'une session future.

### Choisissez votre environnement de déploiement

Le plugin offre une flexibilité totale aux développeurs et aux délégués à la protection des données en proposant 3 environnements distincts

1. **Cookieless Strict (Sécurité Maximale)** Aucun cookie. Le visiteur est identifié par un hachage serveur éphémère `SHA256(IP_anon + UA + Accept-Language + Time)`. Sans aucune persistance à long terme, ce mode garantit un respect absolu des recommandations d'exemption de la CNIL.
2. **Mode Hybride (Le compromis parfait, Recommandé)** Fait tourner l'empreinte anonyme en tâche de fond pour le comptage, et ne déploie un cookie persistant (182 jours) qu'en cas d'accord explicite. Les données récoltées avant le consentement sont alors fusionnées.
3. **Mode Développeur (Préproduction)** Force le tracking persistant dès le premier chargement sans bannière pour faciliter les phases de tests techniques.

### Le flux de données (Mode Hybride)

```text
Visiteur arrive
       │
       ▼
[ Hit pre_consent envoyé immédiatement ]
Hash journalier cookieless. Scroll, durée, engagement collectés.
Aucun cookie posé.
       │
       ▼
[ Bannière affichée ]
       │
       ├──── Accepte ──────────────────────────────────────────────────────┐
       │                                                                   │
       ├──── Refuse ────────────────────────────────────────────┐          │
       │                                                        │          │
       └──── Ferme sans répondre ───────────────────────────┐   │          │
                                                            │   │          │
                                                            ▼   ▼          │
                                              Hit pre_consent conservé     │
                                              = visite cookieless complète │
                                                                           ▼
                                                    Cookie visitorId créé (182j)
                                                    Hit complet envoyé
                                                    Pre_consent fusionné (is_superseded=1)
                                                    Identité persistante activée
```

***

### Le blocage actif des cookies

Même si un visiteur accepte la bannière, son navigateur peut bloquer l'écriture du cookie via un mode de navigation privée ou des extensions antipub. Le script de collecte détecte activement ce blocage

```javascript
// Écriture du cookie
setVisitorCookie(candidate);

// Vérification immédiate de persistance
const persisted = getCookie(COOKIE_VID);

if (persisted) {
    return persisted;       // Cookie ok → visitorId stable
}
return null;                // Cookie bloqué → fallback cookieless
```

Si une valeur nulle est retournée, l'événement est envoyé sans identifiant. Le serveur détecte l'absence du cookie et bascule automatiquement sur l'empreinte sans cookie de secours. Vous ne perdez jamais de données.

***

## Décoder les intentions avec l'analyse psychographique

Les données de fréquentation froides ne racontent qu'une fraction de l'histoire. En croisant la profondeur de défilement et la vélocité de lecture, notre moteur d'analyse classe automatiquement chaque session pour révéler la véritable intention humaine

| Profil | Comportement | Interprétation |
| :--- | :--- | :--- |
| **Zappeur** *(0 à 20%)* | Taux de rebond très élevé. | Contenu non engageant ou ciblage publicitaire inadéquat. |
| **Curieux** *(20 à 74%)* | Lecture partielle. | Parcourt la page pour se faire une idée rapide sans engagement profond. |
| **Compulsif** *(100% rapide)* | Défilement fulgurant jusqu'en bas. | Recherche d'une information précise. Utilisateur en phase décisionnelle. |
| **Super Lecteur** *(75 à 100%)* | Lecture complète et soutenue. | Segment pleinement capté. Indique une excellente adéquation avec les attentes. |

***

## La fin des mirages statistiques (Algorithme de Wilson)

Les outils classiques s'appuient sur des moyennes brutes, ignorant la taille de l'échantillon. En conséquence, une page consultée une seule fois pendant 10 minutes dépassera un guide lu par 5 000 personnes. 

Pour séparer les anomalies des vrais succès, Always Analytics applique la limite inférieure de l'intervalle de confiance de Wilson. Moins vous avez de trafic sur une page, plus la formule applique un malus de prudence sévère. La fiabilité se prouve par le volume.

$$S_{final} = \frac{P + \frac{z^2}{2n} - z \sqrt{\frac{P(1-P)}{n} + \frac{z^2}{4n^2}}}{1 + \frac{z^2}{n}}$$

*(Où **P** est la performance brute pondérée, **n** le nombre de sessions, et **z** le niveau de confiance).*

| Page | Sessions | Engagement Brut | Score Wilson | État |
| :--- | :---: | :---: | :---: | :--- |
| **Guide Pilier Sécurité** | 1 000 | 80% | **78.2** | Fiable |
| **Optimisation PHP** | 500 | 75% | **71.4** | Solide |
| **Mettre à jour Ubuntu...** | 1 | 100% | **12.5** | Instable |

***

## Données collectées

Une vision globale optimisée pour l'acquisition et la conversion

* **Contenu** URL, Titre, Identifiant WordPress (Post ID).
* **Acquisition** Référent (Source) et paramètres UTM.
* **Comportement** Durée de session, Temps d'engagement réel (onglet actif), Profondeur de scroll (25%, 50%, 75%, 100%), Sessions multi pages.
* **Technique** Type d’appareil, Navigateur, OS, Résolution d’écran.
* **Audience** Géolocalisation (Pays et Région), Nouvel utilisateur du jour, Statut de connexion WordPress.

*(Information importante, en mode cookieless strict, la rétention entre les jours est techniquement impossible, le hash de protection tournant automatiquement pour protéger la vie privée).*

***

<div align="center">
  <p><b>Développé par Adrien pour la communauté WordPress.</b></p>
  <a href="https://buymeacoffee.com/assistouest" target="_blank"><img src="https://cdn.buymeacoffee.com/buttons/v2/default-yellow.png" alt="Buy Me A Coffee" style="height: 60px !important;width: 217px !important;" ></a>
</div>
