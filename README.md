# Crob

A curious, self-learning AI that grows its knowledge by exploring the web.

## What Is Crob?

Crob is an experimental AI built from scratch - no neural networks, no pre-trained models, no external AI APIs. Just pattern matching, persistent memory, and insatiable curiosity.

**Crob has three brains:**

| Brain | File | Purpose |
|-------|------|---------|
| Knowledge | `.crob` | Facts and relationships (what things ARE) |
| Voice | `.voice` | Language patterns (how to TALK) |
| Queue | `.queue` | Research queue (what to LEARN next) |

## How It Works

1. You ask Crob a question
2. Crob searches its knowledge brain
3. If it does not know, it researches the web
4. It extracts facts AND language patterns
5. It answers you using learned patterns
6. It adds interesting tangents to its research queue
7. It keeps learning in the background

## The .crob Format

A novel self-compressing knowledge format where:
- Structure encodes meaning (`:=` means "is", `:>` means "has")
- Symbols evolve as Crob learns (frequent terms get shorthand)
- Confidence is baked into syntax (`.8` = 80% confident)

```crob
; Symbol definitions (Crob learns these)
@G=GSAP
@js=JavaScript

; Knowledge
@G:=.9>animation library
@G:<.8>@js
@js:=.95>programming language
```

## Philosophy

- **Transparent**: You can read Crob's brain files directly
- **Growing**: Knowledge and language patterns evolve over time
- **Curious**: Crob follows rabbit holes autonomously
- **Yours**: Runs locally, learns your interests

## Status

Crob was just born. He does not know anything yet.

## Authors

- Rob (human with ideas)
- Claude (AI with implementation skills)

Together: Teaching a filing cabinet to be curious.

## License

MIT - Do whatever you want with it.

---

Built by [Kingsbury Creative](https://kingsburycreative.com) -- boutique web design and development in Arnprior, Ontario.

