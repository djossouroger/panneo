# Application mobile

Expo SDK 54 · expo-router ~6 · TypeScript strict · Design system maison (`components/ui.tsx`).

## Navigation (arborescence `app/`)

### Racine & onglets
- `_layout.tsx` — session (secure store), splash, redirection (welcome ↔ onglets).
- `(tabs)/_layout.tsx` — barre d'onglets : **Accueil**, **Demandes**, **Profil**.
- `(tabs)/index.tsx` — répartiteur : rend `home/client.tsx` ou `home/artisan.tsx` selon le rôle.
- `home/client.tsx` — tableau de bord client (demandes actives/historique, recherche d'artisan…).
- `home/artisan.tsx` — tableau de bord artisan : disponibilité, intervention en cours,
  nouvelles demandes ; **écrans dédiés 100 %** tant que le compte n'est pas `verified` :
  « Compte en cours de validation » (date de soumission, `Clock3`) ou « Compte non validé »
  (motif du rejet, `CircleAlert`), avec action vers `artisan/verification` et déconnexion.
- `(tabs)/requests.tsx` — **Demandes** : branches client / artisan ; branche artisan affiche
  une notice « en attente de validation » tant que le compte n'est pas `verified`.
- `(tabs)/profile.tsx` — profil : édition, artisan (zones, horaires, absences, portfolio,
  vérification) ou client (favoris, compte).

### Auth / compte
- `welcome.tsx`, `login.tsx`, `signup/role.tsx`, `signup/form.tsx` (étape 1),
  `signup/artisan.tsx` (étape 2 : métier + ville), `signup/identity.tsx` (étape 3 :
  **pièce d'identité + selfie**, création du compte puis envoi du dossier).
- `forgot-password.tsx` (message générique anti-énumération + écran de succès
  « Vérifiez votre boîte mail »), `reset-password.tsx` (« Créer un nouveau mot de passe »,
  succès « Mot de passe modifié »).
- `account.tsx` + `account/email.tsx`, `phone.tsx`, `verify-phone.tsx`, `sessions.tsx`, `delete.tsx`.

### Parcours client
- `repair-request/[id].tsx` (détail + avis), `available-artisans/[id].tsx`,
  `awaiting-response/[id].tsx`, `intervention/[id].tsx` (suivi), `report.tsx` (nouvelle demande),
  `disputes.tsx`, `disputes/new.tsx`, `disputes/[id].tsx`, `favorites.tsx`, `notifications.tsx`,
  `artisan/[id].tsx` (profil public).

### Parcours artisan
- `offer-detail/[id].tsx` (accepter/refuser), `artisan/edit.tsx`, `categories.tsx`, `zones.tsx`,
  `hours.tsx`, `absences.tsx`, `portfolio.tsx`, `verification.tsx` (soumission pièce d'identité +
  **selfie** via caméra, justificatif optionnel, aperçus, remplacement/suppression, statut).

## Client HTTP (`lib/api.ts`)

- `apiRequest<T>(path, options)` : gère base URL, `Authorization`, JSON, enveloppe, erreurs.
- `ApiError` : `status`, `message`, `errors`, `network`.
- `friendlyError(error)` : message lisible (403 → message serveur ; sinon message générique).
- `API_BASE_URL` : `EXPO_PUBLIC_API_URL` sinon IP déduite de `Constants.expoConfig.hostUri`
  (port 8001).
- ~70 fonctions typées par domaine (auth, artisan, repair-requests, offers, review, notifications,
  favorites, disputes, account, password).

## Session (`lib/session.ts`)

- `expo-secure-store` : token + user + prénom mémorisé.
- `session.user.artisan_profile.verification_status` alimenté par `/auth/me` → utilisé pour
  gater l'UI métier artisan.

## Composants (`components/`)

- `ui.tsx` — `ScreenContainer`, `AppButton`, `FormField`, `AppTextInput`, `EmptyState`,
  `Chip`, `Badge`, `AppCard`, `Avatar`, `AppHeader`, `SectionTitle`, `ConfirmSheet`, `Loader`,
  `EmptyState`, `Segment`, `Spacer`, `PickerSheet`, etc.
- `repairRequests.tsx` — cartes `RepairRequestCard` / `InterventionCard` / `IncomingRequestCard`.
- `notifications.tsx` — `NotificationBell`.

## Règles d'implémentation (vérifiées en V1)

- **Garde métier côté mobile** : un artisan non `verified` ne déclenche **pas** d'appels aux
  endpoints gatés (availability, offers, repair-requests) — ils renverraient 403.
  À la place : **écrans dédiés** « Compte en cours de validation » / « Compte non validé »
  sur `home/artisan.tsx` + notice sur `(tabs)/requests.tsx`, bouton vers `artisan/verification`.
- **2e photos** max à la création d'une demande (`expo-image-picker`).
- **OTP** : en dev le SMS est loggé (`storage/logs/laravel.log`) ; l'e-mail suit `OTP_DELIVERY`
  (`log` → laravel.log, `mail` → SMTP Gmail). Les codes de démo se retrouvent dans les logs.
- **Vérification e-mail** : écran `verify-email.tsx` (saisie du code, renvoi 60 s), atteint après
  inscription (client), après inscription artisan (`next=/artisan/verification`), après un login
  `403 EMAIL_NOT_VERIFIED`, ou depuis la garde de `(tabs)/index.tsx` pour une session non vérifiée.

## Scripts de vérification

```bash
npx tsc --noEmit
npx expo export --platform ios   # build statique (Metro)
```

> ⚠️ `npm install` peut échouer avec un `ERESOLVE` préexistant (react-dom@19.2.8 vs react@19.1.0).
> Il n'empêche ni `tsc` ni `expo export` : utiliser `npm install --legacy-peer-deps` si un ajout
> de dépendance est nécessaire.
