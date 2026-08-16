# Cycle de vie d'une demande de dépannage

## États (`RepairRequest.status`)

```
                  client annule (pending uniquement)
   pending  ───────────────────────────────────────► cancelled
      │
      │  catégorie active + au moins 1 artisan éligible
      ▼
 awaiting_artisan ───────────────► cancelled      (client annule pendant l'attente)
      │
      │  artisan accepte l'offre
      ▼
    accepted                        ◄── offers pending ──► cancelled (annulation client)
      │
      │  artisan démarre
      ▼
  in_progress
      │
      │  artisan termine
      ▼
   completed  ──► avis (1–5) ──► litige éventuel ──► résolu par l'admin
```

## Acteurs et transitions

| Transition | Déclencheur | Effets |
| ---------- | ----------- | ------ |
| `pending` → `awaiting_artisan` | Création réussie | Génère les candidats (matching), notifications possibles |
| `*` → `cancelled` | Client (`PATCH …/cancel`) | Seulement depuis `pending` (ou offre annulée si `awaiting_artisan` et aucune offre acceptée) ; offres `pending` → `cancelled` |
| `awaiting_artisan` → `accepted` | Artisan `POST /artisan/offers/{offer}/accept` | Offre `accepted`, artisan indisponible, contacts débloqués |
| `awaiting_artisan` → `awaiting_artisan` | Artisan `reject` | Offre `rejected`, artisan non reproposé ; si c'était la seule offre, la demande reste en attente |
| `accepted` → `in_progress` | Artisan `POST …/start` (une fois) | `started_at` ; client notifié |
| `in_progress` → `completed` | Artisan `POST …/complete` (une fois) | `completed_at` ; artisan redevient disponible ; client notifié |

## Garde-fous (défendus par les tests)

- `start` : uniquement l'artisan **accepté**, état `accepted`, une seule fois.
- `complete` : uniquement l'artisan accepté, état `in_progress`, une seule fois.
- Annulation : propriétaire uniquement, état `pending` (ou demande non acceptée).
- Avis : après `completed`, par le client propriétaire, une fois.
- Litige : à partir de `accepted` (client) ou `completed` (artisan/client), par un participant.
- Pendant `accepted`/`in_progress`, le toggle manuel de disponibilité est refusé
  (`409/422`) et `is_available` reste `false`.

## Fin de vie

- **completed** : l'artisan réintègre le matching (disponible + hors horaires selon l'heure).
- **cancelled** : aucune nouvelle offre possible sur cette demande.
- Les données (photos, avis, litiges) sont conservées pour l'historique et la modération admin.

## Tests

`tests/Feature/RepairRequestApiTest.php`, `RepairRequestOfferApiTest.php`,
`RepairRequestInterventionApiTest.php`, `ReviewApiTest.php`, `DisputeApiTest.php`.
