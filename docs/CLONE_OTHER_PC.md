# Cloner et lancer Pannéo sur un AUTRE PC (préparation de secours soutenance)

Ce guide permet de repartir de **zéro** sur un ordinateur qui ne contient encore rien
(prêté, salle informatique, PC de secours) et d'obtenir une démo **qui fonctionne sans bug**,
à partir du dépôt GitHub uniquement.

> Principe : **rien n'est nécessaire d'autre que le dépôt.** Les documents de démonstration
> (CNI/selfie) sont versionnés dans `backend/database/seeders/assets/demo/` et recréés
> automatiquement par `db:seed` s'ils manquent. L'URL mobile se déduit automatiquement de
> l'IP du serveur (aucune IP en dur).

---

## Méthode A — Windows avec XAMPP (recommandée, identique à votre PC)

C'est le chemin le plus simple et le plus fiable : même outillage que votre poste actuel.

### A.1. Installer les prérequis (une seule fois)

| Logiciel | Version | Rôle | Lien |
| -------- | ------- | ---- | ---- |
| XAMPP | dernière (PHP **8.2+**) | PHP, Composer PHP, services | https://www.apachefriends.org |
| PostgreSQL | **18** (même version = évite toute surprise) | base de données | https://www.postgresql.org |
| Node.js | LTS (20/22) | Expo | https://nodejs.org |
| Git | dernière | clonage | https://git-scm.com |
| Téléphone + **Expo Go** | — | affichage du mobile | Store |

> Note : PostgreSQL **n'a pas besoin** d'Apache ni de MySQL. Seul le **PHP 8.2 de XAMPP**
> est utilisé pour Laravel. Laisser le mot de passe `postgres` à l'installation (utilisé
> dans `.env`) — ou notez celui choisi.

Après installation, ouvrir un **PowerShell** et vérifier les versions :

```powershell
php -v        # → PHP 8.2.x (si "php" n'est pas reconnu, voir A.1b)
node -v       # → v20.x / v22.x
npm -v
git --version
```

#### A.1b. Si `php` n'est pas reconnu dans le terminal

- Lancer **XAMPP Control Panel** → bouton **Shell** : il ouvre un terminal où `php` est
  disponible (PHP est déjà dans le `PATH` de ce shell).
- Ou ajouter manuellement le chemin PHP au PATH :
  - Défaut : `C:\xampp\php` (ou `C:\xampp82\php`).
  - Paramètres Windows → « Modifier les variables d'environnement du système » →
    Variables d'environnement → `Path` → Nouveau → coller le chemin → OK →
    **fermer/réouvrir** le terminal.

> Exigences Laravel 12 : PHP >= 8.2 et extensions `pdo_pgsql`, `gd`, `fileinfo`,
> `openssl`, `mbstring`. Toutes sont activées par défaut dans XAMPP. Vérification :
> ```powershell
> php -m | Select-String -Pattern "pdo_pgsql|gd|fileinfo|openssl|mbstring"
> ```

### A.2. Démarrer PostgreSQL et créer la base

```powershell
# Service PostgreSQL (nom exact de la version installée)
Get-Service "postgresql-x64-*"        # connaître le nom exact
Start-Service "postgresql-x64-18"     # adapter le numéro de version si besoin

# Créer la base "panneo" (une seule fois)
& "C:\Program Files\PostgreSQL\18\bin\psql.exe" -U postgres -h 127.0.0.1 -c "CREATE DATABASE panneo;"
# → mot de passe demandé : celui choisi à l'installation (par défaut "postgres")
```

> Si la base existe déjà sur la machine, cette commande échoue avec
> `already exists` : c'est normal, continuez.

### A.3. Cloner le dépôt

```powershell
git clone https://github.com/djossouroger/panneo.git
cd panneo
```

### A.4. Backend Laravel

```powershell
cd C:\...\panneo\backend

# 1. Dépendances
composer install
npm install

# 2. Fichier d'environnement
Copy-Item .env.example .env
php artisan key:generate

# 3. Adapter le .env (ouvrir avec un éditeur : notepad .env)
#    DB_DATABASE=panneo
#    DB_USERNAME=postgres
#    DB_PASSWORD=<votre mot de passe postgres>
#    (le reste du .env.example est déjà prêt pour le local)
notepad .env

# 4. Schéma + données de démonstration (recrée aussi les documents CNI/selfie)
php artisan migrate --seed

# 5. Lien du stockage public (photos)
php artisan storage:link

# 6. Lancer l'API + back-office
php artisan serve --host=0.0.0.0 --port=8001
```

Vérification immédiate :
```powershell
# Dans un AUTRE terminal :
(Invoke-RestMethod "http://localhost:8001/api/v1/health").status   # → ok
```

> Si `composer install` signale une version de PHP trop ancienne : vérifiez le §A.1b
> (c'est le PHP de XAMPP qui doit être utilisé, pas celui de Windows).

### A.5. Mobile Expo

Ouvrir un **second** terminal dans `mobile` :

```powershell
cd C:\...\panneo\mobile

# 1. Dépendances (le .env se crée vide = URL auto)
npm install

# 2. Lancer Expo
npx expo start
```

Scanner le **QR code** avec **Expo Go** (téléphone sur le **même Wi-Fi** que le PC).

### A.6. Vérification « zéro bug » sur le téléphone

1. Dans le navigateur du téléphone :
   `http://<IP-PC>:8001/api/v1/health` → doit afficher `{"status":"ok",...}`.
   - Trouver l'IP : `ipconfig` → ligne **Adresse IPv4** de la carte Wi-Fi.
2. Dans Expo Go : vous devez voir l'écran de **connexion** Pannéo (pas une erreur réseau).
3. Se connecter : `client.demo@panneo.test` (mot de passe dans `docs/DEMO.md`).
4. Le **matching** doit afficher au moins un artisan pour chaque ville (voir §A.7).

### A.7. Point de contrôle « tout fonctionne »

```powershell
# Backend : la suite de tests doit passer
cd C:\...\panneo\backend
php artisan test                     # 149 passed (563 assertions)

# Mobile : typecheck TypeScript sans erreur
cd C:\...\panneo\mobile
npx tsc --noEmit                     # exit 0, aucun message d'erreur
```

Si les deux sorties sont vertes, la démo est **reproductible sans bug** sur ce PC.

---

## Méthode B — Docker Compose (si Docker est installé)

> Alternative quand on ne peut pas (ou ne veut pas) installer XAMPP + PostgreSQL
> séparément. Nécessite **Docker Desktop** sur Windows.

### B.1. Prérequis

- **Docker Desktop** : https://www.docker.com/products/docker-desktop/
- **Git** : https://git-scm.com

### B.2. Cloner

```powershell
git clone https://github.com/djossouroger/panneo.git
cd panneo
```

### B.3. Backend dans Docker

À la racine du dépôt, créer `docker-compose.yml` avec le contenu suivant :

```yaml
services:
  db:
    image: postgres:18
    environment:
      POSTGRES_DB: panneo
      POSTGRES_USER: postgres
      POSTGRES_PASSWORD: postgres
    ports: ["5432:5432"]
    volumes: [pgdata:/var/lib/postgresql/data]

  backend:
    image: composer:2
    working_dir: /app
    volumes:
      - ./backend:/app
      - backend_vendor:/app/vendor
    command: >
      sh -c "composer install --no-interaction &&
             cp -n .env.example .env || true &&
             php artisan key:generate &&
             php artisan migrate --seed &&
             php artisan storage:link &&
             php artisan serve --host=0.0.0.0 --port=8001"
    ports: ["8001:8001"]
    environment:
      DB_CONNECTION: pgsql
      DB_HOST: db
      DB_PORT: 5432
      DB_DATABASE: panneo
      DB_USERNAME: postgres
      DB_PASSWORD: postgres
      OTP_DELIVERY: log
      APP_URL: http://localhost:8001
    depends_on: [db]

volumes:
  pgdata:
  backend_vendor:
```

> Le service `backend` ne contient pas le vrai PHP avec `pdo_pgsql` : pour une utilisation
> réelle, remplacez l'image par une image PHP 8.2 avec l'extension :
> `php:8.2-cli` + `docker-php-ext-install pdo_pgsql`. Si vous n'avez pas le temps,
> préférez la **Méthode A**.

Lancer :

```powershell
docker compose up -d db
# attendre ~5 s (initialisation PostgreSQL), puis :
docker compose up backend
# L'API répond sur http://localhost:8001
```

Mobile : identique à la Méthode A (§A.5), mais remplacez dans le téléphone
`http://<IP-PC>:8001` par `http://<IP-PC>:8001` (l'IP du PC qui héberge Docker —
il n'y a pas de différence, le port 8001 est exposé par Docker).

---

## Méthode C — Récupérer LA BASE PRÊTE depuis votre PC (plan B ultra-rapide)

Si le clonage + install complet n'est pas possible sur le PC de secours, on peut
**transporter l'application déjà configurée** sur une clé USB :

1. Sur votre PC, copier tout le dossier `C:\xampp82\htdocs\panneo` sur une clé USB
   (y compris `backend/.env`, `backend/vendor/`, `backend/node_modules/`, `mobile/node_modules/`).
2. Sur le PC de secours, copier le dossier depuis la clé dans `C:\...\panneo`.
3. Installer **uniquement** : PostgreSQL (créer la base `panneo`, §A.2) et PHP 8.2/XAMPP (§A.1).
4. Lancer directement :
   ```powershell
   cd C:\...\panneo\backend
   php artisan serve --host=0.0.0.0 --port=8001
   # autre terminal
   cd C:\...\panneo\mobile
   npx expo start
   ```
   (Composer/npm ne sont **pas** nécessaires : les dépendances sont déjà installées.)

> **Attention au chemin** : si le dossier n'est pas placé au même chemin que sur votre PC,
> rien ne casse — l'app fonctionne depuis n'importe quel chemin. Seuls `.env` (base de
> données) et l'IP mobile (auto) importent.

---

## Problèmes fréquents et solutions (« zéro bug »)

| Symptôme | Cause | Solution |
| -------- | ----- | -------- |
| `php artisan` → « PHP n'est pas reconnu » | PHP pas dans le PATH | §A.1b (shell XAMPP ou ajout PATH) |
| `SQLSTATE[08006] connection refused` | PostgreSQL arrêté | `Start-Service "postgresql-x64-18"` |
| `password authentication failed` | mauvais mot de passe `.env` | corriger `DB_PASSWORD` dans `backend/.env` |
| `database "panneo" does not exist` | base non créée | §A.2 (`CREATE DATABASE panneo`) |
| Page mobile : erreur réseau | IP / Wi-Fi différent | mêmes Wi-Fi ; vérifier `http://<IP-PC>:8001/api/v1/health` sur le téléphone |
| Les documents artisan (CNI/selfie) vides | storage ancien | `php artisan db:seed` recrée les fichiers (versionnés) |
| `composer install` exige PHP > 8.1 | mauvais PHP actif | §A.1b ; `php -v` doit afficher 8.2 |
| Port 8001 déjà occupé | autre serveur | `php artisan serve --port=8002` + adapter l'URL mobile |
| Expo ne se connecte pas au Metro | pare-feu Windows | autoriser PHP/Node dans le pare-feu (réseau privé) |

---

## Rappel des commandes de lancement « jour J »

```powershell
Start-Service "postgresql-x64-18"          # 1. PostgreSQL

cd C:\...\panneo\backend                     # 2. API + back-office
php artisan serve --host=0.0.0.0 --port=8001

cd C:\...\panneo\mobile                      # 3. Mobile
npx expo start
```

- API : `http://localhost:8001/api/v1`
- Back-office : `http://localhost:8001/admin` (compte `admin.demo@panneo.test`)
- Comptes démo : voir `docs/DEMO.md` (mot de passe commun dans
  `backend/database/seeders/DemoSeeder.php`, non commité ailleurs).