# LeadFlow docs

Domain guides for the parts of the system that aren't obvious from
the code.

- [`setup.md`](./setup.md) — first-time setup after `git clone`:
  native install on Ubuntu (протестировано на нашем vdska),
  Podman-вариант с обзором отличий от Docker.
- [`scoring.md`](./scoring.md) — bank pre-flight pipeline:
  what rules run before each bank API call, how the per-bank
  pipelines are composed, and how a user enables them via the
  `tune` JSON.

Planned (not yet written):

- `http-client.md` — `BankHttpClient`, retry policy, the
  `BankRequestFailed` event and how to add a Slack/email listener.
- `architecture.md` — how a lead moves from the file upload or
  Skorozvon webhook through scoring and into a bank job.