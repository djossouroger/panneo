#!/bin/bash
# =====================================================================
# Pannéo — Initialisation de l'application Laravel sur Railway.
#
# Conçu pour être exécuté en tant que "Pre-Deploy Command" du service App :
#   chmod +x railway/init-app.sh && sh railway/init-app.sh
#
# Notes Railway (Railpack / FrankenPHP) :
#  - Railpack installe déjà composer + npm et lance `npm run build` à la
#    construction ; ce script ne les rejoue que si l'image ne les contient pas.
#  - Le démarrage Railpack exécute lui-même `php artisan migrate --force`,
#    `php artisan storage:link` et l'optimisation : ce script reste idempotent.
#  - Le volume persistant doit être monté sur le chemin défini par
#    PERSISTENT_STORAGE_PATH (ex: /data). Les uploads y survivent aux redéploiements.
#  - Aucun service worker ni cron n'est nécessaire (e-mails synchrones,
#    aucun scheduler de jobs) : voir docs/RAILWAY_DEPLOYMENT.md.
# =====================================================================
set -e

echo "=== Pannéo : initialisation Laravel ==="

if [ ! -d vendor ]; then
  echo "-> composer install"
  composer install --no-interaction --optimize-autoloader --no-dev
fi

if [ ! -d node_modules ]; then
  echo "-> npm install"
  npm install --no-audit --no-fund
fi

if [ ! -d public/build ]; then
  echo "-> npm run build (assets Vite)"
  npm run build
fi

if [ -z "$APP_KEY" ]; then
  echo ""
  echo "!!! ATTENTION : APP_KEY absente. Génération temporaire !!!"
  echo "!!! Définissez APP_KEY comme variable Railway PERSISTANTE    !!!"
  echo "!!! (php artisan key:generate --show) pour que les sessions   !!!"
  echo "!!! survivent aux redéploiements.                             !!!"
  echo ""
  php artisan key:generate --force --no-interaction
fi

echo "-> migrations"
php artisan migrate --force

echo "-> seeds (catégories + admin ; démo désactivée en production)"
php artisan db:seed --force

echo "-> caches"
php artisan optimize:clear
php artisan config:cache
php artisan event:cache
php artisan route:cache
php artisan view:cache

echo "=== Pannéo : initialisation terminée ==="
