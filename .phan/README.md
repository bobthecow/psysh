# Phan static analysis

## Quick Start

```bash
make phan # Run static analysis
```

## Baseline strategy

PsySH supports wide dependency version ranges (php-parser 4.0-5.x, symfony 3.4-7.x), which creates version-specific issues. We use **separate baseline files** to handle these:

### `baseline-min-versions.php`

**False positives** from minimum versions (php-parser 4.0, symfony 3.4).

Issues occur because Phan doesn't see newer APIs that were added in later versions:

- `Node\Name::getParts()` method (use `->parts` property in 4.0)
- `Node\VariadicPlaceholder` class (added in 4.3+)
- `Node\Scalar\Int_`, `Float_` (were `LNumber`/`DNumber` in 4.0)
- `Node\UnionType` (added in 4.9+)

**Remove when:** Dropping php-parser 4.x or symfony 3.x/4.x support.

### `baseline-new-deprecations.php`

**Future deprecations** from latest versions (php-parser 5.x).

For example:

- `UseUse` → `UseItem`
- `LNumber` → `Int_`
- `->parts` property → `getParts()` method
- Symfony Console removed methods (`asText()`, etc.)

**Remove when:** Dropping php-parser 4.x or symfony console 3.x support.

### `baseline-internal-deprecations.php`

**Internal PsySH deprecations** maintained for backward compatibility:

- `Configuration::getManualDb()` → Use `getManual()`
- `Configuration::applyFormatterStyles()` → Use Themes
- `Configuration->formatterStyles` property → Use Theme configuration

**Remove when:** Releasing next 0.x release.

### `baseline-external-deps.php`

**External dependency code** issues related to external dependencies and optional extensions.

### `baseline.php`

Merges all the above baselines (default baseline used by Phan).

### `baseline-current-issues.php`

**Auto-generated** baseline of current issues (not loaded by default). Used by CI to enforce "no new issues" while allowing local `make phan` to show all issues.

Regenerate with:
```bash
vendor/bin/phan --allow-polyfill-parser --save-baseline=.phan/baseline-current-issues.php
```
