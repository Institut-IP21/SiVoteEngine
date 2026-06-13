# CLAUDE.md — web_engine (SiVote Engine)

Core of the voting system: **ballot creation & display, voting-code generation, vote collection, and result display**. Part of the [E-Voting superproject](../CLAUDE.md); pairs with `web_sender`.

## Stack

- **Laravel 12**, PHP `^8.2` (dev image: 8.4)
- Livewire **3**, `doctrine/dbal` **3**, `league/csv`, `predis` 1.1
- **UUIDs**: local `app/Models/Concerns/HasUuidV4.php` (random v4). Deliberately **not** native ordered `HasUuids` — ordered/sequential UUIDs would leak the order votes were cast, breaking ballot secrecy. Replaced the old `goldspecdigital/laravel-eloquent-uuid`.
- CORS is the framework's native `config/cors.php` (the old `fruitcake/laravel-cors` is gone).
- Dev: PHPUnit **11**, Paratest **7**, `larastan/larastan` **3** (phpstan 2), Collision **8**, `spatie/laravel-ignition` **2**

## Testing & static analysis

```bash
php artisan test                                          # Unit + Feature suites
./vendor/bin/paratest                                     # parallel
php -d memory_limit=1024M vendor/bin/phpstan analyse --no-progress
```

PHPStan is at **level 8**, clean (no baseline). `phpstan.neon` uses `checkModelProperties`; framework scaffolding (Exceptions, `Http/Kernel.php`, middleware, providers, console kernel) is excluded. After big edits, `vendor/bin/phpstan clear-result-cache`.

## Domain CLI (custom artisan commands)

`evote:cache` plus ballot/election management: `BallotCreate`, `BallotList`, `BallotCodesGenerate`, `BallotComponentCreate`, `BallotComponentList`, `ElectionCreate`, `ElectionList` (see `app/Console/Commands`).

## Notes / gotchas

- **Vote-integrity fix landed during the upgrade**: vote submission now verifies a vote belongs to the ballot it's being cast on (`$vote->ballot->id !== $ballot->id`) — previously a vote from another ballot could be accepted. Treat ballot/vote ownership checks as core security; pitch any change before touching them.
- `minimum-stability` is still **`dev`** in `composer.json` — tighten to `stable` when convenient (the other two apps are already `stable`).
- `Dockerfile.dev` / `Dockerfile.prod` templates still target PHP 8.2 and lack the `intl` extension — sync with the active 8.4 image when convenient.
- Upgrade work lives on `upgrade/laravel-12` (draft PR **SiVoteEngine#10** → `master`).
