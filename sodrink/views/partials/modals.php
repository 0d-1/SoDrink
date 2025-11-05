<!-- Modale Connexion -->
<div class="modal" id="modal-login" hidden>
  <div class="modal-content">
    <h3>Connexion</h3>
    <form id="form-login">
      <label>Pseudo
        <input type="text" name="pseudo" required minlength="3" maxlength="20" />
      </label>
      <label>Mot de passe
        <input type="password" name="password" required minlength="8" />
      </label>
      <label class="checkbox-line"><input type="checkbox" name="remember" value="1"> Se souvenir de moi (2 semaines)</label>
      <div class="actions">
        <button type="submit" class="btn btn-primary">Se connecter</button>
        <button type="button" class="btn" data-close>Annuler</button>
      </div>
    </form>

    <!-- (le reste de la modale d'inscription est inchangé) -->
  </div>
</div>


<!-- Modale Inscription -->
<div class="modal" id="modal-register" hidden>
  <div class="modal-content">
    <h3>Créer un compte</h3>
    <form id="form-register">
      <label>Pseudo
        <input type="text" name="pseudo" required minlength="3" maxlength="20" />
      </label>
      <label>Prénom
        <input type="text" name="prenom" required maxlength="40" />
      </label>
      <label>Nom
        <input type="text" name="nom" required maxlength="40" />
      </label>
      <label>Mot de passe
        <input type="password" name="password" required minlength="8" />
      </label>
      <label>Instagram (optionnel)
        <input type="text" name="instagram" placeholder="@pseudo" />
      </label>
      <div class="actions">
        <button type="submit" class="btn btn-primary">S'inscrire</button>
        <button type="button" class="btn" data-close>Annuler</button>
      </div>
    </form>
  </div>
</div>