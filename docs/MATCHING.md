# Matching — algorithme de mise en relation

Implémenté dans `app/Services/MatchingService.php`.

## Objectif

À partir d'une demande de dépannage, retourner la liste des **artisans compatibles** que le
client peut contacter, et valider qu'un artisan donné est éligible pour une offre.

## Critères d'éligibilité (TOUS requis)

Un artisan est proposé si et seulement si :

1. **Compte actif** (`users.is_active = true`) et rôle `artisan`.
2. **Compte validé** : `artisan_profiles.verification_status = verified`.
3. **Catégorie** : l'artisan couvre la catégorie de la demande (catégorie primaire ou secondaire
   dans la table pivot `artisan_categories`).
4. **Zone géographique** : la ville/district de la demande est couverte par une
   `artisan_service_area` :
   - district exact (`city` + `district`), **ou**
   - district `null` = toute la ville.
   - Comparaison **insensible à la casse**, espaces trimés.
5. **Horaire** : l'heure de la demande tombe dans un jour + créneau de `artisan_working_hours`
   (jour de la semaine, `start_time`–`end_time`). En dehors des horaires → non proposé.
6. **Disponibilité** :
   - `artisan_profiles.is_available = true`, et
   - aucune **indisponibilité ponctuelle** active (`artisan_unavailabilities` chevauchant la
     date), et
   - **pas d'intervention en cours** (offre acceptée ou demande `in_progress`).

## API exposée

| Méthode | Utilisation |
| ------- | ----------- |
| `candidateArtisans(RepairRequest $request, ?bool $excludeBusy = false)` | Liste paginable des artisans éligibles pour la demande |
| `isCompatible(ArtisanProfile, RepairRequest)` | Booléen : tous les critères ci-dessus |
| `ensureCompatible(ArtisanProfile, RepairRequest)` | `isCompatible` sinon lève `ValidationException` (message clair pour l'API) |
| `isBusy(ArtisanProfile)` | Vrai si intervention acceptée/en cours |

## Notes d'implémentation

- L'appelé (`available-artisans`, `storeOffer`) filtre sur la liste des candidats ; un client
  **ne peut donc pas** contacter un artisan hors critères, même avec un ID.
- Artisans **pending/rejected** : exclus à la source (`candidateArtisans`), et
  `isCompatible` renvoie `false` ; message dédié pour l'offre :
  « Ce dépanneur n'a pas encore été validé par Pannéo. »
- Pas de système de « première offre = gagnante » en V1 : le client choisit parmi les candidats ;
  l'offre acceptée est celle qui passe la demande à `accepted` (voir `docs/REPAIR_WORKFLOW.md`).

## Tests

`tests/Feature/MatchingRulesApiTest.php` (9) + `tests/Feature/ArtisanVerificationGateApiTest.php`
(pending jamais offert) + `RepairRequestOfferApiTest` (incompatibilité refusée).
