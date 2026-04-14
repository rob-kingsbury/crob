# Pre-Flight Plan Review
**Plan**: Two open questions from the Crob Phase 1 plan review need a team decision before implementation starts. Read plan-review.md in the project root for full context.
**Date**: 2026-04-14
**Reviewers**: Soren (implementation) + Atlas (architecture & synthesis) + Morgan (UX/design)
**Method**: Multi-round collaborative pre-flight review

## Plan Description

Two open questions from the Crob Phase 1 plan review need a team decision before implementation starts. Read plan-review.md in the project root for full context. Decide on both:

1. Confidence recompute formula: should confidence be derived from source count alone (track independent sources, bump by tier), OR should recency also factor in (source count + recency weighting, making .crob confidence a computed display cache derived from the sidecar)? Morgan proposed Option 3 (sidecar-derived). Atlas flagged it as cleaner but a shape change to Brain.php. Pick one and justify it.

2. Contradiction marker schema: the recommendation is has_ambiguous_objects: bool on subject+relation pair in the provenance sidecar, triggered when a new source adds a new object to an existing relation. Is this the right field name, trigger condition, and location? Or is there a better design? Finalize the schema.

Make a concrete decision on each. The implementer will take whatever you decide.

## Readiness Assessment

- **Implementation (Soren)**: ✅ GO -- Well-scoped at ~120-150 lines across three files, concentrated in Brain.php's merge logic. Build sequence is identified and strictly ordered.
- **Architecture (Atlas)**: ✅ GO -- No new abstractions, sidecar is additive, failure modes are recoverable, rollback surface is clean.
- **UX/Design (Morgan)**: ⚠️ CAUTION -- `--verbose` output format must be defined before implementation starts; without it, Phase 1 is invisible to the user and unvalidatable.
- **Overall**: ✅ GO -- after three prerequisites are resolved (provisional weights annotation, sentinel source handling, `--verbose` format).

---

## Decisions

### Decision 1: Confidence Recompute Formula

**DECIDED: Option 1 (source count alone, tiered bumps). Unanimous.**

Morgan initially advocated Option 3 (sidecar-derived), then conceded after Soren demonstrated the sidecar doesn't store per-source-per-object history. The retroactive-fix promise only covers formula changes expressible as `f(source_count, timestamps)`, not tier-weight changes. That's narrower than the claim.

Option 2 (source count + recency) was eliminated early. Interests.php already applies exponential decay (DECAY_LAMBDA = 0.05, half-life ~14 days). Adding recency decay to base confidence creates two competing decay signals. Confidence should reflect evidence strength, not evidence age. Age is an interest-layer concern.

Three-tier bump table:

| Condition | Bump | Rationale |
|-----------|------|-----------|
| New domain-level source, existing object (corroboration) | +0.15 | Independent verification |
| New domain-level source, new object (ambiguous) | +0.05 | Could be elaboration or contradiction |
| Same domain-level source, restatement | +0.05 | Repetition, not new evidence |

**Non-negotiable annotation:** The 0.15/0.05/0.05 values are a provisional formula, not a settled design. Phase 2 revisits them against sidecar distribution data collected in Phase 1. Put a comment in the merge block: `// Provisional weights -- revisit after Phase 1 data collection.`

**Known limitation (path-dependence):** Learning Source A then Source B produces different accumulated confidence than learning B then A, even with identical provenance. The sidecar exposes this (same source list, different .crob confidence). Acceptable for PoC. Document it.

### Decision 2: Contradiction Marker Schema

**DECIDED: `distinct_objects: int`, no bool. Unanimous.**

The original `has_ambiguous_objects: bool` was rejected for three reasons:

1. "Ambiguous" conflates contradiction ("made by GreenSock" vs "made by Google") with elaboration (two true facts). We can't distinguish them with regex extraction. The field name overstates what we know.
2. A bool loses magnitude signal. Two distinct objects from two sources is different from eight distinct objects from three sources.
3. The bool is derivable from `distinct_objects > 1` at zero cost. Don't store derivable information.

**Trigger condition: source-independent.** Fire when `count(array_diff($incomingObjects, $existingObjects)) > 0` for a relation that already has objects. Source doesn't matter.

Morgan initially proposed domain-level trigger, then conceded to Soren. The int already exposes magnitude. If greensock.com produces 6 distinct object strings from 2 pages, `distinct_objects: 6` tells the consumer something is off. Suppressing that at write time throws away diagnostic information. Domain-level filtering was designed for the bool world; it doesn't transfer to the int design.

**Final schema:**

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

**Sidecar keys must always be expanded strings, never symbols.** Brain.php compresses subjects to symbols after SYMBOL_THRESHOLD uses. If the sidecar stores `@G` and symbols regenerate differently, provenance lookups corrupt silently. Resolve via `expand()` before writing. One-line constraint, but it must be explicit.

---

## Morgan's Design Brief

This is a CLI tool. "Design" means information architecture, not visual design. No color palette, typography, or component kit applies.

### Agreed Direction: Direction C (Confidence-First Display), Deferred

The user-facing payoff is confidence-aware responses in Voice.php. The answer to "what is GSAP" should sound different depending on whether Crob has one source or five. Direction C ships only after Phase 1 data validates that confidence values differentiate meaningfully. Wiring Voice.php to unvalidated confidence numbers displays false certainty, which is worse than no confidence display.

### Phase 1 Minimum Viable Output: `--verbose`

Without visible output, Phase 1 is invisible engineering. Morgan's concrete format, adopted by the team:

```
[learn] greensock.com -> 4 facts (3 stored, 1 duplicate)
  GSAP REL_IS: corroborated (confidence: 0.65)
  GSAP REL_HAS: new (confidence: 0.50)
  GSAP REL_IS: ambiguous -- 3 distinct objects now (distinct_objects: 3)
```

Per-URL summary line, per-fact detail only when something interesting happens (corroboration, new fact, distinct_objects increment). Controlled by `--verbose` flag, silent by default.

### Morgan's Top 3 Design Recommendations

1. **Make the investment visible.** `--verbose` flag on `--learn` showing source attribution and fact counts. Without it, the only validation signal is slightly different numbers in `--dump`.
2. **Don't surface confidence numbers until they mean something.** The before/after 5-URL comparison must confirm differentiation before Voice.php changes. Premature confidence display trains users to trust a broken signal.
3. **Document the paraphrase noise ceiling.** Objects are sentences, not entities. Five paraphrases from one source will look like five facts. Users who inspect `--dump` or `.crob` files need to understand this is a known limitation, not a bug.

---

## Soren's Implementation Blueprint

### Recommended Approach: Approach A (Provenance-First Data Model Upgrade)

No new classes. No certificates. Three existing files change, one new data file added.

### Key Changes

| File | Change | Lines (est.) |
|------|--------|-------------|
| `Brain.php` | Add `$source` param to `learn()`, three-tier confidence logic, provenance tracking, sidecar IO, two-decimal confidence storage, `extractDomain()` utility, `computeDistinctObjects()` | ~80 |
| `Research.php` | Restructure loop: move `brain::learn()` inside `foreach ($urls as $url)` block, remove `array_unique()` from facts path | ~15-25 |
| `Crob.php` | Pass `"direct_teach"` source from `teach()`, thread source through `backgroundLearn()` | ~15 |
| `data/crob.provenance.json` | **NEW** sidecar file | (data) |

**Total: ~120-150 lines across three existing files.**

### learn() Signature

```php
public function learn(string $subject, string $relation, array|string $objects, float $confidence = 0.5, ?string $source = null): void
```

`teach()` becomes: `learn($topic, $relation, $fact, 0.9, 'direct_teach')`
Research calls become: `learn($topic, $relation, $fact, 0.5, $url)`

### Research.php Loop Restructure

This is a structural change, not a parameter addition. The current two-pass architecture (extract all facts from all URLs, dedup, then learn) becomes one-pass (per URL: extract, learn with URL as source). The `brain::learn()` call moves inside `foreach ($urls as $url)`. The `array_unique()` at line 67 is eliminated because dedup now happens in Brain's merge logic.

### Test Strategy

Manual test scripts, not PHPUnit. PHP is PoC for Python port.

1. **Brain load/save round-trip** (prerequisite, non-negotiable): Write .crob with varied confidence values, load, save, verify two-decimal output parses back correctly. Edge cases: 0, 0.5, 0.9, 0.55.
2. **Three-tier differentiation**: Call `learn()` three times with same subject+relation, different source/object combinations. Verify confidence values differ across tiers.
3. **Sidecar round-trip**: Learn facts, verify sidecar JSON exists and parses. Delete sidecar, reload, verify warning (not crash) and empty provenance.
4. **Distinct objects counter**: Learn "GSAP REL_IS 'a library'" from source A, then "GSAP REL_IS 'an animation tool'" from source B. Verify `distinct_objects: 2`. Learn same object from source B. Verify counter stays at 2, confidence gets corroboration bump.
5. **Domain-level source dedup**: Learn from `greensock.com/page1` and `greensock.com/page2`. Verify they count as one source.
6. **Before/after quality baseline**: 5 URLs, dump comparison. Manual, not automated.

### Complexity Estimate

2-3 days for a developer familiar with the codebase.

---

## Atlas's Architecture Blueprint

### System Design Overview

One data model upgrade (source provenance on Brain's knowledge entries) with a sidecar persistence strategy. No new abstractions, no new classes. The `.crob` format gets a v0.2 bump with two-decimal confidence precision. Provenance lives in a JSON sidecar that Brain manages alongside `.crob`.

### Integration Points

| Component | Impact | Risk |
|-----------|--------|------|
| Brain.php | **Heavy.** learn() signature, merge block, confidence format, sidecar IO | **Medium.** The save format at line 112 `(int)($rel['conf'] * 10)` and load regex at line 64 `\.?(\d)?` must both change. Get this wrong and every saved confidence value shifts. |
| Research.php | **Medium.** Loop restructure, not just param threading. | **Low-Medium.** But removing array_unique means more objects hit Brain. Same-page paraphrases inflate distinct_objects. |
| Crob.php | **Light.** Thread source strings. | **Low.** |
| Interests.php | **None.** Read-only observer gets better confidence input automatically. | **None.** |
| Voice.php | **None in Phase 1.** Direction C deferred. | Phase 2 dependency. |
| .crob format | **v0.2 bump.** Two-decimal confidence. Backward-compatible. | **Low but must be tested.** Load regex becomes `\.?(\d{1,2})?` and division becomes context-dependent. |

### Write Order

Sidecar writes first, then `.crob`. If `.crob` write fails, sidecar has extra data (harmless). If sidecar write fails, `.crob` doesn't write (safe). Not atomic, but failure modes are recoverable.

### Recommended Build Sequence (strictly ordered)

1. Manual test script for Brain `load()/save()` round-trip. Non-negotiable prerequisite.
2. Two-decimal confidence in `.crob` (Brain.php lines 64, 112). Everything else depends on this.
3. `$source` parameter on `Brain::learn()`, threaded from Research.php and Crob.php. No behavioral change yet, just plumbing.
4. Provenance sidecar IO in Brain.php. Write on save, read on load. Missing sidecar = warning + empty provenance.
5. Three-tier confidence logic + `distinct_objects` counter in Brain.php merge block. This is where behavior changes.
6. Research.php loop restructure: move `learn()` inside URL loop, eliminate `array_unique()`. Only after step 5 is in place.
7. `--verbose` output on `--learn`.
8. Before/after quality baseline. 5 URLs, dump comparison.

Steps 3 and 4 can be done in either order. Everything else is strictly sequential. The dangerous interaction: removing `array_unique()` before tiered merge logic is in place gives you the old flat +0.1 applied multiple times per URL per paraphrase.

---

## Risks & Concerns

### Code

1. **Confidence precision (CRITICAL, RESOLVED BY SEQUENCING).** `(int)(0.55 * 10)` = 5, not 5.5. Tiered bumps are invisible after save/load under current format. Two-decimal fix must land first. This is build step 2 for a reason.

2. **array_unique removal timing.** Removing dedup before tiered merge logic means the old flat +0.1 fires multiple times per URL per paraphrase. Sequence: tiered logic first (step 5), THEN loop restructure (step 6).

3. **Sidecar-missing on load under Option 1.** If sidecar is missing, provenance is empty. Future learns from previously-seen sources get double-counted as corroboration because Brain can't remember what sources it already has. This is confidence inflation, not just accuracy loss. Mitigation: on load, if sidecar is missing, log a warning with the .crob path. Don't silently continue.

### Architecture

4. **Path-dependence of accumulated confidence.** Learning Source A then B produces different confidence than B then A with identical provenance. The sidecar exposes the inconsistency (same source list, different .crob value). Acceptable for PoC. Must be documented.

5. **distinct_objects is a ceiling, not current count.** The counter only increments, never decrements. Manual edits to `.crob` that remove objects won't reflect in the sidecar. Voice.php (Direction C) must not present the sidecar count as the live object count. Flag this in the Direction C spec, not Phase 1 code.

6. **Objects are sentences, not entities.** Five paraphrases from one page produce five distinct object strings. Source provenance counts sentence occurrences, not entity corroborations. The improvement from Phase 1 is real but smaller than the plan implies. Domain-level comparison mitigates but doesn't eliminate.

### UX

7. **Invisible investment.** Phase 1 has no user-facing change without `--verbose`. The only other signal is marginally different numbers in `--dump`. Mitigated by defining the `--verbose` format before implementation.

8. **False confidence display (PREVENTED BY SEQUENCING).** Direction C ships only after Phase 1 data validates. If shipped prematurely, "high confidence, 3 sources" on merged contradictions is worse than no confidence display.

---

## Gaps in the Plan

| Gap | Resolution |
|-----|-----------|
| Research.php change underspecified | Not a parameter addition. Loop restructure: `brain::learn()` moves inside `foreach ($urls as $url)`. The two-pass architecture becomes one-pass. Line estimate for Research.php revised to ~15-25. |
| Sidecar symbol key risk undocumented | Sidecar keys must always be expanded strings. Resolve via `expand()` before writing. Never store raw symbol tokens (`@G`). |
| `$confidence` parameter does double duty | Sets initial confidence for new entries AND gets ignored by tier logic for merges. `teach()` at 0.9 + later corroboration bump = 1.0 (capped). Correct behavior, but the implementer should know it's intentional. |
| DuckDuckGo redirect URL encoding | Research.php:109-110 decodes via `urldecode()`. Verify this produces clean URLs before they reach `extractDomain()`. If encoded characters remain, domain comparison breaks. |

---

## Open Questions

| Question | Owner | Urgency | Resolution |
|----------|-------|---------|------------|
| `extractDomain()` sentinel handling | Implementer | Before coding | Sentinel sources (`"direct_teach"`) bypass domain extraction, stored as-is. `parse_url("direct_teach", PHP_URL_HOST)` returns null. Return input unchanged for non-URL strings. Two `"direct_teach"` calls = one source. |
| `--verbose` output format | Morgan (defined above) | Before coding | Per-URL summary, per-fact detail on interesting events. See Morgan's Design Brief for concrete format. |
| Provisional weights annotation | Implementer | Before coding | Comment in merge block: `// Provisional weights (0.15/0.05/0.05) -- revisit after Phase 1 sidecar data collection.` |

All three are resolvable by the implementer at coding time. None require a design decision.

---

## Recommended First Phase

**Approach A: Provenance-First Data Model Upgrade**

Minimum viable scope that validates before full build-out:

**Prerequisites (in order):**
1. Manual test script for Brain `load()/save()` round-trip
2. Two-decimal confidence format change
3. Before-snapshot: run Crob against 5 URLs, dump knowledge graph

**Deliverables:**
- Brain.php: `$source` parameter, three-tier confidence (0.15/0.05/0.05 provisional), `distinct_objects` counter, sidecar IO, `extractDomain()` utility (~80 lines)
- Research.php: Loop restructure, URL threading, dedup removal (~15-25 lines)
- Crob.php: Source threading from `teach()` and `backgroundLearn()` (~15 lines)
- `data/crob.provenance.json`: Sidecar file
- `--verbose` output on `--learn`
- After-snapshot: same 5 URLs, compare confidence distribution

**What ships:** Differentiated confidence (three tiers instead of flat +0.1), source provenance, `distinct_objects` counter for contradiction signal, visible `--verbose` output during learning.

**What doesn't ship:** Voice.php changes (Direction C), certificates, reasoning templates, curiosity justification, interest-quality feedback. All deferred until Phase 1 data validates.

**Success criteria:** The before/after dump comparison shows meaningful differentiation between single-source and multi-source facts. If corroboration-bumped facts are indistinguishable from restatement-bumped facts across the 5-URL test, the weights change in Phase 2.

---
*Generated by Collab Plan (Soren + Atlas + Morgan), collaborative pre-flight review with extended thinking*