# phpiidoc/phpiidoc

[![PHP Version](https://img.shields.io/packagist/php-v/phpiidoc/phpiidoc)](https://packagist.org/packages/phpiidoc/phpiidoc)
[![Latest Version](https://img.shields.io/packagist/v/phpiidoc/phpiidoc)](https://packagist.org/packages/phpiidoc/phpiidoc)
[![License](https://img.shields.io/github/license/webinertia/phpiidoc)](LICENSE)
[![Continuous Integration](https://github.com/webinertia/phpiidoc/actions/workflows/required/webinertia/.github/.github/workflows/continuous-integration.yml/badge.svg)](https://github.com/webinertia/phpiidoc/actions/workflows/required/webinertia/.github/.github/workflows/continuous-integration.yml)
[![codecov](https://codecov.io/gh/webinertia/phpiidoc/graph/badge.svg)](https://codecov.io/gh/webinertia/phpiidoc)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fwebinertia%2Fphpiidoc%2F1.0.x)](https://dashboard.stryker-mutator.io/reports/github.com/webinertia/phpiidoc/1.0.x)

A PHP implementation of AsciiDoc, the plain-text markup language for technical
documentation.

The package is pre-1.0. The current commit is the package scaffold: the Laminas
`ConfigProvider` wiring plus the toolchain contract. There is no parser, renderer or CLI
yet, and no AsciiDoc syntax is supported.

## Requirements

- PHP `~8.4.1 || ~8.5.0`
- Composer

## Installation

phpiidoc is published as a pre-release and its runtime graph is pre-release too
(`webware/message-bus`, `webware/messagebus-event` and `webware/webware-console`), so the
consuming project needs a global minimum stability: the `@alpha` flag on the requirement
only relaxes stability for phpiidoc itself.

```shell
composer config minimum-stability alpha
composer config prefer-stable true
composer require "phpiidoc/phpiidoc:^1.0@alpha"
```

The two `composer config` calls write these keys to your `composer.json`. `prefer-stable`
keeps a stable release winning wherever one satisfies the constraint:

```json
{
    "minimum-stability": "alpha",
    "prefer-stable": true
}
```

`composer.json` declares `Phpiidoc\ConfigProvider` under `extra.laminas.config-provider`,
so a Mezzio or Laminas application merges the package config without further
registration.

## What ships here

Everything in this repository is either a **package of record** consumed from
`webware/webware-tools`, or the **thin per-repo wiring** that cannot live in a shared
config:

| Path | Role |
|---|---|
| `mago.toml` | Extends the centre (`vendor/webware/webware-tools/mago.toml`), pins `php-version`, declares the source paths, and points the linter and analyzer at the checked-in baselines. Never re-add general rules locally. |
| `mago.bat` | Windows shim Infection needs to run the native Mago binary. Tracked, not ignored. No effect on Linux or CI. See webinertia/webware-tools issue 59. |
| `webware-ci.json` | The required CI workflow's parameter contract - read from the repository root by `webinertia/.github`. |
| `phpunit.xml.dist` | PHPUnit 13 strict mode: `requireCoverageMetadata`, `failOnNotice`, `failOnWarning`, `failOnDeprecation`. |
| `compose.yml` / `Dockerfile` / `.devcontainer/` | The containerized toolchain (Composer, PHPUnit, Mago, Infection, PHPBench, roave BC-check). |
| `src/ConfigProvider.php` | The package wiring entry point, declared under `extra.laminas.config-provider`. |
| `.specify/` / `.github/skills/` | Spec Kit project files and the Copilot agent skills, on PowerShell scripts. |
| `.specify/presets/webware-standard/` | The Webware standard preset, which supplies the constitution template that every derived package ratifies into `.specify/memory/constitution.md`. |

`mago.toml`, `mago.bat`, `phpunit.xml.dist`, `.gitattributes`, `.gitignore`, `codecov.yml`, `Dockerfile`,
`.dockerignore`, `infection.json5.dist`, `phpbench.json.dist` and devcontainer config are
byte-identical to the canonical artifacts in
`webware-tools/presets/webware-alignment/artifacts/` - copy updates from there rather than
editing them here.

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
