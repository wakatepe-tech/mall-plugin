# Mall Settings

**Version :** 1.4.0  
**Auteur :** Placeloop  

## Description

Mall Settings est un plugin personnalisé pour WordPress qui affiche les horaires d'ouverture des boutiques et du centre commercial. Le plugin s'appuie sur Advanced Custom Fields (ACF) pour récupérer les horaires configurés. Il propose plusieurs shortcodes pour afficher soit un résumé (message) soit une liste détaillée des horaires.

## Fonctionnalités

- Affichage des horaires d'ouverture avec deux modes d'affichage :
  - **Liste détaillée** (via les templates `mall` et `shop`)
  - **Message résumé** (via les templates `mall_short` et `shop_short`)
- Prise en charge des plages horaires multiples (ex. : matin et après-midi) et gestion spéciale du cas "00:00" pour représenter la fermeture à minuit (le jour suivant).
- Deux shortcodes principaux :
  - `[schedules template="..."]` : affiche les horaires d'ouverture.
  - `[offer_shop]` : affiche les informations de la boutique associée à l'offre.
- Utilisation des fonctions natives de WordPress pour l'enregistrement des shortcodes et le chargement des styles.
- Compatibilité avec PHP 8.2 et WordPress 6.7.2 (ou ultérieur).

## Installation

1. Téléchargez le plugin et placez le dossier `mall-plugin` dans le répertoire `wp-content/plugins/` de votre installation WordPress.
2. Dans l'admin WordPress, rendez-vous dans la section **Extensions** et activez **Mall Settings**.
3. Assurez-vous que le plugin Advanced Custom Fields (ACF) est installé et activé, car Mall Settings en dépend pour récupérer les horaires.

## Utilisation

### Affichage des horaires

Utilisez le shortcode `[schedules]` avec l'attribut `template` pour choisir le mode d'affichage :

- **mall** : Affiche les horaires détaillés du centre commercial.
- **mall_short** : Affiche uniquement le message résumé des horaires du centre commercial.
- **shop** : Affiche les horaires détaillés de la boutique.
- **shop_short** : Affiche uniquement le message résumé des horaires de la boutique.

**Exemples :**

[schedules template="mall"]
[schedules template="mall_short"]
[schedules template="shop"]
[schedules template="shop_short"]

### Affichage de la boutique associée à l'offre

Utilisez le shortcode `[offer_shop]` pour afficher les informations de la boutique liée à l'offre (nom, logo et titre de l'offre).

**Exemple :**

[offer_shop]


## Structure des fichiers

- **plugin.php**  
  Le point d'entrée du plugin qui inclut les classes et instancie les objets.

- **schedules.php**  
  Contient la classe `Schedules` qui gère la récupération, le traitement et l'affichage des horaires.

- **offerShop.php**  
  Contient la classe `OfferShop` qui gère l'affichage des informations de la boutique associée à une offre.

- **css/**  
  Contient les fichiers CSS utilisés par le plugin (ex. `schedules.css`).

## Exigences

- **PHP :** 8.2 ou ultérieur  
- **WordPress :** 6.7.2 ou ultérieur  
- **Advanced Custom Fields :** Plugin ACF doit être installé et activé.

## Licence

Ce plugin est sous licence GPL v2 ou ultérieure.

## Remarques

- Le plugin ne gère pas la mise en cache ni la traduction (la gestion de la langue se limite à la configuration de la locale via `setlocale`).
- La gestion des horaires (par exemple, le traitement de l'heure "00:00") est intégrée dans la fonction `parseDateTime`.

## Historique des versions

- **1.4.0**  
  - Séparation des fonctionnalités d'affichage des horaires et d'affichage des informations de la boutique.
  - Optimisation des appels DateTime et centralisation de la configuration (locale et fuseau horaire).
  - Nettoyage du code et suppression de fonctions inutiles.
  - Amélioration de la gestion des horaires via ACF.