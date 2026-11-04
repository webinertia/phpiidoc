# webware/skeleton

Greenfield starter for Webware packages: the shared tooling config, the organization's
required CI workflow contract, and a containerized dev environment - so a new component
starts aligned instead of being retrofitted.

> **The badges are fenced deliberately.** Un-fence the block below, replace every
> placeholder with the derived repository's own coordinates, and delete this note.
> Nothing should advertise a workflow, a package or a report that does not exist yet.

```markdown
[![PHP Version](https://img.shields.io/packagist/php-v/OWNER/PACKAGE)](https://packagist.org/packages/OWNER/PACKAGE)
[![Latest Version](https://img.shields.io/packagist/v/OWNER/PACKAGE)](https://packagist.org/packages/OWNER/PACKAGE)
[![License](https://img.shields.io/github/license/ORG/REPO)](LICENSE)
[![Continuous Integration](https://github.com/ORG/REPO/actions/workflows/required/webinertia/.github/.github/workflows/continuous-integration.yml/badge.svg)](https://github.com/ORG/REPO/actions/workflows/required/webinertia/.github/.github/workflows/continuous-integration.yml)
[![codecov](https://codecov.io/gh/ORG/REPO/graph/badge.svg)](https://codecov.io/gh/ORG/REPO)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2FORG%2FREPO%2FDEFAULT_BRANCH)](https://dashboard.stryker-mutator.io/reports/github.com/ORG/REPO/DEFAULT_BRANCH)
```

Placeholders: `OWNER/PACKAGE` is the Packagist name, `ORG/REPO` the GitHub
coordinates, `DEFAULT_BRANCH` the default branch.

## What ships here

Everything in this repository is either a **package of record** consumed from
`webware/webware-tools`, or the **thin per-repo wiring** that cannot live in a shared
config:

| Path | Role |
|---|---|
| `mago.toml` | Extends the centre (`vendor/webware/webware-tools/mago.toml`) and overrides `php-version` only. Never re-add general rules locally. |
| `mago.bat` | Windows shim Infection needs to run the native Mago binary. Tracked, not ignored. No effect on Linux or CI. See webinertia/webware-tools issue 59. |
| `webware-ci.json` | The required CI workflow's parameter contract - read from the repository root by `webinertia/.github`. |
| `phpunit.xml.dist` | PHPUnit 13 strict mode: `requireCoverageMetadata`, `failOnNotice`, `failOnWarning`, `failOnDeprecation`. |
| `compose.yml` / `Dockerfile` / `.devcontainer/` | The containerized toolchain (Composer, PHPUnit, Mago, Infection, PHPBench, roave BC-check). |
| `src/ConfigProvider.php` | The package wiring entry point, declared under `extra.laminas.config-provider`. |
| `.specify/` / `.github/skills/` | Spec Kit project files and the Copilot agent skills, on PowerShell scripts. |
| `.specify/templates/overrides/` | The Webware standard documents that every package shares. Currently the constitution. Resolves above the upstream templates and is not managed by `specify`, so upgrades cannot overwrite it. |

`mago.toml`, `mago.bat`, `phpunit.xml.dist`, `.gitattributes`, `.gitignore`, `codecov.yml`, `Dockerfile`,
`.dockerignore`, `infection.json5.dist`, `phpbench.json.dist` and devcontainer config are
byte-identical to the canonical artifacts in
`webware-tools/presets/webware-alignment/artifacts/` - copy updates from there rather than
editing them here.

## Using this template

Work the list top to bottom; the namespace rename is the one that bites, because
`extra.laminas.config-provider` is a plain string that no tool validates for you.

- [ ] `composer.json` - `name`, `description`, `keywords`
- [ ] `composer.json` - `extra.laminas.config-provider` → the new FQCN
- [ ] `composer.json` - both PSR-4 roots: `Webware\Skeleton\` → `src/` and the two `WebwareTest*` roots
- [ ] `src/`, `test/unit/`, `test/integration/` - namespaces and `use` statements
- [ ] Every new file's header block - "This file is part of the Webware Skeleton package"
- [ ] `.github/copilot-instructions.md` - the title line
- [ ] `README.md` - this title, the description, and the fenced badge block (un-fence it, replace the placeholders)
- [ ] `LICENSE` - copyright holder and year
- [ ] `webware-ci.json` - `min_msi` / `min_covered_msi` (see below), and uncomment the `db_*` keys for a package whose tests need a database
- [ ] Default branch - a new repo starts on an `N.N.x` branch (this one is `1.0.x`)
- [ ] `.specify/memory/constitution.md` - run `/speckit-constitution` to ratify the Webware standard for the new package. The standard itself is `.specify/templates/overrides/constitution-template.md`; the command fills `[PROJECT_NAME]` and writes the ratified copy to memory.

The full badge block sits at the top of this file, fenced. Un-fence it and replace the
`OWNER/PACKAGE`, `ORG/REPO` and `DEFAULT_BRANCH` placeholders there rather than adding a
second copy here.

The Stryker badge embeds the branch segment - update it in both the badge URL and the
dashboard link whenever the default branch changes.

**Do not point the CI badge at `continuous-integration.yml`.** A consumer repository has no
such file, so that URL 404s. The required-route URL above is the one GitHub's own "Create
status badge" dialog produces, because the workflow file lives in the config repository.

## Quality gates

Both MSI gates are set to **95**: the ecosystem standard, not a starting point. Lower them
only with a deliberate decision, and never silently:

```json
"min_msi": "95",
"min_covered_msi": "95"
```

Four Mago gates run in CI and must be clean: `format --check`, `lint`, `analyze`, `guard`.
Run `mago fmt` first when making changes, and fix findings at source rather than adding
`@mago-expect` - a suppression needs to be a decision, not a reflex.

## Development

The toolchain runs in a container, so the host needs no PHP install. With VS Code, reopen in
the container; without it:

```shell
docker compose up -d
docker compose exec tooling composer install
docker compose exec tooling composer test
docker compose exec tooling composer test-integration
docker compose exec tooling mago lint
docker compose down
```

Packages whose tests need MySQL uncomment the `mysql` service in `compose.yml`, mirroring the
`db_image` / `db_env_json` / `db_port` values they declare in `webware-ci.json`.
