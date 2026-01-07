<?php
/**
 * Crob's Curiosity (Research Queue)
 *
 * Manages what Crob wants to learn next.
 * Topics get added when interesting things are discovered.
 */

class Curiosity
{
    private string $file;
    private array $queue = [];
    private array $completed = [];
    private array $config = [];

    public function __construct(string $dataDir = null)
    {
        $dataDir = $dataDir ?? __DIR__ . '/../data';
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        $this->file = $dataDir . '/crob.queue';

        $this->config = [
            'max_queue_size' => 1000,
            'max_depth' => 5,
            'max_daily_searches' => 500,
            'cooldown_seconds' => 10,
        ];

        $this->load();
    }

    /**
     * Load queue from file
     */
    public function load(): void
    {
        if (!file_exists($this->file)) {
            return;
        }

        $data = json_decode(file_get_contents($this->file), true);
        if (!$data) return;

        $this->queue = $data['queue'] ?? [];
        $this->completed = $data['completed'] ?? [];
    }

    /**
     * Save queue to file
     */
    public function save(): void
    {
        $data = [
            'queue' => $this->queue,
            'completed' => array_slice($this->completed, -1000),  // Keep last 1000
            'updated' => date('Y-m-d H:i:s'),
        ];

        file_put_contents($this->file, json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * Add a topic to research
     */
    public function enqueue(string $topic, array $meta = []): bool
    {
        $topic = trim($topic);

        // Already queued?
        foreach ($this->queue as $item) {
            if (strcasecmp($item['topic'], $topic) === 0) {
                return false;
            }
        }

        // Already learned?
        if (in_array(strtolower($topic), array_map('strtolower', $this->completed))) {
            return false;
        }

        // Queue full?
        if (count($this->queue) >= $this->config['max_queue_size']) {
            // Remove lowest priority
            usort($this->queue, fn($a, $b) => ($b['priority'] ?? 0.5) <=> ($a['priority'] ?? 0.5));
            array_pop($this->queue);
        }

        // Depth limit?
        $depth = ($meta['depth'] ?? 0) + 1;
        if ($depth > $this->config['max_depth']) {
            return false;
        }

        $this->queue[] = [
            'topic' => $topic,
            'origin' => $meta['origin'] ?? 'unknown',
            'reason' => $meta['reason'] ?? 'Looked interesting',
            'priority' => $meta['priority'] ?? 0.5,
            'depth' => $depth,
            'added' => date('Y-m-d H:i:s'),
        ];

        $this->save();
        return true;
    }

    /**
     * Get next topic to research
     */
    public function next(): ?array
    {
        if (empty($this->queue)) {
            return null;
        }

        // Sort by priority (highest first)
        usort($this->queue, fn($a, $b) => ($b['priority'] ?? 0.5) <=> ($a['priority'] ?? 0.5));

        return $this->queue[0];
    }

    /**
     * Mark topic as completed
     */
    public function complete(string $topic): void
    {
        $topic = trim($topic);

        // Remove from queue
        $this->queue = array_filter($this->queue, fn($item) => strcasecmp($item['topic'], $topic) !== 0);
        $this->queue = array_values($this->queue);  // Re-index

        // Add to completed
        $this->completed[] = $topic;

        $this->save();
    }

    /**
     * Get queue size
     */
    public function size(): int
    {
        return count($this->queue);
    }

    /**
     * Get all queued topics
     */
    public function all(): array
    {
        return $this->queue;
    }

    /**
     * Check if topic is queued or completed
     */
    public function knows(string $topic): bool
    {
        $topic = strtolower(trim($topic));

        foreach ($this->queue as $item) {
            if (strtolower($item['topic']) === $topic) {
                return true;
            }
        }

        return in_array($topic, array_map('strtolower', $this->completed));
    }

    /**
     * Clear the queue
     */
    public function clear(): void
    {
        $this->queue = [];
        $this->save();
    }

    /**
     * Get stats
     */
    public function stats(): array
    {
        return [
            'queued' => count($this->queue),
            'completed' => count($this->completed),
            'max_queue' => $this->config['max_queue_size'],
        ];
    }

    /**
     * Dump for debugging
     */
    public function dump(): array
    {
        return [
            'queue' => $this->queue,
            'completed_count' => count($this->completed),
            'config' => $this->config,
        ];
    }
}
