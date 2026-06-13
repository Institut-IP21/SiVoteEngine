# CLAUDE.md — web_engine (SiVote Engine)

Core of the voting system: **ballot creation & display, voting-code generation, vote collection, and result display**. Part of the [E-Voting superproject](../CLAUDE.md); pairs with `web_sender`.

## Stack

- **Laravel 13**, PHP `^8.4` (dev image: 8.4)
- Livewire **4**, `league/csv` **9**. **No Redis** — cache is `file`, queue runs on `sync`/`database` (`predis` and `doctrine/dbal` removed).
- **Component architecture**: ballot types live under `app/BallotComponents/<Type>/v1`, resolved through `Contracts/BallotComponentInterface` + `Support/ComponentRegistry`, returning **sealed result DTOs** (`app/BallotComponents/DTOs`). `BallotService` drives result calculation over the registry. (This is the collaborator's architecture; the **D1–D11** domain semantics below are our overlay on top of it.)
- **UUIDs**: local `app/Models/Concerns/HasUuidV4.php` (random v4). Deliberately **not** native ordered `HasUuids` — ordered/sequential UUIDs would leak the order votes were cast, breaking ballot secrecy. Replaced the old `goldspecdigital/laravel-eloquent-uuid`.
- CORS is the framework's native `config/cors.php` (the old `fruitcake/laravel-cors` is gone).
- Dev: PHPUnit **13**, Paratest **7**, `larastan/larastan` **3** (phpstan 2), Collision **8**, `spatie/laravel-ignition` **2**; `rector/rector` **2** + `barryvdh/laravel-ide-helper` **3** for mechanical type-declaration / model-property work

## Testing & static analysis

```bash
php artisan test                                          # Unit + Feature suites
./vendor/bin/paratest                                     # parallel
php -d memory_limit=1024M vendor/bin/phpstan analyse --no-progress
```

PHPStan is at **level 8**, clean (no baseline). `phpstan.neon` uses `checkModelProperties`; framework scaffolding (Exceptions, middleware, providers) is excluded. On the L13 slim skeleton there is no `app/Http/Kernel.php` — middleware/route wiring lives in `bootstrap/app.php`. After big edits, `vendor/bin/phpstan clear-result-cache`.

## Domain CLI (custom artisan commands)

`evote:cache` plus ballot/election management: `BallotCreate`, `BallotList`, `BallotCodesGenerate`, `BallotComponentCreate`, `BallotComponentList`, `ElectionCreate`, `ElectionList` (see `app/Console/Commands`).

## Notes / gotchas

- **Vote-integrity fix landed during the upgrade**: vote submission now verifies a vote belongs to the ballot it's being cast on (`$vote->ballot->id !== $ballot->id`) — previously a vote from another ballot could be accepted. Treat ballot/vote ownership checks as core security; pitch any change before touching them.
- `minimum-stability` is still **`dev`** in `composer.json` — tighten to `stable` when convenient (`web_app` is already `stable`; `web_sender` is also still `dev`).
- The PHP image doesn't install `intl`; add it only if a dependency starts requiring it.
- **As of the 2026-06-13 cutover this is `master`** (the integrated L13 work). The old `upgrade/laravel-12` branch + PR **SiVoteEngine#10** are merged/superseded; pre-cutover state is on tag `master-pre-integration-2026-06-13`. See the superproject `CLAUDE.md` for the full integration story.
