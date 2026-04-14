# Handoff -- 2026-04-14 (Session 2)

## What Happened This Session

### Summary
**Confidence vectors: full design locked.** Ran two collab plan reviews (Soren + Atlas + Morgan) on the semi-formal reasoning crossover proposal. First review scoped the original plan down to a lean Approach A, cut all certificate/template abstractions, and surfaced two open questions. Second review resolved both questions unanimously. Full implementation plan is ready — waiting on Opus session budget to do plan mode.

### Changes

No code changed this session. All work is design/planning.

| File | Change |
|------|--------|
| `plan-review.md` | Overwritten twice by collab audit — final version contains the two-question decision session. Keep this file. |
| `docs/SEMI-FORMAL-REASONING-CROSSOVER.md` | Unchanged — still the source doc, but the team cut Phase 2/3 and the certificate hierarchy. |

## Decisions Made (Locked)

### Decision 1 — Confidence formula: Option 1 (source count, tiered bumps). Unanimous.

Three-tier table:

| Condition | Bump |
|-----------|------|
| New domain-level source, existing object (corroboration) | +0.15 |
| New domain-level source, new object (ambiguous) | +0.05 |
| Same domain-level source, restatement | +0.05 |

- Source comparison at **domain level** (`greensock.com/page1` + `greensock.com/page2` = one source)
- Recency excluded from base confidence — Interests.php already handles decay, two competing signals is wrong
- Morgan's sidecar-derived option (Option 3) rejected — sidecar doesn't store per-source-per-object history needed for retroactive recompute
- **Required comment in merge block:** `// Provisional weights -- revisit after Phase 1 data collection.`
- Known limitation: path-dependence (learn A then B ≠ learn B then A). Document, don't fix.

### Decision 2 — Contradiction marker: `distinct_objects: int`. Unanimous.

`has_ambiguous_objects: bool` rejected:
- "Ambiguous" overstates what regex can know
- Bool loses magnitude (2 vs 8 distinct objects is meaningful)
- Derivable from `distinct_objects > 1` at zero cost

Trigger: `count(array_diff($incomingObjects, $existingObjects)) > 0` — source-independent.

**Final provenance sidecar schema:**
```json
{
  "GSAP": {
    "REL_IS": {
      "sources": ["greensock.com", "css-tricks.com"],
      "first_seen": 1712000000,
      "last_seen": 1712100000,
      "distinct_objects": 4
    }
  }
}
```

**Critical constraint:** Sidecar keys must always be expanded strings, never symbols. Call `expand()` before any sidecar write. Silent corruption if violated.

## Implementation Plan (Ready to Build)

### Files changing

| File | Change | Lines (est.) |
|------|--------|-------------|
| `Brain.php` | `$source` param on `learn()`, three-tier confidence logic, sidecar IO, two-decimal confidence storage, `extractDomain()`, `computeDistinctObjects()` | ~80 |
| `Research.php` | Restructure loop (per-URL learn, not two-pass), remove `array_unique()` from facts path | ~15-25 |
| `Crob.php` | Pass `"direct_teach"` source from `teach()`, thread source through `backgroundLearn()` | ~15 |
| `data/crob.provenance.json` | NEW sidecar | data file |

Total: ~120-150 lines.

### learn() signature
```php
public function learn(string $subject, string $relation, array|string $objects, float $confidence = 0.5, ?string $source = null): void
```

### Build sequence (strictly ordered)
1. Manual test script: Brain `load()/save()` round-trip with two-decimal confidence (prerequisite — must exist before format changes)
2. Two-decimal confidence storage in `.crob` (Brain.php write + load regex)
3. Remove `array_unique()` from Research.php:67 facts path
4. Add `$source` to `Brain::learn()`, thread from Research and Crob
5. Three-tier confidence logic in Brain.php merge block (lines 139-147)
6. Provenance sidecar: write/read, sidecar-first write order
7. `--verbose` output for `--learn`
8. Before/after quality baseline (5 URLs, dump comparison)

### `--verbose` output format (Morgan, locked)
```
[learn] greensock.com -> 4 facts (3 stored, 1 duplicate)
  GSAP REL_IS: corroborated (confidence: 0.65)
  GSAP REL_HAS: new (confidence: 0.50)
  GSAP REL_IS: ambiguous -- 3 distinct objects now (distinct_objects: 3)
```

### What was cut
- `FactCertificate` class — verbose logging, not reasoning
- Reasoning templates for `detectRelation()` — theater for keyword matching
- Phase 2 and Phase 3 from original crossover doc — deferred until Phase 1 data validates

### Phase 2 (deferred, trigger: Phase 1 data shows meaningful differentiation)
- Voice.php confidence-aware output ("GSAP is a library — high confidence, 3 sources")
- Contradiction audit tooling (surface `distinct_objects > 1` entries in `--dump`)

## Next Session Plan

1. **Switch to Opus** (session budget was at 83% when we stopped — resets in ~2h from session end)
2. **Plan mode** — use Opus with adaptive thinking to produce the final implementation plan
3. **Optional:** Crob-style curiosity research on any gaps Opus surfaces (provenance models, confidence calibration in PHP knowledge systems)
4. **Build** — if plan mode is quick, can start Phase 1 implementation same session

## Key Context
- `plan-review.md` in crob root — final team review with both decisions
- `docs/SEMI-FORMAL-REASONING-CROSSOVER.md` — original proposal (reference only, superseded by team decisions)
- `chatgt-thoughts.txt` — early conceptual framing from ChatGPT, still valid background
- Python rewrite still on roadmap — build in PHP to prove concept, port data model to Python
- Interests.php is untouched — better Brain confidence automatically improves interest quality (read-only observer)

## Previous Session (Session 1) Summary

Added interest profiling system (Interests.php). Scoring: density(0.35) + recurrence(0.35) + confidence(0.15) + teach bonus(0.15) with exponential decay. Two-tier output: established vs tentative with confidence gate. Collab-audit pre-flight run before implementation.
