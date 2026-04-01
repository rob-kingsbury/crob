<?php
/**
 * Crob - The Main Brain
 *
 * A curious, self-learning AI that grows its knowledge by exploring the web.
 * Built from scratch - no neural networks, no pre-trained models.
 * Just pattern matching, persistent memory, and insatiable curiosity.
 */

require_once __DIR__ . '/Brain.php';
require_once __DIR__ . '/Voice.php';
require_once __DIR__ . '/Curiosity.php';
require_once __DIR__ . '/Research.php';
require_once __DIR__ . '/Interests.php';

class Crob
{
    private Brain $brain;
    private Voice $voice;
    private Curiosity $curiosity;
    private Research $research;
    private Interests $interests;
    private string $dataDir;

    // Intent patterns
    private array $intents = [
        'what_is' => '/^(what|who)\s+(is|are)\s+(.+)/i',
        'what_is_used_for' => '/^what\s+(is|are)\s+(.+)\s+used\s+for/i',
        'how_to' => '/^how\s+(do|can|to)\s+(.+)/i',
        'tell_me_about' => '/^(tell me about|explain|describe)\s+(.+)/i',
        'why' => '/^why\s+(is|are|does|do)\s+(.+)/i',
    ];

    public function __construct(string $dataDir = null)
    {
        $this->dataDir = $dataDir ?? __DIR__ . '/../data';

        $this->brain = new Brain($this->dataDir);
        $this->voice = new Voice($this->dataDir);
        $this->curiosity = new Curiosity($this->dataDir);
        $this->research = new Research($this->brain, $this->voice, $this->curiosity);
        $this->interests = new Interests($this->brain, $this->curiosity, $this->dataDir);
    }

    /**
     * Ask Crob a question
     */
    public function ask(string $input): string
    {
        $input = trim($input);

        if (empty($input)) {
            return $this->voice->reptileBrain() . " You didn't ask anything. This nerd is waiting...";
        }

        // Parse intent and topic
        $parsed = $this->parseInput($input);
        $topic = $parsed['topic'];
        $intent = $parsed['intent'];

        // Check if we already know this
        $existing = $this->brain->query($topic);

        if ($existing && $existing['knowledge']) {
            // Reptile brain kicks in - fast response from memory
            $facts = $this->extractFactsFromKnowledge($existing['knowledge']);
            $response = $this->voice->reptileBrain() . " I know this one!\n\n";
            $response .= $this->voice->speak($intent, $facts, $topic);

            // But primate brain is curious about more...
            if (rand(0, 2) === 0) {
                $related = $this->brain->related($topic, 1);
                if (!empty($related)) {
                    $relatedTopic = array_keys($related)[0];
                    $response .= "\n\n" . $this->voice->expressCuriosity($relatedTopic);
                    $boost = $this->interests->priorityBoost($relatedTopic);
                    $this->curiosity->enqueue($relatedTopic, [
                        'origin' => $topic,
                        'reason' => "Got curious while answering about $topic",
                        'priority' => 0.6 + $boost,
                    ]);
                }
            }

            return $response;
        }

        // Don't know - time to research!
        $response = $this->voice->expressUncertainty($topic) . "\n\n";
        $response .= $this->voice->primateBrain() . " Let me research that...\n\n";

        // Do the research
        $results = $this->research->investigate($topic);

        if (empty($results['facts'])) {
            $response .= "*sad nerd noises* I couldn't find much about $topic. ";
            $response .= "Maybe try asking differently?";
            return $response;
        }

        // Compile response
        $response .= $this->voice->speak($intent, $results['facts'], $topic);

        // Report rabbit holes
        if (!empty($results['rabbit_holes'])) {
            $holes = array_slice($results['rabbit_holes'], 0, 3);
            $response .= "\n\n" . $this->voice->expressCuriosity(implode(', ', $holes));
            $response .= " (added to my research queue)";
        }

        // Stats
        $response .= "\n\n---\n";
        $response .= "Nerd stats: Learned " . count($results['facts']) . " facts from " . count($results['sources']) . " sources.";
        $response .= " Queue: " . $this->curiosity->size() . " topics.";

        return $response;
    }

    /**
     * Let Crob learn something in the background
     */
    public function backgroundLearn(): ?array
    {
        $next = $this->curiosity->next();

        if (!$next) {
            return null;
        }

        $topic = $next['topic'];
        $results = $this->research->investigate($topic, $next['depth'] ?? 1);

        $this->curiosity->complete($topic, ['origin' => $next['origin'] ?? 'unknown']);

        // Re-analyze interests after learning
        $this->interests->analyze();

        return [
            'topic' => $topic,
            'origin' => $next['origin'] ?? 'unknown',
            'facts_learned' => count($results['facts']),
            'new_rabbit_holes' => count($results['rabbit_holes']),
            'queue_size' => $this->curiosity->size(),
        ];
    }

    /**
     * Parse input to extract intent and topic
     */
    private function parseInput(string $input): array
    {
        foreach ($this->intents as $intent => $pattern) {
            if (preg_match($pattern, $input, $matches)) {
                // Topic is usually the last capture group
                $topic = end($matches);
                return [
                    'intent' => $intent,
                    'topic' => $this->cleanTopic($topic),
                    'raw' => $input,
                ];
            }
        }

        // No pattern matched - treat whole input as topic
        return [
            'intent' => 'what_is',
            'topic' => $this->cleanTopic($input),
            'raw' => $input,
        ];
    }

    /**
     * Clean up a topic string
     */
    private function cleanTopic(string $topic): string
    {
        // Remove trailing punctuation
        $topic = rtrim($topic, '?!.');

        // Remove articles
        $topic = preg_replace('/^(a|an|the)\s+/i', '', $topic);

        return trim($topic);
    }

    /**
     * Extract fact strings from knowledge structure
     */
    private function extractFactsFromKnowledge(array $knowledge): array
    {
        $facts = [];
        foreach ($knowledge as $item) {
            if (is_array($item['obj'])) {
                $facts = array_merge($facts, $item['obj']);
            } else {
                $facts[] = $item['obj'];
            }
        }
        return $facts;
    }

    /**
     * Teach Crob something directly
     */
    public function teach(string $topic, string $fact): string
    {
        $relation = Brain::REL_IS;

        // Detect relation type
        if (preg_match('/\b(has|have|contains)\b/i', $fact)) {
            $relation = Brain::REL_HAS;
        } elseif (preg_match('/\b(is used for|helps with)\b/i', $fact)) {
            $relation = Brain::REL_USED_BY;
        }

        $this->brain->learn($topic, $relation, $fact, 0.9);  // High confidence - you told me!

        return $this->voice->primateBrain() . " Got it! I learned that $topic $fact. "
             . $this->voice->expressCuriosity($topic);
    }

    /**
     * Get Crob's stats
     */
    public function stats(): array
    {
        $profile = $this->interests->load();
        $established = $profile ? count($profile['established'] ?? []) : 0;
        $tentative = $profile ? count($profile['tentative'] ?? []) : 0;

        return [
            'knowledge' => [
                'facts' => $this->brain->countFacts(),
                'subjects' => count($this->brain->subjects()),
            ],
            'curiosity' => $this->curiosity->stats(),
            'interests' => [
                'established' => $established,
                'tentative' => $tentative,
                'top' => array_keys($this->interests->top(3)),
            ],
            'born' => filemtime($this->dataDir . '/crob.crob') ?: null,
        ];
    }

    /**
     * Get what Crob knows about a topic (raw)
     */
    public function knows(string $topic): ?array
    {
        return $this->brain->query($topic);
    }

    /**
     * Get Crob's research queue
     */
    public function queue(): array
    {
        return $this->curiosity->all();
    }

    /**
     * Full brain dump for debugging
     */
    public function dump(): array
    {
        return [
            'brain' => $this->brain->dump(),
            'voice' => $this->voice->dump(),
            'curiosity' => $this->curiosity->dump(),
            'interests' => $this->interests->dump(),
        ];
    }

    /**
     * Analyze and return interest profile
     */
    public function analyzeInterests(): array
    {
        return $this->interests->analyze();
    }

    /**
     * Get top interests (from last analysis)
     */
    public function topInterests(int $n = 3): array
    {
        return $this->interests->top($n);
    }

    /**
     * Crob introduces himself
     */
    public function introduce(): string
    {
        $stats = $this->stats();

        $intro = "Hey! I'm Crob - a curious little nerd built by Rob and Claude.\n\n";

        $intro .= "I have two brain modes:\n";
        $intro .= "- Reptile brain: Fast, instinctive answers from memory\n";
        $intro .= "- Primate brain: Thoughtful research when I don't know something\n\n";

        if ($stats['knowledge']['facts'] > 0) {
            $intro .= "So far I know {$stats['knowledge']['facts']} facts about {$stats['knowledge']['subjects']} topics.\n";
            $intro .= "I have {$stats['curiosity']['queued']} topics in my research queue.\n\n";
        } else {
            $intro .= "I don't know anything yet! I was just born.\n";
            $intro .= "Ask me something and I'll research it.\n\n";
        }

        $intro .= "Try asking me: What is CSS Grid?\n";
        $intro .= "Or teach me: teach me that PHP is a programming language";

        return $intro;
    }
}
