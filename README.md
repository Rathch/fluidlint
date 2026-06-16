# fluidlint

Linting, cyclomatic complexity and dead-code analysis for TYPO3 Fluid templates.

SPDX-FileCopyrightText: 2025 Christian Rath-Ulrich  
SPDX-License-Identifier: GPL-3.0-or-later

fluidlint scans Fluid template structure without bootstrapping TYPO3. Custom ViewHelper PHP classes are not validated here — use PHPStan/PHPCS for that. On TYPO3 14.2+, complement this tool with `typo3 fluid:analyze` for syntax errors, invalid ViewHelpers and deprecations.

## Requirements

- PHP 8.2+
- `typo3fluid/fluid` 2.15+ (TYPO3 v13) or 4.x (TYPO3 v14)

## Installation

```bash
composer require --dev cru/fluidlint
```

## Usage

```bash
# Full analysis
vendor/bin/fluidlint scan packages/myext/Resources/Private/

# Individual checks
vendor/bin/fluidlint complexity Resources/Private/Templates/
vendor/bin/fluidlint dead-code Resources/Private/

# CI output
vendor/bin/fluidlint scan . --format=json --fail-on=error
vendor/bin/fluidlint scan . --format=sarif > fluidlint.sarif
```

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

## Checks

| Category   | Rule IDs                                                                                                                                                                                                 |
|------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Structure  | `fluid/parse-error`, `fluid/nesting-depth`, `fluid/empty-section`, `fluid/duplicate-section`                                                                                                             |
| Complexity | `complexity/threshold-exceeded`                                                                                                                                                                          |
| Dead code  | `dead-code/unreachable-then`, `dead-code/unreachable-else`, `dead-code/unreachable-case`, `dead-code/unused-variable`, `dead-code/orphan-partial`, `dead-code/orphan-layout`, `dead-code/unused-section` |

## TYPO3 integration

Install the extension from `packages/typo3-fluidlint` in your TYPO3 project:

```bash
vendor/bin/typo3 fluidlint:analyze
```

This delegates to the same core library as the standalone CLI.

## CI example

```yaml
- run: composer require --dev cru/fluidlint
- run: vendor/bin/fluidlint scan . --format=json --fail-on=error
# Optional on TYPO3 14.2+:
- run: vendor/bin/typo3 fluid:analyze
```

## Limitations

- Conditions using runtime controller variables cannot be classified as dead code
- Dynamic `f:render partial="{name}"` references are not resolved
- Orphan detection for action templates may produce info-level false positives without Extbase mapping

## License

This project is licensed under the GNU General Public License v3.0 or later. See [LICENSE](LICENSE) for details.
