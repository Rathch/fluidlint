# fluidlint

Static analysis for TYPO3 Fluid templates: structural issues, cyclomatic complexity, and dead-code hints — without bootstrapping TYPO3.

SPDX-FileCopyrightText: 2025 Christian Rath-Ulrich  
SPDX-License-Identifier: GPL-3.0-or-later

fluidlint scans Fluid templates directly from the filesystem. PHP ViewHelper classes are not analyzed (use PHPStan/PHPCS for that). On TYPO3 14.2+, complement this tool with `typo3 fluid:analyze` for syntax errors, invalid ViewHelpers, and deprecations.

## Requirements

- PHP 8.2+
- `typo3fluid/fluid` 2.15+ (TYPO3 v13) or 4.x (TYPO3 v14)

## Installation

In your TYPO3 project or extension as a dev dependency:

```bash
composer require --dev cru/fluidlint:^1.0
```

## Quick start

```bash
# Full analysis
vendor/bin/fluidlint scan Resources/Private/

# Complexity or dead code only
vendor/bin/fluidlint complexity Resources/Private/Templates/
vendor/bin/fluidlint dead-code Resources/Private/

# Exit code for CI (fails on warnings or errors)
vendor/bin/fluidlint scan . --fail-on=warning
```

## Commands and options

| Command      | Description                                             |
|--------------|---------------------------------------------------------|
| `scan`       | All checks (structure, complexity, dead code)           |
| `complexity` | Cyclomatic complexity per template                      |
| `dead-code`  | Unreachable branches, unused variables, orphan partials |

Common options:

| Option                           | Description                                       |
|----------------------------------|---------------------------------------------------|
| `--format=text\|json\|sarif`     | stdout output format (default: `text`)            |
| `--fail-on=info\|warning\|error` | Minimum severity for exit code 1                  |
| `--config=FILE`                  | Path to `.fluidlint.yaml` (default: project root) |
| `--exclude=PATTERN`              | Exclude glob pattern (repeatable)                 |
| `--report-file=FILE`             | Write detailed JSON report to file                |
| `-v` / `--verbose`               | Confirm report file path after writing            |

Paths in CLI output and reports are **relative to the scan start path** (the first directory argument, or `.`).

## Configuration

Create `.fluidlint.yaml` in your project root. Defaults ship in `config/fluidlint.defaults.yaml`.

```yaml
nestingDepth:
  warn: 8
  error: 12

complexity:
  warn: 10
  error: 20

deadCode:
  entryPoints:
    - '**/Resources/Private/Templates/**'
  orphanSeverity: info

failOn: warning
```

### Thresholds

- **nestingDepth** — maximum ViewHelper nesting depth
- **complexity** — cyclomatic complexity (`f:if`, `f:for`, `f:switch`, …)
- **failOn** — minimum severity that causes exit code 1

### Dead-code settings

- **entryPoints** — templates from which references are considered reachable
- **orphanSeverity** — severity for unreferenced partials/layouts (`info`, `warning`, `error`)
- **excludeOrphanPatterns** / **excludeAnalysisPatterns** — exclude paths from orphan or dead-code analysis (e.g. `**/Resources/Extensions/**`)

Individual rules can be enabled or disabled under `rules:`:

```yaml
rules:
  dead-code/partial-all-arguments: false
```

## Detailed report

In addition to stdout output, a separate JSON report can be written with all findings and per-file complexity calculations. The stdout format (`--format`) is independent — the report is always JSON.

**Via CLI:**

```bash
vendor/bin/fluidlint scan . --report-file=var/fluidlint-report.json
```

**Via configuration:**

```yaml
report:
  path: var/fluidlint-report.json
```

`--report-file` overrides `report.path`. Missing parent directories are created automatically. File paths in the report are relative to the scan start path.

The report contains:

| Field          | Description                                         |
|----------------|-----------------------------------------------------|
| `generatedAt`  | Analysis timestamp                                  |
| `filesScanned` | Number of templates scanned                         |
| `issueCount`   | Number of findings                                  |
| `thresholds`   | Active threshold settings                           |
| `complexity`   | Per-file complexity including calculation breakdown |
| `issues`       | All findings with context                           |

Per file under `complexity`:

| Field           | Description                                     |
|-----------------|-------------------------------------------------|
| `complexity`    | Total score (base 1 plus branching ViewHelpers) |
| `branchCounts`  | Points per ViewHelper (e.g. `f:if: 3`)          |
| `contributions` | Individual contributions with line and points   |

## Checks

| Category   | Rules                                                                                                                                                                                                                                                                            |
|------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Structure  | `fluid/parse-error`, `fluid/nesting-depth`, `fluid/empty-section`, `fluid/duplicate-section`                                                                                                                                                                                     |
| Complexity | `complexity/threshold-exceeded`                                                                                                                                                                                                                                                  |
| Dead code  | `dead-code/unreachable-then`, `dead-code/unreachable-else`, `dead-code/unreachable-case`, `dead-code/unused-variable`, `dead-code/unused-partial-argument`, `dead-code/partial-all-arguments`, `dead-code/orphan-partial`, `dead-code/orphan-layout`, `dead-code/unused-section` |

## TYPO3 projects

**Standalone CLI** (recommended for most projects):

```bash
composer require --dev cru/fluidlint
vendor/bin/fluidlint scan .
```

**TYPO3 extension** (console command inside TYPO3):

```bash
composer require --dev cru/typo3-fluidlint
vendor/bin/typo3 fluidlint:analyze
```

Both use the same analysis engine.

## CI example

```yaml
- run: composer install
- run: vendor/bin/fluidlint scan . --format=json --fail-on=error
- run: vendor/bin/fluidlint scan . --report-file=var/fluidlint-report.json
```

Archive the JSON report as a CI artifact or feed it into dashboards.

## Limitations

- Conditions depending on runtime controller variables cannot be classified as dead code
- Dynamic partials (`f:render partial="{name}"`) are not resolved
- Action templates without Extbase mapping may be reported as orphans (usually `info`)
- TypoScript with unresolved constants (`{$...}`) is ignored
- `f:render` with `_all` skips per-argument checks

## License

GNU General Public License v3.0 or later — see [LICENSE](LICENSE).
