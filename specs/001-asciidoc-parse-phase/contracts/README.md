# Contracts: AsciiDoc Parse Phase

**Feature**: [spec.md](../spec.md) | **Plan**: [plan.md](../plan.md) | **Data model**: [data-model.md](../data-model.md)

The contracts for this feature are PHP interfaces under `src/`, not documents in this directory.
They are not duplicated here for three reasons:

1. An interface is executable and a Markdown restatement of one is not. The copy drifts the first
   time a method changes.
2. The reasoning behind each contract is already recorded, with file and line citations against the
   reference implementation, in `docs/planning/contracts/parse-phase.md`.
3. This feature is a contract-only change. The interfaces are the deliverable; a prose copy would be
   a second, weaker deliverable.

## New contracts

| Interface | Stage | Purpose |
|---|---|---|
| `src/Parser/ParserInterface.php` | S3 to S8 | The entry point. One call, three arguments, one document out. |
| `src/Reader/ReaderInterface.php` | S1, S2 | The line cursor, marks, include stack and conditional stack. |
| `src/Reader/ReaderFactoryInterface.php` | S1 | Builds a reader for a source and its options. |
| `src/Reader/IncludeResolverInterface.php` | S2 | Resolves an include target to a path and answers containment. |
| `src/Reader/ReadLinesOptions.php` | S2 | The per-call reading behaviour, as a value. |
| `src/Reader/ReadLinesBehaviour.php` | S2 | The closed set of reading behaviours. |
| `src/Attribute/MutableAttributesInterface.php` | S2, S3, S7 | The write side of the attribute store. |
| `src/Diagnostic/DiagnosticCollectorInterface.php` | all | Where the phase reports. |
| `src/Node/ContentModel.php` | S5, S7 | Which substitution group applies to a block. |
| `src/Node/ListItemInterface.php` | S6 | The item node, including the description tuple. |
| `src/Substitutor/SubstitutorInterface.php` | S7 | The entry point for running substitutions. |
| `src/Substitutor/SubstitutionContextInterface.php` | S7 | The state one substitution pass needs. |
| `src/Substitutor/Substitution.php` | S7 | The closed set of substitutions. |
| `src/Substitutor/SubstitutionGroup.php` | S7 | The named ordered groups. |

## Amended contracts

| Interface | Amendment | Reason |
|---|---|---|
| `src/Node/BlockInterface.php` | `getContent()` narrowed to the block's own content | D6, see [research.md](../research.md) |
| `src/Converter/ConverterInterface.php` | lazy-content paragraph replaced | D5, the converter no longer substitutes |
| `src/SafeMode.php` | `processesIncludes()` stops at secure | D9, corrected against the reference |
| `src/Diagnostic/ReasonCode.php` | parse phase codes added | the reporting vocabulary was incomplete |

`test/unit/SafeModeTest.php` changed with the `SafeMode` amendment; the test that asserted the old
behaviour now asserts the corrected behaviour.

## What is not contracted here

- The converter seam, D2. Deferred, see [research.md](../research.md).
- Any node implementation. This feature is interfaces.
- The parser itself. This feature does not implement it.
