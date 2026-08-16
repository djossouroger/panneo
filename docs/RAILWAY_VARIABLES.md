# Railway — Variables d'environnement (Pannéo)

Ce document liste **toutes** les variables à définir dans le tableau de bord
Railway (service App) pour un déploiement de production de Pannéo.

Règle d'or : **les variables Railway remplacent `.env`**. Ne committez jamais
`.env` (il est dans `.gitignore`). Les secrets (`APP_KEY`, `DB_PASSWORD`,
`MAIL_PASSWORD`) ne sont visibles que dans le tableau de bord Railway.

---

## 1. Application

| Variable | Valeur recommandée | Obligatoire | Commentaire |
| -------- | ------------------ | ----------- | ----------- |
| `APP_ENV` | `production` | ✅ | |
| `APP_KEY` | chaîne `base64:...` | ✅ | Générer **une fois** : `php artisan key:generate --show` (dans `backend/`), puis coller. Si absente, sessions/chiffrement cassés à chaque redéploiement. |
| `APP_DEBUG` | `false` | ✅ | Ne jamais activer en prod (fuite d'informations). |
| `APP_URL` | `https://<votre-domaine-railway>` | ✅ | Utilisé pour les URLs publiques `…/storage/…` (photos, portfolio). |
| `APP_LOCALE` | `fr` | | |
| `APP_MAINTENANCE_DRIVER` | `file` | | |

## 2. Base de données (PostgreSQL Railway)

Deux possibilités équivalentes. **Option A recommandée** (une seule variable) :

| Variable | Valeur | Obligatoire | Commentaire |
| -------- | ------ | ----------- | ----------- |
| `DB_CONNECTION` | `pgsql` | ✅ | |
| `DB_URL` | `${{Postgres.DATABASE_URL}}` | ✅ | Référence automatique au service Postgres. Laravel applique le `sslmode=require` contenu dans l'URL (`config/database.php` → `ConfigurationUrlParser`). |

**Option B** (variables détaillées, si besoin) :

| Variable | Valeur |
| -------- | ------ |
| `DB_CONNECTION` | `pgsql` |
| `DB_HOST` | `${{Postgres.PGHOST}}` |
| `DB_PORT` | `${{Postgres.PGPORT}}` |
| `DB_DATABASE` | `${{Postgres.PGDATABASE}}` |
| `DB_USERNAME` | `${{Postgres.PGUSER}}` |
| `DB_PASSWORD` | `${{Postgres.PGPASSWORD}}` |
| `DB_SSLMODE` | `require` |

Ne pas définir `SQLite` ni `DB_CONNECTION=sqlite` sur Railway (système de fichiers
éphémère).

## 3. Stockage persistant (volume)

| Variable | Valeur | Obligatoire | Commentaire |
| -------- | ------ | ----------- | ----------- |
| `PERSISTENT_STORAGE_PATH` | `/data` | ✅ | Chemin **absolu** du volume monté (défini dans l'onglet Volumes du service App). Les disques `local` (documents privés) et `public` (photos/portfolio) pointent alors vers `/data/private` et `/data/public`. |
| `FILESYSTEM_DISK` | `local` | | Défaut déjà correct. |

> Sans volume, les uploads disparaissent à chaque redéploiement (système de fichiers
> éphémère). Le volume doit être monté **avant** le premier déploiement contenant des données.

## 4. OTP et e-mails

> **Important — SMTP bloqué sur les plans gratuits/Hobby.** Railway bloque tout
> SMTP sortant (ports 25/465/587/2525) hors plan **Pro** (cf. docs.railway.com →
> Outbound Networking → Email delivery). En conséquence, la production utilise un
> service d'e-mail **en API HTTPS** : SendGrid (transport custom `sendgrid`,
> livré dans ce dépôt — `app/Mail/Transport/SendGridTransport.php`). Le SMTP Gmail
> n'est possible que sur plan Pro.

### 4a. Envoi via SendGrid (API HTTPS — fonctionne sur TOUS les plans)

| Variable | Valeur | Obligatoire | Commentaire |
| -------- | ------ | ----------- | ----------- |
| `OTP_DELIVERY` | `mail` | ✅ | `log` en dev, `mail` en prod. |
| `MAIL_MAILER` | `sendgrid` | ✅ | Transport HTTPS API (aucun port SMTP requis). |
| `SENDGRID_API_KEY` | `SG.<clé>` | ✅ | Compte sendgrid.com → Settings → API Keys (permissions « Mail Send »). |
| `MAIL_FROM_ADDRESS` | `panneoartisan@gmail.com` | ✅ | Doit être un **expéditeur vérifié** (SendGrid → Settings → Sender Authentication → Single Sender Verification). |
| `MAIL_FROM_NAME` | `Pannéo` | | |

> Limitations : gratuit ~100 e-mails/jour (60 jours d'essai) ; « Single Sender »
> sans domaine ⇒ pas de SPF/DKIM, délivrabilité parfois réduite. Pour la
> production durable, préférer un domaine vérifié (~10 $/an) + Resend/Mailgun,
> ou passer Railway en Pro pour conserver le SMTP Gmail.

### 4b. Alternative : SMTP Gmail (uniquement plan Railway Pro)

| Variable | Valeur | Obligatoire | Commentaire |
| -------- | ------ | ----------- | ----------- |
| `OTP_DELIVERY` | `mail` | ✅ | |
| `MAIL_MAILER` | `smtp` | ✅ | |
| `MAIL_SCHEME` | `smtp` | ✅ | `smtps` + port `465` ou `smtp` + port `587`. **Jamais `tls`** (erreur « scheme not supported »). |
| `MAIL_HOST` | `smtp.gmail.com` | ✅ | |
| `MAIL_PORT` | `587` | ✅ | TLS/STARTTLS. |
| `MAIL_USERNAME` | `panneoartisan@gmail.com` | ✅ | **Adresse complète** (Gmail rejette un username tronqué : erreur 535). |
| `MAIL_PASSWORD` | *(mot de passe d'application Gmail)* | ✅ | Gmail → Sécurité → « Mots de passe d'application » (16 caractères). Jamais le mot de passe du compte. |
| `MAIL_FROM_ADDRESS` | `panneoartisan@gmail.com` | ✅ | |
| `MAIL_FROM_NAME` | `Pannéo` | | |

> Vérification après déploiement : inscrire un compte, le code de vérification
> e-mail doit arriver ; « Mot de passe oublié » doit fonctionner.

## 5. Session / Cache / File d'attente

| Variable | Valeur | Obligatoire | Commentaire |
| -------- | ------ | ----------- | ----------- |
| `SESSION_DRIVER` | `database` | ✅ | Persiste dans PostgreSQL (sinon déconnexions à chaque déploiement). |
| `CACHE_STORE` | `database` | ✅ | Idem. |
| `QUEUE_CONNECTION` | `database` | ✅ | E-mails synchrones aujourd'hui → aucun worker requis. |
| `SESSION_SECURE_COOKIE` | `true` | ✅ | HTTPS derrière le proxy Railway. |

## 6. Logs

| Variable | Valeur | Obligatoire | Commentaire |
| -------- | ------ | ----------- | ----------- |
| `LOG_CHANNEL` | `stderr` | ✅ | Railway capture stdout/stderr (`config/logging.php` fournit le canal `stderr`). Les fichiers `storage/logs` ne sont pas persistants. |
| `LOG_LEVEL` | `info` | | |

## 7. Compte administrateur (amorçage)

| Variable | Valeur | Obligatoire | Commentaire |
| -------- | ------ | ----------- | ----------- |
| `ADMIN_NAME` | `Pannéo Admin` | ✅ | |
| `ADMIN_EMAIL` | *(e-mail réel)* | ✅ | |
| `ADMIN_PASSWORD` | *(mot de passe fort ≥ 8 car.)* | ✅ | Requis par `php artisan db:seed --force` en production (le seeder refuse de démarrer sans ces variables). Alternative : `php artisan admin:create --email=… --password=…`. |

> En production `DatabaseSeeder` **n'exécute pas** `DemoSeeder` : aucun compte
> démo (`admin.demo` / `client.demo` / `artisan.demo`) n'est créé.

## 8. Build Railpack

| Variable | Valeur | Obligatoire | Commentaire |
| -------- | ------ | ----------- | ----------- |
| `RAILPACK_PHP_EXTENSIONS` | `pdo_pgsql` | ✅ | Railpack n'inclut pas `pdo_pgsql` par défaut ; sans elle, la connexion PostgreSQL échoue. |

Ne pas définir `RAILPACK_SKIP_MIGRATIONS` (le démarrage Railpack exécute
`migrate --force`, `storage:link` et l'optimisation).

## 9. Ce qu'il ne faut PAS faire

- **Ne jamais** copier `APP_KEY`, `DB_PASSWORD`, `MAIL_PASSWORD` dans le client
  mobile : toute variable `EXPO_PUBLIC_*` est visible dans le bundle/APK compilé.
- **Ne jamais** activer `APP_DEBUG=true` en production.
- **Ne jamais** laisser `ADMIN_PASSWORD=Password123!` (valeur de démo du `.env.example`).
- **Ne jamais** lancer `migrate:fresh` / `db:wipe` sur la base de production.

## 10. Récapitulatif minimaliste

```
APP_ENV=production
APP_KEY=base64:…
APP_DEBUG=false
APP_URL=https://<domaine>
LOG_CHANNEL=stderr
DB_CONNECTION=pgsql
DB_URL=${{Postgres.DATABASE_URL}}
PERSISTENT_STORAGE_PATH=/data
OTP_DELIVERY=mail
MAIL_MAILER=sendgrid
SENDGRID_API_KEY=SG.<clé>
MAIL_FROM_ADDRESS=panneoartisan@gmail.com
MAIL_FROM_NAME=Pannéo
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_SECURE_COOKIE=true
ADMIN_NAME=Pannéo Admin
ADMIN_EMAIL=<email-admin>
ADMIN_PASSWORD=<mot-de-passe-fort>
RAILPACK_PHP_EXTENSIONS=pdo_pgsql
```
