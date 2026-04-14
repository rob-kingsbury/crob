<?php
/**
 * Three-tier confidence logic test.
 *
 * Verifies that learn() correctly distinguishes:
 *   - new (first time subject+relation seen)
 *   - corroborated (new domain, existing object) +0.15
 *   - ambiguous (new domain, new object) +0.05
 *   - restatement (same domain OR same object, no new signal) +0.05
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

function check(string $label, array $result, string $expectTier, float $expectConf, array &$failures): void {
    if ($result['tier'] !== $expectTier) {
        $failures[] = "$label: expected tier=$expectTier, got {$result['tier']}";
    }
    if (abs($result['confidence'] - $expectConf) > 0.005) {
        $failures[] = sprintf("$label: expected conf=%.2f, got %.2f", $expectConf, $result['confidence']);
    }
}

// Step 1: new fact from greensock.com
$r = $brain->learn('GSAP', Brain::REL_IS, 'animation library', 0.5, 'https://greensock.com/docs');
check('new', $r, 'new', 0.50, $failures);

// Step 2: same domain, same object -> restatement (+0.05)
$r = $brain->learn('GSAP', Brain::REL_IS, 'animation library', 0.5, 'https://greensock.com/tutorials');
check('restatement (same domain same obj)', $r, 'restatement', 0.55, $failures);

// Step 3: new domain, same object -> corroborated (+0.15)
$r = $brain->learn('GSAP', Brain::REL_IS, 'animation library', 0.5, 'https://css-tricks.com/gsap');
check('corroborated (new domain same obj)', $r, 'corroborated', 0.70, $failures);

// Step 4: new domain, new object -> ambiguous (+0.05)
$r = $brain->learn('GSAP', Brain::REL_IS, 'motion toolkit', 0.5, 'https://developer.mozilla.org/gsap');
check('ambiguous (new domain new obj)', $r, 'ambiguous', 0.75, $failures);

// Step 5: already-seen domain, new object -> restatement (no new source signal)
$r = $brain->learn('GSAP', Brain::REL_IS, 'sequencing tool', 0.5, 'https://greensock.com/more');
check('restatement (seen domain new obj)', $r, 'restatement', 0.80, $failures);

// Cleanup
array_map('unlink', glob("$tmpDir/*") ?: []);
rmdir($tmpDir);

if (empty($failures)) {
    echo "PASS: three-tier logic produces correct tier and confidence for all cases\n";
    exit(0);
}

echo "FAIL:\n";
foreach ($failures as $f) echo "  - $f\n";
exit(1);
