# Using Crob

## Quick Start

### CLI Mode

```bash
# Interactive mode
php crob.php

# Single question
php crob.php "What is CSS Grid?"

# Check stats
php crob.php --stats

# View research queue
php crob.php --queue

# Learn next topic in queue
php crob.php --learn
```

### Web Interface

1. Start XAMPP (or any PHP server)
2. Navigate to `http://localhost/crob/web/`
3. Ask questions in the chat interface

---

## Talking to Crob

### Asking Questions

Crob understands several question formats:

```
What is GSAP?
What are CSS variables?
Tell me about JavaScript
Explain flexbox
How do I use React?
Why is TypeScript popular?
```

### Teaching Crob

You can directly teach Crob facts:

**CLI**:
```
You: teach me that PHP is a server-side language
Crob: Got it! I learned that PHP is a server-side language...
```

**In interactive mode**:
```
teach GSAP is an animation library
teach me that JavaScript has closures
```

### Commands (Interactive Mode)

| Command | What it does |
|---------|--------------|
| `quit` / `exit` | Exit Crob |
| `stats` | Show knowledge statistics |
| `queue` | Show research queue |
| `learn` | Research next topic in queue |
| `teach X is Y` | Teach Crob a fact |

---

## How Crob Responds

### Reptile Brain Mode

When Crob already knows something, it responds quickly from memory:

```
You: What is JavaScript?
Crob: Reptile brain says: I know this one!
      JavaScript is a programming language used for web development.
```

### Primate Brain Mode

When Crob doesn't know, it researches:

```
You: What is Svelte?
Crob: My knowledge brain is empty on this. Time to learn!
      Primate brain engaged: Let me research that...

      [Researches web, extracts facts, stores in brain]

      Primate brain says: Svelte is a JavaScript framework...

      Ooh, that's interesting... SvelteKit, Vite, reactive
      (added to my research queue)
```

---

## Background Learning

Crob can learn autonomously from its research queue.

### Manual Background Learning

```bash
# Learn one topic
php crob.php --learn

# Learn multiple topics (run in loop)
while true; do php crob.php --learn; sleep 10; done
```

### What Gets Queued

When Crob researches a topic, it discovers related topics ("rabbit holes"):

```
Asked about: GSAP
    → Discovered: ScrollTrigger, Lenis, WAAPI
    → Added to queue with priority and origin
```

### Queue Management

View the queue:
```bash
php crob.php --queue
```

Output:
```
=== Crob's Research Queue ===

1. ScrollTrigger
   From: GSAP | Priority: 0.6
2. Lenis
   From: ScrollTrigger | Priority: 0.4
3. WAAPI
   From: animation | Priority: 0.3
```

---

## Inspecting Crob's Brain

### Stats Command

```bash
php crob.php --stats
```

Output:
```
=== Crob's Nerd Stats ===

Knowledge Brain:
  Facts: 47
  Subjects: 12

Curiosity Queue:
  Pending: 8
  Completed: 15
```

### Reading Raw Files

The brain files are human-readable:

**Knowledge** (`data/crob.crob`):
```crob
;!crob v0.1
;@born=2025-01-06
;@facts=47

@js=JavaScript
@G=GSAP

@js:=.95>programming language
@js:>.9>functions,variables
@G:=.9>animation library
@G:<.8>@js
```

**Language** (`data/crob.voice`):
```json
{
  "patterns": {
    "what_is": [
      "{topic} is {definition}.",
      "Primate brain says: {topic} is {definition}."
    ]
  }
}
```

**Queue** (`data/crob.queue`):
```json
{
  "queue": [
    {"topic": "ScrollTrigger", "origin": "GSAP", "priority": 0.6}
  ],
  "completed": ["GSAP", "JavaScript"]
}
```

---

## API Endpoints

The web interface exposes a JSON API:

| Endpoint | Method | Description |
|----------|--------|-------------|
| `api.php?action=intro` | GET | Get introduction |
| `api.php?action=ask` | POST | Ask a question |
| `api.php?action=stats` | GET | Get statistics |
| `api.php?action=queue` | GET | Get research queue |
| `api.php?action=learn` | POST | Learn next topic |
| `api.php?action=teach` | POST | Teach a fact |
| `api.php?action=knows&topic=X` | GET | Check knowledge |

### Example: Ask via API

```bash
curl -X POST http://localhost/crob/web/api.php?action=ask \
  -H "Content-Type: application/json" \
  -d '{"question": "What is GSAP?"}'
```

---

## Tips & Tricks

### 1. Seed with Teaching

Before letting Crob research, teach it some foundational facts:

```
teach JavaScript is a programming language
teach CSS is for styling
teach HTML is for structure
```

This gives Crob context for its research.

### 2. Check Quality

After Crob learns, verify key facts:

```bash
php crob.php "What is JavaScript?"
```

If it learned something wrong, you can teach the correct fact.

### 3. Domain Focus

Ask questions in your area of interest. Crob will rabbit-hole into related topics:

```
"What is GSAP?" → ScrollTrigger → Lenis → smooth scroll → ...
```

### 4. Batch Learning

Let Crob learn overnight:

```bash
for i in {1..100}; do
  php crob.php --learn
  sleep 30  # Be nice to servers
done
```

### 5. Export & Backup

The brain is just files. Back them up:

```bash
cp data/crob.crob ~/backup/crob-$(date +%Y%m%d).crob
```

---

## Troubleshooting

### "No facts found"

Crob couldn't find useful information. Try:
- Rephrasing the question
- Using more specific terms
- Teaching the basic fact first

### Empty Research Queue

Nothing to learn! Ask more questions to generate rabbit holes.

### Slow Responses

Web research takes time. Each query:
1. Searches DuckDuckGo
2. Fetches up to 5 pages
3. Parses and extracts

Expect 5-15 seconds for unknown topics.

### Rate Limiting

If DuckDuckGo blocks requests:
- Wait a few minutes
- Increase `search_delay_seconds` in config

---

## Examples

### Learning Web Development

```
You: What is React?
Crob: [researches, learns about React, queues Redux, JSX, hooks]

You: learn
Crob: [researches Redux from queue]

You: What is Redux?
Crob: Reptile brain says: I know this one!
      Redux is a state management library...
```

### Building Domain Knowledge

```
# Start with a broad topic
You: What is machine learning?

# Check what got queued
You: queue
Crob: neural networks, supervised learning, TensorFlow...

# Let it explore
You: learn
You: learn
You: learn

# Now ask specific questions
You: What is a neural network?
Crob: [knows from previous learning]
```

---

## Next Steps

- Read `ARCHITECTURE.md` for deep technical details
- Read `FORMAT.md` for .crob specification
- Open issues for bugs or feature requests

Happy learning!
