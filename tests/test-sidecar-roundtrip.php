<?php
/**
 * Sidecar round-trip test.
 *
 * Verifies crob.provenance.json is written on save, parses correctly,
 * and repopulates the provenance array on load.
 */

require_once __DIR__ . '/../src/Brain.php';

$tmpDir = __DIR__ . '/tmp-brain-data';
$sidecarPath = $tmpDir . '/crob.provenance.json';

if (is_dir($tmpDir)) {
    array_map('unlink', glob("$tmpDir/*") ?: []);
    rmdir($tmpDir);
}
mkdir($tmpDir, 0755, true);

$brain = new Brain($tmpDir);

// Learn enough to populate the sidecar with distinct sources and objects
$brain->learn('GSAP', Brain::REL_IS, 'animation library', 0.5, 'https://greensock.com/docs');
$brain->learn('GSAP', Brain::REL_IS, 'animation library', 0.5, 'https://css-tricks.com/gsap');
$brain->learn('GSAP', Brain::REL_IS, 'motion toolkit', 0.5, 'https://developer.mozilla.org/gsap');

$failures = [];

if (!file_exists($sidecarPath)) {
    $failures[] = "sidecar file not created at $sidecarPath";
} else {
    $json = file_get_contents($sidecarPath);
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        $failures[] = "sidecar JSON did not decode to array";
    } elseif (!isset($decoded['GSAP'][':='])) {
        $failures[] = "sidecar missing GSAP := entry";
    } else {
        $entry = $decoded['GSAP'][':='];
        if (count($entry['sources']) !== 3) {
            $failures[] = "expected 3 sources, got " . count($entry['sources']);
        }
        if ($entry['distinct_objects'] !== 2) {
            $failures[] = "expected distinct_objects=2, got {$entry['distinct_objects']}";
        }
    }
}

// Reload and verify in-memory provenance matches
unset($brain);
$brain2 = new Brain($tmpDir);
$prov = $brain2->getProvenance('GSAP', Brain::REL_IS);

if (empty($prov)) {
    $failures[] = "reloaded provenance is empty";
} else {
    if (count($prov['sources']) !== 3) {
        $failures[] = "reloaded sources count wrong: " . count($prov['sources']);
    }
    if ($prov['distinct_objects'] !== 2) {
        $failures[] = "reloaded distinct_objects wrong: {$prov['distinct_objects']}";
    }
    if (!in_array('greensock.com', $prov['sources'], true)) {
        $failures[] = "reloaded sources missing greensock.com";
    }
}

array_map('unlink', glob("$tmpDir/*") ?: []);
rmdir($tmpDir);

if (empty($failures)) {
    echo "PASS: sidecar round-trips through save() and load()\n";
    exit(0);
}

echo "FAIL:\n";
foreach ($failures as $f) echo "  - $f\n";
exit(1);
