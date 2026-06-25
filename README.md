<div align="center">

# SiVote Engine

**Secret-ballot voting for organizations — understandable, auditable, no blockchain.**

The core of the [SiVote](https://github.com/Institut-IP21) platform: it creates ballots, generates
single-use voting codes, collects votes, and computes results. Pairs with
[SiVote Sender](https://github.com/Institut-IP21/SiVoteSender) for voter management and email delivery.

[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
![PHP 8.4](https://img.shields.io/badge/PHP-8.4-777BB4)
![Laravel 13](https://img.shields.io/badge/Laravel-13-FF2D20)

</div>

---

## Why SiVote

E-voting usually fails one of two ways: it's a cryptographic puzzle no voter understands, or it's a
closed box you have to trust blindly. SiVote takes the opposite bet — **deliberately simple, secret by
design, and fully open** so any organization can read the code that runs its elections.

- **Secret** — on a secret ballot there is no stored link between a voter and their vote (`cast_by` is
  never set), votes are encrypted at rest, and vote IDs are random v4 UUIDs so insertion order can't
  leak the order ballots were cast.
- **Verifiable** — every voter receives the full anonymized list of submitted ballots and can check,
  with their own secret code, that their vote was recorded and the tally is correct. Because each voter
  holds their own copy, a falsified result can't be shown selectively — no cryptography degree required.
- **Secrecy that scales** — three levels, from fully platform-run to a setup where your electoral
  commission distributes codes (via [SiVoteHomeSender](https://github.com/Institut-IP21/SiVoteHomeSender))
  and proxies vote submission so the platform never sees the voter at all.
- **Two ways to run it** — self-host the engine + sender with no GUI and drive it via the `evote:*`
  artisan CLI, or use the hosted GUI at [eglasovanje.si](https://eglasovanje.si) (free for smaller
  organizations).

> **Not for state elections — on principle.** The authors are opponents of e-voting for governmental
> elections: there it adds critical risks and no real benefit. SiVote is built for **private,
> democratic organizations** (associations, cooperatives, unions, parties) where remote secret voting
> genuinely helps. See [docs/SECURITY_MODEL.md](docs/SECURITY_MODEL.md) for the full model.

We've published articles explaining the model in depth at
[eglasovanje.si/vsi-clanki](https://eglasovanje.si/vsi-clanki).

## What the engine does

- Elections → ballots → questions (components) → votes → results.
- Ballot question types: **First-past-the-post**, **Yes/No**, **Approval**, **Ranked choice (IRV)**
  with per-round elimination rationale, ballot accounting, and a first-preference matrix.
- **Quorum** support: a ballot can require a minimum turnout; the result is only binding if met.
- Two ballot modes: **basic** (codes distributed externally / by the Sender) and **session**
  (on-location live voting).
- Per-ballot, Markdown results-email templates and org branding (logo + accent colour).

## Requirements

- PHP **8.4**, Composer
- MySQL **8+**
- File cache + sync/database queue — **no Redis required**
- Laravel 13

## Quick start (local)

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan evote:cache        # warms the ballot-component registry cache
php artisan serve              # or front it with nginx / the included Docker setup
```

For a production self-hosted stack (engine + sender + db + mail, **no GUI**) see
**[docs/SELF_HOSTING.md](docs/SELF_HOSTING.md)** — it walks the full lifecycle via the `evote:*` artisan
CLI: create election → add questions → generate codes → (Sender) invite voters → collect → results.

## Configuration

Key `.env` values:

| Variable | Purpose |
| --- | --- |
| `APP_URL` | Public URL of the engine (voters hit it directly) |
| `APP_LOCALE` | Default language (`sl` / `en`) |
| `API_TOKEN_LIST` | Comma-separated API tokens; requests send `Authorization: <token>` |
| `WEB_APP_URL` | Origin allowed to iframe the ballot-preview page (CSP `frame-ancestors`) |
| `DB_*` | MySQL connection |
| `MAIL_*` | SMTP (only if the engine sends mail directly) |

## API at a glance

Every API route needs an `Authorization: <token>` header **and** an `Owner: <team-uuid>` header
identifying the tenant.

```
POST   /api/election/create
GET    /api/election/{id}
POST   /api/election/{id}/ballot/create
POST   /api/election/{id}/ballot/{ballot}/activate
POST   /api/election/{id}/ballot/{ballot}/deactivate
GET    /api/election/{id}/ballot/{ballot}/result
POST   /api/election/{id}/ballot/{ballot}/component/create
POST   /api/election/{id}/ballot/{ballot}/vote/generate
GET    /api/owner/personalization      POST /api/owner/logo
```

Voters use public web routes (no API token): `GET /election/{id}/ballot/{ballot}?code=<vote-code>` to
see the ballot, `POST` the same to cast it, and `GET …/result` for the public result page. The
authoritative route list is in [`routes/api.php`](routes/api.php) and [`routes/web.php`](routes/web.php).

## How a vote works (secrecy in one paragraph)

The engine pre-creates empty `Vote` rows (one per generated code, id = random UUID v4). The Sender
issues each code to a voter; the engine never receives the voter's identity. The voter opens
`…/ballot/{id}?code=<their code>`, submits selections, and the engine encrypts them onto that vote row.
On a secret ballot `cast_by` stays null — there is no stored voter↔vote link. At close, every voter
gets the full **anonymized ballot list** to verify their vote and the tally (integrity), while secrecy
is hardened by the chosen **level** (platform-run → commission-distributed → commission-proxied). Full
model in [docs/SECURITY_MODEL.md](docs/SECURITY_MODEL.md).

## Testing & static analysis

```bash
php artisan test            # PHPUnit (Unit + Feature)
./vendor/bin/paratest       # parallel
php -d memory_limit=1024M vendor/bin/phpstan analyse --no-progress   # level 8, clean
```

## Contributing & security

- [CONTRIBUTING.md](CONTRIBUTING.md) — DCO sign-off, PR flow, test/PHPStan gates.
- [SECURITY.md](SECURITY.md) — how to report a vulnerability (please don't open a public issue).
- [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md).

## License

**MIT** — see [LICENSE](LICENSE). _eGlasovanje_ is a trademark of Institut-IP21; the hosted GUI
(`web_app`) is proprietary and not part of this repository. Feedback & support: info@ip21.si
