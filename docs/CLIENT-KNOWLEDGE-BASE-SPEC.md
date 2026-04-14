# Client Knowledge Base — Spec

**Status**: Draft, not built
**Date**: 2026-04-14
**Motivation**: Replace fragile markdown context files (`.claude/context.md`, `clients/_DASHBOARD.md`) with structured, provenance-tracked facts that accumulate over months instead of being rewritten every session.

---

## Problem

Kingsbury Creative runs multiple simultaneous client projects. Each one generates implicit knowledge that's hard to persist cleanly:

- Brand preferences ("Bill hates stock photos, loves gritty authentic")
- Approval patterns ("the CEO always rewrites the homepage hero himself")
- Revision history ("v3 was rejected because the nav was too aggressive")
- Technical constraints ("they're on LiteSpeed, no Node required")
- Stakeholder relationships ("Sarah is the real decision maker, not the listed contact")

Right now this lives in three places:

1. **Markdown context files** — fragile, unstructured, decay quickly, no confidence signal
2. **Rob's head** — doesn't survive context switches, sessions, or weeks away from a project
3. **Session handoffs** — useful for the next session only, not for the project's lifetime

None of these answer "what do we know about client X, and how sure are we?"

## Solution

A per-client Crob instance — same Brain, same confidence vectors, same `.crob` format — storing client-specific knowledge with source provenance pointing at the document/meeting/email each fact came from.

The existing Crob code already handles this. We just need a thin wrapper that:

1. Scopes data directories per client
2. Uses sentinel source strings instead of URLs (`"meeting_2026-02-14"`, `"email_from_bill_re_logo"`, `"revision_3_rejected"`)
3. Exposes a CLI for quick read/write operations during a session
4. Integrates with the existing session handoff workflow

No new classes. No fork. Just a wrapper script plus a data directory convention.

---

## Architecture

### Directory layout

```
c:/xampp/htdocs/kingsburycreative/
  clients/
    stompers/
      brain/              NEW — per-client Crob data dir
        crob.crob
        crob.provenance.json
        crob.voice
        crob.queue
        crob.interests
      context.md          EXISTING — unchanged (keep for quick reference)
      assets/             EXISTING
    bandpilot/
      brain/
        ...
```

Each client gets its own isolated Brain. No cross-contamination, no shared symbol table, no accidental leakage of one client's preferences into another's output.

### Data source = Crob runtime

The wrapper is a one-file script that imports the existing Crob PHP code and points it at a client-specific data directory:

```php
// clients/stompers/brain-cli.php
require_once 'c:/xampp/htdocs/crob/src/Crob.php';

$dataDir = __DIR__ . '/brain';
$verbose = in_array('--verbose', $argv);
$crob = new Crob($dataDir, $verbose);

// ...CLI dispatch, reusing crob.php's argv handling...
```

Total: ~30 lines. No modification to the Crob repo. If Crob evolves, the wrapper automatically benefits.

---

## Source sentinel conventions

Crob's `extractDomain()` already handles non-URL strings — they get stored as-is and count as a single stable source. The wrapper uses sentinel strings with a strict naming convention:

| Pattern | Example | Meaning |
|---------|---------|---------|
| `meeting_YYYY-MM-DD` | `meeting_2026-02-14` | In-person or video call |
| `email_<slug>` | `email_from_bill_re_logo` | Specific email thread |
| `revision_<n>_<status>` | `revision_3_rejected` | Design revision outcome |
| `slack_<channel>_<date>` | `slack_general_2026-02-20` | Slack message |
| `invoice_<n>` | `invoice_4` | Billing context |
| `direct_input` | `direct_input` | Rob typed it into the CLI directly |

Because Crob compares sources by "domain" (for non-URLs, the whole string), each sentinel is atomic — two `meeting_2026-02-14` sources count as one. Two different meetings on different dates count as two, and the same fact appearing in both gets the `corroborated` bump.

**This is the same semantic as Crob's URL corroboration**, just with meetings instead of websites. If Bill says "no stock photos" in a meeting AND in an email AND in a revision note, that's 3 independent sources → `corroborated` → high confidence → the AI assistant can act on it without asking.

## CLI usage

```bash
# Quick fact entry during a session
cd clients/stompers
php brain-cli.php learn "owner prefers gritty over polished" --source "meeting_2026-02-14"
php brain-cli.php learn "owner prefers gritty over polished" --source "email_from_bill_2026-02-20"
# Second learn bumps confidence via corroboration tier (+0.15)

# Query at session start
php brain-cli.php query "owner"
# Output: owner prefers gritty over polished [confidence: 0.65, 2 sources, corroborated]

# Full profile
php brain-cli.php dump

# Emergent interests (what does Crob think this client cares about based on our history?)
php brain-cli.php interests

# Verbose learning (see source attribution live)
php brain-cli.php --verbose learn "approval goes through Sarah not Bill"

# List all facts with low confidence (need more corroboration)
php brain-cli.php audit --confidence-below 0.6
```

### Session integration

Add two hooks to the KC session protocol:

**Session start** (`/session-start`):
```bash
php clients/<active-client>/brain-cli.php summary
# Prints: top 10 established facts, emerging tentative facts,
# any distinct_objects > 1 flags (contradictions to resolve)
```

**Session end** (`/handoff`):
```bash
php clients/<active-client>/brain-cli.php intake < session-notes.txt
# Parses session-notes.txt for "learned:" / "noted:" / "confirmed:" lines
# and creates learn() calls with source = meeting sentinel for the session date
```

The intake script is the one piece of new code worth writing — a simple parser that looks for explicit "learned:" markers in handoff notes and feeds them into the brain. Keeps the pattern explicit: facts only enter the brain when Rob says they do.

---

## Scoping: what the client brain should and shouldn't hold

### In scope

- **Brand preferences** — voice, visual style, forbidden phrases, approved phrases
- **Stakeholder map** — who decides what, who signs off, who to cc
- **Technical constraints** — hosting, stack, accessibility level, browser support
- **Project history** — revisions, rejected directions, approved directions with reasons
- **Relationship context** — personal notes, communication preferences, tone calibration
- **Live issues** — open bugs, pending approvals, blockers

### Out of scope

- **Code** — that's in the client's git repo, not here
- **Credentials** — those live in `c:/xampp/htdocs/.credentials/` per KC rules
- **Raw content** — blog posts, images, videos stay in the client repo
- **SEO data** — separate Crob instance recommended (different source conventions, different decay)
- **Generated drafts** — LLM output, not accumulated knowledge

The brain is for things that are **true about the client and change slowly**. Not code state. Not session state. Not assets. Just the accumulated understanding that usually lives in Rob's head.

---

## Integration with existing KC workflows

### Doesn't replace

- `CLAUDE.md` — project rules still live there, they're static
- `docs/SEO_STRATEGY.md`, `docs/LOCAL_POSITIONING.md` — global KC docs, not client-specific
- Client Git repos — code is code
- GitHub Issues — task tracking stays where it is
- Session handoff notes — the brain augments, doesn't replace

### Replaces

- Ad-hoc `clients/<name>/context.md` files that decay between sessions
- "Rob explains the client context in chat every time we start a session"
- Risk that the next AI assistant will make a stock-photo suggestion because nobody wrote down that Bill hates them

---

## Confidence-aware output (downstream value)

Once the brain has real data and Phase 2 of Crob ships (confidence-aware Voice), queries could return output like:

```
> php brain-cli.php query "hero section preferences"

High confidence (multiple sources, corroborated):
  - Owner wants large hero photography, no stock [0.80, 3 sources]
  - Homepage hero rewritten by owner every revision [0.75, 4 revisions]

Medium confidence (one source, not yet corroborated):
  - Consider animation on scroll into hero [0.55, meeting_2026-02-14 only]

Contradiction flag (distinct_objects > 1):
  - "Hero should feel authentic" vs "Hero should look professional"
    Sources: meeting_2026-02-14 said authentic, email_from_partner said professional.
    Human audit recommended before acting on either.
```

Instead of an LLM confidently hallucinating what Bill wants, the brain surfaces what's actually corroborated, what's tentative, and what's genuinely unclear.

---

## Implementation estimate

| Task | Effort | Notes |
|------|--------|-------|
| Wrapper script (`brain-cli.php` per client) | 2 hours | Thin dispatcher around existing Crob CLI |
| Source sentinel convention doc | 1 hour | This spec + naming guide in KC docs |
| `learn` subcommand with `--source` flag | 1 hour | Tiny — Crob already supports it |
| `query` subcommand with confidence filters | 2 hours | Wraps `Brain::query()` + `getProvenance()` |
| `intake` parser for handoff notes | 2 hours | Looks for `learned:` / `noted:` markers |
| Session hook integration (start + end) | 1 hour | Two bash wrappers |
| First client migration (pick Stompers — small, well-understood) | 2 hours | Type in known facts, verify queries work |

**Total: ~1.5 days** for the first working version across one client.

---

## First client: Stompers

Recommended first migration because:

1. **Small surface area** — single-page site, limited stakeholder map
2. **Strong voice requirement** — rough B-sides music angle is exactly the kind of thing that must survive between sessions
3. **Rob already has the knowledge** — can type it in in 30 minutes instead of reconstructing from scratch
4. **Clear corroboration sources** — multiple meetings with Bill, revision feedback notes, existing site copy
5. **Validates the whole pattern** — if it helps for Stompers, the pattern works for any client

Seed facts to type in on day 1:

```
php brain-cli.php learn "Stompers is a bar and restaurant" --source direct_input
php brain-cli.php learn "owner is Bill" --source direct_input
php brain-cli.php learn "Bill prefers gritty over polished" --source meeting_2026-02-14
php brain-cli.php learn "Bill hates stock photography" --source email_from_bill_feb
php brain-cli.php learn "angle is B-sides music authenticity" --source revision_1_approved
# ... and so on
```

After entry, query validation:

```
php brain-cli.php query "Bill"
# Should surface everything known about Bill with confidence and sources
```

## Open questions

1. **Where does the wrapper live?** In the client directory or in a shared `kingsburycreative/bin/` location? Leaning client-directory for isolation, but shared means one code path to fix if the Crob API changes.

2. **Do we bundle the Crob repo as a git submodule in each client repo, or reference the system-wide install?** Submodule = reproducible but heavy. Reference = lighter but couples client to the system install. Recommend: reference for now, switch to submodule if Crob ever changes in breaking ways.

3. **How do we handle facts that become false over time?** Bill changes his mind about stock photos. Brain's current model doesn't support "this was true, now it's not." Options: (a) add a `:!` (NOT) relation that overrides previous `:=`, (b) archive old facts with a timestamp, (c) manual brain editing. Simplest = (c) for now, revisit when it bites.

4. **Cross-client patterns** — if 3 clients all prefer authenticity over polish, is that a KC-level insight worth surfacing? Possibly, but out of scope for v1. Flag as future work.

---

## Why this, not just better markdown

Markdown context files already exist. What does this actually add?

| Feature | Markdown | Client Crob |
|---------|----------|-------------|
| Structured queries | No (grep) | Yes (`query "bill"`) |
| Confidence signal | No | Yes (0.0–0.99) |
| Source provenance | Informal comments | Required field |
| Corroboration detection | Manual | Automatic (tier bumps) |
| Contradiction flagging | No | Yes (`distinct_objects > 1`) |
| Interest emergence | No | Yes (Interests.php) |
| Fact-level recency | No | Yes (`last_seen` timestamps) |
| Decay over time | No (rot silently) | Configurable |

The real win is **contradiction detection**. Markdown lets you write contradictory notes weeks apart without noticing. Client Crob surfaces the contradiction the moment the second fact lands, and forces a human audit before acting on either.

---

## Next steps

1. Build the wrapper script (2 hours)
2. Migrate Stompers as the pilot (2 hours)
3. Run one real session using the brain for context
4. Report back: did it surface anything markdown didn't?
5. If yes, migrate a second client (BandPilot or another active project)
6. If no, the pattern doesn't pull its weight for this use case and we cut the wrapper

**Explicit failure mode**: if after two client migrations the brain isn't surfacing signal the markdown files didn't already have, the wrapper is dead weight. Delete it. Keep Crob for the reasoning-provenance use cases it was built for (knowledge accumulation from research) and don't force it into client work where markdown is good enough.

---

*Spec prepared 2026-04-14. Not yet implemented. No commitment until at least one client migration validates the pattern.*
