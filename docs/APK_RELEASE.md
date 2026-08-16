# Pannéo — Préparation et génération d'un futur APK Android

L'application mobile est prête pour générer un **APK installable** (Android)
via **EAS Build** (cloud Expo). Le domaine de production étant inconnu à ce
stade, **aucun APK final n'est généré maintenant** ; cette page décrit la
procédure exacte à suivre quand l'API sera déployée sur Railway.

---

## 1. Ce qui est déjà en place

| Élément | Statut |
| ------- | ------ |
| Identité de l'app (`app.json`) | `name: Pannéo`, `slug: panneo`, `scheme: panneo` |
| Identifiant Android | `android.package = com.panneo.app` |
| Identifiant iOS | `ios.bundleIdentifier = com.panneo.app` |
| Permissions caméra/photos | Plugin `expo-image-picker` (`cameraPermission`, `photosPermission`, `microphonePermission: false` — pas de RECORD_AUDIO) |
| Profils de build | `mobile/eas.json` : `preview` (APK) et `production` (AAB) |
| Scripts | `npm run typecheck`, `npm run export` (ajoutés) |
| URL API | `lib/api.ts` : plus aucune IP locale codée en dur ; source unique = `EXPO_PUBLIC_API_URL` (dév. : déduction via Metro `hostUri`) |
| Env | `mobile/.env.example` documente la valeur de production ; `mobile/.env` est ignoré par Git |

## 2. Prérequis (une fois, côté développeur)

```bash
cd mobile
npm install --legacy-peer-deps        # ERESOLVE préexistant → contournement documenté
npm run typecheck                     # 0 erreur attendue
npm run export                        # bundle de contrôle (optionnel)
npx eas-cli login                     # compte Expo (owner du projet)
```

> `npm install` peut échouer (conflit `react-dom@19.2.8` vs `react@19.1.0`) :
> utiliser `--legacy-peer-deps`. Cela n'affecte ni `tsc`, ni `expo export`,
> ni le build EAS.

## 3. Définir l'URL de l'API de production

L'URL est **figée dans le bundle au moment du build** : toute variable
`EXPO_PUBLIC_*` est embarquée dans l'APK et lisible par n'importe qui. Il ne
faut y mettre **aucun secret**.

```bash
# Avant de lancer le build (PowerShell Windows)
$env:EXPO_PUBLIC_API_URL="https://<votre-domaine-railway>/api/v1"
```

Alternative : créer `mobile/.env` (ignoré par Git) :

```
EXPO_PUBLIC_API_URL=https://<votre-domaine-railway>/api/v1
```

## 4. Générer l'APK (test interne)

```bash
cd mobile
npx eas-cli build --platform android --profile preview
```

- Profil `preview` (`eas.json`) : `distribution: internal`, `buildType: apk` →
  produit un **APK** installable directement.
- À la fin, EAS affiche une URL de téléchargement (APK) + QR code.
- Installer sur un appareil Android : activer « Sources inconnues » puis
  ouvrir le fichier `.apk`.

## 5. Générer le binaire de production (Play Store)

```bash
npx eas-cli build --platform android --profile production
```

- Profil `production` : `buildType: app-bundle` → produit un **AAB** requis
  pour le Play Store (la version 1.0.0 vient de `app.json`).
- Soumettre via Play Console ; pour iOS, `npx eas-cli build --platform ios`
  (nécessite un compte développeur Apple).

## 6. Vérifications avant de publier

- [ ] `GET https://<domaine>/api/v1/health` → `{"status":"ok"}` depuis le téléphone.
- [ ] Inscription + code e-mail + vérification (SMTP Gmail fonctionnel).
- [ ] Connexion client / artisan / admin.
- [ ] Création d'une demande + photos ; portfolio ; **pièce d'identité + selfie**.
- [ ] Validation artisan côté back-office (`https://<domaine>/admin`).
- [ ] Après redéploiement Railway : données (uploads, comptes) toujours présentes.
- [ ] `SESSION_SECURE_COOKIE=true` (https) sans blocage du login mobile (Bearer token).

## 7. Pièges à éviter

- Ne jamais embarquer `APP_KEY`, `DB_PASSWORD`, `MAIL_PASSWORD` ou tout secret
  dans le bundle mobile.
- Ne pas oublier `--legacy-peer-deps` lors d'un `npm install` dans `mobile/`.
- Un APK construit avec une URL locale ne fonctionnera plus une fois le PC
  éteint : toujours pointer `EXPO_PUBLIC_API_URL` vers le domaine de production.
- Si l'APK n'ouvre pas la caméra/photos : re-vérifier le plugin
  `expo-image-picker` dans `app.json` (permissions `CAMERA`, photo library).

## 8. Commandes de contrôle

```bash
cd mobile
npm run typecheck              # vérification TypeScript
npm run export                 # bundle JS (iOS/Android) de contrôle
npx eas-cli build:list         # liste des builds
```
