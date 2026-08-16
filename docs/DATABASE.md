# Base de données

PostgreSQL en dev/prod, SQLite in-memory en tests. 25 migrations, 16 modèles.

## Modèles

| Modèle | Table | Rôle |
| ------ | ----- | ---- |
| `User` | `users` | Compte (`client`/`artisan`/`admin`), e-mail + téléphone vérifiés, photo, statut actif |
| `ArtisanProfile` | `artisan_profiles` | Profil métier : catégorie principale, ville/district, description, disponibilité, **verification_status**, expérience, spécialités |
| `Category` | `categories` | Métiers (plomberie, électricité…), prix indicatifs |
| `ArtisanServiceArea` | `artisan_service_areas` | Zones d'intervention (ville / district nullable = toute la ville) |
| `ArtisanWorkingHour` | `artisan_working_hours` | Horaires par jour (lundi→samedi) |
| `ArtisanUnavailability` | `artisan_unavailabilities` | Indisponibilités ponctuelles (date/durée/motif) |
| `ArtisanPortfolioItem` | `artisan_portfolio_items` | Photos de réalisations |
| `ArtisanVerificationSubmission` | `artisan_verification_submissions` | Demandes de validation (status, relecteur, horodatages) |
| `ArtisanVerificationDocument` | `artisan_verification_documents` | Pièces (identity_document / selfie / professional_proof), stockées en privé (`storage/app/private`) |
| `RepairRequest` | `repair_requests` | Demandes de dépannage + photos + horodatages d'intervention |
| `RepairRequestOffer` | `repair_request_offers` | Offres envoyées à un artisan pour une demande |
| `Review` | `reviews` | Avis 1–5 du client (unique par demande) |
| `Dispute` | `disputes` | Litiges sur une demande, résolus par l'admin |
| `Notification` | `notifications` | Notifications applicatives (lu/non lu) |
| `VerificationCode` | `verification_codes` | OTP e-mail/téléphone (à usage unique, expirants) |
| `SecurityAuditLog` | `security_audit_logs` | Journal des actions sensibles (AuditLogger) |

Joints : `artisan_categories` (catégorie primaire/secondaire), `artisan_service_areas`,
`artisan_working_hours`, `favorite_artisans` (client → artisan).

## Points clés du schéma

### users
- `role` : `client` | `artisan` | `admin` — détermine le middleware de garde.
- `email` unique, `phone` unique ; `email_verified_at`, `phone_verified_at`.
- `is_active` : compte désactivé ⇒ bloqué partout (`active` middleware + back-office).
- `profile_photo_path` → `profile_photo_url` (champ virtuel, URL stockage).

### artisan_profiles
- `verification_status` : **`pending` | `verified` | `rejected`** (`ArtisanProfile` constants).
- `verified_at`, `verified_by` posés lors de l'approbation admin.
- `is_available` : toggle manuel de disponibilité (bloqué pendant une intervention).
- Relation `user` 1:1 ; `reviews` via interventions terminées.

### artisan_verification_submissions / documents
- Une soumission active à la fois par artisan ; le back-office peut **approuver**, **rejeter**
  (avec motif) ou **rouvrir**.
- Statuts : `pending` | `approved` | `rejected` | `reopened`.
- Documents uploadés par l'artisan : `document_type` = **`identity_document` | `selfie` |
  `professional_proof`** (chaîne simple, pas d'enum → aucune migration nécessaire), `file_path`
  dans le disque **`local`** (`storage/app/private/verification-documents/...`, jamais servi
  publiquement), noms de fichiers générés côté serveur (`original_name` conservé en base).

### repair_requests
- Statuts : `pending` → `awaiting_artisan` → `accepted` → `in_progress` → `completed`
  (ou `cancelled`).
- `started_at`, `completed_at` posés par les endpoints artisan `start`/`complete`.
- `photos` : jusqu'à 2 images (tableau JSON stocké).

### repair_request_offers
- Statuts : `pending` | `accepted` | `rejected` | `cancelled`.
- Une seule offre **active** par demande (`second active offer forbidden`) ; une seule offre
  par artisan par demande.
- `accepted` ⇒ artisan indisponible + client voit ses coordonnées.

### reviews
- `rating` 1–5 (entier), `comment` ≤ 500, **unique(repair_request_id)**.

### verification_codes
- OTP : usage unique (`used_at`), expiration (e-mail 10 min / téléphone 10 min), renvoi limité
  (throttle 3/15 min). E-mail : 6 chiffres ; téléphone : 6 chiffres.

## Contraintes et index

- FKs + cascade cohérentes (une suppression de compte supprime profil/offres/avis…).
- Index sur les colonnes de filtrage métier : `repair_requests.status`,
  `artisan_profiles.verification_status`, `artisan_profiles.is_available`,
  `categories.slug` (unique), `repair_request_offers.repair_request_id`.

## Seeders

- `CategorySeeder` : catégories (plomberie, électricité, etc.) avec prix indicatifs.
- `DemoSeeder` : 3 comptes (voir `README.md`), profil artisan **verified** avec zones,
  horaires (lun–sam 8h–18h), catégories, soumission approuvée + document CNI fictif.
