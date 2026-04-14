# Crob Tests

Manual test scripts. No PHPUnit — Crob is a proof of concept heading to Python rewrite, and PHP test infrastructure would be work against that future.

## Running Tests

```bash
cd c:/xampp/htdocs/crob
php tests/test-brain-roundtrip.php
php tests/test-three-tier.php
php tests/test-sidecar-roundtrip.php
php tests/test-sidecar-missing.php
php tests/test-distinct-objects.php
php tests/test-domain-dedup.php
```

Each prints `PASS` and exits 0, or `FAIL:` followed by reasons and exits 1.

Tests isolate themselves in `tests/tmp-brain-data/` and clean up on success.

## Test Coverage

| Test | What it verifies |
|------|------------------|
| `test-brain-roundtrip.php` | Confidence values round-trip through save/load at 2-decimal precision (legacy 1-digit format still parses). |
| `test-three-tier.php` | `learn()` returns correct tier (new / corroborated / ambiguous / restatement) and confidence bump. |
| `test-sidecar-roundtrip.php` | `data/crob.provenance.json` is written on save, parses correctly, and repopulates in-memory provenance on reload. |
| `test-sidecar-missing.php` | Missing sidecar with existing `.crob` logs a warning and continues with empty provenance (does not crash, does not silently continue). |
| `test-distinct-objects.php` | `distinct_objects` counter increments for genuinely new object strings and stays put when the same object is re-learned from a new source. |
| `test-domain-dedup.php` | `www.greensock.com` and `greensock.com` collapse to one source; `direct_teach` sentinel is stable across calls. |

## Phase 1 Quality Baseline

The real validation for Phase 1 is a before/after comparison of confidence distribution against a set of canonical research topics. This is a **manual procedure**, not CI.

### Procedure

1. **Backup current data**:
   ```bash
   mkdir -p data/backups/pre-phase1
   cp data/crob.crob data/backups/pre-phase1/ 2>/dev/null
   cp data/crob.provenance.json data/backups/pre-phase1/ 2>/dev/null
   ```

2. **Snapshot before state**:
   ```bash
   mkdir -p tests/baselines
   php crob.php --dump > tests/baselines/before.txt
   ```

3. **Run 5 canonical learns** (topics chosen for expected cross-source overlap):
   ```bash
   php crob.php --verbose "what is GSAP"
   php crob.php --verbose "what is ScrollTrigger"
   php crob.php --verbose "what is Lenis"
   php crob.php --verbose "what is CSS Grid"
   php crob.php --verbose "what is Three.js"
   ```

4. **Snapshot after state**:
   ```bash
   php crob.php --dump > tests/baselines/after.txt
   ```

5. **Inspect manually**:
   - Look for confidence values `> 0.65` on multi-source facts (should show in `knowledge[subject][].conf`)
   - Look for restatements staying near `0.55`
   - Check `provenance[subject][relation].sources` — should have 2+ entries for corroborated topics
   - Check `distinct_objects` counter — values `> 1` indicate topics where sources gave different object strings

### Success criteria

- Multi-source facts show confidence distinguishably higher than single-source facts
- At least one topic has `distinct_objects > 1` (ambiguous signal for future Voice.php consumption)
- `--verbose` output shows the Morgan-locked format (header line per URL, detail lines for new/corroborated/ambiguous, restatements suppressed)

### Failure mode

If corroboration-bumped facts are indistinguishable from restatement-bumped facts across all 5 URL sets, the provisional weights (`0.15/0.05/0.05`) need retuning. The data model and sidecar stay — only the constants in `Brain::learn()` move. That retune is Phase 2 work.

## Known Limitations (ship with docs)

1. **Path-dependence**: learning A then B yields different final confidence than B then A with identical provenance. Visible in the sidecar (same source list, different confidence). Acceptable for PoC.
2. **`distinct_objects` is monotonic**: it only increments, never decrements. Manual `.crob` edits that remove objects won't sync to the sidecar. Future Voice.php (Direction C) must not present the sidecar count as a live object count.
3. **Paraphrase inflation**: objects are full extracted sentences, not entities. Five paraphrases from one page produce five distinct object strings. Domain-level source dedup mitigates this but doesn't eliminate it. This is the noise floor Phase 1 operates above.
4. **Sidecar-missing confidence inflation**: if the sidecar is deleted while `.crob` persists, all previously-seen sources become "unknown" on reload. Future learns from those domains count as new sources and corroborate. This is why the load path warns rather than silently continuing.
