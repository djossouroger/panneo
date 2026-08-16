# Scénario de démonstration

Durée cible : ~10 minutes. Préparez le backend seedé et le mobile avant de commencer.

## Préparation

```bash
cd backend
php artisan migrate:fresh --seed
php artisan serve          # http://localhost:8000
# autre terminal
cd mobile
npx expo start             # ouvrir l'app (iOS/Android/web)
```

> Les OTP (e-mail/SMS) sont visibles dans `backend/storage/logs/laravel.log` ; avec
> `OTP_DELIVERY=mail`, les codes e-mail arrivent dans la boîte de réception (SMTP Gmail).

## Étapes

### 1. L'artisan doit être validé (3 min)
- Créez un **nouveau** compte artisan (ex. `jean.artisan@test.com`) : **3 étapes** (profil →
  activité → identité). À l'étape identité, ajoutez une **pièce d'identité** (photo) et un
  **selfie** (appareil photo) — obligatoires ; tentez un fichier non-image → refus.
- Après inscription, son **accueil** affiche l'**écran dédié « Compte en cours de validation »**
  (avec la date de soumission) et le toggle de disponibilité n'apparaît pas.
- Ouvrez le back-office `http://localhost:8000/admin` (admin.demo) → **Vérifications** →
  le détail montre la **pièce et le selfie côte à côte** (clic = agrandir) → **« Valider cet
  artisan ? »** (modal).
- Retournez dans le mobile → rafraîchissez : l'écran dédié disparaît, le tableau de bord est
  débloqué. (Rejet : « Refuser le dossier » avec motif → l'artisan voit « Compte non validé ».)

### 2. Le client crée une demande (2 min)
- Connectez `client.demo@panneo.test`.
- **Accueil → Nouvelle demande** : plomberie, Cotonou / Akpakpa, description d'une fuite,
  optionnellement 1 photo.
- Montrez la liste des **artisans disponibles** (l'artisan validé y figure ; l'artisan non
  validé non).
- Envoyez une offre à l'artisan validé.

### 3. L'artisan accepte et intervient (3 min)
- Connectez `artisan.demo@panneo.test` → onglet **Demandes** → « À répondre » (le client est
  masqué) → **Accepter**.
- Le client voit la demande « acceptée » et peut contacter l'artisan (numéro débloqué).
- Artisan → **Démarrer**, puis **Terminer**.
- Le client est notifié à chaque étape (cloche).

### 4. Avis + litige (2 min)
- Client → la demande terminée → **noter** l'artisan (4 étoiles).
- Montrez le litige : client ouvre un litige sur une autre demande acceptée → l'admin le
  résout depuis `/admin/disputes`.

### 5. Sécurité (1 min)
- Client → **Mot de passe oublié** → OTP (logs) → nouveau mot de passe → reconnexion.
- (Option) Liste des sessions + révocation.

## Ce qu'on NE montre pas en V1
- Paiement en ligne (aucun).
- Notifications push (applicatives internes uniquement).
- Reconnaissance faciale automatique (le selfie est comparé **manuellement** par l'admin).
