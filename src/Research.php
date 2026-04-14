<?php
/**
 * Crob's Research Engine
 *
 * Fetches and extracts knowledge from the web.
 * Learns facts AND language patterns from content.
 */

class Research
{
    private Brain $brain;
    private Voice $voice;
    private Curiosity $curiosity;
    private array $config;
    private bool $verbose;

    public function __construct(Brain $brain, Voice $voice, Curiosity $curiosity, bool $verbose = false)
    {
        $this->brain = $brain;
        $this->voice = $voice;
        $this->curiosity = $curiosity;
        $this->verbose = $verbose;

        $this->config = [
            'max_results' => 5,
            'max_iterations' => 3,
            'user_agent' => 'Crob/0.1 (Curious Learning Bot; +https://github.com/robmcdonald/crob)',
            'timeout' => 10,
        ];
    }

    /**
     * Research a topic
     */
    public function investigate(string $topic, int $depth = 1): array
    {
        $results = [
            'topic' => $topic,
            'facts' => [],
            'sources' => [],
            'rabbit_holes' => [],
        ];

        // Search the web
        $urls = $this->search($topic);
        $results['sources'] = $urls;

        // Fetch, extract, and learn from each result (one-pass per URL so $url
        // can flow as the provenance source into Brain::learn). Dedup happens
        // in the merge block — don't array_unique facts here, that would throw
        // away cross-source corroboration signal.
        foreach ($urls as $url) {
            $content = $this->fetch($url);
            if (!$content) continue;

            $facts = $this->extractFacts($content, $topic);
            $results['facts'] = array_merge($results['facts'], $facts);

            $learnResults = [];
            foreach ($facts as $fact) {
                $relation = $this->detectRelation($fact);
                $learnResults[] = $this->brain->learn($topic, $relation, $fact, 0.5, $url);
            }

            // Learn language patterns
            $sentences = $this->extractSentences($content, $topic);
            foreach ($sentences as $sentence) {
                $this->voice->learnPattern($sentence, $topic);
            }

            // Find rabbit holes (related topics)
            $related = $this->extractRelatedTopics($content, $topic);
            $results['rabbit_holes'] = array_merge($results['rabbit_holes'], $related);

            if ($this->verbose) {
                $this->printVerbose($url, $facts, $learnResults);
            }
        }

        // Rabbit holes still dedup — that's unrelated to source provenance.
        $results['rabbit_holes'] = array_unique($results['rabbit_holes']);

        // Queue rabbit holes if not too deep
        if ($depth < $this->config['max_iterations']) {
            foreach (array_slice($results['rabbit_holes'], 0, 5) as $hole) {
                $this->curiosity->enqueue($hole, [
                    'origin' => $topic,
                    'reason' => "Discovered while researching $topic",
                    'depth' => $depth,
                    'priority' => 0.4,
                ]);
            }
        }

        return $results;
    }

    /**
     * Search the web (using DuckDuckGo HTML)
     */
    public function search(string $query): array
    {
        $urls = [];

        // Use DuckDuckGo HTML (no API key needed)
        $searchUrl = 'https://html.duckduckgo.com/html/?q=' . urlencode($query);

        $html = $this->fetch($searchUrl);
        if (!$html) return [];

        // Extract result URLs
        preg_match_all('/class="result__a" href="([^"]+)"/', $html, $matches);

        foreach ($matches[1] ?? [] as $url) {
            // DuckDuckGo uses redirect URLs, extract actual URL
            if (preg_match('/uddg=([^&]+)/', $url, $m)) {
                $url = urldecode($m[1]);
            }

            // Skip certain domains
            if ($this->isUsefulSource($url)) {
                $urls[] = $url;
            }

            if (count($urls) >= $this->config['max_results']) {
                break;
            }
        }

        return $urls;
    }

    /**
     * Fetch a URL's content
     */
    public function fetch(string $url): ?string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $this->config['timeout'],
            CURLOPT_USERAGENT => $this->config['user_agent'],
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $html = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200 || !$html) {
            return null;
        }

        // Strip HTML, keep text
        return $this->htmlToText($html);
    }

    /**
     * Convert HTML to clean text
     */
    private function htmlToText(string $html): string
    {
        // Remove script and style
        $html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);

        // Remove tags
        $text = strip_tags($html);

        // Clean whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        // Decode entities
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

        return trim($text);
    }

    /**
     * Extract facts about a topic from text
     */
    public function extractFacts(string $text, string $topic): array
    {
        $facts = [];
        $sentences = $this->splitSentences($text);

        foreach ($sentences as $sentence) {
            // Must mention the topic
            if (stripos($sentence, $topic) === false) {
                continue;
            }

            // Must look like a fact
            if ($this->looksLikeFact($sentence)) {
                $facts[] = trim($sentence);
            }
        }

        return array_slice($facts, 0, 10);  // Max 10 facts per source
    }

    /**
     * Check if a sentence looks like a fact
     */
    private function looksLikeFact(string $sentence): bool
    {
        // Good length
        $words = str_word_count($sentence);
        if ($words < 5 || $words > 50) return false;

        // Has fact indicators
        $factPatterns = [
            '/\b(is|are|was|were)\s+(a|an|the)\b/i',
            '/\b(means|refers to|defined as)\b/i',
            '/\b(used for|helps|allows|enables)\b/i',
            '/\b(created|developed|released|introduced)\b/i',
        ];

        foreach ($factPatterns as $pattern) {
            if (preg_match($pattern, $sentence)) {
                return true;
            }
        }

        // No uncertainty indicators
        $uncertainPatterns = [
            '/\b(maybe|probably|might|could be|I think|in my opinion)\b/i',
            '/\?$/',  // Questions
        ];

        foreach ($uncertainPatterns as $pattern) {
            if (preg_match($pattern, $sentence)) {
                return false;
            }
        }

        return false;
    }

    /**
     * Extract sentences mentioning the topic
     */
    private function extractSentences(string $text, string $topic): array
    {
        $sentences = $this->splitSentences($text);
        return array_filter($sentences, fn($s) => stripos($s, $topic) !== false);
    }

    /**
     * Split text into sentences
     */
    private function splitSentences(string $text): array
    {
        // Split on sentence endings
        $sentences = preg_split('/(?<=[.!?])\s+/', $text);
        return array_filter($sentences, fn($s) => strlen(trim($s)) > 10);
    }

    /**
     * Extract related topics (rabbit holes)
     */
    public function extractRelatedTopics(string $text, string $topic): array
    {
        $related = [];

        // Find phrases like "X is related to Y" or "X and Y"
        $patterns = [
            '/\b' . preg_quote($topic, '/') . '\b.{0,50}\b(and|with|using|like)\s+([A-Z][a-z]+(?:\s+[A-Z][a-z]+)?)/i',
            '/([A-Z][a-z]+(?:\s+[A-Z][a-z]+)?)\s+(and|with|like)\s+\b' . preg_quote($topic, '/') . '\b/i',
            '/\b(similar to|compared to|alternative to|see also)\s+([A-Z][a-z]+(?:\s+[A-Z][a-z]+)?)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                $related = array_merge($related, $matches[2] ?? []);
            }
        }

        // Filter out the original topic and common words
        $stopWords = ['The', 'This', 'That', 'These', 'Those', 'It', 'They', 'We', 'You', 'I'];
        $related = array_filter($related, fn($r) =>
            strcasecmp($r, $topic) !== 0 &&
            !in_array($r, $stopWords) &&
            strlen($r) > 2
        );

        return array_unique($related);
    }

    /**
     * Detect what type of relation a fact describes
     */
    private function detectRelation(string $fact): string
    {
        if (preg_match('/\b(is|are)\s+(a|an|the)\b/i', $fact)) {
            return Brain::REL_IS;
        }
        if (preg_match('/\b(has|have|contains|includes)\b/i', $fact)) {
            return Brain::REL_HAS;
        }
        if (preg_match('/\b(part of|belongs to|inside)\b/i', $fact)) {
            return Brain::REL_PART_OF;
        }
        if (preg_match('/\b(used by|popular with)\b/i', $fact)) {
            return Brain::REL_USED_BY;
        }
        if (preg_match('/\b(related|similar|like)\b/i', $fact)) {
            return Brain::REL_RELATES;
        }
        return Brain::REL_IS;  // Default
    }

    /**
     * Print --verbose output for one URL's learn batch. Format locked by
     * Morgan in the plan review: per-URL summary line, per-fact detail only
     * when something interesting happened (new/corroborated/ambiguous).
     * Restatements are noise and get suppressed.
     */
    private function printVerbose(string $url, array $facts, array $learnResults): void
    {
        $domain = $this->brain->extractDomain($url) ?? $url;
        $factCount = count($facts);

        $stored = 0;
        $duplicate = 0;
        foreach ($learnResults as $r) {
            if ($r['tier'] === 'restatement') {
                $duplicate++;
            } else {
                $stored++;
            }
        }

        echo "[learn] {$domain} -> {$factCount} facts ({$stored} stored, {$duplicate} duplicate)\n";

        foreach ($learnResults as $r) {
            if ($r['tier'] === 'restatement') {
                continue;  // suppress noise
            }
            $subject = $r['subject'];
            $rel = Brain::relationName($r['relation']);
            $conf = number_format($r['confidence'], 2);

            if ($r['tier'] === 'ambiguous') {
                $distinct = $r['distinct_objects'];
                echo "  {$subject} {$rel}: ambiguous -- {$distinct} distinct objects now (distinct_objects: {$distinct})\n";
            } else {
                echo "  {$subject} {$rel}: {$r['tier']} (confidence: {$conf})\n";
            }
        }
    }

    /**
     * Check if a URL is worth fetching
     */
    private function isUsefulSource(string $url): bool
    {
        // Skip these
        $blacklist = [
            'youtube.com',
            'facebook.com',
            'twitter.com',
            'instagram.com',
            'pinterest.com',
            'tiktok.com',
        ];

        foreach ($blacklist as $domain) {
            if (stripos($url, $domain) !== false) {
                return false;
            }
        }

        return true;
    }
}
