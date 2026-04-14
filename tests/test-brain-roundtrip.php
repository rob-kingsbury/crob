<?php
/**
 * Brain load/save round-trip test
 *
 * Phase 1 prerequisite (Step 0 of confidence vectors plan).
 *
 * Verifies that confidence values written to .crob round-trip correctly
 * through save() and load(). After Step 1 (two-decimal format), all values
 * including 0.55, 0.65 must round-trip within 0.01.
 *
 * Before Step 1 (single-digit format), only values with .0 precision round-trip
 * exactly. The full-precision subset is the gate for Step 1.
 */

require_once __DIR__ . '/../src/Brain.php';

$tmpDir = __DIR__ . '/tmp-brain-data';

// Clean slate
if (is_dir($tmpDir)) {
    array_map('unlink', glob("$tmpDir/*") ?: []);
    rmdir($tmpDir);
}
mkdir($tmpDir, 0755, true);

// Seed values — 0.55 and 0.65 are the ones that break under single-digit format.
// Max representable confidence is 0.99 per FORMAT.md ("100% intentionally not representable").
$testCases = [
    ['subject' => 'Zero',       'conf' => 0.00, 'expect' => 0.00],
    ['subject' => 'Half',       'conf' => 0.50, 'expect' => 0.50],
    ['subject' => 'FiftyFive',  'conf' => 0.55, 'expect' => 0.55],
    ['subject' => 'SixtyFive',  'conf' => 0.65, 'expect' => 0.65],
    ['subject' => 'Ninety',     'conf' => 0.90, 'expect' => 0.90],
    ['subject' => 'NinetyNine', 'conf' => 0.99, 'expect' => 0.99],
    // 1.00 is capped to 0.99 on save per format spec
    ['subject' => 'OneCapped',  'conf' => 1.00, 'expect' => 0.99],
];

$brain = new Brain($tmpDir);
foreach ($testCases as $tc) {
    $brain->learn($tc['subject'], Brain::REL_IS, 'placeholder object', $tc['conf']);
}

// Drop and reload from disk
unset($brain);
$brain2 = new Brain($tmpDir);

$failures = [];
foreach ($testCases as $tc) {
    $result = $brain2->query($tc['subject']);
    if (!$result || empty($result['knowledge'])) {
        $failures[] = "Missing: {$tc['subject']}";
        continue;
    }
    $storedConf = $result['knowledge'][0]['conf'];
    $expected = $tc['expect'];
    $delta = abs($storedConf - $expected);
    if ($delta > 0.005) {
        $failures[] = sprintf(
            "%s: expected %.2f, got %.2f (delta %.3f)",
            $tc['subject'], $expected, $storedConf, $delta
        );
    }
}

// Cleanup
array_map('unlink', glob("$tmpDir/*") ?: []);
rmdir($tmpDir);

if (empty($failures)) {
    echo "PASS: all confidence values round-tripped within 0.005\n";
    exit(0);
}

echo "FAIL:\n";
foreach ($failures as $f) {
    echo "  - $f\n";
}
exit(1);
