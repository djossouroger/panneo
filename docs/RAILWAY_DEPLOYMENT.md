# Railway — Guide de déploiement (Pannéo, backend Laravel)

Pannéo se déploie sur Railway en un **seul service App** (Laravel + back-office
Blade + assets Vite). Aucun service worker ni cron n'est nécessaire (justification
en fin de document).

Le monorepo contient deux dossiers ; Railway déploie uniquement `backend/`
(**Root Directory = `backend`**). Les fichiers spécifiques au déploiement sont
dans `backend/railway/init-app.sh`.

---

## 1. Prérequis

- Compte [Railway](https://railway.com) + compte [GitHub](https://github.com).
- Un dépôt GitHub **à la racine du monorepo** (il n'existe pas encore de dépôt :
  voir la section « Push du monorepo »).
- La base PostgreSQL locale reste libre ; sur Railway on utilise le plugin
  PostgreSQL du projet.

## 2. Push du monorepo sur GitHub

Le dossier racine `panneo/` n'est pas un dépôt Git. Créer un dépôt vide sur
GitHub puis :

```bash
cd panneo
git init
git add .
git commit -m "Pannéo V1 : backend + mobile + docs (préparation Railway)"
git branch -M main
git remote add origin https://github.com/<votre-compte>/<repo>.git
git push -u origin main
```

> `backend/.gitignore` ignore `.env`, `.env.bak`, `.env.*.local` ; `mobile/.gitignore`
> ignore `.env`. Aucun secret ne doit être poussé. Vérifier avec
> `git status` puis `git ls-files | findstr /I env`.

## 3. Création du projet et du service

1. Railway → **New Project** → **Deploy from GitHub repo**.
2. Sélectionner le dépôt.
3. Dans les paramètres **Source**, définir **Root Directory = `backend`**
   (l'application Laravel est dans ce sous-dossier).
4. Créer le **service App** ; Railpack détecte automatiquement Laravel
   (`composer.json` + `artisan`).

## 4. Base de données PostgreSQL

Dans le projet Railway : **New → Database → PostgreSQL**. Puis, dans les
variables du service App, référencer la base :

- `DB_CONNECTION=pgsql`
- `DB_URL=${{Postgres.DATABASE_URL}}`

(Le `sslmode=require` inclus dans l'URL Railway est appliqué par Laravel.)

## 5. Variables d'environnement

Copier le tableau de `docs/RAILWAY_VARIABLES.md`, notamment :

- `APP_KEY` : générer **une fois** localement `php artisan key:generate --show`
  et la coller. C'est la variable la plus critique (sessions, chiffrement).
- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://<domaine>`.
- `LOG_CHANNEL=stderr`.
- `OTP_DELIVERY=mail` + SMTP Gmail (`MAIL_*`).
- `SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database`.
- `ADMIN_NAME` / `ADMIN_EMAIL` / `ADMIN_PASSWORD` (création de l'admin au seed).
- `RAILPACK_PHP_EXTENSIONS=pdo_pgsql` (indispensable : extension PostgreSQL).

## 6. Volume persistant (uploads)

1. Service App → onglet **Volumes** → **New Volume**.
2. Chemin de montage : **`/data`** (doit correspondre à
   `PERSISTENT_STORAGE_PATH=/data`).
3. Taille : 1 Go minimum (ajustable).

Les fichiers uploadés (photos de profil, portfolio, photos de demandes,
**pièces d'identité + selfies privés**) sont écrits dans `/data/public` et
`/data/private` et survivent aux redéploiements. Le lien `public/storage` est
recréé à chaque démarrage par Railpack (`storage:link`).

## 7. Script d'initialisation (Pre-Deploy)

Dans le service App → **Deploy → Pre-Deploy Command** :

```
chmod +x railway/init-app.sh && sh railway/init-app.sh
```

Le script `backend/railway/init-app.sh` :
- (re)joue `composer install` / `npm install` / `npm run build` si l'image ne les
  contient pas (Railpack les exécute déjà à la construction) ;
- **avertit** si `APP_KEY` est absente (génération temporaire) ;
- exécute `php artisan migrate --force` (idempotent) ;
- exécute `php artisan db:seed --force` (catégories + admin ; **démo désactivée
  en production**) ;
- prépare les caches (`config`, `event`, `route`, `view`).

> La route `GET /api/v1/health` utilise désormais un contrôleur (pas de closure)
> afin que `route:cache` / l'optimisation Railpack fonctionnent.

## 8. Déploiement

Cliquer **Deploy**. Au premier démarrage, Railpack :

1. installe Composer + npm et exécute `npm run build` (assets Vite/Blade) ;
2. optimise l'application (`config:cache`, `event:cache`, `route:cache`, `view:cache`) ;
3. au démarrage : `php artisan migrate --force`, `storage:link`, optimisation,
   puis lance le serveur **FrankenPHP** (Caddy + PHP) sur le port `$PORT`.

## 9. Vérifications post-déploiement

| Test | Commande / URL |
| ---- | -------------- |
| Santé | `GET https://<domaine>/api/v1/health` → `{"status":"ok"}` |
| Back-office | `https://<domaine>/admin` (connexion admin) |
| Inscription + e-mail | Inscrire un compte via l'app → le code de vérification e-mail arrive (SMTP Gmail) |
| Mot de passe oublié | `POST /api/v1/auth/forgot-password` → e-mail reçu |
| Upload | Photo de profil + portfolio + pièces d'identité (vérifier qu'elles réapparaissent après un redéploiement) |
| Document privé | Sans session → 401 ; client → 403 ; autre artisan → 403 ; admin (back-office) → OK |
| Session | Se reconnecter après un redéploiement (session en base) |

## 10. Domaine

Service App → **Networking → Generate Domain** (ou domaine personnalisé). Mettre
ce domaine dans `APP_URL`. Le client mobile de production utilise
`https://<domaine>/api/v1`.

## 11. Pourquoi un seul service (pas de worker ni de cron) ?

- **E-mails** : tous les envois passent par `OtpService::send()` → `Mail::raw`
  **synchrones**. Aucun job/fil d'attente, donc **pas de service worker**.
- **Planification** : `routes/console.php` ne contient que la commande par défaut
  `inspire` ; aucun scheduler actif, donc **pas de service cron**.
- Si ces choix évoluent (mailings asynchrones, jobs), ajouter un service worker
  (`railway/run-worker.sh` → `php artisan queue:work`) et/ou un service cron
  (`railway/run-cron.sh` → `php artisan schedule:run`) selon le guide officiel
  Railway.

## 12. Rétablissement / redeploiement

- Chaque `git push` déclenche un redéploiement automatique.
- Migrations et seeds sont idempotentes (`migrate --force`, `updateOrCreate`).
- Les uploads et la base survivent ; sessions/caches en base.
- **Ne jamais** lancer `migrate:fresh` sur la production.

## 13. Sauvegarde de la base

PostgreSQL Railway : onglet **Backups** du service (déclencher un backup avant
toute opération sensible).
