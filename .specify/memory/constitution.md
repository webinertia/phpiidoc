<!--
  ============================================================================
  SYNC IMPACT REPORT
  ============================================================================
  Version change: N/A -> [CONSTITUTION_VERSION] (initial ratification)

  Modified principles: None (initial ratification)

  Added sections:
    - Core Principles (4 principles)
    - Quality Gates
    - Development Workflow
    - Governance

  Removed sections: None

  Templates requiring updates:
    - plan-template.md - Constitution Check section reads these principles
    - spec-template.md - Success Criteria aligns with the Quality Gates
    - tasks-template.md - Phase structure supports the gates

  Follow-up TODOs: None
  ============================================================================
-->

# [PROJECT_NAME] Constitution

## Core Principles

### I. Code Quality

All code MUST meet these non-negotiable quality standards:

- **Readability**: Code MUST be self-documenting with clear naming conventions. Variable and function
  names MUST express intent without requiring comments to understand basic functionality. Interface
  names end in `Interface`; trait names end in `Trait`. A namespace segment never repeats its parent
  segment. Where dropping a redundant prefix leaves a name that still says what the type is, the
  prefix is dropped; where it leaves no name, the repetition is correct.
- **Maintainability**: Functions MUST have a single responsibility. Methods/Functions MUST NOT exceed 300 lines
  without architectural justification. Cyclomatic complexity MUST remain below 10 per function.
- **Consistency**: All code MUST follow the project's established style guide and formatting rules.
  Mago `format`, `lint`, `analyze`, and `guard` MUST pass with zero findings before merge, checked by
  exit code rather than by the last line of output. Findings are fixed in source and never silently
  suppressed; a baseline entry requires explicit maintainer approval per issue. `mago.toml` extends
  `vendor/webware/webware-tools/mago.toml`, so general settings and general rules are central and
  MUST NOT be defined or overridden locally. Local rules are additive, strengthen the centre, and
  never weaken it. All text files are committed with LF line endings only; the repository
  `.gitattributes` is authoritative and is not overridden.
- **Documentation**: Public APIs MUST have documentation. Complex algorithms MUST include inline
  comments explaining the "why" not the "what".
- **No Dead Code**: Unused imports, variables, and functions MUST be removed. Commented-out code MUST
  NOT be committed.
- **Modern Constructs**: Code MUST use modern PHP features wherever they are applicable: constructor
  property promotion for injected dependencies and value objects; property hooks for computed or
  validated property access; `readonly` for immutable state; enums over class constants and magic
  strings for closed value sets; and named arguments at call sites. First-class callables and `match`
  expressions replace closure wrappers and `switch` where the construct is static or
  expression-valued. Legacy-style equivalents are acceptable only when a feature genuinely does not
  apply.
- **Compatibility**: Support current supported PHP versions only (`~8.4.1 || ~8.5.0 || ~8.6.0`).
  `config.platform.php` is pinned to `8.4.99` for dependency resolution.

**Rationale**: High code quality reduces technical debt, accelerates onboarding, and minimizes bug
introduction during changes. Centralizing tooling authority is what keeps those standards identical
across every component; a local copy of a central rule is drift even when its content is identical
today.

### II. Testing Standards

Testing is mandatory and MUST follow these requirements:

- **Coverage Threshold**: Infection mutation coverage MUST meet `min_msi` and `min_covered_msi` of 95
  from `webware-ci.json`. That is the ecosystem standard, not a starting point. Coverage metadata is
  required: every test class declares `#[CoversClass]` for each source class and `#[CoversMethod]`
  for each method exercised.
- **Test Types Required**:
  - Unit tests for all business logic and utility functions, under `test/unit`
  - Integration tests for persistence and external boundaries, under `test/integration`, run when
    `run_integration` is set and narrowed to a single leg where the suite is expensive
  - Contract tests for cross-component boundaries
- **Test Quality**: Tests MUST be deterministic, with no flaky tests. Tests MUST be independent and
  runnable in isolation. Test names MUST clearly describe the scenario being tested. Test doubles
  follow the mock and stub split: `createStub()` for value-returning doubles, and `createMock()` only
  where behavior is verified with `expects()`. `createMock()` without `expects()` is prohibited.
- **Strict Mode**: `phpunit.xml.dist` sets `requireCoverageMetadata`, `failOnNotice`,
  `failOnDeprecation`, and `failOnWarning`. No loose PHPUnit notices are tolerated in CI. Warnings and
  deprecations are fixed, never filtered.
- **Test-First Encouraged**: For complex features, write tests before implementation. All bug fixes
  MUST include a regression test.
- **CI Gate**: All tests MUST pass on every matrix leg before merge. Test failures block deployment.
  Unit tests run under the lowest, locked, and latest dependency strategies.

**Rationale**: Comprehensive testing catches bugs early, enables confident refactoring, and serves as
living documentation of expected behavior.

### III. User Experience Consistency

All user-facing and consumer-facing surfaces MUST maintain a consistent experience:

- **Interface Patterns**: Similar operations MUST use similar interaction patterns. Consumers MUST
  NOT have to relearn the interface for each component. A component contributes its wiring through a
  `ConfigProvider` and the host merges it. `ConfigProvider` ordering is part of the contract where a
  resolver chain depends on it, and a component MUST NOT assume a specific host application.
- **Error Handling**: All errors MUST provide clear, actionable messages. Error messages MUST NOT
  expose internal implementation details or leak storage-level types. Users MUST always have a clear
  path forward after an error.
- **Response Formatting**: Support both human-readable and machine-parseable output formats. Default
  to human-readable; use flags for structured output.
- **Input Flexibility**: Accept common input variations where reasonable. Provide helpful suggestions
  for invalid input. Named arguments are required in code the component owns.
- **Predictability**: Operations MUST produce consistent results given the same inputs. Side effects
  MUST be clearly documented and expected.

**Rationale**: Consistent interfaces reduce cognitive load, increase consumer confidence, and
accelerate adoption. A component that is predictable to consume is a component that gets consumed.

### IV. Performance Requirements

All features MUST meet baseline performance standards:

- **Response Time**: CLI commands MUST respond within 500ms for typical operations. Network-dependent
  operations MUST show progress indication if exceeding 1 second.
- **Resource Efficiency**: Memory usage MUST remain bounded and proportional to input size. No memory
  leaks in long-running operations.
- **Startup Time**: Application startup MUST complete within 200ms. Lazy-load non-critical
  dependencies.
- **Scalability**: Design MUST support reasonable growth without architectural changes. Document
  known scalability limits.
- **Degradation**: Performance degradation MUST be graceful under load. Features MUST NOT block on
  non-critical operations.
- **Benchmark Regression**: Where the component ships benchmarks (PHPBench), a change MUST NOT
  regress them without documented justification.

**Rationale**: Performance directly impacts productivity and satisfaction. Slow tools get abandoned.

## Quality Gates

All pull requests and code changes MUST pass these gates:

| Gate | Requirement | Enforcement |
|------|-------------|-------------|
| Formatting | Mago `format` reports no changes needed | CI automated check |
| Linting | Mago `lint` reports zero findings | CI automated check |
| Static analysis | Mago `analyze` reports zero findings | CI automated check |
| Boundary rules | Mago `guard` passes; no central rule weakened locally | CI automated check |
| Type and doc safety | No suppressed findings; baseline additions approved per issue | Code review |
| Test coverage | Infection `min_msi` and `min_covered_msi` at or above 95 | CI automated check |
| Test pass | 100 percent of tests passing on every matrix leg | CI automated check |
| Dependency strategies | Tests pass under lowest, locked, and latest | CI automated check |
| Lock file | `composer.lock` committed and current | CI `locked` leg |
| Coverage report | Codecov upload from the canonical coverage leg | CI, report-only |
| Compatibility | No backward-incompatible change to a public API | CI BC-check |
| Documentation | Public APIs documented | Code review |
| Performance | No benchmark regression without justification | Code review and CI |

Integration tests are an additional gate when `run_integration` is set. `webware-ci.json` in the
repository root is the CI contract; its values change only when a deliberate policy decision is
recorded in a spec.

## Development Workflow

### Code Review Requirements

- Every change goes through a pull request. The default branch is never pushed to directly. Releases
  and tags belong to the owner.
- All changes MUST be reviewed. Reviews MUST verify compliance with all four core principles, and
  reviewers MUST check correctness, readability, test coverage, and performance implications.
- No file is deleted, renamed, or moved without owner approval.
- Application code is never run while a debug session may be attached.

### Implementation Standards

- Start with the simplest solution that meets requirements (YAGNI)
- Add complexity only when justified and documented
- Prefer composition over inheritance
- Prefer explicit over implicit behavior
- Spec-driven development: each step (constitution, specify, plan, tasks, implement, converge) lands
  as its own branch and using a merge commit (when appropriate) pull request
- Commits use Conventional Commits and are signed off by the maintainer's real identity
  (`git commit -s`, `Joey Smith <jsmith@webinertia.net>`)

## Governance

This constitution is the authoritative guide for all technical decisions in this project.

### Amendment Process

1. Propose changes via pull request to this file
2. Changes require approval from project maintainers
3. Breaking changes (principle removal or redefinition) require a migration plan
4. All amendments MUST be documented with rationale, and the version and Last Amended date updated
5. Constitutional changes MUST be verified against dependent templates before merge

### Versioning Policy

- **MAJOR**: Backward-incompatible principle changes or removals
- **MINOR**: New principles added or existing ones materially expanded
- **PATCH**: Clarifications, wording improvements, non-semantic changes

### Compliance

- All pull requests MUST reference relevant principles when applicable
- Constitution violations MUST be justified and documented in Complexity Tracking
- Boundary guard rules take effect the moment they land in the central `mago.toml`. The expected
  response is to become compliant, never to disable or locally weaken the rule. Realignment follows
  `vendor/webware/webware-tools/mago-guard-realignment.md`
- Regular audits SHOULD occur to ensure ongoing compliance
- Ambiguity in principles SHOULD be resolved via amendment, not interpretation
- `.github/copilot-instructions.md` carries the operational rules derived from this constitution

### Decision Authority

When technical decisions conflict:

1. This constitution takes precedence over personal preference
2. Explicit requirements take precedence over implicit conventions
3. User experience takes precedence over implementation convenience
4. Simplicity takes precedence over premature optimization

**Version**: [CONSTITUTION_VERSION] | **Ratified**: [RATIFICATION_DATE] | **Last Amended**: [LAST_AMENDED_DATE]
