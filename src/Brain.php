<?php
/**
 * Crob's Knowledge Brain
 *
 * Stores facts and relationships in the .crob format.
 * Self-compressing: frequently used terms become symbols.
 */

class Brain
{
    private string $file;
    private array $symbols = [];      // @G => GSAP
    private array $knowledge = [];    // subject => [relations]
    private array $frequency = [];    // term => usage count

    const SYMBOL_THRESHOLD = 5;       // Uses before creating symbol

    // Relation types
    const REL_IS = ':=';              // definition
    const REL_HAS = ':>';             // contains
    const REL_PART_OF = ':<';         // belongs to
    const REL_RELATES = ':~';         // associated with
    const REL_USED_BY = ':@';         // used by
    const REL_NOT = ':!';             // is not
    const REL_INSTANCE = ':#';        // instance of
    const REL_UNCERTAIN = ':?';       // needs verification

    public function __construct(string $dataDir = null)
    {
        $dataDir = $dataDir ?? __DIR__ . '/../data';
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        $this->file = $dataDir . '/crob.crob';
        $this->load();
    }

    /**
     * Load brain from .crob file
     */
    public function load(): void
    {
        if (!file_exists($this->file)) {
            return;
        }

        $lines = file($this->file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip comments
            if (empty($line) || $line[0] === ';') {
                continue;
            }

            // Symbol definition: @G=GSAP
            if (preg_match('/^(@\w+)=(.+)$/', $line, $m)) {
                $this->symbols[$m[1]] = $m[2];
                continue;
            }

            // Knowledge: subject:relation.confidence>object,object2
            if (preg_match('/^(.+?)(:[=><~@!#?])\.?(\d)?>(.*?)$/', $line, $m)) {
                $subject = $this->expand($m[1]);
                $relation = $m[2];
                $confidence = isset($m[3]) && $m[3] !== '' ? (int)$m[3] / 10 : 0.5;
                $objects = array_map([$this, 'expand'], explode(',', $m[4]));

                if (!isset($this->knowledge[$subject])) {
                    $this->knowledge[$subject] = [];
                }

                $this->knowledge[$subject][] = [
                    'rel' => $relation,
                    'obj' => $objects,
                    'conf' => $confidence,
                ];
            }
        }
    }

    /**
     * Save brain to .crob file
     */
    public function save(): void
    {
        $lines = [];

        // Header
        $lines[] = ';!crob v0.1';
        $lines[] = ';@born=' . date('Y-m-d');
        $lines[] = ';@facts=' . $this->countFacts();
        $lines[] = ';@symbols=' . count($this->symbols);
        $lines[] = '';

        // Symbols
        if (!empty($this->symbols)) {
            $lines[] = '; Symbols';
            foreach ($this->symbols as $sym => $term) {
                $lines[] = "$sym=$term";
            }
            $lines[] = '';
        }

        // Knowledge
        $lines[] = '; Knowledge';
        foreach ($this->knowledge as $subject => $relations) {
            $subjectSym = $this->compress($subject);
            foreach ($relations as $rel) {
                $objects = array_map([$this, 'compress'], $rel['obj']);
                $conf = $rel['conf'] != 0.5 ? '.' . (int)($rel['conf'] * 10) : '';
                $lines[] = $subjectSym . $rel['rel'] . $conf . '>' . implode(',', $objects);
            }
        }

        file_put_contents($this->file, implode("\n", $lines));
    }

    /**
     * Learn a new fact
     */
    public function learn(string $subject, string $relation, array|string $objects, float $confidence = 0.5): void
    {
        $objects = (array)$objects;
        $subject = trim($subject);

        if (!isset($this->knowledge[$subject])) {
            $this->knowledge[$subject] = [];
        }

        // Track frequency for symbol creation
        $this->trackFrequency($subject);
        foreach ($objects as $obj) {
            $this->trackFrequency(trim($obj));
        }

        // Check if we already know this
        foreach ($this->knowledge[$subject] as &$existing) {
            if ($existing['rel'] === $relation) {
                // Merge objects, boost confidence
                $existing['obj'] = array_unique(array_merge($existing['obj'], $objects));
                $existing['conf'] = min(1.0, $existing['conf'] + 0.1);
                $this->save();
                return;
            }
        }

        // New knowledge
        $this->knowledge[$subject][] = [
            'rel' => $relation,
            'obj' => $objects,
            'conf' => $confidence,
        ];

        $this->save();
    }

    /**
     * Query knowledge about a subject
     */
    public function query(string $subject): ?array
    {
        $subject = trim($subject);

        // Direct match
        if (isset($this->knowledge[$subject])) {
            return [
                'subject' => $subject,
                'knowledge' => $this->knowledge[$subject],
                'match' => 'exact',
            ];
        }

        // Case-insensitive search
        $lower = strtolower($subject);
        foreach ($this->knowledge as $key => $val) {
            if (strtolower($key) === $lower) {
                return [
                    'subject' => $key,
                    'knowledge' => $val,
                    'match' => 'case_insensitive',
                ];
            }
        }

        // Partial match
        foreach ($this->knowledge as $key => $val) {
            if (stripos($key, $subject) !== false || stripos($subject, $key) !== false) {
                return [
                    'subject' => $key,
                    'knowledge' => $val,
                    'match' => 'partial',
                ];
            }
        }

        return null;
    }

    /**
     * Find related topics
     */
    public function related(string $subject, int $depth = 2): array
    {
        $related = [];
        $queue = [$subject];
        $visited = [$subject => true];

        for ($d = 0; $d < $depth; $d++) {
            $next = [];
            foreach ($queue as $current) {
                $knowledge = $this->knowledge[$current] ?? [];
                foreach ($knowledge as $rel) {
                    foreach ($rel['obj'] as $obj) {
                        if (!isset($visited[$obj])) {
                            $visited[$obj] = true;
                            $related[$obj] = $depth - $d;  // Closer = higher score
                            $next[] = $obj;
                        }
                    }
                }
            }
            $queue = $next;
        }

        arsort($related);
        return $related;
    }

    /**
     * Get all known subjects
     */
    public function subjects(): array
    {
        return array_keys($this->knowledge);
    }

    /**
     * Count total facts
     */
    public function countFacts(): int
    {
        $count = 0;
        foreach ($this->knowledge as $rels) {
            $count += count($rels);
        }
        return $count;
    }

    /**
     * Expand symbol to full term
     */
    private function expand(string $term): string
    {
        $term = trim($term);
        return $this->symbols[$term] ?? $term;
    }

    /**
     * Compress term to symbol if available
     */
    private function compress(string $term): string
    {
        $term = trim($term);
        $symbol = array_search($term, $this->symbols);
        return $symbol !== false ? $symbol : $term;
    }

    /**
     * Track term frequency, create symbol if threshold reached
     */
    private function trackFrequency(string $term): void
    {
        $term = trim($term);
        if (strlen($term) < 4) return;  // Don't symbolize short terms

        $this->frequency[$term] = ($this->frequency[$term] ?? 0) + 1;

        if ($this->frequency[$term] >= self::SYMBOL_THRESHOLD && !$this->hasSymbol($term)) {
            $symbol = $this->generateSymbol($term);
            $this->symbols[$symbol] = $term;
        }
    }

    /**
     * Check if term has a symbol
     */
    private function hasSymbol(string $term): bool
    {
        return in_array($term, $this->symbols);
    }

    /**
     * Generate a unique symbol for a term
     */
    private function generateSymbol(string $term): string
    {
        // First letter + consonants, max 4 chars
        $consonants = preg_replace('/[aeiou\s]/i', '', $term);
        $symbol = '@' . strtolower(substr($term, 0, 1) . substr($consonants, 0, 3));

        // Handle collisions
        $base = $symbol;
        $i = 1;
        while (isset($this->symbols[$symbol])) {
            $symbol = $base . $i++;
        }

        return $symbol;
    }

    /**
     * Get raw data for debugging
     */
    public function dump(): array
    {
        return [
            'symbols' => $this->symbols,
            'knowledge' => $this->knowledge,
            'frequency' => $this->frequency,
            'facts' => $this->countFacts(),
        ];
    }
}
