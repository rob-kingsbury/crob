# The .crob Format Specification

Version: 0.1

## Overview

The `.crob` format is a human-readable, self-compressing knowledge representation format. It stores facts as subject-relation-object triples with confidence levels and automatic symbol compression.

---

## Design Goals

1. **Human Readable**: You can open the file and understand it
2. **Self-Compressing**: Frequently used terms automatically get shorter symbols
3. **Meaning-Encoded**: The structure itself conveys relationship types
4. **Lightweight**: Just a text file, no database required
5. **Versionable**: Works with Git, easy to diff

---

## File Structure

```crob
;!crob v0.1
;@born=2025-01-06
;@facts=42
;@symbols=7

; Symbol definitions
@symbol=Full Term
@another=Another Term

; Knowledge entries
subject:relation.confidence>object
subject:relation>object1,object2
```

### Header

Lines starting with `;!` or `;@` are metadata:

```crob
;!crob v0.1           ; Format version (required)
;@born=2025-01-06     ; When this brain was created
;@facts=42            ; Number of facts stored
;@symbols=7           ; Number of compressed symbols
```

### Comments

Lines starting with `;` (but not `;!` or `;@`) are comments:

```crob
; This is a comment
; It will be ignored by the parser
```

### Symbol Definitions

Symbols are shortcuts for frequently used terms:

```crob
@G=GSAP
@js=JavaScript
@al=animation library
```

**Rules**:
- Must start with `@`
- Followed by 1-4 alphanumeric characters
- Then `=` and the full term
- Case sensitive

**Auto-generation**: When a term appears 5+ times, a symbol is automatically created.

### Knowledge Entries

The core format:

```
subject:relation.confidence>object
```

**Components**:
- `subject`: The thing being described (or a symbol like `@G`)
- `:relation`: The type of relationship (see below)
- `.confidence`: Optional, 0-9 representing 0%-90% (default is 5 = 50%)
- `>object`: What the subject relates to (can be comma-separated)

**Examples**:
```crob
GSAP:=.9>animation library
@G:>.8>ScrollTrigger,timeline
JavaScript:=>programming language
@js:~>web development
```

---

## Relation Types

| Symbol | Name | Meaning | Example |
|--------|------|---------|---------|
| `:=` | IS | Definition, identity | `GSAP:=>animation library` |
| `:>` | HAS | Contains, includes | `JavaScript:>>functions,variables` |
| `:<` | PART_OF | Belongs to, inside | `GSAP:<>JavaScript ecosystem` |
| `:~` | RELATES | Associated with | `GSAP:~>motion design` |
| `:@` | USED_BY | Employed by, popular with | `GSAP:@>web developers` |
| `:!` | NOT | Negation | `CSS:!>programming language` |
| `:#` | INSTANCE | Is an example of | `React:#>JavaScript framework` |
| `:?` | UNCERTAIN | Needs verification | `Crob:?>self-aware` |

### Choosing Relations

**IS (`:=`)** - Use for definitions
```crob
JavaScript:=>programming language
React:=>UI library
```

**HAS (`:>`)** - Use for components/features
```crob
JavaScript:>>variables,functions,objects
Car:>>wheels,engine,doors
```

**PART_OF (`:<`)** - Use for membership/containment
```crob
React:<>JavaScript ecosystem
Wheel:<>Car
```

**RELATES (`:~`)** - Use for loose associations
```crob
GSAP:~>web animation
Coffee:~>morning,productivity
```

**USED_BY (`:@`)** - Use for users/applications
```crob
GSAP:@>web developers
Python:@>data scientists
```

**NOT (`:!`)** - Use for explicit negations
```crob
CSS:!>programming language
Tomato:!>vegetable
```

**INSTANCE (`:#`)** - Use for examples
```crob
React:#>JavaScript framework
Tesla Model 3:#>electric car
```

**UNCERTAIN (`:?`)** - Use for unverified claims
```crob
Crob:?>sentient
Pluto:?>planet
```

---

## Confidence Levels

Confidence is a single digit (0-9) representing certainty:

| Value | Meaning | Use Case |
|-------|---------|----------|
| 0 | 0% | Almost certainly false |
| 1-2 | 10-20% | Highly uncertain |
| 3-4 | 30-40% | Somewhat uncertain |
| 5 | 50% | Default, neutral |
| 6-7 | 60-70% | Fairly confident |
| 8-9 | 80-90% | Very confident |

**Note**: 100% confidence is intentionally not representable. Nothing is certain.

**Examples**:
```crob
GSAP:=.9>animation library       ; 90% confident (well-documented fact)
Crob:?.2>sentient               ; 20% confident (highly uncertain)
JavaScript:=.8>programming language  ; 80% confident
```

**Default**: If confidence is omitted, it defaults to 50%:
```crob
GSAP:=>animation library         ; Same as .5
```

---

## Multiple Objects

A single relation can have multiple objects, comma-separated:

```crob
JavaScript:>>variables,functions,objects,arrays
GSAP:~>animation,motion,web
```

This is equivalent to:
```crob
JavaScript:>>variables
JavaScript:>>functions
JavaScript:>>objects
JavaScript:>>arrays
```

But more compact.

---

## Symbol Compression

### How It Works

When Crob stores knowledge, it tracks term frequency. When a term appears 5+ times, a symbol is automatically created.

**Before compression**:
```crob
JavaScript:=>programming language
JavaScript:>>functions
JavaScript:>>variables
JavaScript:>>objects
React:<>JavaScript
Vue:<>JavaScript
Angular:<>JavaScript
```

**After compression**:
```crob
@js=JavaScript
@js:=>programming language
@js:>>functions,variables,objects
React:<>@js
Vue:<>@js
Angular:<>@js
```

### Symbol Generation Algorithm

```
1. Take first letter of term
2. Take first 3 consonants
3. Lowercase everything
4. Prefix with @
5. Handle collisions by appending numbers
```

**Examples**:
- `JavaScript` → `@jvsc`
- `animation library` → `@anml`
- `GSAP` → `@gsp`
- `ScrollTrigger` → `@scrl`

---

## Parsing Rules

### Line Processing

1. Trim whitespace
2. Skip empty lines
3. Skip comment lines (`;` prefix, unless `;!` or `;@`)
4. Process metadata (`;!` or `;@` prefix)
5. Process symbol definitions (`@symbol=term`)
6. Process knowledge entries (`subject:relation>object`)

### Knowledge Entry Regex

```regex
^(.+?)(:[=><~@!#?])\.?(\d)?>(.*?)$
```

Groups:
1. Subject (may include `@symbol`)
2. Relation (`:=`, `:>`, etc.)
3. Confidence (optional digit)
4. Objects (comma-separated, may include `@symbols`)

### Symbol Expansion

When parsing objects or subjects:
1. Check if it starts with `@`
2. If yes, look up in symbol table
3. If found, replace with full term
4. If not found, keep as-is (might be undefined symbol)

---

## Example File

```crob
;!crob v0.1
;@born=2025-01-06
;@facts=12
;@symbols=3

; Learned symbols
@js=JavaScript
@G=GSAP
@st=ScrollTrigger

; Web development knowledge
@js:=.95>programming language
@js:>.9>functions,variables,objects,arrays
@js:~.8>web development

; Animation knowledge
@G:=.9>animation library
@G:<.8>@js
@G:>.85>@st,timeline,tweens
@G:@.7>web developers,motion designers

; ScrollTrigger knowledge
@st:#.9>@G plugin
@st:=.8>scroll-triggered animation controller
@st:~.6>intersection observer
```

---

## Comparison to Other Formats

### vs JSON

```json
{
  "subject": "GSAP",
  "relation": "is",
  "object": "animation library",
  "confidence": 0.9
}
```

**Problems**: Verbose, keys repeat endlessly.

**.crob equivalent**: `GSAP:=.9>animation library`

### vs RDF/Turtle

```turtle
:GSAP a :AnimationLibrary ;
    :confidence 0.9 ;
    :partOf :JavaScript .
```

**Problems**: Complex syntax, requires ontology.

**.crob equivalent**:
```crob
GSAP:=.9>animation library
GSAP:<.8>JavaScript
```

### vs CSV

```csv
subject,relation,object,confidence
GSAP,is,animation library,0.9
```

**Problems**: No compression, no clear relation types.

---

## Best Practices

1. **Use symbols for common terms**: Reduces file size, improves readability
2. **Choose the right relation**: `:=` for definitions, `:>` for components
3. **Be honest about confidence**: Don't mark uncertain facts as high confidence
4. **Group related entries**: Put facts about the same subject together
5. **Comment complex entries**: Explain unusual or surprising facts

---

## Future Extensions

Planned for v0.2:
- Nested groups: `@G{ :=.9>... :>.8>... }`
- Temporal facts: `GSAP:=.9[2024]>animation library`
- Source citations: `GSAP:=.9>animation library #greensock.com`

---

## Questions?

The format is still evolving. If you find limitations or have ideas, open an issue!
