<?php
/**
 * Crob's Voice (Language Brain)
 *
 * Learns language patterns from web content.
 * Stores templates for generating human-like responses.
 */

class Voice
{
    private string $file;
    private array $patterns = [];     // intent => [templates]
    private array $connectors = [];   // transition phrases
    private array $personality = [];  // Crob's quirks

    public function __construct(string $dataDir = null)
    {
        $dataDir = $dataDir ?? __DIR__ . '/../data';
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        $this->file = $dataDir . '/crob.voice';
        $this->loadDefaults();
        $this->load();
    }

    /**
     * Default patterns (Crob's starter voice)
     */
    private function loadDefaults(): void
    {
        // Crob's personality - a mega nerd with two brain modes
        $this->personality = [
            'self_reference' => [
                "This nerd",
                "Your friendly neighborhood nerd",
                "This curious little nerd",
                "*adjusts glasses*",
                "Nerd brain activated:",
            ],
            'reptile_brain' => [  // Fast, instinctive responses
                "Reptile brain says:",
                "Quick instinct:",
                "Lizard mode:",
                "My reptile brain immediately thought:",
            ],
            'primate_brain' => [  // Thoughtful, researched responses
                "Primate brain engaged:",
                "After some thinking:",
                "My primate brain figured out:",
                "Putting on my thinking cap:",
            ],
            'curiosity' => [
                "Ooh, that's interesting...",
                "Wait, now I need to know more about",
                "My nerd senses are tingling about",
                "Adding to my rabbit hole list:",
                "*furiously takes notes about*",
            ],
            'uncertainty' => [
                "My reptile brain is unsure, let me use my primate brain...",
                "Hmm, this nerd needs to research more.",
                "I don't know yet, but I WILL find out.",
                "My knowledge brain is empty on this. Time to learn!",
            ],
        ];

        // Default response patterns
        $this->patterns = [
            'what_is' => [
                "{topic} is {definition}.",
                "So {topic} - it's basically {definition}.",
                "Primate brain says: {topic} is {definition}.",
            ],
            'what_is_used_for' => [
                "{topic} is used for {usage}.",
                "People use {topic} for {usage}.",
                "The main use of {topic}? {usage}.",
            ],
            'how_to' => [
                "To {action}, you {steps}.",
                "Here's how to {action}: {steps}.",
                "Nerd guide to {action}: {steps}.",
            ],
            'unknown' => [
                "Reptile brain: no idea. Primate brain: also no idea. But this nerd will find out!",
                "I don't know about {topic} yet. Adding to my research queue!",
                "*nervous nerd noises* I don't know that one... yet.",
            ],
        ];

        // Transition words
        $this->connectors = [
            'addition' => ["Also,", "Plus,", "And get this:", "Oh, and"],
            'contrast' => ["But", "However,", "Though", "On the flip side,"],
            'result' => ["So", "Therefore,", "Which means", "This leads to"],
            'example' => ["For example,", "Like,", "Such as", "Think of"],
        ];
    }

    /**
     * Load learned patterns from file
     */
    public function load(): void
    {
        if (!file_exists($this->file)) {
            return;
        }

        $data = json_decode(file_get_contents($this->file), true);
        if (!$data) return;

        // Merge with defaults (learned patterns take priority)
        if (isset($data['patterns'])) {
            foreach ($data['patterns'] as $intent => $templates) {
                $this->patterns[$intent] = array_merge(
                    $this->patterns[$intent] ?? [],
                    $templates
                );
            }
        }

        if (isset($data['connectors'])) {
            $this->connectors = array_merge($this->connectors, $data['connectors']);
        }

        if (isset($data['personality'])) {
            $this->personality = array_merge($this->personality, $data['personality']);
        }
    }

    /**
     * Save learned patterns
     */
    public function save(): void
    {
        $data = [
            'patterns' => $this->patterns,
            'connectors' => $this->connectors,
            'personality' => $this->personality,
            'updated' => date('Y-m-d H:i:s'),
        ];

        file_put_contents($this->file, json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * Learn a new pattern from text
     */
    public function learnPattern(string $sentence, string $topic): void
    {
        // Convert real sentence to template
        $template = $sentence;
        $template = str_ireplace($topic, '{topic}', $template);

        // Detect intent
        $intent = $this->detectIntent($sentence);

        // Abstract common words
        $template = preg_replace('/\b(powerful|flexible|simple|fast|easy|popular)\b/i', '{adj}', $template);
        $template = preg_replace('/\b(system|tool|library|framework|method)\b/i', '{type}', $template);

        // Store if it's a new useful pattern
        if ($this->isUsefulPattern($template)) {
            if (!isset($this->patterns[$intent])) {
                $this->patterns[$intent] = [];
            }
            if (!in_array($template, $this->patterns[$intent])) {
                $this->patterns[$intent][] = $template;
                $this->save();
            }
        }
    }

    /**
     * Generate a response
     */
    public function speak(string $intent, array $facts, string $topic): string
    {
        $templates = $this->patterns[$intent] ?? $this->patterns['what_is'];
        $template = $templates[array_rand($templates)];

        // Build response
        $response = $this->personality['primate_brain'][array_rand($this->personality['primate_brain'])] . " ";

        // Fill template
        $response .= $this->fillTemplate($template, $facts, $topic);

        // Add personality flourish occasionally
        if (rand(0, 3) === 0) {
            $response .= " " . $this->personality['self_reference'][array_rand($this->personality['self_reference'])] . " finds this fascinating.";
        }

        return $response;
    }

    /**
     * Express curiosity about a topic
     */
    public function expressCuriosity(string $topic): string
    {
        $phrases = $this->personality['curiosity'];
        return $phrases[array_rand($phrases)] . " " . $topic;
    }

    /**
     * Express uncertainty
     */
    public function expressUncertainty(string $topic): string
    {
        $phrases = $this->personality['uncertainty'];
        $phrase = $phrases[array_rand($phrases)];
        return str_replace('{topic}', $topic, $phrase);
    }

    /**
     * Get a reptile brain (quick/instinctive) response prefix
     */
    public function reptileBrain(): string
    {
        return $this->personality['reptile_brain'][array_rand($this->personality['reptile_brain'])];
    }

    /**
     * Get a primate brain (thoughtful) response prefix
     */
    public function primateBrain(): string
    {
        return $this->personality['primate_brain'][array_rand($this->personality['primate_brain'])];
    }

    /**
     * Fill a template with facts
     */
    private function fillTemplate(string $template, array $facts, string $topic): string
    {
        $result = $template;
        $result = str_replace('{topic}', $topic, $result);

        // Find definition fact
        $definition = '';
        $usage = '';
        foreach ($facts as $fact) {
            if (stripos($fact, ' is ') !== false || stripos($fact, ' are ') !== false) {
                $definition = $fact;
            }
            if (stripos($fact, ' for ') !== false || stripos($fact, ' to ') !== false) {
                $usage = $fact;
            }
        }

        $result = str_replace('{definition}', $definition ?: ($facts[0] ?? 'something interesting'), $result);
        $result = str_replace('{usage}', $usage ?: ($facts[0] ?? 'various things'), $result);

        return $result;
    }

    /**
     * Detect the intent of a sentence
     */
    private function detectIntent(string $sentence): string
    {
        if (preg_match('/\b(is|are) (a|an|the)\b/i', $sentence)) {
            return 'what_is';
        }
        if (preg_match('/\b(used for|use for|helps with)\b/i', $sentence)) {
            return 'what_is_used_for';
        }
        if (preg_match('/\b(to|how to|can)\b/i', $sentence)) {
            return 'how_to';
        }
        return 'general';
    }

    /**
     * Check if a pattern is worth saving
     */
    private function isUsefulPattern(string $template): bool
    {
        // Must have a placeholder
        if (strpos($template, '{') === false) return false;

        // Not too short or too long
        $len = strlen($template);
        if ($len < 15 || $len > 200) return false;

        // Not too many unknowns
        $placeholders = substr_count($template, '{');
        if ($placeholders > 4) return false;

        return true;
    }

    /**
     * Get patterns for debugging
     */
    public function dump(): array
    {
        return [
            'patterns' => $this->patterns,
            'connectors' => $this->connectors,
            'personality' => $this->personality,
        ];
    }
}
