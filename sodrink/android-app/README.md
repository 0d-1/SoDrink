# Application Android SoDrink

Cette application Compose permet de se connecter au serveur SoDrink et de consulter rapidement la prochaine soirée et les évènements à venir. Elle utilise Retrofit + Kotlin Serialization, gère les cookies de session et récupère automatiquement un token CSRF avant la connexion.

## Démarrage rapide
1. Ouvrir `android-app` dans Android Studio Iguana (ou plus récent).
2. Définir l'URL de base du serveur (ex: `http://10.0.2.2:8000` si le backend tourne en local) dans le champ prévu. L'application ajoute automatiquement le suffixe `/public` pour cibler les API.
3. Saisir le pseudo, le mot de passe et lancer la connexion. Après authentification, la section "Prochaine soirée" et la liste des évènements à venir sont chargées.

## Générer un APK
### Avec Android Studio
1. Menu **Build > Build Bundle(s) / APK(s) > Build APK(s)**.
2. Android Studio place le fichier généré dans `app/build/outputs/apk/debug/app-debug.apk` (signé avec la clé de debug par défaut). 
3. Activez le mode développeur et le débogage USB sur le téléphone, branchez-le puis glissez-déposez l'APK sur l'appareil ou utilisez `adb install app-debug.apk`.

### En ligne de commande
1. Depuis `android-app`, exécuter `./gradlew assembleDebug`.
2. Récupérer `app/build/outputs/apk/debug/app-debug.apk` et l'installer sur le téléphone (`adb install` ou transfert manuel).

### Variante release (signée)
1. Générer un keystore (si besoin) : `keytool -genkeypair -v -storetype PKCS12 -keystore sodrink.keystore -alias sodrink -keyalg RSA -keysize 2048 -validity 10000`.
2. Ajouter les entrées suivantes dans `~/.gradle/gradle.properties` :
   ```
   SODRINK_STORE_FILE=/chemin/vers/sodrink.keystore
   SODRINK_STORE_PASSWORD=mot_de_passe_store
   SODRINK_KEY_ALIAS=sodrink
   SODRINK_KEY_PASSWORD=mot_de_passe_clef
   ```
3. Décommenter/ajouter la configuration de signature correspondante dans `app/build.gradle.kts` si vous personnalisez les chemins, puis lancer `./gradlew assembleRelease`. L'APK se trouvera dans `app/build/outputs/apk/release/app-release.apk`.

### Remarques
- Le dépôt n'inclut pas le binaire `gradle-wrapper.jar` car le téléchargement externe est bloqué dans l'environnement d'exécution. Android Studio le régénère automatiquement via **Gradle > Wrapper** ou en exécutant `gradle wrapper` sur une machine disposant d'Internet.
- Les appels réseau reposent sur les endpoints JSON existants (`/api/csrf.php`, `/api/auth/login.php`, `/api/sections/next-event.php`).
