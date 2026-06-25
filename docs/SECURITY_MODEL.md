# SiVote Security & Secrecy Model

SiVote is a secret-ballot system **for private, democratic organizations** — associations,
cooperatives, unions, political parties, congresses. The design starts from one trade-off:

> The fundamental question in a secret voting system is balancing the **risk of abuse** against
> **ease of use**.

Rather than force every organization onto the same point of that trade-off, SiVote offers **three
levels** — from easiest-to-use to most-secure — and keeps **integrity** strong and **voter-verifiable**
at every level. This document describes that model. The canonical, in-depth articles live at
[eglasovanje.si](https://eglasovanje.si/vsi-clanki) (e.g. *Security Levels*, *How we ensure security,
integrity & confidentiality*).

> **Not for state / governmental elections — by design and on principle.** The authors are, and remain,
> *opponents* of e-voting for public elections: in that context it adds critical risks and no real
> benefit. SiVote is built for private organizations, where the trade-off genuinely favors remote
> voting. This is not a "use a more advanced system for national elections" caveat — we don't believe
> e-voting belongs in state elections at all.

---

## Integrity (accuracy) — voter-verifiable, at every level

Integrity does **not** rely on trusting the platform. When voting closes, the system publishes the
full **list of all submitted ballots** — each identified by its anonymous voting code together with the
selections — and emails **every voter their own copy** of that list (a document you can open in any
spreadsheet program), alongside a link to the computed results.

Using the secret code from their invitation, each voter can independently verify that:

1. **their own vote** was recorded correctly,
2. the **number of submitted ballots** is correct,
3. the **result calculation** is correct,
4. their document is **identical to the one other voters received**.

Because the list is **distributed** (each voter holds their own copy) rather than centrally hosted, an
attacker cannot show each voter a locally-correct but globally-falsified result — if even a few
randomly-chosen voters compare their copies, any tampering is exposed. This gives **universal,
understandable verifiability without cryptographic machinery** — the integrity guarantee holds equally
at Levels 1, 2, and 3.

## Secrecy (anonymity) — scales by level

Two mechanisms underpin secrecy at every level, then the level adds isolation on top:

- **Anonymous voting codes.** Each voter votes with a unique code that is randomly generated and
  randomly assigned per vote. The code carries no identity — you cannot derive the voter from the code.
- **Separation of distribution from collection.** The system that *distributes* codes is physically
  and operationally separate from the system that *collects and processes* ballots.

### The three levels

| | Distribution of codes | Vote collection | Confidentiality | Best for |
|---|---|---|---|---|
| **Level 1** | Platform (codes sent automatically; **the code↔address mapping is not stored**) | Platform | **Effective**, assuming trust that the operator won't misuse their own systems | Smaller orgs; frequent, lower-sensitivity decisions |
| **Level 2** | **Your electoral commission**, from its own computer, using a simple open-source tool (`home_sender`) | Platform | **High** — even platform admins cannot link ballots to identities; the mapping lives on a system the platform never sees* | Orgs making sensitive decisions that still trust the platform for collection |
| **Level 3** | Your electoral commission (`home_sender`) | Platform, **but reached through a metadata-stripping proxy the commission runs** (`home_engine_proxy`) | **Highest** — the platform never directly contacts the voter, so no IP / browser-fingerprint metadata can be captured | Orgs making highly sensitive decisions that want full isolation from the hosted operator |

\* Residual at Level 2: vote *collection* still runs on the platform, so in principle an operator could
attempt to capture submission metadata — a costly effort with very little chance of success, a
negligible risk for an organization. Level 3 closes even that by routing submission through the
commission's proxy.

Integrity (the four checks above) is **✓ at all three levels**. Levels 2 and 3 reach a level of
anonymity that meets or exceeds postal voting, and require no exotic, hard-to-understand technology —
only a small, pre-reviewed open-source tool and (for Level 3) a proxy. For maximum security at Level 3
the work is ideally split between **two commissions** — one for distribution, one for the proxy /
collection.

> The open-source [`home_sender`](https://github.com/Institut-IP21/SiVoteHomeSender) (Level 2/3 code distribution) and
> the metadata-stripping proxy (Level 3) are precisely the pieces that let an organization take secrecy
> into its own hands. That is *why they exist*.

## How the code reinforces the model

In this engine, secrecy and integrity are backed by concrete implementation choices:

- **No stored voter↔vote link** on a secret ballot — the vote record's `cast_by` is never set.
- **Encryption at rest** — vote contents are encrypted with the instance's app key (`Encryptable`).
- **Random vote IDs** — vote records use random v4 UUIDs (`HasUuidV4`), never sequential, so storage
  order cannot leak the order in which ballots were cast.
- **Distribution vs. collection are separate apps with separate databases** — the
  [Sender](https://github.com/Institut-IP21/SiVoteSender) handles codes/voters, this Engine handles
  votes/results. That separation is the structural basis for the levels above (and for the
  commission-run Level 2/3 deployments).

## Design philosophy & honest scope

- **Understandable over clever.** Voters verify the outcome by comparing a plain spreadsheet, not by
  trusting cryptographic proofs they can't inspect. Verifiability that every member can understand is
  itself a security property.
- **Different approach, different context.** Cryptographically end-to-end-verifiable systems (e.g.
  Helios, Belenios) target a different problem space; SiVote deliberately favors a procedural model
  with distributed, human-checkable verification, tuned to private organizations. This is a design
  *choice*, not a missing feature.
- **Operational requirements.** Serve over HTTPS; protect each instance's app key (votes are encrypted
  with it); keep the distribution and collection systems separated as the chosen level requires.

For vulnerability reporting see [SECURITY.md](../SECURITY.md).
