# Quickstart: AsciiDoc Parse Phase

**Feature**: [spec.md](./spec.md) | **Plan**: [plan.md](./plan.md)

This guide is for validating the parse phase once it exists. It assumes the working directory is the
repository root and that the tooling container is up.

## Start the tooling container

Every gate runs inside the container. A result from a native Windows PHP is not evidence, because the
package does not claim Windows support and CI never runs there.

```powershell
docker compose up -d tooling
```

Bring it down when finished:

```powershell
docker compose down
```

## Parse a document

The phase has one entry point. Everything else is reached through it. The snippet below names the
concrete classes the implementation tasks create; the converter is supplied by the renderer feature,
which owns backend selection.

```php
use Phpiidoc\Parser\Parser;
use Phpiidoc\Render\ParseOptions;
use Phpiidoc\Render\Source;
use Phpiidoc\SafeMode;

$parser = new Parser();
$document = $parser->parse(
    new Source(path: 'docs/sample.adoc'),
    new ParseOptions(safeMode: SafeMode::Safe, baseDir: __DIR__),
    $converter,
);

$html = $converter->convert($document);
```

The document that comes back is prepared. No call on it parses anything, and no call substitutes
anything. If a converter has to look at raw AsciiDoc text to render a node, that is a defect in this
phase, not in the converter.

## Compare against the reference implementation

The reference implementation is Ruby Asciidoctor, checked out at
`C:\Users\jsmit\github.com\tyrsson\asciidoctor`. It is the only oracle.

The parity corpus is harvested from the reference implementation's own fixtures. Each fixture has an
expected HTML file produced by the reference implementation and committed beside the input:

```powershell
# In the reference checkout, to regenerate one expected file
bundle exec asciidoctor -b html5 -o - test/fixtures/paragraph.adoc > expected.html
```

The comparison runs as an integration test, not as a command:

```powershell
docker compose exec tooling vendor/bin/phpunit --testsuite "integration test" --filter ParityTest
```

A byte comparison is not the check. It fails on whitespace no reader can see and passes on nothing
extra. The parity test compares by DOM structure and text content, so it asserts what the reader
sees. The harvesting and comparison tasks are T001 and T002 in [tasks.md](./tasks.md).

When a comparison fails, the first question is which stage produced the difference, not which line of
HTML differs. The stages and their inputs and outputs are in [spec.md](./spec.md) under Scope.

## Run the gates

All gates, in the order they are worth running:

```powershell
docker compose exec tooling vendor/bin/phpunit
docker compose exec tooling mago lint --ignore-baseline
docker compose exec tooling mago analyze
docker compose exec tooling mago format --check
docker compose exec tooling vendor/bin/infection --threads=4
docker compose exec tooling vendor/bin/phpbench run --report=default
```

`mago` is invoked directly, never through a composer script. `--ignore-baseline` is the honest lint
check: a finding on new code is never baselined. Infection is capped at 4 threads.

## Run one stage

Each stage has its own tests, named for the stage. To iterate on the substitution pipeline without
parsing a document:

```powershell
docker compose exec tooling vendor/bin/phpunit --filter SubstitutorTest
```

## Check the contracts are still the contracts

The contracts are the interfaces under `src/`. Nothing in this feature implements them. If an
implementation needs a contract to change, the contract change is a separate reviewed change with its
own reasoning record in `docs/planning/contracts/`, not a side effect of writing the parser.

## Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| An include is not read | Safe mode is `secure` or above | `SafeMode::processesIncludes()` is false at `secure` and above. This is correct; see D9 in [research.md](./research.md). |
| An include is reported as an escape | The target resolves outside the base directory, possibly through a symlink | This is the containment check working. See D8 in [research.md](./research.md). |
| A section id is empty | The prepare stage did not run, or the section has no title | Section ids are generated in the prepare stage, not when the section is created. |
| A converter sees raw AsciiDoc text | A substitution group was not applied to the block | Check the block's `ContentModel`. |
