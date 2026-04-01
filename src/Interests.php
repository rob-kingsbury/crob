<?php
/**
 * Crob's Interest Profile (Derived Observer)
 *
 * Reads Brain knowledge and Curiosity history to derive weighted interest profiles.
 * Standalone observer — never modifies Brain or Curiosity data.
 * Compute-on-demand only — never triggered from write paths.
 */

class Interests
{
    private Brain $brain;
    private Curiosity $curiosity;
    private string $file;

    // Scoring weights — named constants, expected to be tuned after observing real output
    const W_DENSITY    = 0.35;   // knowledge density (relation count per subject)
    const W_RECURRENCE = 0.35;   // behavioral recurrence (how often topic appears in completed)
    const W_CONFIDENCE = 0.15;   // average confidence across relations
    const W_TEACH      = 0.15;   // teach bonus (0.6 if taught, 0.0 if not)

    // Confidence gate thresholds
    const GATE_MIN_ORIGINS   = 3;  // independent curiosity origins to qualify as established
    const GATE_MIN_RELATIONS = 5;  // distinct brain relations to qualify as established

    // Decay
    const DECAY_LAMBDA = 0.05;   // exponential decay rate — half-life ~14 days

    // Teach bonus value
    const TEACH_BONUS = 0.6;

    public function __construct(Brain $brain, Curiosity $curiosity, string $dataDir = null)
    {
        $this->brain = $brain;
        $this->curiosity = $curiosity;
        $dataDir = $dataDir ?? __DIR__ . '/../data';
        $this->file = $dataDir . '/crob.interests';
    }

    /**
     * Main entry — analyze Brain + Curiosity and return full interest profile
     */
    public function analyze(): array
    {
        // Cache completed items once — used by multiple methods
        $completedItems = $this->curiosity->completedItems();

        $density = $this->knowledgeDensity();
        $recurrence = $this->recurrence($completedItems);
        $recency = $this->recencyScores($completedItems);
        $confidence = $this->confidenceScores();
        $taught = $this->taughtTopics($completedItems);
        $clusters = $this->clusterSubjects();

        // Normalize density keys to lowercase for consistent lookup
        $densityNorm = [];
        foreach ($density as $subject => $count) {
            $norm = strtolower($subject);
            $densityNorm[$norm] = max($densityNorm[$norm] ?? 0, $count);
        }

        // Build display names (prefer Brain's original casing)
        $displayNames = [];
        foreach ($this->brain->subjects() as $subject) {
            $displayNames[strtolower($subject)] = $subject;
        }

        // All subjects normalized to lowercase
        $allSubjects = array_unique(array_merge(
            array_keys($densityNorm),
            array_keys($recurrence)
        ));

        $scores = [];
        $maxDensity = !empty($densityNorm) ? max($densityNorm) : 1;
        $totalCompleted = array_sum($recurrence) ?: 1;

        foreach ($allSubjects as $norm) {
            $densityScore = ($densityNorm[$norm] ?? 0) / $maxDensity;
            $recurrenceScore = ($recurrence[$norm] ?? 0) / $totalCompleted;
            $confidenceScore = $confidence[$norm] ?? 0.5;
            $teachScore = isset($taught[$norm]) ? self::TEACH_BONUS : 0.0;

            $baseScore =
                $densityScore    * self::W_DENSITY +
                $recurrenceScore * self::W_RECURRENCE +
                $confidenceScore * self::W_CONFIDENCE +
                $teachScore      * self::W_TEACH;

            // Apply recency decay
            $decay = $recency[$norm] ?? 1.0;
            $weight = $baseScore * $decay;

            if ($weight > 0.01) {
                $display = $displayNames[$norm] ?? $norm;
                $scores[$display] = [
                    'weight' => round($weight, 4),
                    'density' => $densityNorm[$norm] ?? 0,
                    'recurrence' => $recurrence[$norm] ?? 0,
                    'confidence' => round($confidenceScore, 2),
                    'taught' => isset($taught[$norm]),
                    'decay' => round($decay, 3),
                ];
            }
        }

        // Sort by weight descending
        uasort($scores, fn($a, $b) => $b['weight'] <=> $a['weight']);

        // Assign clusters
        $clustered = $this->assignClusters($scores, $clusters);

        // Precompute origins per subject for gate check
        $originsMap = $this->buildOriginsMap($completedItems);

        // Apply confidence gate — split into established vs tentative
        $established = [];
        $tentative = [];

        foreach ($clustered as $subject => $data) {
            if ($this->meetsGate($subject, $data, $originsMap)) {
                $established[$subject] = $data;
            } else {
                $tentative[$subject] = $data;
            }
        }

        $profile = [
            'established' => $established,
            'tentative' => $tentative,
            'analyzed_at' => time(),
            'subjects_analyzed' => count($allSubjects),
        ];

        $this->save($profile);
        return $profile;
    }

    /**
     * Get top N interest clusters for priority boosting
     */
    public function top(int $n = 3): array
    {
        $profile = $this->load();
        if (!$profile || empty($profile['established'])) {
            return [];
        }

        return array_slice($profile['established'], 0, $n, true);
    }

    /**
     * Calculate priority boost for a topic based on current interests
     * Returns 0.0 if unrelated, up to +0.15 if strongly related to top interests
     */
    public function priorityBoost(string $topic): float
    {
        $top = $this->top(3);
        if (empty($top)) {
            return 0.0;
        }

        $topic = strtolower(trim($topic));
        $topSubjects = array_map('strtolower', array_keys($top));

        // Direct match with a top interest
        if (in_array($topic, $topSubjects)) {
            return 0.15;
        }

        // Check if topic shares a cluster with any top interest
        foreach ($top as $data) {
            if (isset($data['cluster']) && $data['cluster'] !== null) {
                // Check if the topic appears in the same cluster's related subjects
                $related = $this->brain->related($topic, 2);
                foreach ($topSubjects as $topSubject) {
                    if (isset($related[$topSubject]) || isset($related[ucfirst($topSubject)])) {
                        return 0.10;
                    }
                }
            }
        }

        return 0.0;
    }

    /**
     * Relation count per subject in Brain
     */
    private function knowledgeDensity(): array
    {
        $density = [];
        foreach ($this->brain->subjects() as $subject) {
            $result = $this->brain->query($subject);
            if ($result && isset($result['knowledge'])) {
                $density[$subject] = count($result['knowledge']);
            }
        }
        return $density;
    }

    /**
     * How often each topic appears in the completed list
     */
    private function recurrence(array $completedItems): array
    {
        $counts = [];
        foreach ($completedItems as $item) {
            $topic = strtolower($item['topic']);
            $counts[$topic] = ($counts[$topic] ?? 0) + 1;

            // Also count origins — a topic appearing as origin for many completions is a hub
            $origin = strtolower($item['origin'] ?? 'unknown');
            if ($origin !== 'unknown') {
                $counts[$origin] = ($counts[$origin] ?? 0) + 1;
            }
        }
        return $counts;
    }

    /**
     * Recency decay scores based on most recent completion timestamp
     */
    private function recencyScores(array $completedItems): array
    {
        $latest = [];
        $now = time();

        foreach ($completedItems as $item) {
            $topic = strtolower($item['topic']);
            $ts = $item['completed_at'];

            if ($ts === null) {
                // Legacy item with no timestamp — assign moderate decay
                $latest[$topic] = $latest[$topic] ?? 0.5;
                continue;
            }

            $daysSince = ($now - $ts) / 86400;
            $decay = exp(-self::DECAY_LAMBDA * $daysSince);

            // Keep the most recent (highest decay value)
            if (!isset($latest[$topic]) || $decay > $latest[$topic]) {
                $latest[$topic] = $decay;
            }
        }

        // Subjects in Brain but not in completed — they exist but haven't been researched via queue
        foreach ($this->brain->subjects() as $subject) {
            $norm = strtolower($subject);
            if (!isset($latest[$norm])) {
                $latest[$norm] = 0.7; // Present in brain, no queue history — moderate freshness
            }
        }

        return $latest;
    }

    /**
     * Average confidence per subject from Brain knowledge (normalized to lowercase keys)
     */
    private function confidenceScores(): array
    {
        $scores = [];
        foreach ($this->brain->subjects() as $subject) {
            $result = $this->brain->query($subject);
            if ($result && !empty($result['knowledge'])) {
                $confs = array_column($result['knowledge'], 'conf');
                $scores[strtolower($subject)] = array_sum($confs) / count($confs);
            }
        }
        return $scores;
    }

    /**
     * Identify topics present in Brain but absent from Curiosity (likely taught)
     */
    private function taughtTopics(array $completedItems): array
    {
        $taught = [];
        $knownTopics = [];

        foreach ($completedItems as $item) {
            $knownTopics[strtolower($item['topic'])] = true;
        }

        // Also check queued items
        foreach ($this->curiosity->all() as $item) {
            $knownTopics[strtolower($item['topic'])] = true;
        }

        foreach ($this->brain->subjects() as $subject) {
            $norm = strtolower($subject);
            if (!isset($knownTopics[$norm])) {
                $taught[$norm] = true;
            }
        }

        return $taught;
    }

    /**
     * Precompute distinct origins per subject from completed items
     */
    private function buildOriginsMap(array $completedItems): array
    {
        $map = [];
        foreach ($completedItems as $item) {
            $topic = strtolower($item['topic']);
            $origin = strtolower($item['origin'] ?? 'unknown');
            if ($origin !== 'unknown') {
                if (!isset($map[$topic])) {
                    $map[$topic] = [];
                }
                $map[$topic][$origin] = true;
            }
        }
        return $map;
    }

    /**
     * Cluster subjects by shared relation objects (reverse graph index)
     */
    private function clusterSubjects(): array
    {
        $reverseIndex = $this->invertGraph();
        $clusters = [];
        $assigned = [];

        // Sort objects by how many subjects reference them (descending) — denser hubs first
        uasort($reverseIndex, fn($a, $b) => count($b) - count($a));

        foreach ($reverseIndex as $object => $subjects) {
            if (count($subjects) < 2) {
                continue; // Need at least 2 subjects to form a cluster
            }

            // Skip if all subjects are already assigned
            $unassigned = array_filter($subjects, fn($s) => !isset($assigned[$s]));
            if (count($unassigned) < 2) {
                continue;
            }

            $clusterName = $object; // Name by the shared relation object
            $clusters[$clusterName] = $subjects;

            foreach ($subjects as $subject) {
                $assigned[$subject] = $clusterName;
            }
        }

        return $clusters;
    }

    /**
     * Build reverse index: object → [subjects that reference it]
     */
    private function invertGraph(): array
    {
        $index = [];
        foreach ($this->brain->subjects() as $subject) {
            $result = $this->brain->query($subject);
            if (!$result || empty($result['knowledge'])) {
                continue;
            }
            foreach ($result['knowledge'] as $rel) {
                foreach ($rel['obj'] as $obj) {
                    $objNorm = strtolower(trim($obj));
                    $subNorm = strtolower($subject);
                    if ($objNorm === $subNorm) {
                        continue; // Skip self-references
                    }
                    if (!isset($index[$objNorm])) {
                        $index[$objNorm] = [];
                    }
                    if (!in_array($subNorm, $index[$objNorm])) {
                        $index[$objNorm][] = $subNorm;
                    }
                }
            }
        }
        return $index;
    }

    /**
     * Assign cluster labels to scored subjects
     */
    private function assignClusters(array $scores, array $clusters): array
    {
        // Build subject → cluster lookup
        $lookup = [];
        foreach ($clusters as $clusterName => $subjects) {
            foreach ($subjects as $subject) {
                $lookup[strtolower($subject)] = $clusterName;
            }
        }

        foreach ($scores as $subject => &$data) {
            $data['cluster'] = $lookup[strtolower($subject)] ?? null;
        }

        return $scores;
    }

    /**
     * Check if a subject meets the confidence gate for "established" status
     */
    private function meetsGate(string $subject, array $data, array $originsMap): bool
    {
        // Check distinct relations threshold
        if (($data['density'] ?? 0) >= self::GATE_MIN_RELATIONS) {
            return true;
        }

        // Check independent origins threshold
        $origins = $originsMap[strtolower($subject)] ?? [];
        return count($origins) >= self::GATE_MIN_ORIGINS;
    }

    /**
     * Save interest profile to file
     */
    public function save(array $profile): void
    {
        file_put_contents($this->file, json_encode($profile, JSON_PRETTY_PRINT));
    }

    /**
     * Load last computed profile from file
     */
    public function load(): ?array
    {
        if (!file_exists($this->file)) {
            return null;
        }

        $data = json_decode(file_get_contents($this->file), true);
        return $data ?: null;
    }

    /**
     * Dump for debugging
     */
    public function dump(): array
    {
        $profile = $this->load();
        return [
            'profile' => $profile,
            'clusters' => $this->clusterSubjects(),
            'reverse_index_size' => count($this->invertGraph()),
        ];
    }
}
