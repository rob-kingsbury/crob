# Crob Web Interface Specification

**Date:** 2025-01-08
**Purpose:** Technical specification for Python-based web interface

---

## Technology Stack

### Backend
- **Framework:** FastAPI (async, auto-docs, modern)
- **Server:** uvicorn (ASGI)
- **Background tasks:** APScheduler or Celery
- **Storage:** .crob files + optional SQLite for queries
- **Vector math:** NumPy (for HDV)
- **Web scraping:** aiohttp + BeautifulSoup4
- **NLP:** spaCy (optional, for better fact extraction)

### Frontend
- **Base:** HTML5 + CSS3
- **Styling:** Tailwind CSS (utility-first)
- **Reactivity:** Alpine.js (minimal JS framework, ~15KB)
- **Graphs:** D3.js or Cytoscape.js (knowledge graph viz)
- **Charts:** Chart.js (stats visualization)

### Deployment
- **Local:** `uvicorn main:app --reload`
- **Production:** Docker container + nginx reverse proxy
- **Background daemon:** systemd service or Docker compose

---

## Architecture Overview

```
┌─────────────────────────────────────────────────┐
│                   Browser                       │
│  (HTML/CSS/JS - Tailwind + Alpine.js)         │
└────────────────┬────────────────────────────────┘
                 │ HTTP/JSON
                 ▼
┌─────────────────────────────────────────────────┐
│              FastAPI Server                     │
│  ┌──────────┬──────────┬──────────┬──────────┐ │
│  │  /ask    │ /status  │ /graph   │ /teach   │ │
│  └──────────┴──────────┴──────────┴──────────┘ │
└────────────────┬────────────────────────────────┘
                 │
    ┌────────────┼────────────┐
    ▼            ▼            ▼
┌────────┐  ┌─────────┐  ┌──────────┐
│ Brain  │  │Research │  │Curiosity │
│  .py   │  │  .py    │  │   .py    │
└────┬───┘  └────┬────┘  └────┬─────┘
     │           │            │
     ▼           ▼            ▼
┌─────────────────────────────────────┐
│        File Storage                 │
│  ┌──────────┬───────────┬────────┐ │
│  │crob.crob │crob.crobhd│crob.   │ │
│  │(facts)   │(vectors)  │curiosity│ │
│  └──────────┴───────────┴────────┘ │
└─────────────────────────────────────┘

┌─────────────────────────────────────┐
│      Background Daemon              │
│   (APScheduler - runs every 60s)    │
│                                     │
│  while True:                        │
│    topic = curiosity.next()         │
│    research.investigate(topic)      │
│    brain.learn(facts)               │
│    generate_new_questions()         │
└─────────────────────────────────────┘
```

---

## API Endpoints

### `POST /ask`
**Query Crob with natural language**

Request:
```json
{
  "question": "What is GSAP?"
}
```

Response:
```json
{
  "answer": "GSAP is an animation library for JavaScript",
  "confidence": 0.85,
  "source": "memory",
  "related": ["ScrollTrigger", "Lottie", "CSS animations"],
  "meta": {
    "times_seen": 4,
    "first_learned": "2025-01-07T14:32:00Z",
    "sources": ["greensock.com", "MDN", "CSS-Tricks"]
  }
}
```

If unknown (triggers research):
```json
{
  "answer": "I don't know yet, but I'm researching it now...",
  "confidence": 0.0,
  "source": "researching",
  "status": "learning"
}
```

---

### `GET /status`
**Current system status**

Response:
```json
{
  "knowledge": {
    "total_facts": 1247,
    "subjects": 342,
    "average_confidence": 0.72,
    "last_updated": "2025-01-08T10:23:15Z"
  },
  "curiosity": {
    "queue_size": 23,
    "currently_researching": "ScrollTrigger",
    "completed_today": 8,
    "total_completed": 156
  },
  "uncertainty": {
    "low_confidence_facts": 47,
    "contradictions": 3,
    "unresolved_questions": 12
  },
  "system": {
    "uptime": "2 days 14 hours",
    "daemon_running": true,
    "last_research": "2 minutes ago"
  }
}
```

---

### `GET /graph`
**Knowledge graph data for visualization**

Response:
```json
{
  "nodes": [
    {
      "id": "GSAP",
      "label": "GSAP",
      "type": "subject",
      "confidence": 0.9,
      "centrality": 0.85,
      "facts_count": 12
    },
    {
      "id": "JavaScript",
      "label": "JavaScript",
      "type": "subject",
      "confidence": 0.95,
      "centrality": 0.95,
      "facts_count": 28
    }
  ],
  "edges": [
    {
      "source": "GSAP",
      "target": "JavaScript",
      "relation": "PART_OF",
      "confidence": 0.8,
      "weight": 3
    }
  ]
}
```

---

### `GET /curiosity`
**Current curiosity queue**

Response:
```json
{
  "queue": [
    {
      "topic": "How does GSAP compare to Framer Motion?",
      "origin": "GSAP",
      "reason": "Comparative gap detected",
      "priority": 0.8,
      "status": "queued",
      "depth": 2,
      "added": "2025-01-08T09:15:00Z"
    },
    {
      "topic": "What is Lenis?",
      "origin": "ScrollTrigger",
      "reason": "Mentioned alongside in research",
      "priority": 0.5,
      "status": "queued",
      "depth": 3,
      "added": "2025-01-08T08:42:00Z"
    }
  ],
  "total": 23,
  "next_research_in": "45 seconds"
}
```

---

### `GET /uncertain`
**Low-confidence and contradictory facts**

Response:
```json
{
  "low_confidence": [
    {
      "fact": "ScrollTrigger:?>GSAP plugin",
      "confidence": 0.35,
      "reason": "Only one source, needs verification",
      "last_checked": "2025-01-07T16:20:00Z",
      "sources": ["random blog"]
    }
  ],
  "contradictions": [
    {
      "fact_a": "JavaScript:=>programming language",
      "fact_b": "JavaScript:!>programming language",
      "confidence_a": 0.85,
      "confidence_b": 0.3,
      "sources_a": ["MDN", "Wikipedia", "JS.info"],
      "sources_b": ["old forum post"],
      "status": "unresolved",
      "priority": 0.7
    }
  ]
}
```

---

### `POST /teach`
**Manually teach Crob a fact**

Request:
```json
{
  "subject": "Tailwind CSS",
  "relation": ":=",
  "object": "utility-first CSS framework",
  "confidence": 0.9
}
```

Response:
```json
{
  "status": "learned",
  "fact": "Tailwind CSS:=.9>utility-first CSS framework",
  "new_questions": [
    "What is utility-first CSS?",
    "How does Tailwind CSS compare to Bootstrap?"
  ]
}
```

---

### `POST /resolve`
**Resolve a contradiction manually**

Request:
```json
{
  "contradiction_id": "c12",
  "choose": "fact_a",
  "reason": "More authoritative sources"
}
```

Response:
```json
{
  "status": "resolved",
  "kept": "JavaScript:=>programming language",
  "removed": "JavaScript:!>programming language"
}
```

---

## Frontend Components

### 1. Dashboard (Main View)

```
┌─────────────────────────────────────────────────┐
│  CROB - Curious AI                              │
│  A black box that isn't sure why he knows      │
├─────────────────────────────────────────────────┤
│                                                 │
│  ┌───────────┐  ┌───────────┐  ┌───────────┐  │
│  │   1,247   │  │    23     │  │    47     │  │
│  │   Facts   │  │  Queued   │  │ Uncertain │  │
│  └───────────┘  └───────────┘  └───────────┘  │
│                                                 │
│  Currently researching: ScrollTrigger           │
│  Last learned: 2 minutes ago                    │
│                                                 │
├─────────────────────────────────────────────────┤
│  Ask Crob:                                      │
│  ┌───────────────────────────────────────────┐ │
│  │ What is GSAP?                        [Ask]│ │
│  └───────────────────────────────────────────┘ │
└─────────────────────────────────────────────────┘
```

### 2. Response Panel

```
┌─────────────────────────────────────────────────┐
│  Crob says:                    Confidence: 85%  │
├─────────────────────────────────────────────────┤
│                                                 │
│  GSAP is an animation library for JavaScript.   │
│  I've seen this mentioned across 4 sources.     │
│                                                 │
│  I'm also curious about:                        │
│  • ScrollTrigger (what is it?)                  │
│  • How GSAP compares to CSS animations          │
│                                                 │
│  Sources: greensock.com, MDN, CSS-Tricks, ...   │
│                                                 │
└─────────────────────────────────────────────────┘
```

### 3. Knowledge Graph Visualization

```
┌─────────────────────────────────────────────────┐
│  Knowledge Graph                          [⚙️]  │
├─────────────────────────────────────────────────┤
│                                                 │
│        JavaScript                               │
│           │                                     │
│           ├── GSAP ─── ScrollTrigger            │
│           │     └───── Lottie                   │
│           │                                     │
│           ├── React                             │
│           └── TypeScript                        │
│                                                 │
│  [Node size = centrality]                       │
│  [Edge thickness = confidence]                  │
│  [Color = topic category]                       │
└─────────────────────────────────────────────────┘
```

### 4. Curiosity Queue

```
┌─────────────────────────────────────────────────┐
│  What Crob is Curious About                     │
├─────────────────────────────────────────────────┤
│                                                 │
│  🔍 Currently researching:                      │
│  └─ ScrollTrigger (from GSAP) [████░░] 75%     │
│                                                 │
│  📋 Queued:                                     │
│  1. How does GSAP compare to Framer Motion?     │
│     Origin: GSAP | Priority: ⭐⭐⭐⭐            │
│                                                 │
│  2. What is Lenis?                              │
│     Origin: ScrollTrigger | Priority: ⭐⭐⭐    │
│                                                 │
│  3. CSS Grid vs Flexbox differences             │
│     Origin: CSS | Priority: ⭐⭐               │
│                                                 │
│  [Next research in: 32 seconds]                 │
└─────────────────────────────────────────────────┘
```

### 5. Uncertainty Dashboard

```
┌─────────────────────────────────────────────────┐
│  Uncertain Knowledge                            │
├─────────────────────────────────────────────────┤
│                                                 │
│  ⚠️ Low Confidence (< 50%):                    │
│                                                 │
│  • ScrollTrigger is a GSAP plugin (35%)         │
│    └─ Only 1 source, needs verification         │
│    [Research More] [Verify] [Ignore]            │
│                                                 │
│  🔄 Contradictions:                             │
│                                                 │
│  • JavaScript programming language status       │
│    ├─ IS (85%) - 3 sources                     │
│    └─ NOT (30%) - 1 source                     │
│    [Keep First] [Keep Second] [Research]        │
│                                                 │
└─────────────────────────────────────────────────┘
```

---

## Frontend Implementation

### Alpine.js Component Structure

```html
<div x-data="crobApp()">
  <!-- Dashboard -->
  <div class="stats">
    <div class="stat-card">
      <span class="stat-value" x-text="status.facts"></span>
      <span class="stat-label">Facts Learned</span>
    </div>
  </div>

  <!-- Query -->
  <form @submit.prevent="ask()">
    <input x-model="question" placeholder="Ask Crob...">
    <button type="submit">Ask</button>
  </form>

  <!-- Response -->
  <div x-show="answer" class="response">
    <div class="confidence-bar">
      <div :style="`width: ${confidence * 100}%`"></div>
    </div>
    <p x-text="answer"></p>
  </div>

  <!-- Curiosity Queue -->
  <ul>
    <template x-for="item in curiosityQueue">
      <li>
        <strong x-text="item.topic"></strong>
        <small x-text="`from ${item.origin}`"></small>
      </li>
    </template>
  </ul>
</div>

<script>
function crobApp() {
  return {
    question: '',
    answer: '',
    confidence: 0,
    status: {},
    curiosityQueue: [],

    async ask() {
      const res = await fetch('/ask', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({question: this.question})
      });
      const data = await res.json();
      this.answer = data.answer;
      this.confidence = data.confidence;
      this.loadStatus();
    },

    async loadStatus() {
      const res = await fetch('/status');
      this.status = await res.json();
    },

    async loadCuriosity() {
      const res = await fetch('/curiosity');
      const data = await res.json();
      this.curiosityQueue = data.queue;
    },

    init() {
      this.loadStatus();
      this.loadCuriosity();

      // Auto-refresh every 5 seconds
      setInterval(() => {
        this.loadStatus();
        this.loadCuriosity();
      }, 5000);
    }
  }
}
</script>
```

---

## Backend Implementation Sketch

### `main.py` (FastAPI Server)

```python
from fastapi import FastAPI, BackgroundTasks
from fastapi.staticfiles import StaticFiles
from fastapi.responses import FileResponse
from pydantic import BaseModel
import uvicorn

from brain import Brain
from research import Research
from curiosity import Curiosity

app = FastAPI(title="Crob API", version="0.2")

# Initialize core components
brain = Brain(data_dir="./data")
research = Research(brain)
curiosity = Curiosity(brain)

# Serve frontend
app.mount("/static", StaticFiles(directory="frontend"), name="static")

@app.get("/")
async def root():
    return FileResponse("frontend/index.html")

class Query(BaseModel):
    question: str

@app.post("/ask")
async def ask(query: Query, background_tasks: BackgroundTasks):
    result = brain.query(query.question)

    if result and result['confidence'] > 0.3:
        return {
            "answer": brain.format_answer(result),
            "confidence": result['confidence'],
            "source": "memory",
            "related": brain.related(query.question, depth=1)
        }
    else:
        # Don't know - queue for background research
        background_tasks.add_task(research.investigate, query.question)
        return {
            "answer": "I don't know yet, researching...",
            "confidence": 0.0,
            "source": "researching",
            "status": "learning"
        }

@app.get("/status")
async def status():
    return {
        "knowledge": brain.stats(),
        "curiosity": curiosity.stats(),
        "uncertainty": brain.uncertainty_stats(),
        "system": {
            "daemon_running": daemon.is_alive(),
            "last_research": curiosity.last_research_time()
        }
    }

@app.get("/graph")
async def graph():
    return brain.get_graph_data()

@app.get("/curiosity")
async def get_curiosity():
    return {
        "queue": curiosity.all(),
        "total": curiosity.size(),
        "next_research_in": curiosity.time_until_next()
    }

if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=8000, reload=True)
```

---

### `daemon.py` (Background Learner)

```python
import asyncio
from apscheduler.schedulers.asyncio import AsyncIOScheduler

from brain import Brain
from research import Research
from curiosity import Curiosity

async def learning_cycle():
    """Single research cycle"""
    brain = Brain()
    research = Research(brain)
    curiosity = Curiosity(brain)

    # Get next topic
    topic = curiosity.next()

    if not topic:
        # Generate question from knowledge gaps
        topic = brain.generate_question()

    if topic:
        print(f"🧠 Researching: {topic['topic']}")

        # Research
        results = await research.investigate(topic['topic'])

        # Learn facts
        for fact in results['facts']:
            brain.learn(fact['subject'], fact['relation'], fact['object'])

        # Generate new questions
        new_questions = brain.what_am_i_curious_about_now(
            topic['topic'],
            results
        )

        for q in new_questions:
            curiosity.enqueue(q, origin=topic['topic'])

        # Mark complete
        curiosity.complete(topic['topic'])

        print(f"✓ Learned {len(results['facts'])} facts")
        print(f"✓ Generated {len(new_questions)} new questions")

def start_daemon():
    """Start background learning daemon"""
    scheduler = AsyncIOScheduler()

    # Run every 60 seconds
    scheduler.add_job(learning_cycle, 'interval', seconds=60)

    scheduler.start()
    print("🤖 Crob daemon started - learning every 60 seconds")

    try:
        asyncio.get_event_loop().run_forever()
    except (KeyboardInterrupt, SystemExit):
        scheduler.shutdown()

if __name__ == "__main__":
    start_daemon()
```

---

## Deployment

### Local Development

```bash
# Terminal 1: Start API server
python main.py

# Terminal 2: Start background daemon
python daemon.py

# Browser: http://localhost:8000
```

---

### Docker Deployment

**`Dockerfile`:**
```dockerfile
FROM python:3.11-slim

WORKDIR /app

COPY requirements.txt .
RUN pip install -r requirements.txt

COPY . .

EXPOSE 8000

CMD ["sh", "-c", "python daemon.py & uvicorn main:app --host 0.0.0.0"]
```

**`docker-compose.yml`:**
```yaml
version: '3.8'

services:
  crob:
    build: .
    ports:
      - "8000:8000"
    volumes:
      - ./data:/app/data
    environment:
      - PYTHONUNBUFFERED=1
    restart: unless-stopped
```

**Deploy:**
```bash
docker-compose up -d
```

---

## Testing Strategy

### Unit Tests
```python
# test_brain.py
def test_learn_fact():
    brain = Brain(":memory:")
    brain.learn("GSAP", ":=", "animation library", 0.9)
    result = brain.query("GSAP")
    assert result['confidence'] == 0.9

def test_semantic_recall():
    brain = Brain(":memory:")
    brain.learn("GSAP", ":=", "animation library")
    result = brain.query("animation tools")
    assert "GSAP" in result['alternatives']
```

### Integration Tests
```python
# test_api.py
from fastapi.testclient import TestClient
from main import app

client = TestClient(app)

def test_ask_endpoint():
    response = client.post("/ask", json={"question": "What is GSAP?"})
    assert response.status_code == 200
    assert "confidence" in response.json()
```

---

## Performance Targets

- **Query latency:** < 100ms (cached), < 500ms (research)
- **Dashboard load:** < 200ms
- **Graph render:** < 1s for 1000 nodes
- **Background research:** ~30s per topic
- **Memory usage:** < 500MB for 10K facts

---

## Next Steps

1. Port Brain.php to `brain.py`
2. Build FastAPI skeleton
3. Create basic HTML/Alpine frontend
4. Test query/response flow
5. Add background daemon
6. Implement knowledge graph viz
7. Deploy locally and validate

**Decision point:** After step 4, evaluate if web interface actually makes Crob more useful.
