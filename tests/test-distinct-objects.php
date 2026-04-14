<?php
/**
 * distinct_objects counter test.
 *
 * Verifies:
 *   - learning two distinct object strings increments counter to 2
 *   - re-learning a seen object does NOT increment the counter
 *   - confidence still bumps according to tier
 */

require_once __DIR__ . '/../src/Brain.php';

$tmpDir = __DIR__ . '/tmp-brain-data';
if (is_dir($tmpDir)) {
    array_map('unlink', glob("$tmpDir/*") ?: []);
    rmdir($tmpDir);
}
mkdir($tmpDir, 0755, true);

$brain = new Brain($tmpDir);
$failures = [];

// A: first object, source A -> distinct = 1
$r = $brain->learn('GSAP', Brain::REL_IS, 'a library', 0.5, 'https://a.example.com');
if ($r['distinct_objects'] !== 1) {
    $failures[] = "after 1st learn: expected 1 distinct, got {$r['distinct_objects']}";
}

// B: second object, source B -> distinct = 2 (ambiguous tier)
$r = $brain->learn('GSAP', Brain::REL_IS, 'an animation tool', 0.5, 'https://b.example.com');
if ($r['distinct_objects'] !== 2) {
    $failures[] = "after 2nd learn: expected 2 distinct, got {$r['distinct_objects']}";
}
if ($r['tier'] !== 'ambiguous') {
    $failures[] = "after 2nd learn: expected tier=ambiguous, got {$r['tier']}";
}

// C: existing object 'a library', new source C -> corroborated, distinct stays 2
$r = $brain->learn('GSAP', Brain::REL_IS, 'a library', 0.5, 'https://c.example.com');
if ($r['distinct_objects'] !== 2) {
    $failures[] = "after 3rd learn (corroboration): expected 2 distinct, got {$r['distinct_objects']}";
}
if ($r['tier'] !== 'corroborated') {
    $failures[] = "after 3rd learn: expected tier=corroborated, got {$r['tier']}";
}

// Verify confidence increased through the sequence (0.50 -> 0.55 ambiguous -> 0.70 corroborated)
if (abs($r['confidence'] - 0.70) > 0.005) {
    $failures[] = sprintf("after 3rd learn: expected conf=0.70, got %.2f", $r['confidence']);
}

array_map('unlink', glob("$tmpDir/*") ?: []);
rmdir($tmpDir);

if (empty($failures)) {
    echo "PASS: distinct_objects counter tracks unique objects, not sources\n";
    exit(0);
}

echo "FAIL:\n";
foreach ($failures as $f) echo "  - $f\n";
exit(1);
