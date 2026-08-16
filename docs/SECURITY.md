# Sécurité

## Authentification API

- **Sanctum** : token personnel par connexion (`Authorization: Bearer`).
- `/auth/login` : `throttle:5,1` (5 essais / minute).
- OTP (e-mail et téléphone) : `throttle:3,15` sur l'envoi/renvoi.
- **Vérification e-mail à l'inscription** : code envoyé à la création du compte, login bloqué
  (`403`, `code: EMAIL_NOT_VERIFIED`) tant que `email_verified_at` est nul ; `send` neutre
  (pas de fuite d'existence), `confirm` OTP usage unique 10 min (throttle 5/1 min).
- Mot de passe oublié : `throttle:5,1`, réponse identique que le compte existe ou non
  (pas de fuite d'existence), OTP à **usage unique** (`used_at`) et expirant (10 min).
- **Anti-énumération sur la réinitialisation** : `POST /auth/password/reset` renvoie le même
  message (« Ce code est invalide ou a expiré. ») que l'e-mail existe ou non — l'endpoint ne
  distingue jamais « e-mail introuvable ».

## Contrôle d'accès (middlewares)

- `EnsureAccountIsActive` (`active`) : tout compte `is_active=false` est exclu (`403`).
- `EnsureRole` (`role:client` / `role:artisan`) : sépare strictement les capacités.
- `EnsureArtisanVerified` (`artisan.verified`) : **nouveau en LOT 08** — `403 ARTISAN_NOT_VERIFIED`
  sur availability / offres / repair-requests tant que `verification_status != verified`.
- `AdminOnly` : back-office web réservé au rôle `admin`.

## Protection des données

- **Documents de vérification** (pièce d'identité + selfie) : stockés sur le disque **`local`**
  (`storage/app/private/...`, jamais servis publiquement), noms de fichiers **générés côté
  serveur**, MIME vérifié (images JPG/PNG/WEBP ≤ 5 Mo pour la pièce/le selfie, image/PDF pour le
  justificatif). Téléchargement restreint à l'artisan propriétaire ou à un admin
  (`authorize` + garde) ; affichage inline réservé au back-office (route admin). Jamais exposés
  dans les profils publics.
- **Profil public artisan** : n'expose pas téléphone/coordonnées tant que l'offre n'est pas
  acceptée ; réservé aux artisans actifs et `verified` (404 sinon).
- **Repair requests / offres / avis / litiges** : propriétaire uniquement (policy + vérifications
  en contrôleur) ; un client ne peut pas voir la demande d'un autre.
- **Notifications** : un utilisateur ne peut lire/marquer que ses propres notifications.

## Sessions & comptes

- Liste des sessions / révocation individuelle ; révocation des autres sessions avec mot de passe.
- Changer e-mail/téléphone : OTP envoyé vers la **nouvelle** coordonnée.
- Suppression de compte : mot de passe requis, blocage si intervention en cours, purge cascade.
- Reset de mot de passe : révoque les autres sessions.

## Entrées utilisateur

- Validation Laravel stricte (Form Requests) : types, longueurs (comment ≤ 500, etc.), plages
  (rating 1–5), formats (e-mail, téléphone E.164 pour la démo, horaires).
- Uploads : **MIME vérifiés** (images pour photos/portfolio ; **pièce d'identité + selfie
  obligatoirement images**, justificatif professionnel image/PDF), taille bornée (5 Mo), les
  photos publiques sont dans `storage/app/public` (lien `storage`), les **documents d'identité
  restent privés** (`storage/app/private`), noms d'origine conservés en base mais chemins
  internes sûrs.
- SQL via Eloquent (requêtes paramétrées) ; pas d'échappement manuel.

## Logs d'audit

`AuditLogger` → `security_audit_logs` : actions sensibles (validation/rejet de vérification,
changement d'e-mail/téléphone, suppression de compte, révocation de sessions, statuts
utilisateurs).

## Anti-abuse / opérations

- Rate limiting global Laravel + throttles ciblés sur login/OTP.
- Mode d'envoi configurable via `OTP_DELIVERY` : `log` (défaut, codes dans
  `storage/logs/laravel.log`) ou `mail` (vrai e-mail SMTP pour le canal e-mail, Gmail
  configuré). **À remplacer par un transporteur réel en production** pour le SMS.

## Points de vigilance avant mise en production

1. Remplacer `APP_KEY`, `APP_URL`, configurer PostgreSQL de prod et `php artisan config:cache`.
2. HTTPS forcé (middleware `TrustProxies`/`SecureUrl`) + proxy inverse.
3. Vraie passerelle SMS (implémenter `SmsProviderInterface`) et SMTP de production.
4. Bascule de la queue `database` vers un worker dédié (queue:work) en prod.
5. Sauvegardes automatiques PostgreSQL + disque `storage`.
6. Limiter l'upload des documents (taille) côté serveur (validation déjà en place, à confirmer
   en prod) et purger les fichiers orphelins.
7. Surveiller les `security_audit_logs` et les tentatives de connexion.
