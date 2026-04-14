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
    private string $provenanceFile;
    private array $symbols = [];      // @G => GSAP
    private array $knowledge = [];    // subject => [relations]
    private array $frequency = [];    // term => usage count
    private array $provenance = [];   // expandedSubject => relation => [sources, first_seen, last_seen, distinct_objects]

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
        $this->provenanceFile = $dataDir . '/crob.provenance.json';
        $this->load();
        $this->loadProvenance();
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
            // Confidence: 1 digit = legacy .N format (0.N), 2 digits = new .NN format (0.NN)
            if (preg_match('/^(.+?)(:[=><~@!#?])\.?(\d{1,2})?>(.*?)$/', $line, $m)) {
                $subject = $this->expand($m[1]);
                $relation = $m[2];
                $confidence = isset($m[3]) && $m[3] !== ''
                    ? (strlen($m[3]) === 1 ? (int)$m[3] / 10 : (int)$m[3] / 100)
                    : 0.5;
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
     * Save brain to .crob file + provenance sidecar.
     *
     * Sidecar writes first. If .crob write fails, sidecar has extra entries
     * (harmless on next load). If sidecar write fails, .crob doesn't write
     * (safe — provenance stays consistent with knowledge on disk).
     */
    public function save(): void
    {
        $this->saveProvenance();

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
                // Two-decimal format: .00-.99 (never .100 — cap at .99 for representability).
                $confCapped = min(0.99, $rel['conf']);
                $conf = $rel['conf'] != 0.5
                    ? '.' . str_pad((string)(int)round($confCapped * 100), 2, '0', STR_PAD_LEFT)
                    : '';
                $lines[] = $subjectSym . $rel['rel'] . $conf . '>' . implode(',', $objects);
            }
        }

        file_put_contents($this->file, implode("\n", $lines));
    }

    /**
     * Learn a new fact
     *
     * Returns a result array describing what happened:
     *   tier: 'new' | 'corroborated' | 'ambiguous' | 'restatement'
     *   confidence: float (post-merge)
     *   distinct_objects: int (count after merge)
     *   subject: string (expanded, never a symbol)
     *   relation: string
     *
     * $source is the provenance hint. For research, pass the URL. For direct
     * teaching, pass "direct_teach". null means unknown (legacy callers).
     */
    public function learn(
        string $subject,
        string $relation,
        array|string $objects,
        float $confidence = 0.5,
        ?string $source = null
    ): array {
        $objects = (array)$objects;
        $subject = trim($subject);
        $expandedSubject = $this->expand($subject);

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
                // Provisional weights (0.15/0.05/0.05) -- revisit after Phase 1 sidecar data collection.
                if (!isset($this->provenance[$expandedSubject][$relation])) {
                    $this->provenance[$expandedSubject][$relation] = [
                        'sources' => [],
                        'first_seen' => time(),
                        'last_seen' => time(),
                        'distinct_objects' => count($existing['obj']),
                    ];
                }
                $prov = &$this->provenance[$expandedSubject][$relation];

                $domain = $this->extractDomain($source);
                $newObjects = array_values(array_diff($objects, $existing['obj']));
                $isNewSource = $domain !== null && !in_array($domain, $prov['sources'], true);
                $hasNewObjects = count($newObjects) > 0;

                if ($isNewSource && !$hasNewObjects) {
                    $bump = 0.15;
                    $tier = 'corroborated';
                } elseif ($isNewSource && $hasNewObjects) {
                    $bump = 0.05;
                    $tier = 'ambiguous';
                } else {
                    $bump = 0.05;
                    $tier = 'restatement';
                }

                $existing['obj'] = array_values(array_unique(array_merge($existing['obj'], $objects)));
                $existing['conf'] = min(0.99, round($existing['conf'] + $bump, 2));

                if ($isNewSource) {
                    $prov['sources'][] = $domain;
                }
                $prov['last_seen'] = time();
                $prov['distinct_objects'] = count($existing['obj']);

                $distinctObjects = $prov['distinct_objects'];
                $finalConf = $existing['conf'];
                unset($prov);

                $this->save();
                return [
                    'tier' => $tier,
                    'confidence' => $finalConf,
                    'distinct_objects' => $distinctObjects,
                    'subject' => $expandedSubject,
                    'relation' => $relation,
                ];
            }
        }

        // New knowledge
        $this->knowledge[$subject][] = [
            'rel' => $relation,
            'obj' => $objects,
            'conf' => round($confidence, 2),
        ];

        $domain = $this->extractDomain($source);
        $this->provenance[$expandedSubject][$relation] = [
            'sources' => $domain !== null ? [$domain] : [],
            'first_seen' => time(),
            'last_seen' => time(),
            'distinct_objects' => count($objects),
        ];

        $this->save();

        return [
            'tier' => 'new',
            'confidence' => round($confidence, 2),
            'distinct_objects' => count($objects),
            'subject' => $expandedSubject,
            'relation' => $relation,
        ];
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
            'provenance' => $this->provenance,
        ];
    }

    /**
     * Load provenance sidecar from disk. Missing file = empty + warning.
     * This is intentional: silent continuation would double-count sources
     * on future learns and inflate confidence.
     */
    private function loadProvenance(): void
    {
        if (!file_exists($this->provenanceFile)) {
            if (file_exists($this->file)) {
                // .crob exists but sidecar doesn't — warn because future learns
                // will count previously-seen sources as new.
                error_log("Crob: provenance sidecar missing at {$this->provenanceFile} — treating as empty");
            }
            $this->provenance = [];
            return;
        }

        $raw = file_get_contents($this->provenanceFile);
        $decoded = json_decode($raw, true);
        $this->provenance = is_array($decoded) ? $decoded : [];
    }

    /**
     * Save provenance sidecar. Called from save() before the .crob write.
     * Keys must always be expanded subject strings (never symbols like @G)
     * so provenance survives symbol regeneration across loads.
     */
    private function saveProvenance(): void
    {
        $json = json_encode($this->provenance, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException("Failed to encode provenance sidecar: " . json_last_error_msg());
        }
        $ok = file_put_contents($this->provenanceFile, $json);
        if ($ok === false) {
            throw new RuntimeException("Failed to write provenance sidecar to {$this->provenanceFile}");
        }
    }

    /**
     * Extract the domain from a source URL. Non-URL sentinel strings like
     * "direct_teach" are returned unchanged so they count as a single stable
     * source across repeated calls.
     */
    public function extractDomain(?string $source): ?string
    {
        if ($source === null) {
            return null;
        }
        $host = parse_url($source, PHP_URL_HOST);
        if ($host === null || $host === false) {
            return $source;  // sentinel like "direct_teach" — store as-is
        }
        return preg_replace('/^www\./', '', $host);
    }

    /**
     * Human-readable name for a relation symbol. Used by --verbose output.
     */
    public static function relationName(string $rel): string
    {
        static $names = [
            self::REL_IS        => 'REL_IS',
            self::REL_HAS       => 'REL_HAS',
            self::REL_PART_OF   => 'REL_PART_OF',
            self::REL_RELATES   => 'REL_RELATES',
            self::REL_USED_BY   => 'REL_USED_BY',
            self::REL_NOT       => 'REL_NOT',
            self::REL_INSTANCE  => 'REL_INSTANCE',
            self::REL_UNCERTAIN => 'REL_UNCERTAIN',
        ];
        return $names[$rel] ?? $rel;
    }

    /**
     * Read provenance data for a subject+relation pair. Read-only accessor
     * for external consumers (e.g. --dump, --verbose, future contradiction audits).
     */
    public function getProvenance(string $subject, ?string $relation = null): array
    {
        $expanded = $this->expand(trim($subject));
        if ($relation === null) {
            return $this->provenance[$expanded] ?? [];
        }
        return $this->provenance[$expanded][$relation] ?? [];
    }
}
