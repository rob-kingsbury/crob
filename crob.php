#!/usr/bin/env php
<?php
/**
 * Crob CLI - Talk to your curious nerd from the command line
 *
 * Usage:
 *   php crob.php                     - Interactive mode
 *   php crob.php "what is GSAP"      - Single question
 *   php crob.php --learn             - Background learning
 *   php crob.php --stats             - Show stats
 *   php crob.php --queue             - Show research queue
 *   php crob.php --dump              - Debug dump
 */

require_once __DIR__ . '/src/Crob.php';

// Parse command line
$args = array_slice($argv, 1);

// Extract --verbose / -v from anywhere in the args before dispatching
$verbose = false;
$args = array_values(array_filter($args, function ($a) use (&$verbose) {
    if ($a === '--verbose' || $a === '-v') {
        $verbose = true;
        return false;
    }
    return true;
}));

$crob = new Crob(null, $verbose);

if (empty($args)) {
    // Interactive mode
    interactiveMode($crob);
    exit(0);
}

$firstArg = $args[0];

// Flags
if ($firstArg === '--stats' || $firstArg === '-s') {
    $stats = $crob->stats();
    echo "=== Crob's Nerd Stats ===\n\n";
    echo "Knowledge Brain:\n";
    echo "  Facts: {$stats['knowledge']['facts']}\n";
    echo "  Subjects: {$stats['knowledge']['subjects']}\n";
    echo "\nCuriosity Queue:\n";
    echo "  Pending: {$stats['curiosity']['queued']}\n";
    echo "  Completed: {$stats['curiosity']['completed']}\n";
    echo "\nInterests:\n";
    echo "  Established: {$stats['interests']['established']}\n";
    echo "  Emerging: {$stats['interests']['tentative']}\n";
    if (!empty($stats['interests']['top'])) {
        echo "  Top: " . implode(', ', $stats['interests']['top']) . "\n";
    }
    exit(0);
}

if ($firstArg === '--queue' || $firstArg === '-q') {
    $queue = $crob->queue();
    echo "=== Crob's Research Queue ===\n\n";
    if (empty($queue)) {
        echo "Queue is empty. This nerd has nothing to research!\n";
        echo "Ask me something to get the curiosity flowing.\n";
    } else {
        foreach ($queue as $i => $item) {
            echo ($i + 1) . ". {$item['topic']}\n";
            echo "   From: {$item['origin']} | Priority: {$item['priority']}\n";
        }
    }
    exit(0);
}

if ($firstArg === '--learn' || $firstArg === '-l') {
    echo "=== Crob Background Learning ===\n\n";
    $result = $crob->backgroundLearn();
    if (!$result) {
        echo "Nothing in the queue to learn. Ask me something first!\n";
    } else {
        echo "Learned about: {$result['topic']}\n";
        echo "Origin: {$result['origin']}\n";
        echo "Facts learned: {$result['facts_learned']}\n";
        echo "New rabbit holes: {$result['new_rabbit_holes']}\n";
        echo "Queue remaining: {$result['queue_size']}\n";
    }
    exit(0);
}

if ($firstArg === '--interests' || $firstArg === '-i') {
    echo "=== Crob's Interest Profile ===\n\n";
    $profile = $crob->analyzeInterests();

    if (empty($profile['established']) && empty($profile['tentative'])) {
        echo "Still learning... ask me things or hit Learn.\n";
        echo "Interests emerge from patterns in what I research.\n";
        exit(0);
    }

    if (!empty($profile['established'])) {
        echo "Established Interests:\n";
        foreach ($profile['established'] as $topic => $data) {
            $weight = str_pad(number_format($data['weight'], 2), 5, ' ', STR_PAD_LEFT);
            $cluster = $data['cluster'] ? " [{$data['cluster']}]" : '';
            $evidence = [];
            if ($data['density'] > 0) $evidence[] = "{$data['density']} relations";
            if ($data['recurrence'] > 0) $evidence[] = "{$data['recurrence']}x in history";
            if ($data['taught']) $evidence[] = "taught";
            $evidenceStr = implode(', ', $evidence);
            echo "  {$weight}  {$topic}{$cluster}\n";
            echo "         {$evidenceStr} | confidence: {$data['confidence']} | decay: {$data['decay']}\n";
        }
    }

    if (!empty($profile['tentative'])) {
        echo "\nEmerging (too early to tell):\n";
        foreach (array_slice($profile['tentative'], 0, 10) as $topic => $data) {
            $weight = str_pad(number_format($data['weight'], 2), 5, ' ', STR_PAD_LEFT);
            echo "  {$weight}  {$topic}\n";
        }
        if (count($profile['tentative']) > 10) {
            echo "  ... and " . (count($profile['tentative']) - 10) . " more\n";
        }
    }

    echo "\n{$profile['subjects_analyzed']} subjects analyzed.\n";
    exit(0);
}

if ($firstArg === '--dump' || $firstArg === '-d') {
    print_r($crob->dump());
    exit(0);
}

if ($firstArg === '--help' || $firstArg === '-h') {
    echo "Crob - A curious, self-learning AI\n\n";
    echo "Usage:\n";
    echo "  php crob.php                     Interactive mode\n";
    echo "  php crob.php \"question\"          Ask a single question\n";
    echo "  php crob.php --stats             Show knowledge stats\n";
    echo "  php crob.php --queue             Show research queue\n";
    echo "  php crob.php --interests         Show interest profile\n";
    echo "  php crob.php --learn             Learn next item in queue\n";
    echo "  php crob.php --dump              Debug dump\n";
    echo "  php crob.php --verbose, -v       Print per-URL learn details during research\n";
    echo "  php crob.php --help              This help\n";
    exit(0);
}

// Single question mode
$question = implode(' ', $args);
echo $crob->ask($question) . "\n";

/**
 * Interactive REPL mode
 */
function interactiveMode(Crob $crob): void
{
    echo "\n";
    echo "  ██████╗██████╗  ██████╗ ██████╗ \n";
    echo " ██╔════╝██╔══██╗██╔═══██╗██╔══██╗\n";
    echo " ██║     ██████╔╝██║   ██║██████╔╝\n";
    echo " ██║     ██╔══██╗██║   ██║██╔══██╗\n";
    echo " ╚██████╗██║  ██║╚██████╔╝██████╔╝\n";
    echo "  ╚═════╝╚═╝  ╚═╝ ╚═════╝ ╚═════╝ \n";
    echo "\n";
    echo $crob->introduce() . "\n\n";
    echo "Commands: quit, stats, queue, interests, learn, teach <topic> <fact>\n";
    echo str_repeat('-', 50) . "\n\n";

    while (true) {
        echo "You: ";
        $input = trim(fgets(STDIN));

        if (empty($input)) {
            continue;
        }

        $lower = strtolower($input);

        if ($lower === 'quit' || $lower === 'exit' || $lower === 'bye') {
            echo "\nCrob: This nerd will keep learning while you're gone. Bye!\n\n";
            break;
        }

        if ($lower === 'stats') {
            $stats = $crob->stats();
            echo "\nCrob: Nerd stats - {$stats['knowledge']['facts']} facts, ";
            echo "{$stats['knowledge']['subjects']} subjects, ";
            echo "{$stats['curiosity']['queued']} in queue.\n\n";
            continue;
        }

        if ($lower === 'queue') {
            $queue = $crob->queue();
            if (empty($queue)) {
                echo "\nCrob: My research queue is empty. I need more rabbit holes!\n\n";
            } else {
                echo "\nCrob: My curiosity queue:\n";
                foreach (array_slice($queue, 0, 5) as $item) {
                    echo "  - {$item['topic']} (from {$item['origin']})\n";
                }
                if (count($queue) > 5) {
                    echo "  ... and " . (count($queue) - 5) . " more\n";
                }
                echo "\n";
            }
            continue;
        }

        if ($lower === 'interests') {
            $profile = $crob->analyzeInterests();
            $established = $profile['established'] ?? [];
            $tentative = $profile['tentative'] ?? [];
            if (empty($established) && empty($tentative)) {
                echo "\nCrob: Still learning... interests emerge from what I research.\n\n";
            } else {
                echo "\nCrob: Here's what I'm gravitating toward:\n";
                foreach (array_slice($established, 0, 5, true) as $topic => $data) {
                    $w = number_format($data['weight'], 2);
                    echo "  * {$topic} ({$w})\n";
                }
                if (!empty($tentative)) {
                    echo "  Emerging: " . implode(', ', array_slice(array_keys($tentative), 0, 3)) . "\n";
                }
                echo "\n";
            }
            continue;
        }

        if ($lower === 'learn') {
            echo "\nCrob: *puts on learning hat*\n";
            $result = $crob->backgroundLearn();
            if (!$result) {
                echo "Nothing to learn! My queue is empty.\n\n";
            } else {
                echo "Learned about {$result['topic']}! ";
                echo "Got {$result['facts_learned']} facts and found {$result['new_rabbit_holes']} new rabbit holes.\n\n";
            }
            continue;
        }

        if (preg_match('/^teach\s+(?:me\s+that\s+)?(.+?)\s+(is|are|has|means)\s+(.+)$/i', $input, $m)) {
            $topic = $m[1];
            $fact = $m[2] . ' ' . $m[3];
            echo "\nCrob: " . $crob->teach($topic, $fact) . "\n\n";
            continue;
        }

        // Regular question
        echo "\nCrob: " . $crob->ask($input) . "\n\n";
    }
}
