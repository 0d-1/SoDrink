\# SoDrink — Site communautaire privé (PHP + JSON)



SoDrink est un mini-site privé pour organiser des soirées entre amis :

\- comptes utilisateurs (pseudo, profil, avatar),

\- \*\*Prochaine Soirée\*\* (événements gérés par l’admin),

\- \*\*Galerie\*\* (upload d’images par les membres),

\- panneau \*\*Admin\*\* (utilisateurs + activation/ordre des sections).



\## Stack

\- \*\*Backend\*\*: PHP 8.1+ (aucun framework), endpoints en `api/`.

\- \*\*Frontend\*\*: HTML5 + CSS + JavaScript (modules ES).

\- \*\*Stockage\*\*: fichiers JSON en `data/` (via `JsonStore` avec verrous + écriture atomique).



\## Prérequis

\- PHP ≥ 8.1 avec extensions `json`, `fileinfo` (MIME), `mbstring`.

\- Serveur web (Apache/Nginx) pointant sur \*\*`public/`\*\* comme document root.



\## Installation (dev rapide)

```bash

\# Cloner

git clone <repo> sodrink \&\& cd sodrink



\# Copier l'environnement

cp config/.env.example config/.env



\# Dossiers écriture

mkdir -p public/uploads/avatars public/uploads/gallery data

chmod -R 775 public/uploads data



\# Lancer en local (serveur PHP intégré)

php -S 127.0.0.1:8000 -t public

