# Produit — Pannéo

## Concept

Pannéo met en relation des **clients** ayant un besoin de dépannage à domicile (plomberie,
électricité…) avec des **artisans qualifiés**, géolocalisés, et **préalablement validés**
par la plateforme. L'utilisateur décrit son problème, choisit un artisan compatible et suit
l'intervention jusqu'à l'avis final.

## Rôles

| Rôle     | Exposé à                                   | Fonctions principales |
| -------- | ------------------------------------------ | --------------------- |
| **Client** | Application mobile                          | Créer une demande de dépannage, consulter les artisans disponibles, envoyer une offre, accepter/refuser les réponses, suivre l'intervention, noter, ouvrir un litige, favoris |
| **Artisan** | Application mobile                          | Compléter son profil (métiers, zones, horaires, portfolio), **se soumettre à la validation admin**, gérer sa disponibilité, répondre aux demandes, réaliser l'intervention, notes/recommandations |
| **Admin** | Back-office web (`/admin`)                  | Tableau de bord, gestion des utilisateurs, **validation/rejet des artisans**, suivi des demandes, litiges, catégories, avis |

## Cas d'usage principaux

1. **Inscription** e-mail + téléphone (OTP SMS log), choix du rôle.
2. **Artisan** : complète son profil (3 étapes) → **soumet son dossier de vérification** (CNI +
   selfie avec la pièce, justificatif professionnel en option) → attend la validation admin.
3. **Client** : décrit sa panne (+ jusqu'à 2 photos) → la plateforme propose des artisans
   compatibles → le client en choisit un.
4. **Artisan** (validé, disponible) : reçoit la demande, accepte ou refuse.
5. **Intervention** : l'artisan la démarre, puis la termine → le client note (1–5).
6. **Litige** éventuel pendant le processus, arbitré par l'admin.
7. **Sécurité du compte** : changement d'e-mail/téléphone à double OTP, liste des sessions,
   suppression de compte.

## Principes de l'audit (LOT 08)

- La **vérité** du projet est l'état réel du code, pas les spécifications initiales.
- **Aucune fonctionnalité existante n'a été supprimée** ; aucune nouvelle fonctionnalité
  métier n'a été ajoutée.
- Les deux exigences obligatoires de la V1 sont remplies et **documentées** :
  1. **Mot de passe oublié** réellement fonctionnel (OTP e-mail → réinitialisation, cf. `docs/API.md`).
  2. **Validation administrative des artisans obligatoire** : tout artisan non validé est
     exclu du matching et **bloqué** sur les endpoints métier (`403 ARTISAN_NOT_VERIFIED`,
     cf. `docs/BUSINESS_RULES.md`).

## Notes produit

- Le **paiement** n'existe pas en V1 : l'échange se règle hors plateforme (à la main).
- La **vérification** d'identité repose sur des **documents** : pièce d'identité (CNI, passeport,
  permis) + **selfie** avec la pièce (obligatoires, strictement confidentiels, stockage privé) ;
  un justificatif professionnel (diplôme, attestation, facture) peut être ajouté en option.
- La communication client ↔ artisan passe par le **téléphone** (débloqué après acceptation).
- Le **SMS** d'OTP est implémenté via un fournisseur « log » (`LogSmsProvider`) : le code est
  écrit dans les logs (`storage/logs/laravel.log`). À remplacer par un vrai transporteur en prod.
- L'**e-mail** d'OTP est envoyé par SMTP (`OTP_DELIVERY=mail`, Gmail configuré dans `.env`) :
  inscription (vérification e-mail obligatoire avant le premier login), mot de passe oublié,
  changement d'e-mail.
