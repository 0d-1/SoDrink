# SoDrink

SoDrink est une application web communautaire qui aide un groupe d'amis à organiser leurs soirées, à partager des photos et à suivre qui détient la "torpille" (le gage photo qui passe de main en main). Le projet repose sur un backend PHP léger, un stockage JSON sur disque et une interface en PHP/JavaScript sans build step.

## Sommaire
- [Fonctionnalités](#fonctionnalités)
- [Architecture](#architecture)
- [Prérequis](#prérequis)
- [Installation](#installation)
- [Configuration](#configuration)
- [Lancement en développement](#lancement-en-développement)
- [Structure des données](#structure-des-données)
- [Sécurité](#sécurité)
- [Pistes d'amélioration](#pistes-damélioration)

## Fonctionnalités
- **Agenda des événements** : création, mise à jour et suppression d'événements avec participants, liste des prochains rendez-vous et calcul du prochain événement à venir.【F:sodrink/src/domain/Events.php†L9-L60】
- **Galerie photo** : ajout d'images, likes, commentaires et suppression grâce au dépôt JSON dédié aux médias.【F:sodrink/src/domain/Gallery.php†L9-L64】
- **Notifications** : système de notifications persistantes par utilisateur avec compteur d'éléments non lus et marquage en lecture.【F:sodrink/src/domain/Notifications.php†L9-L56】
- **Gestion des sections de la page d'accueil** : activation/désactivation et tri des blocs (prochain événement, galerie, torpille) à partir d'un fichier JSON modifiable depuis l'interface admin.【F:sodrink/public/index.php†L15-L68】【F:sodrink/src/domain/Sections.php†L9-L48】
- **Jeu de la torpille** : workflow complet pour passer la torpille d'un membre à l'autre avec upload d'une photo estampillée et suivi de l'état courant.【F:sodrink/src/domain/Torpille.php†L9-L112】

## Architecture
- `public/` : points d'entrée HTTP (pages, API RESTful et assets statiques) utilisés par le serveur web.【F:sodrink/public/index.php†L1-L74】
- `views/` : fragments PHP pour l'interface utilisateur (head, header, modales, etc.).【F:sodrink/public/index.php†L11-L14】【F:sodrink/public/index.php†L71-L74】
- `src/` : logique applicative, domaine, sécurité et utilitaires, chargés via l'autoloader défini dans `bootstrap.php`.【F:sodrink/src/bootstrap.php†L6-L25】
- `data/` : persistance JSON (utilisateurs, événements, galerie, notifications, torpille, configuration des sections).【F:sodrink/src/config.php†L32-L49】
- `public/assets/js/` : scripts front-end modulaires pour les différentes pages (app, auth, admin, profil, etc.).【F:sodrink/public/assets/js/admin.js†L1-L9】

## Prérequis
- PHP 8.1 ou plus récent (l'application utilise des fonctions telles que `str_starts_with`, le typage strict et les tableaux typés modernes).【F:sodrink/src/config.php†L5-L24】
- Extension GD activée pour générer les filigranes de la torpille.【F:sodrink/src/domain/Torpille.php†L71-L104】
- Serveur web capable de pointer vers `sodrink/public` (Apache, Nginx ou serveur PHP intégré).【F:sodrink/public/index.php†L1-L74】

## Installation
```bash
# Cloner le dépôt
git clone <url> sodrink
cd sodrink
```
Au premier lancement, le bootstrap crée automatiquement les répertoires `data/` et `public/uploads/...` s'ils n'existent pas déjà.【F:sodrink/src/bootstrap.php†L70-L73】

## Configuration
Les variables d'environnement peuvent être définies via un fichier `sodrink/config/.env` (optionnel). Les clés supportées incluent `APP_ENV`, `APP_TZ` et `MAX_UPLOAD_MB`; elles surchargent les constantes par défaut définies dans `src/config.php`.【F:sodrink/src/config.php†L7-L59】

## Lancement en développement
Pour tester l'application en local, utilisez le serveur PHP intégré :
```bash
php -S localhost:8080 -t sodrink/public
```
Naviguez ensuite sur `http://localhost:8080`. Le bootstrap détecte correctement la base d'URL même si l'app tourne dans un sous-dossier, configure les sessions sécurisées et charge automatiquement les classes.【F:sodrink/src/bootstrap.php†L8-L68】

## Structure des données
Chaque ressource métier est stockée dans un fichier JSON dédié et manipulée via `SoDrink\Storage\JsonStore`, qui gère les verrous et l'écriture atomique pour éviter la corruption des données.【F:sodrink/src/storage/JsonStore.php†L1-L94】 Les fichiers sont créés à la volée lorsqu'ils n'existent pas encore.【F:sodrink/src/storage/JsonStore.php†L12-L23】

## Sécurité
- **Sessions et cookies** : configuration stricte des paramètres de session (`httponly`, `samesite=Strict`) et gestion du cookie "remember me" avec rotation du jeton.【F:sodrink/src/bootstrap.php†L31-L55】【F:sodrink/src/security/auth.php†L49-L121】
- **Protection CSRF** : génération de jetons, extraction depuis les en-têtes, formulaires ou payload JSON et vérification serveur avant chaque mutation.【F:sodrink/src/security/csrf.php†L1-L35】
- **Authentification & rôles** : helpers pour vérifier la connexion, les droits admin et imposer la présence d'une session sur les endpoints sensibles.【F:sodrink/src/security/auth.php†L7-L31】

## Pistes d'amélioration
- Ajouter une suite de tests automatisés (PHPUnit) pour couvrir la logique métier (événements, galerie, torpille).
- Remplacer le stockage fichier par une base de données relationnelle lorsque le nombre d'utilisateurs grandit.
- Introduire une compilation front-end (Vite, Webpack) si l'application gagne en complexité côté client.
- Internationaliser l'interface et le contenu des notifications pour s'adapter à plusieurs langues.
