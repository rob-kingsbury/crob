# Crob

A curious, self-learning AI that grows its knowledge by exploring the web. No neural networks, no pre-trained models, no external AI APIs — just pattern matching, persistent memory, source-tracked confidence, and insatiable curiosity.

## What Is Crob?

Crob is an experiment in **minimum viable intelligence**: how much useful behavior can you get from a transparent, auditable, file-based system with zero black boxes? Every fact Crob knows is inspectable. Every confidence score traces back to the URLs it came from. Every interest emerges from behavior, not declarations.

Crob has five components:

| Component | File(s) | Purpose |
|-----------|---------|---------|
| **Brain** | `data/crob.crob` + `data/crob.provenance.json` | Facts, relationships, confidence, and source provenance |
| **Voice** | `data/crob.voice` | Language patterns learned from real sentences |
| **Curiosity** | `data/crob.queue` + `data/crob.completed` | Priority queue of topics to research + history |
| **Research** | (code only) | DuckDuckGo search, HTML scrape, fact extraction |
| **Interests** | `data/crob.interests` | Derived interest profile (emergent, not declared) |

## How It Works

1. You ask Crob a question
2. Crob queries its Brain for a direct or partial match
3. If it does not know, Research fetches 5 DuckDuckGo results
4. Each URL is fetched, scraped, and scanned for fact-shaped sentences
5. Each fact is learned with **the URL as its source**, so Brain tracks who said what
6. Crob follows rabbit holes autonomously, prioritized by its current interests
7. Interests re-compute from behavior after every learning cycle

## The `.crob` Format

A self-compressing, human-readable knowledge format where structure encodes meaning.

```crob
;!crob v0.1
;@born=2026-04-14
;@facts=42

; Symbol definitions (auto-generated after 5 uses of a term)
@G=GSAP
@js=JavaScript

; Knowledge: subject:relation.confidence>object
@G:=.70>animation library
@G:<.55>@js
@js:=.95>programming language
```

**Relations**:

| Symbol | Name | Example |
|--------|------|---------|
| `:=` | IS | `@G:=>animation library` |
| `:>` | HAS | `@js:>>functions,variables` |
| `:<` | PART_OF | `@G:<>@js` |
| `:~` | RELATES | `@G:~>motion design` |
| `:@` | USED_BY | `@G:@>web developers` |
| `:!` | NOT | `CSS:!>programming language` |
| `:#` | INSTANCE | `React:#>JavaScript framework` |
| `:?` | UNCERTAIN | `Crob:?>sentient` |

**Confidence**: two decimal digits, `.00` through `.99`. 100% is intentionally not representable — nothing is certain.

**Symbol compression**: when a term appears 5+ times, Crob generates a shorthand symbol (e.g. `@G` for `GSAP`) and rewrites future references. The brain gets denser the more it learns.

See [`docs/FORMAT.md`](docs/FORMAT.md) for the full specification.

## Confidence Vectors (Phase 1, 2026-04-14)

Crob's confidence model is **source-aware**. When a fact is re-encountered, Brain looks at WHERE it came from and decides what kind of evidence it is:

| Condition | Bump | Meaning |
|-----------|------|---------|
| New domain, existing object | **+0.15** | **Corroborated** — independent source confirms existing fact |
| New domain, new object | **+0.05** | **Ambiguous** — could be elaboration, could be contradiction |
| Same domain, any restatement | **+0.05** | **Restatement** — repetition isn't new evidence |

Source comparison happens at the **domain level** — `greensock.com/page1` and `www.greensock.com/page2` count as one source. Otherwise DuckDuckGo results pages on the same site would produce false corroboration.

Every subject+relation pair tracks its provenance in `data/crob.provenance.json`:

```json
{
  "GSAP": {
    ":=": {
      "sources": ["greensock.com", "css-tricks.com", "developer.mozilla.org"],
      "first_seen": 1712000000,
      "last_seen": 1712100000,
      "distinct_objects": 2
    }
  }
}
```

`distinct_objects` is the contradiction signal. If three sources all say GSAP is an "animation library," that's `distinct_objects: 1` — clean corroboration. If one says "library" and another says "plugin system," `distinct_objects: 2` — worth auditing, because the object strings disagree.

> **Note**: The 0.15/0.05/0.05 weights are provisional. They'll be retuned in Phase 2 against real multi-URL research data.

## Interest Profiling

Crob derives interests from **what he actually does**, not what he's told to care about. The Interests component is a read-only observer that scores every subject by:

- **Knowledge density** (35%) — how many relations Brain has about it
- **Behavioral recurrence** (35%) — how often it appears in completed research
- **Confidence** (15%) — average confidence across its relations (now meaningful thanks to Phase 1)
- **Teach bonus** (15%) — extra weight for directly-taught facts

All scores decay exponentially with a 14-day half-life. Interests split into two tiers:

- **Established** — 3+ independent research origins OR 5+ distinct relations
- **Tentative** — below that threshold (emerging, too early to tell)

Established interests feed back into the curiosity queue: topics adjacent to Crob's current interests get a priority boost, so he chases threads that match his emergent character instead of random rabbit holes.

## Installation

Requires PHP 8.0+ and `curl`. No composer, no dependencies.

```bash
git clone https://github.com/rob-kingsbury/crob.git
cd crob
php crob.php --help
```

## CLI Usage

```bash
# Interactive REPL
php crob.php

# Ask a question
php crob.php "what is GSAP"

# Ask with per-URL learning output (see source attribution live)
php crob.php --verbose "what is GSAP"

# Run one item from the research queue
php crob.php --learn

# Show stats (knowledge, queue, interests)
php crob.php --stats

# Show emergent interest profile
php crob.php --interests

# Inspect research queue
php crob.php --queue

# Full debug dump (brain, voice, curiosity, interests, provenance)
php crob.php --dump

# Help
php crob.php --help
```

### Verbose output example

```
[learn] greensock.com -> 4 facts (3 stored, 1 duplicate)
  GSAP REL_IS: new (confidence: 0.50)
  GSAP REL_HAS: new (confidence: 0.50)

[learn] css-tricks.com -> 5 facts (2 stored, 3 duplicate)
  GSAP REL_IS: corroborated (confidence: 0.65)

[learn] developer.mozilla.org -> 3 facts (1 stored, 2 duplicate)
  GSAP REL_IS: ambiguous -- 3 distinct objects now (distinct_objects: 3)
```

Restatements are suppressed as noise. Only new / corroborated / ambiguous events print detail lines.

## Interactive Commands

Inside the REPL:

| Command | What it does |
|---------|--------------|
| `<question>` | Ask anything — Crob queries knowledge first, researches if unknown |
| `teach me that X is Y` | Teach a fact directly (confidence 0.9, source `direct_teach`) |
| `stats` | Quick stats summary |
| `queue` | Show next 5 research topics |
| `interests` | Show emergent interest profile |
| `learn` | Process one item from the research queue |
| `quit` / `exit` / `bye` | Stop |

## Architecture

```
crob.php (CLI entry)
     |
     v
Crob.php (orchestrator)
 |-- Brain.php      Knowledge + provenance + .crob format
 |-- Voice.php      Language patterns + speech synthesis
 |-- Curiosity.php  Priority queue + origin tracking + completion history
 |-- Research.php   DuckDuckGo + HTML scrape + fact extraction
 `-- Interests.php  Read-only observer, derives weighted topic profile
```

**Key principles**:

- **Read before write**: every component can be audited without running code
- **No side effects on read**: querying never changes state
- **Read-only observers stay read-only**: Interests never writes to Brain
- **Source provenance is non-negotiable**: every fact traces back to where it came from
- **Compute-on-demand**: derived artifacts (interest profiles, symbol tables) regenerate, never persist conflicting state

## Testing

Manual test scripts in `tests/`. No PHPUnit — Crob is a proof of concept heading toward a Python rewrite, and PHP test infrastructure is work against that future.

```bash
php tests/test-brain-roundtrip.php     # Confidence precision
php tests/test-three-tier.php          # Three-tier merge logic
php tests/test-sidecar-roundtrip.php   # Provenance sidecar IO
php tests/test-sidecar-missing.php     # Graceful degradation
php tests/test-distinct-objects.php    # Contradiction counter
php tests/test-domain-dedup.php        # Domain-level source dedup
```

Each prints `PASS` and exits 0, or `FAIL:` with reasons and exits 1.

See [`tests/README.md`](tests/README.md) for the Phase 1 quality baseline procedure.

## Philosophy

Crob exists to answer: **how much useful behavior can you get from a transparent, auditable system with zero black boxes?**

The constraints are deliberate:

- **No neural networks**, every decision is traceable to a line of code
- **No pre-trained models**, Crob starts knowing nothing and builds up
- **No external AI APIs**, subscription interfaces only, no paying per token
- **File-based persistence**, you can open the brain in a text editor
- **First-principles honesty**, if something is uncertain, the format says so

The ceiling is low. Crob will never write poetry or debug C++. But the floor is explicable, which most modern AI is not.

## Roadmap

**Phase 1 (shipped 2026-04-14)**: Source-aware confidence vectors. Provenance sidecar. Three-tier merge logic. `--verbose` output. Full test suite.

**Phase 2 (next)**: Confidence-aware Voice output (answers sound different depending on how sure Crob is). Retune provisional weights against real multi-URL baseline data. Contradiction audit tooling.

**Phase 3 (future)**: Python rewrite. Web interface for brain exploration. Curiosity queue items carrying reasoning trails.

See [`docs/ROADMAP.md`](docs/ROADMAP.md) for longer-horizon thinking.

## Authors

- **Rob Kingsbury** — design, direction, epistemology
- **Claude** (Anthropic) — implementation partner
- **Soren, Atlas, Morgan** — AI personas in the `claude-collab` project that review Crob's plans before he builds them

## License

MIT. Do whatever you want with it.

---

Built by [Kingsbury Creative](https://kingsburycreative.com), boutique web design and development in Arnprior, Ontario.
