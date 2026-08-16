# Soutenance / Maintenance

## Points d'entrée du projet

- **Code** : `C:\xampp82\htdocs\panneo\backend` (Laravel), `mobile` (Expo).
- **Base de données** : PostgreSQL (config `backend/.env`), SQLite in-memory en tests
  (`phpunit.xml`).
- **Back-office** : `http://localhost:8000/admin` — login `admin.demo@panneo.test`/`Demo123!`.
- **API** : `http://localhost:8000/api/v1` (OpenAPI manquante ; la référence est `docs/API.md`).

## Commandes récurrentes

```bash
# Backend
composer install
php artisan key:generate          # si .env vide
php artisan migrate --seed        # schéma + catégories + démo
php artisan migrate:fresh --seed  # réinitialisation complète
php artisan serve                 # http://localhost:8000
php artisan queue:work            # traite e-mails/jobs (dev : driver log ok)
php artisan test                  # 146 tests / 560 assertions
vendor/bin/pint                   # lint

# Mobile
npm install --legacy-peer-deps    # si ERESOLVE sur react-dom
npx expo start
npx tsc --noEmit
npx expo export --platform ios    # vérification de build
```

## Dépendances & versions

- Backend : Laravel 12.66, PHP ^8.2 (8.2.12 local), Sanctum 4.2, PHPUnit ^11.5.50, Pint ^1.24.
- Mobile : Expo SDK 54, RN 0.81.5, React 19.1.0, expo-router 6.0.24, TS 5.9.
- **Ne pas mettre à jour expo/react-native sans re-tester** (SDK 54 impose des versions exactes ;
  les docs de référence sont https://docs.expo.dev/versions/v54.0.0/).

## Où chercher quoi

| Besoin | Fichier |
| ------ | ------- |
| Règles métier | `app/Services/MatchingService.php`, `docs/BUSINESS_RULES.md` |
| Matching | `app/Services/MatchingService.php` |
| Validation artisan (blocage) | `app/Http/Middleware/EnsureArtisanVerified.php`, `routes/api.php`, `MatchingService` |
| Dossier vérification (CNI + selfie) | `app/Http/Controllers/Api/ArtisanController.php::submitVerification` |
| OTP | `app/Services/OtpService.php`, `VerificationCode`, `PhoneAuthController` |
| SMS | `app/Services/Sms/*` (`LogSmsProvider` en dev) |
| Notifications | `app/Services/NotificationService.php`, modèle `Notification` |
| Audit | `app/Services/AuditLogger.php` |
| Back-office validation | `app/Http/Controllers/Admin/VerificationController.php` |
| Client HTTP mobile | `mobile/lib/api.ts` |
| Session mobile | `mobile/lib/session.ts` |
| UI | `mobile/components/ui.tsx` |
| Écrans artisan | `mobile/app/artisan/*`, `mobile/app/home/artisan.tsx`, `(tabs)/requests.tsx` |

## Opérations sensibles

- **Bascule de compte** : `admin/users` (toggle `is_active`).
- **Validation artisan** : `admin/verifications` (valider via modal / refuser avec motif /
  rouvrir) → effets immédiats sur matching + accès API. Le détail affiche la pièce et le selfie
  **côte à côte** ; les documents sont stockés en privé (`storage/app/private`).
- **Suppression de compte** : API `DELETE /account/delete` (purge cascade) ; bloque si
  intervention en cours.
- **Reset mot de passe** : via API `forgot-password`/`password/reset` (révoque les sessions).

## En cas de problème

1. **Tests qui échouent** : vérifier `php artisan test` ; les tests utilisent SQLite in-memory
   (aucune donnée de dev affectée).
2. **OTP introuvable** : chercher dans `backend/storage/logs/laravel.log` (driver log).
3. **403 ARTISAN_NOT_VERIFIED inattendu** : l'artisan doit être `verified` — via
   `/admin/verifications` ou le seeder démo.
4. **npm ERESOLVE** : utiliser `npm install --legacy-peer-deps`.
5. **API introuvable depuis le téléphone** : l'app déduit l'IP du serveur Metro ; sinon poser
   `EXPO_PUBLIC_API_URL`.

## Backups recommandés

- PostgreSQL : `pg_dump` quotidien.
- `backend/storage/app/public` (photos publiques) : sauvegarde récurrente.
- `backend/storage/app/private` (**documents d'identité CNI + selfie**) : sauvegarde récurrente
  **prioritaire** (données sensibles).
- `.env` : versionné hors git (à conserver sous `secrets`).
