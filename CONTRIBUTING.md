# Watchspire Development Guidelines

* [Project Principles](#project-principles)
* [Git Commit Standards](#commit-message-standards)
* [Commit Types](#commit-types)
* [Scopes](#scope)
* [Subject](#subject)
* [Body](#body)
* [Footer](#footer)

---

## <a name="project-principles"></a> Project Principles

Watchspire is a WordPress reliability and early-warning plugin. Contributions should preserve the project's core principles:

- **Privacy first** — never store form field contents or customer PII. Store only operational outcomes and metadata required for monitoring.
- **Performance matters** — heavy work belongs in background jobs, not normal front-end requests.
- **Resource-aware scanning** — link and image scanning must remain batched, interruptible, resumable, and safe for constrained hosting environments.
- **Free stays useful** — Watchspire Free provides passive monitoring without artificial limits on supported forms, pages, or links.
- **Pro stays separate** — synthetic testing, external heartbeat monitoring, independent alert channels, failure correlation, and WooCommerce monitoring belong in the separate Watchspire Pro add-on.
- **WordPress standards** — follow WordPress Coding Standards, sanitize input, escape output, use nonces and capability checks, and keep user-facing strings translatable.
- **No silent failures** — failures should degrade gracefully and provide actionable messages rather than fataling or blocking WordPress admin.

---

## <a name="commit-message-standards"></a> Git Commit Standards

To maintain a clean and understandable Watchspire project history, use consistent git commit messages. These messages improve readability and can also be used to generate the **Watchspire changelog**.

### Commit Message Structure

```text
<type>(<scope>): <subject>

<body>

<footer>
```

- The **header** is mandatory.
- The **scope** is optional.
- Keep each line at or below **100 characters** whenever practical.

### Reverting a Commit

To revert a commit:

- Start the message with `revert:` followed by the original commit header.
- Include `This reverts commit <hash>.` in the body.

You can use [`git revert`](https://git-scm.com/docs/git-revert) to generate the revert commit.

---

## <a name="commit-types"></a> Commit Types

- **feat** — introduce a new feature
- **fix** — resolve a bug
- **docs** — documentation-only changes
- **style** — formatting or code-style changes with no behavior change
- **refactor** — code changes that neither fix a bug nor add a feature
- **perf** — improve performance or reduce resource usage
- **test** — add or update tests
- **chore** — tooling, maintenance, build, or other non-feature work
- **ci** — CI/CD configuration changes
- **build** — build-system or dependency changes

---

## <a name="scope"></a> Scope

Recommended Watchspire scopes:

- `dashboard`
- `monitor`
- `forms`
- `mail`
- `ssl`
- `uptime`
- `errors`
- `scanner`
- `changelog`
- `crawler`
- `alerts`
- `reports`
- `scheduler`
- `database`
- `privacy`
- `admin`
- `rest`
- `pro`

Use `*` when a change affects multiple major areas.

Examples:

```text
feat(forms): add Fluent Forms delivery monitoring
fix(mail): correctly record wp_mail_failed errors
perf(scanner): reduce link scan batch size on constrained hosts
feat(changelog): record plugin auto-update events
fix(crawler): aggregate repeated AI bot hits by day
```

---

## <a name="subject"></a> Subject

The subject line should:

- Use the **imperative, present tense**: `add`, `fix`, `update`, `remove`
- Start with a **lowercase** letter
- Not end with a period
- Describe the change clearly

Good:

```text
fix(forms): prevent duplicate submission outcome records
```

Avoid:

```text
Fixed a problem with forms.
```

---

## <a name="body"></a> Body

Use the body when the reason for the change is not obvious.

Describe:

- What changed
- Why the change was needed
- How behavior differs from before
- Important privacy, performance, compatibility, or migration considerations

Example:

```text
fix(scanner): resume interrupted link scan from the last completed batch

Persist the scan cursor after each batch so a cancelled or interrupted
scan does not restart from the beginning.

This reduces repeated remote requests on large sites.
```

---

## <a name="footer"></a> Footer

For a breaking change:

```text
BREAKING CHANGE: rename the monitor result status key from state to status
```

To reference an issue:

```text
Closes #123
```

---

## Pull Request Checklist

Before opening a pull request, confirm that:

- The change follows WordPress Coding Standards.
- New user-facing strings are translatable.
- Input is sanitized and output is escaped.
- Admin actions and REST routes include appropriate capability and nonce checks.
- No form field contents or customer PII are persisted.
- Front-end page loads are not given heavy synchronous work.
- Background work is interruptible and safe to retry.
- Existing Watchspire Free / Watchspire Pro boundaries are preserved.
- Relevant tests and documentation are updated.
- `WP_DEBUG` does not produce new notices or warnings.

For general Conventional Commit guidance, see the [Conventional Commits specification](https://www.conventionalcommits.org/).
