# Back-office administrateur

Console web Laravel **Blade** (`resources/views/admin/`), routes dans `routes/web.php`
préfixées `/admin`, protégées par `auth:web` + `AdminOnly` (`role = admin`).

## Accès

- URL : `http://localhost:8000/admin` (racine `/` redirige vers `admin.login`).
- Compte démo : `admin.demo@panneo.test` / `Demo123!`.

## Pages

| Route | Vue | Description |
| ----- | --- | ----------- |
| `/admin/login` | `admin/login.blade.php` | Connexion session web |
| `/admin` (dashboard) | `admin/dashboard.blade.php` | KPIs : utilisateurs, artisans en attente, demandes, litiges |
| `/admin/repair-requests` | `admin/repair-requests/index.blade.php` | Liste des demandes |
| `/admin/repair-requests/{repairRequest}` | `show.blade.php` | Détail + historique de matching |
| `/admin/users` | `admin/users.blade.php` | Utilisateurs + bascule actif/inactif |
| `/admin/artisans` | `admin/artisans.blade.php` | Liste des artisans |
| `/admin/artisans/{artisan}` | `admin/artisan/show.blade.php` | Profil complet d'un artisan |
| `/admin/verifications` | `admin/verifications/index.blade.php` | **Demandes de validation** (colonnes : artisan, téléphone, métiers, ville, date d'inscription, statut) |
| `/admin/verifications/{submission}` | `show.blade.php` | Détail : images **pièce d'identité + selfie côte à côte** (agrandissables), justificatif optionnel |
| `/admin/verifications/{submission}/approve` | — | **Valider** via modal « Valider cet artisan ? » → artisan `verified` + notification |
| `/admin/verifications/{submission}/reject` | — | **Refuser le dossier** avec motif → artisan `rejected` + notification |
| `/admin/verifications/{submission}/reopen` | — | **Rouvrir** une soumission |
| `/admin/verifications/documents/{document}/download` | — | Téléchargement d'une pièce (admin uniquement) |
| `/admin/verifications/documents/{document}/image` | — | Affichage inline d'une image (admin uniquement, réservé aux MIME image/*) |
| `/admin/disputes` / `/admin/disputes/{dispute}` | `disputes/*` | Litiges + mise à jour du statut |
| `/admin/categories` | `admin/categories.blade.php` | Catégories + édition (nom, prix, actif) |
| `/admin/reviews` | `admin/reviews.blade.php` | Avis des clients |

## Cycle de validation d'un artisan (rôle clé)

1. L'artisan s'inscrit en **3 étapes** (profil → activité → identité) puis soumet son dossier :
   **pièce d'identité + selfie** obligatoires (images JPG/PNG/WEBP ≤ 5 Mo), justificatif
   professionnel optionnel (`POST /api/v1/artisan/verification`).
2. L'admin voit la soumission `pending` dans `/admin/verifications` (date d'inscription
   affichée).
3. L'admin **compare visuellement** la pièce et le selfie (images côte à côte, clic pour
   agrandir — comparaison manuelle, sans reconnaissance faciale) et décide :
   - **Valider cet artisan ?** (modal) → `verification_status = verified`, `verified_at/verified_by`,
     notification « Votre compte a été validé » à l'artisan ; il devient éligible au matching.
   - **Refuser le dossier** (motif obligatoire) → `verification_status = rejected`, notification
     avec le motif.
4. Un artisan rejeté voit le motif dans l'app et peut corriger et **resoumettre** (revient `pending`).
5. Pendant ce temps, l'artisan garde l'accès à son profil (préparation) mais **tout endpoint
   métier renvoie `403 ARTISAN_NOT_VERIFIED`** et il **n'apparaît pas** dans le matching.

## Contrôleurs

`app/Http/Controllers/Admin/*` — `AuthController`, `DashboardController`, `UserController`,
`ArtisanController`, `VerificationController`, `RepairRequestController`, `DisputeController`,
`CategoryController`, `ReviewController`.

## Notes

- Toutes les actions sensibles passent par `AuditLogger` (`security_audit_logs`).
- Les vues utilisent Tailwind CSS (ressources Vite déjà configurées, build `npm run build`).
- Layout commun : `admin/layout.blade.php` (sidebar, topbar, CSRF sur tous les formulaires).
