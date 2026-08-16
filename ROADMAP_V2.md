# Roadmap V2 — Pannéo

> Document de vision pour la version 2. Les éléments ci-dessous sont des **suggestions**,
> classées par priorité, à valider avant tout développement. Rien ici n'engage une
> implémentation : l'audit (LOT 08) n'a volontairement **pas** ajouté de fonctionnalité métier.

## 1. Post-paiement (priorité haute)

- **Paiement en ligne** (Mobile Money Bénin : MTN MoMo / Moov Money / Celtiis, via un
  agrégateur type CinetPay) avec caisse de dépôt :
  - prix estimé au moment de l'acceptation de l'offre,
  - encaissement au passage en `completed`,
  - **commission plateforme** prélevée automatiquement.
- Factures / reçus PDF, export comptable CSV.
- **Séquestre litiges** : blocage du règlement tant qu'un litige est ouvert.

## 2. Expérience client

- Notifications push (expo-notifications + envoi serveur FCM/APNs).
- Chat intégré client ↔ artisan pendant l'intervention (aujourd'hui : téléphone).
- Replanification / annulation tardive avec motif et pénalité éventuelle.
- Devis multi-artisans comparables (le client sélectionne, pas seulement le premier accepté).
- Suivi temps réel de la position de l'artisan pendant le déplacement (avec consentement).

## 3. Expérience artisan

- Agenda mensuel et planning auto-généré.
- Statuts financiers (gains, litiges, commission) dans le profil.
- Abonnements / badges (profil premium, mise en avant dans le matching).

## 4. Confiance & sécurité

- **OCR des documents d'identité** + vérification automatisée de la correspondance
  selfie ↔ pièce (reconnaissance faciale) : le selfie fait déjà partie de la V1, la
  **comparaison reste manuelle** (visuelle) en V1.
- Vérification du numéro de téléphone au niveau du transporteur (opérateur) plutôt que SMS log.
- Assurance responsabilité professionnelle / garantie travaux.

## 5. Plateforme & technique

- Rate limiting par IP sur l'inscription (protection anti-abuse).
- File d'attente réelle pour l'envoi des e-mails/SMS (déjà le squelette avec les jobs).
- Monitoring APM + logs centralisés, alertes (deployed Sentinel / Laravel Pulse).
- Mise en production : HTTPS forcé, proxy, backups automatisés PostgreSQL, staging.
- CI/CD (GitHub Actions : `pint`, `php artisan test`, `tsc`, `expo export`).
- Recherche plein-texte et filtres avancés dans le back-office.
- Notifications Web pour l'admin (nouvelle demande de vérification, litige…).

## Priorisation recommandée (MVP V2)

1. Paiement Mobile Money + commission plateforme
2. Notifications push
3. Reconnaissance faciale automatisée selfie ↔ pièce (OCR)
4. Chat client ↔ artisan
5. Monitoring + CI/CD
