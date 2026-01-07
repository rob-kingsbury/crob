# Crob Architecture

## Overview

Crob is an experimental AI built from first principles. No neural networks. No pre-trained models. No external AI APIs. Just pattern matching, persistent memory, and curiosity.

This document explains every component, why it exists, and how it works.

---

## Table of Contents

1. [Philosophy](#philosophy)
2. [The Three Brains](#the-three-brains)
3. [The .crob Format](#the-crob-format)
4. [Data Flow](#data-flow)
5. [Component Deep Dives](#component-deep-dives)
6. [The Research Engine](#the-research-engine)
7. [Language Learning](#language-learning)
8. [The Curiosity System](#the-curiosity-system)
9. [File Structure](#file-structure)
10. [Extension Points](#extension-points)

---

## Philosophy

### Why Build This?

Modern AI is a black box. You send text to an API, magic happens on someone else's servers, text comes back. You can't inspect it, can't understand it, can't own it.

Crob asks: **What's the minimum viable intelligence?**

The answer: pattern matching + memory + curiosity.

### Core Principles

1. **Transparency**: Every piece of knowledge is inspectable. Read the `.crob` file, see what Crob knows.

2. **Ownership**: Crob runs on your machine. No API keys required. No data leaves unless you want it to.

3. **Growth**: Crob starts knowing nothing and learns through use. Your Crob becomes an expert in YOUR interests.

4. **Simplicity**: If you can't explain how a component works to a beginner, it's too complex.

### What Crob Is NOT

- Not a chatbot pretending to be human
- Not a replacement for LLMs like ChatGPT
- Not trying to pass the Turing test
- Not going to write your code or poems

### What Crob IS

- A learning system you can understand completely
- A knowledge accumulator for your domains
- An experiment in minimal AI
- A teaching tool for how AI concepts work

---

## The Three Brains

Crob has three distinct memory systems, each stored in its own file:

### 1. Knowledge Brain (`crob.crob`)

**What it stores**: Facts and relationships between concepts.

**Format**: The custom `.crob` format (see below).

**Example**:
```crob
@G=GSAP
@js=JavaScript
@G:=.9>animation library
@G:<.8>@js
```

This says: "GSAP is (with 90% confidence) an animation library. GSAP is part of (with 80% confidence) JavaScript."

**Key class**: `Brain.php`

### 2. Language Brain (`crob.voice`)

**What it stores**: Templates for how to speak about things.

**Format**: JSON with patterns grouped by intent.

**Example**:
```json
{
  "patterns": {
    "what_is": [
      "{topic} is {definition}.",
      "Primate brain says: {topic} is {definition}."
    ]
  },
  "personality": {
    "self_reference": ["This nerd", "Your friendly neighborhood nerd"],
    "reptile_brain": ["Reptile brain says:", "Quick instinct:"],
    "primate_brain": ["Primate brain engaged:", "After some thinking:"]
  }
}
```

**Key class**: `Voice.php`

### 3. Curiosity Brain (`crob.queue`)

**What it stores**: Topics Crob wants to research, with metadata.

**Format**: JSON queue with priority and origin tracking.

**Example**:
```json
{
  "queue": [
    {
      "topic": "ScrollTrigger",
      "origin": "GSAP",
      "reason": "Found while researching GSAP",
      "priority": 0.6,
      "depth": 1
    }
  ],
  "completed": ["GSAP", "JavaScript", "CSS Grid"]
}
```

**Key class**: `Curiosity.php`

---

## The .crob Format

### Why a Custom Format?

Existing formats have problems:

| Format | Problem |
|--------|---------|
| JSON | Keys repeat ("subject", "relation", "object" over and over) |
| XML | Verbose tags everywhere |
| SQL | Requires database engine |
| RDF | Overly complex for our needs |

The `.crob` format is:
- Human readable
- Self-compressing (symbols evolve)
- Meaning-encoded (structure = relationship type)
- Lightweight (single text file)

### Syntax Specification

```
; This is a comment

; Symbol definitions (learned shortcuts)
@symbol=Full Term

; Knowledge entries
subject:relation.confidence>object
subject:relation.confidence>object1,object2,object3
```

### Relation Types

| Symbol | Meaning | Example |
|--------|---------|---------|
| `:=` | is / definition | `GSAP:=>animation library` |
| `:>` | has / contains | `JavaScript:>>variables,functions` |
| `:<` | part of / belongs to | `GSAP:<>JavaScript` |
| `:~` | relates to | `GSAP:~>ScrollTrigger` |
| `:@` | used by | `GSAP:@>web developers` |
| `:!` | is not | `CSS:!>programming language` |
| `:#` | instance of | `React:#>JavaScript framework` |
| `:?` | uncertain | `Crob:?>sentient` |

### Confidence Levels

Confidence is optional. Default is 0.5 (50%).

```crob
GSAP:=.9>animation library   ; 90% confident
GSAP:=>animation library     ; 50% confident (default)
```

### Symbol Evolution

When a term appears 5+ times, Crob automatically creates a symbol:

```crob
; Before (verbose)
JavaScript:=>programming language
JavaScript:>>functions
JavaScript:>>variables
React:<>JavaScript
Vue:<>JavaScript

; After (compressed)
@js=JavaScript
@js:=>programming language
@js:>>functions,variables
React:<>@js
Vue:<>@js
```

This happens automatically. The more you use Crob, the more efficient its storage becomes.

---

## Data Flow

### When You Ask a Question

```
┌─────────────────────────────────────────────────────────────────┐
│                         YOU                                     │
│                   "What is GSAP?"                               │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│                    PARSE INPUT                                  │
│         Intent: "what_is"    Topic: "GSAP"                      │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│                 CHECK KNOWLEDGE BRAIN                           │
│                  Do I know "GSAP"?                              │
└─────────┬───────────────────────────────────────┬───────────────┘
          │ YES                                   │ NO
          ▼                                       ▼
┌─────────────────────┐                 ┌─────────────────────────┐
│   REPTILE BRAIN     │                 │    PRIMATE BRAIN        │
│   Fast recall       │                 │    Research mode        │
│   from memory       │                 │                         │
└─────────┬───────────┘                 │  1. Search web          │
          │                             │  2. Fetch pages         │
          │                             │  3. Extract facts       │
          │                             │  4. Learn patterns      │
          │                             │  5. Store knowledge     │
          │                             │  6. Find rabbit holes   │
          │                             └───────────┬─────────────┘
          │                                         │
          ▼                                         ▼
┌─────────────────────────────────────────────────────────────────┐
│                    VOICE: Generate Response                     │
│    Pick template, fill with facts, add personality              │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│                         YOU                                     │
│     "Primate brain says: GSAP is an animation library..."      │
└─────────────────────────────────────────────────────────────────┘
```

### Background Learning

When Crob has topics in its curiosity queue, it can learn autonomously:

```
┌─────────────────────────────────────────────────────────────────┐
│                    CURIOSITY QUEUE                              │
│    1. ScrollTrigger (from: GSAP, priority: 0.6)                │
│    2. Lenis (from: ScrollTrigger, priority: 0.4)               │
│    3. WAAPI (from: animation, priority: 0.3)                   │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼ (pick highest priority)
┌─────────────────────────────────────────────────────────────────┐
│                    RESEARCH: ScrollTrigger                      │
│    1. Search web                                                │
│    2. Extract facts                                             │
│    3. Learn language patterns                                   │
│    4. Find new rabbit holes                                     │
└────────────────────────┬────────────────────────────────────────┘
                         │
          ┌──────────────┼──────────────┐
          ▼              ▼              ▼
    ┌──────────┐   ┌──────────┐   ┌──────────────┐
    │ KNOWLEDGE │   │  VOICE   │   │  CURIOSITY   │
    │   BRAIN   │   │  BRAIN   │   │    QUEUE     │
    │  (facts)  │   │(patterns)│   │(new topics)  │
    └──────────┘   └──────────┘   └──────────────┘
```

---

## Component Deep Dives

### Brain.php

The knowledge storage engine.

**Key Methods**:

```php
// Learn a new fact
$brain->learn("GSAP", Brain::REL_IS, "animation library", 0.9);

// Query knowledge
$result = $brain->query("GSAP");
// Returns: ['subject' => 'GSAP', 'knowledge' => [...], 'match' => 'exact']

// Find related topics (graph traversal)
$related = $brain->related("GSAP", depth: 2);
// Returns: ['ScrollTrigger' => 2, 'JavaScript' => 1, ...]
```

**Internal Structure**:

```php
$knowledge = [
    'GSAP' => [
        ['rel' => ':=', 'obj' => ['animation library'], 'conf' => 0.9],
        ['rel' => ':<', 'obj' => ['JavaScript'], 'conf' => 0.8],
    ],
    'JavaScript' => [
        ['rel' => ':=', 'obj' => ['programming language'], 'conf' => 0.95],
    ],
];
```

### Voice.php

The language pattern engine.

**Key Concepts**:

1. **Patterns**: Templates with placeholders
   ```
   "{topic} is {definition}."
   "Primate brain says: {topic} is {definition}."
   ```

2. **Personality**: Crob's quirks
   - Refers to itself as "this nerd"
   - Has "reptile brain" (fast) and "primate brain" (thoughtful) modes
   - Expresses curiosity about new topics

3. **Pattern Learning**: When Crob reads web content, it doesn't just extract facts. It also learns HOW humans explain things.

   ```php
   // Real sentence from the web
   "GSAP is a powerful animation library for JavaScript"

   // Becomes template
   "{topic} is a {adj} {type} for {context}"
   ```

### Curiosity.php

The research queue manager.

**Key Concepts**:

1. **Priority Queue**: Higher priority topics get researched first
2. **Depth Limiting**: Prevents infinite rabbit holes (max 5 levels deep)
3. **Duplicate Prevention**: Won't re-queue already known topics
4. **Origin Tracking**: Knows WHY each topic was added

### Research.php

The web research engine.

**Process**:

1. **Search**: Uses DuckDuckGo HTML (no API key needed)
2. **Fetch**: Downloads page content
3. **Extract Facts**: Finds sentences that look like definitions
4. **Extract Patterns**: Learns how those sentences are structured
5. **Find Rabbit Holes**: Identifies related topics to explore later

**Fact Detection Heuristics**:

A sentence "looks like a fact" if:
- It's 5-50 words long
- Contains "is", "are", "means", "defined as"
- Does NOT contain "maybe", "probably", "I think"
- Does NOT end with a question mark

---

## The Research Engine

### How Search Works

Crob uses DuckDuckGo's HTML interface (not an API):

```php
$url = 'https://html.duckduckgo.com/html/?q=' . urlencode($query);
```

This is:
- Free (no API key)
- Rate-limited naturally (be nice)
- Returns real search results

### Source Filtering

Some domains are blocked:
- Social media (YouTube, Twitter, Facebook, etc.)
- These have low fact density or require JavaScript

### Fact Extraction

```php
function extractFacts($text, $topic) {
    $sentences = splitIntoSentences($text);

    return array_filter($sentences, function($s) use ($topic) {
        // Must mention the topic
        if (stripos($s, $topic) === false) return false;

        // Must look like a fact
        return looksLikeFact($s);
    });
}
```

### Rabbit Hole Detection

```php
// Finds patterns like "X is related to Y" or "X and Y"
preg_match_all('/\b' . $topic . '\b.{0,50}\b(and|with|using)\s+([A-Z][a-z]+)/', $text, $matches);
```

---

## Language Learning

### How Crob Learns to Talk

Every time Crob reads a webpage, it does two things:

1. **Extract Facts** (goes to Knowledge Brain)
2. **Extract Patterns** (goes to Voice Brain)

### Pattern Abstraction

Real sentence:
```
"CSS Grid is a powerful layout system for modern web design."
```

Becomes template:
```
"{topic} is a {adj} {type} for {context}."
```

### Template Usage

When answering, Crob:
1. Picks a template for the intent (what_is, how_to, etc.)
2. Fills in placeholders with actual facts
3. Adds personality flourishes

---

## The Curiosity System

### How Crob Gets Curious

When researching topic A, Crob might discover topics B, C, D. These become "rabbit holes" and get added to the curiosity queue.

### Priority Calculation

```php
$priority = calculatePriority($topic, $origin, $depth);

// Higher priority if:
// - Strongly connected to known topics
// - Discovered recently
// - Not too deep in the rabbit hole
```

### Depth Limiting

Each topic has a "depth" - how many hops from the original question.

```
You asked: "What is GSAP?"           (depth 0)
    → Found: ScrollTrigger           (depth 1)
        → Found: Lenis               (depth 2)
            → Found: smooth scroll   (depth 3)
                → STOP at max_depth
```

Default max depth is 5.

---

## File Structure

```
crob/
├── crob.php              # CLI interface
├── README.md             # Project overview
├── LICENSE               # MIT license
│
├── src/                  # Core PHP classes
│   ├── Crob.php          # Main orchestrator
│   ├── Brain.php         # Knowledge storage
│   ├── Voice.php         # Language patterns
│   ├── Curiosity.php     # Research queue
│   └── Research.php      # Web research engine
│
├── web/                  # Web interface
│   ├── index.php         # Chat UI
│   └── api.php           # JSON API
│
├── data/                 # Crob's brain files (gitignored)
│   ├── crob.crob         # Knowledge
│   ├── crob.voice        # Language patterns
│   └── crob.queue        # Research queue
│
└── docs/                 # Documentation
    ├── ARCHITECTURE.md   # This file
    ├── FORMAT.md         # .crob format spec
    └── EXAMPLES.md       # Usage examples
```

---

## Extension Points

### Adding New Relation Types

In `Brain.php`:
```php
const REL_CAUSES = ':*';  // New: "X causes Y"
```

Then update the parser to recognize it.

### Adding New Search Engines

In `Research.php`, replace or extend `search()`:
```php
function searchGoogle($query) { ... }
function searchWikipedia($query) { ... }
```

### Adding New Personality Traits

In `Voice.php`, add to the personality array:
```php
'excitement' => [
    "Oh wow!",
    "This is fascinating!",
    "*excited nerd noises*"
]
```

### Custom Fact Extraction

Extend `looksLikeFact()` for domain-specific patterns:
```php
// For code documentation
if (preg_match('/\bfunction\s+\w+\s+returns?\b/i', $sentence)) {
    return true;
}
```

---

## What's Next?

See `docs/ROADMAP.md` for planned features:
- Multiple knowledge bases (switch between domains)
- Export to other formats (JSON-LD, RDF)
- Visual graph explorer
- Scheduled background learning
- Conflict resolution (contradictory facts)

---

## Questions?

This is an experiment. If something doesn't make sense, it's probably a bug or missing documentation. Open an issue!

Built with curiosity by Rob and Claude.
