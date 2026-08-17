# Pannéo — Plateforme de mise en relation dépannage à domicile

Application de mise en relation entre **clients** qui signalent un besoin de dépannage
(plomberie, électricité…) et des **artisans** géolocalisés et **validés par la plateforme**.

Monorepo composé de deux projets :

| Dossier    | Techno                                   | Rôle                                      |
| ---------- | ---------------------------------------- | ----------------------------------------- |
| `backend/` | Laravel 12.66 · PHP 8.2 · Sanctum 4.2    | API REST (`/api/v1`) + back-office admin   |
| `mobile/`  | Expo SDK 54 · React Native 0.81 · TypeScript | Application mobile iOS / Android        |

---

## Démarrage LOCAL (version soutenance — Windows)

> Le déploiement Railway est **suspendu**. Cette version est conçue pour tourner
> entièrement en local. Toute la documentation de déploiement (Railway, futur VPS)
> reste disponible dans [`docs/`](docs/).

### 1. Prérequis

- **XAMPP** (Apache + PHP 8.2) : https://www.apachefriends.org
- **PostgreSQL 18** installé comme service Windows (`postgresql-x64-18`) : https://www.postgresql.org
- **Composer** : https://getcomposer.org
- **Node.js LTS** (pour Expo) : https://nodejs.org
- **Un téléphone Android/iOS** avec l'app **Expo Go** (même Wi-Fi que le PC), ou un émulateur.

### 2. PostgreSQL

1. Démarrer le service PostgreSQL :
   ```powershell
   Start-Service postgresql-x64-18
   ```
2. Créer la base (une seule fois) :
   ```powershell
   & "C:\Program Files\PostgreSQL\18\bin\psql.exe" -U postgres -h 127.0.0.1 -c "CREATE DATABASE panneo;"
   ```
   (mot de passe `postgres` demandé — celui configuré à l'installation)

### 3. Backend Laravel

Ouvrir un terminal PowerShell **dans le dossier `backend`** :

```powershell
cd C:\xampp82\htdocs\panneo\backend

# 1. Dépendances
composer install
npm install

# 2. Fichier d'environnement (une seule fois)
Copy-Item .env.example .env
php artisan key:generate
# -> Éditer .env : DB_PASSWORD=..., MAIL_USERNAME=..., MAIL_PASSWORD=... (voir §6)

# 3. Schéma + données de démonstration
php artisan migrate --seed

# 4. Stockage public (photos)
php artisan storage:link

# 5. Lancer l'API + back-office
php artisan serve --host=0.0.0.0 --port=8001
```

L'API est disponible sur `http://localhost:8001/api/v1`.

### 4. Back-office admin

Toujours avec le serveur lancé (§3), ouvrir :

```
http://localhost:8001/admin
```

Compte admin (voir §8).

### 5. Application mobile Expo

Ouvrir un **second** terminal PowerShell **dans le dossier `mobile`** :

```powershell
cd C:\xampp82\htdocs\panneo\mobile
npm install
npx expo start
```

Scanner le **QR code** avec **Expo Go** sur le téléphone (ou appuyer sur `a` pour
l'émulateur Android, `w` pour le web).

> L'URL de l'API est **déduite automatiquement** de l'adresse IP du serveur Metro
> (`EXPO_PUBLIC_API_URL` vide dans `mobile/.env` → `http://<IP-PC>:8001/api/v1`).

### 6. Adresse IP à utiliser pour le téléphone

Le téléphone et le PC doivent être sur le **même réseau Wi-Fi**.

- Trouver l'IP locale du PC :
  ```powershell
  ipconfig
  ```
  → chercher la ligne **Adresse IPv4** de la carte Wi-Fi (ex. `192.168.1.64`).

- Le backend doit être lancé avec `--host=0.0.0.0` (voir §3) pour être accessible
  depuis le réseau.

- Si la déduction automatique ne fonctionne pas, forcer l'URL dans `mobile/.env` :
  ```
  EXPO_PUBLIC_API_URL=http://192.168.1.64:8001/api/v1
  ```
  puis relancer `npx expo start` (purger le cache avec `npx expo start -c`).

- Vérifier l'accès depuis le téléphone : ouvrir `http://192.168.1.64:8001/api/v1/health`
  dans le navigateur du téléphone → doit répondre `{"success":true,...}`.

### 7. Configuration SMTP (e-mails + OTP)

Dans `backend/.env` :

```
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=<adresse Gmail complète>
MAIL_PASSWORD=<mot de passe d'application Gmail>
MAIL_FROM_ADDRESS=<adresse Gmail>
OTP_DELIVERY=mail
```

> **Mot de passe d'application Gmail** : Google → Compte → Sécurité →
> « Mots de passe d'application » (nécessite la validation en 2 étapes activée).
> Le `MAIL_PASSWORD` n'est **pas** le mot de passe du compte Gmail.

Pour déboguer sans envoyer de vrais e-mails : `OTP_DELIVERY=log` → les codes sont
écrits dans `backend/storage/logs/laravel.log`.

### 8. Comptes de démonstration

Créés par `php artisan migrate --seed` (mot de passe commun documenté dans
`docs/DEMO.md`, modifiable dans `backend/database/seeders/DemoSeeder.php`) :

| Rôle                        | Email                      | Téléphone      | Statut                     |
| --------------------------- | -------------------------- | -------------- | -------------------------- |
| Admin back-office           | `admin.demo@panneo.test`   | +2290100000001 | —                          |
| Client                      | `client.demo@panneo.test`  | +2290100000002 | —                          |
| Artisan **validé**          | `artisan.demo@panneo.test` | +2290100000003 | `verified`                 |
| Artisan **en attente**      | `artisan2.pending@panneo.test` | +2290100000004 | `pending` (pour valider) |

**Un client + deux artisans validés** (plomberie / climatisation) existent aussi pour
**chaque ville** : `client.<ville>.demo@panneo.test` et
`artisan.plomberie.<ville>.demo@panneo.test`, `artisan.climatisation.<ville>.demo@panneo.test`
avec `<ville>` ∈ { cotonou, akpakpa, calavi, porto-novo, parakou, ouidah, bohicon, abomey }.
Chaque client de ville a une demande `pending` pré-créée → la recherche affiche 1–2 artisans
de sa ville. (Voir `docs/DEMO.md` pour le tableau complet.)

Demandes de démonstration pré-créées pour le client `client.demo@panneo.test` :

| Référence          | Catégorie    | Statut          | Détail                                    |
| ------------------ | ------------ | --------------- | ----------------------------------------- |
| `PAN-2026-DEMO001` | Plomberie    | `pending`       | recherche de dépanneurs disponible        |
| `PAN-2026-DEMO002` | Plomberie    | `awaiting_artisan` | offre en attente pour l'artisan démo   |
| `PAN-2026-DEMO003` | Électricité  | `completed`     | intervention terminée + avis 4/5          |

**Les mots de passe réels ne sont jamais commités.** Ils vivent uniquement dans le
`.env` local (ignoré par git) et dans `database/seeders/DemoSeeder.php`.

---

## Vérification / tests

```powershell
# Backend (149 tests / 563 assertions)
cd C:\xampp82\htdocs\panneo\backend
php artisan test

# Mobile (typcheck TypeScript)
cd C:\xampp82\htdocs\panneo\mobile
npx tsc --noEmit
```

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
| `docs/CLONE_OTHER_PC.md`                           | Cloner et lancer sur un AUTRE PC (plan de secours soutenance, sans bug) |
| `docs/DEMO.md`                                     | Scénario de démonstration |
| `docs/SOUTENANCE.md`                               | Guide de maintenance / opérations |
| `docs/EXISTING_EXTRA_FEATURES.md`                  | Fonctionnalités hors périmètre initial |
| `docs/FINAL_AUDIT_REPORT.md`                       | Rapport final de l'audit LOT 08 |
| `docs/RAILWAY_VARIABLES.md`                        | Variables d'environnement Railway (DB, SMTP, stockage, admin) |
| `docs/RAILWAY_DEPLOYMENT.md`                       | Déploiement complet sur Railway (guide pas à pas, réutilisable pour un VPS) |
| `docs/APK_RELEASE.md`                              | Préparation / génération d'un futur APK Android (EAS) |