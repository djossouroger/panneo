# Pannéo — Plateforme de mise en relation dépannage à domicile

Application de mise en relation entre **clients** qui signalent un besoin de dépannage
(plomberie, électricité…) et des **artisans** géolocalisés et **validés par la plateforme**.

Monorepo composé de deux projets :

| Dossier    | Techno                                   | Rôle                                      |
| ---------- | ---------------------------------------- | ----------------------------------------- |
| `backend/` | Laravel 12.66 · PHP 8.2 · Sanctum 4.2    | API REST (`/api/v1`) + back-office admin   |
| `mobile/`  | Expo SDK 54 · React Native 0.81 · TypeScript | Application mobile iOS / Android        |

---

## Démarrage rapide

### Backend

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

### Mobile

```bash
cd mobile
npm install
npx expo start
```

La base URL de l'API est déduite automatiquement de l'adresse du serveur Metro
(`EXPO_PUBLIC_API_URL` vide → port 8001 de l'hôte). On peut la surcharger :

```bash
# Windows PowerShell
$env:EXPO_PUBLIC_API_URL="http://192.168.x.x:8001/api/v1"
npx expo start
```

---

## Comptes de démonstration (seeder)

Mot de passe commun : `Demo123!`

| Rôle     | Email                        | Téléphone       |
| -------- | ---------------------------- | --------------- |
| Admin    | `admin.demo@panneo.test`     | +2290100000001  |
| Client   | `client.demo@panneo.test`    | +2290100000002  |
| Artisan  | `artisan.demo@panneo.test`   | +2290100000003  |

L'artisan de démo est créé **déjà validé** (`verification_status = verified`), il peut donc
recevoir des demandes immédiatement. L'administrateur se connecte au back-office sur
`http://localhost:8000/admin`. Un artisan inscrit via l'app (3 étapes : profil → activité →
**identité avec pièce + selfie**) est créé `pending` et doit être validé par l'admin.

---

## Architecture en bref

```
mobile (Expo/React Native)  ──HTTP/JSON──►  API Laravel /api/v1 (Sanctum)
                                               │
                                               ├── services métier (MatchingService, OtpService…)
                                               ├── PostgreSQL (données)
                                               └── back-office admin (Blade, /admin)
```

- **Auth** : Sanctum tokens, rôles `client` / `artisan` / `admin`, OTP e-mail (SMTP Gmail) + SMS (log),
  **vérification e-mail obligatoire à l'inscription** (login bloqué tant que `email_verified_at` est nul).
- **Matching** : `MatchingService` = catégorie + zone géographique + horaires + disponibilité
  + **validation du compte artisan** + pas d'intervention en cours.
- **Flux de dépannage** : client crée une demande → propose des artisans compatibles →
  l'artisan accepte/refuse → intervention (début/fin) → avis + litige éventuel.
- **Validation artisan obligatoire** : un artisan `pending`/`rejected` reçoit `403 ARTISAN_NOT_VERIFIED`
  sur les endpoints métier (disponibilité, offres, interventions) et n'apparaît jamais dans le matching.
  Le dossier (pièce d'identité + **selfie**, stockage privé) est examiné côte à côte dans le
  back-office (validation par modal).

---

## Validation

```bash
# Backend (146 tests / 560 assertions)
cd backend
php artisan test

# Mobile
cd mobile
npx tsc --noEmit
npx expo export --platform ios
```

---

## Déploiement (Railway)

Le backend est prêt pour Railway : **un seul service App** (Laravel + back-office
Blade + assets Vite), sans worker ni cron (e-mails synchrones, aucun scheduler).
Procédure complète : [`docs/RAILWAY_DEPLOYMENT.md`](docs/RAILWAY_DEPLOYMENT.md),
variables : [`docs/RAILWAY_VARIABLES.md`](docs/RAILWAY_VARIABLES.md).

Résumé :

```bash
# Railway : Root Directory = backend  (monorepo à pousser sur GitHub)
# Build : Railpack (détection automatique Laravel, FrankenPHP)
# Volume : monté sur /data  →  PERSISTENT_STORAGE_PATH=/data
# Pre-Deploy Command :
chmod +x railway/init-app.sh && sh railway/init-app.sh
# Variables clés : APP_ENV=production, APP_DEBUG=false, APP_KEY (générée une fois),
#   DB_URL=${{Postgres.DATABASE_URL}}, LOG_CHANNEL=stderr,
#   OTP_DELIVERY=mail + SMTP Gmail, RAILPACK_PHP_EXTENSIONS=pdo_pgsql
```

Points garantis :

- **Uploads persistants** (volume) : photos publiques → `/data/public`,
  documents privés (CNI + selfie) → `/data/private`.
- **`DatabaseSeeder` sécurisé en production** : aucun compte démo créé
  (`DemoSeeder` exclu), admin via `ADMIN_*` ou `php artisan admin:create`.
- **Route `/api/v1/health`** sous contrôleur → compatible `route:cache`/Railpack.
- **Futur APK Android** : `app.json` + `eas.json` prêts
  ([`docs/APK_RELEASE.md`](docs/APK_RELEASE.md)) — génération uniquement après
  définition du domaine de production.

---

## Documentation

Tout est dans [`docs/`](docs/) :

| Fichier                                            | Contenu |
| -------------------------------------------------- | ------- |
| `docs/PRODUCT.md`                                  | Description du produit, rôles, cas d'usage |
| `docs/ARCHITECTURE.md`                             | Stack, arborescence, flux globaux |
| `docs/DATABASE.md`                                 | Schéma, 16 modèles, contraintes |
| `docs/API.md`                                      | Référence des endpoints `/api/v1` |
| `docs/MOBILE.md`                                   | Structure de l'app, écrans, navigation |
| `docs/BACKOFFICE.md`                               | Console admin (`/admin`), Blade |
| `docs/BUSINESS_RULES.md`                           | Règles métier détaillées |
| `docs/MATCHING.md`                                 | Algorithme de matching |
| `docs/REPAIR_WORKFLOW.md`                          | Cycle de vie d'une demande |
| `docs/SECURITY.md`                                 | Auth, OTP, tokens, bonnes pratiques |
| `docs/DESIGN_SYSTEM.md`                            | Design system mobile |
| `docs/MANUAL_TEST_PLAN.md`                         | Plan de test manuel |
| `docs/DEMO.md`                                     | Scénario de démonstration |
| `docs/SOUTENANCE.md`                               | Guide de maintenance / opérations |
| `docs/EXISTING_EXTRA_FEATURES.md`                  | Fonctionnalités hors périmètre initial |
| `docs/FINAL_AUDIT_REPORT.md`                       | Rapport final de l'audit LOT 08 |
| `docs/RAILWAY_VARIABLES.md`                        | Variables d'environnement Railway (DB, SMTP, stockage, admin) |
| `docs/RAILWAY_DEPLOYMENT.md`                       | Déploiement complet sur Railway (guide pas à pas) |
| `docs/APK_RELEASE.md`                              | Préparation / génération d'un futur APK Android (EAS) |
