# Crob Evolution: From Child Brain to Adult Cognition

**Date:** 2025-01-08
**Context:** Discussion with Claude on evolving Crob from prototype to useful tool

---

## The Core Problem

Crob v0.1 is a **child brain**:
- Accumulates everything, forgets nothing
- No contradictions or conflicts
- No self-awareness of uncertainty
- Learns only when asked
- String-matching recall (primitive)

**The question:** How do we evolve it into something actually useful while maintaining cognitive fidelity?

---

## Key Philosophical Positions

### What We Don't Want

1. **No traditional decay** - Don't want facts to disappear
2. **No machine learning black box** - Transparency is core
3. **No forced ontologies** - Keep the flexibility that makes .crob interesting
4. **Not competing with LLMs** - Different architecture, different purpose

### What We Do Want

1. **Emergent uncertainty** - Less confident about unused knowledge
2. **Autonomous curiosity** - Learns without being prompted
3. **Cognitive realism** - Struggles, contradicts itself, questions
4. **Transparent reasoning** - Can audit why it knows what it knows
5. **Useful specialization** - Becomes expert in YOUR domains over time

---

## The Black Box That Isn't Sure Why

**Goal:** Crob should have facts but not understand its own reasoning.

**Implementation:**
- Store facts with confidence scores
- Track provenance (where did I learn this?)
- Flag contradictions explicitly
- Generate questions from knowledge gaps
- Admit uncertainty in responses

**Example response:**
```
I'm fairly confident (0.85) that GSAP is an animation library
for JavaScript. I've seen this mentioned across 4 different sources.

I'm less sure (0.4) about whether it's better than CSS animations—
I've only encountered that claim once, and I'm still researching it.

I'm currently curious about:
- How GSAP compares to Framer Motion
- What ScrollTrigger actually does
```

---

## The Four Mechanisms of Adult Cognition

### 1. Confidence Dynamics (Not Decay)

**Problem:** All facts are eternal and equal weight

**Solution:** Confidence adjusts based on reinforcement
- Single source = 0.4-0.6 confidence
- Multiple sources = boost confidence
- Contradictory sources = flag as uncertain
- Re-encountering fact = confidence increase
- Never reaches 1.0 (nothing is certain)

**Implementation:**
```python
def learn(subject, relation, object, initial_confidence=0.5):
    if fact_exists:
        # Reinforcement
        existing['confidence'] = min(0.95, existing['confidence'] + 0.15)
        existing['times_seen'] += 1
    else:
        # New fact
        store(subject, relation, object, confidence=initial_confidence)
```

---

### 2. Contradiction Detection

**Problem:** Can hold conflicting beliefs without noticing

**Solution:** Flag conflicts, create resolution queue

**New file: `crob.doubts`**
```json
{
  "contradictions": [
    {
      "fact_a": "JavaScript:=>programming language",
      "fact_b": "JavaScript:!>programming language",
      "sources": ["MDN", "random blog"],
      "confidence_a": 0.85,
      "confidence_b": 0.3,
      "status": "unresolved",
      "priority": 0.7
    }
  ]
}
```

**Behavior:** Primate brain researches conflicts to resolve them

---

### 3. Working Memory Limits

**Problem:** Unlimited retrieval is cognitively unrealistic

**Solution:** 7-item active memory cache
- Frequently accessed facts stay "hot"
- Less-used facts require slower retrieval
- Simulates attention bottlenecks

**Architecture:**
```
Query → Check working memory (fast)
         ↓ miss
      → HDV semantic search (medium)
         ↓ miss
      → Full .crob scan (slow)
```

---

### 4. Autonomous Question Generation

**Problem:** Only learns when prompted

**Solution:** Generate questions from knowledge structure

**Strategies:**
1. **Orphaned references** - "I know ABOUT X but not WHAT X is"
2. **Low confidence** - "I'm not sure about this, need more sources"
3. **Relationship gaps** - "I know A and B but not how they relate"
4. **Analogical gaps** - "I know X is like Y, but how are they different?"

**Example:**
```python
def generate_question():
    # Find referenced but undefined topics
    for subject, relations in knowledge:
        for obj in relations.objects:
            if not knows(obj):
                return f"What is {obj}?"

    # Find low-confidence facts
    for fact in all_facts:
        if fact.confidence < 0.5:
            return f"Tell me more about {fact.subject}"
```

---

## Hyperdimensional Storage Integration

### The Problem with String Matching

Current query system:
- Exact match only
- Case-insensitive fallback
- Substring search

**Fails on:**
- Synonyms ("motion" ≠ "animation")
- Concepts ("tools for web" doesn't match "JavaScript libraries")
- Analogies ("like GSAP but for React")

---

### HDV/SDR Solution

**Hyperdimensional vectors** enable:
1. **Semantic similarity** - "animation tools" recalls GSAP
2. **Compositional binding** - Context-sensitive memory
3. **Graceful degradation** - Partial queries still work
4. **Natural "forgetting"** - Bit-flip vectors to fade memories

---

### Novel Architecture

Keep `.crob` human-readable, add binary `.crobhd` index:

```
data/
├── crob.crob          # Source of truth (human-readable)
├── crob.crobhd        # Hypervector index (binary)
└── crob.symbols       # Term → vector mappings
```

**Workflow:**
1. Parse `.crob` fact: `GSAP:=.9>animation library`
2. Generate 10K-bit hypervectors:
   - `HDV(GSAP)`
   - `HDV(animation library)`
   - `HDV(GSAP IS animation library)` = bind(GSAP, :=, animation library)
3. Store text in `.crob`, vector in `.crobhd`

**Query:**
1. User: "animation tools"
2. Generate `HDV(animation) + HDV(tools)`
3. Compare against stored vectors (Hamming distance)
4. Return semantically similar results
5. Fall back to exact match if needed

**Why this is novel:**
Combining **symbolic reasoning** (.crob text) with **subsymbolic memory** (HDV vectors) mirrors human dual-process cognition.

---

## The Autonomous Learning Loop

### Current Behavior
- User asks question
- Crob searches memory
- If missing, scrapes web
- Answers and stops

### Target Behavior
- **Background daemon runs continuously**
- Pulls from curiosity queue
- Researches topics autonomously
- Learns facts and patterns
- Generates NEW questions from discoveries
- Repeats indefinitely

---

### Daemon Architecture

```python
async def learning_loop():
    while True:
        # 1. Pick next curious topic
        topic = curiosity.next_thread()

        if not topic:
            # Generate question from knowledge gaps
            topic = brain.generate_question()

        # 2. Research it
        results = research.investigate(topic)

        # 3. Learn facts
        for fact in results['facts']:
            brain.learn(topic, fact)

        # 4. Generate follow-up questions
        new_questions = brain.what_am_i_curious_about_now(topic, results)

        for q in new_questions:
            curiosity.enqueue(q, origin=topic)

        # 5. Check for contradictions
        if brain.has_conflict(topic):
            doubts.flag_contradiction(topic)

        await asyncio.sleep(60)  # Research every minute
```

---

### Question Generation Strategies

**1. Orphaned References**
```python
# I know GSAP uses ScrollTrigger, but what is ScrollTrigger?
for relation in knowledge[subject]:
    for obj in relation.objects:
        if not knows(obj):
            enqueue("What is {obj}?")
```

**2. Definitional Gaps**
```python
# I know GSAP HAS features, but what IS it?
if has_relation(topic, REL_HAS) and not has_relation(topic, REL_IS):
    enqueue("What is {topic}?")
```

**3. Comparative Gaps**
```python
# I know GSAP and Framer Motion are similar, how are they different?
similar = find_related(topic, depth=1)
if similar:
    enqueue("How is {topic} different from {similar[0]}?")
```

**4. Low-Confidence Reinforcement**
```python
# I'm not sure about this, need more sources
for fact in all_facts:
    if fact.confidence < 0.5:
        enqueue("Tell me more about {fact.subject}")
```

---

## Web Interface Architecture

### Technology Stack

**Backend:**
- Python 3.11+
- FastAPI (async API framework)
- uvicorn (ASGI server)
- APScheduler (background daemon)

**Frontend:**
- HTML5 + Tailwind CSS
- Alpine.js (minimal reactive framework)
- D3.js or Cytoscape.js (knowledge graph viz)

**Storage:**
- `.crob` files (human-readable facts)
- `.crobhd` binary (hypervector index)
- SQLite (query optimization, optional)

---

### API Endpoints

```python
POST   /ask              # Query Crob
GET    /status           # Current learning status
GET    /graph            # Knowledge graph data
GET    /curiosity        # What Crob is wondering about
GET    /uncertain        # Low-confidence facts
POST   /teach            # Manually add facts
GET    /contradictions   # Conflicting beliefs
```

---

### UI Components

**Dashboard:**
- Facts learned count
- Curiosity queue size
- Uncertain facts count
- Currently researching topic

**Query Interface:**
- Natural language input
- Response with confidence score
- "Learning..." indicator for new topics
- Related topics suggestions

**Knowledge Graph:**
- Interactive visualization
- Nodes = concepts
- Edges = relationships
- Color = confidence level
- Size = centrality (connectivity)

**Curiosity Queue:**
- List of pending research topics
- Origin (where question came from)
- Priority score
- Status (queued/researching/completed)

**Uncertainty Dashboard:**
- Low-confidence facts
- Contradictions flagged
- Manual verification interface

---

## Learning Speed & Quality

### Current Bottlenecks

1. **Web scraping** - 2-5 seconds per search
2. **Sequential fetching** - 5 URLs @ 1-3 seconds each
3. **Fact extraction** - Primitive regex (~40% accuracy)
4. **No strategic prioritization** - Random depth-first exploration

### Realistic Learning Rate (Unoptimized)

**24-hour test:**
- 100-200 facts across 15-25 topics
- Knowledge graph 3-4 levels deep
- Significant noise and low-confidence junk

### Optimized Learning Rate

**With improvements:**
- Parallel scraping (5x faster)
- Better NLP fact extraction (spaCy, not regex)
- Strategic question prioritization
- Confidence thresholding (ignore < 0.3)

**24-hour test (optimized):**
- 200-300 vetted facts across 20-30 topics
- Knowledge graph 4-5 levels deep
- Mostly relevant, confidence-weighted

**Week 1:**
- 1,000-2,000 facts
- Competent in seeded domain (e.g., web animation)
- Actively identifies and researches gaps

---

## Technology Migration Path

### Why Not PHP?

**Problems:**
1. No native async/await (concurrency sucks)
2. Weak scientific/ML ecosystem (no HDV libraries)
3. Long-running daemons are awkward
4. No good interactive tooling (REPL, notebooks)

### Python Advantages

1. **Asyncio** - concurrent web scraping
2. **NumPy** - vector math for HDV
3. **Rich ecosystem** - spaCy (NLP), networkx (graphs), FastAPI
4. **Better daemon support** - designed for long-running processes
5. **Jupyter notebooks** - interactive experimentation

### Alternative: Rust

**Pros:**
- Blazing fast
- Excellent concurrency (Tokio)
- Type safety prevents bugs
- Can handle massive graphs

**Cons:**
- Steeper learning curve
- Longer development time
- Smaller NLP/ML ecosystem

**Verdict:** Python for prototyping, Rust if performance becomes critical

---

## Potential Use Cases

### Where Crob Could Be Useful

**1. Personal Research Assistant**
- Track evolving domains (JS frameworks, design tools)
- Autonomous background learning in your field
- Personalized knowledge graph over time

**2. Transparent Knowledge Systems**
- Legal research (auditable reasoning)
- Medical information (explicit uncertainty)
- Compliance (where "I don't know" is a feature)

**3. Educational Tool**
- Teaching epistemology (knowledge/belief/uncertainty)
- Cognitive science demonstrations
- AI safety (transparent reasoning systems)

### What Makes It Different

**Not:** Another chatbot, another vector DB, another RAG system

**Is:** A cognitively faithful knowledge system that:
- Admits uncertainty explicitly
- Shows its reasoning process
- Learns autonomously in your domains
- Contradicts itself and notices
- Becomes expert in what YOU care about

---

## Open Questions

### Technical

1. **HDV dimension size?** - 10K bits? 5K? (affects collision rate)
2. **Confidence decay formula?** - Linear? Exponential? None?
3. **Working memory eviction?** - LRU? Confidence-weighted?
4. **Contradiction resolution?** - Trust higher confidence? Trust recency? Ask user?

### Philosophical

1. **Is autonomous curiosity actually useful?** - Or does it just generate noise?
2. **Can symbol+subsymbol hybrid work?** - Or will one dominate?
3. **Is uncertainty more valuable than accuracy?** - For certain domains, yes?

### Strategic

1. **Pet project or real tool?** - Determines effort investment
2. **Target domain?** - General knowledge? Specific field?
3. **Solo experiment or open-source?** - Community involvement?

---

## Next Steps (When Ready)

### Phase 0: Validate Core Idea (1 week)
- Port Brain.php to Python (~200 lines)
- Seed with "GSAP" topic
- Let daemon run 24-48 hours
- **Decision point:** Is the knowledge graph coherent?

### Phase 1: Core Backend (2 weeks)
- Port all PHP classes to Python
- Implement confidence dynamics
- Build autonomous learning daemon
- Add basic CLI interface

### Phase 2: HDV Integration (1-2 weeks)
- Implement hyperdimensional vectors
- Build semantic search layer
- Test retrieval quality vs string matching

### Phase 3: Web Interface (2 weeks)
- FastAPI backend
- Basic HTML/JS frontend
- Dashboard with status/query/graph
- Background daemon integration

### Phase 4: Polish (ongoing)
- Contradiction detection
- Uncertainty tracking
- Knowledge graph visualization
- User feedback loop

---

## Success Criteria

**Prototype succeeds if:**
1. Autonomous learning produces coherent knowledge graphs (not noise)
2. HDV semantic retrieval works better than string matching
3. Confidence scores meaningfully reflect uncertainty
4. YOU would actually use it for your own research

**If it fails:**
- Core insight (transparent + uncertain + curious) is still valuable
- Learnings apply to next project
- Document what didn't work and why

---

## Philosophy

Crob is not trying to be:
- Smarter than GPT
- Faster than vector DBs
- More accurate than Wikipedia

Crob is trying to be:
- **Honest** about what it doesn't know
- **Transparent** in its reasoning
- **Curious** about your domains
- **Cognitively realistic** (struggles, contradicts, questions)

Most AI optimizes for accuracy.
Crob optimizes for **epistemic humility**.

That's rare and valuable.

---

## References

- ChatGPT feedback: `chatgt-thoughts.txt`
- Current architecture: `ARCHITECTURE.md`
- Format spec: `FORMAT.md`
- Original implementation: `src/*.php`
