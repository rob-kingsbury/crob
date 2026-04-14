# Semi-Formal Reasoning × Crob: Crossover Analysis

**Date:** 2026-04-01
**Source:** Meta's "Agentic Code Reasoning" paper (arXiv 2603.01896)
**Status:** Pending Atlas review

---

## Background

Meta researchers introduced **semi-formal reasoning** — a structured prompting technique where an LLM agent must fill out a "logical certificate" before producing an answer. The certificate requires:

1. **Explicit premises** — what the agent knows and where it came from
2. **Traced execution paths** — concrete step-by-step reasoning, not pattern-matching shortcuts
3. **Formal conclusions** — derived from the above, not guessed

Results: code review accuracy improved from 78% to 93% (patch equivalence), fault localization Top-5 accuracy gained 5 points, code Q&A reached 87% (+9 points). The key insight is that **structured constraints prevent agents from taking reasoning shortcuts**.

---

## Where Crob Could Borrow

### 1. Fact Extraction (Research.php)

**Current state:** `looksLikeFact()` uses regex heuristics — checks sentence length, looks for patterns like "is a", "defined as", filters out uncertainty markers. Binary pass/fail.

**Semi-formal upgrade:** Each extracted fact could pass through a structured validation template:

```
PREMISE:    [source URL, sentence context]
EVIDENCE:   [fact indicator pattern matched, cross-source count]
RELATION:   [detected type + WHY this classification]
CONFIDENCE: [derived score, not default 0.5]
VERDICT:    [accept/reject/flag-for-corroboration]
```

**Benefit:** Moves from "does this sentence pattern-match as a fact?" to "can I justify calling this a fact?" — the same philosophical shift Meta demonstrated.

**Implementation complexity:** Medium. Could be a new `FactCertificate` class that wraps the existing extraction pipeline. Research.php's `extractFacts()` returns raw strings today; it would return certificate objects instead.

### 2. Confidence Derivation (Brain.php)

**Current state:** `learn()` bumps confidence by flat +0.1 when the same relation is re-encountered. No distinction between sources.

**Semi-formal upgrade:** Track WHY confidence changes:

```
PRIOR:        [existing confidence, source count]
NEW EVIDENCE: [same source restatement | independent corroboration | contradictory]
ADJUSTMENT:   [+0.05 for restatement | +0.15 for independent | -0.1 for contradiction]
POSTERIOR:    [new confidence with reasoning trail]
```

**Benefit:** "I've seen this 5 times from 5 sources" ≠ "I've seen this 5 times from 1 source." Currently Crob treats them identically. A provenance-aware confidence model would dramatically improve knowledge quality.

**Implementation complexity:** Medium-high. Requires adding source provenance to the `.crob` format (or a sidecar file). The learn() method signature would need a `$source` parameter.

### 3. Relation Detection (Research.php)

**Current state:** `detectRelation()` uses keyword matching — "is a" → REL_IS, "part of" → REL_PART_OF, etc.

**Semi-formal upgrade:** A reasoning template for relation classification:

```
SENTENCE:     [the raw fact]
SUBJECT:      [identified subject]
OBJECT:       [identified object]
CANDIDATES:   [REL_IS because..., REL_HAS because..., REL_PART_OF because...]
SELECTED:     [highest-confidence relation + reasoning]
```

**Benefit:** Reduces misattribution. "JavaScript has GSAP" vs "GSAP is part of JavaScript" — the current keyword approach could misclassify depending on sentence structure.

**Implementation complexity:** Low-medium. Could be a lookup table upgrade without changing architecture.

---

## Where Crob Could Inform Meta's Approach

### 1. Curiosity-Driven Exploration

Meta's agents are task-scoped — they answer a specific question, then stop. Crob's curiosity system (priority queue + depth limiting + origin tracking) provides a lightweight model for giving structured-reasoning agents **autonomous exploratory behavior**.

**Concrete contribution:** An agent that uses semi-formal reasoning to validate each step of an exploration path, guided by a curiosity queue that decides WHAT to explore next. Structured reasoning provides the discipline; curiosity provides the direction.

### 2. Context Window Management via Symbol Evolution

Meta's agents read many files during extended reasoning chains, accumulating context. Crob's `.crob` format auto-creates shorthand symbols after 5 uses of a term. This compression principle could help agentic systems maintain longer reasoning chains without exceeding context limits.

**Concrete contribution:** During a multi-step code review, frequently-referenced functions/files could be aliased (similar to Crob's `@G=GSAP`), reducing token overhead in the logical certificate.

### 3. Behavioral Interest Profiling as Attention Signal

Crob's Interests.php computes weighted topic profiles: density(0.35) + recurrence(0.35) + confidence(0.15) + teach bonus(0.15), with exponential decay. This is effectively a **soft attention mechanism**.

**Concrete contribution:** Meta's structured prompting tells agents HOW to reason. Crob's interest profiling could tell agents WHAT'S WORTH reasoning about — prioritizing which code paths to analyze deeply vs. skim, based on accumulated behavioral signal from prior reviews.

---

## Implementation Recommendations

### Phase 1: Low-Hanging Fruit
- Add source provenance to `Brain::learn()` (new `$source` parameter)
- Create `FactCertificate` class wrapping extraction output
- Differentiate confidence bumps: restatement (+0.05) vs corroboration (+0.15)

### Phase 2: Structured Validation
- Build reasoning templates for `detectRelation()`
- Add cross-source fact verification in `Research::investigate()`
- Certificate-based confidence that replaces the flat +0.1 model

### Phase 3: Full Integration
- Curiosity queue items carry semi-formal justification for WHY they were enqueued
- Background learning produces auditable reasoning trails
- Interest profiling weights informed by certificate quality scores

---

## Open Questions

1. **Complexity vs Philosophy:** Crob's core philosophy is "minimum viable intelligence." Does semi-formal reasoning push it toward over-engineering, or does it deepen the experiment?
2. **Performance:** Certificates add overhead. For a PHP CLI tool with web scraping, is the latency acceptable?
3. **Python Rewrite Timing:** The roadmap mentions a Python rewrite. Should these changes wait for that, or prove the concept in PHP first?
4. **Collab Integration:** How does this interact with the planned Interests → claude-collab port? Does structured reasoning in Crob inform how Soren/Atlas/Morgan should validate their own claims?

---

*Analysis prepared for Atlas review. Cross-reference with crob ARCHITECTURE.md and Meta's arXiv 2603.01896.*
