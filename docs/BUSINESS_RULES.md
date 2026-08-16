# Règles métier

Inventaire des règles implémentées côté serveur (source de vérité : contrôleurs, services,
middlewares, tests).

## Comptes & sécurité

- Inscription : e-mail + téléphone uniques ; rôle `client` ou `artisan` (pas `admin`).
- Téléphone vérifié par OTP avant utilisation sensible (changement de numéro, suppression…).
- Compte `is_active = false` ⇒ toutes les routes API bloquées (`active` middleware), retour
  `403 account_inactive`.
- Changement d'e-mail / téléphone : OTP envoyé vers la **nouvelle** adresse / numéro.
- Suppression de compte : mot de passe requis ; **bloquée si l'artisan a une intervention en
  cours** ; révoque toutes les sessions ; purge en cascade.
- Liste des sessions (tokens) ; révocation des autres sessions nécessite le mot de passe ;
  le mot de passe oublié révoque les autres sessions.
- `SecurityAuditLog` trace : connexions sensibles, changements de coordonnées, suppressions.

## Validation artisan (exigence V1)

- Tout artisan **doit** être `verified` pour faire le métier.
- **Blocage serveur** (`EnsureArtisanVerified` → `403` `code: ARTISAN_NOT_VERIFIED`) sur :
  - `PATCH /artisan/availability`
  - tout le groupe `/artisan/offers/*`
  - tout le groupe `/artisan/repair-requests/*` (dont `start` et `complete`)
- **Matching** : les artisans non `verified` ne sont jamais proposés (`MatchingService`).
- Un client ne peut pas envoyer d'offre à un artisan non `verified` (`422`).
- Accès **libre** malgré le statut : profil, catégories, zones, horaires, indisponibilités,
  portfolio, vérification (l'artisan prépare son dossier avant validation).
- Une soumission `pending` par artisan à la fois ; annulable ; réouverture possible par l'admin.
- Documents privés : l'artisan ne peut télécharger que les siens ; admin toujours autorisé.

## Workflow d'inscription & de validation artisan (V1)

1. **Inscription en 3 étapes** (mobile) : profil (nom, e-mail, téléphone, mot de passe) →
   activité (métier principal, ville, quartier) → **identité** (pièce d'identité + selfie).
2. **Dossier obligatoire** : `identity_document` **+ `selfie`** (images JPG/PNG/WEBP ≤ 5 Mo,
   contrôle du MIME réel) ; `professional_proof` (image ou PDF) **optionnel** ; 2 à 4 documents
   par soumission. Les fichiers sont stockés en privé, noms générés côté serveur.
3. Dès l'inscription, `verification_status = pending` ; après soumission du dossier, l'artisan
   voit un **écran dédié « Compte en cours de validation »** (avec date de soumission) tant que
   l'admin n'a pas statué.
4. L'admin examine les images **côte à côte** (comparaison visuelle pièce ↔ selfie, sans
   reconnaissance faciale automatique) :
   - **Valider** (via modal « Valider cet artisan ? ») → `verified` + notification
     « Votre compte a été validé » (`artisan_account_verified`).
   - **Refuser le dossier** (motif obligatoire) → `rejected` + notification avec le motif.
5. En cas de rejet, l'artisan voit un **écran dédié « Compte non validé »** (avec le motif) et
   peut soumettre un nouveau dossier, qui repasse `pending`.

## Matching & disponibilité

Voir `docs/MATCHING.md` pour le détail. Règles de disponibilité :
- Artisan `verified` **et** `is_available` **et** pas d'intervention en cours.
- Pendant une intervention acceptée, `is_available` est forcé à `false` (et le toggle manuel
  est refusé).
- Au `complete`, l'artisan redevient automatiquement disponible.

## Demande de dépannage

- Création : catégorie active, description, adresse/zone, 0–2 photos.
- Propriétaire seul peut voir/annuler ; artisan ne peut pas créer de demande client.
- `pending` : annulable par le client (une fois).
- `awaiting_artisan` : au moins un artisan disponible doit exister, sinon `422`.
- Une seule offre **active** par demande ; l'artisan ne peut avoir qu'une offre par demande.

## Offres & interventions

- `accept` : offre `pending` → `accepted` ; demande → `accepted` ; artisan devient
  indisponible ; contacts client débloqués pour l'artisan ; notification client.
- `reject` : offre `rejected` ; demande repasse `awaiting_artisan` ; l'artisan n'est pas
  re-proposé pour cette demande.
- Client annule pendant `awaiting_artisan` ⇒ les offres `pending` passent `cancelled`.
- `start` : une seule fois, artisan accepté uniquement, demande `accepted` → `in_progress`,
  `started_at` posé ; client notifié.
- `complete` : une seule fois, `in_progress` → `completed`, `completed_at` posé, artisan
  redevient disponible, client notifié (une seule fois).

## Avis & litiges

- Avis réservé au client propriétaire, uniquement sur demande `completed`.
- `rating` entier 1–5, `comment` ≤ 500 caractères, **une seule fois** par demande (contrainte
  unique en base). Un artisan ne peut pas s'auto-évaluer.
- Litige : ouvert par un participant (client sur `accepted`, artisan sur `completed`, ou l'un
  des deux selon l'état) ; jamais sur `pending`. Liste visible par les participants seulement.
- L'admin met à jour le litige ; les deux participants sont notifiés.

## Notifications

- Événements : offre créée (→ artisan), offre acceptée/refusée (→ client), intervention
  démarrée/terminée (→ client), avis (→ artisan), validation de vérification (→ artisan,
  `artisan_account_verified`), rejet de vérification avec motif (→ artisan),
  mise à jour de litige (→ participants).
- Ordre décroissant ; lecture individuelle ou globale ; compteur de non-lues.
- Envois e-mail réels via files `database` ; en dev, driver `log`.

## Divers

- Profil public d'un artisan : réservé aux artisans actifs **et `verified`** (`404` sinon) ;
  n'expose pas les données privées (documents, téléphone tant que pas accepté).
- Favoris : réservés aux clients, uniquement **après une intervention terminée** avec l'artisan.
- Catégories : inactives refusées à la création de demande ; prix indicatifs affichés.
