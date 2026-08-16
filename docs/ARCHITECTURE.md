# Architecture

## Stack

### Backend — `backend/`

- **Laravel 12.66.0** (PHP ^8.2, PHP 8.2.12 local) — API REST + back-office Blade.
- **Sanctum 4.2** — tokens d'authentification API.
- **PostgreSQL** en développement/production, **SQLite en mémoire** pour les tests.
- Queue : file **database** (`jobs`), drivers **log** par défaut pour e-mail (`mail`) et SMS.
- Lint : Laravel Pint.

### Mobile — `mobile/`

- **Expo SDK 54** (`expo ~54.0.35`), **React Native 0.81.5**, **React 19.1.0**.
- **expo-router ~6.0.24** (file-based routing), `@react-navigation/*` v7 sous-jacent.
- **TypeScript ~5.9**, strict.
- UI : composants maison (`components/ui.tsx`), icônes **lucide-react-native**,
  stockage sécurisé **expo-secure-store**, **expo-image-picker** / **expo-document-picker**.

## Découpage

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/        ← 11 contrôleurs API (/api/v1)
│   │   │   └── Admin/      ← 10 contrôleurs back-office (/admin)
│   │   └── Middleware/     ← EnsureRole, EnsureAccountIsActive, EnsureArtisanVerified, AdminOnly
│   ├── Models/             ← 16 modèles Eloquent
│   ├── Services/           ← MatchingService, NotificationService, OtpService, AuditLogger, SMS
│   └── Notifications/      ← notifications e-mail/mail de l'application
├── routes/                 ← api.php (v1), web.php (admin + /)
├── database/
│   ├── migrations/         ← 25 migrations (ordre chronologique)
│   └── seeders/            ← CategorySeeder, DemoSeeder
├── resources/views/admin/  ← 14 vues Blade du back-office
└── tests/                  ← 15 fichiers, Feature + Unit

mobile/
├── app/                    ← écrans (expo-router)
│   ├── (tabs)/             ← onglets racine : accueil, demandes, profil
│   ├── home/               ← home artisan vs client
│   ├── artisan/            ← profil artisan (édition, horaires, zones, absences, portfolio, vérification)
│   ├── signup/             ← inscription (rôle → formulaire → artisan)
│   ├── account/            ← compte (e-mail, téléphone, sessions, suppression, vérif téléphone)
│   ├── disputes/, intervention/, offer-detail/, repair-request/, available-artisans/…
│   └── login, welcome, notifications, favorites, report, forgot/reset-password…
├── components/             ← ui.tsx, repairRequests.tsx, notifications.tsx
└── lib/                    ← api.ts (client HTTP + types), session.ts, dates.ts, contact.ts…
```

## Flux global

```
[Client mobile] ──POST /api/v1/repair-requests──► [API Laravel]
      │                                                │
      │  GET /repair-requests/{id}/available-artisans   │ MatchingService (catégorie, zone,
      │◄─────────────────────────────────────────────────┤ horaires, dispo, statut vérifié, pas d'intervention)
      │                                                │
      │  POST /repair-requests/{id}/offers              │ (offre → notification artisan)
      │  POST /artisan/offers/{id}/accept  ◄── artisan  │ (client débloqué → téléphone)
      │  POST /artisan/repair-requests/{id}/start       │
      │  POST /artisan/repair-requests/{id}/complete    │
      │  POST /repair-requests/{id}/review              │
      └─────────────────────────────────────────────────┘
```

L'**admin** intervient via le back-office (`/admin`) : validation des artisans, modération
litiges/utilisateurs, consultation des demandes et des avis.

## Règles transverses

- Tous les endpoints business d'API sont derrière `auth:sanctum` + `active`
  (`EnsureAccountIsActive`) + vérification de rôle (`EnsureRole:client|artisan`).
- La **validation artisan** est un prérequis côté serveur :
  - middleware `artisan.verified` sur availability, offres, repair-requests ;
  - `MatchingService` n'expose jamais un artisan non `verified`.
- Les **documents de vérification** sont protégés : stockage **privé** (`storage/app/private`,
  disque `local`), téléchargement/affichage réservé à l'artisan propriétaire ou à un admin
  (route `image` pour le back-office).
- L'**heure serveur** fait référence pour horaires, indisponibilités et états.
