# Application Android SoDrink

Cette application Compose permet de se connecter au serveur SoDrink et de consulter rapidement la prochaine soirée et les évènements à venir. Elle utilise Retrofit + Kotlin Serialization, gère les cookies de session et récupère automatiquement un token CSRF avant la connexion.

## Démarrage rapide
1. Ouvrir `android-app` dans Android Studio Iguana (ou plus récent).
2. Définir l'URL de base du serveur (ex: `http://10.0.2.2:8000` si le backend tourne en local) dans le champ prévu. L'application ajoute automatiquement le suffixe `/public` pour cibler les API.
3. Saisir le pseudo, le mot de passe et lancer la connexion. Après authentification, la section "Prochaine soirée" et la liste des évènements à venir sont chargées.

### Remarques
- Le dépôt n'inclut pas le binaire `gradle-wrapper.jar` car le téléchargement externe est bloqué dans l'environnement d'exécution. Android Studio le régénère automatiquement via **Gradle > Wrapper** ou en exécutant `gradle wrapper` sur une machine disposant d'Internet.
- Les appels réseau reposent sur les endpoints JSON existants (`/api/csrf.php`, `/api/auth/login.php`, `/api/sections/next-event.php`).
