# Design system mobile

Tous les composants UI vivent dans `components/ui.tsx` ; les couleurs dans `lib/` (palette
`colors`). L'app est en **thème clair**, style « cartes arrondies ».

## Palette

| Rôle | Usage |
| ---- | ----- |
| `colors.primary` (bleu) | Actions principales, liens, accents |
| `colors.primaryLight` | Fond des blocs / avatars |
| `colors.text` / `colors.muted` | Texte principal / secondaire |
| `colors.border` | Cartes, séparateurs |
| `colors.success` (vert) | Disponibilité, succès |
| `colors.urgent` (orange) | Alertes, « En validation », indisponibilité |
| `colors.danger` (rouge) | Erreurs, suppression |
| `colors.white` / `colors.background` | Surfaces |
| `colors.warning` (jaune) | Avertissements |
| `colors.info` (bleu clair) | Info |

## Composants principaux (`components/ui.tsx`)

- `ScreenContainer` — layout racine (safe area, fond, padding).
- `AppButton` — bouton primaire / `secondary` / `danger`, variante `variant`, état `disabled`,
  `loading`.
- `FormField` + `AppTextInput` — champs de formulaire avec label, erreur inline, props `secure`,
  `multiline`, `keyboardType`.
- `AppHeader` — header de sous-écran (titre + retour).
- `SectionTitle` — titre de section.
- `AppCard` — carte standard (padding, bordure, radius).
- `Chip` — étiquette (statut, métier).
- `Badge` — compteur (ex. notifications, offres en attente).
- `Avatar` / `AvatarGroup` — photo de profil + fallback initiales.
- `EmptyState` — état vide (icône + titre + texte).
- `Loader` — spinner plein écran ; `LoadMore` — pagination.
- `Segment` — sélecteur segmenté (onglets locaux).
- `PickerSheet` — sélecteur modal (ActionSheet style).
- `ConfirmSheet` — confirmation destructive.

## Composants métier

- `components/repairRequests.tsx` :
  - `RepairRequestCard` — demande côté client (statut, catégorie, adresse, photos).
  - `InterventionCard` — intervention artisan (client masqué si pas acceptée, contacts visibles
    après acceptation).
  - `IncomingRequestCard` — demande reçue par l'artisan (client masqué tant que `pending`).
- `components/notifications.tsx` — `NotificationBell` (cloche + compteur non-lues).

## Écrans « état »

- **États de chargement** : `Loader` (plein écran) / `ActivityIndicator` inline.
- **États d'erreur** : `ErrorMessage` (bandeau) + bouton « Réessayer ».
- **États vides** : `EmptyState` (ex. « Aucune demande pour le moment »).
- **Artisan non vérifié** : écrans d'état dédiés sur `home/artisan.tsx` — « Compte en cours de
  validation » (icône `Clock3`, date de soumission) ou « Compte non validé » (icône `CircleAlert`,
  motif du rejet) — avec boutons vers `artisan/verification` et « Se déconnecter ». Notice
  similaire sur `(tabs)/requests.tsx`.

## Conventions

- Espacement standard 16, radius 12, ombres légères ; texte ≥ 12px, titres bold 16–18px.
- Icônes `lucide-react-native`.
- Accessibilité : `accessibilityLabel` sur les boutons-iconés.
- Interdiction d'utiliser des emojis dans l'UI sauf demande explicite.
