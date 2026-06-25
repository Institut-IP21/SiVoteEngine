# Contributing to SiVote Engine

Thanks for helping improve SiVote. Because this software runs elections, we hold a high bar for
correctness, security, and clarity — and we genuinely welcome audits and fixes.

## Developer Certificate of Origin (DCO)

Contributions are accepted under the [Developer Certificate of Origin](https://developercertificate.org/)
— a lightweight, sign-off-based affirmation, **no CLA or paperwork**. By signing off you certify you
wrote the change (or otherwise have the right to submit it) under the repository's [LICENSE](LICENSE).
Sign every commit:

```bash
git commit -s -m "Fix vote-integrity check"
```

This appends a `Signed-off-by: Your Name <you@example.com>` trailer (it uses your git
`user.name` / `user.email`).

## Pull-request flow

1. Fork and branch from `master` (`git checkout -b feature/your-change`).
2. Make the change with a test. New behaviour ⇒ a test; bug fix ⇒ a regression test.
3. Ensure the gates pass:
   ```bash
   php artisan test
   php -d memory_limit=1024M vendor/bin/phpstan analyse --no-progress   # level 8, must stay clean
   ```
4. Sign off your commits (`git commit -s`, DCO) and open a PR describing the change and its rationale.

## Ground rules for a voting system

- **Never weaken ballot secrecy.** Don't introduce a stored voter↔vote link; don't switch vote IDs to
  ordered/sequential UUIDs; keep vote contents encrypted. See [docs/SECURITY_MODEL.md](docs/SECURITY_MODEL.md).
- **Ownership/integrity checks are security-critical.** A vote must belong to the ballot it's cast on.
  Flag any change near vote/ballot ownership in the PR description.
- Match the surrounding code style; keep `declare(strict_types=1)`.
- Found a vulnerability? Do **not** open a public issue — see [SECURITY.md](SECURITY.md).

By contributing, you agree your contributions are licensed under the project's license (see
[LICENSE](LICENSE)).
