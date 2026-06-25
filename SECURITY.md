# Security Policy

SiVote runs elections, so we take security reports seriously and welcome responsible disclosure.

## Reporting a vulnerability

**Please do not open a public GitHub issue for security vulnerabilities.**

Email **security@ip21.si** with:

- a description of the issue and its impact,
- steps to reproduce,
- affected version / commit,
- a suggested fix if you have one.

You'll get an acknowledgement within **48 hours** and a status update within **7 days**.

## Supported versions

| Version | Status |
| --- | --- |
| `master` | Actively maintained |
| Tagged releases | Best-effort for 12 months |
| Pre-release / integration branches | Not supported |

## Disclosure timeline

Day 0 report → triage within 48h → fix or timeline within 7 days → coordinated public advisory after a
patch is available (typically ≤30 days). Researchers acting in good faith under this policy are granted
safe harbor and credited in the advisory unless they prefer to remain anonymous.

## Scope & known boundaries

SiVote provides **voter-verifiable integrity at every level** (a distributed, anonymized ballot list)
and **ballot secrecy that scales across three levels** (from platform-run to fully
commission-isolated). Read [docs/SECURITY_MODEL.md](docs/SECURITY_MODEL.md) for the model and the
deployment checklist (HTTPS, strong `API_TOKEN_LIST`, `APP_KEY` protection, `AWS_SNS_TOPIC_ARNS`
allowlist on the Sender) before reporting environment-specific findings.
