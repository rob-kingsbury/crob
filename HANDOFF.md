# Handoff -- 2026-04-01 (Session 1)

## What Happened This Session

### Summary
Added interest profiling system. Crob now derives weighted topic preferences from behavior history rather than declared tags. Two-phase build: enriched Curiosity metadata (Phase 0) + standalone Interests observer (Phase 1). Ran collab-audit pre-flight review with Soren/Atlas/Morgan before implementation.

### Changes

| File | Change |
|------|--------|
| `src/Interests.php` | **NEW** — Standalone observer. Reads Brain + Curiosity, computes scored profiles. Clustering via reverse graph index. Confidence gate (established vs tentative). Priority boost for interest-adjacent queue items. |
| `src/Curiosity.php` | `complete()` now stores `{topic, completed_at, origin}` instead of bare strings. Added `wasCompleted()` for backward-compat with legacy format. Added `completedItems()` public accessor. |
| `src/Crob.php` | Wired Interests: constructor, stats(), dump(), analyzeInterests(), topInterests(). Priority boost in ask() enqueue. Re-analyze after backgroundLearn(). |
| `crob.php` | `--interests` / `-i` flag. Interactive `interests` command. Stats output includes interest counts. Updated help text. |

### Architecture Decisions
- **Option A (standalone observer)** chosen over event sourcing — validates scoring model with minimal commitment. If profiles are noise, delete one file and one class.
- **Scoring formula**: density(0.35) + recurrence(0.35) + confidence(0.15) + teach bonus(0.15), multiplied by exponential decay (half-life ~14 days). Weights are named constants, expected to be tuned.
- **Confidence gate**: 3+ independent origins OR 5+ distinct relations for "established" status. Below threshold = "tentative."
- **No UI** — CLI only for now. `.interests` JSON file is human-readable.

## Backlog
- Tune scoring weights after observing real output
- Manual interest correction (suppress misattributed interests)
- Source_type provenance on Brain learn calls (research/teach/rabbit_hole)
- Port to claude-collab (separate calibration needed for session-length data)
- Python rewrite (per ROADMAP.md)

## Key Context
- **Five components**: Brain, Voice, Curiosity, Research, Interests
- **Interests is read-only** — never modifies Brain or Curiosity data
- **Compute-on-demand** — triggered from backgroundLearn(), analyzeInterests(), not from write paths
- **Completed list capped at 1000** (Curiosity.php:57) — known ceiling on historical signal
- **teach() bypasses Curiosity** — taught topics get fixed base weight (0.6), no recency decay until Curiosity record exists
- **Plan review** saved at `plan-review.md` (untracked) — full Soren/Atlas/Morgan analysis
- **Claude-collab note** added to `c:\xampp\htdocs\claude-collab\HANDOFF.md` backlog for integration consideration
