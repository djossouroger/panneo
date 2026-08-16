# Fonctionnalités existantes hors périmètre initial

L'audit a identifié des fonctionnalités **déjà implémentées** qui dépassent le périmètre
minimum V1. Elles sont conservées et testées (rien n'a été supprimé).

## Compte & sécurité
- **Vérification e-mail obligatoire à l'inscription** : code envoyé à la création du compte,
  écran « Vérifiez votre adresse e-mail » dans l'app, login bloqué (`403 EMAIL_NOT_VERIFIED`)
  tant que l'e-mail n'est pas confirmé (`auth/email-verify/send` + `confirm`).
- Vérification téléphone par OTP (`auth/phone/send-code`, `verify`) et `phone_verified_at`.
- Changement d'e-mail et de téléphone **à double OTP** vers la nouvelle coordonnée.
- Gestion des **sessions** (liste, révocation unitaire, révocation des autres avec mot de passe).
- **Suppression de compte** en libre-service (mot de passe requis, purge cascade, blocage si
  intervention en cours).
- **Journal d'audit de sécurité** (`security_audit_logs`, `AuditLogger`).

## Artisan
- **Portfolio** de réalisations (upload/suppression de photos).
- **Indisponibilités ponctuelles** (création/annulation) en plus des horaires fixes.
- **Catégories multiples** (primaire + secondaires) au lieu d'une seule.
- **Zones d'intervention** multi-villes/districts avec notion « toute la ville ».
- **Photo de profil** côté artisan et côté client.
- **Statistiques** du profil (note moyenne, interventions terminées, favoris).

## Client / social
- **Favoris** artisans (après une intervention terminée uniquement).
- **Notifications applicatives** complètes (liste, non-lues, lecture globale, cloche mobile).
- **Profil public** d'artisan avec photo, portfolio, note moyenne.
- Photos (jusqu'à 2) sur les demandes de dépannage.

## Litiges
- **Module complet de litiges** (ouverture par un participant, statuts, arbitrage admin,
  notifications aux deux parties).

## Back-office
- Tableau de bord avec KPIs.
- Gestion des **utilisateurs** (bascule actif/inactif).
- Édition des **catégories** (nom, prix indicatifs, actif).
- Modération des **avis**.

## Dispositif de vérification (détail)
- Soumission **documents** (pièce d'identité + **selfie avec la pièce** obligatoires, justificatif
  professionnel optionnel), images JPG/PNG/WEBP ≤ 5 Mo, stockage privé ; annulation d'une
  soumission, historique, rejet avec motif + resoumission, réouverture admin.
- **Selfie** : intégré au dispositif V1 (exigence produit) — pièce d'identité et selfie sont
  comparés visuellement côté back-office (images côte à côte), sans reconnaissance faciale
  automatique.

> Toutes ces fonctionnalités sont couvertes par les tests Feature existants
> (15 fichiers, 146 tests) et documentées dans `docs/`.
