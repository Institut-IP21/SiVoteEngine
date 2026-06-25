# Self-hosting SiVote (no GUI)

Run a complete secret-ballot system with just the **Engine** + **Sender** — no proprietary GUI. You
operate a self-hosted instance through its **artisan CLI commands** (`php artisan evote:*`). The HTTP
API exists for the graphical app to consume; for headless self-hosting the CLI is the intended
interface.

## Components

| Service | Role |
| --- | --- |
| **Engine** (this repo) | Elections, ballots, voting codes, vote collection, results. Voters hit it directly. |
| **Sender** | Voter lists, ballot invites, verification, delivery tracking (AWS SES/SNS). |
| **MySQL 8+** | Separate `engine` and `sender` schemas. |
| **Queue worker** | `php artisan queue:work` in the Sender (database queue — no Redis). |
| **SMTP / AWS SES** | Outbound mail for invites and results. |

Engine and Sender are deliberately **separate apps with separate databases**: the Sender holds the
voter↔code mapping, the Engine holds the (encrypted) votes. Keeping them apart is the structural basis
for the [security levels](SECURITY_MODEL.md).

## Install

Per app (engine, then sender):

```bash
cp .env.example .env
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan evote:cache
```

In the **Sender**, run a queue worker so mail sends: `php artisan queue:work --tries=3`.
Set a strong shared API token, matching `DB_*`, and (Sender) `AWS_*` / `SES_MAX_SEND_RATE` /
`AWS_SNS_TOPIC_ARNS`. Serve over HTTPS.

> The commands below are run inside each app (`cd web_engine` / `cd web_sender`, or
> `docker compose exec engine …` / `… sender …`). Every command supports `--help` for its exact
> options. IDs printed by a `make`/`list` command feed the next step.

## Run an election end-to-end (CLI)

### Engine — build the ballot

```bash
# Create an election (set the security level here: 1, 2 or 3)
php artisan evote:make:election --title="Board Election 2026" --level=1

# Create a ballot in it (use the election ID from the previous step)
php artisan evote:make:ballot --election=<EID> --title="Chair" --description="Elect the board chair"

# Add a question (component). Run with --help for the exact --options syntax / types.
php artisan evote:make:ballot:component --ballot=<BID> --title="Who should chair?" \
    --type=fptp --options="Alice,Bob,Charlie"

# Generate single-use voting codes (one per voter)
php artisan evote:make:ballot:codes --ballot=<BID> --quantity=100

# Export the codes for the Sender (or for home_sender at Level 2/3)
php artisan evote:export:codes --ballot=<BID> --file=codes.json
```

Inspect anytime: `evote:list:election`, `evote:show:election --election=<EID>`,
`evote:list:ballot --election=<EID>`, `evote:show:ballot --ballot=<BID>`.

### Sender — voters and invitations

```bash
# Create a voter list (owner is your organization's UUID)
php artisan evote:make:voterlist --title="Members" --owner=<OWNER>

# Add voters — one at a time, or bulk from CSV
php artisan evote:add:voter --voterlist=<LID> --title="Alice" --email="alice@org.tld"
php artisan evote:add:voter --voterlist=<LID> --csv=voters.csv

# Send invites: feed the exported codes + a template; %%CODE%% is substituted per voter
php artisan evote:send:invites --voterlist=<LID> --codes=codes.json \
    --template=invite.html --subject="Your ballot" \
    --url="https://engine.example.org/election/<EID>/ballot/<BID>?code=%%CODE%%"
```

### Open, collect, close

```bash
# Open voting
php artisan evote:activate:ballot --ballot=<BID>      # (engine)
```

Voters click the link in their email, fill out the ballot at
`https://engine.example.org/election/<EID>/ballot/<BID>?code=<code>`, and submit. The engine encrypts
each submission onto that code's vote row.

```bash
# Close voting, then read the result
php artisan evote:deactivate:ballot --ballot=<BID>    # (engine)
php artisan evote:result:ballot --ballot=<BID>        # (engine)

# Email results to voters (export the result list first; see below for the CSV)
php artisan evote:send:results --voterlist=<LID> --csv=results.csv \
    --subject="Results: Chair" --template=results.html \
    --result-link="https://engine.example.org/election/<EID>/ballot/<BID>/result"   # (sender)
```

The results email includes the full **anonymized ballot list** so every voter can verify their vote and
the tally with their code — the integrity guarantee described in [SECURITY_MODEL.md](SECURITY_MODEL.md).
Check delivery with `evote:stats:batch --batch=<UUID>` (sender).

### Optional: voter verification (pre-vote)

```bash
php artisan evote:make:verification --voterlist=<LID> --template=verify.html \
    --subject="Confirm participation" --redirect-url="https://…"     # (sender)
php artisan evote:send:verification --verification=<VID>             # (sender)
```

## Security levels at the CLI

The `--level` you pass to `evote:make:election` is the model's core control (see
[SECURITY_MODEL.md](SECURITY_MODEL.md)):

- **Level 1** — distribute invites with the Sender's `evote:send:invites` as above.
- **Level 2 / 3** — the electoral commission distributes codes itself from its own machine using the
  standalone open-source [`home_sender`](https://github.com/Institut-IP21/SiVoteHomeSender) (and, at Level 3, runs the
  metadata-stripping proxy). Export codes with `evote:export:codes` and hand them to that workflow
  instead of `evote:send:invites`, so the platform never learns the code↔voter mapping.

## Operations

- **Backups:** `mysqldump` both schemas regularly (votes are encrypted with each app's `APP_KEY` —
  back the key up separately, and note its holder can decrypt; the levels, not the encryption, are what
  remove operator trust).
- **Mail health:** watch the Sender's `failed_jobs` and the SNS bounce/complaint flow.
- **502s after big deploys** are usually OPcache — restart PHP-FPM and `php artisan optimize:clear`.
- **Security checklist:** HTTPS everywhere · strong `API_TOKEN_LIST` · protect `APP_KEY` ·
  set `AWS_SNS_TOPIC_ARNS` · keep the distribution and collection systems separated per the chosen level.
