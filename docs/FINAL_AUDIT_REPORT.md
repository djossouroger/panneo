# Rapport final d'audit — Pannéo V1 (LOT 08)

**Date** : 16 août 2026 · **Périmètre** : backend Laravel, mobile Expo, back-office admin,
PostgreSQL. **Méthode** : audit de l'état réel (code + tests + données), corrections
conservatrices, vérifications, documentation complète.

---

## 1. Objectifs

1. Auditer l'état réel du projet et corriger les écarts entre spécifications et réalité.
2. Garantir les **deux ajouts obligatoires V1** :
   - **Mot de passe oublié** réellement fonctionnel.
   - **Validation administrative obligatoire des artisans** (blocage métier).
3. Aucune régression, aucune suppression de fonctionnalité existante, aucune nouvelle
   fonctionnalité métier.
4. Livrer la documentation complète (`README.md`, `ROADMAP_V2.md`, `docs/`).

---

## 2. Résultat des audits

### Mot de passe oublié — ✅ conforme (anti-énumération renforcée)

Déjà implémenté de bout en bout et testé :
- API : `POST /auth/forgot-password` (envoi OTP e-mail, `throttle:5,1`, réponse neutre) et
  `POST /auth/password/reset` (OTP à usage unique, expiration 10 min, réinitialisation,
  **révocation des autres sessions**).
- **Anti-énumération** : `password/reset` renvoie désormais le même message d'erreur
  (« Ce code est invalide ou a expiré. ») que l'e-mail existe ou non — aucune fuite d'existence.
- Mobile : écrans `forgot-password.tsx` (message générique + état de succès « Vérifiez votre
  boîte mail ») / `reset-password.tsx` (« Créer un nouveau mot de passe », état de succès
  « Mot de passe modifié »).
- Tests : `PasswordResetApiTest` (6 tests). → **Documenté** (`docs/API.md`, `docs/MANUAL_TEST_PLAN.md`).

### Validation artisan — ✅ mise en conformité (implémentation ajoutée)

L'exigence « l'artisan doit être validé avant d'exercer » **n'était pas défendue côté serveur** :
un artisan `pending`/`rejected` pouvait encore basculer sa disponibilité et répondre aux
demandes. Corrections apportées :

| Fichier | Changement |
| ------- | ---------- |
| `app/Http/Middleware/EnsureArtisanVerified.php` | **Nouveau** : `403 { code: 'ARTISAN_NOT_VERIFIED' }` |
| `bootstrap/app.php` | Alias de middleware `artisan.verified` |
| `routes/api.php` | Middleware sur `PATCH /artisan/availability` + groupes `offers` et `repair-requests` |
| `app/Services/MatchingService.php` | `candidateArtisans` exclut les non-`verified` ; `isCompatible=false` ; `ensureCompatible` → 422 « Ce dépanneur n'a pas encore été validé par Pannéo. » |
| Mobile `home/artisan.tsx` / `(tabs)/requests.tsx` | UI « en attente de validation » : ne déclenche plus d'appels aux endpoints gatés ; écrans dédiés pending/rejected (v1 : bandeau + bouton) |

Périmètre **volontairement restreint** : profil, catégories, zones, horaires, indisponibilités,
portfolio et vérification restent accessibles aux artisans non validés (préparation du dossier).

**Complément (exigence produit V1)** : la vérification d'identité exige désormais une **pièce
d'identité** et un **selfie avec la pièce** (tous deux obligatoires, images JPG/PNG/WEBP ≤ 5 Mo,
stockage privé `storage/app/private`), le justificatif professionnel restant optionnel.
L'inscription artisan se fait en **3 étapes** (profil → activité → identité), avec des écrans
dédiés « en cours de validation » (horodatage de soumission) et « non validé » (motif du rejet)
dans l'app, et un back-office qui affiche les images **côte à côte** avec validation par modal.

---

## 3. Corrections apportées

- **Blocage métier** : middleware + routes + MatchingService (ci-dessus).
- **Tests** : trait `InteractsWithArtisans` crée des artisans `verified` par défaut ;
  adaptation des tests qui supposaient un artisan neuf `pending` ; ajout de
  `ArtisanVerificationGateApiTest` (9 tests : availability, offers, repair-requests, start,
  matching, offre client → 422, accès profil libre). Réparation d'une édition corrompue dans
  `ArtisanReputationAndVerificationTest`.
- **Mobile** : état « pending » sur l'accueil artisan et l'onglet Demandes (typecheck + build OK).

## 4. Vérifications

| Vérification | Résultat |
| ------------ | -------- |
| `php artisan test` | **146 tests, 560 assertions, tous verts** |
| `php artisan migrate:fresh --seed` | ✅ (artisan démo créé `verified`) |
| `npx tsc --noEmit` (mobile) | ✅ 0 erreur |
| `npx expo export --platform ios` | ✅ bundle généré |

## 5. État de la base de code

- **Backend** : Laravel 12.66 / PHP 8.2 / Sanctum 4.2 — 16 modèles, 25 migrations, 21
  contrôleurs (11 API + 10 admin), 6 services, 4 middlewares, 15 fichiers de tests.
- **Mobile** : Expo SDK 54 / RN 0.81.5 / React 19.1 — 44 écrans, 3 composants, 6 modules `lib`.
- **Back-office** : 14 vues Blade, routes `/admin` complètes (dont validation des artisans).
- **Stockage** : photos publiques dans `storage/app/public` (lien `storage`) ; **documents de
  vérification** (CNI + selfie) dans `storage/app/private` (disque `local`, jamais servis
  publiquement).

## 6. Points d'attention résiduels (non bloquants, hors périmètre V1)

1. **SMS OTP** via `LogSmsProvider` (dev) → remplacer par un vrai transporteur en prod.
2. **E-mails** : `OTP_DELIVERY=mail` → SMTP Gmail configuré dans `.env` ; à confirmer en prod.
3. **`npm install`** : `ERESOLVE` préexistant (react-dom@19.2.8 vs react@19.1.0) → contourner
   avec `--legacy-peer-deps` ; n'affecte pas `tsc`/`expo export`.
4. **`APP_URL` du `.env`** obsolète (l'app mobile calcule l'URL depuis Metro) → à corriger en
   prod avec l'URL réelle (serveur).
5. **Fichiers parasites supprimés** : `null` (racine), logs expo, stub `report-placeholder.tsx`
   (voir `docs/`).
6. **`VerificationCode`** pour téléphone utilise le même cycle que l'e-mail → cohérent, à
   superviser.

## 7. Livrables

- Code corrigé + compléments V1 (selfie, 3 étapes, écrans dédiés, back-office, vérification
  e-mail à l'inscription) — **146 tests verts**.
- Documentation : `README.md`, `ROADMAP_V2.md`, `docs/` (19 fichiers).
- Plan de test manuel : `docs/MANUAL_TEST_PLAN.md` ; scénario de démo : `docs/DEMO.md`.

## 8. Conclusion

Pannéo V1 est **stable, testé et documenté**. Les deux exigences obligatoires sont garanties :
mot de passe oublié **fonctionnel** (existant, audit et documentation) et **validation
administrative des artisans obligatoire** (blocage serveur + matching + UI). Le socle est prêt
pour les évolutions V2 décrites dans `ROADMAP_V2.md`.

---

## 9. Préparation déploiement Railway — RAILWAY READINESS ✅

Audit de l'état réel du projet en vue du déploiement sur Railway, corrections
**sans aucune fonctionnalité métier nouvelle**.

### 9.1 Constats et corrections

| Domaine | Constat de l'audit | Correction apportée |
| ------- | ------------------ | ------------------- |
| Versionnement | Racine du monorepo sans dépôt Git ; `mobile/` a un dépôt local, `backend/` non | Procédure de push documentée (`docs/RAILWAY_DEPLOYMENT.md`) |
| Secrets | `backend/.env.bak` (secrets) non ignoré ; `mobile/.env` non ignoré | `.gitignore` complétés (`.env.bak`, `.env.*.local`, `.env`) |
| Route `/api/v1/health` | Closure dans `routes/api.php` → **incompatible `route:cache` / build Railpack** | `HealthController::show` (contrôleur) — test conservé |
| Stockage | Disques `local`/`public` figés sur `storage/app` (éphémère sur Railway) | `config/filesystems.php` : racine centralisée via `PERSISTENT_STORAGE_PATH` (volume `/data`), y compris le lien `public/storage` |
| Seeders | `DemoSeeder` (comptes démo `Demo123!`) exécuté même en production ; admin avec mot de passe par défaut | `DatabaseSeeder` : `DemoSeeder` **exclu en production** ; `ADMIN_*` **obligatoires** en prod ; nouveau `php artisan admin:create` |
| Env | `.env.example` avec valeurs de dev (MAILER log, pas de stockage, pas d'OTP) | `.env.example` complet et commenté pour la prod (DB_URL, PERSISTENT_STORAGE_PATH, OTP_DELIVERY, SMTP, LOG stderr, admin) |
| Déploiement | Aucun script | `railway/init-app.sh` (migrate --force, seed --force sécurisé, caches) |
| Mobile APK | `app.json` sans identité (`package`, `bundleIdentifier`), sans permissions ; IP locale en dur dans `lib/api.ts` ; pas de profil EAS | `app.json` : `com.panneo.app`, plugin `expo-image-picker` (permissions caméra/photos, pas de RECORD_AUDIO) ; `eas.json` (preview→APK, production→AAB) ; `api.ts` : plus d'IP codée en dur (`EXPO_PUBLIC_API_URL` source unique) ; scripts `typecheck`/`export` |
| Docs | Absence de guide de déploiement | `docs/RAILWAY_VARIABLES.md`, `docs/RAILWAY_DEPLOYMENT.md`, `docs/APK_RELEASE.md`, section README |

### 9.2 Choix techniques (justifiés)

- **Un seul service App** : e-mails **synchrones** (`OtpService` → `Mail::raw`), aucun job de
  file d'attente, aucun scheduler → **pas de worker ni de cron** requis.
- **Aucun fichier de build forcé** (`Dockerfile`, `railway.json`, `nixpacks.toml`,
  `Procfile`) : Railpack détecte Laravel nativement (FrankenPHP, port `$PORT`,
  `storage:link` + `migrate --force` + optimisation au démarrage, caches au build).
  Le php.ini par défaut de Railpack ne limite pas les uploads (limites applicatives :
  3 Mo profil / 4 Mo portfolio / 5 Mo × 4 documents).
- **`RAILPACK_PHP_EXTENSIONS=pdo_pgsql`** : extension PostgreSQL absente par défaut.

### 9.3 Vérifications effectuées

| Vérification | Résultat |
| ------------ | -------- |
| `php artisan test` | **146 tests, 560 assertions, tous verts** (route health → contrôleur OK) |
| `php artisan route:cache` / `config:cache` / `event:cache` / `view:cache` | ✅ puis `optimize:clear` |
| `php artisan admin:create --help` | ✅ commande disponible |
| `npm run build` (backend, Vite/Blade) | ✅ 56 modules, `public/build` généré |
| `npx expo config` (mobile) | ✅ identité + plugins résolus |
| `npx tsc --noEmit` (mobile) | ✅ 0 erreur |
| `npx expo export --platform android` | ✅ bundle généré |

### 9.4 Verdict RAILWAY READINESS

**PANNÉO PRÊTE POUR DÉPLOIEMENT RAILWAY**

La seule variable d'environnement dont la valeur est inconnue à ce stade est
`APP_URL`/domaine de production (généré au moment du déploiement). Aucune
modification de code ne sera nécessaire après le déploiement ; le domaine est à
recopier dans `APP_URL` (backend) et `EXPO_PUBLIC_API_URL` (build mobile APK).
