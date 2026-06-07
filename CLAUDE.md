# CLAUDE.md — web_engine (SiVote Engine)

Core of the voting system: **ballot creation & display, voting-code generation, vote collection, and result display**. Part of the [E-Voting superproject](../CLAUDE.md); pairs with `web_sender`.

## Stack

- **Laravel 9** (target: **12** — see superproject CLAUDE.md), PHP 8.x (dev image: 8.4)
- Livewire **2.7**, `doctrine/dbal` **2.\***, `league/csv`, `goldspecdigital/laravel-eloquent-uuid` 9, `predis`
- `fruitcake/laravel-cors` 2 (CORS is built into the framework from Laravel 9+, so this is removable on upgrade)
- Dev: PHPUnit 9, Paratest 6, `nunomaduro/larastan` 1 (static analysis), Collision 6
- `minimum-stability: dev` (tighten to `stable` during upgrade)

## Testing

```bash
php artisan test          # Unit + Feature suites
./vendor/bin/phpunit
./vendor/bin/paratest     # parallel
./vendor/bin/phpstan analyse   # larastan
```

## Domain CLI (custom artisan commands)

`evote:cache` plus ballot/election management: `BallotCreate`, `BallotList`, `BallotCodesGenerate`, `BallotComponentCreate`, `BallotComponentList`, `ElectionCreate`, `ElectionList` (see `app/Console/Commands`).

## Upgrade watch-list (9 → 12)

- **Livewire 2 → 3** is the biggest breaking change (component syntax, lifecycle, Alpine).
- `doctrine/dbal` 2 → 3/4 (column-change migrations) — Laravel 11+ reduces the need for it.
- Remove `fruitcake/laravel-cors`; use the framework's `config/cors.php`.
- `goldspecdigital/laravel-eloquent-uuid` may be replaceable by native `HasUuids`.
- `larastan` 1 → 2/3, Collision 6 → 7/8, PHPUnit 9 → 10/11, Paratest 6 → 7.
- `minimum-stability` → `stable`.
