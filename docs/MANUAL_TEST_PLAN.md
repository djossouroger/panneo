# Plan de test manuel

Prérequis : backend lancé (`php artisan serve`), base seedée (`migrate:fresh --seed`),
mobile lancé (`npx expo start`). Codes OTP : `OTP_DELIVERY=log` → `backend/storage/logs/laravel.log` ;
`OTP_DELIVERY=mail` → dans la boîte e-mail (SMTP Gmail).

Comptes : `artisan.demo@panneo.test`, `client.demo@panneo.test`, `admin.demo@panneo.test`
(mot de passe commun défini dans `backend/database/seeders/DemoSeeder.php`).

## 1. Auth & compte

- [ ] Inscription client → écran « Vérifiez votre adresse e-mail » → saisie du code → accès à l'app.
- [ ] Inscription artisan → dossier d'identité envoyé puis écran de vérification e-mail (`next=/artisan/verification`).
- [ ] Login d'un compte **non vérifié** → message « Vérifiez votre adresse e-mail » → redirection
      vers la vérification e-mail ; renvoi du code limité (60 s) ; après confirmation, connexion OK.
- [ ] Inscription admin refusée.
- [ ] Login mauvais mot de passe → message clair ; 5 essais → 429.
- [ ] **Mot de passe oublié** : `forgot-password` → bouton « Continuer » → message générique
      « Si un compte correspond… » → écran de succès « Vérifiez votre boîte mail » → saisir le
      code OTP (logs e-mail) sur `reset-password` (« Créer un nouveau mot de passe ») → succès
      « Mot de passe modifié » → connexion avec le nouveau mot de passe.
- [ ] Anti-énumération : sur `reset-password`, un e-mail inexistant renvoie le même message
      « Ce code est invalide ou a expiré. » (aucune distinction « e-mail introuvable »).
- [ ] Déconnexion → token révoqué.
- [ ] Changement d'e-mail avec OTP vers la nouvelle adresse ; idem téléphone.
- [ ] Vérification téléphone (OTP) ; renvoi limité.
- [ ] Sessions listées, révoquer les autres (mot de passe requis).
- [ ] Suppression de compte (mot de passe requis).

## 2. Validation artisan (fonctionnalité clé)

- [ ] Inscription artisan **en 3 étapes** (profil → activité → identité) : la pièce d'identité
      et le selfie sont **obligatoires** (photos via galerie/appareil photo) ; un fichier non
      image ou > 5 Mo est refusé ; le compte est créé `pending`.
- [ ] Nouvel artisan `pending` : **écran dédié** « Compte en cours de validation » (avec date de
      soumission) sur l'accueil, notice sur l'onglet Demandes ; le toggle de disponibilité n'est
      pas affiché.
- [ ] Artisan `pending` : **aucun** appel aux offres/interventions (pas de 403 à l'écran).
- [ ] Artisan `pending` peut quand même éditer son profil, ses horaires, zones, portfolio.
- [ ] Admin : `admin/verifications` (colonnes téléphone + date d'inscription) → le détail affiche
      **pièce + selfie côte à côte** (clic = agrandir) → « Valider cet artisan ? » (modal) →
      l'artisan passe `verified` (notification « Votre compte a été validé »).
- [ ] Après rafraîchissement mobile, l'écran dédié disparaît, le tableau de bord s'affiche.
- [ ] Admin : « Refuser le dossier » avec motif → l'artisan voit l'**écran dédié « Compte non
      validé »** avec le motif ; peut soumettre une nouvelle demande.

## 3. Matching & disponibilité

- [ ] Demande créée dans la zone/horaire de l'artisan démo (Cotonou, 8h–18h) → l'artisan
      apparaît dans `available-artisans`.
- [ ] Hors horaires / hors zone → absent.
- [ ] Artisan indisponible (toggle off) → absent.
- [ ] Artisan avec intervention en cours → absent ; toggle refusé pendant intervention.
- [ ] Artisan `pending`/`rejected` → jamais proposé.

## 4. Cycle complet client ↔ artisan

1. Client crée une demande (plomberie, Cotonou/Akpakpa, photo facultative).
2. Client consulte les artisans disponibles → envoie une offre à l'artisan démo.
3. Artisan (connecté) voit la nouvelle demande dans « À répondre » ; le client est masqué.
4. Artisan accepte → contacts visibles ; client notifié ; demande passe `accepted`.
5. Artisan démarre l'intervention → `in_progress` (client notifié).
6. Artisan termine → `completed`, redevient disponible.
7. Client note 1–5 (une fois) → l'artisan voit la note.
8. Annulation : client annule une demande `pending` → offres annulées.

## 5. Litiges

- [ ] Client ouvre un litige sur une demande `accepted` ; artisan sur `completed`.
- [ ] Non-participant ne voit pas le litige.
- [ ] Admin met à jour le statut → notifications aux deux.

## 6. Back-office

- [ ] Dashboard affiche les KPIs.
- [ ] `users` : désactiver un compte → l'utilisateur est bloqué sur l'API.
- [ ] `repair-requests` : liste + détail + historique de matching.
- [ ] `verifications` : images pièce + selfie côte à côte, agrandissement, téléchargement,
      valider (modal) / refuser avec motif / rouvrir.
- [ ] `disputes`, `categories` (édition prix), `reviews`.

## 7. Régression mobile

- [ ] `npx tsc --noEmit` sans erreur.
- [ ] `npx expo export --platform ios` aboutit.
- [ ] Backend : `php artisan test` → 146 tests verts.

## Parcours non régressifs (fonctionnalités existantes conservées)

- Favoris (client après intervention terminée), profil public artisan, portfolio,
  absences, notifications (cloche + compteur), photos de profil.
