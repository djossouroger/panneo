# API — Référence `/api/v1`

- Toutes les routes sont préfixées `/api/v1`.
- Authentification : header `Authorization: Bearer <token>` (Sanctum) pour les routes protégées.
- Enveloppe : `{ success: bool, data: …, message?, errors? }`.
- Format dates : ISO 8601. Réponse standard : `200/201`, erreurs `401/403/404/409/422/429`.

## Publiques

| Méthode | Route | Description |
| ------- | ----- | ----------- |
| `POST` | `/auth/register` | Inscription client ou artisan (créé le profil artisan si besoin, envoie un code de confirmation e-mail) |
| `POST` | `/auth/login` | Connexion (throttle 5/1 min) → `{ token, user }` ; `403 EMAIL_NOT_VERIFIED` si l'e-mail n'est pas confirmé |
| `POST` | `/auth/forgot-password` | Envoie un OTP e-mail si le compte existe (throttle 5/1 min) |
| `POST` | `/auth/password/reset` | Réinitialise le mot de passe avec l'OTP (throttle 5/1 min) |
| `POST` | `/auth/email-verify/send` | Envoie/renvoie l'OTP de vérification e-mail, réponse neutre si le compte n'existe pas (throttle 3/15 min) |
| `POST` | `/auth/email-verify/confirm` | Confirme l'e-mail avec l'OTP, active le compte (throttle 5/1 min) |
| `GET` | `/categories` | Liste des catégories actives |
| `GET` | `/artisans/{artisan}` | Profil public d'un artisan (actif et validé) |
| `GET` | `/health` | `{ status: 'ok' }` |

## Authentifié (tout rôle) — `auth:sanctum` + `active`

| Méthode | Route | Description |
| ------- | ----- | ----------- |
| `POST` | `/auth/logout` | Révoque le token courant |
| `GET` | `/auth/me` | Profil courant (inclut `artisan_profile`) |
| `GET` | `/notifications` | Notifications paginées |
| `GET` | `/notifications/unread-count` | Nombre de non-lues |
| `PATCH` | `/notifications/{notification}/read` | Marque une notification lue |
| `PATCH` | `/notifications/read-all` | Marque tout lu |
| `POST` | `/auth/phone/send-code` | Envoie OTP de vérification téléphone (throttle 3/15 min) |
| `POST` | `/auth/phone/resend` | Renvoi (throttle 3/15 min) |
| `POST` | `/auth/phone/verify` | Valide le téléphone avec l'OTP |
| `GET` | `/account/sessions` | Sessions actives |
| `DELETE` | `/account/sessions/{session}` | Révoque une session |
| `POST` | `/account/sessions/others` | Révoque les autres sessions (mot de passe requis) |
| `PUT` | `/account/profile` | Modifie le nom |
| `POST` | `/account/profile-photo` | Photo de profil |
| `POST` | `/account/email/send-code` | OTP pour changement d'e-mail (throttle 3/15 min) |
| `POST` | `/account/email` | Confirme le changement d'e-mail |
| `POST` | `/account/phone/send-code` | OTP pour changement de téléphone (throttle 3/15 min) |
| `POST` | `/account/phone` | Confirme le changement de téléphone |
| `POST` | `/account/delete` | Supprime le compte (mot de passe requis) |
| `GET` | `/disputes` | Litiges des demandes de l'utilisateur |
| `GET` | `/disputes/{dispute}` | Détail d'un litige |
| `POST` | `/repair-requests/{repairRequest}/disputes` | Ouvre un litige |

## Client — `role:client`

| Méthode | Route | Description |
| ------- | ----- | ----------- |
| `GET` | `/repair-requests` | Demandes du client (paginations par statut) |
| `POST` | `/repair-requests` | Crée une demande (catégorie, description, adresse, 0–2 photos) |
| `GET` | `/repair-requests/{repairRequest}` | Détail (réservé au propriétaire) |
| `GET` | `/repair-requests/{repairRequest}/available-artisans` | Artisans compatibles pour la demande |
| `POST` | `/repair-requests/{repairRequest}/offers` | Envoie une offre à un artisan compatible |
| `POST` | `/repair-requests/{repairRequest}/review` | Note l'artisan (1–5, une fois) |
| `GET` | `/repair-requests/{repairRequest}/review` | Avis existant (ou `null`) |
| `PATCH` | `/repair-requests/{repairRequest}/cancel` | Annule une demande `pending` |
| `GET` | `/favorites` | Artisans favoris |
| `POST` | `/artisans/{artisan}/favorite` | Toggle favori (après intervention terminée) |
| `GET` | `/artisans/{artisan}/favorite` | Statut favori |

## Artisan — `role:artisan`

### Profil (accessible même non validé)

| Méthode | Route | Description |
| ------- | ----- | ----------- |
| `GET` | `/artisan/profile` | Profil complet (catégories, zones, horaires, portfolio, stats) |
| `PUT` | `/artisan/profile` | Description, expérience, spécialités |
| `POST` | `/artisan/profile-photo` | Photo de profil |
| `PUT` | `/artisan/categories` | Catégories (primaire + secondaires) |
| `PUT` | `/artisan/service-areas` | Zones d'intervention |
| `PUT` | `/artisan/working-hours` | Horaires hebdomadaires |
| `GET` | `/artisan/unavailabilities` | Indisponibilités |
| `POST` | `/artisan/unavailabilities` | Crée une indisponibilité |
| `DELETE` | `/artisan/unavailabilities/{unavailability}` | Annule une indisponibilité |
| `GET` | `/artisan/portfolio` | Réalisations |
| `POST` | `/artisan/portfolio` | Ajoute une photo |
| `DELETE` | `/artisan/portfolio/{item}` | Supprime une photo |
| `GET` | `/artisan/verification` | Statut de vérification + soumission courante |
| `POST` | `/artisan/verification` | Soumet le dossier : **pièce d'identité + selfie obligatoires** (images JPG/PNG/WEBP ≤ 5 Mo), justificatif professionnel (image ou PDF) optionnel, `min:2`/`max:4` documents |
| `POST` | `/artisan/verification/cancel` | Annule la soumission `pending` |
| `GET` | `/artisan/verification/documents/{document}` | Télécharge une de ses pièces (artisan propriétaire uniquement) |

### Métier — **réservé aux artisans `verified`** (`403 ARTISAN_NOT_VERIFIED` sinon)

| Méthode | Route | Description |
| ------- | ----- | ----------- |
| `PATCH` | `/artisan/availability` | Bascule `is_available` (bloqué pendant intervention) |
| `GET` | `/artisan/offers` | Demandes reçues (paginations) |
| `GET` | `/artisan/offers/{offer}` | Détail d'une offre |
| `POST` | `/artisan/offers/{offer}/accept` | Accepte (débloque les contacts client) |
| `POST` | `/artisan/offers/{offer}/reject` | Refuse (la demande repasse `awaiting_artisan`) |
| `GET` | `/artisan/repair-requests` | Interventions (`active` / `completed`) |
| `GET` | `/artisan/repair-requests/{repairRequest}` | Détail |
| `POST` | `/artisan/repair-requests/{repairRequest}/start` | Démarre l'intervention |
| `POST` | `/artisan/repair-requests/{repairRequest}/complete` | Termine l'intervention (redevenu disponible) |

## Erreurs remarquables

- `401` : non authentifié / token invalide.
- `403` : rôle interdit, compte désactivé (`account_inactive`), ou **`ARTISAN_NOT_VERIFIED`**
  (artisan `pending`/`rejected` sur un endpoint métier).
- `404` : ressource absente (ex. profil public d'un artisan non validé).
- `422` : validation (ex. offre vers un artisan incompatible, double avis, statut invalide).
- `429` : rate limiting (login, OTP).

## Mot de passe oublié (exigence V1)

1. `POST /auth/forgot-password` `{ email }` → envoie un **OTP e-mail** (6 chiffres) si le
   compte existe. Réponse identique que le compte existe ou non (pas de fuite d'existence).
2. `POST /auth/password/reset` `{ email, code, password }` → valide l'OTP (usage unique,
   expiré après 10 min), réinitialise, **révoque les autres sessions**. Message d'erreur
   **identique** que l'e-mail existe ou non (anti-énumération).
3. Écrans mobiles correspondants : `app/forgot-password.tsx` (message générique + écran de
   succès « Vérifiez votre boîte mail »), `app/reset-password.tsx` (« Créer un nouveau mot de
   passe » + succès « Mot de passe modifié »).
4. Tests : `tests/Feature/PasswordResetApiTest.php` (6 tests).
