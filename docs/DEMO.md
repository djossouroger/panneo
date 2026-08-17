# Scénario de démonstration

Durée cible : ~10 minutes. Préparez le backend seedé et le mobile avant de commencer.

## Comptes de démonstration

Le mot de passe commun des comptes démo est défini dans `backend/database/seeders/DemoSeeder.php`
(constante `DEMO_PASSWORD`, non commitée ailleurs).

| Rôle                        | Email                      | Téléphone      | Statut                     |
| --------------------------- | -------------------------- | -------------- | -------------------------- |
| Admin back-office           | `admin.demo@panneo.test`   | +2290100000001 | —                          |
| Client                      | `client.demo@panneo.test`  | +2290100000002 | —                          |
| Artisan **validé**          | `artisan.demo@panneo.test` | +2290100000003 | `verified`                 |
| Artisan **en attente**      | `artisan2.pending@panneo.test` | +2290100000004 | `pending` (à valider)  |

### Comptes par ville

Pour garantir qu'un client trouve toujours un dépanneur **dans sa propre ville**, un client
et **deux artisans validés** (plomberie+électricité+électroménager / climatisation+serrurerie)
sont créés pour chacune des villes suivantes (disponibles 7j/7 24h) :

| Ville       | Client                          | Artisan plomberie                                  | Artisan climatisation                                      |
| ----------- | ------------------------------- | -------------------------------------------------- | ----------------------------------------------------------- |
| Cotonou     | `client.cotonou.demo@panneo.test`   | `artisan.plomberie.cotonou.demo@panneo.test`   | `artisan.climatisation.cotonou.demo@panneo.test`   |
| Akpakpa     | `client.akpakpa.demo@panneo.test`   | `artisan.plomberie.akpakpa.demo@panneo.test`   | `artisan.climatisation.akpakpa.demo@panneo.test`   |
| Calavi      | `client.calavi.demo@panneo.test`    | `artisan.plomberie.calavi.demo@panneo.test`    | `artisan.climatisation.calavi.demo@panneo.test`    |
| Porto-Novo  | `client.porto-novo.demo@panneo.test`| `artisan.plomberie.porto-novo.demo@panneo.test`| `artisan.climatisation.porto-novo.demo@panneo.test`|
| Parakou     | `client.parakou.demo@panneo.test`   | `artisan.plomberie.parakou.demo@panneo.test`   | `artisan.climatisation.parakou.demo@panneo.test`   |
| Ouidah      | `client.ouidah.demo@panneo.test`    | `artisan.plomberie.ouidah.demo@panneo.test`    | `artisan.climatisation.ouidah.demo@panneo.test`    |
| Bohicon     | `client.bohicon.demo@panneo.test`   | `artisan.plomberie.bohicon.demo@panneo.test`   | `artisan.climatisation.bohicon.demo@panneo.test`   |
| Abomey      | `client.abomey.demo@panneo.test`    | `artisan.plomberie.abomey.demo@panneo.test`    | `artisan.climatisation.abomey.demo@panneo.test`    |

Chaque client de ville a une demande `pending` pré-créée
(`PAN-2026-<Ville>-DEMO`) : connectez-vous et la recherche de dépanneurs affiche
immédiatement 1–2 artisans **de la même ville**.

Demandes de démonstration pré-créées pour `client.demo@panneo.test` :

| Référence          | Catégorie    | Statut            | Détail                               |
| ------------------ | ------------ | ----------------- | ------------------------------------ |
| `PAN-2026-DEMO001` | Plomberie    | `pending`         | recherche de dépanneurs disponible   |
| `PAN-2026-DEMO002` | Plomberie    | `awaiting_artisan`| offre en attente pour l'artisan démo |
| `PAN-2026-DEMO003` | Électricité  | `completed`       | intervention terminée + avis 4/5     |

## Préparation

```bash
cd backend
php artisan migrate --seed   # schéma + catégories + démo (idempotent)
php artisan serve --host=0.0.0.0 --port=8001
# autre terminal
cd mobile
npx expo start             # ouvrir l'app (iOS/Android/web)
```

> Les OTP (e-mail/SMS) sont visibles dans `backend/storage/logs/laravel.log` ; avec
> `OTP_DELIVERY=mail`, les codes e-mail arrivent dans la boîte de réception (SMTP Gmail).

## Étapes

### 1. L'artisan doit être validé (3 min)
- Un artisan **en attente** est déjà disponible pour cette démo : `artisan2.pending@panneo.test`
  (électricien, dossier soumis). Vous pouvez aussi créer un **nouveau** compte artisan
  (ex. `jean.artisan@test.com`) : **3 étapes** (profil → activité → identité). À l'étape
  identité, ajoutez une **pièce d'identité** (photo) et un **selfie** (appareil photo) —
  obligatoires ; tentez un fichier non-image → refus.
- Après inscription, son **accueil** affiche l'**écran dédié « Compte en cours de validation »**
  (avec la date de soumission) et le toggle de disponibilité n'apparaît pas.
- Ouvrez le back-office `http://localhost:8001/admin` (admin.demo) → **Vérifications** →
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
