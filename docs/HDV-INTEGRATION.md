# Hyperdimensional Computing Integration for Crob

**Date:** 2025-01-08
**Purpose:** Technical specification for adding HDV/SDR to Crob's memory system

---

## What Are Hyperdimensional Vectors?

**Hyperdimensional Computing (HDC)** uses very high-dimensional vectors (10,000+ dimensions) to represent concepts.

**Key properties:**
1. **High dimensionality** - 10K bits allows near-orthogonal random vectors
2. **Sparse distributed** - Most bits are 0, ~5% are 1
3. **Noise-tolerant** - Partial/corrupted vectors still recall correctly
4. **Compositional** - Can bind concepts: `DOG + RUNS = "dog runs"`

---

## Why HDV for Crob?

### Current Problem: String Matching is Brittle

```python
# Current query in Brain.php
query("GSAP")           # ✓ Exact match
query("gsap")           # ✓ Case-insensitive
query("GreenSock")      # ✗ Miss (synonym)
query("animation tools") # ✗ Miss (concept)
```

### HDV Solution: Semantic Similarity

```python
# With HDV
query("GSAP")           # ✓ Exact match (1.0)
query("GreenSock")      # ✓ Semantic match (0.89)
query("animation tools") # ✓ Concept match (0.82)
query("motion library")  # ✓ Synonym match (0.78)
```

**How:** Vectors for semantically similar concepts have high overlap (low Hamming distance)

---

## Architecture Overview

### Dual Storage System

```
Human-readable storage:     Binary vector index:
┌─────────────┐            ┌──────────────┐
│ crob.crob   │            │ crob.crobhd  │
│             │            │              │
│ GSAP:=.9>   │ ←────────→ │ 1001...0101  │
│ animation   │            │ 0110...1001  │
│ library     │            │ ...          │
└─────────────┘            └──────────────┘
     ↓                           ↓
Human-readable              Machine-queryable
Auditable                   Semantic search
Source of truth             Performance layer
```

### Why Both?

- `.crob` = authoritative, transparent, versionable
- `.crobhd` = fast, associative, semantic retrieval
- Can regenerate `.crobhd` from `.crob` anytime

---

## Technical Implementation

### 1. Vector Representation

**Dimensions:** 10,000 bits (1,250 bytes)

**Encoding:**
- Random seed for each unique term
- ~500 bits set to 1 (~5% sparsity)
- Deterministic (same term → same vector always)

```python
import random
import numpy as np

DIMENSIONS = 10000
SPARSITY = 0.05  # 5% of bits are 1

def encode_term(term: str) -> np.ndarray:
    """Generate deterministic hypervector for term"""
    # Use term as seed for reproducibility
    seed = hash(term) % (2**32)
    random.seed(seed)

    # Create sparse vector
    vector = np.zeros(DIMENSIONS, dtype=bool)
    num_ones = int(DIMENSIONS * SPARSITY)
    indices = random.sample(range(DIMENSIONS), num_ones)
    vector[indices] = 1

    return vector
```

---

### 2. Compositional Binding

**Binding operation:** XOR (exclusive or)

**Creates unique patterns:**
```python
def bind(*vectors):
    """Combine vectors to represent compound concepts"""
    result = vectors[0]
    for v in vectors[1:]:
        result = np.logical_xor(result, v)
    return result

# Example:
GSAP = encode_term("GSAP")
IS = encode_term(":=")
ANIMATION_LIB = encode_term("animation library")

# Bind: "GSAP IS animation library"
fact_vector = bind(GSAP, IS, ANIMATION_LIB)
```

**Property:** Binding is reversible
```python
# Unbind: Given "GSAP IS X", what is X?
X = bind(fact_vector, GSAP, IS)
# X ≈ ANIMATION_LIB (recoverable via similarity search)
```

---

### 3. Similarity Measurement

**Hamming distance:** Count differing bits

```python
def similarity(v1, v2):
    """Measure similarity (0-1, higher = more similar)"""
    hamming_dist = np.sum(v1 != v2)
    return 1 - (hamming_dist / DIMENSIONS)

# Example:
sim = similarity(encode_term("GSAP"), encode_term("GreenSock"))
# Result: ~0.05 (random - they're unrelated in vector space)

# But after learning they're related:
# We'd store them with shared context, increasing similarity
```

---

### 4. Storage Format

**File: `crob.crobhd`**

```
Header (16 bytes):
- Magic number: "CROBHDV1" (8 bytes)
- Dimensions: uint32 (4 bytes)
- Count: uint32 (4 bytes)

Symbol Table (variable):
- For each symbol:
  - ID: uint32
  - Name length: uint16
  - Name: UTF-8 string
  - Vector: DIMENSIONS bits packed

Fact Vectors (variable):
- For each fact:
  - Subject ID: uint32
  - Relation ID: uint32
  - Object ID: uint32
  - Confidence: uint8 (0-255)
  - Bound vector: DIMENSIONS bits packed
```

**Binary packing:**
```python
def pack_vector(vector: np.ndarray) -> bytes:
    """Pack boolean array to bytes"""
    return np.packbits(vector).tobytes()

def unpack_vector(data: bytes) -> np.ndarray:
    """Unpack bytes to boolean array"""
    return np.unpackbits(np.frombuffer(data, dtype=np.uint8))[:DIMENSIONS]
```

---

## Integration with Crob

### Modified Brain Architecture

```python
class Brain:
    def __init__(self, data_dir):
        self.crob_file = f"{data_dir}/crob.crob"
        self.hdv_file = f"{data_dir}/crob.crobhd"

        self.knowledge = {}      # Text-based storage
        self.vectors = {}        # HDV index
        self.symbol_vectors = {} # Term → vector mapping

        self.load_crob()
        self.load_hdv()

    def learn(self, subject, relation, obj, confidence=0.5):
        # 1. Store in .crob (human-readable)
        self._store_crob(subject, relation, obj, confidence)

        # 2. Generate/update HDV vectors
        subj_vec = self._get_or_create_vector(subject)
        rel_vec = self._get_or_create_vector(relation)
        obj_vec = self._get_or_create_vector(obj)

        # 3. Bind into fact vector
        fact_vec = bind(subj_vec, rel_vec, obj_vec)

        # 4. Store in .crobhd
        self._store_hdv(subject, relation, obj, fact_vec, confidence)

        # 5. Update semantic clusters (optional)
        self._update_clusters(subject, obj)
```

---

### Query Strategy (Hybrid)

```python
def query(self, query_text: str, threshold=0.7):
    """Hybrid query: exact match → HDV → fuzzy"""

    # 1. Try exact match (fastest)
    if query_text in self.knowledge:
        return {
            'result': self.knowledge[query_text],
            'match_type': 'exact',
            'confidence': 1.0
        }

    # 2. Try HDV semantic match
    query_vec = self._encode_query(query_text)
    similarities = []

    for term, vec in self.vectors.items():
        sim = similarity(query_vec, vec)
        if sim > threshold:
            similarities.append((term, sim))

    if similarities:
        similarities.sort(key=lambda x: x[1], reverse=True)
        best_match, sim_score = similarities[0]

        return {
            'result': self.knowledge[best_match],
            'match_type': 'semantic',
            'confidence': sim_score,
            'alternatives': similarities[1:5]  # Top 5 matches
        }

    # 3. Fall back to substring search
    # ... existing code ...
```

---

## Semantic Learning

### Context-Sensitive Encoding

When Crob learns relationships, update vectors to be more similar:

```python
def learn_semantic_relationship(self, term_a, term_b, strength=0.1):
    """Make two terms more semantically similar"""
    vec_a = self.vectors[term_a]
    vec_b = self.vectors[term_b]

    # Flip some bits to increase overlap
    diff_bits = np.where(vec_a != vec_b)[0]
    num_to_flip = int(len(diff_bits) * strength)
    flip_indices = random.sample(list(diff_bits), num_to_flip)

    # Make them more similar
    vec_a[flip_indices] = vec_b[flip_indices]

    self.vectors[term_a] = vec_a
```

**Usage:**
```python
# When learning "GSAP is an animation library"
brain.learn("GSAP", ":=", "animation library", 0.9)

# Also update semantic space
brain.learn_semantic_relationship("GSAP", "animation library", strength=0.2)
brain.learn_semantic_relationship("GSAP", "JavaScript", strength=0.1)
```

**Result:** Future queries for "animation" will recall "GSAP" even without exact match

---

## Memory Dynamics with HDV

### "Forgetting" via Bit Flipping

Instead of deleting facts, flip random bits to degrade recall:

```python
def degrade_memory(self, term, degradation=0.05):
    """Simulate memory decay by flipping bits"""
    vec = self.vectors[term]

    # Flip random bits
    num_to_flip = int(DIMENSIONS * degradation)
    flip_indices = random.sample(range(DIMENSIONS), num_to_flip)
    vec[flip_indices] = ~vec[flip_indices]

    self.vectors[term] = vec
```

**Effect:** Degraded vectors become less similar to related concepts, harder to recall

---

### Reinforcement via Bit Alignment

When re-encountering facts, align vectors more closely:

```python
def reinforce_memory(self, term_a, term_b):
    """Strengthen association by increasing vector similarity"""
    self.learn_semantic_relationship(term_a, term_b, strength=0.05)
```

**Cognitive realism:** Frequently co-occurring concepts become easier to recall together

---

## Confidence Integration

### Weighted Similarity

Incorporate confidence scores into retrieval:

```python
def weighted_similarity(self, query_vec, fact):
    """Similarity adjusted by confidence"""
    vec_sim = similarity(query_vec, fact['vector'])
    confidence = fact['confidence']

    # Lower confidence = require higher similarity to retrieve
    threshold_adjustment = 1.0 - (confidence * 0.3)

    return vec_sim - threshold_adjustment
```

**Effect:** Low-confidence facts require higher semantic match to be retrieved

---

## Advanced Features

### 1. Analogical Reasoning

**Pattern:** "X is to Y as A is to ?"

```python
def analogy(self, x, y, a):
    """Find B such that X:Y :: A:B"""
    vec_x = self.vectors[x]
    vec_y = self.vectors[y]
    vec_a = self.vectors[a]

    # Compute relationship: Y - X
    relationship = bind(vec_y, vec_x)

    # Apply to A: B = A + relationship
    vec_b = bind(vec_a, relationship)

    # Find closest match
    return self._find_nearest(vec_b)

# Example:
analogy("GSAP", "JavaScript", "Lottie")
# → "JSON" (Lottie:JSON :: GSAP:JavaScript)
```

---

### 2. Concept Clustering

Group related terms automatically:

```python
def find_clusters(self, min_similarity=0.6):
    """Identify semantic clusters in knowledge"""
    clusters = []

    for term_a, vec_a in self.vectors.items():
        cluster = [term_a]

        for term_b, vec_b in self.vectors.items():
            if term_a != term_b:
                if similarity(vec_a, vec_b) > min_similarity:
                    cluster.append(term_b)

        if len(cluster) > 1:
            clusters.append(cluster)

    return clusters

# Example result:
# [
#   ['GSAP', 'ScrollTrigger', 'Lottie', 'animation'],
#   ['JavaScript', 'TypeScript', 'programming'],
# ]
```

---

### 3. Gap Detection

Find missing relationships in knowledge graph:

```python
def find_knowledge_gaps(self):
    """Identify concepts that should be related but aren't"""
    gaps = []

    for term_a, vec_a in self.vectors.items():
        # Find terms with medium similarity (0.4-0.6)
        # These might be related but connection is unclear
        for term_b, vec_b in self.vectors.items():
            sim = similarity(vec_a, vec_b)
            if 0.4 < sim < 0.6:
                if not self.has_direct_relation(term_a, term_b):
                    gaps.append({
                        'term_a': term_a,
                        'term_b': term_b,
                        'similarity': sim,
                        'question': f"How does {term_a} relate to {term_b}?"
                    })

    return sorted(gaps, key=lambda x: x['similarity'], reverse=True)
```

**Usage:** Feed gaps to curiosity queue for autonomous research

---

## Performance Considerations

### Vector Operations

**Fast operations:**
- XOR binding: O(n) where n = dimensions
- Hamming distance: O(n)
- Single query: ~1ms for 1000 vectors

**Slow operations:**
- Full similarity search: O(n²) for all pairs
- Clustering: O(n²)

### Optimization Strategies

**1. Locality-Sensitive Hashing (LSH)**
```python
# Pre-compute hash buckets for fast approximate search
# Only compare within same bucket
```

**2. Caching**
```python
# Cache frequently queried vectors
# Cache recent similarity computations
```

**3. Incremental Updates**
```python
# Only recompute changed vectors
# Don't rebuild entire index on each learn()
```

---

## Migration Path

### Phase 1: Basic HDV (Week 1)
- Implement vector encoding
- Build `.crobhd` binary format
- Test similarity search vs string matching

### Phase 2: Semantic Learning (Week 2)
- Context-sensitive vector updates
- Reinforcement/degradation mechanics
- Confidence-weighted retrieval

### Phase 3: Advanced Features (Week 3)
- Analogical reasoning
- Automatic clustering
- Gap detection for curiosity

### Phase 4: Optimization (Week 4)
- LSH for fast approximate search
- Caching layer
- Benchmark at scale (10K+ facts)

---

## Validation Tests

### Test 1: Synonym Recall
```python
brain.learn("GSAP", ":=", "animation library")
brain.learn("GreenSock", ":=", "animation library")

# Should now be semantically similar
assert similarity(brain.vectors["GSAP"],
                  brain.vectors["GreenSock"]) > 0.7
```

### Test 2: Concept Composition
```python
result = brain.query("JavaScript animation tools")

# Should recall GSAP even though exact phrase wasn't stored
assert "GSAP" in result['alternatives']
```

### Test 3: Degradation
```python
brain.learn("Obscure Framework", ":=", "outdated tool")
brain.degrade_memory("Obscure Framework", degradation=0.3)

# Should be harder to recall
result = brain.query("outdated")
assert "Obscure Framework" not in result or result['confidence'] < 0.4
```

---

## Open Research Questions

1. **Optimal dimensionality?** - 10K bits standard, but could 5K work?
2. **Sparsity ratio?** - 5% is typical, but affects collision rate
3. **Binding operation?** - XOR standard, but circular convolution alternative?
4. **Confidence encoding?** - Should confidence affect vector representation directly?
5. **Decay strategy?** - Random bit flips? Systematic degradation? None?

---

## References

- Kanerva, P. (2009). "Hyperdimensional Computing"
- Rachkovskij, D. (2001). "Representation and Processing of Structures with Binary Sparse Distributed Codes"
- Kleyko et al. (2021). "Vector Symbolic Architectures as a Computing Framework"

---

## Next Steps

1. Implement basic vector encoding in Python
2. Build similarity search prototype
3. Test on real .crob knowledge base
4. Compare retrieval quality vs string matching
5. Decide if cognitive benefits justify complexity

**Decision point:** If HDV retrieval isn't meaningfully better than string + NLP, abandon it.
